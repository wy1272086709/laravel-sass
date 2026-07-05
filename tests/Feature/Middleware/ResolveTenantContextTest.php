<?php

use App\Domain\Tenant\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__resolve-tenant', function () {
        $ctx = app(TenantContext::class);

        return response()->json([
            'tenantId' => $ctx->tenantId,
            'impersonatorId' => $ctx->impersonatorId,
            'tier' => $ctx->tier->value,
        ]);
    })->middleware('resolve.tenant');
});

it('resolves a platform-view context for unauthenticated requests', function () {
    $this->get('/__resolve-tenant')
        ->assertOk()
        ->assertExactJson([
            'tenantId' => null,
            'impersonatorId' => null,
            'tier' => 'basic',
        ]);
});
