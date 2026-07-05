<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 优惠券类型（租户域表 coupons.type）。
 */
enum CouponType: string
{
    case FullReduction = 'full_reduction';
    case Discount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::FullReduction => '满减',
            self::Discount => '折扣',
        };
    }
}
