<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlatformRoleResource\Pages;

use App\Filament\Platform\Resources\PlatformRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformRole extends CreateRecord
{
    protected static string $resource = PlatformRoleResource::class;
}
