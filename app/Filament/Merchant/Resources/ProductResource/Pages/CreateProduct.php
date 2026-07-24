<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\ProductResource\Pages;

use App\Filament\Merchant\Resources\ProductResource;
use App\Jobs\InventoryAlertJob;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $this->syncSkuSummary();
        InventoryAlertJob::dispatch($this->record->tenant_id)->afterCommit();
    }

    private function syncSkuSummary(): void
    {
        if ($this->record->skus()->exists()) {
            $this->record->update([
                'price' => $this->record->skus()->min('price'),
                'stock' => $this->record->skus()->sum('stock'),
            ]);
        }
    }
}
