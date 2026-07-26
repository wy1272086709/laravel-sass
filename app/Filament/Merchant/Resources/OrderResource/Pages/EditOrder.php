<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources\OrderResource\Pages;

use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Filament\Merchant\Resources\OrderResource;
use App\Jobs\SyncLogisticsJob;
use App\Support\OperationLogContext;
use App\Support\OperationLogger;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('status') && $this->record->status === OrderStatus::Shipped) {
            SyncLogisticsJob::dispatch($this->record->id)->afterCommit();
        }

        // 审计：Filament 后台编辑订单（不走状态机语义，统一记 Updated）
        if ($this->record->wasChanged()) {
            $originalStatus = $this->record->getOriginal('status');
            $userId = Auth::guard('merchant')->id();

            app(OperationLogger::class)->log(new OperationLogContext(
                tenantId: (int) $this->record->tenant_id,
                actorKind: 'merchant_user',
                actorId: $userId !== null ? (int) $userId : null,
                actorLabel: 'MerchantUser:'.$userId,
                subjectType: 'order',
                subjectId: $this->record->id,
                subjectLabel: $this->record->order_no,
                action: OperationAction::Updated,
                fromStatus: $originalStatus instanceof OrderStatus ? $originalStatus->value : $originalStatus,
                toStatus: $this->record->status?->value,
                payload: ['changed' => array_intersect_key(
                    $this->record->getChanges(),
                    array_flip(['status', 'buyer_name', 'total_amount', 'cancel_reason']),
                )],
            ));
        }
    }
}
