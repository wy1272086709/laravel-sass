<?php

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Platform\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Platform\Resources\TenantResource\Pages\ListTenants;
use App\Jobs\GenerateApiBillJob;
use App\Jobs\MerchantWelcomeEmailJob;
use App\Models\Merchant\MerchantUser;
use App\Models\Platform\Package;
use App\Models\Platform\PlatformUser;
use App\Models\System\ImpersonationLog;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

it('lets platform users browse tenant management pages', function () {
    $user = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();

    actingAs($user, 'platform');

    $this->get(TenantResource::getUrl(panel: 'platform'))
        ->assertOk()
        ->assertSee($tenant->name);

    $this->get(TenantResource::getUrl('create', panel: 'platform'))->assertOk();
    $this->get(TenantResource::getUrl('edit', ['record' => $tenant], panel: 'platform'))->assertOk();
});

it('creates and updates tenants through the resource forms', function () {
    Bus::fake([MerchantWelcomeEmailJob::class]);
    $user = PlatformUser::factory()->create();
    $package = Package::factory()->create(['name' => '测试套餐']);

    actingAs($user, 'platform');

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'merchant_code' => 'MHT-FILAMENT',
            'name' => 'Filament 商户',
            'contact_name' => '测试联系人',
            'contact_phone' => '13900009999',
            'package_id' => $package->id,
            'status' => 'enabled',
            'commission_rate' => '0.0200',
            'joined_at' => now(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tenant = Tenant::query()->where('merchant_code', 'MHT-FILAMENT')->firstOrFail();

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->fillForm([
            'name' => 'Filament 商户更新',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->refresh()->name)->toBe('Filament 商户更新');
    Bus::assertDispatched(MerchantWelcomeEmailJob::class, fn (MerchantWelcomeEmailJob $job): bool => $job->tenantId === $tenant->id);
});

it('registers the expected tenant resource pages', function () {
    expect(TenantResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(class_exists(ListTenants::class))->toBeTrue();
});

it('starts impersonation from the tenant table action', function () {
    $platformUser = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $merchantUser = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($platformUser, 'platform');

    Livewire::test(ListTenants::class)
        ->callTableAction('impersonate', $tenant)
        ->assertRedirect('/merchant');

    expect(auth()->guard('merchant')->id())->toBe($merchantUser->id)
        ->and(session('impersonated_by'))->toBe($platformUser->id)
        ->and(ImpersonationLog::query()->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('queues bill generation for a selected tenant and period', function () {
    Bus::fake([GenerateApiBillJob::class]);
    $platformUser = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();

    actingAs($platformUser, 'platform');

    Livewire::test(ListTenants::class)
        ->callTableAction('generateApiBill', $tenant, data: ['period' => '2026-06'])
        ->assertHasNoTableActionErrors();

    Bus::assertDispatched(GenerateApiBillJob::class, fn (GenerateApiBillJob $job): bool => $job->tenantId === $tenant->id && $job->period === '2026-06');
});
