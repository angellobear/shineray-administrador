<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Domain\Payments\Exceptions\PaymentGatewayException;
use App\Domain\Payments\Exceptions\PaymentOperationNotSupportedException;
use App\Domain\Payments\Gateways\Concerns\BuildsCheckoutContext;
use App\Enums\Integration;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\IntegrationLog;
use App\Models\Payment;
use App\Support\TaxCalculator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Datafast (OPPWa / COPYandPAY). Porta `initiatePayment` y `authorizePayment`
 * de `datafast-payment-processor.ts`; el mismo código sirve para B2C y B2B,
 * solo cambia el `entityId`.
 *
 * Códigos de resultado: `000.200.100` = checkout creado; en la consulta del
 * pago se acepta el patrón oficial de éxito de OPPWa (que incluye
 * `000.000.000`, el único que aceptaba el código Node).
 */
class DatafastGateway implements PaymentGatewayContract
{
    use BuildsCheckoutContext;

    public const CHECKOUT_CREATED_CODE = '000.200.100';

    /** Patrón oficial de "transacción exitosa" de OPPWa. */
    public const SUCCESS_PATTERN = '/^(000\.000\.|000\.100\.1|000\.[36]|000\.400\.0[^3]|000\.400\.100)/';

    public function __construct(
        protected readonly TaxCalculator $tax,
        protected readonly bool $b2b = false,
    ) {}

    public function gateway(): PaymentGateway
    {
        return $this->b2b ? PaymentGateway::DatafastB2b : PaymentGateway::Datafast;
    }

    public function initiate(Cart $cart, array $context = []): PaymentSessionData
    {
        $cart->loadMissing(['items.variant.product', 'shippingAddress']);
        $order = $this->orderFromContext($context);
        $payment = $this->paymentFromContext($context);
        $address = $cart->shippingAddress;
        $amount = $order->total ?? $cart->total;
        $reference = ($order->order_number ?? $cart->public_id).'_'.now()->getTimestamp();

        $fields = [
            'entityId' => $this->entityId(),
            'amount' => $this->dollars($amount),
            'currency' => 'USD',
            'paymentType' => 'DB',
            'customer.givenName' => $address->first_name ?? '',
            'customer.middleName' => '',
            'customer.surname' => $address->last_name ?? '',
            'customer.ip' => (string) ($context['ip'] ?? ''),
            'customer.merchantCustomerId' => $reference,
            'merchantTransactionId' => $reference,
            'customer.email' => (string) $cart->email,
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => mb_substr((string) ($address->metadata['dni'] ?? ''), 0, 10),
            'customer.phone' => (string) ($address->phone ?? ''),
            'billing.street1' => (string) ($address->address_1 ?? ''),
            'billing.country' => strtoupper((string) ($address->country_code ?? 'EC')),
            'billing.postcode' => (string) ($address->postal_code ?? '000000'),
            'shipping.street1' => (string) ($address->address_1 ?? ''),
            'shipping.country' => strtoupper((string) ($address->country_code ?? 'EC')),
            'customParameters[SHOPPER_MID]' => (string) config('services.datafast.shopper_mid'),
            'customParameters[SHOPPER_TID]' => (string) config('services.datafast.shopper_tid'),
            'customParameters[SHOPPER_ECI]' => (string) config('services.datafast.shopper_eci'),
            'customParameters[SHOPPER_PSERV]' => (string) config('services.datafast.shopper_pserv'),
            'customParameters[SHOPPER_VAL_BASE0]' => '0',
            'customParameters[SHOPPER_VAL_BASEIMP]' => $this->dollars($this->tax->netFromGross($amount)),
            'customParameters[SHOPPER_VAL_IVA]' => $this->dollars($this->tax->taxFromGross($amount)),
            'customParameters[SHOPPER_VERSIONDF]' => '2',
        ];

        foreach ($cart->items->values() as $index => $item) {
            $name = rawurlencode((string) ($item->variant->product->title ?? $item->variant->sku));
            $fields["cart.items[{$index}].name"] = $name;
            $fields["cart.items[{$index}].description"] = 'Descripcion: '.$name;
            $fields["cart.items[{$index}].price"] = $this->dollars($item->lineTotal());
            $fields["cart.items[{$index}].quantity"] = $item->quantity;
        }

        try {
            $response = $this->http()->asForm()->post('/v1/checkouts', $fields)->throw();
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::Datafast, 'initiate_fail', ['message' => $exception->getMessage()], $payment);

            throw PaymentGatewayException::initiateFailed($this->gateway(), $exception->getMessage());
        }

        $body = (array) $response->json();
        $code = (string) data_get($body, 'result.code');

        if ($code !== self::CHECKOUT_CREATED_CODE || blank($body['id'] ?? null)) {
            IntegrationLog::record(Integration::Datafast, 'initiate_fail', ['code' => $code, 'description' => data_get($body, 'result.description')], $payment);

            throw PaymentGatewayException::initiateFailed($this->gateway(), (string) data_get($body, 'result.description', $code));
        }

        IntegrationLog::record(Integration::Datafast, 'initiate', ['code' => $code, 'checkout_id' => $body['id']], $payment, 'info');

        return new PaymentSessionData(
            gatewayReference: (string) $body['id'],
            data: [
                'checkout_id' => $body['id'],
                'widget_url' => rtrim((string) config('services.datafast.base_url'), '/').'/v1/paymentWidgets.js?checkoutId='.$body['id'],
            ],
            raw: $body,
        );
    }

    public function authorize(Payment $payment, array $sessionData = []): PaymentResult
    {
        $resourcePath = (string) ($sessionData['transactionURL'] ?? $sessionData['resourcePath'] ?? '');

        if ($resourcePath === '') {
            return PaymentResult::failed('MISSING_TRANSACTION_URL', 'Falta resourcePath/transactionURL del widget de Datafast.');
        }

        try {
            $body = (array) $this->http()->get($resourcePath, ['entityId' => $this->entityId()])->throw()->json();
        } catch (Throwable $exception) {
            return PaymentResult::failed('HTTP_ERROR', $exception->getMessage());
        }

        $code = (string) data_get($body, 'result.code');

        if (preg_match(self::SUCCESS_PATTERN, $code) !== 1) {
            return PaymentResult::failed($code, (string) data_get($body, 'result.description', 'Pago rechazado'), $body);
        }

        return PaymentResult::authorized((string) ($body['id'] ?? $payment->gateway_reference), $code, $body);
    }

    public function capture(Payment $payment): PaymentResult
    {
        // paymentType DB (débito) captura en la misma autorización.
        return new PaymentResult(PaymentStatus::Captured, $payment->gateway_reference, $payment->response_code);
    }

    public function refund(Payment $payment, int $amountCents): PaymentResult
    {
        throw PaymentOperationNotSupportedException::for($this->gateway(), 'refund');
    }

    public function cancel(Payment $payment): PaymentResult
    {
        throw PaymentOperationNotSupportedException::for($this->gateway(), 'cancel');
    }

    public function status(Payment $payment): PaymentStatus
    {
        if ($payment->gateway_reference === null) {
            return $payment->status;
        }

        try {
            $body = (array) $this->http()->get('/v1/checkouts/'.$payment->gateway_reference.'/payment', ['entityId' => $this->entityId()])->throw()->json();
        } catch (Throwable) {
            return $payment->status;
        }

        return preg_match(self::SUCCESS_PATTERN, (string) data_get($body, 'result.code')) === 1
            ? PaymentStatus::Authorized
            : PaymentStatus::Failed;
    }

    protected function entityId(): string
    {
        return (string) ($this->b2b
            ? (config('services.datafast.entity_id_b2b') ?: config('services.datafast.entity_id'))
            : config('services.datafast.entity_id'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.datafast.base_url'))
            ->withToken((string) config('services.datafast.bearer_token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.datafast.timeout', 30));
    }
}
