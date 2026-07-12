<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;

class QueueOpsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = '运营看板';

    protected static ?string $navigationLabel = '队列中心';

    protected static ?string $title = '队列中心';

    protected static ?string $slug = 'queue-ops';

    protected static string $view = 'filament.pages.vue-panel';

    public function getPanelMountId(): string
    {
        return 'queue-ops-panel';
    }

    public function getPanelScript(): string
    {
        return 'resources/js/panels/queue-ops.js';
    }
}
