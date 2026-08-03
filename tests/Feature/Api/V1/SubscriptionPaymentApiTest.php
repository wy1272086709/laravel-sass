<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\PaymentOrderStatus;
use App\Domain\Enums\SubscriptionStatus;
use App\Domain\Enums\WebhookEventStatus;
use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Models\Api\ApiKey;
use App\Models\Billing\PaymentOrder;
use App\Models\Billing\PaymentWebhookEvent;
use App\Models\Billing\TenantSubscription;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ApiRateLimitMiddleware::class);
    config()->set('payments.providers.mock.webhook_secret', 'test-webhook-secret');
});

it('creates a pending subscription checkout without changing the active package', function () {
    [$tenant, $token, $apiKey] = subscriptionApiToken();
    $targetPackage = Package::factory()->create(['price_monthly' => 399]);
    $payload = ['package_id' => $targetPackage->id];

    signedApiJson(
        'POST',
        '/api/v1/subscription/checkout',
        $token,
        $apiKey,
        $payload,
        'subscription-checkout-001',
    )
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentOrderStatus::Pending->value)
        ->assertJsonPath('data.amount', 399)
        ->assertJsonPath('data.provider', 'mock');

    expect($tenant->refresh()->package_id)->not->toBe($targetPackage->id)
        ->and(PaymentOrder::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(TenantSubscription::query()->withoutGlobalScopes()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('activates a subscription after a valid paid webhook', function () {
    [$tenant, $token, $apiKey] = subscriptionApiToken();
    $targetPackage = Package::factory()->create(['price_monthly' => 399]);
    $orderNo = signedApiJson(
        'POST',
        '/api/v1/subscription/checkout',
        $token,
        $apiKey,
        ['package_id' => $targetPackage->id],
        'subscription-checkout-002',
    )->assertCreated()->json('data.order_no');

    $payload = paymentWebhookPayload('evt-paid-001', 'checkout.completed', $orderNo);
    paymentWebhookRequest($payload)
        ->assertAccepted()
        ->assertJsonPath('data.duplicate', false);

    $order = PaymentOrder::query()->withoutGlobalScopes()->where('order_no', $orderNo)->firstOrFail();
    $subscription = TenantSubscription::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($order->status)->toBe(PaymentOrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->package_id)->toBe($targetPackage->id)
        ->and($tenant->refresh()->package_id)->toBe($targetPackage->id)
        ->and(PaymentWebhookEvent::query()->first()->status)->toBe(WebhookEventStatus::Processed);
});

it('acknowledges a duplicate webhook without processing it twice', function () {
    [$tenant, $token, $apiKey] = subscriptionApiToken();
    $targetPackage = Package::factory()->create();
    $orderNo = signedApiJson(
        'POST',
        '/api/v1/subscription/checkout',
        $token,
        $apiKey,
        ['package_id' => $targetPackage->id],
        'subscription-checkout-003',
    )->json('data.order_no');
    $payload = paymentWebhookPayload('evt-duplicate-001', 'checkout.completed', $orderNo);

    paymentWebhookRequest($payload)->assertAccepted();
    paymentWebhookRequest($payload)
        ->assertOk()
        ->assertJsonPath('data.duplicate', true)
        ->assertJsonPath('data.status', WebhookEventStatus::Processed->value);

    expect(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->first()->attempts)->toBe(1)
        ->and(PaymentOrder::query()->withoutGlobalScopes()->where('status', PaymentOrderStatus::Paid)->count())->toBe(1);
});

it('rejects an invalid signature without storing the webhook', function () {
    $payload = paymentWebhookPayload('evt-invalid-signature', 'checkout.completed', 'SUB-MISSING');
    $timestamp = (string) now()->timestamp;

    $this->call('POST', '/api/v1/payments/webhooks/mock', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYMENT_TIMESTAMP' => $timestamp,
        'HTTP_X_PAYMENT_SIGNATURE' => 'invalid',
    ], $payload)
        ->assertStatus(401)
        ->assertJsonPath('code', 40120);

    expect(PaymentWebhookEvent::query()->count())->toBe(0);
});

it('rejects reusing a webhook event id with a different payload', function () {
    [$tenant, $token, $apiKey] = subscriptionApiToken();
    $targetPackage = Package::factory()->create();
    $orderNo = signedApiJson(
        'POST',
        '/api/v1/subscription/checkout',
        $token,
        $apiKey,
        ['package_id' => $targetPackage->id],
        'subscription-checkout-004',
    )->json('data.order_no');

    paymentWebhookRequest(paymentWebhookPayload('evt-reused-001', 'checkout.completed', $orderNo))
        ->assertAccepted();
    paymentWebhookRequest(paymentWebhookPayload('evt-reused-001', 'payment.refunded', $orderNo))
        ->assertStatus(409)
        ->assertJsonPath('code', 40920);
});

/** @return array{0: Tenant, 1: string, 2: ApiKey} */
function subscriptionApiToken(): array
{
    $package = Package::factory()->create();
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_SUBSCRIPTION_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'signing_secret' => 'plain-secret',
        'permissions' => [ApiPermission::SubscriptionManage],
    ]);
    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();

    return [$tenant, $response->json('data.access_token'), $apiKey];
}

function paymentWebhookPayload(string $eventId, string $eventType, string $orderNo): string
{
    return (string) json_encode([
        'id' => $eventId,
        'type' => $eventType,
        'data' => [
            'object' => [
                'order_no' => $orderNo,
                'payment_id' => 'pay-'.$eventId,
                'customer_id' => 'cus-test-001',
                'subscription_id' => 'sub-'.$orderNo,
                'paid_at' => now()->toJSON(),
                'current_period_end' => now()->addMonth()->toJSON(),
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function paymentWebhookRequest(string $payload): TestResponse
{
    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'test-webhook-secret');

    return test()->call('POST', '/api/v1/payments/webhooks/mock', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYMENT_TIMESTAMP' => $timestamp,
        'HTTP_X_PAYMENT_SIGNATURE' => $signature,
    ], $payload);
}
