<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InventoryAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $threshold = 10,
    ) {}

    public function handle(QueueJobLogger $logger): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $log = $logger->start(self::class, $tenant->id, [
            'tenant_id' => $tenant->id,
            'threshold' => $this->threshold,
        ]);

        $lowStockCount = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('stock', '<=', $this->threshold)
            ->count();

        $logger->success($log, [
            'tenant_id' => $tenant->id,
            'threshold' => $this->threshold,
            'low_stock_count' => $lowStockCount,
        ]);
    }
}
