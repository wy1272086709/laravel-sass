## Context

当前代码已有订阅 Checkout、`PaymentGateway` 接口、Mock 网关、支付事件表、队列 Job 和事务型 `WebhookProcessor`。但渠道在订单和容器绑定中写死为 `mock`，回调只支持自定义时间戳 HMAC；远程下单仍位于数据库事务内，也没有主动查单和关单补偿。

本变更跨越外部支付 API、密码学验证、数据库状态机、队列、Scheduler 和部署 Secret，必须保持现有五层依赖约束及 `/api/v1` Checkout 响应兼容。行为契约见 `specs/subscription-payment/spec.md`，完整前期设计见 `docs/superpowers/specs/2026-08-03-wechat-h5-payment-webhook-design.md`。

## Goals / Non-Goals

**Goals:**

- 将真实渠道细节限制在 Infrastructure，领域层只消费渠道无关结果和通知 DTO。
- 让通知和主动查单共用同一幂等业务状态变更路径。
- 避免远程 API 调用持有数据库事务或行锁。
- 在不访问微信网络、不使用真实商户密钥的条件下完整测试密码学和状态流。
- 支持逐步启用微信新订单，同时持续处理所有既有在途订单。

**Non-Goals:**

- 不设计退款、分账、服务商/子商户、JSAPI、Native 或小程序支付。
- 不改变商户月结账单 `tenant_bills` 的状态流转方案。
- 不在应用运行时自动下载或轮换微信平台证书；本期使用微信支付公钥模式并由部署系统轮换 Secret 文件。

## Decisions

### 1. 使用官方 SDK并置于渠道适配器之后

新增官方 `wechatpay/wechatpay` 依赖。`WeChatPayGateway` 封装 H5 下单、按商户订单号查单和关单；领域接口返回内部 DTO，不泄漏 SDK 的请求、响应或异常类型。

选择官方 SDK 是为了复用 API v3 请求签名和响应验签实现，降低自行维护密码学协议的风险。未选择第三方 Laravel 聚合包，因为它会引入与现有 `PaymentGateway` 重叠的抽象；未选择手写 Guzzle/OpenSSL 请求签名，因为安全维护成本没有业务收益。

### 2. 渠道注册与新订单选择分离

所有已支持渠道的通知解析器和网关能力始终注册；`payments.default` 只决定新订单使用哪个渠道。这样紧急停止微信新 Checkout 时，既有微信订单仍能接收通知和被主动查单。

生产配置预检验证微信 AppID、商户号、商户证书序列号、微信支付公钥 ID、32 字节 API v3 Key、可解析 PEM 文件、公网 HTTPS 通知 URL 和 H5 网站信息。`local`/`testing` 可显式使用 Mock，其他环境不能使用默认 Mock Secret。

### 3. 下单使用两段式本地状态，不在事务内访问微信

第一段数据库事务负责幂等创建订阅和 `creating` 支付单，提交后调用微信。取得已验签的 `h5_url` 后，第二个短事务将订单更新为 `pending`、写入过期时间和 Checkout 元数据。

网络结果不确定时保持 `creating`，由同一请求的查单或定时补偿确认真实状态。不会换用新商户订单号重试，从而避免同一个本地意图生成多个可支付交易。

支付单增加 `expires_at`、`last_queried_at`、`gateway_error_code`、`gateway_error_at`，并增加 `(provider, status, expires_at)` 索引。`PaymentOrderStatus` 增加 `creating`；支付通知允许 `creating` 或 `pending` 进入 `paid`。

### 4. 微信通知先验签解密，再标准化落库

微信 Controller 保留原始请求体，读取四个 `Wechatpay-*` 头。通知验证器使用配置的微信支付公钥 ID选择公钥，通过官方 SDK/官方签名工具验证原始报文；随后用 API v3 Key 对 `resource` 做 AES-256-GCM 认证解密。

验证器输出渠道无关 DTO：事件 ID、内部事件类型、商户订单号、渠道交易号、支付时间、金额、币种及经过校验的标准载荷。落库服务加载本地订单并验证 AppID、商户号、`MWEB`、`SUCCESS`、金额和币种后，以 `(provider, event_id)` 唯一键保存。成功落库即返回空体 204，业务 Job 在响应后运行。

Mock 回调保留现有 HMAC 协议，通过同一 DTO 和落库服务进入处理链路。微信回调不套用平台 API 的 JSON 成功信封，因为微信协议要求 200/204 空响应；错误响应仍由专用 Controller 返回最小化 4xx/5xx，避免泄漏内部信息。

### 5. 通知与查单补偿共用事件处理器

`WebhookProcessor` 接收标准事件，继续在一个事务中锁定事件与订单，幂等更新支付单、订阅和租户套餐。微信通知 ID 用作回调事件 ID；主动查单补偿使用确定性事件 ID，例如 `query:{mchid}:{out_trade_no}:{transaction_id}`，确保重复扫描只生成一个补偿事件。

Scheduler 以小批次扫描已到期的微信 `creating`/`pending` 订单，Job 逐单处理以限制锁范围。查单为 `SUCCESS` 时创建补偿事件；`NOTPAY` 时先调用微信关单，成功后短事务取消本地订单；网络错误或可重试 5xx 只更新时间与脱敏错误，保留非终态。

### 6. 金额以十进制字符串转换为整数分

本地 `decimal(12,2)` 值按规范化十进制字符串拆分并转换成整数分，禁止经过二进制浮点。通知金额以整数分与本地转换结果比较；币种必须为 `CNY`。微信 `transaction_id` 仅在成功事件处理时写入 `external_payment_id`。

### 7. 测试使用临时 RSA 密钥和确定性密文

测试夹具生成或保存专用测试 RSA 密钥对，使用私钥签署与微信格式一致的回调报文；使用固定测试 API v3 Key 生成 AES-256-GCM 资源。HTTP 客户端/SDK传输层使用 fake，不访问真实微信。测试覆盖签名篡改、公钥 ID、时间偏差、签名探测、GCM 标签、身份与金额不一致、重复事件及补偿状态。

测试密钥只能位于测试夹具，不能被生产配置加载。

## Risks / Trade-offs

- **[SDK 版本或微信协议变化]** → 锁定 Composer 版本，依赖更新单独评审，并用协议级测试固定请求和回调行为。
- **[私钥或 API v3 Key 泄漏]** → 只读 Secret 挂载、最小文件权限、配置输出脱敏、日志字段白名单和部署前泄漏检查。
- **[下单超时但微信已创建交易]** → 保持原订单号并优先查单，禁止以新订单号盲目重试。
- **[回调被防火墙拦截或 Worker 延迟]** → 公网回调连通性验收、主动查单补偿、队列延迟与失败数量告警。
- **[微信支付公钥轮换造成验签失败]** → 公钥 ID与文件成对配置，轮换采用先部署兼容配置再切换；未知 ID失败并告警，不降级跳过验签。
- **[定时扫描放大微信 API 压力]** → 使用复合索引、小批次、单订单 Job、查询时间节流和指数退避。
- **[紧急切换渠道遗漏在途交易]** → 默认渠道仅影响新订单，微信通知路由和补偿任务不随默认值关闭。

## Migration Plan

1. 添加 SDK、配置结构、DTO、枚举和向后兼容的 nullable 数据库字段/索引；默认渠道仍为 Mock。
2. 部署微信网关、通知验证、事件标准化、查单和关单代码，但不开放微信新 Checkout。
3. 挂载生产 Secret，运行配置预检，启动 Worker/Scheduler，并验证通知 URL 公网连通性。
4. 在受控环境以真实小额订单完成 H5 拉起、支付通知、重复通知、主动查单和关单验收。
5. 将 `PAYMENT_PROVIDER` 切换为 `wechat`，观察回调失败率、处理延迟、失败队列和非终态订单。
6. 稳定后更新运行手册并保留 Mock 仅供 local/testing。

回滚时先阻止新的微信 Checkout或把新订单渠道切回先前渠道，但保持微信通知端点、密钥、Worker 和补偿任务运行，直到所有微信在途订单进入终态。数据库新增字段保持向后兼容，不在紧急回滚中删除。
