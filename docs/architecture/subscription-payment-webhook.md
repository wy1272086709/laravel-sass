# 订阅支付与 Webhook 幂等设计

## 目标

本模块提供套餐订阅支付闭环，同时把支付渠道差异隔离在 `PaymentGateway` 接口之后。当前使用 Mock 网关验证业务链路，后续接入 Stripe、PayPal 等渠道时不改变订阅领域状态机。

核心原则：

- 创建支付单不立即变更租户套餐；
- 只有通过验签的支付 Webhook 才能激活订阅；
- Webhook 事件以渠道和事件 ID 唯一；
- 支付单、订阅和租户套餐在同一数据库事务内更新；
- HTTP 接收和业务处理通过 Redis Job 解耦。

## 数据模型

### tenant_subscriptions

每个租户最多一条当前订阅，记录渠道订阅 ID、套餐、状态和当前周期。

状态：

```text
pending -> active -> past_due
                  -> cancelled
                  -> expired
```

### payment_orders

每次 checkout 对应一条支付单。`(tenant_id, idempotency_key)` 唯一，防止同一租户重复创建支付单。

状态：

```text
pending -> paid
        -> failed
        -> cancelled
paid    -> refunded
```

### payment_webhook_events

保存支付渠道通知及处理状态。`(provider, event_id)` 唯一，是 Webhook 幂等的数据库最终防线。

同时保存原始请求体 SHA-256。相同事件 ID 使用不同载荷重放时返回冲突，不会覆盖首次事件。

## API

### 查询当前订阅

```http
GET /api/v1/subscription
Authorization: Bearer <access-token>
```

AccessToken 需要 `subscription_manage` 权限。

### 创建 checkout

```http
POST /api/v1/subscription/checkout
Authorization: Bearer <access-token>
Idempotency-Key: subscription-checkout-001
X-App-Key: ...
X-Timestamp: ...
X-Nonce: ...
X-Signature: ...
Content-Type: application/json

{"package_id":2}
```

接口沿用开放 API 的签名、nonce 防重放和请求幂等中间件。成功返回 pending 支付单和渠道 checkout URL，此时租户当前套餐不会改变。

### 支付 Webhook

```http
POST /api/v1/payments/webhooks/mock
X-Payment-Timestamp: 1785675600
X-Payment-Signature: <hmac-sha256>
Content-Type: application/json
```

签名原文：

```text
{timestamp}.{raw_request_body}
```

签名算法：

```php
hash_hmac('sha256', $timestamp.'.'.$rawBody, $webhookSecret);
```

时间戳允许误差为 5 分钟。必须使用原始请求体计算签名，不能先 JSON decode 再 encode，否则空格、转义或键顺序变化会导致签名不同。

成功支付事件示例：

```json
{
  "id": "evt_001",
  "type": "checkout.completed",
  "data": {
    "object": {
      "order_no": "SUB202608020001",
      "payment_id": "pay_001",
      "customer_id": "cus_001",
      "subscription_id": "sub_001",
      "paid_at": "2026-08-02T21:00:00+08:00",
      "current_period_end": "2026-09-02T21:00:00+08:00"
    }
  }
}
```

支持事件：

| 事件 | 支付单 | 订阅 |
|---|---|---|
| `checkout.completed` | paid | active |
| `invoice.paid` | paid | active |
| `invoice.payment_failed` | failed | past_due |
| `subscription.cancelled` | cancelled | cancelled |
| `payment.refunded` | refunded | 保持当前状态 |

## 处理链路

```text
支付渠道
  -> Webhook Controller
  -> 校验渠道、时间戳和 HMAC
  -> payment_webhook_events 唯一落库
  -> Redis: ProcessPaymentWebhookJob
  -> Worker 开启数据库事务
  -> 锁定 webhook event 和 payment order
  -> 更新 payment order
  -> 更新 tenant subscription
  -> 支付成功时更新 tenant.package_id
  -> event 标记 processed
```

Webhook 首次接收返回 HTTP 202。重复发送完全相同的事件返回 HTTP 200，并包含：

```json
{
  "event_id": "evt_001",
  "duplicate": true,
  "status": "processed"
}
```

重复通知不会再次投递 Job。即使队列自身发生重复投递，Processor 也会锁定事件行并在发现 `processed` 后直接返回。

## 配置

本地环境：

```dotenv
PAYMENT_PROVIDER=mock
MOCK_PAYMENT_WEBHOOK_SECRET=local-mock-webhook-secret
```

生产环境必须替换 Webhook Secret，关闭 `APP_DEBUG`，并通过 Secret 管理系统注入。Webhook Secret 不应提交到代码库或记录到日志。

接入真实渠道时需要：

1. 实现 `PaymentGateway`，负责创建真实 checkout；
2. 在 Service Provider 中绑定新实现；
3. 将真实渠道事件转换为本模块的标准事件字段；
4. 使用渠道官方验签方式替换 Mock HMAC verifier；
5. 保留数据库事件唯一索引和事务处理，不依赖渠道“只通知一次”。

## 运维命令

```bash
php artisan migrate --force
php artisan queue:work redis --tries=3 --timeout=60
php artisan queue:failed
php artisan queue:retry <uuid>
```

更新 API Key 权限后，旧 AccessToken 的 abilities 不会自动变化，需要重新签发 AccessToken。
