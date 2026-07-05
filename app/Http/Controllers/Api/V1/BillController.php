<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\TenantBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
        ]);

        $bills = TenantBill::query()
            ->when($filters['year'] ?? null, fn ($query, int $year) => $query->where('billing_period', 'like', "{$year}-%"))
            ->latest('billing_period')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return ApiResponse::paginated($bills->through(fn (TenantBill $bill): array => $this->serializeBill($bill)));
    }

    public function show(string $period): JsonResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1, 404);

        $bill = TenantBill::query()
            ->where('billing_period', $period)
            ->firstOrFail();

        return ApiResponse::ok($this->serializeBillDetail($bill));
    }

    /** @return array<string, mixed> */
    private function serializeBill(TenantBill $bill): array
    {
        return [
            'billing_period' => $bill->billing_period,
            'transaction_total' => (float) $bill->transaction_total,
            'commission_amount' => (float) $bill->commission_amount,
            'api_usage_fee' => (float) $bill->api_usage_fee,
            'api_overage_fee' => (float) $bill->api_overage_fee,
            'total_receivable' => (float) $bill->total_receivable,
            'status' => $bill->status->value,
            'payment_channel' => $bill->payment_channel,
            'paid_at' => $bill->paid_at?->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeBillDetail(TenantBill $bill): array
    {
        return [
            ...$this->serializeBill($bill),
            'merchant_reported_amount' => $bill->merchant_reported_amount === null ? null : (float) $bill->merchant_reported_amount,
            'difference_amount' => $bill->difference_amount === null ? null : (float) $bill->difference_amount,
            'line_items' => [
                [
                    'type' => 'commission',
                    'amount' => (float) $bill->commission_amount,
                    'description' => 'Platform commission',
                ],
                [
                    'type' => 'api_usage',
                    'amount' => (float) $bill->api_usage_fee,
                    'description' => 'API usage fee',
                ],
                [
                    'type' => 'api_overage',
                    'amount' => (float) $bill->api_overage_fee,
                    'description' => 'API overage fee',
                ],
            ],
        ];
    }
}
