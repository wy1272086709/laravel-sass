<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 优惠券状态（租户域表 coupons.status）。
 */
enum CouponStatus: string
{
    case NotStarted = 'not_started';
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => '未开始',
            self::Active => '进行中',
            self::Ended => '已结束',
        };
    }
}
