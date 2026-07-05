<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\ApiKeyResource;
use App\Filament\Merchant\Resources\ApiKeyResource\Pages\CreateApiKey;
use App\Models\Api\ApiKey;
use App\Models\Merchant\MerchantUser;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('merchant'));
});

it('lets merchant users browse only their own api keys without exposing secrets', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    ApiKey::factory()->forTenant($tenantA)->create(['name' => '本店 API', 'app_key' => 'AK_MINE']);
    ApiKey::factory()->forTenant($tenantB)->create(['name' => '其他 API', 'app_key' => 'AK_OTHER']);

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    $this->get(ApiKeyResource::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('本店 API')
        ->assertSee('AK_MINE')
        ->assertDontSee('其他 API')
        ->assertDontSee('secret');
});

it('creates api keys with hashed secrets and enum permissions', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(CreateApiKey::class)
        ->fillForm([
            'name' => 'Filament API',
            'app_key' => 'AK_FILAMENT',
            'app_secret' => 'plain-secret',
            'permissions' => ['product_query', 'bill_query'],
            'status' => 'enabled',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $apiKey = ApiKey::query()->where('app_key', 'AK_FILAMENT')->firstOrFail();

    expect($apiKey->tenant_id)->toBe($tenant->id)
        ->and(Hash::check('plain-secret', $apiKey->app_secret))->toBeTrue()
        ->and($apiKey->permissions[0]->value)->toBe('product_query');
});
