<?php

namespace App\Domain\Cart\Jobs;

use App\Domain\Cart\Services\AbandonedCartService;
use App\Models\Cart;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Porta `schedule-abandoned.ts` (cron cada 5 minutos). Para cada carrito
 * candidato decide si toca enviar el siguiente email, esperar, o darlo por
 * cerrado si se pasó de `max_overdue`.
 */
class DetectAbandonedCartsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Margen tras el primer intervalo para considerar que el carrito "se movió" (5 min en Node). */
    private const ACTIVITY_GRACE_MINUTES = 5;

    public int $uniqueFor = 600;

    public function handle(AbandonedCartService $service): void
    {
        $intervals = $service->intervals();

        if ($intervals === []) {
            Log::info('Carrito abandonado: no hay intervalos configurados, no se envía nada.');

            return;
        }

        $config = config('shineray.abandoned_cart');
        $now = now();
        $toComplete = [];

        $service->candidatesQuery()->limit(1000)->get()->each(function (Cart $cart) use ($intervals, $config, $now, $service, &$toComplete): void {
            $next = $this->nextInterval($cart, $intervals);

            if ($next === null) {
                return;
            }

            $due = $this->dueAt($cart, $intervals[0], $next);

            if ($due->isAfter($now)) {
                return;
            }

            if ($due->diffInMinutes($now) > (int) $config['max_overdue_minutes']) {
                $toComplete[] = $cart->id;

                return;
            }

            $lastDate = $cart->abandoned_lastdate;

            if ($lastDate !== null && $lastDate->addMinutes((int) $config['recently_processed_minutes'])->isAfter($now)) {
                return;
            }

            if ($lastDate !== null && $cart->abandoned_last_interval !== null && $now->isBefore($lastDate->addMinutes($next))) {
                return;
            }

            try {
                $service->sendAbandonedCartEmail($cart, $next);
            } catch (Throwable $exception) {
                Log::error('Carrito abandonado: fallo al enviar email', ['cart_id' => $cart->id, 'error' => $exception->getMessage()]);
            }
        });

        if ($config['set_as_completed_if_overdue']) {
            $service->setCartsAsCompleted($toComplete);
        }
    }

    /**
     * @param  list<int>  $intervals
     */
    private function nextInterval(Cart $cart, array $intervals): ?int
    {
        $index = $cart->abandoned_last_interval === null ? -1 : array_search($cart->abandoned_last_interval, $intervals, true);

        if ($index === -1 || $index === false) {
            return $intervals[0];
        }

        return $intervals[$index + 1] ?? null;
    }

    /**
     * El intervalo se cuenta desde la creación del carrito, salvo que el
     * cliente lo haya seguido editando después del primer intervalo: entonces
     * se cuenta desde la última actividad.
     */
    private function dueAt(Cart $cart, int $firstInterval, int $nextInterval): CarbonInterface
    {
        $createdAt = $cart->created_at ?? now();
        $activityAt = $cart->last_activity_at ?? $cart->updated_at ?? $createdAt;

        $base = $createdAt->diffInMinutes($activityAt) > $firstInterval + self::ACTIVITY_GRACE_MINUTES
            ? $activityAt
            : $createdAt;

        return $base->addMinutes($nextInterval);
    }
}
