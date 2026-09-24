<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateInventoryRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use ApiResponse;

    /**
     * Total stock per product across every store (admin-only visibility, not the
     * customer-facing "available_quantity" — includes inactive stores/products too).
     * One query: LEFT JOIN so a product with zero inventory rows still shows
     * total_quantity 0 instead of disappearing, then grouped in memory (not a
     * per-product query loop).
     */
    public function summary(): JsonResponse
    {
        $rows = Product::query()
            ->leftJoin('inventories', 'inventories.product_id', '=', 'products.id')
            ->leftJoin('stores', 'stores.id', '=', 'inventories.store_id')
            ->orderBy('products.name')
            ->get([
                'products.id as product_id',
                'products.name as product_name',
                'stores.id as store_id',
                'stores.name as store_name',
                DB::raw('COALESCE(inventories.quantity, 0) as quantity'),
            ]);

        $summary = $rows->groupBy('product_id')->map(function ($productRows) {
            $stores = $productRows
                ->filter(fn ($row) => $row->store_id !== null)
                ->map(fn ($row) => [
                    'store_id' => $row->store_id,
                    'store_name' => $row->store_name,
                    'quantity' => (int) $row->quantity,
                ])
                ->values();

            return [
                'product_id' => $productRows->first()->product_id,
                'product_name' => $productRows->first()->product_name,
                'total_quantity' => (int) $stores->sum('quantity'),
                'stores' => $stores,
            ];
        })->values();

        return $this->success($summary);
    }

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
