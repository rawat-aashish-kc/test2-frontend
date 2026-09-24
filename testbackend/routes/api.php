<?php

use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PlatformDiscountController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductDiscountController;
use App\Http\Controllers\Api\Admin\StoreController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('stores', StoreController::class);
    Route::apiResource('products', AdminProductController::class);

    Route::get('stores/{store}/inventory', [InventoryController::class, 'index']);
    Route::put('stores/{store}/inventory/{product}', [InventoryController::class, 'update']);

    Route::get('products/{product}/discounts', [ProductDiscountController::class, 'index']);
    Route::post('products/{product}/discounts', [ProductDiscountController::class, 'store']);
    Route::put('product-discounts/{productDiscount}', [ProductDiscountController::class, 'update']);
    Route::delete('product-discounts/{productDiscount}', [ProductDiscountController::class, 'destroy']);

    Route::apiResource('platform-discounts', PlatformDiscountController::class)->except(['show']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::put('cart/items/{product}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{product}', [CartController::class, 'removeItem']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
});
