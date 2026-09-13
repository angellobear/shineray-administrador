<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateCartRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function store(Request $request): CartResource
    {
        $validated = $request->validate(['email' => ['sometimes', 'nullable', 'email', 'max:255']]);
        $customer = $request->user('customer');

        $cart = $this->carts->create($customer instanceof Customer ? $customer : null, $validated['email'] ?? null);

        return (new CartResource($this->present($cart)))->additional([]);
    }

    public function show(Cart $cart): CartResource
    {
        return new CartResource($this->present($cart));
    }

    public function update(UpdateCartRequest $request, Cart $cart): CartResource
    {
        $customer = $request->user('customer');

        if ($request->has('email') || $customer instanceof Customer) {
            $this->carts->updateContact($cart, [
                'email' => $request->validated('email'),
                'customer' => $customer instanceof Customer ? $customer : null,
            ]);
        }

        if ($request->has('shipping_address')) {
            $this->carts->setShippingAddress($cart, $request->validated('shipping_address'));
        }

        return new CartResource($this->present($cart));
    }

    private function present(Cart $cart): Cart
    {
        return $cart->load(['items.variant.product', 'discounts', 'shippingAddress']);
    }
}
