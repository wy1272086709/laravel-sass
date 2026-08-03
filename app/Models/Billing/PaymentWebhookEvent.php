<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Domain\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
