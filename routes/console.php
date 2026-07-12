<?php

use App\Jobs\ApiUsageFlushJob;
use App\Jobs\MonthlyBillingJob;
use App\Jobs\RiskRuleScanJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MonthlyBillingJob)->monthlyOn(1, '02:00');
Schedule::job(new ApiUsageFlushJob(now()->subDay()->toDateString()))->dailyAt('00:05');
Schedule::job(new RiskRuleScanJob)->hourly();
