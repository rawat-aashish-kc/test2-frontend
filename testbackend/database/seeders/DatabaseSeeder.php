<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\PlatformDiscount;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed demo data: one admin, one customer, a handful of stores/products/
     * inventory/discounts, enough to exercise every rule in TASKS.md by hand.
     */
    public function run(): void
    {
        $adminPassword = 'password';
        $customerPassword = 'password';

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make($adminPassword),
            'role' => 'admin',
        ]);

        $customer = User::create([
            'name' => 'Demo Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make($customerPassword),
            'role' => 'customer',
            'address' => '1 Demo Street',
            'lat' => 0.0,
            'lng' => 0.0,
        ]);

        // Roughly 1.1km, 3.3km, 5.6km, 8.9km from the demo customer (0, 0).
        $storeA = Store::create(['name' => 'Downtown Outlet', 'address' => '10 Downtown Ave', 'lat' => 0.01, 'lng' => 0.0]);
        $storeB = Store::create(['name' => 'Midtown Outlet', 'address' => '20 Midtown Rd', 'lat' => 0.03, 'lng' => 0.0]);
        $storeC = Store::create(['name' => 'Uptown Outlet', 'address' => '30 Uptown Blvd', 'lat' => 0.05, 'lng' => 0.0]);
        $storeD = Store::create(['name' => 'Suburb Outlet', 'address' => '40 Suburb Ln', 'lat' => 0.08, 'lng' => 0.0]);

        $mouse = Product::create(['name' => 'Wireless Mouse', 'description' => 'Ergonomic wireless mouse.', 'price' => 15.00]);
        $keyboard = Product::create(['name' => 'Mechanical Keyboard', 'description' => 'Tactile mechanical keyboard.', 'price' => 45.00]);
        $hub = Product::create(['name' => 'USB-C Hub', 'description' => '7-in-1 USB-C hub.', 'price' => 25.00]);
        $stand = Product::create(['name' => 'Laptop Stand', 'description' => 'Adjustable aluminum laptop stand.', 'price' => 30.00]);
        $webcam = Product::create(['name' => 'Webcam HD', 'description' => '1080p USB webcam.', 'price' => 40.00]);
        $headphones = Product::create(['name' => 'Noise Cancelling Headphones', 'description' => 'Over-ear ANC headphones.', 'price' => 80.00]);

        // Same product, multiple stores, deliberately uneven so both the
        // single-store and the multi-store-split paths are reachable by hand.
        $inventory = [
            // Mouse: Store A alone can cover any reasonable order (single-store path).
            [$mouse, $storeA, 50], [$mouse, $storeB, 30],
            // Keyboard: no single store has more than 4 — ordering 5+ forces a split.
            [$keyboard, $storeA, 3], [$keyboard, $storeB, 4], [$keyboard, $storeD, 2],
            [$hub, $storeB, 20], [$hub, $storeC, 20],
            [$stand, $storeC, 15],
            [$webcam, $storeA, 10], [$webcam, $storeD, 10],
            // Headphones: only 5 in stock anywhere — ordering more than 5 demonstrates the insufficient-stock rejection.
            [$headphones, $storeD, 5],
        ];

        foreach ($inventory as [$product, $store, $qty]) {
            Inventory::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $qty]);
        }

        // Quantity discount tiers (multiple tiers on the mouse to show "highest tier wins").
        ProductDiscount::create(['product_id' => $mouse->id, 'min_quantity' => 5, 'discount_percent' => 10]);
        ProductDiscount::create(['product_id' => $mouse->id, 'min_quantity' => 10, 'discount_percent' => 20]);
        ProductDiscount::create(['product_id' => $keyboard->id, 'min_quantity' => 3, 'discount_percent' => 15]);

        // Order-level discount — mutually exclusive with product discounts (see CONTRACT.md).
        PlatformDiscount::create(['min_order_amount' => 150, 'discount_percent' => 10]);

        $this->command->info('Seeded demo data. Login credentials:');
        $this->command->info("  Admin:    email={$admin->email} password={$adminPassword}");
        $this->command->info("  Customer: email={$customer->email} password={$customerPassword}");
    }
}
