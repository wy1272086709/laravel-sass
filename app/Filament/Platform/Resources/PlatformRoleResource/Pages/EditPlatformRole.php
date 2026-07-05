<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlatformRoleResource\Pages;

use App\Filament\Platform\Resources\PlatformRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformRole extends EditRecord
{
    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
