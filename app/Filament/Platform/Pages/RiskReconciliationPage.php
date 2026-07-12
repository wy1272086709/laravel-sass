<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;

class RiskReconciliationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = '运营看板';

    protected static ?string $navigationLabel = '风控对账';

    protected static ?string $title = '风控对账';

    protected static ?string $slug = 'risk-reconciliation';

    protected static string $view = 'filament.pages.vue-panel';

    public function getPanelMountId(): string
    {
        return 'risk-reconciliation-panel';
    }

    public function getPanelScript(): string
    {
        return 'resources/js/panels/risk-reconciliation.js';
    }
}
