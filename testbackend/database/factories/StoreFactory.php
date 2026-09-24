<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Outlet',
            'address' => fake()->address(),
            'lat' => fake()->latitude(-1, 1),
            'lng' => fake()->longitude(-1, 1),
            'is_active' => true,
        ];
    }
}
