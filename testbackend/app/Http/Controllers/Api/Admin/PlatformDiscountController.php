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
        return $this->success(PlatformDiscount::orderBy('min_order_amount')->get()->map($this->format(...)));
    }

    public function store(PlatformDiscountRequest $request): JsonResponse
    {
        $discount = PlatformDiscount::create($request->validated());

        return $this->success($this->format($discount), 'Platform discount created', 201);
    }

    public function update(PlatformDiscountRequest $request, PlatformDiscount $platformDiscount): JsonResponse
    {
        $platformDiscount->update($request->validated());

        return $this->success($this->format($platformDiscount->fresh()), 'Platform discount updated');
    }

    public function destroy(PlatformDiscount $platformDiscount): JsonResponse
    {
        $platformDiscount->update(['is_active' => false]);

        return $this->success(null, 'Platform discount deactivated');
    }

    /**
     * Eloquent's decimal cast serializes as a string (to preserve precision);
     * cast it back to a number here so the frontend never has to coerce it.
     *
     * @return array<string, mixed>
     */
    private function format(PlatformDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'min_order_amount' => (float) $discount->min_order_amount,
            'discount_percent' => (float) $discount->discount_percent,
            'is_active' => $discount->is_active,
        ];
    }
}
