# 项目中的 API 网关：请求日志与链路追踪

> 对应代码（截至 2026-09-22）：`app/Http/Middleware/ApiRequestLogMiddleware.php`（别名 `api.log`）、`app/Jobs/LogApiRequestJob.php`、`api_request_logs` 表、`ApiMonitoringController`。
> 这一块回答的问题是：**开放 API 每一次调用，谁调的、什么时候调的、调了什么、成功没有、花了多久——以及出问题后怎么在 5 分钟内定位到那一次调用。**

#### 一、解决什么问题

1. **调用审计**：第三方 ERP 通过 `/api/v1` 打数据，出了争议（"我没下过这个单"）需要原始调用记录；密钥泄露被恶意调用，需要从日志里看出攻击面。
2. **故障排查（链路追踪）**：商户反馈"刚才有个请求报错了"，客服只会给一个页面上看到的错误号。需要能凭一个 ID 直接定位到唯一一条调用记录（谁、哪个密钥、哪个 IP、哪个接口、什么状态码、耗时多少）。
3. **监控数据源**：平台后台的 API 监控面板（当日调用量、错误量、平均耗时、24 小时趋势、Top10 调用租户）全靠这张表聚合。
4. **计费/计量的对账依据**：和 Redis 日配额计数（`ApiDailyCounter`）、`api_usage_daily` 日结表互相印证。

#### 二、端到端链路

```text
外部系统（ERP / OMS）
   │ ① 请求带或不带 X-Request-Id
   ▼
[/api/v1] api 组中间件（ResolveTenantContext → ApplyTenantGlobalScope → SqlTenantGuard）
   ▼
api.log（最外层路由中间件）
   │ ② request_id = 透传（校验格式）或生成 UUID；requested_at 此刻定格
   │ ③ 计时开始
   ▼
api.exception → api.auth（双 Token）→ api.ip（黑白名单）
   → api.signature（防重放）→ api.idempotent（幂等）→ api.rate（配额）
   │     └ 任一环被拒（401/403/409/429）→ 直接带着状态码原路返回
   ▼
Controller（业务）
   │
   ▼ 响应沿原路 unwind
api.log 收尾：
   │ ④ 响应头回写 X-Request-Id（调用方可记录，下次报障凭它查）
   │ ⑤ dispatch LogApiRequestJob（只投递队列，不写库）
   ▼
队列（jobs 表 / Redis）
   │ ⑥ queue worker 异步消费
   ▼
api_request_logs 表
   │ ⑦ 聚合
   ▼
ApiMonitoringController（/api/internal/platform/api-monitor）
```

要点：**日志挂在网关链最外层**，所以被鉴权拒绝、被限流拦截、被签名校验挡掉的请求同样有记录——安全审计要看的恰恰是"谁在失败"。

#### 三、组件明细

##### 1. ApiRequestLogMiddleware（路由根组第一环）

`routes/api.php` 根组：`Route::prefix('v1')->middleware(['api.log', 'api.exception'])`。

- **request_id 解析**：请求头 `X-Request-Id` 存在且匹配 `^[A-Za-z0-9._:-]{8,128}$` 就透传（跨系统串联同一条链路）；否则生成 UUID。格式校验同时防了日志注入和超长攻击。
- **记录时机**：不是 `terminate()`，而是在 `$next($request)` 返回后的 unwind 阶段同步记录。原因：状态码、耗时此刻全部可知，且行为在 `artisan serve` 和 Octane 常驻进程下完全一致（terminable middleware 在不同运行时的语义有差异）。
- **异常兜底**：未捕获异常（真正的 500）记一条 status_code=500 后原样重抛交给框架渲染——异常路径不丢日志。被 `api.exception` 兜住的 422/404 则以真实状态码记录。
- **跳过名单**：`api/v1/ping` 探活不记（高频、零信息量）。要扩展改 `SKIP_PATHS` 常量。
- **Octane 安全**：中间件无任何共享状态（全部请求内局部变量），常驻 worker 复用不串数据；且记录动作发生在 `OctaneTenantCleanupMiddleware` 的 finally 重置之前，不受租户上下文清理影响（取租户用的是请求属性 `api_key`，不依赖容器单例）。

##### 2. LogApiRequestJob（异步落库）

- `ShouldQueue` + `Queueable`，`handle()` 只做一次 `ApiRequestLog::create()`。
- **payload 全标量**（string/int/null）：不序列化 Eloquent 模型。两个原因：队列 payload 序列化模型会把当时的对象状态冻结，worker 消费时可能已是陈旧数据；模型序列化体积大，日志高频场景会拖慢队列。
- `requested_at` 在**请求到达时刻**就转成字符串定格（不是落库时刻），保证逐小时聚合的桶归属准确。

##### 3. api_request_logs 表（租户域，tenant_id 可空）

| 列 | 说明 |
|------|------|
| `tenant_id` | **可空**（2026_09_22 迁移改的）。token 签发、支付 webhook、被鉴权拒绝的请求发生时还没有租户上下文，必须允许 NULL 才能记录全量调用 |
| `api_key_id` | 可空，同上 |
| `request_id` | 链路追踪 ID，唯一排障入口 |
| `method` / `endpoint` | `GET` / `/api/v1/orders`（只记路径，不含 query 串——避免敏感参数落库） |
| `status_code` | 真实响应状态码（含 401/403/429/500） |
| `duration_ms` | 中间件计时，覆盖"进网关到业务响应生成"全程 |
| `ip_address` | 调用方 IP |
| `requested_at` | 请求到达时刻（索引：`(tenant_id, requested_at)`、`requested_at`，支撑按租户/按时间的聚合查询） |

##### 4. ApiMonitoringController（消费侧）

`GET /api/internal/platform/api-monitor`（平台后台 Session 鉴权），四块数据：
- `summary`：当日调用量 / 错误量（status>=400）/ 平均耗时；
- `hourly_trend`：24 小时逐时调用量与错误量（按 DB 驱动适配 MySQL/Postgres/SQLite 的小时格式化表达式）；
- `top_tenants`：按调用量排序的 Top10 租户；
- `recent_logs`：最近 10 条明细（含 request_id）。

查询全部 `withoutGlobalScopes()`——平台监控是跨租户视角，不能被租户隔离 Scope 过滤。

#### 四、一次排障的走读（面试可讲）

**场景：商户反馈"下午 3 点左右推订单一直报错"。**

1. 商户从响应头拿到/或从自己系统日志里找到 `X-Request-Id: trace-xxxx`；
2. `ApiRequestLog::where('request_id', 'trace-xxxx')` → 一条记录：`POST /api/v1/orders`、status_code=40107、tenant、api_key、IP、耗时、时刻全在；
3. 40107 是签名校验失败（`ApiSignatureMiddleware`）→ 结合 `ip_address` 判断是商户换了出口 IP 还是时钟偏移超 5 分钟；
4. 若要看面：监控接口的 `hourly_trend` 看错误量是陡增还是持续，`top_tenants` 确认是否只影响这一个商户。

整条链路不碰业务日志文件，一张表一个 ID 定位，这就是"请求日志 + 链路追踪"和"打一堆 error log"的区别。

#### 五、设计决策速查（为什么这么做）

| 决策 | 理由 |
|------|------|
| 挂网关链最外层而非 Controller 里记 | 被拒绝的请求也要有记录（401 爆破、429 限流触发都是安全事件） |
| 异步落库（投递队列） | 请求关键路径上只多一次队列 RPUSH，日志库慢/挂不影响 API 可用性 |
| payload 全标量 | 避免队列序列化模型的陈旧数据与大对象问题 |
| X-Request-Id 校验后透传 + 回写响应头 | 跨系统串联链路；商户报障只需给一个 ID |
| tenant_id 可空 | 无租户上下文的调用（token 签发、webhook、被拒请求）不能丢 |
| 记录在 unwind 阶段而非 terminate() | serve / Octane 行为一致，状态码耗时此刻才完整 |
| endpoint 不含 query 串 | 敏感参数（签名、密钥）不落库 |
| /ping 跳过 | 探活高频零价值，避免日志表被冲爆 |

#### 六、部署与运维注意

1. **queue worker 必须运行**（`php artisan queue:work`，docker 部署里对应 worker 进程），否则日志只进队列不落表；
2. **表增长治理**：这张表只增不减，量级上来后需要定期归档/分区（当前 MVP 未做，演进方向：按月归档到冷表 + 调度任务清理 N 天前数据）；
3. 与另外两类日志的分工：`operation_logs` 记**后台人工操作**（谁在 Filament 里改了什么），`queue_job_logs` 记**定时/异步任务执行**，`api_request_logs` 记**开放 API 机器调用**——三者审计域不同，不要混。

#### 七、测试覆盖

`tests/Feature/Middleware/ApiRequestLogMiddlewareTest.php`（6 例）+ `tests/Feature/Jobs/LogApiRequestJobTest.php`（2 例）：

- 完整 payload 断言（租户、密钥、方法、路径、状态码、耗时、IP、request_id 与响应头一致）；
- 合法 X-Request-Id 透传 / 非法 ID 换成 UUID；
- 未认证 401 请求照记（tenant 为 null）；
- 被 IP 黑名单拦截的 403 请求照记；
- ping 跳过不投递；
- Job 落库（含 tenant 为空的行）。

#### 八、边界与演进方向

- **采样**：当前全量记录；若 QPS 上去，可按租户/接口配置采样率（高频读接口 10% 采样，写接口与错误全量）；
- **标准化 trace 上下文**：`X-Request-Id` 是单系统 ID；若未来拆微服务，演进为 W3C `traceparent`（trace_id + span_id），网关作为 trace 起点向下游传播；
- **归档调度**：`api_request_logs` 按月分区 + 调度任务清理；
- **实时化**：当前靠队列批量落库（秒级延迟），如需准实时告警（如 5 分钟错误率突增），可在 Job 内顺带写 Redis 滑动窗口计数，复用 `SlidingWindowRateLimiter`。
