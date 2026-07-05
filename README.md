# 多商户 SaaS 电商开放中台

PHP 8.3 · Laravel 11 · Filament v3 · Vue3 · Octane/Swoole · Redis · MySQL 8

## 设计文档

| 文档 | 路径 |
|------|------|
| SDD 软件设计文档 | [docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md](docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md) |
| 五层架构说明 | [docs/architecture/layered-architecture.md](docs/architecture/layered-architecture.md) |
| 数据库 / 迁移 / Model / Factory | [docs/database/schema-overview.md](docs/database/schema-overview.md) |
| OpenAPI 3.0 规范 | [docs/api/openapi.yaml](docs/api/openapi.yaml) |
| OpenSpec 规范源 | [openspec/](openspec/)（project / agreements / specs / changes） |

## UI 参考素材

`截图文件/` — 平台管理后台 + 商户管理后台 + 共用登录页

## 启动与开发

```bash
composer install                      # 安装依赖（首次）
cp .env.example .env                  # 生成环境配置
php artisan key:generate              # 应用密钥

php artisan serve                     # http://127.0.0.1:8000
# 双后台：/platform/login  ·  /merchant/login（阶段 1 仅登录页可渲染，账号/迁移在阶段 2）

php artisan test                      # Pest 测试套件（阶段 1：34 项全绿）
```

> 阶段 1 默认 SQLite（无需启动 MySQL）；Redis 用本机 6379（predis）。接 MySQL 时切 `.env` 的 DB 块。

