<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformPermission;
use App\Models\Platform\PlatformRole;
use App\Models\Platform\PlatformUser;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => '商户管理', 'slug' => 'tenant.manage', 'group' => 'platform'],
            ['name' => '套餐配置', 'slug' => 'package.manage', 'group' => 'platform'],
            ['name' => '角色权限', 'slug' => 'rbac.manage', 'group' => 'platform'],
            ['name' => 'API 监控', 'slug' => 'api.monitor', 'group' => 'ops'],
            ['name' => '队列中心', 'slug' => 'queue.manage', 'group' => 'ops'],
            ['name' => '风控对账', 'slug' => 'risk.reconcile', 'group' => 'ops'],
        ])->map(fn (array $attributes): PlatformPermission => PlatformPermission::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes,
        ));

        $role = PlatformRole::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => '超级管理员',
                'description' => '拥有平台后台全部管理权限',
            ],
        );

        $role->permissions()->sync($permissions->pluck('id')->all());

        PlatformUser::query()->updateOrCreate(
            ['email' => 'admin@saas.test'],
            [
                'name' => '平台管理员',
                'password' => 'password',
                'phone' => '13800000000',
                'department' => '运营',
                'role_id' => $role->id,
            ],
        );
    }
}
