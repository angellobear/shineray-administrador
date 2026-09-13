<?php

namespace App\Domain\Orders\Services;

use App\Domain\Cart\Exceptions\CartException;
use App\Domain\Cart\Services\CartService;
use App\Domain\Orders\DTOs\CheckoutSession;
use App\Domain\Payments\Exceptions\PaymentFailedException;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Enums\CartStatus;
use App\Enums\Integration;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\PaymentAuthorized;
use App\Models\Address;
use App\Models\Cart;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta el checkout. Secuencia extraída de los payment processors de
 * Medusa, pero desacoplada: aquí solo se congela el carrito en una orden y se
 * habla con el gateway; guía, factura y email son listeners de
 * `PaymentAuthorized`.
 */
final readonly class CheckoutService
{
    public function __construct(
        private CartService $carts,
        private PaymentGatewayResolver $gateways,
        private OrderNumberGenerator $orderNumbers,
    ) {}

    /**
     * Congela el carrito en una orden `pending` (reutiliza la orden pendiente
     * del mismo carrito si ya existe) e inicia la sesión de pago.
     *
     * @param  array<string, mixed>  $context  ip, user_agent y datos B2B (transportista_id, installments...).
     */
    public function start(Cart $cart, PaymentGateway $gateway, array $context = []): CheckoutSession
    {
        $this->carts->assertActive($cart);
        $this->carts->recalculate($cart);
        $cart->load(['items.variant.product', 'discounts', 'shippingAddress', 'customer']);

        if ($cart->items->isEmpty()) {
            throw new CartException('El carrito está vacío.');
        }

        if ($cart->shippingAddress === null || blank($cart->email)) {
            throw new CartException('Falta el email o la dirección de envío.');
        }

        $gatewayImplementation = $this->gateways->resolve($gateway);

        [$order, $payment] = DB::transaction(function () use ($cart, $gateway, $context): array {
            $order = $this->snapshotOrder($cart, $gateway, $context);

            $payment = $order->payments()->create([
                'gateway' => $gateway,
                'status' => PaymentStatus::Pending,
                'amount' => $order->total,
            ]);

            return [$order, $payment];
        });

        $session = $gatewayImplementation->initiate($cart, $context + ['order' => $order, 'payment' => $payment]);

        $payment->update([
            'gateway_reference' => $session->gatewayReference,
            'raw_request' => $context === [] ? null : array_diff_key($context, ['order' => 1, 'payment' => 1]),
            'raw_response' => $session->raw === [] ? null : $session->raw,
        ]);

        return new CheckoutSession($order, $payment, $session);
    }

    /**
     * Autoriza el pago con el gateway y confirma la orden. Idempotente: un
     * pago ya autorizado devuelve la orden sin volver a llamar al gateway.
     *
     * @param  array<string, mixed>  $sessionData  Lo que devuelve el frontend/webhook (transactionURL, resourcePath, etc.).
     *
     * @throws PaymentFailedException
     * @throws LockTimeoutException
     */
    public function complete(Payment $payment, array $sessionData = []): Order
    {
        return Cache::lock('checkout:payment:'.$payment->id, 30)->block(10, function () use ($payment, $sessionData): Order {
            $payment->refresh()->load('order');
            $order = $payment->order;

            if ($payment->isSuccessful()) {
                return $order;
            }

            if ($order->status !== OrderStatus::Pending) {
                throw new CartException('La orden ya no admite pagos.');
            }

            $result = $this->gateways->resolve($payment->gateway)->authorize($payment, $sessionData);

            if (! $result->isSuccessful()) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'response_code' => $result->responseCode,
                    'raw_response' => $result->raw === [] ? null : $result->raw,
                ]);

                IntegrationLog::record($this->integrationFor($payment->gateway), 'authorize_fail', [
                    'order_number' => $order->order_number,
                    'response_code' => $result->responseCode,
                    'message' => $result->message,
                ], $payment);

                throw new PaymentFailedException($result);
            }

            DB::transaction(function () use ($payment, $order, $result): void {
                $payment->update([
                    'status' => $result->status,
                    'gateway_reference' => $result->gatewayReference ?? $payment->gateway_reference,
                    'response_code' => $result->responseCode,
                    'raw_response' => $result->raw === [] ? null : $result->raw,
                    'authorized_at' => now(),
                ]);

                $order->transitionTo(OrderStatus::Paid)->save();

                $cart = $order->cart;

                if ($cart instanceof Cart && $cart->status === CartStatus::Active) {
                    $cart->forceFill([
                        'status' => CartStatus::Completed,
                        'completed_at' => now(),
                        'abandoned_completed_at' => $cart->abandoned_completed_at ?? now(),
                    ])->save();
                }

                IntegrationLog::record($this->integrationFor($payment->gateway), 'authorize_ok', [
                    'order_number' => $order->order_number,
                    'response_code' => $result->responseCode,
                ], $payment, 'info');

                PaymentAuthorized::dispatch($order, $payment);
                OrderPlaced::dispatch($order);
            });

            return $order->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function snapshotOrder(Cart $cart, PaymentGateway $gateway, array $context): Order
    {
        $shipping = $cart->shippingAddress;
        $isB2b = $gateway->isB2b() || (bool) $cart->customer?->is_b2b;

        $metadata = array_filter([
            'gateway' => $gateway->value,
            'is_b2b' => $isB2b,
            'dni' => $shipping?->metadata['dni'] ?? null,
            'dni_type' => $shipping?->metadata['dni_type'] ?? null,
            'city_id' => $shipping?->metadata['city_id'] ?? null,
            'city_name' => $shipping?->metadata['city_name'] ?? null,
            'shipping_quote_cents' => $cart->metadata['shipping_quote_cents'] ?? null,
            'transportista_id' => $context['transportista_id'] ?? null,
            'transportista_name' => $context['transportista_name'] ?? null,
            'installments' => $context['installments'] ?? null,
            'cod_client' => $context['cod_client'] ?? null,
            'ruc' => $context['ruc'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $order = Order::query()
            ->where('cart_id', $cart->id)
            ->where('status', OrderStatus::Pending)
            ->first();

        $attributes = [
            'customer_id' => $cart->customer_id,
            'email' => $cart->email,
            'status' => OrderStatus::Pending,
            'subtotal' => $cart->subtotal,
            'shipping_total' => $cart->shipping_total,
            'tax_total' => $cart->tax_total,
            'discount_total' => $cart->discount_total,
            'total' => $cart->total,
            'metadata' => $metadata,
        ];

        if ($order instanceof Order) {
            $order->update($attributes);
            $order->items()->delete();
            $order->addresses()->delete();
        } else {
            $order = Order::create($attributes + [
                'order_number' => $this->orderNumbers->generate(),
                'cart_id' => $cart->id,
            ]);
        }

        $order->items()->createMany($cart->items->map(fn ($item): array => [
            'product_variant_id' => $item->product_variant_id,
            'title' => $item->variant->product->title ?? $item->variant->title,
            'sku' => $item->variant->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'metadata' => ['cod_producto' => $item->variant->product?->metadata['COD_PRODUCTO'] ?? $item->variant->sku],
        ])->all());

        if ($shipping instanceof Address) {
            $address = $order->addresses()->create($shipping->only([
                'first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'province', 'country_code', 'postal_code', 'metadata',
            ]));
            $order->shipping_address_id = $address->id;
            $order->billing_address_id = $address->id;
            $order->save();
        }

        $order->discounts()->sync($cart->discounts->pluck('id')->all());

        return $order;
    }

    private function integrationFor(PaymentGateway $gateway): Integration
    {
        return match ($gateway) {
            PaymentGateway::Datafast, PaymentGateway::DatafastB2b => Integration::Datafast,
            PaymentGateway::Deuna, PaymentGateway::DeunaB2b => Integration::Deuna,
            PaymentGateway::CreditoB2b, PaymentGateway::Manual => Integration::ShinerayErp,
        };
    }
}
