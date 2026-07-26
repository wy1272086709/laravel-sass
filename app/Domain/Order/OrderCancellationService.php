<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use App\Support\OperationActor;
use App\Support\OperationLogContext;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\DB;

final class OrderCancellationService
{
    public function __construct(
        private readonly OperationLogger $logger,
    ) {}

    /**
     * 取消订单（手动取消与系统超时关单的唯一收口）。
     *
     * @param OperationActor|null $actor  传入则写审计（Controller 传 ApiKey/用户，超时关单 Job 传 system）；null 保持向后兼容不写审计。
     */
    public function cancel(
        int $orderId,
        ?string $reason = null,
        bool $onlyIfExpired = false,
        ?OperationActor $actor = null,
    ): bool {
        return DB::transaction(function () use ($orderId, $reason, $onlyIfExpired, $actor): bool {
            $order = Order::query()
                ->withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($orderId);

            $fromStatus = $order->status;   // 权威前状态（加锁后读取）

            if (! $fromStatus->canTransitionTo(OrderStatus::Cancelled)) {
                return false;
            }

            if ($onlyIfExpired && ($fromStatus !== OrderStatus::PendingPayment || $order->created_at?->gt(now()->subMinutes(30)))) {
                return false;
            }

            foreach ($order->items as $item) {
                Product::query()->withoutGlobalScopes()->whereKey($item->product_id)->increment('stock', $item->quantity);
                Product::query()->withoutGlobalScopes()->whereKey($item->product_id)->decrement('sales_count', $item->quantity);

                if ($item->sku_id !== null) {
                    ProductSku::query()->withoutGlobalScopes()->whereKey($item->sku_id)->increment('stock', $item->quantity);
                }
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            // 审计：与业务 UPDATE 同事务原子提交；from_status 用加锁后的权威值
            if ($actor !== null) {
                $this->logger->log(new OperationLogContext(
                    tenantId: (int) $order->tenant_id,
                    actorKind: $actor->kind,
                    actorId: $actor->id,
                    actorLabel: $actor->label,
                    subjectType: 'order',
                    subjectId: $order->id,
                    subjectLabel: $order->order_no,
                    action: OperationAction::Cancelled,
                    fromStatus: $fromStatus->value,
                    toStatus: OrderStatus::Cancelled->value,
                    payload: array_filter([
                        'reason' => $reason,
                        'only_if_expired' => $onlyIfExpired ? true : null,
                    ], fn ($v) => $v !== null),
                    idempotencyKey: $actor->idempotencyKey,
                ));
            }

            return true;
        });
    }
}
