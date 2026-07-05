<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Models\Merchant\MerchantUser;
use App\Models\System\ImpersonationLog;
use App\Models\Tenant\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $platformUser = Auth::guard('platform')->user();
        abort_unless($platformUser !== null, 403);

        $merchantUser = MerchantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->firstOrFail();

        Auth::guard('merchant')->login($merchantUser);

        $log = ImpersonationLog::query()->create([
            'tenant_id' => $tenant->id,
            'platform_user_id' => $platformUser->id,
            'merchant_user_id' => $merchantUser->id,
            'started_at' => now(),
            'reason' => $request->string('reason')->toString() ?: null,
        ]);

        $request->session()->put('impersonated_by', $platformUser->id);
        $request->session()->put('impersonation_log_id', $log->id);

        app()->instance(
            TenantContext::class,
            new TenantContext($tenant->id, $platformUser->id, $tenant->package?->tier ?? PackageTier::Basic),
        );

        return redirect('/merchant');
    }

    public function stop(Request $request): RedirectResponse
    {
        $logId = $request->session()->pull('impersonation_log_id');

        if ($logId !== null) {
            ImpersonationLog::query()
                ->whereKey($logId)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
        }

        $request->session()->forget('impersonated_by');
        Auth::guard('merchant')->logout();

        return redirect('/platform');
    }
}
