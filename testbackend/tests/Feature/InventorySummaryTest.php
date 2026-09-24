<?php

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

test('inventory summary totals match the sum of per-store quantities, and zero-stock products still show 0', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $stocked = Product::factory()->create(['name' => 'Widget']);
    $unstocked = Product::factory()->create(['name' => 'Gadget']);

    Inventory::create(['store_id' => $storeA->id, 'product_id' => $stocked->id, 'quantity' => 5]);
    Inventory::create(['store_id' => $storeB->id, 'product_id' => $stocked->id, 'quantity' => 3]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/inventory/summary');

    $response->assertOk();
    $rows = collect($response->json('data'))->keyBy('product_id');

    expect((int) $rows[$stocked->id]['total_quantity'])->toBe(8)
        ->and($rows[$stocked->id]['stores'])->toHaveCount(2)
        ->and((int) $rows[$unstocked->id]['total_quantity'])->toBe(0)
        ->and($rows[$unstocked->id]['stores'])->toBe([]);
});

test('inventory summary rejects a customer', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer, 'sanctum')->getJson('/api/admin/inventory/summary')->assertForbidden();
});

test('inventory summary rejects an unauthenticated request', function () {
    $this->getJson('/api/admin/inventory/summary')->assertUnauthorized();
});
