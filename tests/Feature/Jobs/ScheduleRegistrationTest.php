<?php

use Illuminate\Support\Facades\Artisan;

it('registers stage 5 scheduled jobs', function () {
    Artisan::call('schedule:list');

    $output = Artisan::output();

    expect($output)->toContain('MonthlyBillingJob')
        ->and($output)->toContain('ApiUsageFlushJob')
        ->and($output)->toContain('RiskRuleScanJob');
});
