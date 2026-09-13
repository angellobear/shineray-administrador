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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * DeUna (pago por QR). Porta `deuna-payment-processor.ts` y su variante B2B
 * (misma API; B2B agrega `expiredTime`). La confirmación llega por webhook y
 * SIEMPRE se verifica contra `/payment/info` antes de aprobar.
 */
class DeunaGateway implements PaymentGatewayContract
{
    use BuildsCheckoutContext;

    public const APPROVED_STATUSES = ['APPROVED', 'SUCCESS'];

    public function __construct(protected readonly bool $b2b = false) {}

    public function gateway(): PaymentGateway
    {
        return $this->b2b ? PaymentGateway::DeunaB2b : PaymentGateway::Deuna;
    }

    public function initiate(Cart $cart, array $context = []): PaymentSessionData
    {
        $order = $this->orderFromContext($context);
        $payment = $this->paymentFromContext($context);
        $amount = $order->total ?? $cart->total;
        // DeUna limita la referencia interna a 20 caracteres; el número de orden cabe entero.
        $internalReference = mb_substr($order->order_number ?? $cart->public_id, 0, 20);

        $body = [
            'pointOfSale' => (string) config('services.deuna.point_of_sale'),
            'qrType' => 'dynamic',
            'amount' => (float) $this->dollars($amount),
            'detail' => 'Pago de Shineray Repuestos',
            'internalTransactionReference' => $internalReference,
            'format' => '2',
        ];

        if ($this->b2b) {
            $body['expiredTime'] = 5;
        }

        try {
            $response = (array) $this->http()->post('/payment/request', $body)->throw()->json();
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::Deuna, 'initiate_fail', ['message' => $exception->getMessage()], $payment);

            throw PaymentGatewayException::initiateFailed($this->gateway(), $exception->getMessage());
        }

        if (blank($response['transactionId'] ?? null)) {
            IntegrationLog::record(Integration::Deuna, 'initiate_fail', ['response' => $response], $payment);

            throw PaymentGatewayException::initiateFailed($this->gateway(), 'respuesta sin transactionId');
        }

        IntegrationLog::record(Integration::Deuna, 'initiate', ['transaction_id' => $response['transactionId']], $payment, 'info');

        return new PaymentSessionData(
            gatewayReference: (string) $response['transactionId'],
            data: [
                'transaction_id' => $response['transactionId'],
                'internal_reference' => $internalReference,
                'qr' => $response['qr'] ?? null,
                'deeplink' => $response['deeplink'] ?? null,
            ],
            raw: $response,
        );
    }

    public function authorize(Payment $payment, array $sessionData = []): PaymentResult
    {
        $transactionId = (string) ($sessionData['transactionId'] ?? $sessionData['transactionURL'] ?? $payment->gateway_reference ?? '');

        if ($transactionId === '') {
            return PaymentResult::failed('MISSING_TRANSACTION_ID', 'Falta el id de transacción de DeUna.');
        }

        try {
            $body = (array) $this->http()->post('/payment/info', [
                'idTransacionReference' => $transactionId,
                'idType' => '0',
            ])->throw()->json();
        } catch (Throwable $exception) {
            return PaymentResult::failed('HTTP_ERROR', $exception->getMessage());
        }

        $status = strtoupper((string) ($body['status'] ?? ''));

        if (! in_array($status, self::APPROVED_STATUSES, true)) {
            return PaymentResult::failed($status !== '' ? $status : 'UNKNOWN', 'Pago DeUna no aprobado', $body);
        }

        return PaymentResult::authorized($transactionId, $status, $body);
    }

    public function capture(Payment $payment): PaymentResult
    {
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
        $result = $this->authorize($payment);

        return $result->isSuccessful() ? PaymentStatus::Authorized : $payment->status;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.deuna.base_url'))
            ->withHeaders([
                'x-api-key' => (string) config('services.deuna.api_key'),
                'x-api-secret' => (string) config('services.deuna.api_secret'),
            ])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.deuna.timeout', 30));
    }
}
