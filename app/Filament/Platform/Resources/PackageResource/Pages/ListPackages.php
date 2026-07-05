<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PackageResource\Pages;

use App\Filament\Platform\Resources\PackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
