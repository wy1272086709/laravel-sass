<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MerchantWelcomeEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $tenantId,
    ) {}

    public function handle(QueueJobLogger $logger): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $log = $logger->start(self::class, $tenant->id, ['tenant_id' => $tenant->id], 'notification');

        $logger->success($log, [
            'tenant_id' => $tenant->id,
            'contact_phone' => $tenant->contact_phone,
            'mock_sent' => true,
        ]);
    }
}
