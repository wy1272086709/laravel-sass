<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Domain\Enums\PackageTier;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 套餐（平台域，无 tenant_id）。
 */
class Package extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tier' => PackageTier::class,
            'price_monthly' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Tenant>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
