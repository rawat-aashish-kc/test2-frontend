<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\FormatsOrders;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    use ApiResponse, FormatsOrders;

    public function index(): JsonResponse
    {
        $orders = Order::with('user:id,name')->latest()->get()->map(fn (Order $order) => [
            'id' => $order->id,
            'customer_name' => $order->user->name,
            ...$this->formatOrderSummary($order),
        ]);

        return $this->success($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user:id,name', 'items.allocations.store']);

        return $this->success([
            'id' => $order->id,
            'customer_name' => $order->user->name,
            ...$this->formatOrderSummary($order),
            'items' => $this->formatOrderItems($order),
        ]);
    }
}
