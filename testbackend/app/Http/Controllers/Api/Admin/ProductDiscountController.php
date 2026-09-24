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
        return $this->success($product->discounts()->orderBy('min_quantity')->get());
    }

    public function store(ProductDiscountRequest $request, Product $product): JsonResponse
    {
        $discount = $product->discounts()->create($request->validated());

        return $this->success($discount, 'Product discount created', 201);
    }

    public function update(ProductDiscountRequest $request, ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->update($request->validated());

        return $this->success($productDiscount->fresh(), 'Product discount updated');
    }

    public function destroy(ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->update(['is_active' => false]);

        return $this->success(null, 'Product discount deactivated');
    }
}
