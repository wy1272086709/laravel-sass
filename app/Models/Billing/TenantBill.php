<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Domain\Enums\BillStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 租户月度账单（租户域）。本期仅状态流转（A 方案）；payment_* 为 C 预留。
 */
class TenantBill extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'api_usage_fee' => 'decimal:2',
            'api_overage_fee' => 'decimal:2',
            'total_receivable' => 'decimal:2',
            'merchant_reported_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'status' => BillStatus::class,
            'paid_at' => 'datetime',
            'payment_meta' => 'array',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<ReconciliationDiscrepancy> */
    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class, 'tenant_bill_id');
    }
}
