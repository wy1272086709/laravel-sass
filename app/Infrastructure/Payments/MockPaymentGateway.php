<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Domain\Payments\PaymentGateway;
use App\Models\Billing\PaymentOrder;

final class MockPaymentGateway implements PaymentGateway
{
    public function createCheckout(PaymentOrder $order): array
    {
        return [
            'external_payment_id' => 'mock_pay_'.$order->order_no,
            'checkout_url' => rtrim((string) config('app.url'), '/').'/mock-payments/checkout/'.$order->order_no,
        ];
    }
}
