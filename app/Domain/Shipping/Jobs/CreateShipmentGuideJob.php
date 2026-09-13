<?php

namespace App\Domain\Shipping\Jobs;

use App\Domain\Shipping\Services\ShippingService;
use App\Enums\Integration;
use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Crea la guía Servientrega de una orden pagada (primer paso del pipeline
 * post-pago). Los pedidos B2B no generan guía hoy (proceso manual); se deja
 * el envío `pending` explícito en vez del `"000000"` de antes.
 *
 * Un fallo definitivo NO detiene el pipeline: el job registra
 * `servientrega_fail` y termina, igual que la tolerancia actual, pero con
 * reintentos y bitácora en vez de un log de archivo.
 */
class CreateShipmentGuideJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public Order $order) {}

    public function handle(ShippingService $shipping): void
    {
        $order = $this->order->fresh() ?? $this->order;

        if ($order->isB2b() && ! config('shineray.shipping.create_guides_for_b2b', false)) {
            $order->shipments()->firstOrCreate(['provider' => 'servientrega'], ['status' => 'pending']);

            return;
        }

        try {
            $shipping->createGuideForOrder($order);
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::Servientrega, 'servientrega_fail', [
                'order_number' => $order->order_number,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ], $order);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 120);
            }
        }
    }
}
