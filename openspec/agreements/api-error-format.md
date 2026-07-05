# Agreement: 开放 API 统一错误格式

> 来源：[docs/api/openapi.yaml](../../docs/api/openapi.yaml) §components.responses

## 响应信封

所有 `/api/v1` 响应（成功与错误）MUST 使用统一结构：

```json
{ "code": 0, "message": "ok", "data": <object|array|null> }
```

列表响应 MUST 附带分页 `meta`：

```json
{ "code": 0, "message": "ok", "data": [ ... ], "meta": { "page": 1, "per_page": 20, "total": 100, "last_page": 5 } }
```

## 业务错误码（MUST）

| HTTP | code  | message                        | 何时使用                                   |
|-----:|------:|--------------------------------|--------------------------------------------|
| 401  | 40101 | Invalid or expired AccessToken | AccessToken 无效/过期                      |
| 403  | 40301 | Missing required permission X  | 命中 scope 鉴权失败                        |
| 404  | 40401 | Resource not found             | 资源不存在                                 |
| 422  | 42201 | Validation failed              | 表单校验失败（data 为 `{字段: [消息]}`）   |
| 429  | 42901 | API daily quota exceeded       | 触发配额阻断                               |

## 配额阻断体（429 / 42901）

```json
{
  "code": 42901,
  "message": "API daily quota exceeded",
  "data": { "tier": "basic", "quota": 10000, "used": 10001 }
}
```

- `data.tier`：basic / professional / enterprise。
- `data.quota`：日配额上限。
- `data.used`：当日已用。
- 无强制 `Retry-After`/`X-RateLimit-*` 头（配额状态全部体现在 body）。

## 其他

- 订单发货状态冲突：HTTP **409**，body 为错误信封（`code` 取 `40901`）。
- 全局异常中间层 MUST 将未捕获异常转换为上述信封，禁止裸露框架堆栈。
