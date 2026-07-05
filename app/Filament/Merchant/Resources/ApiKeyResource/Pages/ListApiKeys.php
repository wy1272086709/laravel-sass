<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\ApiKeyResource\Pages;

use App\Filament\Merchant\Resources\ApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
