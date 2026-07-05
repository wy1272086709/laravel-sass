<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 平台角色（平台域）。自定义 RBAC，pivot 表 platform_role_permission（单数）。
 */
class PlatformRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsToMany<PlatformPermission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformPermission::class,
            'platform_role_permission',
            'role_id',
            'permission_id',
        );
    }

    /**
     * @return HasMany<PlatformUser>
     */
    public function users(): HasMany
    {
        return $this->hasMany(PlatformUser::class, 'role_id');
    }
}
