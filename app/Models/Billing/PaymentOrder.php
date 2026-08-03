<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Domain\Enums\PaymentOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentOrderStatus::class,
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }
}
