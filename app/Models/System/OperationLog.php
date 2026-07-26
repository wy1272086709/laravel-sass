<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Domain\Enums\OperationAction;
use App\Models\Api\ApiKey;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Merchant\MerchantUser;
use App\Models\Platform\PlatformUser;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 业务操作审计日志（租户域，通用表）。
 * 写入入口：App\Support\OperationLogger（统一 withoutGlobalScopes + 显式 tenant_id）。
 */
class OperationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => OperationAction::class,
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<PlatformUser, $this> */
    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    /** @return BelongsTo<MerchantUser, $this> */
    public function merchantUser(): BelongsTo
    {
        return $this->belongsTo(MerchantUser::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
