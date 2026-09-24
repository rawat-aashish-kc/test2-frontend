<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Store::orderBy('name')->get());
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $store = Store::create($request->validated());

        return $this->success($store, 'Store created', 201);
    }

    public function show(Store $store): JsonResponse
    {
        return $this->success($store);
    }

    public function update(StoreRequest $request, Store $store): JsonResponse
    {
        $store->update($request->validated());

        return $this->success($store->fresh(), 'Store updated');
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->update(['is_active' => false]);

        return $this->success(null, 'Store deactivated');
    }
}
