<?php

namespace App\Domain\Orders\Services;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Números de orden legibles y no enumerables: `SH-250913-7K2QX9`.
 */
final class OrderNumberGenerator
{
    public function generate(): string
    {
        $prefix = (string) config('shineray.checkout.order_number_prefix', 'SH');

        do {
            $candidate = sprintf('%s-%s-%s', $prefix, now()->format('ymd'), Str::upper(Str::random(6)));
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
