<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\Services\InvoicePayloadFactory;
use App\Domain\Notifications\Jobs\SendOrderConfirmationJob;
use App\Enums\Integration;
use App\Enums\PaymentGateway;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Registra cliente (si no existe) y factura en el ERP tras un pago
 * autorizado. Reemplaza el bloque "Save warehouse data" de los payment
 * processors: ahora con reintentos y bitácora en vez de un try/catch mudo.
 * Idempotente: si la orden ya tiene `erp_invoice_number` no vuelve a facturar.
 */
class SaveInvoiceToErpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public Order $order,
        public Payment $payment,
    ) {}

    /**
     * Si la factura falla definitivamente, la cadena se corta: el correo de
     * confirmación se despacha igual para no dejar al cliente sin aviso.
     */
    public function failed(?Throwable $exception): void
    {
        SendOrderConfirmationJob::dispatch($this->order);
    }

    public function handle(ErpClientContract $erp, InvoicePayloadFactory $payloads): void
    {
        $order = $this->order->fresh() ?? $this->order;

        if ($order->metadata['erp_invoice_number'] ?? null) {
            return;
        }

        if ($this->payment->gateway === PaymentGateway::Manual) {
            return;
        }

        try {
            $client = $this->resolveBillingClient($erp, $payloads->clientFromOrder($order));
            $result = $erp->saveInvoice($payloads->fromOrder($order, $this->payment, $client));
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::ShinerayErp, 'invoice_fail', [
                'order_number' => $order->order_number,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ], $order);

            throw $exception;
        }

        $order->update(['metadata' => array_merge($order->metadata ?? [], [
            'erp_invoice_number' => $result->invoiceNumber ?? 'OK',
            'erp_invoiced_at' => now()->toIso8601String(),
        ])]);

        IntegrationLog::record(Integration::ShinerayErp, 'invoice_ok', [
            'order_number' => $order->order_number,
            'invoice_number' => $result->invoiceNumber,
        ], $order, 'info');
    }

    /**
     * B2C: si el ERP no conoce la cédula, se registra como consumidor final;
     * si la conoce, se usan los datos que tiene el ERP (nombre/dirección).
     */
    private function resolveBillingClient(ErpClientContract $erp, ClientInfo $client): ClientInfo
    {
        if ($this->order->isB2b()) {
            return $client;
        }

        $known = $erp->getInfoClient($client->idType, $client->id);

        if ($known === null) {
            $erp->saveInfoClient($client);

            return new ClientInfo(
                idType: $client->idType,
                id: $client->id,
                firstName: $client->firstName,
                lastName: $client->lastName,
                address: trim(($this->order->shippingAddress->city ?? '').', '.$client->address, ', '),
                phone: $client->phone,
                email: $client->email,
            );
        }

        return new ClientInfo(
            idType: $client->idType,
            id: $known->id,
            firstName: $known->firstName,
            lastName: $known->lastName,
            address: trim(($this->order->shippingAddress->city ?? '').', '.$known->address, ', '),
            phone: $client->phone,
            email: $client->email,
        );
    }
}
