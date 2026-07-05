<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Contracts\Support\Htmlable;

class UnifiedLogin extends Login
{
    protected static string $view = 'filament.pages.auth.unified-login';

    public function getTitle(): string|Htmlable
    {
        return Filament::getCurrentPanel()->getId() === 'platform' ? '平台后台登录' : '商户后台登录';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getPanelSwitchUrl(string $panel): string
    {
        return Filament::getPanel($panel)->getLoginUrl();
    }
}
