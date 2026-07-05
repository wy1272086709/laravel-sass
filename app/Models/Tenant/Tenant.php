<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Domain\Enums\TenantStatus;
use App\Models\Api\ApiKey;
use App\Models\Api\ApiRequestLog;
use App\Models\Api\ApiUsageDaily;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Merchant\MerchantUser;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Risk\RiskAlert;
use App\Models\System\ImpersonationLog;
use App\Models\System\PackageChangeLog;
use App\Models\System\QueueJobLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 租户实体（不使用 BelongsToTenant；平台可查全量）。
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'commission_rate' => 'decimal:4',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return HasMany<MerchantUser>
     */
    public function merchantUsers(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    /** @return HasMany<Product> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Order> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<ApiKey> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /** @return HasMany<TenantBill> */
    public function bills(): HasMany
    {
        return $this->hasMany(TenantBill::class);
    }

    /** @return HasMany<RiskAlert> */
    public function riskAlerts(): HasMany
    {
        return $this->hasMany(RiskAlert::class);
    }

    /** @return HasMany<TenantSetting> */
    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /** @return HasMany<ApiRequestLog> */
    public function apiRequestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /** @return HasMany<ApiUsageDaily> */
    public function apiUsageDaily(): HasMany
    {
        return $this->hasMany(ApiUsageDaily::class);
    }

    /** @return HasMany<ReconciliationDiscrepancy> */
    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class);
    }

    /** @return HasMany<QueueJobLog> */
    public function queueJobLogs(): HasMany
    {
        return $this->hasMany(QueueJobLog::class);
    }

    /** @return HasMany<ImpersonationLog> */
    public function impersonationLogs(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class);
    }

    /** @return HasMany<PackageChangeLog> */
    public function packageChangeLogs(): HasMany
    {
        return $this->hasMany(PackageChangeLog::class);
    }
}
