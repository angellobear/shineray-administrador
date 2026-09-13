<?php

namespace App\Mail;

use App\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de recuperación de carrito (reemplaza `data/templates/abandoned_cart`).
 */
class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cart $cart) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) config('shineray.abandoned_cart.subject'),
        );
    }

    public function content(): Content
    {
        $this->cart->loadMissing(['items.variant.product', 'shippingAddress']);

        return new Content(
            markdown: 'mail.abandoned-cart',
            with: [
                'header' => config('shineray.abandoned_cart.header'),
                'firstName' => $this->cart->shippingAddress?->first_name,
                'items' => $this->cart->items,
                'total' => $this->cart->subtotal,
                'recoveryUrl' => rtrim((string) config('shineray.storefront_url'), '/').'/cart?cart='.$this->cart->public_id,
            ],
        );
    }
}
