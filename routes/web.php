<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Internal\ApiMonitoringController;
use App\Http\Controllers\Internal\PlatformDashboardController;
use App\Http\Controllers\Internal\QueueOpsController;
use App\Http\Controllers\Internal\RiskReconciliationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth:platform'])->group(function () {
    Route::post('/platform/impersonate/stop', [ImpersonationController::class, 'stop'])
        ->name('platform.impersonate.stop');

    Route::post('/platform/impersonate/{tenant}', [ImpersonationController::class, 'start'])
        ->name('platform.impersonate.start');

    Route::prefix('/internal/queue-ops')->name('internal.queue-ops.')->group(function () {
        Route::get('/summary', [QueueOpsController::class, 'summary'])->name('summary');
        Route::get('/jobs', [QueueOpsController::class, 'jobs'])->name('jobs');
        Route::get('/dead-letters', [QueueOpsController::class, 'deadLetters'])->name('dead-letters');
        Route::get('/delayed', [QueueOpsController::class, 'delayed'])->name('delayed');
    });

    Route::prefix('/api/internal/platform')->name('internal.platform.')->group(function () {
        Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
        Route::get('/api-monitor', ApiMonitoringController::class)->name('api-monitor');
        Route::get('/queue-ops', [QueueOpsController::class, 'panel'])->name('queue-ops');
        Route::get('/risk-recon', RiskReconciliationController::class)->name('risk-recon');
    });
});
