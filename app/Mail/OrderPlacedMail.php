<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación de compra (reemplaza `data/templates/order_placed` + el
 * evento `order.placed`). El BCC interno viene de config, no del código.
 */
class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $bcc = array_map(
            fn (string $email): Address => new Address(trim($email)),
            (array) config('shineray.notifications.order_placed_bcc', []),
        );

        return new Envelope(
            subject: "Tu orden #{$this->order->order_number} - Confirmación de compra",
            bcc: $bcc,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['items.variant.product', 'shippingAddress', 'shipments']);
        $guide = $this->order->shipments->whereNotNull('guide_number')->sortByDesc('id')->first()->guide_number
            ?? $this->order->shippingAddress?->metadata['servientrega_guide_id']
            ?? null;

        return new Content(
            markdown: 'mail.order-placed',
            with: [
                'order' => $this->order,
                'items' => $this->order->items,
                'address' => $this->order->shippingAddress,
                'guideNumber' => $guide,
                'trackingUrl' => $guide === null ? null : 'https://www.servientrega.com.ec/Tracking/?guia='.rawurlencode($guide).'&tipo=GUIA',
                'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
            ],
        );
    }
}
