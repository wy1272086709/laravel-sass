<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\CouponResource\Pages;

use App\Filament\Merchant\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
