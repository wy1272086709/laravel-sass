<?php

namespace Database\Factories\System;

use App\Domain\Enums\QueueJobStatus;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueueJobLog>
 */
class QueueJobLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'job_uuid' => (string) Str::uuid(),
            'name' => $this->faker->randomElement(['MonthlyBillingJob', 'ApiUsageFlushJob']),
            'queue' => 'default',
            'status' => QueueJobStatus::Pending,
            'attempts' => 0,
            'payload' => [],
            'queued_at' => now(),
        ];
    }
}
