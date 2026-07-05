<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\CouponResource\Pages;

use App\Filament\Merchant\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;
}
