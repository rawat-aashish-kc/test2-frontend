<?php

namespace App\Services;

use App\Models\Order;

/**
 * Return-time counterpart of CartPricer: bridges Eloquent (an order's
 * *remaining* quantities + its own order-time discount tier snapshots) into
 * DiscountCalculator, and builds the recalculated shape. Unlike checkout,
 * the resolve() preference is the order's own current discount_type (so the
 * customer's original choice is kept where it still qualifies — see
 * CONTRACT.md's "Order recalculation rule").
 */
class OrderPricer
{
    /**
     * @return array{
     *     subtotal: float,
     *     discount_type: string,
     *     discount_amount: float,
     *     total: float,
     *     lines: array<int, array{order_item_id: int, line_subtotal: float, line_discount_amount: float}>,
     * }
     */
    public static function price(Order $order): array
    {
        $order->loadMissing(['items.discountTiers', 'platformDiscountTiers']);

        $lines = $order->items
            ->filter(fn ($item) => $item->remainingQuantity() > 0)
            ->map(fn ($item) => [
                // DiscountCalculator's "product_id" is just a grouping key to it; we key by
                // order_item_id instead, since tiers were snapshotted per order_item (a
                // product can only appear once per order, so this is a 1:1 relabeling).
                'product_id' => $item->id,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->remainingQuantity(),
            ])
            ->values()
            ->all();

        // DiscountCalculator keys product tiers by "product_id" — here that's really each
        // order_item's own id, since tiers were snapshotted per order_item (a product can
        // only appear once per order anyway, so this is a 1:1 relabeling, not a behavior change).
        $productDiscountTiers = $order->items
            ->mapWithKeys(fn ($item) => [
                $item->id => $item->discountTiers->map(fn ($tier) => [
                    'min_quantity' => $tier->min_quantity,
                    'discount_percent' => (float) $tier->discount_percent,
                ])->all(),
            ])
            ->all();

        $platformDiscountTiers = $order->platformDiscountTiers
            ->map(fn ($tier) => [
                'min_order_amount' => (float) $tier->min_order_amount,
                'discount_percent' => (float) $tier->discount_percent,
            ])
            ->all();

        $calculated = DiscountCalculator::calculate($lines, $productDiscountTiers, $platformDiscountTiers);
        $resolved = DiscountCalculator::resolve(
            $calculated['product_discount_total'],
            $calculated['platform_discount_total'],
            $order->discount_type !== 'none' ? $order->discount_type : null,
        );

        $lines = array_map(fn (array $line) => [
            'order_item_id' => $line['product_id'], // relabeled back, see note above
            'line_subtotal' => $line['line_subtotal'],
            'line_discount_amount' => $resolved['discount_type'] === 'product' ? $line['product_line_discount'] : 0.0,
        ], $calculated['lines']);

        return [
            'subtotal' => $calculated['subtotal'],
            'discount_type' => $resolved['discount_type'],
            'discount_amount' => $resolved['discount_amount'],
            'total' => round($calculated['subtotal'] - $resolved['discount_amount'], 2),
            'lines' => $lines,
        ];
    }
}
