<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Merchant\MerchantUser;
use App\Models\Platform\PlatformUser;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impersonation 审计日志（租户域）。平台管理员进入商户后台全程留痕。
 */
class ImpersonationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
}
