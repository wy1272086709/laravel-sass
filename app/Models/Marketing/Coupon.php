<?php

declare(strict_types=1);

namespace App\Models\Marketing;

use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\CouponType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 优惠券（租户域，营销）。
 * - type=full_reduction：discount_value=满减金额，min_amount=门槛
 * - type=discount：discount_value=折扣百分比（0-100）
 */
class Coupon extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'status' => CouponStatus::class,
            'discount_value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
