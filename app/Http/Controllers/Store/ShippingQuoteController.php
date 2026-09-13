<?php

namespace App\Http\Controllers\Store;

use App\Domain\Shipping\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;

/** Cotiza el envío del carrito con Servientrega y lo deja aplicado en los totales. */
class ShippingQuoteController extends Controller
{
    public function __invoke(Cart $cart, ShippingService $shipping): JsonResponse
    {
        $quote = $shipping->quoteForCart($cart);

        return response()->json([
            'quote' => [
                'cost' => $quote->costCents,
                'is_fallback' => $quote->isFallback,
            ],
            'cart' => new CartResource($cart->load(['items.variant.product', 'discounts', 'shippingAddress'])),
        ]);
    }
}
