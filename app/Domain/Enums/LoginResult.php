<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 登录结果（平台域表 login_logs.result）。双 Guard 登录均可记录。
 */
enum LoginResult: string
{
    case Success = 'success';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => '成功',
            self::Failure => '失败',
        };
    }
}
