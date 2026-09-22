<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OrderResource\Pages;

use App\Filament\Merchant\Resources\OrderResource;
use App\Jobs\CloseExpiredOrderJob;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::guard('merchant')->id()
            ? Auth::guard('merchant')->user()->tenant_id
            : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        CloseExpiredOrderJob::dispatch($this->record->id)->delay(now()->addMinutes(30))->afterCommit();
    }
}
