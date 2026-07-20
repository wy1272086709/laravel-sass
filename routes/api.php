<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api.exception'])
    ->group(function () {
        Route::post('/auth/token', [AuthController::class, 'token']);
        Route::post('/auth/token/refresh', [AuthController::class, 'refresh']);
        Route::delete('/auth/token', [AuthController::class, 'revoke'])
            ->middleware('api.auth');

        Route::get('/ping', fn (TenantContext $context) => response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'pong' => true,
                'tenant_id' => $context->tenantId,
                'tier' => $context->tier->value,
            ],
        ]))->middleware('api.auth');

        Route::middleware(['api.auth:product_query', 'api.rate'])->group(function () {
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/products/{product}', [ProductController::class, 'show']);
        });

        Route::middleware(['api.auth:order_manage'])->group(function () {
            Route::post('/products', [ProductController::class, 'store'])
                ->middleware(['api.signature', 'api.idempotent', 'api.rate']);
            Route::put('/products/{product}', [ProductController::class, 'update'])
                ->middleware(['api.signature', 'api.rate']);
            Route::patch('/products/{product}/status', [ProductController::class, 'status'])
                ->middleware(['api.signature', 'api.rate']);

            Route::get('/orders', [OrderController::class, 'index'])
                ->middleware('api.rate');
            Route::post('/orders', [OrderController::class, 'store'])
                ->middleware(['api.signature', 'api.idempotent', 'api.rate']);
            Route::get('/orders/{orderNo}', [OrderController::class, 'show'])
                ->middleware('api.rate');
            Route::post('/orders/{orderNo}/ship', [OrderController::class, 'ship'])
                ->middleware(['api.signature', 'api.idempotent', 'api.rate']);
            Route::post('/orders/{orderNo}/cancel', [OrderController::class, 'cancel'])
                ->middleware(['api.signature', 'api.idempotent', 'api.rate']);
            Route::post('/orders/{orderNo}/refund', [OrderController::class, 'refund'])
                ->middleware(['api.signature', 'api.idempotent', 'api.rate']);
        });

        Route::middleware(['api.auth:bill_query', 'api.rate'])->group(function () {
            Route::get('/bills', [BillController::class, 'index']);
            Route::get('/bills/{period}', [BillController::class, 'show']);
        });

        Route::middleware(['api.auth:dashboard_read', 'api.rate'])->group(function () {
            Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
            Route::get('/dashboard/trends', [DashboardController::class, 'trends']);
        });
    });
