<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Models\Billing\PaymentOrder;

interface PaymentGateway
{
    /** @return array{external_payment_id: string, checkout_url: string} */
    public function createCheckout(PaymentOrder $order): array;
}
