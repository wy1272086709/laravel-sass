<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Enums\BillStatus;
use App\Domain\Enums\ReconciliationStatus;
use App\Models\Api\ApiUsageDaily;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class BillSettlementService
{
    public const API_OVERAGE_UNIT_PRICE = 0.001;

    public function defaultPeriod(): string
    {
        return CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');
    }

    public function generateMonthlyBill(Tenant $tenant, ?string $period = null): TenantBill
    {
        $period ??= $this->defaultPeriod();
        [$start, $end] = $this->periodRange($period);

        $transactionTotal = (float) Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $commissionAmount = $this->money($transactionTotal * (float) $tenant->commission_rate);
        $api = $this->apiFees($tenant, $period, $start->daysInMonth);
        $totalReceivable = $this->money($commissionAmount + $api['api_usage_fee'] + $api['api_overage_fee']);

        return TenantBill::query()
            ->withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'billing_period' => $period,
                ],
                [
                    'transaction_total' => $transactionTotal,
                    'commission_amount' => $commissionAmount,
                    'api_usage_fee' => $api['api_usage_fee'],
                    'api_overage_fee' => $api['api_overage_fee'],
                    'total_receivable' => $totalReceivable,
                    'status' => BillStatus::PendingSettlement,
                ],
            );
    }

    public function recordMerchantReport(TenantBill $bill, float|int|string $reportedAmount): TenantBill
    {
        $reported = $this->money((float) $reportedAmount);
        $difference = $this->money((float) $bill->total_receivable - $reported);

        $bill->forceFill([
            'merchant_reported_amount' => $reported,
            'difference_amount' => $difference,
        ])->save();

        if (abs($difference) > 0.00001) {
            ReconciliationDiscrepancy::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'tenant_id' => $bill->tenant_id,
                        'tenant_bill_id' => $bill->id,
                        'status' => ReconciliationStatus::Unreconciled,
                    ],
                    [
                        'difference_amount' => $difference,
                        'note' => 'Merchant reported amount differs from platform receivable.',
                    ],
                );
        }

        return $bill->refresh();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodRange(string $period): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$period}-01 00:00:00", config('app.timezone'))
            ?: CarbonImmutable::parse("{$period}-01", config('app.timezone'));

        return [$start->startOfMonth(), $start->endOfMonth()];
    }

    /** @return array{api_usage_fee: float, api_overage_fee: float, request_count: int, overage_count: int} */
    private function apiFees(Tenant $tenant, string $period, int $daysInPeriod): array
    {
        $requestCount = (int) ApiUsageDaily::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('usage_date', [
                Carbon::parse("{$period}-01")->startOfMonth()->toDateString(),
                Carbon::parse("{$period}-01")->endOfMonth()->toDateString(),
            ])
            ->sum('request_count');

        $quota = (int) ($tenant->package?->api_quota_daily ?? $tenant->package()->value('api_quota_daily') ?? 0);
        $includedRequests = $quota * $daysInPeriod;
        $overageCount = max(0, $requestCount - $includedRequests);

        return [
            'api_usage_fee' => 0.0,
            'api_overage_fee' => $this->money($overageCount * self::API_OVERAGE_UNIT_PRICE),
            'request_count' => $requestCount,
            'overage_count' => $overageCount,
        ];
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
