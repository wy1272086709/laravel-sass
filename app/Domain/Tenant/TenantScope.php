<?php

declare(strict_types=1);

namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 全局租户 Scope：所有带 tenant_id 的模型默认按当前 TenantContext 过滤。
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        if ($context->tenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', $context->tenantId);
        }
    }
}

