<?php

use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\PlatformDiscount;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\User;

/**
 * Builds: one customer, two stores (A nearer, B farther), one product split
 * across them (A: 3, B: 4 — neither alone covers a 7-unit order, forcing a
 * multi-store split), a 10% product discount at 5+ units. Returns the
 * customer, product, and both stores for each test to use.
 *
 * @return array{customer: User, product: Product, storeA: Store, storeB: Store}
 */
function returnTestSetup(): array
{
    $customer = User::factory()->create(['role' => 'customer', 'address' => 'Test St', 'lat' => 0, 'lng' => 0]);
    $storeA = Store::factory()->create(['lat' => 0.01, 'lng' => 0]);
    $storeB = Store::factory()->create(['lat' => 0.05, 'lng' => 0]);
    $product = Product::factory()->create(['price' => 10]);

    Inventory::create(['store_id' => $storeA->id, 'product_id' => $product->id, 'quantity' => 3]);
    Inventory::create(['store_id' => $storeB->id, 'product_id' => $product->id, 'quantity' => 4]);
    ProductDiscount::create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);

    return compact('customer', 'product', 'storeA', 'storeB');
}

/**
 * Places a 7-unit order for the setup's product (splits 3 from A + 4 from B)
 * and returns the decoded order detail + its single order_item id.
 *
 * @return array{order: array, orderItemId: int}
 */
function placeSplitOrder(User $customer, Product $product): array
{
    test()->actingAs($customer, 'sanctum');
    test()->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 7])->assertOk();
    $response = test()->postJson('/api/orders')->assertCreated();
    $order = $response->json('data');

    return ['order' => $order, 'orderItemId' => $order['items'][0]['id']];
}

test('return restores stock to the correct store in a multi-store order', function () {
    ['customer' => $customer, 'product' => $product, 'storeA' => $storeA, 'storeB' => $storeB] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    expect(Inventory::where('store_id', $storeA->id)->value('quantity'))->toBe(0)
        ->and(Inventory::where('store_id', $storeB->id)->value('quantity'))->toBe(0);

    // Return 2 — Store A supplied 3 (its allocation was created first), so both come from A.
    $response = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ])->assertOk();

    expect(Inventory::where('store_id', $storeA->id)->value('quantity'))->toBe(2)
        ->and(Inventory::where('store_id', $storeB->id)->value('quantity'))->toBe(0)
        ->and($response->json('data.items.0.returned_quantity'))->toBe(2);
});

test('returning more than purchased is rejected, nothing written', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    $response = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 8]],
    ]);

    $response->assertStatus(422)->assertJsonPath('message', 'Only 7 of '.$product->name.' remaining to return, 8 requested');
    expect(OrderItem::find($orderItemId)->returned_quantity)->toBe(0);
});

test('returning more than what remains after an earlier partial return is rejected', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 3]],
    ])->assertOk();

    // 4 remain (7 - 3); asking for 5 must fail.
    $response = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 5]],
    ]);

    $response->assertStatus(422)->assertJsonPath('message', 'Only 4 of '.$product->name.' remaining to return, 5 requested');
});

test('product discount drops once the remaining quantity falls below its tier', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    // 7 units, 10% tier at 5+: 70 subtotal, 7 discount, 63 total.
    expect($order['discount_type'])->toBe('product')->and($order['total'])->toEqual(63.0);

    // Return 2 -> 5 remain, still >= 5, discount should still apply.
    $afterFirst = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ])->assertOk()->json('data');

    expect($afterFirst['discount_type'])->toBe('product')
        ->and($afterFirst['subtotal'])->toEqual(50.0)
        ->and($afterFirst['discount_amount'])->toEqual(5.0)
        ->and($afterFirst['total'])->toEqual(45.0);

    // Return 1 more -> 4 remain, below the tier: discount gone entirely.
    $afterSecond = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ])->assertOk()->json('data');

    expect($afterSecond['discount_type'])->toBe('none')
        ->and($afterSecond['discount_amount'])->toEqual(0.0)
        ->and($afterSecond['subtotal'])->toEqual(40.0)
        ->and($afterSecond['total'])->toEqual(40.0)
        ->and($afterSecond['refund_amount'])->toEqual(23.0); // 63 - 40
});

test('platform discount drops once the remaining order amount falls below its minimum', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    // Raise the product tier out of reach so only the platform discount can apply.
    ProductDiscount::where('product_id', $product->id)->update(['min_quantity' => 100]);
    PlatformDiscount::create(['min_order_amount' => 50, 'discount_percent' => 20]);

    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    // 7 * 10 = 70 >= 50: platform discount applies (20% = 14), total 56.
    expect($order['discount_type'])->toBe('platform')->and($order['total'])->toEqual(56.0);

    // Return 3 -> 4 remain, subtotal 40 < 50: platform discount no longer qualifies.
    $after = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 3]],
    ])->assertOk()->json('data');

    expect($after['discount_type'])->toBe('none')
        ->and($after['subtotal'])->toEqual(40.0)
        ->and($after['total'])->toEqual(40.0);
});

test('product and platform discounts still never combine after recalculation', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    // Both can qualify: product tier at 5+ (10%), platform at $30+ (10%).
    PlatformDiscount::create(['min_order_amount' => 30, 'discount_percent' => 10]);

    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    // 70 subtotal: product 10%=7, platform 10%=7 — tie, order already picked one (product, ties favor it).
    expect($order['discount_type'])->toBe('product');

    // Return 1 -> 6 remain, subtotal 60. Both still qualify (product 6*10*10%=6, platform 60*10%=6 — still tied).
    // The order's existing choice ("product") should be kept, not silently swapped.
    $after = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ])->assertOk()->json('data');

    expect($after['discount_type'])->toBe('product')
        ->and($after['discount_amount'])->toEqual(6.0)
        ->and($after['items'][0]['line_discount_amount'])->toEqual(6.0);
});

test('repeated partial returns accumulate correctly and a final return fully empties the order', function () {
    ['customer' => $customer, 'product' => $product, 'storeA' => $storeA, 'storeB' => $storeB] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ])->assertOk();
    test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ])->assertOk();

    // 3 returned so far, all from Store A (its allocation was only 3 total) — A fully restocked.
    expect(Inventory::where('store_id', $storeA->id)->value('quantity'))->toBe(3)
        ->and(Inventory::where('store_id', $storeB->id)->value('quantity'))->toBe(0);

    // Final return of the remaining 4 must come from Store B and complete the order.
    $final = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 4]],
    ])->assertOk()->json('data');

    expect(Inventory::where('store_id', $storeA->id)->value('quantity'))->toBe(3)
        ->and(Inventory::where('store_id', $storeB->id)->value('quantity'))->toBe(4)
        ->and($final['status'])->toBe('returned')
        ->and($final['total'])->toEqual(0.0)
        ->and($final['refund_amount'])->toBe($order['total'])
        ->and($final['items'][0]['returned_quantity'])->toBe(7);
});

test('an admin-changed discount tier does not retroactively affect a return\'s recalculation', function () {
    ['customer' => $customer, 'product' => $product] = returnTestSetup();
    ['order' => $order, 'orderItemId' => $orderItemId] = placeSplitOrder($customer, $product);

    // Admin deactivates the tier that applied at order time.
    ProductDiscount::where('product_id', $product->id)->update(['is_active' => false]);

    // Returning down to 5 (still meeting the ORIGINAL 5+ tier) must still get the discount,
    // because recalculation uses the order-time snapshot, not today's (now-deactivated) tier.
    $after = test()->postJson("/api/orders/{$order['id']}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ])->assertOk()->json('data');

    expect($after['discount_type'])->toBe('product')
        ->and($after['discount_amount'])->toEqual(5.0);
});
