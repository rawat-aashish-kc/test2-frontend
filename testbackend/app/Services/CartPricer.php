<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\PlatformDiscount;
use App\Models\ProductDiscount;

/**
 * Bridges Eloquent (cart contents + active discount rules + the customer's
 * stored discount choice) into DiscountCalculator, and builds the final
 * priced shape. Shared by the cart preview and by order placement, so both
 * price a cart identically.
 */
class CartPricer
{
    /**
     * @return array{
     *     subtotal: float,
     *     discount_type: string,
     *     discount_amount: float,
     *     total: float,
     *     lines: array<int, array{product_id: int, quantity: int, unit_price: float, line_subtotal: float, line_discount_amount: float}>,
     *     discount_options: array{
     *         product: array{available: bool, amount: float},
     *         platform: array{available: bool, amount: float},
     *     },
     * }
     */
    public static function price(Cart $cart): array
    {
        $cart->loadMissing('items.product');

        $lines = $cart->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'unit_price' => (float) $item->product->price,
            'quantity' => $item->quantity,
        ])->all();

        $productIds = $cart->items->pluck('product_id');

        $productDiscountTiers = ProductDiscount::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('product_id')
            ->map(fn ($tiers) => $tiers->map(fn ($tier) => [
                'min_quantity' => $tier->min_quantity,
                'discount_percent' => (float) $tier->discount_percent,
            ])->all())
            ->all();

        $platformDiscountTiers = PlatformDiscount::query()
            ->where('is_active', true)
            ->get()
            ->map(fn ($tier) => [
                'min_order_amount' => (float) $tier->min_order_amount,
                'discount_percent' => (float) $tier->discount_percent,
            ])
            ->all();

        $calculated = DiscountCalculator::calculate($lines, $productDiscountTiers, $platformDiscountTiers);
        $resolved = DiscountCalculator::resolve(
            $calculated['product_discount_total'],
            $calculated['platform_discount_total'],
            $cart->discount_choice,
        );

        $lines = array_map(function (array $line) use ($resolved) {
            return [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_subtotal' => $line['line_subtotal'],
                'line_discount_amount' => $resolved['discount_type'] === 'product' ? $line['product_line_discount'] : 0.0,
            ];
        }, $calculated['lines']);

        return [
            'subtotal' => $calculated['subtotal'],
            'discount_type' => $resolved['discount_type'],
            'discount_amount' => $resolved['discount_amount'],
            'total' => round($calculated['subtotal'] - $resolved['discount_amount'], 2),
            'lines' => $lines,
            'discount_options' => [
                'product' => [
                    'available' => $calculated['product_discount_total'] > 0.0,
                    'amount' => $calculated['product_discount_total'],
                ],
                'platform' => [
                    'available' => $calculated['platform_discount_total'] > 0.0,
                    'amount' => $calculated['platform_discount_total'],
                ],
            ],
        ];
    }
}
