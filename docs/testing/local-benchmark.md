# 阶段 7 本地压测记录

## 目标

验证本地 Redis、数据库、开放 API 限流和平台内部面板接口在演示环境下可稳定运行，并提供可重复执行的 baseline。

## 环境准备

```bash
composer install
pnpm install
php artisan migrate:fresh --seed
pnpm build
```

确认 Redis 可连接。测试环境使用 `CACHE_STORE=array` 和同步队列；真实压测建议使用 `.env` 中的 MySQL、Redis 与 Octane/Swoole。

## Baseline 测试

阶段 7 提供一个不依赖外部压测工具的 Pest baseline：

```bash
php artisan test tests/Performance/ApiRateLimitBenchTest.php
```

当前本机记录：

```text
Stage 7 API rate-limit baseline: 25 requests, 4.67-6.33 ms avg
```

说明：
- 请求路径：`GET /api/v1/products?per_page=3`
- 覆盖链路：`ApiAuthMiddleware`、`ApiRateLimitMiddleware`、`ApiDailyCounter`、租户商品查询
- 验收重点：请求全部成功，Redis 日计数等于请求次数

## Octane 压测步骤

阶段 8-1 新增了本地 HTTP benchmark 命令，可同时用于 `artisan serve` 与 Octane：

```bash
php artisan benchmark:local-api \
  --base-url=http://127.0.0.1:8000 \
  --target='/api/v1/products?per_page=10' \
  --requests=50
```

该命令会使用演示 API Key 自动换取 token，并输出 `avg_ms`、`p95_ms`、`error_rate`。设计说明见 `docs/architecture/local-benchmark-design.md`。

启动服务：

```bash
php artisan octane:start --server=swoole --host=127.0.0.1 --port=8000
```

获取 token：

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"app_key":"AK_DEMO_MHT-10001","app_secret":"secret"}' \
  | php -r '$json=json_decode(stream_get_contents(STDIN), true); echo $json["data"]["access_token"] ?? "";')
```

核心目标路径：

| 路径 | 方法 | 说明 |
|------|------|------|
| `/api/v1/auth/token` | POST | API 密钥换 token |
| `/api/v1/products` | GET | 商品查询 + 限流 |
| `/api/v1/orders` | POST | 下单 + 库存扣减 |
| `/api/internal/platform/api-monitor` | GET | 平台内部 API 监控面板数据 |

如果本机安装了 `ab`：

```bash
ab -n 200 -c 20 -H "Authorization: Bearer $TOKEN" \
  http://127.0.0.1:8000/api/v1/products
```

如果本机安装了 `hey`：

```bash
hey -n 200 -c 20 -H "Authorization: Bearer $TOKEN" \
  http://127.0.0.1:8000/api/v1/products
```

内部面板接口需要平台后台 session。更适合用浏览器登录 `admin@saas.test` 后在 Network 面板观察，或由 Laravel feature test 验证：

```bash
php artisan test tests/Feature/Internal
```

## 记录模板

```text
日期：
Git commit：
PHP：
数据库：
Redis：
服务模式：artisan serve / Octane Swoole
目标路径：
请求数：
并发数：
平均响应：
P95：
错误率：
备注：
```
