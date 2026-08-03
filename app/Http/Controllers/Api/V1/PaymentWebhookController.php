<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\WebhookEventStatus;
use App\Domain\Payments\WebhookSignatureVerifier;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Billing\PaymentWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        string $provider,
        Request $request,
        WebhookSignatureVerifier $verifier,
    ): JsonResponse {
        abort_unless(config("payments.providers.{$provider}") !== null, 404);

        $payload = $request->getContent();
        $timestamp = (string) $request->header('X-Payment-Timestamp', '');
        $signature = (string) $request->header('X-Payment-Signature', '');

        if (! $verifier->verify($provider, $payload, $timestamp, $signature)) {
            return ApiResponse::error(40120, 'Invalid payment webhook signature', 401);
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! is_string($decoded['id'] ?? null) || ! is_string($decoded['type'] ?? null)) {
            return ApiResponse::error(40020, 'Invalid payment webhook payload', 400);
        }

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            [
                'provider' => $provider,
                'event_id' => $decoded['id'],
            ],
            [
                'event_type' => $decoded['type'],
                'status' => WebhookEventStatus::Pending,
                'payload' => $decoded,
                'payload_hash' => hash('sha256', $payload),
                'received_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            if (! hash_equals($event->payload_hash, hash('sha256', $payload))) {
                return ApiResponse::error(40920, 'Webhook event ID was reused with a different payload', 409);
            }

            return ApiResponse::ok([
                'event_id' => $event->event_id,
                'duplicate' => true,
                'status' => $event->status->value,
            ]);
        }

        ProcessPaymentWebhookJob::dispatch($event->id)->afterCommit();

        return ApiResponse::ok([
            'event_id' => $event->event_id,
            'duplicate' => false,
            'status' => WebhookEventStatus::Pending->value,
        ], 202);
    }
}
