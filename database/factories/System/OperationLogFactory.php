<?php

namespace Database\Factories\System;

use App\Domain\Enums\OperationAction;
use App\Models\System\OperationLog;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationLog>
 */
class OperationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'actor_kind' => 'system',
            'actor_label' => '系统',
            'subject_type' => 'order',
            'subject_id' => null,
            'subject_label' => null,
            'action' => OperationAction::Created,
            'from_status' => null,
            'to_status' => null,
            'payload' => [],
        ];
    }
}
