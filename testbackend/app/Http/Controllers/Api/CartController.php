<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddCartItemRequest;
use App\Http\Requests\Api\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartPricer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->priceCart($this->cartFor($request)));
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartFor($request);
        $product = Product::findOrFail($request->validated('product_id'));

        $existing = $cart->items()->where('product_id', $product->id)->first();
        $alreadyInCart = $existing?->quantity ?? 0;
        $newQuantity = $alreadyInCart + $request->validated('quantity');

        $this->assertStockAvailable($product, $newQuantity, $alreadyInCart);

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id],
            ['quantity' => $newQuantity],
        );

        return $this->success($this->priceCart($cart), 'Added to cart');
    }

    public function updateItem(UpdateCartItemRequest $request, Product $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        $item = $cart->items()->where('product_id', $product->id)->firstOrFail();

        $this->assertStockAvailable($product, $request->validated('quantity'));

        $item->update(['quantity' => $request->validated('quantity')]);

        return $this->success($this->priceCart($cart), 'Cart updated');
    }

    public function removeItem(Request $request, Product $product): JsonResponse
    {
        $cart = $this->cartFor($request);
        $cart->items()->where('product_id', $product->id)->delete();

        return $this->success($this->priceCart($cart), 'Removed from cart');
    }

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function assertStockAvailable(Product $product, int $requestedQuantity, int $alreadyInCart = 0): void
    {
        $available = $product->availableQuantity();

        if ($requestedQuantity > $available) {
            $message = "Only {$available} in stock for {$product->name}";
            if ($alreadyInCart > 0) {
                $message .= " ({$alreadyInCart} already in your cart)";
            }

            throw ValidationException::withMessages(['quantity' => [$message]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function priceCart(Cart $cart): array
    {
        $cart->load('items.product');
        $priced = CartPricer::price($cart);
        $lineSubtotals = collect($priced['lines'])->keyBy('product_id');

        $items = $cart->items->map(fn (CartItem $item) => [
            'product_id' => $item->product_id,
            'product_name' => $item->product->name,
            'unit_price' => (float) $item->product->price,
            'quantity' => $item->quantity,
            'line_subtotal' => $lineSubtotals[$item->product_id]['line_subtotal'],
        ])->values();

        return [
            'items' => $items,
            'subtotal' => $priced['subtotal'],
            'discount_type' => $priced['discount_type'],
            'discount_amount' => $priced['discount_amount'],
            'total' => $priced['total'],
        ];
    }
}
