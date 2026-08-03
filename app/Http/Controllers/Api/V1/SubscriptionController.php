<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\SubscriptionCheckoutService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\PaymentOrder;
use App\Models\Billing\TenantSubscription;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(TenantContext $context): JsonResponse
    {
        $subscription = TenantSubscription::query()
            ->with('package')
            ->where('tenant_id', $context->tenantId)
            ->first();

        return ApiResponse::ok($subscription === null ? null : $this->serializeSubscription($subscription));
    }

    public function checkout(
        Request $request,
        TenantContext $context,
        SubscriptionCheckoutService $checkout,
    ): JsonResponse {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $package = Package::query()
            ->whereKey($data['package_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $tenant = Tenant::query()->findOrFail($context->tenantId);

        try {
            $order = $checkout->create(
                $tenant,
                $package,
                (string) $request->header('Idempotency-Key'),
            );
        } catch (\DomainException $exception) {
            return ApiResponse::error(40911, $exception->getMessage(), 409);
        }

        return ApiResponse::ok($this->serializeOrder($order), 201);
    }

    /** @return array<string, mixed> */
    private function serializeSubscription(TenantSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'status' => $subscription->status->value,
            'package' => [
                'id' => $subscription->package->id,
                'tier' => $subscription->package->tier->value,
                'name' => $subscription->package->name,
                'price_monthly' => (float) $subscription->package->price_monthly,
            ],
            'current_period_start' => $subscription->current_period_start?->toJSON(),
            'current_period_end' => $subscription->current_period_end?->toJSON(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'cancelled_at' => $subscription->cancelled_at?->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrder(PaymentOrder $order): array
    {
        return [
            'order_no' => $order->order_no,
            'provider' => $order->provider,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'status' => $order->status->value,
            'checkout_url' => $order->metadata['checkout_url'] ?? null,
        ];
    }
}
