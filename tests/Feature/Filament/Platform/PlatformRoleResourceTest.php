<?php

use App\Filament\Platform\Resources\PlatformRoleResource;
use App\Filament\Platform\Resources\PlatformRoleResource\Pages\CreatePlatformRole;
use App\Models\Platform\PlatformPermission;
use App\Models\Platform\PlatformRole;
use App\Models\Platform\PlatformUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

it('lets platform users browse role management pages', function () {
    $user = PlatformUser::factory()->create();
    $role = PlatformRole::query()->create([
        'name' => '运营管理员',
        'slug' => 'ops-admin',
    ]);

    actingAs($user, 'platform');

    $this->get(PlatformRoleResource::getUrl(panel: 'platform'))
        ->assertOk()
        ->assertSee('运营管理员');

    $this->get(PlatformRoleResource::getUrl('create', panel: 'platform'))->assertOk();
    $this->get(PlatformRoleResource::getUrl('edit', ['record' => $role], panel: 'platform'))->assertOk();
});

it('creates platform roles and syncs permissions', function () {
    $user = PlatformUser::factory()->create();
    $permission = PlatformPermission::query()->create([
        'name' => '商户管理',
        'slug' => 'tenant.manage',
        'group' => 'platform',
    ]);

    actingAs($user, 'platform');

    Livewire::test(CreatePlatformRole::class)
        ->fillForm([
            'name' => '测试角色',
            'slug' => 'test-role',
            'description' => '测试说明',
            'permissions' => [$permission->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = PlatformRole::query()->where('slug', 'test-role')->firstOrFail();

    expect($role->permissions()->whereKey($permission)->exists())->toBeTrue();
});
