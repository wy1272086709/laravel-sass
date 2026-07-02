<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 商户用户（merchant guard）。
 *
 * 极简骨架：仅满足双 Guard 与 ResolveTenantContext 解析。
 * 阶段 2 将补：tenant_id 外键、BelongsToTenant、$fillable、密码 hash cast、
 * Factory 与 merchant_users 迁移。
 */
class MerchantUser extends Authenticatable
{
    protected $table = 'merchant_users';

    protected $guarded = [];
}
