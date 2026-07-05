<?php

use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth:platform'])->group(function () {
    Route::post('/platform/impersonate/stop', [ImpersonationController::class, 'stop'])
        ->name('platform.impersonate.stop');

    Route::post('/platform/impersonate/{tenant}', [ImpersonationController::class, 'start'])
        ->name('platform.impersonate.start');
});
