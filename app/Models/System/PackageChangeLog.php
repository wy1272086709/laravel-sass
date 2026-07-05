<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Domain\Enums\PackageChangeType;
use App\Models\Platform\Package;
use App\Models\Platform\PlatformUser;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 套餐变更日志（平台域，审计，不随租户级联删除）。
 */
class PackageChangeLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'change_type' => PackageChangeType::class,
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function fromPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'from_package_id');
    }

    /** @return BelongsTo<Package, $this> */
    public function toPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'to_package_id');
    }

    /** @return BelongsTo<PlatformUser, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'operator_id');
    }
}
