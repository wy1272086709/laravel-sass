<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CloseExpiredOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(QueueJobLogger $logger): void
    {
        $order = Order::query()->withoutGlobalScopes()->findOrFail($this->orderId);
        $log = $logger->start(self::class, $order->tenant_id, ['order_id' => $order->id]);

        try {
            $closed = false;

            if ($order->status === OrderStatus::PendingPayment && $order->created_at?->lte(now()->subMinutes(30))) {
                $order->forceFill([
                    'status' => OrderStatus::Cancelled,
                    'cancel_reason' => 'timeout_unpaid',
                    'cancelled_at' => now(),
                ])->save();

                $closed = true;
            }

            $logger->success($log, [
                'order_id' => $order->id,
                'closed' => $closed,
                'status' => $order->refresh()->status->value,
            ]);
        } catch (Throwable $throwable) {
            $logger->failed($log, $throwable);

            throw $throwable;
        }
    }
}
