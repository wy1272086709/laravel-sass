<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Domain\Order\OrderCancellationService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\CloseExpiredOrderJob;
use App\Jobs\InventoryAlertJob;
use App\Jobs\SyncLogisticsJob;
use App\Models\Api\ApiKey;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use App\Support\OperationActor;
use App\Support\OperationLogContext;
use App\Support\OperationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

class OrderController extends Controller
{
    public function __construct(
        private readonly OperationLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min' => 1],
            'per_page' => ['sometimes', 'integer', 'min' => 1, 'max' => 100],
            'status' => ['sometimes', new Enum(OrderStatus::class)],
            'order_no' => ['sometimes', 'string', 'max' => 64],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $orders = Order::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['order_no'] ?? null, fn ($query, string $orderNo) => $query->where('order_no', 'like', "%{$orderNo}%"))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return ApiResponse::paginated($orders->through(fn (Order $order): array => $this->serializeOrder($order)));
    }

    public function store(Request $request, TenantContext $context): JsonResponse
    {
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.sku_id' => ['sometimes', 'nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($data, $context, $request): Order {
            $products = Product::query()
                ->whereIn('id', collect($data['items'])->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $skuIds = collect($data['items'])->pluck('sku_id')->filter()->unique()->all();
            $skus = ProductSku::query()
                ->whereIn('id', $skuIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $remainingProductStock = $products->mapWithKeys(fn (Product $product): array => [$product->id => $product->stock])->all();
            $remainingSkuStock = $skus->mapWithKeys(fn (ProductSku $sku): array => [$sku->id => $sku->stock])->all();
            $resolvedItems = [];
            $totalAmount = 0.0;

            foreach ($data['items'] as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);
                abort_if($product === null, 404);
                $quantity = (int) $item['quantity'];
                $sku = null;

                if (isset($item['sku_id'])) {
                    /** @var ProductSku|null $sku */
                    $sku = $skus->get($item['sku_id']);
                    abort_if($sku === null || $sku->product_id !== $product->id, 404);
                    abort_if($remainingSkuStock[$sku->id] < $quantity, 422, 'Insufficient SKU stock');
                    $remainingSkuStock[$sku->id] -= $quantity;
                } else {
                    abort_if($product->skus()->exists(), 422, 'sku_id is required for multi-SKU products');
                }

                abort_if($remainingProductStock[$product->id] < $quantity, 422, 'Insufficient product stock');
                $remainingProductStock[$product->id] -= $quantity;
                $unitPrice = $sku?->price ?? $product->price;
                $totalAmount += (float) $unitPrice * $quantity;
                $resolvedItems[] = compact('product', 'sku', 'quantity', 'unitPrice');
            }

            $order = Order::query()->create([
                'tenant_id' => $context->tenantId,
                'order_no' => $this->nextOrderNo(),
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'],
                'total_amount' => $totalAmount,
                'status' => OrderStatus::PendingPayment,
            ]);

            foreach ($resolvedItems as $item) {
                /** @var Product $product */
                $product = $item['product'];
                /** @var ProductSku|null $sku */
                $sku = $item['sku'];
                $quantity = $item['quantity'];

                $order->items()->create([
                    'tenant_id' => $context->tenantId,
                    'product_id' => $product->id,
                    'sku_id' => $sku?->id,
                    'product_name' => $product->name,
                    'spec_snapshot' => $sku?->specs ?? $product->specs ?? [],
                    'unit_price' => $item['unitPrice'],
                    'quantity' => $quantity,
                ]);

                $sku?->decrement('stock', $quantity);
                $product->decrement('stock', $quantity);
                $product->increment('sales_count', $quantity);
            }

            $order->load('items');

            // 审计：与扣库存同事务原子提交
            $this->auditLogger->log($this->buildContext(
                $request,
                $order,
                OperationAction::Created,
                null,
                OrderStatus::PendingPayment->value,
                ['items_count' => $order->items->count(), 'total_amount' => (float) $order->total_amount],
            ));

            return $order;
        });

        CloseExpiredOrderJob::dispatch($order->id)->delay(now()->addMinutes(30));
        InventoryAlertJob::dispatch($order->tenant_id);

        return ApiResponse::ok($this->serializeOrderDetail($order), 201);
    }

    public function show(string $orderNo): JsonResponse
    {
        $order = Order::query()
            ->with('items')
            ->where('order_no', $orderNo)
            ->firstOrFail();

        return ApiResponse::ok($this->serializeOrderDetail($order));
    }

    public function ship(Request $request, string $orderNo): JsonResponse
    {
        $order = $this->findOrder($orderNo);

        if (! $order->status->canTransitionTo(OrderStatus::Shipped)) {
            return ApiResponse::error(40901, 'Order status does not allow shipping', 409);
        }

        $fromStatus = $order->status->value;

        $order->update([
            'status' => OrderStatus::Shipped,
            'shipped_at' => now(),
        ]);

        $this->auditLogger->log($this->buildContext(
            $request,
            $order,
            OperationAction::Shipped,
            $fromStatus,
            OrderStatus::Shipped->value,
            ['tracking_no' => $request->input('tracking_no')],
        ));

        SyncLogisticsJob::dispatch($order->id);

        return ApiResponse::ok($this->serializeOrderDetail($order->refresh()->load('items')));
    }

    public function cancel(Request $request, string $orderNo, OrderCancellationService $cancellation): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $order = $this->findOrder($orderNo);

        $apiKey = $request->attributes->get('api_key');
        \assert($apiKey instanceof ApiKey);

        $actor = new OperationActor(
            kind: 'api_key',
            id: $apiKey->id,
            label: 'ApiKey:'.$apiKey->id,
            idempotencyKey: $request->header('Idempotency-Key'),
        );

        if (! $cancellation->cancel($order->id, $data['reason'] ?? null, actor: $actor)) {
            return ApiResponse::error(40901, 'Order status does not allow cancellation', 409);
        }

        return ApiResponse::ok($this->serializeOrderDetail($order->refresh()->load('items')));
    }

    public function refund(Request $request, string $orderNo): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $order = $this->findOrder($orderNo);

        if (! $order->status->canTransitionTo(OrderStatus::RefundRequested)) {
            return ApiResponse::error(40901, 'Order status does not allow refund request', 409);
        }

        $fromStatus = $order->status->value;

        $order->update(['status' => OrderStatus::RefundRequested]);

        $this->auditLogger->log($this->buildContext(
            $request,
            $order,
            OperationAction::RefundRequested,
            $fromStatus,
            OrderStatus::RefundRequested->value,
            ['reason' => $request->string('reason')->toString(), 'amount' => $request->input('amount')],
        ));

        return ApiResponse::ok($this->serializeOrderDetail($order->refresh()->load('items')));
    }

    private function findOrder(string $orderNo): Order
    {
        return Order::query()
            ->with('items')
            ->where('order_no', $orderNo)
            ->firstOrFail();
    }

    private function nextOrderNo(): string
    {
        do {
            $orderNo = 'ORD'.now()->format('YmdHis').str()->upper(str()->random(4));
        } while (Order::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
    }

    /** @return array<string, mixed> */
    private function serializeOrder(Order $order): array
    {
        return [
            'order_no' => $order->order_no,
            'buyer_name' => $order->buyer_name,
            'buyer_phone' => $order->buyer_phone,
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status->value,
            'created_at' => $order->created_at?->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrderDetail(Order $order): array
    {
        return [
            ...$this->serializeOrder($order),
            'items' => $order->items->map(fn ($item): array => [
                'product_name' => $item->product_name,
                'sku_id' => $item->sku_id,
                'spec_snapshot' => $item->spec_snapshot,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
            ])->values()->all(),
            'paid_at' => $order->paid_at?->toJSON(),
            'shipped_at' => $order->shipped_at?->toJSON(),
        ];
    }

    /**
     * 构造订单操作审计上下文（API 路径，actor 固定为 api_key）。
     *
     * @param array<string, mixed>|null $payload
     */
    private function buildContext(
        Request $request,
        Order $order,
        OperationAction $action,
        ?string $fromStatus,
        string $toStatus,
        ?array $payload = null,
    ): OperationLogContext {
        $apiKey = $request->attributes->get('api_key');
        \assert($apiKey instanceof ApiKey);

        return new OperationLogContext(
            tenantId: (int) $order->tenant_id,
            actorKind: 'api_key',
            actorId: $apiKey->id,
            actorLabel: 'ApiKey:'.$apiKey->id,
            subjectType: 'order',
            subjectId: $order->id,
            subjectLabel: $order->order_no,
            action: $action,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            payload: $payload,
            idempotencyKey: $request->header('Idempotency-Key'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
