<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\FormatsOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReturnOrderRequest;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Services\OrderPlacer;
use App\Services\OrderPricer;
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
        $lat = $request->input('lat') !== null ? (float) $request->input('lat') : null;
        $lng = $request->input('lng') !== null ? (float) $request->input('lng') : null;

        $result = OrderPlacer::place($request->user(), $lat, $lng);

        if ($result['error']) {
            return $this->error($result['error'], $result['status']);
        }

        $order = $result['order']->load(['items.allocations.store']);

        return $this->success([
            'id' => $order->id,
            ...$this->formatOrderSummary($order),
            'items' => $this->formatOrderItems($order),
        ], 'Order placed', 201);
    }

    public function returns(ReturnOrderRequest $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404, 'Order not found.');

        $requests = collect($request->validated('items'));
        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereIn('id', $requests->pluck('order_item_id'))
            ->get()
            ->keyBy('id');

        // Validate every line before writing anything (all-or-nothing, same discipline as order placement).
        foreach ($requests as $entry) {
            $orderItem = $orderItems->get($entry['order_item_id']);
            if (! $orderItem) {
                return $this->error('That item does not belong to this order', 422);
            }

            $remaining = $orderItem->remainingQuantity();
            if ($entry['quantity'] > $remaining) {
                return $this->error(
                    "Only {$remaining} of {$orderItem->product_name} remaining to return, {$entry['quantity']} requested",
                    422,
                );
            }
        }

        try {
            DB::transaction(function () use ($order, $requests, $orderItems) {
                foreach ($requests as $entry) {
                    $orderItem = $orderItems->get($entry['order_item_id']);
                    $toReturn = $entry['quantity'];

                    // Restock the store(s) that supplied this line, original allocation order
                    // first, never more than each allocation actually supplied (ASSUMPTIONS.md #16).
                    $allocations = OrderItemAllocation::where('order_item_id', $orderItem->id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($allocations as $allocation) {
                        if ($toReturn <= 0) {
                            break;
                        }

                        $take = min($toReturn, $allocation->remainingQuantity());
                        if ($take <= 0) {
                            continue;
                        }

                        $inventory = Inventory::where('store_id', $allocation->store_id)
                            ->where('product_id', $orderItem->product_id)
                            ->lockForUpdate()
                            ->first();

                        if ($inventory) {
                            $inventory->increment('quantity', $take);
                        } else {
                            Inventory::create([
                                'store_id' => $allocation->store_id,
                                'product_id' => $orderItem->product_id,
                                'quantity' => $take,
                            ]);
                        }

                        $allocation->increment('returned_quantity', $take);
                        $toReturn -= $take;
                    }

                    if ($toReturn > 0) {
                        // Pre-validated above; only reachable if concurrent requests raced past it.
                        throw new RuntimeException('Inventory changed concurrently.');
                    }

                    $orderItem->increment('returned_quantity', $entry['quantity']);
                }

                // Recalculate the whole order from its remaining quantities (not just the
                // lines touched by this request), per CONTRACT.md's Order recalculation rule.
                $priced = OrderPricer::price($order);
                $lineByOrderItem = collect($priced['lines'])->keyBy('order_item_id');

                foreach (OrderItem::where('order_id', $order->id)->get() as $orderItem) {
                    $line = $lineByOrderItem->get($orderItem->id);
                    $orderItem->update([
                        'line_subtotal' => $line['line_subtotal'] ?? 0,
                        'line_discount_amount' => $line['line_discount_amount'] ?? 0,
                    ]);
                }

                $allReturned = OrderItem::where('order_id', $order->id)
                    ->whereColumn('returned_quantity', '<', 'quantity')
                    ->doesntExist();

                $order->update([
                    'subtotal' => $priced['subtotal'],
                    'discount_type' => $priced['discount_type'],
                    'discount_amount' => $priced['discount_amount'],
                    'total' => $priced['total'],
                    'status' => $allReturned ? 'returned' : $order->status,
                ]);
            });
        } catch (RuntimeException) {
            return $this->error('Inventory changed while processing this return, please try again.', 409);
        }

        $order->refresh()->load(['items.allocations.store']);

        return $this->success([
            'id' => $order->id,
            ...$this->formatOrderSummary($order),
            'items' => $this->formatOrderItems($order),
        ], 'Return processed');
    }
}
