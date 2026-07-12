<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order\Order;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLogisticsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(QueueJobLogger $logger): void
    {
        $order = Order::query()->withoutGlobalScopes()->findOrFail($this->orderId);
        $log = $logger->start(self::class, $order->tenant_id, ['order_id' => $order->id], 'sync_data');

        $logger->success($log, [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'mock_synced' => true,
        ]);
    }
}
