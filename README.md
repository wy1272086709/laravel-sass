# 多商户 SaaS 电商开放中台

PHP 8.3 · Laravel 11 · Filament v3 · Vue 3 · Echarts · Octane/Swoole · Redis · MySQL 8

这是一个面向多商户的 SaaS 电商开放中台，包含平台总后台、商户后台、开放 API、API 配额限流、队列任务、月结账单、风控对账和 Vue3 可视化面板。

## 快速启动

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
pnpm build
php artisan serve
```

本地默认地址：

| 入口 | 地址 |
|------|------|
| 平台后台 | `http://127.0.0.1:8000/platform/login` |
| 商户后台 | `http://127.0.0.1:8000/merchant/login` |
| 开放 API | `http://127.0.0.1:8000/api/v1` |

## 演示账号

| 类型 | 账号 | 密码 |
|------|------|------|
| 平台管理员 | `admin@saas.test` | `password` |
| 基础版商户 | `merchant-basic@saas.test` | `password` |
| 专业版商户 | `merchant-pro@saas.test` | `password` |
| 企业版商户 | `merchant-enterprise@saas.test` | `password` |

演示 API 密钥：

| 商户 | App Key | App Secret |
|------|---------|------------|
| MHT-10001 | `AK_DEMO_MHT-10001` | `secret` |
| MHT-10002 | `AK_DEMO_MHT-10002` | `secret` |
| MHT-10003 | `AK_DEMO_MHT-10003` | `secret` |

## 开放 API 示例

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"app_key":"AK_DEMO_MHT-10001","app_secret":"secret"}'
```

拿到 `data.access_token` 后访问商品列表：

```bash
curl http://127.0.0.1:8000/api/v1/products \
  -H 'Authorization: Bearer <access_token>'
```

## 测试与构建

```bash
php artisan test
php artisan test tests/Feature/Smoke tests/Performance/ApiRateLimitBenchTest.php
php artisan migrate:fresh --seed
pnpm build
```

阶段 7 增加了封板冒烟测试：

| 测试 | 覆盖 |
|------|------|
| `tests/Feature/Smoke/PlatformSmokeTest.php` | 平台后台核心页面 |
| `tests/Feature/Smoke/ApiSmokeTest.php` | Auth / Products / Orders / Bills / Dashboard API 主链路 |
| `tests/Performance/ApiRateLimitBenchTest.php` | API 限流链路可重复 baseline |

## 队列与调度

开发环境默认可用同步队列跑测试。需要观察调度时可执行：

```bash
php artisan schedule:list
php artisan queue:work
```

关键 Job：

| Job | 说明 |
|-----|------|
| `CloseExpiredOrderJob` | 关闭超时未支付订单 |
| `MonthlyBillingJob` | 月度账单生成 |
| `GenerateApiBillJob` | 单租户 API 费用重算 |
| `ApiUsageFlushJob` | Redis API 日计数落库 |
| `RiskRuleScanJob` | 风控规则扫描 |

## Octane 本地运行

确认 Redis、数据库和 Swoole 扩展可用后：

```bash
php artisan octane:start --server=swoole --host=127.0.0.1 --port=8000
```

压测步骤见 [docs/testing/local-benchmark.md](docs/testing/local-benchmark.md)。

## 文档

| 文档 | 路径 |
|------|------|
| 软件设计文档 | [docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md](docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md) |
| 五层架构说明 | [docs/architecture/layered-architecture.md](docs/architecture/layered-architecture.md) |
| 队列设计 | [docs/architecture/queue-design.md](docs/architecture/queue-design.md) |
| 账单设计 | [docs/architecture/billing-design.md](docs/architecture/billing-design.md) |
| Vue 面板设计 | [docs/architecture/vue-panels-design.md](docs/architecture/vue-panels-design.md) |
| 数据库结构 | [docs/database/schema-overview.md](docs/database/schema-overview.md) |
| 对外 OpenAPI | [docs/api/openapi.yaml](docs/api/openapi.yaml) |
| 内部面板 OpenAPI | [docs/api/internal.yaml](docs/api/internal.yaml) |
| 本地压测 | [docs/testing/local-benchmark.md](docs/testing/local-benchmark.md) |
| OpenSpec 规范源 | [openspec/](openspec/) |
