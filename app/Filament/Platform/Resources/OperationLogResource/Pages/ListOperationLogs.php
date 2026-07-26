<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\OperationLogResource\Pages;

use App\Filament\Platform\Resources\OperationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationLogs extends ListRecords
{
    protected static string $resource = OperationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
