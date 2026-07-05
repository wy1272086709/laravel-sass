<?php

use App\Filament\Pages\Auth\UnifiedLogin;
use App\Models\Merchant\MerchantUser;
use App\Models\Platform\PlatformUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the unified login page on both panels', function () {
    Filament::setCurrentPanel(Filament::getPanel('platform'));

    $this->get('/platform/login')
        ->assertOk()
        ->assertSee('平台管理员')
        ->assertSee('商户用户');

    Filament::setCurrentPanel(Filament::getPanel('merchant'));

    $this->get('/merchant/login')
        ->assertOk()
        ->assertSee('平台管理员')
        ->assertSee('商户用户');
});

it('authenticates platform and merchant users with separate guards', function () {
    $platformUser = PlatformUser::factory()->create([
        'email' => 'platform-login@test.local',
        'password' => 'password',
    ]);
    $merchantUser = MerchantUser::factory()->create([
        'email' => 'merchant-login@test.local',
        'password' => 'password',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('platform'));

    Livewire::test(UnifiedLogin::class)
        ->fillForm([
            'email' => $platformUser->email,
            'password' => 'password',
            'remember' => false,
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->guard('platform')->check())->toBeTrue();

    auth()->guard('platform')->logout();
    session()->invalidate();
    session()->regenerateToken();

    Filament::setCurrentPanel(Filament::getPanel('merchant'));

    Livewire::test(UnifiedLogin::class)
        ->fillForm([
            'email' => $merchantUser->email,
            'password' => 'password',
            'remember' => false,
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->guard('merchant')->check())->toBeTrue();
});

it('lets platform users access their profile page', function () {
    $platformUser = PlatformUser::factory()->create();

    Filament::setCurrentPanel(Filament::getPanel('platform'));

    $this->actingAs($platformUser, 'platform')
        ->get('/platform/profile')
        ->assertOk()
        ->assertSee($platformUser->email);
});
