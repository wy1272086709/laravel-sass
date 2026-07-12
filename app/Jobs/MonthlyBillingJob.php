<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Billing\BillSettlementService;
use App\Infrastructure\Redis\LuaDistributedLock;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MonthlyBillingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $period = null,
    ) {}

    public function handle(BillSettlementService $billing, LuaDistributedLock $lock, QueueJobLogger $logger): void
    {
        $period = $this->period ?? $billing->defaultPeriod();
        $token = $lock->acquire('billing', "monthly:{$period}", 600_000);

        if ($token === null) {
            return;
        }

        try {
            Tenant::query()
                ->with('package')
                ->lazyById()
                ->each(function (Tenant $tenant) use ($billing, $logger, $period): void {
                    $log = $logger->start(self::class, $tenant->id, [
                        'tenant_id' => $tenant->id,
                        'period' => $period,
                    ], 'billing');

                    try {
                        $bill = $billing->generateMonthlyBill($tenant, $period);
                        $logger->success($log, [
                            'tenant_id' => $tenant->id,
                            'period' => $period,
                            'bill_id' => $bill->id,
                            'total_receivable' => (float) $bill->total_receivable,
                        ]);
                    } catch (Throwable $throwable) {
                        $logger->failed($log, $throwable);
                    }
                });
        } finally {
            $lock->release('billing', "monthly:{$period}", $token);
        }
    }
}
