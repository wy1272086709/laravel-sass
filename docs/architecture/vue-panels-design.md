# 阶段 6 Vue3 可视化面板设计

## 目标

阶段 6 在平台后台增加 4 个 Vue3 + Echarts 嵌入面板，让系统可以直观看到平台经营、API 接入、队列调度和风控对账状态。

面板只负责展示和轻量筛选，数据由平台内部 API 提供。内部 API 走 `auth:platform`，不暴露给开放 API `/api/v1`。

## 拆分

### 6-1：内部 API 契约

先实现 4 组内部 API，并用 Feature Test 固定字段。

接口：
- `GET /api/internal/platform/dashboard`
- `GET /api/internal/platform/api-monitor`
- `GET /api/internal/platform/queue-ops`
- `GET /api/internal/platform/risk-recon`

验收：
- 平台仪表盘返回 GMV 趋势、套餐占比、队列健康。
- API 监控返回 24h 调用趋势、Top10 租户/API Key、最近请求日志。
- 队列中心返回任务状态分布、最近任务、延迟/死信摘要。
- 风控对账返回 7 天告警趋势、风险等级分布、差异单列表。

### 6-2：Vue 面板与构建入口

实现 4 个 Vue 单文件组件和 4 个独立入口。

文件：
- `resources/js/panels/PlatformDashboardPanel.vue`
- `resources/js/panels/ApiMonitoringPanel.vue`
- `resources/js/panels/QueueOpsPanel.vue`
- `resources/js/panels/RiskReconciliationPanel.vue`
- `resources/js/panels/platform-dashboard.js`
- `resources/js/panels/api-monitoring.js`
- `resources/js/panels/queue-ops.js`
- `resources/js/panels/risk-reconciliation.js`

原则：
- 页面内不写功能说明文案，只展示业务数据、图表和表格。
- 使用 `vue-echarts` 封装图表。
- 组件自行 fetch 内部 API，失败时显示简洁错误状态。
- 图表容器使用固定高度，避免加载后布局跳动。

### 6-3：Filament 页面挂载

新增 4 个平台页面，并共用 Blade 壳。

页面：
- `PlatformDashboardPage`
- `ApiMonitoringPage`
- `QueueOpsPage`
- `RiskReconciliationPage`

Blade：
- `resources/views/filament/pages/vue-panel.blade.php`

验收：
- 页面在平台后台导航可见。
- 每个页面挂载对应 Vite entry。
- `pnpm build` 可生成所有入口产物。

## 内部 API 响应格式

统一返回：

```json
{
  "code": 0,
  "message": "ok",
  "data": {}
}
```

### Dashboard

```json
{
  "data": {
    "summary": {
      "tenant_count": 12,
      "order_count": 320,
      "gmv": 88120.5,
      "pending_bills": 7
    },
    "gmv_trend": {
      "dates": ["2026-07-01"],
      "amounts": [1200.0],
      "order_counts": [8]
    },
    "package_distribution": [
      {"tier": "basic", "name": "基础版", "tenant_count": 3}
    ],
    "queue_health": {
      "success": 10,
      "failed": 1,
      "pending": 2
    }
  }
}
```

### API Monitor

```json
{
  "data": {
    "summary": {
      "calls_today": 1200,
      "errors_today": 12,
      "avg_duration_ms": 38
    },
    "hourly_trend": {
      "hours": ["00:00"],
      "counts": [20],
      "errors": [1]
    },
    "top_tenants": [
      {"tenant_id": 1, "tenant_name": "星河数码旗舰店", "request_count": 300}
    ],
    "recent_logs": []
  }
}
```

### Queue Ops

复用 5-3 的队列中心能力，但聚合到一个面板接口：

```json
{
  "data": {
    "status_counts": {"success": 10, "failed": 1},
    "recent_jobs": [],
    "delayed": {"close_expired_orders": 2},
    "dead_letters": {"default": 1, "billing": 0}
  }
}
```

### Risk Recon

```json
{
  "data": {
    "alert_trend": {
      "dates": ["2026-07-01"],
      "counts": [3]
    },
    "level_distribution": {"high": 1, "medium": 2, "low": 0},
    "recent_alerts": [],
    "discrepancies": []
  }
}
```

## 数据边界

- 所有内部 API 都是平台视角，允许跨租户聚合。
- 不走 `TenantScope` 限制时，查询必须显式 `withoutGlobalScopes()`，避免被当前请求上下文影响。
- 页面只读，不提供处理/编辑动作。
- 真实筛选、导出、钻取留到后续增强。

## 测试策略

6-1：
- Feature Test 覆盖 4 个内部 API 的字段结构和平台认证。

6-2：
- `pnpm build` 验证 Vue/Echarts 编译。

6-3：
- Feature Test 覆盖平台页面可访问，至少断言挂载节点和 Vite entry 出现在响应中。

## 非目标

阶段 6 不做：
- 实时 WebSocket 推送。
- Echarts 复杂交互钻取。
- 可视化面板中的写操作。
- 面板权限细分到按钮级。
