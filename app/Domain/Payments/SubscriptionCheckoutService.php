<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Enums\PaymentOrderStatus;
use App\Domain\Enums\SubscriptionStatus;
use App\Models\Billing\PaymentOrder;
use App\Models\Billing\TenantSubscription;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubscriptionCheckoutService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function create(Tenant $tenant, Package $package, string $idempotencyKey): PaymentOrder
    {
        return DB::transaction(function () use ($tenant, $package, $idempotencyKey): PaymentOrder {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $existing = PaymentOrder::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->package_id !== $package->id) {
                    throw new \DomainException('Idempotency key already belongs to another package.');
                }

                return $existing;
            }

            $subscription = TenantSubscription::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($subscription === null) {
                $subscription = TenantSubscription::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'provider' => 'mock',
                    'status' => SubscriptionStatus::Pending,
                ]);
            }

            $order = PaymentOrder::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'package_id' => $package->id,
                'order_no' => 'SUB'.now()->format('YmdHis').strtoupper(Str::random(10)),
                'provider' => 'mock',
                'idempotency_key' => $idempotencyKey,
                'amount' => $package->price_monthly,
                'currency' => 'CNY',
                'status' => PaymentOrderStatus::Pending,
            ]);

            $checkout = $this->gateway->createCheckout($order);
            $order->forceFill([
                'external_payment_id' => $checkout['external_payment_id'],
                'metadata' => ['checkout_url' => $checkout['checkout_url']],
            ])->save();

            return $order->refresh();
        });
    }
}
