<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant\Tenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 商户用户（merchant guard，租户域，带 tenant_id + TenantScope）。
 */
class MerchantUser extends Authenticatable implements FilamentUser
{
    use BelongsToTenant, HasApiTokens, HasFactory;

    protected $table = 'merchant_users';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'merchant' && $this->is_active;
    }
}
