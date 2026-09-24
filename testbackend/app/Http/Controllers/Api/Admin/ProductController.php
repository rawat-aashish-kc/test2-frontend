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
        return $this->success(Product::orderBy('name')->get()->map($this->format(...)));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->success($this->format($product), 'Product created', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success($this->format($product));
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return $this->success($this->format($product->fresh()), 'Product updated');
    }

    /**
     * Eloquent's decimal cast serializes as a string (to preserve precision);
     * cast it back to a number here so the frontend never has to coerce it.
     *
     * @return array<string, mixed>
     */
    private function format(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'is_active' => $product->is_active,
        ];
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return $this->success(null, 'Product deactivated');
    }
}
