<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;

class ApiMonitoringPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = '运营看板';

    protected static ?string $navigationLabel = 'API 监控';

    protected static ?string $title = 'API 监控';

    protected static ?string $slug = 'api-monitoring';

    protected static string $view = 'filament.pages.vue-panel';

    public function getPanelMountId(): string
    {
        return 'api-monitoring-panel';
    }

    public function getPanelScript(): string
    {
        return 'resources/js/panels/api-monitoring.js';
    }
}
