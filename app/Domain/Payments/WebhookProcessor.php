<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Enums\PaymentOrderStatus;
use App\Domain\Enums\SubscriptionStatus;
use App\Domain\Enums\WebhookEventStatus;
use App\Models\Billing\PaymentOrder;
use App\Models\Billing\PaymentWebhookEvent;
use App\Models\Billing\TenantSubscription;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class WebhookProcessor
{
    public function process(int $eventId): void
    {
        try {
            DB::transaction(function () use ($eventId): void {
                $event = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($eventId);

                if ($event->status === WebhookEventStatus::Processed) {
                    return;
                }

                $event->forceFill([
                    'status' => WebhookEventStatus::Processing,
                    'attempts' => $event->attempts + 1,
                    'error' => null,
                ])->save();

                $payload = $event->payload;
                $object = $payload['data']['object'] ?? [];
                $orderNo = (string) ($object['order_no'] ?? '');

                if ($orderNo === '') {
                    throw new \UnexpectedValueException('Webhook payload is missing data.object.order_no.');
                }

                $order = PaymentOrder::query()
                    ->withoutGlobalScopes()
                    ->where('provider', $event->provider)
                    ->where('order_no', $orderNo)
                    ->lockForUpdate()
                    ->firstOrFail();

                match ($event->event_type) {
                    'checkout.completed', 'invoice.paid' => $this->markPaid($order, $object),
                    'invoice.payment_failed' => $this->markFailed($order),
                    'subscription.cancelled' => $this->markCancelled($order),
                    'payment.refunded' => $this->markRefunded($order),
                    default => null,
                };

                $event->forceFill([
                    'status' => WebhookEventStatus::Processed,
                    'processed_at' => now(),
                ])->save();
            });
        } catch (Throwable $throwable) {
            PaymentWebhookEvent::query()->whereKey($eventId)->update([
                'status' => WebhookEventStatus::Failed->value,
                'attempts' => DB::raw('attempts + 1'),
                'error' => $throwable->getMessage(),
                'updated_at' => now(),
            ]);

            throw $throwable;
        }
    }

    /** @param array<string, mixed> $object */
    private function markPaid(PaymentOrder $order, array $object): void
    {
        $paidAt = isset($object['paid_at']) ? Carbon::parse((string) $object['paid_at']) : now();
        $periodEnd = isset($object['current_period_end'])
            ? Carbon::parse((string) $object['current_period_end'])
            : $paidAt->copy()->addMonthNoOverflow();

        $order->forceFill([
            'status' => PaymentOrderStatus::Paid,
            'external_payment_id' => $object['payment_id'] ?? $order->external_payment_id,
            'paid_at' => $paidAt,
            'failed_at' => null,
        ])->save();

        $subscription = TenantSubscription::query()
            ->withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($order->subscription_id);

        $subscription->forceFill([
            'package_id' => $order->package_id,
            'external_customer_id' => $object['customer_id'] ?? $subscription->external_customer_id,
            'external_subscription_id' => $object['subscription_id'] ?? $subscription->external_subscription_id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $paidAt,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
        ])->save();

        Tenant::query()->whereKey($order->tenant_id)->update(['package_id' => $order->package_id]);
    }

    private function markFailed(PaymentOrder $order): void
    {
        $order->forceFill([
            'status' => PaymentOrderStatus::Failed,
            'failed_at' => now(),
        ])->save();

        TenantSubscription::query()
            ->withoutGlobalScopes()
            ->whereKey($order->subscription_id)
            ->update(['status' => SubscriptionStatus::PastDue->value]);
    }

    private function markCancelled(PaymentOrder $order): void
    {
        $order->forceFill(['status' => PaymentOrderStatus::Cancelled])->save();

        TenantSubscription::query()
            ->withoutGlobalScopes()
            ->whereKey($order->subscription_id)
            ->update([
                'status' => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
    }

    private function markRefunded(PaymentOrder $order): void
    {
        $order->forceFill(['status' => PaymentOrderStatus::Refunded])->save();
    }
}
