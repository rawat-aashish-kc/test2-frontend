<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;

/**
 * Shared order-detail shape for both the admin and customer order endpoints
 * (CONTRACT.md: identical `items[].allocations[]` breakdown in both).
 */
trait FormatsOrders
{
    /**
     * @return array<string, mixed>
     */
    protected function formatOrderSummary(Order $order): array
    {
        return [
            'subtotal' => (float) $order->subtotal,
            'discount_type' => $order->discount_type,
            'discount_amount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
            'original_total' => (float) $order->original_total,
            'refund_amount' => $order->refundAmount(),
            'status' => $order->status,
            'created_at' => $order->created_at,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formatOrderItems(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'unit_price' => (float) $item->unit_price,
            'quantity' => $item->quantity,
            'returned_quantity' => $item->returned_quantity,
            'line_subtotal' => (float) $item->line_subtotal,
            'line_discount_amount' => (float) $item->line_discount_amount,
            'allocations' => $item->allocations->map(fn ($allocation) => [
                'store_name' => $allocation->store->name,
                'quantity' => $allocation->quantity,
                'returned_quantity' => $allocation->returned_quantity,
                'distance_km' => (float) $allocation->distance_km,
            ])->all(),
        ])->all();
    }
}
