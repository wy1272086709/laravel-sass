<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Domain\Enums\ReconciliationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 对账差异单（租户域）。BillSettlementService 在实报≠应收时生成。
 */
class ReconciliationDiscrepancy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'difference_amount' => 'decimal:2',
            'status' => ReconciliationStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<TenantBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(TenantBill::class, 'tenant_bill_id');
    }
}
