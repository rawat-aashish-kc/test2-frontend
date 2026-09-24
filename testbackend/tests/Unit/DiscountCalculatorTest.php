<?php

use App\Services\DiscountCalculator;

// --- calculate(): raw totals, no winner picked ---

test('product discount applies when quantity meets a single tier', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 5]],
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [],
    );

    expect($result['subtotal'])->toBe(50.0)
        ->and($result['product_discount_total'])->toBe(5.0)
        ->and($result['platform_discount_total'])->toBe(0.0)
        ->and($result['lines'][0]['product_line_discount'])->toBe(5.0);
});

test('product discount does not apply below the minimum quantity', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 4]],
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [],
    );

    expect($result['product_discount_total'])->toBe(0.0);
});

test('highest qualifying product discount tier wins, not both', function () {
    $tiers = [1 => [
        ['min_quantity' => 5, 'discount_percent' => 10.0],
        ['min_quantity' => 10, 'discount_percent' => 20.0],
    ]];

    $sevenUnits = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 7]],
        productDiscountTiers: $tiers,
        platformDiscountTiers: [],
    );
    expect($sevenUnits['product_discount_total'])->toBe(7.0); // 7 * 10 * 10%

    $twelveUnits = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 12]],
        productDiscountTiers: $tiers,
        platformDiscountTiers: [],
    );
    expect($twelveUnits['product_discount_total'])->toBe(24.0); // 12 * 10 * 20%, not stacked with the 10% tier
});

test('platform discount applies when order subtotal meets the minimum', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 40.0, 'quantity' => 3]], // subtotal 120
        productDiscountTiers: [],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 15.0]],
    );

    expect($result['subtotal'])->toBe(120.0)
        ->and($result['platform_discount_total'])->toBe(18.0)
        ->and($result['product_discount_total'])->toBe(0.0);
});

test('platform discount does not apply below the minimum order amount', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 30.0, 'quantity' => 3]], // subtotal 90
        productDiscountTiers: [],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 15.0]],
    );

    expect($result['platform_discount_total'])->toBe(0.0);
});

test('multi-line cart sums line subtotals and only discounts the qualifying line', function () {
    $result = DiscountCalculator::calculate(
        lines: [
            ['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 5], // qualifies, 10% = 5
            ['product_id' => 2, 'unit_price' => 5.0, 'quantity' => 2],  // no rule
        ],
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [],
    );

    expect($result['subtotal'])->toBe(60.0)
        ->and($result['product_discount_total'])->toBe(5.0)
        ->and($result['lines'][0]['product_line_discount'])->toBe(5.0)
        ->and($result['lines'][1]['product_line_discount'])->toBe(0.0);
});

// --- resolve(): which discount applies, and the customer's choice ---

test('resolve: neither total qualifies -> none', function () {
    $result = DiscountCalculator::resolve(0.0, 0.0);

    expect($result['discount_type'])->toBe('none')
        ->and($result['discount_amount'])->toBe(0.0);
});

test('resolve: only product qualifies -> applied automatically, no choice needed', function () {
    $result = DiscountCalculator::resolve(5.0, 0.0, preference: 'platform');

    // Preference is ignored: platform isn't actually available, so product wins by default.
    expect($result['discount_type'])->toBe('product')
        ->and($result['discount_amount'])->toBe(5.0);
});

test('resolve: only platform qualifies -> applied automatically, no choice needed', function () {
    $result = DiscountCalculator::resolve(0.0, 18.0, preference: 'product');

    expect($result['discount_type'])->toBe('platform')
        ->and($result['discount_amount'])->toBe(18.0);
});

test('resolve: both qualify and customer chose product -> product applies even though platform is larger', function () {
    $result = DiscountCalculator::resolve(8.0, 15.0, preference: 'product');

    expect($result['discount_type'])->toBe('product')
        ->and($result['discount_amount'])->toBe(8.0);
});

test('resolve: both qualify and customer chose platform -> platform applies even though product is larger', function () {
    $result = DiscountCalculator::resolve(15.0, 8.0, preference: 'platform');

    expect($result['discount_type'])->toBe('platform')
        ->and($result['discount_amount'])->toBe(8.0);
});

test('resolve: both qualify, no preference yet -> defaults to the larger amount', function () {
    expect(DiscountCalculator::resolve(8.0, 15.0, preference: null)['discount_type'])->toBe('platform');
    expect(DiscountCalculator::resolve(15.0, 8.0, preference: null)['discount_type'])->toBe('product');
});

test('resolve: both qualify, no preference, tie favors product', function () {
    $result = DiscountCalculator::resolve(10.0, 10.0, preference: null);

    expect($result['discount_type'])->toBe('product');
});

test('resolve: both qualify but preference is invalid/stale -> falls back to the larger amount', function () {
    $result = DiscountCalculator::resolve(8.0, 15.0, preference: 'not-a-real-choice');

    expect($result['discount_type'])->toBe('platform');
});
