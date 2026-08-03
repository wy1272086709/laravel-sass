# 微信支付 H5 与生产级 Webhook 设计

## 目标

在现有订阅支付领域模型上接入微信支付 API v3 普通直连商户 H5 支付，形成可上线的支付闭环：

- 创建微信 H5 支付订单并向客户端返回 `h5_url`；
- 验证并解密微信支付成功通知；
- 幂等激活订阅并更新租户套餐；
- 通过主动查单补偿可能丢失的通知；
- 关闭超时且未支付的微信订单；
- 保留 Mock 渠道用于本地开发和自动化测试。

首期不包含退款申请、退款通知、分账、服务商/子商户模式和自动下载平台证书。

## 接入方式

使用微信支付官方 PHP SDK `wechatpay/wechatpay`，采用微信支付公钥模式。SDK 负责 API v3 请求签名、响应验签和回调签名验证，项目通过基础设施适配器隔离 SDK，领域服务不直接依赖微信类型。

不自行实现 API v3 请求签名协议，也不引入支付聚合框架。这样可以减少密码学实现风险，同时避免第三方支付抽象与现有 `PaymentGateway` 重叠。

参考资料：

- [微信支付 API v3 官方 PHP SDK](https://github.com/wechatpay-apiv3/wechatpay-php)
- [微信支付 H5 下单](https://pay.wechatpay.cn/doc/v3/merchant/4012791834)
- [微信支付成功回调通知](https://pay.wechatpay.cn/doc/v3/merchant/4013070368)

## 架构

### 渠道接口

扩展现有 `PaymentGateway`，为生产支付闭环提供三个能力：

- 创建 H5 Checkout；
- 按商户订单号查询支付状态；
- 关闭未支付订单。

`MockPaymentGateway` 继续实现同一接口。`WeChatPayGateway` 封装官方 SDK，负责微信 API 请求、响应验签及微信字段到内部结果对象的转换。

应用容器根据 `payments.default` 绑定渠道实现。`PAYMENT_PROVIDER=wechat` 时必须绑定 `WeChatPayGateway`；未知渠道或配置缺失必须启动失败，不能静默回退到 Mock。

### 通知解析

支付通知不再假设所有渠道都使用 Mock HMAC 格式。Controller 根据路由中的 `provider` 解析对应通知：

- Mock 解析器保留现有 HMAC 行为；
- `WeChatPayNotificationVerifier` 使用微信支付公钥验证原始请求体签名，随后使用 API v3 Key 解密 `resource`；
- 解析结果转换为渠道无关的内部通知 DTO，再交给事件落库和业务处理层。

微信 `TRANSACTION.SUCCESS` 映射为内部 `checkout.completed` 事件。现有 `WebhookProcessor` 继续作为渠道无关的事务边界，负责行锁、幂等、支付单更新、订阅激活和租户套餐更新。

### 到期订单补偿

新增定时任务扫描到期的微信待支付订单。每个订单先主动向微信查单：

- 微信状态为 `SUCCESS`：构造内部支付成功事件，通过与回调相同的幂等处理链路补偿入账；
- 微信状态为 `NOTPAY`：调用关单接口，成功后将本地支付单标记为取消；
- 状态仍在处理中或请求暂时失败：保留待支付状态并在后续周期重试；
- 微信返回终态异常：记录结构化错误并按状态映射本地订单。

## 数据流

### 创建 H5 支付

1. `SubscriptionCheckoutService` 在数据库事务中创建本地订阅和支付单，渠道来自配置，不再写死 `mock`。
2. 本地金额从十进制元精确转换为整数分；仅允许 CNY、正金额和微信允许的金额范围。
3. 调用微信 H5 下单接口，提交与商户号绑定的 AppID、商户号、真实商品描述、商户订单号、支付结束时间、HTTPS 通知地址、客户端 IP 和 H5 场景信息。
4. 商户订单号保持 6 至 32 个微信允许的字符，并在同一商户号下唯一。
5. 微信响应通过 SDK 验签后，将 `h5_url` 保存到支付单元数据。
6. 对外 API 继续使用现有 `checkout_url` 字段返回 `h5_url`，避免破坏前端契约。

下单调用发生在数据库事务外，避免在远程网络请求期间持有数据库锁。应用先以 `creating` 状态持久化订单，再请求微信；成功取得 `h5_url` 后转为 `pending`。若网络结果不确定，则保持 `creating` 并按商户订单号查单后决定后续动作，不重复创建本地订单或更换商户订单号。

### 接收支付通知

1. Controller 读取原始请求体以及 `Wechatpay-Serial`、`Wechatpay-Signature`、`Wechatpay-Timestamp`、`Wechatpay-Nonce`。
2. 验证必需请求头、允许的时间偏差和公钥 ID；拒绝以 `WECHATPAY/SIGNTEST/` 开头的签名探测流量。
3. 使用请求头指定的微信支付公钥验证 `timestamp + newline + nonce + newline + raw body + newline`。
4. 使用 32 字节 API v3 Key、`resource.nonce` 和 `resource.associated_data` 对 AES-256-GCM 密文进行认证解密。
5. 校验事件类型为 `TRANSACTION.SUCCESS`，且解密结果中的 `appid`、`mchid`、`out_trade_no`、币种、支付金额和 `trade_state` 与本地订单完全一致。
6. 以微信通知 `id` 作为事件 ID，将原始通知 JSON、载荷哈希和标准化事件落入 `payment_webhook_events`。
7. 数据持久化成功后立即返回 HTTP 204，再由队列异步执行订阅状态变更。

相同 `(provider, event_id)` 只落库一次。相同通知 ID 携带不同载荷时拒绝处理并记录安全事件。即使队列重复投递，Processor 也会锁定事件并跳过已处理记录。

### 通知响应

- 验签、解密、结构校验及事件落库全部成功：HTTP 204，无响应正文；
- 缺少请求头、JSON 格式错误或字段错误：HTTP 400；
- 签名、公钥 ID、时间戳或认证解密失败：HTTP 401；
- 临时数据库故障导致无法持久化：HTTP 500，让微信按官方策略重试。

不在 Controller 中执行订阅更新，以满足微信要求的 5 秒响应窗口。

## 配置与密钥

新增或调整以下配置：

```dotenv
PAYMENT_PROVIDER=wechat
WECHAT_PAY_APP_ID=
WECHAT_PAY_MCH_ID=
WECHAT_PAY_MERCHANT_SERIAL_NO=
WECHAT_PAY_PRIVATE_KEY_PATH=/run/secrets/wechatpay_private_key.pem
WECHAT_PAY_PUBLIC_KEY_ID=
WECHAT_PAY_PUBLIC_KEY_PATH=/run/secrets/wechatpay_public_key.pem
WECHAT_PAY_API_V3_KEY=
WECHAT_PAY_NOTIFY_URL=https://api.example.com/api/v1/payments/webhooks/wechat
WECHAT_PAY_H5_SITE_URL=https://example.com
WECHAT_PAY_H5_SITE_NAME=
WECHAT_PAY_ORDER_TTL_MINUTES=30
```

生产环境要求：

- 商户 API 私钥和微信支付公钥以只读 Secret 文件挂载，不写入仓库、镜像或普通环境变量；
- API v3 Key 由 Secret 管理系统注入，不输出到日志和诊断页面；
- 通知 URL 必须是公网 HTTPS，且域名满足微信商户平台配置要求；
- 配置缓存前执行预检，验证文件存在、权限可读、PEM 可解析、API v3 Key 为 32 字节、URL 合法；
- Mock 的默认 Secret 只能用于 `local` 和 `testing` 环境，生产环境不得使用默认值。

## 安全校验

通知业务处理前必须满足以下条件：

- 签名对应配置的微信支付公钥 ID；
- 回调时间戳在允许偏差内；
- AES-256-GCM 认证标签有效；
- AppID 和商户号与本服务配置一致；
- 商户订单号存在且属于 `wechat` 渠道；
- 通知金额、币种与本地支付单一致；
- `trade_type` 为 `MWEB`；
- `trade_state` 为 `SUCCESS`；
- 同一微信交易号不能绑定不同本地订单。

安全日志只记录请求 ID、通知 ID、公钥 ID、商户订单号哈希、失败类别和时间，不记录私钥、公钥内容、API v3 Key、完整回调密文或其他敏感字段。

## 错误处理与可观测性

- 网络超时和明确可重试的微信 5xx 使用有限次数指数退避，并设置严格连接及总超时；
- 参数错误、签名错误、余额或权限等业务错误不进行盲目重试；
- 下单响应不确定时先查单，再决定是否重试；
- 每次微信 API 调用记录接口名、请求 ID、商户订单号哈希、状态码、微信错误码和耗时；
- 验签失败、金额不一致、AppID/商户号不一致作为安全告警；
- 队列最终失败保留事件 `failed` 状态和脱敏错误摘要，并接入现有失败队列处理；
- Scheduler、Worker、失败队列数量、回调失败率、通知处理延迟和待支付超时订单数量必须具备监控。

## 数据模型调整

沿用现有 `payment_webhook_events` 唯一键和状态字段。`payment_orders` 增加以下字段：

- `expires_at timestamp nullable`：微信订单支付结束时间；
- `last_queried_at timestamp nullable`：最后一次主动查单时间；
- `gateway_error_code string(64) nullable`：最近一次脱敏的微信错误码；
- `gateway_error_at timestamp nullable`：最近一次渠道错误时间。

同时调整以下状态和约束：

- `PaymentOrderStatus` 增加 `creating`，状态流为 `creating -> pending -> paid|cancelled|failed`，支付成功通知允许直接将 `creating` 转为 `paid`；
- 保持 `external_payment_id` 可空，微信 H5 下单阶段尚无支付成功交易号；
- 支付成功后将微信 `transaction_id` 写入 `external_payment_id`；
- `metadata.checkout_url` 保存微信 `h5_url`，不得记录密钥或完整通知资源。
- 新增 `(provider, status, expires_at)` 复合索引，供到期补偿任务扫描；
- 保留现有 `(provider, external_payment_id)` 唯一约束，确保同一微信交易号不能绑定多个本地订单。

## 测试策略

### 单元测试

- 使用伪造 HTTP 响应测试 H5 下单参数、金额转换、响应映射及错误分类；
- 使用测试 RSA 密钥生成真实签名，覆盖合法通知、篡改正文、错误公钥 ID、签名探测流量和过期时间戳；
- 使用测试 API v3 Key 生成 AES-256-GCM 密文，覆盖解密成功、认证标签错误和字段缺失；
- 测试微信状态到内部状态的完整映射；
- 测试元到分的精确转换，不允许浮点舍入误差。

### Feature 测试

- 微信 H5 Checkout 返回兼容的 `checkout_url`；
- 首次合法支付通知返回 204 并异步激活订阅；
- 重复通知不重复处理；
- 相同事件 ID 不同载荷被拒绝；
- 签名、金额、币种、AppID、商户号或订单归属不匹配时不激活订阅；
- 查单发现已支付时补偿入账；
- 未支付到期订单关单并取消；
- 网络异常保留可恢复状态并在后续重试；
- 现有 Mock 支付测试继续通过。

测试不得访问真实微信网络，也不得依赖真实商户凭据。

## 部署与验收

上线前必须完成：

1. 安装并锁定官方 SDK 版本，完成依赖安全审计；
2. 执行配置预检、完整测试和生产数据库迁移预演；
3. 以 Secret 挂载密钥，并确认应用进程最小读取权限；
4. 部署独立 Queue Worker 和 Scheduler，配置失败队列告警；
5. 在微信商户平台配置 H5 支付域名及回调所需网络策略；
6. 使用真实小额订单验收 H5 拉起、付款、回调、订阅激活和重复通知；
7. 验收延迟通知补偿及未支付订单自动关闭；
8. 检查日志中不存在密钥和完整敏感报文。

上线通过 `PAYMENT_PROVIDER=wechat` 切换新订单渠道。发生异常时可阻止新的真实 Checkout，但已经创建的微信订单必须继续保留回调和查单处理能力，不能通过简单切换到 Mock 丢弃在途交易。

## 完成标准

- 微信 H5 下单、支付通知、主动查单和关单均通过官方 API v3 协议实现；
- 所有微信响应和通知均完成签名验证；
- 支付通知通过认证解密并执行订单级金额、币种和商户身份校验；
- 回调在事件可靠落库后 5 秒内返回，业务处理异步且幂等；
- 回调丢失和到期未支付订单均有自动补偿路径；
- Mock 与微信渠道可显式切换，生产环境不会静默使用 Mock；
- 自动化测试覆盖密码学验证、主要成功链路、安全拒绝链路和补偿链路；
- 真实商户小额支付验收通过，运维监控和密钥管理就绪。
