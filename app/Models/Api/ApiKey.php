<?php

declare(strict_types=1);

namespace App\Models\Api;

use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Casts\AsEnumArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 开放 API 密钥（租户域）。
 * - app_secret 以 HASH 存储（明文仅创建时返回一次）。
 * - permissions 为 ApiPermission 枚举数组，经 AsEnumArrayObject 读写即得枚举集合。
 */
class ApiKey extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected $hidden = [
        'app_secret',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => AsEnumArrayObject::class . ':' . ApiPermission::class,
            'status' => ApiKeyStatus::class,
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<ApiRequestLog> */
    public function requestLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }
}
