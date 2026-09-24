<?php

namespace App\Services;

/**
 * Pure calculation service: computes raw discount totals and resolves which
 * one applies, per CONTRACT.md's "Discount calculation rule". Takes plain
 * arrays in, plain arrays out — no Eloquent, so it can be unit tested
 * without a database.
 */
class DiscountCalculator
{
    /**
     * Raw totals only — does NOT decide which discount applies. When both a
     * product and a platform discount qualify, that choice belongs to the
     * customer (see resolve()), not to this method.
     *
     * @param  array<int, array{product_id: int, unit_price: float, quantity: int}>  $lines
     * @param  array<int, array<int, array{min_quantity: int, discount_percent: float}>>  $productDiscountTiers  keyed by product_id
     * @param  array<int, array{min_order_amount: float, discount_percent: float}>  $platformDiscountTiers
     * @return array{
     *     subtotal: float,
     *     product_discount_total: float,
     *     platform_discount_total: float,
     *     lines: array<int, array{product_id: int, quantity: int, unit_price: float, line_subtotal: float, product_line_discount: float}>,
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

        $productDiscountTotal = 0.0;

        $lines = array_map(function (array $line) use ($productDiscountTiers, &$productDiscountTotal) {
            $tiers = $productDiscountTiers[$line['product_id']] ?? [];
            $tier = self::bestTier($tiers, 'min_quantity', $line['quantity']);
            $line['product_line_discount'] = $tier !== null
                ? round($line['unit_price'] * $line['quantity'] * $tier['discount_percent'] / 100, 2)
                : 0.0;
            $productDiscountTotal += $line['product_line_discount'];

            return $line;
        }, $lineSubtotals);

        $platformTier = self::bestTier($platformDiscountTiers, 'min_order_amount', $subtotal);
        $platformDiscountTotal = $platformTier !== null
            ? round($subtotal * $platformTier['discount_percent'] / 100, 2)
            : 0.0;

        return [
            'subtotal' => $subtotal,
            'product_discount_total' => round($productDiscountTotal, 2),
            'platform_discount_total' => $platformDiscountTotal,
            'lines' => $lines,
        ];
    }

    /**
     * Decides which discount applies, given both raw totals and the
     * customer's preference (if any):
     * - Neither qualifies → "none".
     * - Only one qualifies → that one, automatically (nothing to choose).
     * - Both qualify → the customer's stored preference if it's one of
     *   "product"/"platform"; otherwise the larger amount (sensible default
     *   until the customer picks, never a stacked/combined discount either way).
     *
     * @return array{discount_type: string, discount_amount: float}
     */
    public static function resolve(float $productDiscountTotal, float $platformDiscountTotal, ?string $preference = null): array
    {
        $productAvailable = $productDiscountTotal > 0.0;
        $platformAvailable = $platformDiscountTotal > 0.0;

        if (! $productAvailable && ! $platformAvailable) {
            return ['discount_type' => 'none', 'discount_amount' => 0.0];
        }

        if ($productAvailable && ! $platformAvailable) {
            return ['discount_type' => 'product', 'discount_amount' => $productDiscountTotal];
        }

        if ($platformAvailable && ! $productAvailable) {
            return ['discount_type' => 'platform', 'discount_amount' => $platformDiscountTotal];
        }

        // Both qualify: honor a valid preference, else default to the larger amount.
        $type = in_array($preference, ['product', 'platform'], true)
            ? $preference
            : ($productDiscountTotal >= $platformDiscountTotal ? 'product' : 'platform');

        return [
            'discount_type' => $type,
            'discount_amount' => $type === 'product' ? $productDiscountTotal : $platformDiscountTotal,
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
