<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OrderResource\Pages;

use App\Domain\Enums\OrderStatus;
use App\Filament\Merchant\Resources\OrderResource;
use App\Jobs\SyncLogisticsJob;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('status') && $this->record->status === OrderStatus::Shipped) {
            SyncLogisticsJob::dispatch($this->record->id)->afterCommit();
        }
    }
}
