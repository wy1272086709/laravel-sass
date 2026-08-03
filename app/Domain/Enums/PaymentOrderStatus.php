<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum PaymentOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
