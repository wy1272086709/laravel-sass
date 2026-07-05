<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PackageResource\Pages;

use App\Filament\Platform\Resources\PackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePackage extends CreateRecord
{
    protected static string $resource = PackageResource::class;
}
