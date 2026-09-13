<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Exceptions\CartException;
use App\Enums\CartStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones del carrito del storefront. Nunca valida stock local: el ERP es
 * la autoridad sobre existencias (ver ProductVariant::$attributes).
 */
final readonly class CartService
{
    public function __construct(private CartTotals $totals) {}

    public function create(?Customer $customer = null, ?string $email = null): Cart
    {
        return Cart::create([
            'customer_id' => $customer?->id,
            'email' => $email ?? $customer?->email,
            'status' => CartStatus::Active,
            'last_activity_at' => now(),
        ]);
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): Cart
    {
        $this->assertActive($cart);

        $variant->loadMissing('product');

        if (! $variant->product->isPublished() || $variant->product->trashed()) {
            throw CartException::productUnavailable();
        }

        return DB::transaction(function () use ($cart, $variant, $quantity): Cart {
            $item = $cart->items()->firstWhere('product_variant_id', $variant->id);

            if ($item instanceof CartItem) {
                $item->update(['quantity' => $item->quantity + $quantity]);
            } else {
                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                    // Snapshot del precio: no se recalcula si el producto cambia después.
                    'unit_price' => $variant->price,
                ]);
            }

            return $this->recalculate($cart);
        });
    }

    public function updateItemQuantity(Cart $cart, CartItem $item, int $quantity): Cart
    {
        $this->assertActive($cart);

        return DB::transaction(function () use ($cart, $item, $quantity): Cart {
            $item->update(['quantity' => $quantity]);

            return $this->recalculate($cart);
        });
    }

    public function removeItem(Cart $cart, CartItem $item): Cart
    {
        $this->assertActive($cart);

        return DB::transaction(function () use ($cart, $item): Cart {
            $item->delete();

            return $this->recalculate($cart);
        });
    }

    /**
     * @param  array{email?: string|null, customer?: Customer|null}  $attributes
     */
    public function updateContact(Cart $cart, array $attributes): Cart
    {
        $this->assertActive($cart);

        $cart->fill(array_filter([
            'email' => $attributes['email'] ?? null,
            'customer_id' => ($attributes['customer'] ?? null)?->id,
        ], fn (mixed $value): bool => $value !== null));

        $cart->last_activity_at = now();
        $cart->save();

        return $cart;
    }

    /**
     * @param  array<string, mixed>  $address  first_name, last_name, phone, address_1, city, province, metadata{dni, dni_type, city_id}
     */
    public function setShippingAddress(Cart $cart, array $address): Cart
    {
        $this->assertActive($cart);

        return DB::transaction(function () use ($cart, $address): Cart {
            $shippingAddress = $cart->shippingAddress;

            if ($shippingAddress instanceof Address) {
                $shippingAddress->update($address);
            } else {
                $shippingAddress = $cart->addresses()->create($address);
                $cart->shipping_address_id = $shippingAddress->id;
            }

            // Una nueva dirección invalida la cotización de envío anterior.
            $metadata = $cart->metadata ?? [];
            unset($metadata['shipping_quote_cents']);
            $cart->metadata = $metadata;

            return $this->recalculate($cart);
        });
    }

    /** Guarda la cotización de envío (sin IVA) y recalcula. */
    public function setShippingQuote(Cart $cart, int $quoteCents): Cart
    {
        $this->assertActive($cart);

        $cart->metadata = array_merge($cart->metadata ?? [], ['shipping_quote_cents' => max(0, $quoteCents)]);

        return $this->recalculate($cart);
    }

    public function applyDiscount(Cart $cart, string $code): Cart
    {
        $this->assertActive($cart);

        $discount = Discount::query()->where('code', mb_strtoupper(trim($code)))->first();

        if (! $discount instanceof Discount || ! $discount->isUsable()) {
            throw CartException::discountNotUsable($code);
        }

        return DB::transaction(function () use ($cart, $discount): Cart {
            $cart->discounts()->syncWithoutDetaching([$discount->id]);

            return $this->recalculate($cart);
        });
    }

    public function removeDiscount(Cart $cart, Discount $discount): Cart
    {
        $this->assertActive($cart);

        return DB::transaction(function () use ($cart, $discount): Cart {
            $cart->discounts()->detach($discount->id);

            return $this->recalculate($cart);
        });
    }

    public function recalculate(Cart $cart): Cart
    {
        $cart->load(['items', 'discounts']);

        $cart->fill($this->totals->calculate($cart, $cart->discounts));
        $cart->last_activity_at = now();
        $cart->save();

        return $cart;
    }

    public function assertActive(Cart $cart): void
    {
        if ($cart->status !== CartStatus::Active) {
            throw CartException::notActive();
        }
    }
}
