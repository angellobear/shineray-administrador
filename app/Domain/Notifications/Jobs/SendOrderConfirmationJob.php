<?php

namespace App\Domain\Notifications\Jobs;

use App\Enums\Integration;
use App\Mail\OrderPlacedMail;
use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Último paso del pipeline post-pago: envía la confirmación de compra una
 * vez que la guía y la factura ya se intentaron (así el correo lleva el
 * número de guía cuando existe). Idempotente por orden.
 */
class SendOrderConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $order = $this->order->fresh() ?? $this->order;

        if ($order->metadata['confirmation_sent_at'] ?? null) {
            return;
        }

        try {
            Mail::to($order->email)->send(new OrderPlacedMail($order));
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::ShinerayErp, 'order_email_fail', [
                'order_number' => $order->order_number,
                'message' => $exception->getMessage(),
            ], $order);

            throw $exception;
        }

        $order->update(['metadata' => array_merge($order->metadata ?? [], ['confirmation_sent_at' => now()->toIso8601String()])]);
    }
}
