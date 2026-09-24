<?php

use App\Services\StoreAllocator;

test('fulfills entirely from the nearest store when it alone has enough stock', function () {
    $result = StoreAllocator::allocate(
        quantity: 15,
        customerLat: 0.0,
        customerLng: 0.0,
        storeStocks: [
            ['store_id' => 1, 'lat' => 0.02, 'lng' => 0.0, 'quantity' => 20], // ~2.2km
            ['store_id' => 2, 'lat' => 0.05, 'lng' => 0.0, 'quantity' => 20], // ~5.6km
        ],
    );

    expect($result['fulfilled'])->toBeTrue()
        ->and($result['allocations'])->toHaveCount(1)
        ->and($result['allocations'][0]['store_id'])->toBe(1)
        ->and($result['allocations'][0]['quantity'])->toBe(15);
});

test('splits across stores nearest-first when no single store has enough', function () {
    $result = StoreAllocator::allocate(
        quantity: 15,
        customerLat: 0.0,
        customerLng: 0.0,
        storeStocks: [
            ['store_id' => 1, 'lat' => 0.02, 'lng' => 0.0, 'quantity' => 5],  // nearest, not enough alone
            ['store_id' => 2, 'lat' => 0.05, 'lng' => 0.0, 'quantity' => 10], // farther, not enough alone either
        ],
    );

    expect($result['fulfilled'])->toBeTrue()
        ->and($result['allocations'])->toHaveCount(2)
        ->and($result['allocations'][0]['store_id'])->toBe(1)
        ->and($result['allocations'][0]['quantity'])->toBe(5)
        ->and($result['allocations'][1]['store_id'])->toBe(2)
        ->and($result['allocations'][1]['quantity'])->toBe(10);
});

test('a farther store that alone covers the quantity wins over splitting from the nearest', function () {
    $result = StoreAllocator::allocate(
        quantity: 15,
        customerLat: 0.0,
        customerLng: 0.0,
        storeStocks: [
            ['store_id' => 1, 'lat' => 0.02, 'lng' => 0.0, 'quantity' => 5],  // nearest, not enough alone
            ['store_id' => 2, 'lat' => 0.05, 'lng' => 0.0, 'quantity' => 20], // farther, enough alone
        ],
    );

    expect($result['fulfilled'])->toBeTrue()
        ->and($result['allocations'])->toHaveCount(1)
        ->and($result['allocations'][0]['store_id'])->toBe(2)
        ->and($result['allocations'][0]['quantity'])->toBe(15);
});

test('reports unfulfilled when total stock across all stores is short', function () {
    $result = StoreAllocator::allocate(
        quantity: 10,
        customerLat: 0.0,
        customerLng: 0.0,
        storeStocks: [
            ['store_id' => 1, 'lat' => 0.02, 'lng' => 0.0, 'quantity' => 3],
            ['store_id' => 2, 'lat' => 0.05, 'lng' => 0.0, 'quantity' => 5],
        ],
    );

    expect($result['fulfilled'])->toBeFalse()
        ->and($result['total_available'])->toBe(8)
        ->and($result['allocations'])->toBe([]);
});

test('ignores stores with zero stock', function () {
    $result = StoreAllocator::allocate(
        quantity: 5,
        customerLat: 0.0,
        customerLng: 0.0,
        storeStocks: [
            ['store_id' => 1, 'lat' => 0.01, 'lng' => 0.0, 'quantity' => 0],
            ['store_id' => 2, 'lat' => 0.05, 'lng' => 0.0, 'quantity' => 5],
        ],
    );

    expect($result['fulfilled'])->toBeTrue()
        ->and($result['allocations'])->toHaveCount(1)
        ->and($result['allocations'][0]['store_id'])->toBe(2);
});

test('haversine distance is 0 for identical coordinates and grows with separation', function () {
    expect(StoreAllocator::haversineKm(0.0, 0.0, 0.0, 0.0))->toBe(0.0);

    // Roughly 1 degree of latitude ~= 111km.
    $distance = StoreAllocator::haversineKm(0.0, 0.0, 1.0, 0.0);
    expect($distance)->toBeGreaterThan(110.0)->toBeLessThan(112.0);
});
