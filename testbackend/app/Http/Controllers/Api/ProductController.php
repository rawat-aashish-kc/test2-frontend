<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['inventories' => fn ($query) => $query->whereHas('store', fn ($q) => $q->where('is_active', true))])
            ->with('activeDiscounts')
            ->orderBy('name')
            ->get();

        return $this->success($products->map($this->format(...)));
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404, 'Product not found.');

        $product->load(['inventories' => fn ($query) => $query->whereHas('store', fn ($q) => $q->where('is_active', true)), 'activeDiscounts']);

        return $this->success($this->format($product));
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'available_quantity' => (int) $product->inventories->sum('quantity'),
            'discount_tiers' => $product->activeDiscounts->map(fn ($tier) => [
                'min_quantity' => $tier->min_quantity,
                'discount_percent' => (float) $tier->discount_percent,
            ])->values(),
        ];
    }
}
