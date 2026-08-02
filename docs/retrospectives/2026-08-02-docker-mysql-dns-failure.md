# Docker MySQL 服务名解析故障复盘

日期：2026-08-02

## 故障现象

访问远程平台登录接口时，Laravel 返回：

```text
SQLSTATE[HY000] [2002]
php_network_getaddresses: getaddrinfo for mysql failed: Name or service not known
(Connection: mysql, SQL: select * from `platform_users` ...)
```

异常页面将调用位置显示为：

```text
OctaneTenantCleanupMiddleware.php:24
```

该中间件只是调用后续请求处理器的外层入口，不是故障根因。实际失败发生在 Laravel 第一次连接数据库、查询 `platform_users` 时。

## 已确认的故障层级

应用使用以下数据库配置：

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
```

其中 `mysql` 不是公网域名，也不是宿主机 DNS 中的主机名，而是 Docker Compose 的服务名。正常情况下，同一个 Compose 网络内的容器通过 Docker 内置 DNS 将它解析为 MySQL 容器 IP：

```text
Laravel app
  -> Docker DNS 查询 mysql
  -> 得到 MySQL 容器 IP
  -> 建立 TCP 3306 连接
  -> MySQL 用户认证
  -> 执行 SQL
```

本次错误发生在第二步：

```text
getaddrinfo for mysql failed
```

因此可以排除：

- SQL 语句错误；
- `platform_users` 表不存在；
- MySQL 用户名或密码错误；
- 租户中间件查询条件错误；
- MySQL 拒绝连接。

如果是密码错误，通常会看到 `Access denied`；如果 DNS 正常但端口未监听，通常会看到 `Connection refused` 或连接超时。本次连 MySQL 容器地址都没有解析出来。

## 为什么之前正常

`DB_HOST=mysql` 只有在以下条件同时满足时才有效：

1. 应用运行在 Docker 容器内；
2. 应用容器和 MySQL 容器位于同一个 Compose 网络；
3. Compose 中确实存在名为 `mysql` 的服务端点；
4. Docker 内置 DNS 工作正常。

之前能够登录，说明请求发生时上述条件成立。后来首次出现异常，说明运行环境发生了变化，而不是 `DB_HOST=mysql` 这个值突然失效。

可能触发相同错误的运行状态包括：

- MySQL 容器退出或被手动停止，服务端点从 Compose 网络消失；
- Docker daemon 或服务器重启后，只恢复了应用容器，没有恢复 MySQL；
- 应用被改为直接在宿主机运行，但仍使用容器内专用主机名 `mysql`；
- 应用容器被启动到另一个 Docker 网络；
- 部署过程中单独重建容器，造成短暂的网络或 DNS 服务端点缺失。

由于故障发生在远程服务器，现有工作区没有当时的 `docker events`、容器退出码和服务日志，无法严谨地确认是哪一个外部事件触发。可以确认的是：原 Compose 配置允许 MySQL 停止后不再自动恢复，同时应用健康检查无法发现数据库已经不可达。这两个缺口会使上述短暂事件扩大成持续故障。

## 原配置缺口

### MySQL、Redis 和 Web 没有重启策略

原配置只有 `queue-worker` 和 `scheduler` 使用：

```yaml
restart: unless-stopped
```

`app`、`mysql` 和 `redis` 没有重启策略。MySQL 进程异常退出或 Docker daemon 重启后，它们不具备与 Worker 相同的自动恢复保障。

`depends_on` 不能解决运行期故障。它只约束 Compose 启动阶段的先后顺序：

```yaml
depends_on:
  mysql:
    condition: service_healthy
```

应用启动完成后，如果 MySQL 再退出，Compose 不会因为 `depends_on` 自动停止或重启应用。

### `/up` 没有检查外部依赖

原健康检查为：

```yaml
test: ["CMD", "curl", "--fail", "--silent", "http://127.0.0.1:8000/up"]
```

Laravel `/up` 只证明 Octane 能返回 HTTP 响应，不证明 MySQL 和 Redis 可访问。因此可能出现：

```text
Octane 正常 -> /up 返回 200 -> app 显示 healthy
MySQL 已停止 -> 所有业务查询返回 500
```

这是一种错误健康状态。

## 修复方案

### 为关键服务增加自动重启

为 `app`、`mysql` 和 `redis` 增加：

```yaml
restart: unless-stopped
```

该策略表示：容器因异常退出或 Docker daemon 重启时自动恢复；只有运维人员明确执行 stop 后才保持停止。

### 增加依赖感知健康检查

新增 `docker/healthcheck.php`，从应用容器内检查：

- `127.0.0.1:8000`：Octane 端口；
- `${DB_HOST}:${DB_PORT}`：MySQL DNS 与 TCP 端口；
- `${REDIS_HOST}:${REDIS_PORT}`：Redis DNS 与 TCP 端口。

Compose 改为：

```yaml
healthcheck:
  test: ["CMD", "php", "/var/www/html/docker/healthcheck.php"]
```

该检查解决的是运行状态识别问题。它不会替代数据库账号认证，也不会仅靠 `unhealthy` 自动重启容器；实际恢复主要依赖数据库自身的 `restart` 策略，健康状态用于阻止依赖服务过早启动，并向运维明确暴露故障。

## 本地验证结果

应用修复后执行完整容器重建，未删除命名卷：

```bash
docker compose -f docker/docker-compose.yml up -d --force-recreate
```

验证结果：

```text
app             healthy
mysql           healthy
redis           healthy
queue-worker    running
scheduler       running
```

进一步从应用容器验证：

```text
mysql 服务名解析成功
SELECT 1 成功
admin@saas.test 查询成功
GET /platform/login 返回 HTTP 200
```

说明 Compose DNS、MySQL 连接和 Laravel 查询链路均已恢复。

## 远程服务器排查命令

再次出现相似问题时，应先保留现场，再决定是否重启：

```bash
docker compose -f docker/docker-compose.yml ps -a
docker compose -f docker/docker-compose.yml logs --tail=200 mysql app
docker inspect laravelproj-mysql --format '{{.State.Status}} {{.State.ExitCode}} {{.State.Error}}'
docker compose -f docker/docker-compose.yml exec app getent hosts mysql
docker compose -f docker/docker-compose.yml exec app php -r 'echo gethostbyname("mysql"), PHP_EOL;'
```

判断方法：

```text
ps 中没有 mysql       -> MySQL 服务未创建或使用了错误 Compose 项目
mysql 为 Exited        -> 查看 ExitCode 和日志定位退出原因
getent 无输出          -> Docker DNS/网络/服务端点问题
解析成功但端口不通     -> MySQL 未就绪、已崩溃或网络规则问题
端口通但 Access denied -> 数据库凭据或用户授权问题
```

如果应用实际运行在宿主机而不是 Compose 网络内，不能使用 `DB_HOST=mysql`，应改成宿主机可访问的地址和映射端口，例如 `127.0.0.1:3307`。容器内仍应使用 `mysql:3306`，不能把两种运行环境混用。

## 远程发布步骤

部署本次修复后执行：

```bash
docker compose -f docker/docker-compose.yml up -d --build --force-recreate
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs --tail=200 app mysql redis
```

不要执行：

```bash
docker compose down -v
```

`-v` 会删除 MySQL 和 Redis 命名卷，可能造成不可恢复的数据丢失。

## 后续建议

1. 生产环境关闭 `APP_DEBUG`，避免数据库地址、SQL、代码路径和调用栈直接暴露给公网用户。
2. 将开发与生产 Compose 分开，密码通过服务器环境变量或 Secret 注入。
3. 对 MySQL 数据卷做定期备份，并记录容器退出码和 Docker daemon 重启事件。
4. 接入外部存活监控和业务就绪监控，区分“进程活着”和“依赖可用”。
5. 对数据库短暂不可用设置告警；不要在应用层无限重试，避免故障期间进一步放大压力。

## 经验总结
1. `DB_HOST=mysql` 是 Docker 网络内的服务发现名称，不是固定 IP 或公共 DNS。
2. `depends_on` 只管理启动顺序，不负责运行期依赖恢复。
3. HTTP 进程存活不等于业务可用，健康检查必须覆盖关键依赖。
4. `getaddrinfo failed` 应先排查容器、网络和 DNS，不应从 SQL 或中间件逻辑入手。
5. 没有故障现场日志时，应明确区分已确认根因层级和可能触发事件。
