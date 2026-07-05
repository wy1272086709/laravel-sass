<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Domain\Enums\LoginResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 登录日志（平台域，记录双 Guard 登录尝试含失败）。
 */
class LoginLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => LoginResult::class,
            'logged_at' => 'datetime',
        ];
    }
}
