<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait BelongsToTenant
 *
 * - 自动注册 TenantScope
 * - 创建时自动填充 tenant_id（来自 TenantContext）
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if (property_exists($model, 'tenant_id') && $model->tenant_id === null) {
                $model->tenant_id = $context->tenantId;
            }
        });
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }
}

