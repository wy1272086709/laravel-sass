# 阶段 8-1 本地压测设计

## 目标

阶段 8-1 用于补齐封板后的实机压测链路。目标不是替代专业压测平台，而是让本机可以稳定复现以下能力：

- 对 `artisan serve` 与 Octane 使用同一套压测入口。
- 自动使用演示 API Key 换取 AccessToken。
- 输出平均耗时、P95、错误率。
- 当本机缺少 Swoole 时，仍能保留可执行的 HTTP benchmark 命令和 Pest baseline。

## 命令

```bash
php artisan benchmark:local-api
```

常用参数：

```bash
php artisan benchmark:local-api \
  --base-url=http://127.0.0.1:8000 \
  --app-key=AK_DEMO_MHT-10001 \
  --app-secret=secret \
  --target='/api/v1/products?per_page=10' \
  --requests=50
```

默认目标路径是 `/api/v1/products`，覆盖：

```text
HTTP Client
  -> POST /api/v1/auth/token
  -> GET /api/v1/products
  -> ApiAuthMiddleware
  -> ApiRateLimitMiddleware
  -> ApiDailyCounter
  -> ProductController
```

## 运行模式

### artisan serve

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan benchmark:local-api --requests=50
```

### Octane Swoole

```bash
php artisan octane:start --server=swoole --host=127.0.0.1 --port=8000
php artisan benchmark:local-api --requests=200
```

## 指标说明

| 指标 | 说明 |
|------|------|
| `avg_ms` | 所有请求平均耗时 |
| `p95_ms` | 排序后 95% 分位耗时 |
| `error_rate` | 非 2xx 或连接失败请求占比 |

## 当前环境记录

2026-07-05 本机 `php -m` 未列出 `swoole`，`php artisan octane:status` 显示 Octane 未运行。因此本阶段先交付：

- `benchmark:local-api` 命令
- 命令级测试
- `tests/Performance/ApiRateLimitBenchTest.php` baseline
- Octane 实机压测步骤

安装 Swoole 后可直接按本文件命令补实机压测结果。
