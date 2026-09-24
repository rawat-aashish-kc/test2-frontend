<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Product::orderBy('name')->get());
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->success($product, 'Product created', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success($product);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return $this->success($product->fresh(), 'Product updated');
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return $this->success(null, 'Product deactivated');
    }
}
