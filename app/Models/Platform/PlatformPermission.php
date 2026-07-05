<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 平台权限点（平台域）。平台后台 RBAC 权限目录。
 */
class PlatformPermission extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsToMany<PlatformRole>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformRole::class,
            'platform_role_permission',
            'permission_id',
            'role_id',
        );
    }
}
