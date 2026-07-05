<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 平台管理员（platform guard）。
 *
 * $fillable 与 SDD §2.2 / 平台后台用例一致；password 哈希存储。
 */
class PlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'platform_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'department',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<PlatformRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'role_id');
    }
}
