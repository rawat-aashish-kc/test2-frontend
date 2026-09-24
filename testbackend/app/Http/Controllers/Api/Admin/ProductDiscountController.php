<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductDiscountRequest;
use App\Models\Product;
use App\Models\ProductDiscount;
use Illuminate\Http\JsonResponse;

class ProductDiscountController extends Controller
{
    use ApiResponse;

    public function index(Product $product): JsonResponse
    {
        return $this->success($product->discounts()->orderBy('min_quantity')->get()->map($this->format(...)));
    }

    public function store(ProductDiscountRequest $request, Product $product): JsonResponse
    {
        $discount = $product->discounts()->create($request->validated());

        return $this->success($this->format($discount), 'Product discount created', 201);
    }

    public function update(ProductDiscountRequest $request, ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->update($request->validated());

        return $this->success($this->format($productDiscount->fresh()), 'Product discount updated');
    }

    public function destroy(ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->update(['is_active' => false]);

        return $this->success(null, 'Product discount deactivated');
    }

    /**
     * Eloquent's decimal cast serializes as a string (to preserve precision);
     * cast it back to a number here so the frontend never has to coerce it.
     *
     * @return array<string, mixed>
     */
    private function format(ProductDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'product_id' => $discount->product_id,
            'min_quantity' => $discount->min_quantity,
            'discount_percent' => (float) $discount->discount_percent,
            'is_active' => $discount->is_active,
        ];
    }
}
