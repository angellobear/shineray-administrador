<?php

namespace App\Http\Controllers\Store;

use App\Domain\Orders\Services\CheckoutService;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StartCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /** Inicia el checkout: orden pendiente + sesión de pago del gateway elegido. */
    public function start(StartCheckoutRequest $request, Cart $cart): JsonResponse
    {
        $context = array_merge((array) $request->validated('context', []), [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $session = $this->checkout->start($cart, PaymentGateway::from($request->validated('gateway')), $context);

        return response()->json([
            'order' => new OrderResource($session->order->load(['items', 'shippingAddress'])),
            'payment' => [
                'id' => $session->payment->id,
                'gateway' => $session->payment->gateway->value,
                'status' => $session->payment->status->value,
                'reference' => $session->session->gatewayReference,
                'redirect_url' => $session->session->redirectUrl,
                'data' => $session->session->data,
            ],
        ], 201);
    }

    /** Confirma el pago pendiente de la orden con lo que devolvió el gateway. */
    public function complete(Request $request, Order $order): OrderResource
    {
        $validated = $request->validate(['session_data' => ['sometimes', 'array']]);

        $payment = $order->payments()->where('status', 'pending')->latest('id')->first()
            ?? $order->latestPayment();

        abort_if($payment === null, 404, 'La orden no tiene un pago que confirmar.');

        $order = $this->checkout->complete($payment, (array) ($validated['session_data'] ?? []));

        return new OrderResource($order->load(['items', 'shippingAddress', 'shipments']));
    }
}
