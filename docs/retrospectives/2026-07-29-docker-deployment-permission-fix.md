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

## 后续补充：空 vendor 卷兼容

首次修复部署到线上后，又出现以下错误：

```text
grep: .env: No such file or directory
Failed opening required '/var/www/html/vendor/autoload.php'
```

进一步确认存在两个边界场景：

1. 上一次失败部署已经创建了空的 `vendor-data`。Docker 只在命名卷首次创建时执行镜像目录的 copy-up，已经存在的空卷不会在重建镜像后自动补入依赖。
2. 部分服务器部署平台可能重写容器启动参数，不能只依赖 Dockerfile 中隐式继承的 ENTRYPOINT。

最终增加两层保障：

- 镜像构建完成后，将生产依赖额外保存到不受源码挂载影响的 `/opt/vendor`。
- 入口脚本发现 `/var/www/html/vendor/autoload.php` 不存在时，主动从 `/opt/vendor` 恢复依赖。
- Compose 的 `app`、`queue-worker`、`scheduler` 均显式声明 `/usr/local/bin/laravel-entrypoint`。

因此，即使服务器复用一个已存在的空 `vendor-data`，容器也会自行修复，而不需要删除数据库等其他数据卷。

## 后续补充：migration 完成但 seed 未执行

### 问题现象

容器、MySQL 和登录页面都能正常打开，但使用文档中的演示管理员仍提示“登录信息有误”：

```text
admin@saas.test / password
```

数据库检查结果是 migration 已执行、业务表已经创建，但 `platform_users`、`tenants` 等表为空。

### 根因

原应用启动命令只包含：

```bash
php artisan migrate --force
```

Migration 只负责创建或升级表结构，不会自动执行 `DatabaseSeeder`。因此 `PlatformAdminSeeder` 没有创建平台管理员，登录认证必然失败。

也不能简单地在每次容器启动时无条件执行完整 `db:seed`。现有 Seeder 使用 `updateOrCreate`，重复执行可能覆盖管理员密码或演示数据；容器重启不应改变已经投入使用的账号。

### 解决方案

新增以下文件：

- `docker/start-app.sh`：集中管理应用启动顺序。
- `database/seeders/DeploymentSeeder.php`：执行一次性部署数据初始化。
- `deployment_seed_runs` migration：持久化 Seeder 版本完成标记。

最终启动顺序调整为：

```text
生成 APP_KEY（仅缺失时）
  -> package:discover
  -> migrate --force
  -> DeploymentSeeder
  -> 启动 Octane
```

`DeploymentSeeder` 使用 `default-demo-data-v1` 作为版本标记：

1. 标记不存在时，在数据库事务中调用完整 `DatabaseSeeder`。
2. 所有 Seeder 成功后才写入 `deployment_seed_runs`。
3. 中途失败时事务回滚且不写标记，下次启动可以安全重试。
4. 标记存在时直接跳过，避免容器重启反复覆盖账号和数据。

Compose 默认启用首次初始化：

```yaml
DB_SEED_ON_STARTUP: ${DB_SEED_ON_STARTUP:-true}
```

不需要演示数据的正式环境可以在启动前设置：

```bash
export DB_SEED_ON_STARTUP=false
```

此开关只控制 Seeder，migration 仍会照常执行。

### 验证标准

全新数据库启动后应满足：

- `deployment_seed_runs` 存在 `default-demo-data-v1` 记录。
- `platform_users` 中存在 `admin@saas.test`。
- `admin@saas.test / password` 可以登录平台后台。
- 再次重启容器时日志显示部署 Seed 已完成并跳过。
- 修改管理员资料后重启容器，资料不会被 Seeder 覆盖。

## 后续补充：Vite manifest 缺失

### 问题现象

平台登录后打开 Vue 数据面板出现 HTTP 500：

```text
Illuminate\Foundation\ViteManifestNotFoundException
Vite manifest not found at: /var/www/html/public/build/manifest.json
```

### 根因

部署包按照 `.gitignore` 排除了 `public/build` 和 `node_modules`，这是正确的源码归档策略，但原 Dockerfile 只安装 PHP 依赖，没有执行 `pnpm build`。因此 Blade 模板执行 `@vite(...)` 时找不到构建清单。

不能依赖开发机器提前生成并上传 `public/build`，否则构建结果容易和当前源码、Node 版本或锁文件不一致。

### 解决方案

Dockerfile 增加 Node 多阶段构建：

```text
node:22-alpine
  -> pnpm install --frozen-lockfile
  -> pnpm build
  -> 生成 /app/public/build
  -> 复制到 PHP 镜像 /opt/public-build
```

`/opt/public-build` 不受 `/var/www/html` 源码 bind mount 影响。入口脚本每次启动都会将当前镜像中的构建产物同步到 `/var/www/html/public/build`，并在镜像缺少 `manifest.json` 时立即报出明确错误。

这样部署包仍然可以排除 `.gitignore` 内容，同时保证镜像构建结果包含与源码和 `pnpm-lock.yaml` 匹配的前端资源。

### 同步修复 .env 权限

入口脚本原本只为自己新建的 `.env` 调整权限。如果服务器上已存在由其他 UID 创建且权限为 `600` 的 `.env`，降权后的 Laravel 进程仍无法读取。

现在入口脚本保留 `.env` 的宿主机所有者，将所属组设置为容器内的 `laravel`，并使用 `640` 权限：

```text
文件所有者：可读写
laravel 组：只读
其他用户：无权限
```

因此不再需要手工执行 `chown 1000:1000 .env`，同时避免把服务器上的 `.env` 所有者强制改成容器 UID。
