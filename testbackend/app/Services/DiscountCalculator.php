<?php

namespace App\Services;

/**
 * Pure calculation service: computes cart/order totals per CONTRACT.md's
 * "Discount calculation rule". Takes plain arrays in, plain arrays out — no
 * Eloquent, so it can be unit tested without a database.
 */
class DiscountCalculator
{
    /**
     * @param  array<int, array{product_id: int, unit_price: float, quantity: int}>  $lines
     * @param  array<int, array<int, array{min_quantity: int, discount_percent: float}>>  $productDiscountTiers  keyed by product_id
     * @param  array<int, array{min_order_amount: float, discount_percent: float}>  $platformDiscountTiers
     * @return array{
     *     subtotal: float,
     *     discount_type: string,
     *     discount_amount: float,
     *     total: float,
     *     lines: array<int, array{product_id: int, quantity: int, unit_price: float, line_subtotal: float, line_discount_amount: float}>,
     * }
     */
    public static function calculate(array $lines, array $productDiscountTiers, array $platformDiscountTiers): array
    {
        $lineSubtotals = [];
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $lineSubtotal = round($line['unit_price'] * $line['quantity'], 2);
            $lineSubtotals[] = [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_subtotal' => $lineSubtotal,
            ];
            $subtotal += $lineSubtotal;
        }
        $subtotal = round($subtotal, 2);

        $productLineDiscounts = [];
        $productDiscountTotal = 0.0;

        foreach ($lineSubtotals as $line) {
            $tiers = $productDiscountTiers[$line['product_id']] ?? [];
            $tier = self::bestTier($tiers, 'min_quantity', $line['quantity']);
            $lineDiscount = $tier !== null
                ? round($line['unit_price'] * $line['quantity'] * $tier['discount_percent'] / 100, 2)
                : 0.0;
            $productLineDiscounts[$line['product_id']] = $lineDiscount;
            $productDiscountTotal += $lineDiscount;
        }
        $productDiscountTotal = round($productDiscountTotal, 2);

        $platformTier = self::bestTier($platformDiscountTiers, 'min_order_amount', $subtotal);
        $platformDiscountTotal = $platformTier !== null
            ? round($subtotal * $platformTier['discount_percent'] / 100, 2)
            : 0.0;

        if ($productDiscountTotal <= 0.0 && $platformDiscountTotal <= 0.0) {
            $discountType = 'none';
            $discountAmount = 0.0;
        } elseif ($productDiscountTotal >= $platformDiscountTotal) {
            // Product discount wins ties: it's line-specific and already "earned".
            $discountType = 'product';
            $discountAmount = $productDiscountTotal;
        } else {
            $discountType = 'platform';
            $discountAmount = $platformDiscountTotal;
        }

        $lines = array_map(function (array $line) use ($discountType, $productLineDiscounts) {
            $line['line_discount_amount'] = $discountType === 'product'
                ? $productLineDiscounts[$line['product_id']]
                : 0.0;

            return $line;
        }, $lineSubtotals);

        return [
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'total' => round($subtotal - $discountAmount, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Highest-threshold tier the value qualifies for ($value >= $thresholdKey).
     *
     * @param  array<int, array<string, float>>  $tiers
     */
    private static function bestTier(array $tiers, string $thresholdKey, float $value): ?array
    {
        $best = null;
        foreach ($tiers as $tier) {
            if ($value >= $tier[$thresholdKey] && ($best === null || $tier[$thresholdKey] > $best[$thresholdKey])) {
                $best = $tier;
            }
        }

        return $best;
    }
}
