<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\PlatformDiscount;
use App\Models\ProductDiscount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Places an order from a user's current cart: validates stock, allocates
 * stores, snapshots the discount tiers that applied (for later returns —
 * ASSUMPTIONS.md #14), decrements inventory, clears the cart. Used by
 * OrderController::store() and by the demo seeder (so a return can be
 * tested by hand right after seeding, without placing an order first).
 *
 * Returns a plain array rather than throwing, matching this codebase's other
 * services (DiscountCalculator, StoreAllocator, CartPricer) — the caller
 * decides what an error means (HTTP response vs. seeder failure).
 */
class OrderPlacer
{
    /**
     * @return array{order: ?Order, error: ?string, status: int}
     */
    public static function place(User $user, ?float $lat = null, ?float $lng = null): array
    {
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (! $cart || $cart->items->isEmpty()) {
            return ['order' => null, 'error' => 'Cart is empty', 'status' => 422];
        }

        $lat ??= (float) $user->lat;
        $lng ??= (float) $user->lng;

        $priced = CartPricer::price($cart);
        $lineByProduct = collect($priced['lines'])->keyBy('product_id');

        // Build (and validate) the full store-allocation plan before touching any data,
        // so an unfulfillable line rejects the whole order with nothing written.
        $plan = [];
        foreach ($cart->items as $item) {
            $storeStocks = Inventory::query()
                ->where('product_id', $item->product_id)
                ->where('quantity', '>', 0)
                ->whereHas('store', fn ($query) => $query->where('is_active', true))
                ->with('store:id,lat,lng')
                ->get()
                ->map(fn (Inventory $inventory) => [
                    'store_id' => $inventory->store_id,
                    'lat' => (float) $inventory->store->lat,
                    'lng' => (float) $inventory->store->lng,
                    'quantity' => $inventory->quantity,
                ])
                ->all();

            $allocation = StoreAllocator::allocate($item->quantity, $lat, $lng, $storeStocks);

            if (! $allocation['fulfilled']) {
                return [
                    'order' => null,
                    'error' => "Only {$allocation['total_available']} of {$item->product->name} available across all stores, {$item->quantity} requested",
                    'status' => 422,
                ];
            }

            $plan[$item->product_id] = $allocation;
        }

        // Snapshot every currently-active discount tier that could apply to this order,
        // so a later return recalculates against what the customer actually saw — not
        // whatever tiers admin has configured by then (ASSUMPTIONS.md #14).
        $productIds = $cart->items->pluck('product_id');
        $productTierSnapshots = ProductDiscount::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('product_id');
        $platformTierSnapshots = PlatformDiscount::query()->where('is_active', true)->get();

        try {
            $order = DB::transaction(function () use ($user, $cart, $priced, $lineByProduct, $plan, $lat, $lng, $productTierSnapshots, $platformTierSnapshots) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'subtotal' => $priced['subtotal'],
                    'discount_type' => $priced['discount_type'],
                    'discount_amount' => $priced['discount_amount'],
                    'total' => $priced['total'],
                    'original_total' => $priced['total'],
                    'status' => 'placed',
                    'customer_lat' => $lat,
                    'customer_lng' => $lng,
                ]);

                foreach ($platformTierSnapshots as $tier) {
                    $order->platformDiscountTiers()->create([
                        'min_order_amount' => $tier->min_order_amount,
                        'discount_percent' => $tier->discount_percent,
                    ]);
                }

                foreach ($cart->items as $item) {
                    $line = $lineByProduct[$item->product_id];

                    $orderItem = $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'unit_price' => $line['unit_price'],
                        'quantity' => $item->quantity,
                        'line_subtotal' => $line['line_subtotal'],
                        'line_discount_amount' => $line['line_discount_amount'],
                    ]);

                    foreach ($productTierSnapshots->get($item->product_id, []) as $tier) {
                        $orderItem->discountTiers()->create([
                            'min_quantity' => $tier->min_quantity,
                            'discount_percent' => $tier->discount_percent,
                        ]);
                    }

                    foreach ($plan[$item->product_id]['allocations'] as $allocation) {
                        $orderItem->allocations()->create([
                            'store_id' => $allocation['store_id'],
                            'quantity' => $allocation['quantity'],
                            'distance_km' => $allocation['distance_km'],
                        ]);

                        // Re-check under a row lock: stock could have moved since the
                        // plan was built above. If it has, roll back the whole order.
                        $inventory = Inventory::where('store_id', $allocation['store_id'])
                            ->where('product_id', $item->product_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $inventory || $inventory->quantity < $allocation['quantity']) {
                            throw new RuntimeException('Inventory changed concurrently.');
                        }

                        $inventory->decrement('quantity', $allocation['quantity']);
                    }
                }

                $cart->items()->delete();
                $cart->update(['discount_choice' => null]);

                return $order;
            });
        } catch (RuntimeException) {
            return ['order' => null, 'error' => 'Inventory changed while placing your order, please try again.', 'status' => 409];
        }

        return ['order' => $order, 'error' => null, 'status' => 201];
    }
}
