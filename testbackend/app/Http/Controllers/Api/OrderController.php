<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\FormatsOrders;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Services\CartPricer;
use App\Services\StoreAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponse, FormatsOrders;

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->latest()->get()->map(fn (Order $order) => [
            'id' => $order->id,
            ...$this->formatOrderSummary($order),
        ]);

        return $this->success($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404, 'Order not found.');

        $order->load(['items.allocations.store']);

        return $this->success([
            'id' => $order->id,
            ...$this->formatOrderSummary($order),
            'items' => $this->formatOrderItems($order),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (! $cart || $cart->items->isEmpty()) {
            return $this->error('Cart is empty', 422);
        }

        $lat = (float) ($request->input('lat') ?? $user->lat);
        $lng = (float) ($request->input('lng') ?? $user->lng);

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
                return $this->error(
                    "Only {$allocation['total_available']} of {$item->product->name} available across all stores, {$item->quantity} requested",
                    422,
                );
            }

            $plan[$item->product_id] = $allocation;
        }

        try {
            $order = DB::transaction(function () use ($user, $cart, $priced, $lineByProduct, $plan, $lat, $lng) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'subtotal' => $priced['subtotal'],
                    'discount_type' => $priced['discount_type'],
                    'discount_amount' => $priced['discount_amount'],
                    'total' => $priced['total'],
                    'status' => 'placed',
                    'customer_lat' => $lat,
                    'customer_lng' => $lng,
                ]);

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

                return $order;
            });
        } catch (RuntimeException) {
            return $this->error('Inventory changed while placing your order, please try again.', 409);
        }

        $order->load(['items.allocations.store']);

        return $this->success([
            'id' => $order->id,
            ...$this->formatOrderSummary($order),
            'items' => $this->formatOrderItems($order),
        ], 'Order placed', 201);
    }
}
