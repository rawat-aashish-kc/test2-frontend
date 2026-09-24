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
        return $this->success(Store::orderBy('name')->get()->map($this->format(...)));
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $store = Store::create($request->validated());

        return $this->success($this->format($store), 'Store created', 201);
    }

    public function show(Store $store): JsonResponse
    {
        return $this->success($this->format($store));
    }

    public function update(StoreRequest $request, Store $store): JsonResponse
    {
        $store->update($request->validated());

        return $this->success($this->format($store->fresh()), 'Store updated');
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->update(['is_active' => false]);

        return $this->success(null, 'Store deactivated');
    }

    /**
     * Eloquent's decimal cast serializes as a string (to preserve precision);
     * cast it back to a number here so the frontend never has to coerce it.
     *
     * @return array<string, mixed>
     */
    private function format(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'address' => $store->address,
            'lat' => (float) $store->lat,
            'lng' => (float) $store->lng,
            'is_active' => $store->is_active,
        ];
    }
}
