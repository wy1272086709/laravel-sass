<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Billing\BillSettlementService;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateApiBillJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $period,
    ) {}

    public function handle(BillSettlementService $billing, QueueJobLogger $logger): void
    {
        $tenant = Tenant::query()->with('package')->findOrFail($this->tenantId);
        $log = $logger->start(self::class, $tenant->id, ['tenant_id' => $tenant->id, 'period' => $this->period], 'billing');

        try {
            $bill = $billing->generateMonthlyBill($tenant, $this->period);
            $logger->success($log, ['tenant_id' => $tenant->id, 'period' => $this->period, 'bill_id' => $bill->id]);
        } catch (Throwable $throwable) {
            $logger->failed($log, $throwable);

            throw $throwable;
        }
    }
}
