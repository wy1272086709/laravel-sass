<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\TenantBillResource\Pages;

use App\Filament\Merchant\Resources\TenantBillResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantBills extends ListRecords
{
    protected static string $resource = TenantBillResource::class;
}
