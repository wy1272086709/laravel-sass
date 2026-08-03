<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Domain\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
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

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class, 'subscription_id');
    }
}
