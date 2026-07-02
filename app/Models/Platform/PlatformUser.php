<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 平台管理员（platform guard）。
 *
 * 极简骨架：仅满足双 Guard 与 ResolveTenantContext 解析。
 * $fillable / HasApiTokens / 迁移 / Factory 留待阶段 2（数据持久层）落地。
 */
class PlatformUser extends Authenticatable
{
    protected $table = 'platform_users';

    protected $guarded = [];
}
