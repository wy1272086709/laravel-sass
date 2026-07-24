<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Jobs\MerchantWelcomeEmailJob;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        MerchantWelcomeEmailJob::dispatch($this->record->id)->afterCommit();
    }
}
