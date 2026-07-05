<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Domain\Enums\QueueJobStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 队列任务日志（租户域，队列中心面板数据源）。
 */
class QueueJobLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => QueueJobStatus::class,
            'payload' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
