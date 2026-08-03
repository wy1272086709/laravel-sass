<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Payments\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $eventId) {}

    public function handle(WebhookProcessor $processor): void
    {
        $processor->process($this->eventId);
    }
}
