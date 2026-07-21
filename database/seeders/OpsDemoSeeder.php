<?php

namespace Database\Seeders;

use App\Domain\Enums\QueueJobStatus;
use App\Domain\Enums\ReconciliationStatus;
use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Models\Api\ApiKey;
use App\Models\Api\ApiRequestLog;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Risk\RiskAlert;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Seeder;

class OpsDemoSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()
            ->orderBy('id')
            ->get()
            ->each(function (Tenant $tenant, int $tenantIndex): void {
                $apiKey = ApiKey::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->first();

                $order = Order::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->first();

                $this->seedApiLogs($tenant, $tenantIndex, $apiKey);
                $this->seedQueueJobs($tenant, $tenantIndex);
                $this->seedRiskAlerts($tenant, $tenantIndex, $order);
                $this->seedReconciliation($tenant, $tenantIndex);
            });
    }

    private function seedApiLogs(Tenant $tenant, int $tenantIndex, ?ApiKey $apiKey): void
    {
        $endpoints = [
            '/api/v1/products',
            '/api/v1/orders',
            '/api/v1/dashboard/overview',
            '/api/v1/bills',
        ];

        foreach (range(0, 23) as $offset) {
            foreach (range(1, $tenantIndex + 2) as $sequence) {
                $statusCode = ($offset + $sequence + $tenantIndex) % 9 === 0
                    ? (($offset + $tenantIndex) % 2 === 0 ? 500 : 422)
                    : (($sequence % 3 === 0) ? 201 : 200);

                ApiRequestLog::query()
                    ->withoutGlobalScopes()
                    ->updateOrCreate(
                        ['request_id' => sprintf('DEMO-API-%d-%02d-%02d', $tenant->id, $offset, $sequence)],
                        [
                            'tenant_id' => $tenant->id,
                            'api_key_id' => $apiKey?->id,
                            'method' => $sequence % 2 === 0 ? 'POST' : 'GET',
                            'endpoint' => $endpoints[($offset + $sequence + $tenantIndex) % count($endpoints)],
                            'status_code' => $statusCode,
                            'duration_ms' => 80 + ($offset * 5) + ($sequence * 17) + ($tenantIndex * 23),
                            'ip_address' => sprintf('10.%d.%d.%d', $tenant->id, $tenantIndex + 10, $offset + $sequence),
                            'requested_at' => now()->subHours($offset)->subMinutes($tenantIndex * 4 + $sequence),
                        ],
                    );
            }
        }
    }

    private function seedQueueJobs(Tenant $tenant, int $tenantIndex): void
    {
        $jobs = [
            ['同步商品库存', 'sync_data', QueueJobStatus::Success],
            ['关闭超时订单', 'default', QueueJobStatus::Pending],
            ['生成月度账单', 'billing', QueueJobStatus::Processing],
            ['推送订单通知', 'notification', QueueJobStatus::Failed],
            ['重试账单回调', 'billing', QueueJobStatus::Dead],
        ];

        foreach ($jobs as $index => [$name, $queue, $status]) {
            $queuedAt = now()->subMinutes(20 + ($tenantIndex * 8) + ($index * 11));
            $startedAt = in_array($status, [QueueJobStatus::Pending], true) ? null : $queuedAt->copy()->addMinutes(1);
            $finishedAt = in_array($status, [QueueJobStatus::Pending, QueueJobStatus::Processing], true) ? null : $queuedAt->copy()->addMinutes(3 + $index);

            QueueJobLog::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    ['job_uuid' => sprintf('DEMO-JOB-%d-%02d', $tenant->id, $index)],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                        'queue' => $queue,
                        'status' => $status,
                        'attempts' => match ($status) {
                            QueueJobStatus::Failed => 2,
                            QueueJobStatus::Dead => 3,
                            default => 1,
                        },
                        'payload' => [
                            'tenant_id' => $tenant->id,
                            'source' => 'demo',
                        ],
                        'error' => in_array($status, [QueueJobStatus::Failed, QueueJobStatus::Dead], true)
                            ? '第三方接口响应超时，已进入重试链路'
                            : null,
                        'queued_at' => $queuedAt,
                        'started_at' => $startedAt,
                        'finished_at' => $finishedAt,
                    ],
                );
        }
    }

    private function seedRiskAlerts(Tenant $tenant, int $tenantIndex, ?Order $order): void
    {
        $alerts = [
            [RiskAlertType::BrushOrder, RiskLevel::High, RiskAlertStatus::Pending, '同设备短时间多笔支付'],
            [RiskAlertType::DuplicatePayment, RiskLevel::Medium, RiskAlertStatus::Handled, '支付流水疑似重复回调'],
            [RiskAlertType::AbnormalLogin, RiskLevel::Low, RiskAlertStatus::Ignored, '商户后台异地登录'],
        ];

        foreach ($alerts as $index => [$type, $level, $status, $description]) {
            RiskAlert::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'note' => sprintf('DEMO-RISK-%d-%02d', $tenant->id, $index),
                    ],
                    [
                        'type' => $type,
                        'risk_level' => $level,
                        'status' => $status,
                        'context' => [
                            'scene' => $description,
                            'score' => 92 - ($index * 13) - ($tenantIndex * 4),
                        ],
                        'triggered_at' => now()->subDays($index + $tenantIndex)->subMinutes(15 + ($index * 9)),
                        'order_id' => $order?->id,
                        'handled_at' => $status === RiskAlertStatus::Handled ? now()->subHours(3 + $index) : null,
                    ],
                );
        }
    }

    private function seedReconciliation(Tenant $tenant, int $tenantIndex): void
    {
        $bill = TenantBill::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->latest('billing_period')
            ->first();

        if (! $bill) {
            return;
        }

        $difference = [18.00, -26.50, 42.30][$tenantIndex % 3];
        $status = $tenantIndex === 1 ? ReconciliationStatus::Reconciled : ReconciliationStatus::Unreconciled;

        $bill->update([
            'merchant_reported_amount' => (float) $bill->total_receivable + $difference,
            'difference_amount' => $difference,
        ]);

        ReconciliationDiscrepancy::query()
            ->withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'tenant_bill_id' => $bill->id,
                    'note' => sprintf('DEMO-DIFF-%d', $tenant->id),
                ],
                [
                    'difference_amount' => $difference,
                    'status' => $status,
                    'resolved_at' => $status === ReconciliationStatus::Reconciled ? now()->subDay() : null,
                ],
            );
    }
}
