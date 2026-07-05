<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\TenantBillResource\Pages;

use App\Filament\Merchant\Resources\TenantBillResource;
use Filament\Resources\Pages\EditRecord;

class EditTenantBill extends EditRecord
{
    protected static string $resource = TenantBillResource::class;
}
