<?php

use App\Domain\Legacy\LegacyMigrator;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    DB::purge('legacy');
    $schema = Schema::connection('legacy');

    $schema->create('customer', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('email');
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('phone')->nullable();
        $t->string('password_hash')->nullable();
        $t->boolean('has_account')->default(false);
        $t->text('metadata')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('address', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('customer_id')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('address_1')->nullable();
        $t->string('address_2')->nullable();
        $t->string('city')->nullable();
        $t->string('province')->nullable();
        $t->string('country_code')->nullable();
        $t->string('postal_code')->nullable();
        $t->string('phone')->nullable();
        $t->text('metadata')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('product', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('title');
        $t->text('description')->nullable();
        $t->string('handle')->nullable();
        $t->string('thumbnail')->nullable();
        $t->string('status');
        $t->text('metadata')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('product_variant', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('product_id');
        $t->string('sku')->nullable();
        $t->string('title')->nullable();
        $t->integer('inventory_quantity')->default(0);
        $t->boolean('allow_backorder')->default(false);
        $t->boolean('manage_inventory')->default(true);
        $t->integer('weight')->nullable();
        $t->text('metadata')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('money_amount', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('variant_id');
        $t->string('currency_code');
        $t->integer('amount');
        $t->string('price_list_id')->nullable();
        $t->string('region_id')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('discount', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('code');
        $t->string('rule_id')->nullable();
        $t->boolean('is_disabled')->default(false);
        $t->timestamp('starts_at')->nullable();
        $t->timestamp('ends_at')->nullable();
        $t->integer('usage_limit')->nullable();
        $t->integer('usage_count')->default(0);
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('discount_rule', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('type');
        $t->integer('value')->default(0);
    });
    $schema->create('cart', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('email')->nullable();
        $t->string('customer_id')->nullable();
        $t->string('shipping_address_id')->nullable();
        $t->text('metadata')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->timestamp('abandoned_completed_at')->nullable();
        $t->integer('abandoned_count')->nullable();
        $t->bigInteger('abandoned_last_interval')->nullable();
        $t->timestamp('abandoned_lastdate')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    $schema->create('line_item', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('cart_id')->nullable();
        $t->string('order_id')->nullable();
        $t->string('title');
        $t->integer('unit_price');
        $t->string('variant_id')->nullable();
        $t->integer('quantity');
        $t->text('metadata')->nullable();
    });
    $schema->create('order', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('status');
        $t->string('fulfillment_status')->nullable();
        $t->string('payment_status')->nullable();
        $t->integer('display_id');
        $t->string('cart_id')->nullable();
        $t->string('customer_id')->nullable();
        $t->string('email');
        $t->string('billing_address_id')->nullable();
        $t->string('shipping_address_id')->nullable();
        $t->text('metadata')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->timestamp('canceled_at')->nullable();
    });
    $schema->create('payment', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('order_id')->nullable();
        $t->integer('amount');
        $t->string('provider_id');
        $t->text('data')->nullable();
        $t->timestamp('captured_at')->nullable();
        $t->timestamp('created_at')->nullable();
    });
    $schema->create('shipping_method', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('order_id')->nullable();
        $t->integer('price')->default(0);
    });
    $schema->create('order_discounts', function (Blueprint $t) {
        $t->string('order_id');
        $t->string('discount_id');
    });
    $schema->create('client_b2b', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('id_client')->nullable();
        $t->string('type_client')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('email')->nullable();
        $t->string('phone_number')->nullable();
        $t->string('active')->nullable();
        $t->text('address')->nullable();
    });
    $schema->create('policies_b2b', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->boolean('es_activo')->nullable();
        $t->float('factor_credito')->nullable();
        $t->integer('num_cuotas')->nullable();
        $t->string('cod_cliente')->nullable();
    });
    $schema->create('transportistas_b2b', function (Blueprint $t) {
        $t->string('id')->primary();
        $t->string('razon_social')->nullable();
        $t->string('ruc')->nullable();
    });

    $legacy = DB::connection('legacy');
    $legacy->table('customer')->insert([
        ['id' => 'cus_1', 'email' => 'Ana@Example.com', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '099', 'password_hash' => 'scrypt$abc', 'has_account' => true, 'metadata' => json_encode(['dni' => '0999999999']), 'created_at' => '2025-01-10 10:00:00', 'deleted_at' => null],
        ['id' => 'cus_2', 'email' => 'b2b@example.com', 'first_name' => 'Luis', 'last_name' => 'Gómez', 'phone' => null, 'password_hash' => 'scrypt$shared', 'has_account' => true, 'metadata' => json_encode(['b2b' => true, 'ruc' => '0999999999001', 'cod_client' => 'DM']), 'created_at' => '2025-02-01 10:00:00', 'deleted_at' => null],
        ['id' => 'cus_3', 'email' => 'borrado@example.com', 'first_name' => null, 'last_name' => null, 'phone' => null, 'password_hash' => null, 'has_account' => false, 'metadata' => null, 'created_at' => '2025-02-01 10:00:00', 'deleted_at' => '2025-03-01 10:00:00'],
    ]);
    $legacy->table('address')->insert([
        ['id' => 'addr_book', 'customer_id' => 'cus_1', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'address_1' => 'Casa', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'country_code' => 'ec', 'postal_code' => null, 'phone' => '099', 'metadata' => null, 'deleted_at' => null],
        ['id' => 'addr_order', 'customer_id' => null, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'address_1' => 'Calle 1', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'country_code' => 'ec', 'postal_code' => '000000', 'phone' => '099', 'metadata' => json_encode(['dni' => '0999999999', 'dniType' => 1, 'city_id' => 42, 'servientrega_guide_id' => '0123456789']), 'deleted_at' => null],
        ['id' => 'addr_b2b', 'customer_id' => null, 'first_name' => 'Luis', 'last_name' => 'Gómez', 'address_1' => 'Bodega', 'city' => 'GUAYAQUIL', 'province' => 'GUAYAS', 'country_code' => 'ec', 'postal_code' => '000000', 'phone' => '098', 'metadata' => json_encode(['ruc' => '0999999999001', 'cod_client' => 'DM', 'transportista_id' => 'T1', 'numeroCuota' => 3, 'servientrega_guide_id' => '000000']), 'deleted_at' => null],
        ['id' => 'addr_cart', 'customer_id' => null, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'address_1' => 'Calle 1', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'country_code' => 'ec', 'postal_code' => '000000', 'phone' => '099', 'metadata' => json_encode(['dni' => '0999999999']), 'deleted_at' => null],
    ]);
    $legacy->table('product')->insert([
        ['id' => 'prod_1', 'title' => 'Pistón 150cc', 'description' => 'desc', 'handle' => 'piston-150cc', 'thumbnail' => 'https://cdn/p1.jpg', 'status' => 'published', 'metadata' => json_encode(['COD_PRODUCTO' => 'MOT-1', 'NOMBRE_CATEGORIA' => 'CONJUNTO DE MOTOR', 'is_b2b' => true]), 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-02 00:00:00', 'deleted_at' => null],
        ['id' => 'prod_2', 'title' => 'Descontinuado', 'description' => null, 'handle' => 'descontinuado', 'thumbnail' => null, 'status' => 'draft', 'metadata' => json_encode(['COD_PRODUCTO' => 'OLD-1']), 'created_at' => '2025-01-01 00:00:00', 'updated_at' => null, 'deleted_at' => null],
        ['id' => 'prod_3', 'title' => 'Borrado', 'description' => null, 'handle' => 'borrado', 'thumbnail' => null, 'status' => 'published', 'metadata' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => null, 'deleted_at' => '2025-05-01 00:00:00'],
    ]);
    $legacy->table('product_variant')->insert([
        ['id' => 'var_1', 'product_id' => 'prod_1', 'sku' => 'MOT-1', 'title' => 'Color', 'inventory_quantity' => 12, 'allow_backorder' => false, 'manage_inventory' => true, 'weight' => 2, 'metadata' => null, 'created_at' => '2025-01-01 00:00:00', 'deleted_at' => null],
        ['id' => 'var_2', 'product_id' => 'prod_2', 'sku' => 'OLD-1', 'title' => 'Color', 'inventory_quantity' => 0, 'allow_backorder' => false, 'manage_inventory' => true, 'weight' => null, 'metadata' => null, 'created_at' => '2025-01-01 00:00:00', 'deleted_at' => null],
    ]);
    $legacy->table('money_amount')->insert([
        ['id' => 'ma_1', 'variant_id' => 'var_1', 'currency_code' => 'usd', 'amount' => 1130, 'price_list_id' => null, 'region_id' => null, 'created_at' => '2025-01-01 00:00:00', 'deleted_at' => null],
        ['id' => 'ma_2', 'variant_id' => 'var_2', 'currency_code' => 'usd', 'amount' => 500, 'price_list_id' => null, 'region_id' => null, 'created_at' => '2025-01-01 00:00:00', 'deleted_at' => null],
    ]);
    $legacy->table('discount_rule')->insert(['id' => 'rule_1', 'type' => 'percentage', 'value' => 10]);
    $legacy->table('discount')->insert(['id' => 'disc_1', 'code' => 'diez', 'rule_id' => 'rule_1', 'is_disabled' => false, 'starts_at' => null, 'ends_at' => null, 'usage_limit' => null, 'usage_count' => 2, 'deleted_at' => null]);
    $legacy->table('cart')->insert([
        ['id' => 'cart_open', 'email' => 'ana@example.com', 'customer_id' => 'cus_1', 'shipping_address_id' => 'addr_cart', 'metadata' => null, 'completed_at' => null, 'abandoned_completed_at' => null, 'abandoned_count' => 1, 'abandoned_last_interval' => 3600000, 'abandoned_lastdate' => '2026-09-12 10:00:00', 'created_at' => now()->subDays(2)->toDateTimeString(), 'updated_at' => now()->subDay()->toDateTimeString(), 'deleted_at' => null],
        ['id' => 'cart_old', 'email' => 'viejo@example.com', 'customer_id' => null, 'shipping_address_id' => null, 'metadata' => null, 'completed_at' => null, 'abandoned_completed_at' => null, 'abandoned_count' => 0, 'abandoned_last_interval' => null, 'abandoned_lastdate' => null, 'created_at' => now()->subDays(90)->toDateTimeString(), 'updated_at' => null, 'deleted_at' => null],
        ['id' => 'cart_done', 'email' => 'ana@example.com', 'customer_id' => 'cus_1', 'shipping_address_id' => null, 'metadata' => null, 'completed_at' => '2025-06-01 10:00:00', 'abandoned_completed_at' => null, 'abandoned_count' => 0, 'abandoned_last_interval' => null, 'abandoned_lastdate' => null, 'created_at' => '2025-06-01 09:00:00', 'updated_at' => null, 'deleted_at' => null],
    ]);
    $legacy->table('line_item')->insert([
        ['id' => 'li_cart', 'cart_id' => 'cart_open', 'order_id' => null, 'title' => 'Pistón 150cc', 'unit_price' => 1130, 'variant_id' => 'var_1', 'quantity' => 2, 'metadata' => null],
        ['id' => 'li_o1', 'cart_id' => 'cart_done', 'order_id' => 'order_1', 'title' => 'Pistón 150cc', 'unit_price' => 1130, 'variant_id' => 'var_1', 'quantity' => 2, 'metadata' => null],
        ['id' => 'li_o2', 'cart_id' => null, 'order_id' => 'order_2', 'title' => 'Producto viejo', 'unit_price' => 5000, 'variant_id' => 'var_gone', 'quantity' => 1, 'metadata' => null],
    ]);
    $legacy->table('order')->insert([
        ['id' => 'order_1', 'status' => 'completed', 'fulfillment_status' => 'shipped', 'payment_status' => 'captured', 'display_id' => 123, 'cart_id' => 'cart_done', 'customer_id' => 'cus_1', 'email' => 'ana@example.com', 'billing_address_id' => 'addr_order', 'shipping_address_id' => 'addr_order', 'metadata' => null, 'created_at' => '2025-06-01 10:00:00', 'updated_at' => '2025-06-02 10:00:00', 'canceled_at' => null],
        ['id' => 'order_2', 'status' => 'pending', 'fulfillment_status' => 'not_fulfilled', 'payment_status' => 'awaiting', 'display_id' => 124, 'cart_id' => null, 'customer_id' => 'cus_2', 'email' => 'b2b@example.com', 'billing_address_id' => 'addr_b2b', 'shipping_address_id' => 'addr_b2b', 'metadata' => null, 'created_at' => '2025-07-01 10:00:00', 'updated_at' => null, 'canceled_at' => null],
    ]);
    $legacy->table('payment')->insert([
        ['id' => 'pay_1', 'order_id' => 'order_1', 'amount' => 3029, 'provider_id' => 'Datafast Ec', 'data' => json_encode(['datafastResponse' => ['id' => 'DF-1', 'result' => ['code' => '000.000.000'], 'paymentBrand' => 'VISA']]), 'captured_at' => '2025-06-01 10:01:00', 'created_at' => '2025-06-01 10:00:00'],
        ['id' => 'pay_2', 'order_id' => 'order_2', 'amount' => 5750, 'provider_id' => 'Credito Ec B2B', 'data' => json_encode(['id' => 'CR-1']), 'captured_at' => null, 'created_at' => '2025-07-01 10:00:00'],
    ]);
    $legacy->table('shipping_method')->insert(['id' => 'sm_1', 'order_id' => 'order_1', 'price' => 430]);
    $legacy->table('order_discounts')->insert(['order_id' => 'order_1', 'discount_id' => 'disc_1']);
    $legacy->table('client_b2b')->insert(['id' => 'cb_1', 'id_client' => '0999999999001', 'type_client' => 'DI', 'first_name' => 'Luis', 'last_name' => 'Gómez', 'email' => 'B2B@example.com', 'phone_number' => '098', 'active' => 'true', 'address' => json_encode(['city' => 'GUAYAQUIL'])]);
    $legacy->table('policies_b2b')->insert([
        ['id' => 'pol_1', 'es_activo' => true, 'factor_credito' => 1.05, 'num_cuotas' => 3, 'cod_cliente' => 'DM'],
        ['id' => 'pol_2', 'es_activo' => false, 'factor_credito' => 1.1, 'num_cuotas' => 6, 'cod_cliente' => 'DM'],
    ]);
    $legacy->table('transportistas_b2b')->insert(['id' => 'tr_1', 'razon_social' => 'Trans SA', 'ruc' => '0001']);
});

test('migrates every legacy table into the new schema with the documented mappings', function () {
    $this->artisan('migrate:legacy-data')->assertSuccessful();

    // Clientes: email normalizado, sin contraseña (scrypt incompatible), B2B desde metadata, borrados omitidos.
    $ana = Customer::query()->where('email', 'ana@example.com')->firstOrFail();
    expect($ana)->password->toBeNull()->dni->toBe('0999999999')->is_b2b->toBeFalse();
    expect($ana->metadata)->toHaveKey(LegacyMigrator::LEGACY_ID_KEY, 'cus_1')->toHaveKey('_legacy_has_account', true);
    expect($ana->addresses)->toHaveCount(1);
    expect(Customer::query()->where('email', 'b2b@example.com')->firstOrFail()->is_b2b)->toBeTrue();
    expect(Customer::query()->count())->toBe(2);

    // Productos: precio en centavos desde money_amount, allow_backorder forzado, metadata conservada.
    $piston = ProductVariant::query()->where('sku', 'MOT-1')->firstOrFail();
    expect($piston)->price->toBe(1130)->allow_backorder->toBeTrue()->weight_kg->toBe(2.0);
    expect($piston->product)->title->toBe('Pistón 150cc')->handle->toBe('piston-150cc');
    expect($piston->product->metadata)->toHaveKey('is_b2b', true)->toHaveKey(LegacyMigrator::LEGACY_ID_KEY, 'prod_1');
    expect(Product::query()->where('handle', 'descontinuado')->firstOrFail()->status->value)->toBe('draft');
    expect(Product::query()->count())->toBe(2);

    // Cupones
    $discount = Discount::query()->where('code', 'DIEZ')->firstOrFail();
    expect($discount->value)->toBe(10);
    expect($discount)->usage_count->toBe(2)->is_active->toBeTrue();

    // Carritos: solo abiertos y recientes; intervalo ms → minutos.
    $cart = Cart::query()->where('metadata->'.LegacyMigrator::LEGACY_ID_KEY, 'cart_open')->firstOrFail();
    expect($cart)->abandoned_last_interval->toBe(60)->abandoned_count->toBe(1)->subtotal->toBe(2260)->customer_id->toBe($ana->id);
    expect($cart->items)->toHaveCount(1);
    expect($cart->shippingAddress?->city)->toBe('QUITO');
    expect(Cart::query()->count())->toBe(1);

    // Órdenes: número visible conservado, totales recalculados, pago, guía, descuento, B2B.
    $order = Order::query()->where('order_number', 'SH-L000123')->firstOrFail();
    expect($order)
        ->status->toBe(OrderStatus::Fulfilled)
        ->email->toBe('ana@example.com')
        ->customer_id->toBe($ana->id)
        ->cart_id->toBeNull()
        ->subtotal->toBe(2260)
        ->discount_total->toBe(226)
        ->shipping_total->toBe(430)
        ->tax_total->toBe(370)
        ->total->toBe(2834)
        ->isB2b()->toBeFalse();
    expect($order->metadata)->toHaveKey('dni_type', 1)->toHaveKey('city_id', 42)->toHaveKey('gateway', 'datafast');
    expect($order->items->first())->sku->toBe('MOT-1')->quantity->toBe(2);
    expect($order->shippingAddress->metadata)->toHaveKey('dni_type', 1)->not->toHaveKey('dniType');
    expect($order->payments->first())->gateway->toBe(PaymentGateway::Datafast)->gateway_reference->toBe('DF-1')->response_code->toBe('000.000.000');
    expect($order->shipments->first())->guide_number->toBe('0123456789');
    expect($order->discounts->pluck('code')->all())->toBe(['DIEZ']);

    $b2bOrder = Order::query()->where('order_number', 'SH-L000124')->firstOrFail();
    expect($b2bOrder)->status->toBe(OrderStatus::Paid)->isB2b()->toBeTrue();
    expect($b2bOrder->metadata)->toHaveKey('installments', 3)->toHaveKey('transportista_id', 'T1')->toHaveKey('ruc', '0999999999001');
    expect($b2bOrder->items->first())->sku->toBe('var_gone')->product_variant_id->toBeNull();
    expect($b2bOrder->payments->first()->gateway)->toBe(PaymentGateway::CreditoB2b);
    expect($b2bOrder->shipments->first())->guide_number->toBeNull()->status->value->toBe('pending');

    // B2B: cliente enlazado por email, políticas por tipo, transportistas.
    $client = B2bClient::query()->where('id_client', '0999999999001')->firstOrFail();
    expect($client)->type_client->toBe('DI')->active->toBeTrue()->customer_id->toBe(Customer::query()->where('email', 'b2b@example.com')->value('id'));
    expect(B2bPolicy::query()->where('client_type', 'DM')->count())->toBe(2);
    expect($client->activePolicies())->toHaveCount(1);
    $this->assertDatabaseHas('b2b_transportistas', ['ruc' => '0001', 'business_name' => 'Trans SA']);
});

test('a second run is idempotent and only migrates what is missing', function () {
    $this->artisan('migrate:legacy-data')->assertSuccessful();
    DB::connection('legacy')->table('order')->insert(['id' => 'order_3', 'status' => 'pending', 'fulfillment_status' => 'not_fulfilled', 'payment_status' => 'captured', 'display_id' => 125, 'cart_id' => null, 'customer_id' => 'cus_1', 'email' => 'ana@example.com', 'billing_address_id' => null, 'shipping_address_id' => null, 'metadata' => null, 'created_at' => '2025-08-01 10:00:00', 'updated_at' => null, 'canceled_at' => null]);

    $this->artisan('migrate:legacy-data')
        ->expectsOutputToContain('orders')
        ->assertSuccessful();

    expect(Customer::query()->count())->toBe(2);
    expect(Product::query()->count())->toBe(2);
    expect(Order::query()->count())->toBe(3);
    expect(Cart::query()->count())->toBe(1);
    expect(B2bPolicy::query()->count())->toBe(2);
});

test('dry run writes nothing', function () {
    $this->artisan('migrate:legacy-data --dry-run')->assertSuccessful();

    expect(Customer::query()->count())->toBe(0);
    expect(Order::query()->count())->toBe(0);
});

test('the verify command reports counts and validates a sample of orders', function () {
    $this->artisan('migrate:legacy-data')->assertSuccessful();

    $this->artisan('migrate:legacy-verify --sample=5')
        ->expectsOutputToContain('Verificación OK')
        ->assertSuccessful();

    Order::query()->where('order_number', 'SH-L000123')->update(['subtotal' => 1]);

    $this->artisan('migrate:legacy-verify --sample=5')->assertFailed();
});
