<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OrderResource\Pages;

use App\Filament\Merchant\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
