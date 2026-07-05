<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OrderResource\Pages;

use App\Filament\Merchant\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
}
