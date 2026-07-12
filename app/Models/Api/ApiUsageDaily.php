<?php

declare(strict_types=1);

namespace App\Models\Api;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API 日用量落库（租户域）。ApiUsageFlushJob 日终从 Redis 计数写入。
 */
class ApiUsageDaily extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'api_usage_daily';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
