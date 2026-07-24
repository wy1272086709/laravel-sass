<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use Illuminate\Support\Facades\DB;

final class OrderCancellationService
{
    public function cancel(int $orderId, ?string $reason = null, bool $onlyIfExpired = false): bool
    {
        return DB::transaction(function () use ($orderId, $reason, $onlyIfExpired): bool {
            $order = Order::query()
                ->withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($orderId);

            if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                return false;
            }

            if ($onlyIfExpired && ($order->status !== OrderStatus::PendingPayment || $order->created_at?->gt(now()->subMinutes(30)))) {
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

            return true;
        });
    }
}
