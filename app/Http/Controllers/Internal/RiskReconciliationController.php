<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Domain\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Risk\RiskAlert;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;

class RiskReconciliationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $alertsByDate = RiskAlert::query()
            ->withoutGlobalScopes()
            ->selectRaw('DATE(triggered_at) as alert_date, COUNT(*) as total')
            ->whereBetween('triggered_at', [$start, $end])
            ->groupBy('alert_date')
            ->get()
            ->keyBy('alert_date');

        $dates = collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->values();

        $levelCounts = RiskAlert::query()
            ->withoutGlobalScopes()
            ->selectRaw('risk_level, COUNT(*) as total')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'alert_trend' => [
                    'dates' => $dates->all(),
                    'counts' => $dates->map(fn (string $date): int => (int) ($alertsByDate->get($date)?->total ?? 0))->all(),
                ],
                'level_distribution' => collect(RiskLevel::cases())
                    ->mapWithKeys(fn (RiskLevel $level): array => [$level->value => (int) ($levelCounts[$level->value] ?? 0)])
                    ->all(),
                'recent_alerts' => RiskAlert::query()
                    ->withoutGlobalScopes()
                    ->with('tenant')
                    ->latest('triggered_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (RiskAlert $alert): array => [
                        'id' => $alert->id,
                        'tenant_name' => $alert->tenant?->name,
                        'type' => $alert->type->value,
                        'risk_level' => $alert->risk_level->value,
                        'status' => $alert->status->value,
                        'context' => $alert->context,
                        'triggered_at' => $alert->triggered_at?->toJSON(),
                    ])
                    ->all(),
                'discrepancies' => ReconciliationDiscrepancy::query()
                    ->withoutGlobalScopes()
                    ->with(['tenant', 'bill'])
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (ReconciliationDiscrepancy $discrepancy): array => [
                        'id' => $discrepancy->id,
                        'tenant_name' => $discrepancy->tenant?->name,
                        'billing_period' => $discrepancy->bill?->billing_period,
                        'difference_amount' => (float) $discrepancy->difference_amount,
                        'status' => $discrepancy->status->value,
                        'note' => $discrepancy->note,
                    ])
                    ->all(),
            ],
        ]);
    }
}
