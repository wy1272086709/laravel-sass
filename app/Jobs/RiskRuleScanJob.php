<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Risk\RuleEngine;
use App\Models\Risk\RiskAlert;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RiskRuleScanJob implements ShouldQueue
{
    use Queueable;

    public function handle(RuleEngine $engine, QueueJobLogger $logger): void
    {
        Tenant::query()
            ->lazyById()
            ->each(function (Tenant $tenant) use ($engine, $logger): void {
                $log = $logger->start(self::class, $tenant->id, ['tenant_id' => $tenant->id]);

                try {
                    $created = 0;

                    foreach ($engine->evaluateTenant($tenant) as $hit) {
                        $exists = RiskAlert::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->where('status', RiskAlertStatus::Pending)
                            ->where('type', $hit['type'])
                            ->where('context->rule_code', $hit['context']['rule_code'])
                            ->where('context->reason', $hit['context']['reason'])
                            ->when($hit['order_id'] ?? null, fn ($query, int $orderId) => $query->where('order_id', $orderId))
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        RiskAlert::query()
                            ->withoutGlobalScopes()
                            ->create([
                                'tenant_id' => $tenant->id,
                                'type' => $hit['type'],
                                'risk_level' => $hit['risk_level'],
                                'status' => RiskAlertStatus::Pending,
                                'context' => $hit['context'],
                                'order_id' => $hit['order_id'],
                                'triggered_at' => now(),
                            ]);

                        $created++;
                    }

                    $logger->success($log, ['tenant_id' => $tenant->id, 'created_alerts' => $created]);
                } catch (Throwable $throwable) {
                    $logger->failed($log, $throwable);

                    throw $throwable;
                }
            });
    }
}
