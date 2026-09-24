<?php

use App\Services\DiscountCalculator;

test('product discount applies when quantity meets a single tier', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 5]],
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [],
    );

    expect($result['subtotal'])->toBe(50.0)
        ->and($result['discount_type'])->toBe('product')
        ->and($result['discount_amount'])->toBe(5.0)
        ->and($result['total'])->toBe(45.0)
        ->and($result['lines'][0]['line_discount_amount'])->toBe(5.0);
});

test('product discount does not apply below the minimum quantity', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 4]],
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [],
    );

    expect($result['discount_type'])->toBe('none')
        ->and($result['discount_amount'])->toBe(0.0)
        ->and($result['total'])->toBe(40.0);
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
    expect($sevenUnits['discount_amount'])->toBe(7.0); // 7 * 10 * 10%

    $twelveUnits = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 12]],
        productDiscountTiers: $tiers,
        platformDiscountTiers: [],
    );
    expect($twelveUnits['discount_amount'])->toBe(24.0); // 12 * 10 * 20%, not stacked with the 10% tier
});

test('platform discount applies when order subtotal meets the minimum', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 40.0, 'quantity' => 3]], // subtotal 120
        productDiscountTiers: [],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 15.0]],
    );

    expect($result['subtotal'])->toBe(120.0)
        ->and($result['discount_type'])->toBe('platform')
        ->and($result['discount_amount'])->toBe(18.0)
        ->and($result['total'])->toBe(102.0)
        ->and($result['lines'][0]['line_discount_amount'])->toBe(0.0);
});

test('platform discount does not apply below the minimum order amount', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 30.0, 'quantity' => 3]], // subtotal 90
        productDiscountTiers: [],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 15.0]],
    );

    expect($result['discount_type'])->toBe('none')
        ->and($result['discount_amount'])->toBe(0.0);
});

test('product and platform discounts never combine, the larger one wins', function () {
    // Line qualifies for an $8 product discount; subtotal also qualifies for a $15 platform discount.
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 20.0, 'quantity' => 8]], // subtotal 160, product tier 5% = 8
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 5.0]]],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 9.375]], // 160 * 9.375% = 15
    );

    expect($result['discount_type'])->toBe('platform')
        ->and($result['discount_amount'])->toBe(15.0)
        ->and($result['lines'][0]['line_discount_amount'])->toBe(0.0)
        ->and($result['total'])->toBe(145.0);
});

test('ties favor the product discount', function () {
    $result = DiscountCalculator::calculate(
        lines: [['product_id' => 1, 'unit_price' => 10.0, 'quantity' => 10]], // subtotal 100, product tier 10% = 10
        productDiscountTiers: [1 => [['min_quantity' => 5, 'discount_percent' => 10.0]]],
        platformDiscountTiers: [['min_order_amount' => 100.0, 'discount_percent' => 10.0]], // also = 10
    );

    expect($result['discount_type'])->toBe('product')
        ->and($result['discount_amount'])->toBe(10.0);
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
        ->and($result['discount_type'])->toBe('product')
        ->and($result['discount_amount'])->toBe(5.0)
        ->and($result['lines'][0]['line_discount_amount'])->toBe(5.0)
        ->and($result['lines'][1]['line_discount_amount'])->toBe(0.0);
});
