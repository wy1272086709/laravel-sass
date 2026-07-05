<?php

use App\Filament\Platform\Resources\PackageResource;
use App\Filament\Platform\Resources\PackageResource\Pages\CreatePackage;
use App\Models\Platform\Package;
use App\Models\Platform\PlatformUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

it('lets platform users browse package configuration pages', function () {
    $user = PlatformUser::factory()->create();
    $package = Package::factory()->create(['name' => '专业套餐']);

    actingAs($user, 'platform');

    $this->get(PackageResource::getUrl(panel: 'platform'))
        ->assertOk()
        ->assertSee('专业套餐');

    $this->get(PackageResource::getUrl('create', panel: 'platform'))->assertOk();
    $this->get(PackageResource::getUrl('edit', ['record' => $package], panel: 'platform'))->assertOk();
});

it('creates packages through the resource form', function () {
    $user = PlatformUser::factory()->create();

    actingAs($user, 'platform');

    Livewire::test(CreatePackage::class)
        ->fillForm([
            'tier' => 'enterprise',
            'name' => '企业版测试',
            'price_monthly' => 1299,
            'api_quota_daily' => 1_000_000,
            'merchant_limit' => 10,
            'features' => ['priority_support' => 'true'],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Package::query()->where('tier', 'enterprise')->exists())->toBeTrue();
});
