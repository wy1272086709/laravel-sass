<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\ProductResource\Pages;

use App\Filament\Merchant\Resources\ProductResource;
use App\Jobs\InventoryAlertJob;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->skus()->exists()) {
            $this->record->update([
                'price' => $this->record->skus()->min('price'),
                'stock' => $this->record->skus()->sum('stock'),
            ]);
        }

        InventoryAlertJob::dispatch($this->record->tenant_id)->afterCommit();
    }
}
