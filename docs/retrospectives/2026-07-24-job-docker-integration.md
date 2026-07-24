# 五个 Job 接入与 Docker 运行复盘

日期：2026-07-24

## 背景

项目中已有以下五个异步任务：

- `CloseExpiredOrderJob`：关闭超过支付时限的待支付订单。
- `GenerateApiBillJob`：按租户和账期生成 API 账单。
- `InventoryAlertJob`：检查低库存并写入任务执行日志。
- `MerchantWelcomeEmailJob`：租户创建后发送商户欢迎通知。
- `SyncLogisticsJob`：订单发货后同步物流系统。

这些类描述了业务意图，但仅有 Job 类不代表功能已经接入。完整链路至少需要业务触发点、队列生产、Worker 消费、定时调度、失败处理和外部服务适配。

## 问题一：Job 存在，但没有 dispatch 触发点

### 影响

五个 Job 虽然可以被单独调用，但正常业务流程不会把它们投递到队列。订单创建后不会自动安排超时关闭和库存检查，租户创建后不会触发欢迎通知，订单发货后不会同步物流，账单也没有可用的业务入口触发生成。

这种状态容易造成“功能已经写完”的错觉：类和 `handle()` 都存在，但线上实际没有任何消息进入队列。

### 根因

实现只覆盖了任务执行逻辑，没有把任务与领域事件或应用服务入口连接起来，也缺少对 dispatch 行为的集成测试。

### 修复

在对应业务成功提交后接入任务投递：

- 创建订单后延迟投递 `CloseExpiredOrderJob`，并投递 `InventoryAlertJob`。
- 创建租户后投递 `MerchantWelcomeEmailJob`。
- 订单状态变为已发货后投递 `SyncLogisticsJob`。
- 为账单生成提供受权限约束的后台操作入口，投递 `GenerateApiBillJob`。
- 涉及数据库事务时使用提交后投递，避免 Worker 读取到尚未提交或最终已回滚的数据。

### 验证方式

- 使用 `Queue::fake()` 验证各业务入口会投递正确的 Job、参数和延迟时间。
- 验证事务失败时不会残留队列消息。
- 使用 Redis 队列实际创建订单、租户及修改发货状态，确认消息被 Worker 消费。
- 检查 `failed_jobs` 和应用日志，确认没有静默失败。

### 后续行动

- 优先考虑用领域事件统一 API 与 Filament 后台的触发逻辑，避免多个入口遗漏或重复 dispatch。
- 为任务增加幂等键或唯一任务约束，防止重复提交造成重复通知、重复同步或重复出账。
- 补充失败重试、退避策略和失败告警。

## 问题二：Docker 缺少 Queue Worker 和 Scheduler

### 影响

Docker Compose 原先只有 `app`、`mysql`、`redis`。应用可以向 Redis 投递任务，但没有 Worker 消费；Laravel 定时任务也没有进程持续调用调度器。因此即时 Job 会一直积压，定时 Job 永远不会入队。

### 根因

容器化只覆盖了 Web 请求链路，遗漏了 Laravel 应用的两个常驻运行角色：

- `php artisan queue:work`：消费队列。
- `php artisan schedule:work`：驱动定时调度。

Redis 只是队列存储，不会主动执行 PHP Job。

### 修复

在 Compose 中为应用增加独立的 Worker 和 Scheduler 服务，共用应用代码、PHP/Swoole 镜像、环境变量及依赖服务。Web、Worker、Scheduler 分进程运行，便于独立重启、扩容和观察。

### 验证方式

- `docker compose ps` 应同时显示 Web、Worker、Scheduler、MySQL、Redis 处于运行状态。
- 投递测试 Job 后，Redis 队列长度应下降，日志中应出现执行结果。
- 执行 `php artisan schedule:list` 核对任务及下次执行时间。
- 查看 Scheduler 日志，确认到点投递；再查看 Worker 日志，确认任务被消费。
- 人工触发一次短周期测试任务，验证“调度 -> 入队 -> 消费”全链路，而不只验证进程存活。

### 后续行动

- 为 Worker 设置合理的 `--tries`、`--timeout`、内存限制及优雅退出策略。
- 在部署或代码更新后执行 Worker 重启，使常驻进程加载新代码。
- 增加队列积压量、失败任务数、任务耗时和 Scheduler 心跳监控。

## 问题三：常驻 Scheduler 会冻结构造时计算的日期

### 影响

若在 `routes/console.php` 中写成：

```php
Schedule::job(new ApiUsageFlushJob(now()->subDay()->toDateString()))
    ->dailyAt('00:05');
```

使用 `schedule:work` 时，调度定义由常驻进程启动时加载。传给 Job 的日期可能一直是 Scheduler 启动当天计算出的“昨天”，后续每天仍处理同一个日期，造成 API 用量漏结算或重复处理。

### 根因

把“每次执行时变化的参数”放在调度注册阶段计算，混淆了调度定义时间和任务执行时间。短生命周期的 `schedule:run` 每分钟重载配置时不容易暴露该问题，而 `schedule:work` 会放大它。

### 修复

调度时不要固化动态日期，由 `ApiUsageFlushJob::handle()` 在真正执行时计算目标日期；或者调度闭包在触发时创建 Job 并传入当时计算的日期。当前场景优先采用 Job 内部计算，使任务手动执行、重试和定时执行保持一致。

### 验证方式

- 冻结系统时间，验证 Job 在不同执行日期计算出各自正确的前一天。
- 保持同一 Scheduler 进程运行，跨越两个调度周期，确认处理日期随执行日变化。
- 对用量汇总增加唯一约束或幂等更新，重复执行同一日期不应产生重复账目。

### 后续行动

- 审查所有 Schedule 定义，查找注册阶段的 `now()`、`today()`、随机值及数据库查询。
- 时间相关测试明确应用时区与数据库时区，避免日期边界偏移。

## 问题四：Worker/Scheduler 仅引用 image 会触发首次启动拉取

### 影响

如果 Web 服务负责本地 `build`，而 Worker 和 Scheduler 只写同名 `image`，首次执行 `docker compose up --build` 时 Compose 可能并发启动服务。此时本地镜像尚未构建完成，另外两个服务会尝试从镜像仓库拉取该名称，导致 `pull access denied` 或启动顺序不稳定。

### 根因

错误地假设 Compose 会先完成 Web 服务的镜像构建，再启动所有仅引用该镜像的服务。服务间运行依赖不能保证镜像构建阶段的顺序。

### 修复

Web、Worker、Scheduler 共享同一套 `build` 配置和明确的本地 `image` 名称，可通过 YAML anchor 减少重复。这样 Compose 知道这些服务都使用同一个可本地构建的镜像，不需要在首次启动时抢先拉取。

`depends_on` 仍用于数据库、Redis 或应用初始化就绪顺序，但不能替代共享构建配置。

### 验证方式

- 在本机不存在目标镜像的情况下执行首次 `docker compose up --build`。
- 确认日志没有访问远端仓库或 `pull access denied`。
- 执行 `docker compose config`，确认三个应用服务解析到一致的 build context、Dockerfile 和 image。
- 修改 Dockerfile 后重新构建，确认 Web、Worker、Scheduler 使用同一镜像 ID。

### 后续行动

- CI 中增加“空镜像缓存首次启动”验证，避免只在开发者已有缓存的环境中通过。
- 生产环境建议由 CI 构建一次带版本号的不可变镜像，三个运行角色引用同一 digest。

## 问题五：欢迎邮件与物流同步仍是 Mock

### 影响

`MerchantWelcomeEmailJob` 和 `SyncLogisticsJob` 即使已经成功入队并执行，目前也只完成模拟日志，不会真正发送邮件或调用物流平台。队列监控显示成功，并不等于外部业务动作已经完成。

### 根因

项目尚未提供邮件收件地址/模板/发送通道，以及物流供应商 API、鉴权、运单字段映射和回调处理。因此 Job 目前只是预留的异步边界。

### 修复现状

本次接入只打通任务触发和消费链路，并明确保留 Mock 状态；在外部接口和业务数据未确定前，不虚构真实发送结果。

### 验证方式

- 当前阶段验证 Job 被正确投递、消费，并输出可关联租户或订单的结构化日志。
- 不应把 Mock 日志作为邮件送达或物流同步成功的验收依据。
- 接入真实适配器后，分别使用邮件沙箱和物流测试环境验证请求、响应、超时、重试及失败状态。

### 后续行动

- 为欢迎邮件补充租户邮箱字段、模板、发件通道、退信处理和发送状态。
- 抽象物流客户端接口，为不同供应商实现适配器，密钥通过配置注入。
- 为外部请求增加幂等标识、超时、指数退避、限流和可观测的错误码。
- 区分“Job 执行成功”和“第三方业务处理成功”，必要时保存同步状态并处理异步回调。

## 验收清单

- 五个 Job 均能从真实业务入口触发，并有自动化测试覆盖。
- 数据库事务提交后才投递依赖新数据的任务。
- Docker 首次启动不依赖预先存在的本地应用镜像。
- Web、Worker、Scheduler、MySQL、Redis 均正常运行。
- 定时任务中的动态日期在执行阶段计算，没有常驻进程冻结问题。
- 队列任务能够被消费，失败任务可查询、重试并告警。
- 欢迎邮件和物流同步明确标记为 Mock，不能作为外部对接完成的依据。
- 接入真实第三方适配器后，再分别完成邮件送达与物流同步的端到端验收。

## 问题六：测试环境不能复用开发数据库与 Redis

### 影响

容器内测试若继承 `DB_DATABASE=saas_platform`，带 `RefreshDatabase` 的测试可能修改开发数据；若测试库中的 Job 被投递到开发 Redis，开发 worker 消费时又会因为找不到对应租户或订单而失败重试。

### 根因

Compose 的运行环境与 PHPUnit 测试环境没有强制隔离数据库名和队列连接，且容器环境中的 `APP_ENV=local` 会覆盖 PHPUnit 期望的测试环境。

### 修复与验证

- 使用独立的 `saas_platform_test` 数据库，并只授予测试账号该库权限。
- 容器测试显式设置 `APP_ENV=testing`、`DB_DATABASE=saas_platform_test`、`CACHE_STORE=array`、`SESSION_DRIVER=array` 和 `QUEUE_CONNECTION=sync`。
- 需要断言派发行为的测试使用 `Queue::fake()`，禁止测试消息进入开发 Redis。
- 后续应固化为 `.env.testing` 或项目测试脚本，并增加测试启动保护：数据库名不符合测试库规则时立即失败。
