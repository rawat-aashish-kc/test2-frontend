<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\PlatformDiscountRequest;
use App\Models\PlatformDiscount;
use Illuminate\Http\JsonResponse;

class PlatformDiscountController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(PlatformDiscount::orderBy('min_order_amount')->get());
    }

    public function store(PlatformDiscountRequest $request): JsonResponse
    {
        $discount = PlatformDiscount::create($request->validated());

        return $this->success($discount, 'Platform discount created', 201);
    }

    public function update(PlatformDiscountRequest $request, PlatformDiscount $platformDiscount): JsonResponse
    {
        $platformDiscount->update($request->validated());

        return $this->success($platformDiscount->fresh(), 'Platform discount updated');
    }

    public function destroy(PlatformDiscount $platformDiscount): JsonResponse
    {
        $platformDiscount->update(['is_active' => false]);

        return $this->success(null, 'Platform discount deactivated');
    }
}
