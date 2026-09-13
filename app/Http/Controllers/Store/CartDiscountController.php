<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Discount;
use Illuminate\Http\Request;

class CartDiscountController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function store(Request $request, Cart $cart): CartResource
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $this->carts->applyDiscount($cart, $validated['code']);

        return new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress']));
    }

    public function destroy(Cart $cart, Discount $discount): CartResource
    {
        $this->carts->removeDiscount($cart, $discount);

        return new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress']));
    }
}
