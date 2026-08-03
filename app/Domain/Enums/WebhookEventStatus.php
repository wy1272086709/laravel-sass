<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum WebhookEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
