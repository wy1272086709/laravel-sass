<?php

use App\Jobs\ApiUsageFlushJob;
use App\Jobs\MonthlyBillingJob;
use App\Jobs\RiskRuleScanJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\TestJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MonthlyBillingJob)->monthlyOn(1, '02:00');
Schedule::job(new ApiUsageFlushJob)->dailyAt('00:05');
Schedule::job(new RiskRuleScanJob)->hourly();
Schedule::job(new TestJob)
    ->name('test-job')
    ->withoutOverlapping(60)
    ->onFailure(function (Throwable $throwable) {
        logger()->error('TestJob 执行失败，'.$throwable->getMessage());
    })
    ->everyMinute();
