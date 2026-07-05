<?php

use App\Models\Merchant\MerchantUser;
use App\Models\Platform\PlatformUser;
use App\Models\System\ImpersonationLog;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('starts impersonation by logging into merchant guard and writing an audit log', function () {
    $platformUser = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $merchantUser = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($platformUser, 'platform');

    $this->post(route('platform.impersonate.start', $tenant), [
        'reason' => '协助排查订单',
    ])
        ->assertRedirect('/merchant');

    expect(auth()->guard('platform')->check())->toBeTrue()
        ->and(auth()->guard('merchant')->id())->toBe($merchantUser->id)
        ->and(session('impersonated_by'))->toBe($platformUser->id);

    $log = ImpersonationLog::query()->firstOrFail();

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->platform_user_id)->toBe($platformUser->id)
        ->and($log->merchant_user_id)->toBe($merchantUser->id)
        ->and($log->reason)->toBe('协助排查订单')
        ->and($log->ended_at)->toBeNull();
});

it('stops impersonation and keeps the platform guard available', function () {
    $platformUser = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $merchantUser = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($platformUser, 'platform');

    $this->post(route('platform.impersonate.start', $tenant));

    $this->post(route('platform.impersonate.stop'))
        ->assertRedirect('/platform');

    expect(auth()->guard('platform')->check())->toBeTrue()
        ->and(auth()->guard('merchant')->check())->toBeFalse()
        ->and(session()->has('impersonated_by'))->toBeFalse();

    $log = ImpersonationLog::query()
        ->where('merchant_user_id', $merchantUser->id)
        ->firstOrFail();

    expect($log->ended_at)->not->toBeNull();
});
