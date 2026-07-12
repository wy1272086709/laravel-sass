<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;

class PlatformDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = '运营看板';

    protected static ?string $navigationLabel = '平台仪表盘';

    protected static ?string $title = '平台仪表盘';

    protected static ?string $slug = 'platform-dashboard';

    protected static string $view = 'filament.pages.vue-panel';

    public function getPanelMountId(): string
    {
        return 'platform-dashboard-panel';
    }

    public function getPanelScript(): string
    {
        return 'resources/js/panels/platform-dashboard.js';
    }
}
