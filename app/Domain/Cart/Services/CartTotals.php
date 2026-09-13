<?php

namespace App\Domain\Cart\Services;

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\Discount;
use App\Support\TaxCalculator;
use Illuminate\Support\Collection;

/**
 * Cálculo de totales del carrito. Convención (misma que el checkout actual,
 * donde Datafast descompone `total / 1.15` como base imponible):
 *
 * - `subtotal`: Σ cantidad × precio unitario, sin IVA.
 * - `discount_total`: descuentos de porcentaje/monto fijo sobre el subtotal.
 * - `shipping_total`: costo de envío sin IVA (0 si hay cupón de envío gratis;
 *   la cotización original queda en `metadata.shipping_quote_cents`).
 * - `tax_total`: IVA sobre (subtotal − descuento + envío).
 * - `total`: subtotal − descuento + envío + IVA.
 */
final readonly class CartTotals
{
    public function __construct(private TaxCalculator $tax) {}

    /**
     * @param  Collection<int, Discount>  $discounts
     * @return array{subtotal: int, discount_total: int, shipping_total: int, tax_total: int, total: int}
     */
    public function calculate(Cart $cart, Collection $discounts): array
    {
        $subtotal = (int) $cart->items->sum(fn ($item) => $item->quantity * $item->unit_price);

        $discountTotal = min($subtotal, (int) $discounts
            ->reject(fn (Discount $discount): bool => $discount->type === DiscountType::FreeShipping)
            ->sum(fn (Discount $discount): int => $discount->amountFor($subtotal)));

        $shippingQuote = (int) ($cart->metadata['shipping_quote_cents'] ?? 0);
        $freeShipping = $discounts->contains(fn (Discount $discount): bool => $discount->type === DiscountType::FreeShipping);
        $shippingTotal = $freeShipping ? 0 : $shippingQuote;

        $taxable = $subtotal - $discountTotal + $shippingTotal;
        $taxTotal = $this->tax->taxFromNet($taxable);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'total' => $taxable + $taxTotal,
        ];
    }
}
