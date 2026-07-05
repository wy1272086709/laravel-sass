<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
            'keyword' => ['sometimes', 'string', 'max:100'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0', 'gte:price_min'],
        ]);

        $products = Product::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['keyword'] ?? null, function ($query, string $keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('product_code', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['price_min'] ?? null, fn ($query, mixed $price) => $query->where('price', '>=', $price))
            ->when($filters['price_max'] ?? null, fn ($query, mixed $price) => $query->where('price', '<=', $price))
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return ApiResponse::paginated($products->through(fn (Product $product): array => $this->serializeProduct($product)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'specs' => ['sometimes', 'array'],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $product = Product::query()->create([
            'product_code' => $this->nextProductCode(),
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'specs' => $data['specs'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'status' => ProductStatus::Listed,
        ]);

        return ApiResponse::ok($this->serializeProduct($product), 201);
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::ok($this->serializeProduct($this->findProduct($product)));
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'specs' => ['sometimes', 'array'],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $product = $this->findProduct($product);
        $product->update($data);

        return ApiResponse::ok($this->serializeProduct($product->refresh()));
    }

    public function status(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', new Enum(ProductStatus::class)],
        ]);

        $product = $this->findProduct($product);
        $product->update(['status' => $data['status']]);

        return ApiResponse::ok($this->serializeProduct($product->refresh()));
    }

    private function findProduct(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    private function nextProductCode(): string
    {
        do {
            $code = 'G-'.now()->format('ymd').'-'.str()->upper(str()->random(6));
        } while (Product::query()->where('product_code', $code)->exists());

        return $code;
    }

    /** @return array<string, mixed> */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'product_code' => $product->product_code,
            'name' => $product->name,
            'cover_image' => $product->cover_image,
            'price' => (float) $product->price,
            'stock' => $product->stock,
            'sales_count' => $product->sales_count,
            'specs' => $product->specs,
            'status' => $product->status->value,
            'created_at' => $product->created_at?->toJSON(),
        ];
    }
}
