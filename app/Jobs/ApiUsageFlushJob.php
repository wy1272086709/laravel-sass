<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Redis\ApiDailyCounter;
use App\Models\Api\ApiUsageDaily;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApiUsageFlushJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $usageDate = null,
    ) {}

    public function handle(ApiDailyCounter $counter, QueueJobLogger $logger): void
    {
        $date = $this->usageDate ?? now()->subDay()->toDateString();

        Tenant::query()
            ->with('package')
            ->lazyById()
            ->each(function (Tenant $tenant) use ($counter, $date, $logger): void {
                $log = $logger->start(self::class, $tenant->id, ['tenant_id' => $tenant->id, 'usage_date' => $date]);

                try {
                    $requestCount = $counter->get($tenant->id, $date);
                    $quota = (int) ($tenant->package?->api_quota_daily ?? 0);

                    ApiUsageDaily::query()
                        ->withoutGlobalScopes()
                        ->updateOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'usage_date' => $date,
                            ],
                            [
                                'request_count' => $requestCount,
                                'overage_count' => max(0, $requestCount - $quota),
                            ],
                        );

                    $logger->success($log, [
                        'tenant_id' => $tenant->id,
                        'usage_date' => $date,
                        'request_count' => $requestCount,
                    ]);
                } catch (Throwable $throwable) {
                    $logger->failed($log, $throwable);

                    throw $throwable;
                }
            });
    }
}
