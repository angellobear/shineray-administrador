<?php

namespace App\Domain\Cart\Services;

use App\Enums\CartStatus;
use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Motor de carrito abandonado (porta `abandoned-cart.ts`). Los intervalos se
 * guardan en minutos en `abandoned_last_interval` (en Node eran milisegundos;
 * el ETL los convierte).
 */
final class AbandonedCartService
{
    /**
     * Carritos candidatos: con email real, con items, no completados y creados
     * dentro de la ventana de seguimiento.
     *
     * @return Builder<Cart>
     */
    public function candidatesQuery(bool $fromAdmin = false): Builder
    {
        $config = config('shineray.abandoned_cart');

        return Cart::query()
            ->with(['items.variant.product', 'shippingAddress'])
            ->whereNotNull('email')
            ->where('email', 'not like', '%'.$config['excluded_email_pattern'].'%')
            ->where('status', CartStatus::Active)
            ->whereNull('completed_at')
            ->whereHas('items')
            ->when(! $fromAdmin, fn (Builder $query) => $query
                ->whereNull('abandoned_completed_at')
                ->where('created_at', '>', now()->subDays((int) $config['days_to_track'])))
            ->latest('created_at')
            ->orderByDesc('id');
    }

    /**
     * Envía el email de recuperación y registra el intento en el carrito.
     *
     * @param  int|null  $intervalMinutes  Intervalo que disparó el envío (null = envío manual desde el admin).
     */
    public function sendAbandonedCartEmail(Cart $cart, ?int $intervalMinutes = null): void
    {
        $intervals = $this->intervals();
        $isLast = $intervalMinutes !== null && $intervals !== [] && $intervalMinutes === end($intervals);

        Mail::to($cart->email)->send(new AbandonedCartMail($cart));

        $cart->forceFill([
            'abandoned_lastdate' => now(),
            'abandoned_count' => $cart->abandoned_count + 1,
            'abandoned_last_interval' => $intervalMinutes ?? $cart->abandoned_last_interval,
            'abandoned_completed_at' => $isLast ? now() : $cart->abandoned_completed_at,
        ])->save();
    }

    /**
     * @param  list<int>  $cartIds
     */
    public function setCartsAsCompleted(array $cartIds): int
    {
        if ($cartIds === []) {
            return 0;
        }

        return Cart::query()->whereKey($cartIds)->whereNull('abandoned_completed_at')->update([
            'abandoned_completed_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    /**
     * Intervalos configurados, en minutos y ordenados ascendente.
     *
     * @return list<int>
     */
    public function intervals(): array
    {
        $intervals = array_values(array_filter(
            array_map('intval', (array) config('shineray.abandoned_cart.intervals_minutes', [])),
            fn (int $minutes): bool => $minutes > 0,
        ));
        sort($intervals);

        return $intervals;
    }
}
