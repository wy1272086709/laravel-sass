# Docker 线上部署权限故障复盘

日期：2026-07-29

## 故障现象

项目压缩包上传服务器并启动 Docker Compose 后，依次出现：

```text
/var/www/html/vendor does not exist and could not be created
cp: cannot create regular file '.env': Permission denied
Class "Laravel\Pail\PailServiceProvider" not found
```

部署包按 `.gitignore` 排除了 `.env`、`vendor`、`node_modules` 和 `public/build`。

## 根因

### 容器用户没有挂载目录写权限

服务器通常以 `root` 解压代码，而镜像原本通过 `USER laravel` 直接以普通用户启动。Compose 将宿主机目录绑定挂载到 `/var/www/html` 后，`laravel` 用户无法创建 `.env`、`vendor` 和 Laravel 缓存文件。

Dockerfile 构建阶段的 `chown /var/www/html` 无法解决此问题，因为 bind mount 会在容器启动时覆盖镜像中该目录的内容和权限。

### 镜像内 vendor 被挂载遮蔽

即使 Dockerfile 已执行 `composer install`，下面的源码挂载仍会遮蔽镜像中的 `vendor`：

```yaml
- ..:/var/www/html
```

因此，运行时依赖不能只放在被 bind mount 覆盖的目录中。

### 开发缓存引用生产环境未安装的包

宿主机 `bootstrap/cache/packages.php` 由包含开发依赖的环境生成。生产镜像通过 `--no-dev` 排除了 Laravel Pail，但旧缓存仍引用 `PailServiceProvider`，导致应用无法启动。

### Composer 下载缺少重试

镜像构建期间曾遇到 GitHub HTTP/2 连接提前关闭。原命令没有重试，一次临时网络故障就会中断发布。

## 修复方案

### 入口脚本

新增 `docker/entrypoint.sh`：

1. 以 root 创建 `.env` 和必需的可写目录。
2. 只调整 `vendor`、`storage`、`bootstrap/cache` 和 Composer 缓存权限。
3. 清除 `bootstrap/cache` 中可重新生成的 PHP 缓存。
4. 使用 `setpriv` 降权，以 `laravel` 用户运行应用命令。

入口脚本只在 `.env` 不存在时从 `.env.example` 创建，不覆盖服务器已有配置。初始化完成后，Octane 主进程 UID/GID 均为 1000，不会长期以 root 运行。

### vendor 独立命名卷

Compose 增加：

```yaml
- vendor-data:/var/www/html/vendor
```

该卷避免镜像中的生产依赖被源码 bind mount 遮蔽，并由 `app`、`queue-worker`、`scheduler` 共用。

### 构建与启动流程

Dockerfile 在构建阶段执行：

```bash
composer install --no-dev --no-scripts --no-progress --optimize-autoloader
```

失败后最多重试三次。由于构建该层时没有完整 Laravel 源码，因此禁用 Composer scripts，待源码挂载完成后再执行 package discovery。

最终启动顺序：

```text
入口脚本初始化目录和权限
  -> 必要时创建 .env
  -> 清理旧 package cache
  -> 降权到 laravel
  -> 必要时生成 APP_KEY
  -> package:discover
  -> migrate --force
  -> 启动 Octane
```

队列与调度容器在应用健康检查通过后启动。

## 验证

使用最终配置执行：

```bash
docker compose -f docker/docker-compose.yml build app
docker compose -f docker/docker-compose.yml up -d --force-recreate
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs --tail=200 app queue-worker scheduler
```

验证结果：

- `app`、`mysql`、`redis` 状态 healthy。
- `queue-worker`、`scheduler` 正常运行。
- `/up` 健康检查返回 HTTP 200。
- 不再出现 `.env`、`vendor` 和缓存目录权限错误。
- 不再出现 `PailServiceProvider` 缺失错误。
- PHP 主进程以 UID/GID 1000 运行。

## 服务器发布步骤

```bash
docker compose -f docker/docker-compose.yml down
docker compose -f docker/docker-compose.yml up -d --build
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs --tail=200 app queue-worker scheduler
```

不要在线上随意执行 `docker compose down -v`。`-v` 会删除 Compose 管理的命名卷，包括 MySQL 数据卷，可能造成不可恢复的数据丢失。

## 后续生产加固

本次改动解决了启动和权限故障，但当前 Compose 仍偏向开发环境，后续应继续处理：

- 使用 `APP_ENV=production` 并关闭 `APP_DEBUG`。
- 移除 Compose 中的示例数据库和 Redis 密码，改用环境变量或 Secret。
- 配置正式 `APP_URL`、HTTPS 和可信代理。
- 在 CI 或镜像构建阶段生成 `public/build` 前端产物。
- 数据库迁移前备份，并明确自动 migration 的发布策略。
- 拆分开发与生产 Compose，避免环境配置混用。

## 经验总结

1. Dockerfile 中的权限不等于 bind mount 后的权限。
2. 依赖目录与源码挂载重叠时，应使用嵌套命名卷或取消生产源码挂载。
3. 初始化可以短暂使用 root，业务进程应立即降权。
4. Laravel 的 `bootstrap/cache` 属于环境相关生成物，跨环境部署应重新生成。
5. 外部依赖下载应有限重试，不能无限重试掩盖真实故障。
6. 线上操作命名卷前必须确认是否包含数据库持久化数据。
