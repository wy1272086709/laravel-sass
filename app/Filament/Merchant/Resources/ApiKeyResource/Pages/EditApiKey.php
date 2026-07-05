<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\ApiKeyResource\Pages;

use App\Filament\Merchant\Resources\ApiKeyResource;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;
}
