<?php

declare(strict_types=1);

namespace App\Domain\Payments;

final class WebhookSignatureVerifier
{
    private const ALLOWED_DRIFT_SECONDS = 300;

    public function verify(string $provider, string $payload, string $timestamp, string $signature): bool
    {
        $secret = (string) config("payments.providers.{$provider}.webhook_secret", '');

        if ($secret === '' || ! ctype_digit($timestamp) || $signature === '') {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::ALLOWED_DRIFT_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }
}
