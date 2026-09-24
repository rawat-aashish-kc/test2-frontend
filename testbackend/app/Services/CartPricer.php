<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\PlatformDiscount;
use App\Models\ProductDiscount;

/**
 * Bridges Eloquent (cart contents + active discount rules) into
 * DiscountCalculator's plain-array input. Shared by the cart preview and by
 * order placement, so both price a cart identically.
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

        return DiscountCalculator::calculate($lines, $productDiscountTiers, $platformDiscountTiers);
    }
}
