<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Order\OrderCancellationService;
use App\Models\Order\Order;
use App\Support\OperationActor;
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

    public function handle(OrderCancellationService $cancellation, QueueJobLogger $logger): void
    {
        $order = Order::query()->withoutGlobalScopes()->findOrFail($this->orderId);
        $log = $logger->start(self::class, $order->tenant_id, ['order_id' => $order->id]);

        try {
            $actor = new OperationActor(
                kind: 'system',
                id: null,
                label: '系统超时关单',
            );

            $closed = $cancellation->cancel($order->id, 'timeout_unpaid', onlyIfExpired: true, actor: $actor);

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
