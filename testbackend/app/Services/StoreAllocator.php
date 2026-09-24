<?php

namespace App\Services;

/**
 * Pure calculation service: picks which store(s) fulfill a requested quantity
 * of one product, per SCHEMA.md/CONTRACT.md's store-selection rule (nearest
 * single store when possible, else nearest-first split). No Eloquent, so it
 * can be unit tested without a database.
 */
class StoreAllocator
{
    /**
     * @param  array<int, array{store_id: int, lat: float, lng: float, quantity: int}>  $storeStocks
     * @return array{
     *     fulfilled: bool,
     *     total_available: int,
     *     allocations: array<int, array{store_id: int, quantity: int, distance_km: float}>,
     * }
     */
    public static function allocate(int $quantity, float $customerLat, float $customerLng, array $storeStocks): array
    {
        $withDistance = array_values(array_map(
            function (array $store) use ($customerLat, $customerLng) {
                $store['distance_km'] = self::haversineKm($customerLat, $customerLng, $store['lat'], $store['lng']);

                return $store;
            },
            array_filter($storeStocks, fn (array $store) => $store['quantity'] > 0)
        ));

        usort($withDistance, fn (array $a, array $b) => $a['distance_km'] <=> $b['distance_km']);

        $totalAvailable = array_sum(array_column($withDistance, 'quantity'));

        if ($totalAvailable < $quantity) {
            return ['fulfilled' => false, 'total_available' => $totalAvailable, 'allocations' => []];
        }

        // Prefer the nearest single store that alone covers the full quantity.
        foreach ($withDistance as $store) {
            if ($store['quantity'] >= $quantity) {
                return [
                    'fulfilled' => true,
                    'total_available' => $totalAvailable,
                    'allocations' => [[
                        'store_id' => $store['store_id'],
                        'quantity' => $quantity,
                        'distance_km' => $store['distance_km'],
                    ]],
                ];
            }
        }

        // Otherwise fulfill greedily, nearest store first, until covered.
        $remaining = $quantity;
        $allocations = [];

        foreach ($withDistance as $store) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $store['quantity']);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'store_id' => $store['store_id'],
                'quantity' => $take,
                'distance_km' => $store['distance_km'],
            ];
            $remaining -= $take;
        }

        return [
            'fulfilled' => $remaining <= 0,
            'total_available' => $totalAvailable,
            'allocations' => $allocations,
        ];
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }
}
