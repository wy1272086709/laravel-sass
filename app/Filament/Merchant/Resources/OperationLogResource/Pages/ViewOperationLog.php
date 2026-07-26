<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OperationLogResource\Pages;

use App\Filament\Merchant\Resources\OperationLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationLog extends ViewRecord
{
    protected static string $resource = OperationLogResource::class;
}
