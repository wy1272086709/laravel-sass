<?php

/**
 * 一次性脚本：为开放 API 对接测试建立完整链路（幂等，可反复执行）。
 *
 * 数据库当前为空，仅向 personal_access_tokens 插一条记录无法通过 api.auth 中间件
 * （中间件要求 token 的 tokenable 是一个启用的 ApiKey，且 ApiKey 归属于有效租户）。
 * 因此这里一并建立：Package -> Tenant -> ApiKey -> Sanctum AccessToken。
 *
 * 运行：docker exec laravelproj-app php storage/setup_test_token.php
 */

use App\Domain\Api\AccessTokenService;
use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\PackageTier;
use App\Domain\Enums\TenantStatus;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// ---- 固定的测试凭据（便于记忆与反复对接） ----
$merchantCode      = 'MHT-TEST-0001';
$appKey            = 'AK_TEST_0001';
$appSecretPlain    = 'SK_TEST_0001_secret';
$signingSecretRaw  = 'SIGNING_SECRET_TEST_0001';

$result = DB::transaction(function () use (
    $merchantCode, $appKey, $appSecretPlain, $signingSecretRaw
) {
    // 1. 清理旧测试数据（幂等重跑）
    $oldTenant = Tenant::query()->withTrashed()->where('merchant_code', $merchantCode)->first();
    if ($oldTenant !== null) {
        $oldKeyIds = ApiKey::query()->where('tenant_id', $oldTenant->id)->pluck('id')->all();
        if ($oldKeyIds !== []) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', ApiKey::class)
                ->whereIn('tokenable_id', $oldKeyIds)
                ->delete();
        }
        $oldTenant->forceDelete(); // 级联删除 api_keys（FK cascadeOnDelete）
    }

    // 2. 套餐（平台域，企业版，配额宽松）
    $package = Package::query()->firstOrCreate(
        ['tier' => PackageTier::Enterprise->value],
        [
            'name' => '企业版（测试）',
            'price_monthly' => 999.00,
            'api_quota_daily' => 100000,
            'merchant_limit' => 100,
            'features' => [],
            'is_active' => true,
        ]
    );

    // 3. 租户
    $tenant = Tenant::query()->create([
        'merchant_code' => $merchantCode,
        'name' => '测试商户 0001',
        'contact_name' => '测试联系人',
        'contact_phone' => '13800000000',
        'package_id' => $package->id,
        'status' => TenantStatus::Enabled->value,
        'commission_rate' => 0.0200,
        'joined_at' => now(),
    ]);

    // 4. API 密钥
    //    - app_secret 以 HASH 存储（Hash::check 兼容），列上无 hashed cast，需手动 Hash::make()
    //    - signing_secret 经 encrypted cast 自动加解密，赋明文即可
    $apiKey = ApiKey::query()->create([
        'tenant_id' => $tenant->id,
        'name' => '测试对接密钥',
        'app_key' => $appKey,
        'app_secret' => Hash::make($appSecretPlain),
        'signing_secret' => $signingSecretRaw,
        'permissions' => [
            ApiPermission::ProductQuery,
            ApiPermission::OrderManage,
            ApiPermission::DashboardRead,
            ApiPermission::BillQuery,
        ],
        'status' => ApiKeyStatus::Enabled->value,
    ]);

    // 5. AccessToken（走 Sanctum createToken，确保 token 列哈希格式与 findToken 一致）
    $abilities = [
        ApiPermission::ProductQuery->value,
        ApiPermission::OrderManage->value,
        ApiPermission::DashboardRead->value,
        ApiPermission::BillQuery->value,
    ];
    $token = $apiKey->createToken(
        AccessTokenService::ACCESS_TOKEN_NAME, // 'api-access' —— 中间件强校验此 name
        $abilities,
        now()->addMinutes(AccessTokenService::ACCESS_TOKEN_TTL_MINUTES), // 120 分钟
    );

    return [
        'tenant_id' => $tenant->id,
        'api_key_id' => $apiKey->id,
        'token_id' => $token->accessToken->id,
        'app_key' => $appKey,
        'app_secret_plain' => $appSecretPlain,
        'signing_secret_plain' => $signingSecretRaw,
        'bearer' => $token->plainTextToken,
        'abilities' => $abilities,
        'expires_at' => $token->accessToken->expires_at?->toDateTimeString(),
    ];
});

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
