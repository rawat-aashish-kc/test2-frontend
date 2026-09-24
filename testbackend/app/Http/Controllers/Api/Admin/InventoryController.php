<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateInventoryRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    use ApiResponse;

    public function index(Store $store): JsonResponse
    {
        $rows = $store->inventories()->with('product:id,name')->get()->map(fn (Inventory $inventory) => [
            'product_id' => $inventory->product_id,
            'product_name' => $inventory->product->name,
            'quantity' => $inventory->quantity,
        ]);

        return $this->success($rows);
    }

    public function update(UpdateInventoryRequest $request, Store $store, Product $product): JsonResponse
    {
        $inventory = Inventory::updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $product->id],
            ['quantity' => $request->validated('quantity')],
        );

        return $this->success([
            'product_id' => $inventory->product_id,
            'quantity' => $inventory->quantity,
        ], 'Inventory updated');
    }
}
