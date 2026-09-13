<?php

namespace App\Domain\Legacy;

use App\Enums\CartStatus;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingProvider;
use App\Models\Address;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\TaxCalculator;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ETL de una sola pasada desde el esquema de Medusa v1 (TypeORM) al esquema
 * nuevo. Lee de la conexión `legacy` (solo lectura) y escribe con Eloquent.
 *
 * Idempotente: cada fila migrada guarda `metadata._legacy_id`; una segunda
 * corrida (el ETL incremental del corte) solo trae lo que falta. Los
 * productos se casan por SKU y los clientes por email.
 *
 * Reglas verificadas contra los datos actuales:
 * - Precios: `money_amount` en `usd` (ya en centavos, sin IVA).
 * - Todas las variantes migran con `allow_backorder = true`.
 * - Guías `"000000"` se convierten en `shipments.guide_number = null`.
 * - `abandoned_last_interval` pasa de milisegundos a minutos.
 * - Los hashes de contraseña de Medusa son scrypt: no son compatibles con
 *   bcrypt, así que todos los clientes migran sin contraseña y la fijan con
 *   "olvidé mi contraseña" (invitación para B2B).
 */
final class LegacyMigrator
{
    public const LEGACY_ID_KEY = '_legacy_id';

    /** @var array<string, int> legacy customer id → nuevo id */
    private array $customerIds = [];

    /** @var array<string, int> legacy variant id → nuevo id */
    private array $variantIds = [];

    /** @var array<string, int> legacy discount id → nuevo id */
    private array $discountIds = [];

    /** @var array<string, int> legacy cart id → nuevo id */
    private array $cartIds = [];

    /** @var array<string, array{migrated: int, skipped: int}> */
    private array $report = [];

    public function __construct(
        private readonly ConnectionInterface $legacy,
        private readonly TaxCalculator $tax,
        private readonly int $chunkSize = 500,
        private readonly int $cartsDays = 30,
        private readonly ?Closure $log = null,
    ) {}

    /**
     * @param  list<string>  $only  Subconjunto de pasos a correr (en el orden canónico).
     * @return array<string, array{migrated: int, skipped: int}>
     */
    public function run(array $only = []): array
    {
        $steps = [
            'customers' => $this->migrateCustomers(...),
            'products' => $this->migrateProducts(...),
            'discounts' => $this->migrateDiscounts(...),
            'carts' => $this->migrateCarts(...),
            'orders' => $this->migrateOrders(...),
            'b2b' => $this->migrateB2b(...),
        ];

        foreach ($steps as $name => $step) {
            if ($only !== [] && ! in_array($name, $only, true)) {
                continue;
            }

            $this->report[$name] = ['migrated' => 0, 'skipped' => 0];
            $this->info("→ {$name}");
            $step();
            $this->info(sprintf('   %d migrados, %d omitidos', $this->report[$name]['migrated'], $this->report[$name]['skipped']));
        }

        return $this->report;
    }

    private function migrateCustomers(): void
    {
        $existingByEmail = Customer::withTrashed()->pluck('id', 'email')->mapWithKeys(fn ($id, $email) => [mb_strtolower((string) $email) => (int) $id])->all();

        $this->legacy->table('customer')->whereNull('deleted_at')->orderBy('id')->chunk($this->chunkSize, function (Collection $rows) use (&$existingByEmail): void {
            foreach ($rows as $row) {
                $email = mb_strtolower(trim((string) $row->email));
                $metadata = $this->json($row->metadata);

                if (isset($existingByEmail[$email])) {
                    $this->customerIds[$row->id] = $existingByEmail[$email];
                    $this->skip('customers');

                    continue;
                }

                $customer = Customer::create([
                    'email' => $email,
                    'password' => null,
                    'first_name' => $row->first_name,
                    'last_name' => $row->last_name,
                    'phone' => $row->phone,
                    'dni' => isset($metadata['dni']) ? (string) $metadata['dni'] : null,
                    'is_b2b' => (bool) ($metadata['b2b'] ?? false),
                    'metadata' => $metadata + [self::LEGACY_ID_KEY => $row->id, '_legacy_has_account' => (bool) $row->has_account],
                ]);
                $customer->forceFill(['created_at' => $row->created_at])->saveQuietly();

                $this->customerIds[$row->id] = $customer->id;
                $existingByEmail[$email] = $customer->id;
                $this->migrated('customers');
            }
        });

        // Libreta de direcciones del cliente (las de carrito/orden se copian con su padre).
        $this->legacy->table('address')->whereNotNull('customer_id')->whereNull('deleted_at')->orderBy('id')->chunk($this->chunkSize, function (Collection $rows): void {
            foreach ($rows as $row) {
                $customerId = $this->customerIds[$row->customer_id] ?? null;

                if ($customerId === null || Address::query()->where('addressable_type', (new Customer)->getMorphClass())->where('addressable_id', $customerId)->where('metadata->'.self::LEGACY_ID_KEY, $row->id)->exists()) {
                    continue;
                }

                (new Address($this->addressAttributes($row)))
                    ->forceFill(['addressable_type' => (new Customer)->getMorphClass(), 'addressable_id' => $customerId])
                    ->save();
            }
        });
    }

    private function migrateProducts(): void
    {
        Product::withoutSyncingToSearch(function (): void {
            $this->legacy->table('product')->whereNull('deleted_at')->orderBy('id')->chunk($this->chunkSize, function (Collection $rows): void {
                $variants = $this->legacy->table('product_variant')
                    ->whereIn('product_id', $rows->pluck('id'))
                    ->whereNull('deleted_at')
                    ->orderBy('created_at')
                    ->get()
                    ->groupBy('product_id');

                $prices = $this->legacy->table('money_amount')
                    ->whereIn('variant_id', $variants->flatten()->pluck('id'))
                    ->where('currency_code', 'usd')
                    ->whereNull('deleted_at')
                    ->whereNull('price_list_id')
                    ->orderBy('created_at')
                    ->get()
                    ->groupBy('variant_id');

                foreach ($rows as $row) {
                    $variant = $variants->get($row->id)?->first();

                    if ($variant === null || blank($variant->sku)) {
                        $this->skip('products');

                        continue;
                    }

                    $existing = ProductVariant::query()->where('sku', $variant->sku)->first();

                    if ($existing instanceof ProductVariant) {
                        $this->variantIds[$variant->id] = $existing->id;
                        $this->skip('products');

                        continue;
                    }

                    $product = Product::create([
                        'title' => $row->title,
                        'description' => $row->description,
                        'handle' => $this->uniqueHandle((string) ($row->handle ?: Str::slug($row->title.'-'.$variant->sku))),
                        'thumbnail' => $row->thumbnail,
                        'status' => $row->status === 'published' ? ProductStatus::Published : ProductStatus::Draft,
                        'metadata' => $this->json($row->metadata) + [self::LEGACY_ID_KEY => $row->id],
                    ]);
                    $product->forceFill(['created_at' => $row->created_at, 'updated_at' => $row->updated_at])->saveQuietly();

                    $newVariant = $product->variants()->create([
                        'sku' => $variant->sku,
                        'title' => $variant->title ?: 'Default',
                        'price' => (int) ($prices->get($variant->id)?->first()->amount ?? 0),
                        'inventory_quantity' => (int) ($variant->inventory_quantity ?? 0),
                        'allow_backorder' => true,
                        'manage_inventory' => false,
                        'weight_kg' => $variant->weight === null ? null : (float) $variant->weight,
                        'metadata' => [self::LEGACY_ID_KEY => $variant->id],
                    ]);

                    $this->variantIds[$variant->id] = $newVariant->id;
                    $this->migrated('products');
                }
            });
        });
    }

    private function migrateDiscounts(): void
    {
        $rows = $this->legacy->table('discount')
            ->leftJoin('discount_rule', 'discount_rule.id', '=', 'discount.rule_id')
            ->whereNull('discount.deleted_at')
            ->orderBy('discount.id')
            ->get(['discount.*', 'discount_rule.type as rule_type', 'discount_rule.value as rule_value']);

        foreach ($rows as $row) {
            $code = mb_strtoupper(trim((string) $row->code));
            $existing = Discount::query()->where('code', $code)->first();

            if ($existing instanceof Discount) {
                $this->discountIds[$row->id] = $existing->id;
                $this->skip('discounts');

                continue;
            }

            $discount = Discount::create([
                'code' => $code,
                'type' => DiscountType::tryFrom((string) $row->rule_type) ?? DiscountType::Fixed,
                'value' => (int) ($row->rule_value ?? 0),
                'is_active' => ! (bool) $row->is_disabled,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'usage_limit' => $row->usage_limit,
                'usage_count' => (int) ($row->usage_count ?? 0),
            ]);

            $this->discountIds[$row->id] = $discount->id;
            $this->migrated('discounts');
        }
    }

    /**
     * Solo carritos abiertos recientes: son los que alimentan el flujo de
     * carrito abandonado. Los cerrados ya existen como órdenes.
     */
    private function migrateCarts(): void
    {
        $this->cartIds = Cart::query()->whereNotNull('metadata->'.self::LEGACY_ID_KEY)->get(['id', 'metadata'])
            ->mapWithKeys(fn (Cart $cart): array => [(string) $cart->metadata[self::LEGACY_ID_KEY] => $cart->id])
            ->all();

        $this->legacy->table('cart')
            ->whereNull('deleted_at')
            ->whereNull('completed_at')
            ->where('created_at', '>=', now()->subDays($this->cartsDays))
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows): void {
                $items = $this->legacy->table('line_item')->whereIn('cart_id', $rows->pluck('id'))->whereNull('order_id')->get()->groupBy('cart_id');
                $addresses = $this->legacy->table('address')->whereIn('id', $rows->pluck('shipping_address_id')->filter())->get()->keyBy('id');

                foreach ($rows as $row) {
                    if (isset($this->cartIds[$row->id])) {
                        $this->skip('carts');

                        continue;
                    }

                    $cart = Cart::create([
                        'customer_id' => $this->customerIds[$row->customer_id] ?? null,
                        'email' => $row->email,
                        'status' => CartStatus::Active,
                        'metadata' => $this->json($row->metadata) + [self::LEGACY_ID_KEY => $row->id],
                        'abandoned_completed_at' => $row->abandoned_completed_at,
                        'abandoned_count' => (int) ($row->abandoned_count ?? 0),
                        'abandoned_last_interval' => $row->abandoned_last_interval === null ? null : (int) round(((int) $row->abandoned_last_interval) / 60000),
                        'abandoned_lastdate' => $row->abandoned_lastdate,
                        'last_activity_at' => $row->updated_at ?? $row->created_at,
                    ]);

                    $this->copyItems($cart, $items->get($row->id) ?? collect());

                    if ($row->shipping_address_id !== null && ($address = $addresses->get($row->shipping_address_id)) !== null) {
                        $shipping = $cart->addresses()->create($this->addressAttributes($address));
                        $cart->shipping_address_id = $shipping->id;
                    }

                    $cart->fill($this->totalsFor($cart, 0, 0));
                    $cart->forceFill(['created_at' => $row->created_at, 'updated_at' => $row->updated_at])->save();

                    $this->cartIds[$row->id] = $cart->id;
                    $this->migrated('carts');
                }
            });
    }

    private function migrateOrders(): void
    {
        $migrated = Order::query()->whereNotNull('metadata->'.self::LEGACY_ID_KEY)->get(['id', 'metadata'])
            ->mapWithKeys(fn (Order $order): array => [(string) $order->metadata[self::LEGACY_ID_KEY] => true])
            ->all();

        $this->legacy->table('order')->orderBy('id')->chunk($this->chunkSize, function (Collection $rows) use (&$migrated): void {
            $items = $this->legacy->table('line_item')->whereIn('order_id', $rows->pluck('id'))->get()->groupBy('order_id');
            $addresses = $this->legacy->table('address')->whereIn('id', $rows->pluck('shipping_address_id')->merge($rows->pluck('billing_address_id'))->filter())->get()->keyBy('id');
            $payments = $this->legacy->table('payment')->whereIn('order_id', $rows->pluck('id'))->get()->groupBy('order_id');
            $shippingMethods = $this->legacy->table('shipping_method')->whereIn('order_id', $rows->pluck('id'))->get()->groupBy('order_id');
            $discounts = $this->legacy->table('order_discounts')->whereIn('order_id', $rows->pluck('id'))->get()->groupBy('order_id');

            foreach ($rows as $row) {
                if (isset($migrated[$row->id])) {
                    $this->skip('orders');

                    continue;
                }

                $shipping = $row->shipping_address_id !== null ? $addresses->get($row->shipping_address_id) : null;
                $shippingMeta = $shipping !== null ? $this->json($shipping->metadata) : [];
                $payment = $payments->get($row->id)?->sortByDesc('created_at')->first();
                $gateway = $this->gatewayFor($payment?->provider_id);
                $customerMeta = $row->customer_id !== null ? $this->customerMetadata($row->customer_id) : [];
                $isB2b = $gateway?->isB2b() || (bool) ($customerMeta['b2b'] ?? false);

                $order = Order::create([
                    'order_number' => $this->orderNumber($row->display_id, $row->id),
                    'cart_id' => $this->cartIds[$row->cart_id] ?? null,
                    'customer_id' => $this->customerIds[$row->customer_id] ?? null,
                    'email' => $row->email,
                    'status' => $this->orderStatus($row),
                    'metadata' => array_filter([
                        self::LEGACY_ID_KEY => $row->id,
                        '_legacy_display_id' => $row->display_id,
                        'gateway' => $gateway?->value,
                        'is_b2b' => $isB2b,
                        'dni' => $shippingMeta['dni'] ?? null,
                        'dni_type' => $shippingMeta['dniType'] ?? $shippingMeta['dni_type'] ?? null,
                        'city_id' => $shippingMeta['city_id'] ?? null,
                        'city_name' => $shippingMeta['city_name'] ?? null,
                        'transportista_id' => $shippingMeta['transportista_id'] ?? null,
                        'transportista_name' => $shippingMeta['transportista_name'] ?? null,
                        'installments' => $shippingMeta['numeroCuota'] ?? null,
                        'cod_client' => $shippingMeta['cod_client'] ?? null,
                        'ruc' => $shippingMeta['ruc'] ?? null,
                        'address' => $shippingMeta['address'] ?? null,
                    ], fn (mixed $value): bool => $value !== null),
                    'paid_at' => $row->created_at,
                    'canceled_at' => $row->canceled_at,
                ]);

                $this->copyItems($order, $items->get($row->id) ?? collect());

                if ($shipping !== null) {
                    $address = $order->addresses()->create($this->addressAttributes($shipping));
                    $order->shipping_address_id = $address->id;
                    $order->billing_address_id = $address->id;
                }

                $discountIds = array_values(($discounts->get($row->id) ?? collect())->pluck('discount_id')->map(fn ($id): ?int => $this->discountIds[$id] ?? null)->filter()->all());
                $order->discounts()->sync($discountIds);

                $shippingCents = (int) ($shippingMethods->get($row->id)?->sum('price') ?? 0);
                $discountCents = $this->legacyDiscountTotal($order, $discountIds);
                $order->fill($this->totalsFor($order, $shippingCents, $discountCents));
                $order->forceFill(['created_at' => $row->created_at, 'updated_at' => $row->updated_at])->save();

                if ($payment !== null && $gateway !== null) {
                    $data = $this->json($payment->data);
                    $order->payments()->create([
                        'gateway' => $gateway,
                        'status' => $row->canceled_at !== null ? PaymentStatus::Canceled : PaymentStatus::Authorized,
                        'amount' => (int) $payment->amount,
                        'gateway_reference' => (string) ($data['datafastResponse']['id'] ?? $data['id'] ?? $data['checkoutId'] ?? $payment->id),
                        'response_code' => isset($data['datafastResponse']['result']['code']) ? (string) $data['datafastResponse']['result']['code'] : null,
                        'raw_response' => $data['datafastResponse'] ?? $data,
                        'authorized_at' => $payment->captured_at ?? $payment->created_at,
                    ]);
                }

                $guide = (string) ($shippingMeta['servientrega_guide_id'] ?? '');
                $order->shipments()->create([
                    'provider' => ShippingProvider::Servientrega,
                    'guide_number' => $guide !== '' && $guide !== '000000' ? $guide : null,
                    'status' => $guide !== '' && $guide !== '000000' ? ShipmentStatus::Created : ShipmentStatus::Pending,
                ]);

                $migrated[$row->id] = true;
                $this->migrated('orders');
            }
        });
    }

    private function migrateB2b(): void
    {
        $this->legacy->table('client_b2b')->orderBy('id')->chunk($this->chunkSize, function (Collection $rows): void {
            foreach ($rows as $row) {
                if (blank($row->id_client)) {
                    $this->skip('b2b');

                    continue;
                }

                $client = B2bClient::query()->firstOrNew(['id_client' => $row->id_client]);
                $wasNew = ! $client->exists;
                $email = $row->email !== null ? mb_strtolower(trim((string) $row->email)) : null;

                $client->fill([
                    'type_client' => $row->type_client,
                    'first_name' => (string) $row->first_name,
                    'last_name' => (string) $row->last_name,
                    'email' => $email,
                    'phone_number' => $row->phone_number,
                    'address' => $this->json($row->address),
                    'active' => filter_var($row->active ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);

                if ($client->customer_id === null && $email !== null) {
                    $customer = Customer::query()->where('email', $email)->first();

                    if ($customer instanceof Customer && $customer->b2bClient()->doesntExist()) {
                        $client->customer_id = $customer->id;
                    }
                }

                $client->save();
                $wasNew ? $this->migrated('b2b') : $this->skip('b2b');
            }
        });

        foreach ($this->legacy->table('policies_b2b')->orderBy('id')->get() as $row) {
            if (blank($row->cod_cliente)) {
                continue;
            }

            B2bPolicy::query()->updateOrCreate(
                ['client_type' => $row->cod_cliente, 'installments' => (int) ($row->num_cuotas ?? 1)],
                ['is_active' => (bool) $row->es_activo, 'credit_factor' => (float) ($row->factor_credito ?? 0)],
            );
        }

        foreach ($this->legacy->table('transportistas_b2b')->orderBy('id')->get() as $row) {
            if (blank($row->ruc)) {
                continue;
            }

            B2bTransportista::query()->updateOrCreate(['ruc' => $row->ruc], ['business_name' => (string) $row->razon_social]);
        }
    }

    /**
     * @param  Collection<int, \stdClass>  $items
     */
    private function copyItems(Cart|Order $parent, Collection $items): void
    {
        foreach ($items as $item) {
            $variantId = $this->variantIds[$item->variant_id] ?? ProductVariant::query()->where('metadata->'.self::LEGACY_ID_KEY, $item->variant_id)->value('id');

            if ($parent instanceof Cart) {
                if ($variantId === null) {
                    continue;
                }

                $parent->items()->create([
                    'product_variant_id' => $variantId,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) $item->unit_price,
                    'metadata' => [self::LEGACY_ID_KEY => $item->id],
                ]);

                continue;
            }

            $variant = $variantId !== null ? ProductVariant::query()->whereKey($variantId)->first() : null;

            $parent->items()->create([
                'product_variant_id' => $variantId,
                'title' => (string) $item->title,
                'sku' => (string) ($variant->sku ?? $this->json($item->metadata)['sku'] ?? $item->variant_id ?? 'legacy'),
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
                'metadata' => [self::LEGACY_ID_KEY => $item->id, 'cod_producto' => $variant?->sku],
            ]);
        }
    }

    /**
     * Medusa no persiste totales: se recalculan con la misma convención del
     * checkout nuevo (IVA sobre subtotal − descuento + envío).
     *
     * @return array{subtotal: int, discount_total: int, shipping_total: int, tax_total: int, total: int}
     */
    private function totalsFor(Cart|Order $parent, int $shippingCents, int $discountCents): array
    {
        $subtotal = (int) $parent->items()->get()->sum(fn ($item): int => $item->quantity * $item->unit_price);
        $discount = min($subtotal, $discountCents);
        $taxable = $subtotal - $discount + $shippingCents;
        $tax = $this->tax->taxFromNet($taxable);

        return ['subtotal' => $subtotal, 'discount_total' => $discount, 'shipping_total' => $shippingCents, 'tax_total' => $tax, 'total' => $taxable + $tax];
    }

    /**
     * @param  list<int>  $discountIds
     */
    private function legacyDiscountTotal(Order $order, array $discountIds): int
    {
        if ($discountIds === []) {
            return 0;
        }

        $subtotal = (int) $order->items()->get()->sum(fn ($item): int => $item->quantity * $item->unit_price);

        return (int) Discount::query()->whereKey($discountIds)->get()->sum(fn (Discount $discount): int => $discount->amountFor($subtotal));
    }

    /**
     * @return array<string, mixed>
     */
    private function addressAttributes(\stdClass $row): array
    {
        $metadata = $this->json($row->metadata);

        if (isset($metadata['dniType'])) {
            $metadata['dni_type'] = $metadata['dniType'];
            unset($metadata['dniType']);
        }

        return [
            'first_name' => (string) ($row->first_name ?? ''),
            'last_name' => (string) ($row->last_name ?? ''),
            'phone' => $row->phone,
            'address_1' => (string) ($row->address_1 ?? ''),
            'address_2' => $row->address_2,
            'city' => (string) ($row->city ?? ''),
            'province' => (string) ($row->province ?? ''),
            'country_code' => strtoupper((string) ($row->country_code ?: 'EC')),
            'postal_code' => (string) ($row->postal_code ?: '000000'),
            'metadata' => $metadata + [self::LEGACY_ID_KEY => $row->id],
        ];
    }

    private function gatewayFor(?string $providerId): ?PaymentGateway
    {
        if ($providerId === null) {
            return null;
        }

        $id = Str::lower($providerId);
        $b2b = str_contains($id, 'b2b');

        return match (true) {
            str_contains($id, 'credito') => PaymentGateway::CreditoB2b,
            str_contains($id, 'datafast') => $b2b ? PaymentGateway::DatafastB2b : PaymentGateway::Datafast,
            str_contains($id, 'deuna') => $b2b ? PaymentGateway::DeunaB2b : PaymentGateway::Deuna,
            default => PaymentGateway::Manual,
        };
    }

    private function orderStatus(\stdClass $row): OrderStatus
    {
        if ($row->canceled_at !== null || $row->status === 'canceled') {
            return OrderStatus::Canceled;
        }

        if (($row->payment_status ?? null) === 'refunded') {
            return OrderStatus::Refunded;
        }

        if (in_array($row->fulfillment_status ?? null, ['shipped', 'fulfilled'], true) || $row->status === 'completed') {
            return OrderStatus::Fulfilled;
        }

        return OrderStatus::Paid;
    }

    /** Conserva el número visible de Medusa: `SH-L000123`. */
    private function orderNumber(mixed $displayId, string $legacyId): string
    {
        $number = $displayId !== null ? 'SH-L'.str_pad((string) $displayId, 6, '0', STR_PAD_LEFT) : 'SH-L'.Str::upper(Str::substr($legacyId, -8));

        while (Order::query()->where('order_number', $number)->exists()) {
            $number .= '-'.Str::upper(Str::random(3));
        }

        return $number;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerMetadata(string $legacyCustomerId): array
    {
        $row = $this->legacy->table('customer')->where('id', $legacyCustomerId)->first(['metadata']);

        return $row === null ? [] : $this->json($row->metadata);
    }

    private function uniqueHandle(string $handle): string
    {
        $candidate = $handle;

        while (Product::withTrashed()->where('handle', $candidate)->exists()) {
            $candidate = $handle.'-'.Str::lower(Str::random(4));
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function migrated(string $step): void
    {
        $this->report[$step]['migrated']++;
    }

    private function skip(string $step): void
    {
        $this->report[$step]['skipped']++;
    }

    private function info(string $message): void
    {
        if ($this->log !== null) {
            ($this->log)($message);
        }
    }

    /**
     * Conteos de referencia para `migrate:legacy-verify`.
     *
     * @return array<string, array{legacy: int, new: int}>
     */
    public function counts(): array
    {
        return [
            'customers' => ['legacy' => $this->legacy->table('customer')->whereNull('deleted_at')->count(), 'new' => Customer::query()->count()],
            'products' => ['legacy' => $this->legacy->table('product')->whereNull('deleted_at')->count(), 'new' => Product::query()->count()],
            'orders' => ['legacy' => $this->legacy->table('order')->count(), 'new' => Order::query()->count()],
            'discounts' => ['legacy' => $this->legacy->table('discount')->whereNull('deleted_at')->count(), 'new' => Discount::query()->count()],
            'b2b_clients' => ['legacy' => $this->legacy->table('client_b2b')->count(), 'new' => B2bClient::query()->count()],
            'b2b_transportistas' => ['legacy' => $this->legacy->table('transportistas_b2b')->count(), 'new' => B2bTransportista::query()->count()],
        ];
    }

    /**
     * Muestra aleatoria de órdenes: compara email y subtotal calculado desde
     * los line items de Medusa con lo migrado.
     *
     * @return list<array{order_number: string, ok: bool, detail: string}>
     */
    public function sampleOrders(int $size = 20): array
    {
        $results = [];

        foreach (Order::query()->whereNotNull('metadata->'.self::LEGACY_ID_KEY)->inRandomOrder()->limit($size)->get() as $order) {
            $legacyId = (string) $order->metadata[self::LEGACY_ID_KEY];
            $legacy = $this->legacy->table('order')->where('id', $legacyId)->first();
            $legacySubtotal = (int) $this->legacy->table('line_item')->where('order_id', $legacyId)->get()->sum(fn ($i): int => (int) $i->quantity * (int) $i->unit_price);

            $ok = $legacy !== null && mb_strtolower((string) $legacy->email) === mb_strtolower($order->email) && $legacySubtotal === $order->subtotal;
            $results[] = ['order_number' => $order->order_number, 'ok' => $ok, 'detail' => sprintf('email %s / subtotal legacy %d vs nuevo %d', $legacy->email ?? '?', $legacySubtotal, $order->subtotal)];
        }

        return $results;
    }

    public static function legacyQuery(string $table): Builder
    {
        return DB::connection('legacy')->table($table);
    }
}
