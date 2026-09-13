<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function store(StoreCartItemRequest $request, Cart $cart): CartResource
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($request->integer('product_variant_id'));

        $this->carts->addItem($cart, $variant, $request->integer('quantity'));

        return new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress']));
    }

    public function update(Request $request, Cart $cart, CartItem $item): CartResource
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:999']]);

        $this->carts->updateItemQuantity($cart, $item, $request->integer('quantity'));

        return new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress']));
    }

    public function destroy(Cart $cart, CartItem $item): CartResource
    {
        $this->carts->removeItem($cart, $item);

        return new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress']));
    }
}
