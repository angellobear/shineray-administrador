<?php

use App\Domain\Cart\Jobs\DetectAbandonedCartsJob;
use App\Domain\ErpSync\Jobs\SyncProductsJob;
use App\Domain\Notifications\Jobs\SendOrderConfirmationJob;
use App\Enums\OrderStatus;
use App\Mail\AbandonedCartMail;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests are redirected to the login page on every admin route', function (string $route) {
    auth()->logout();

    $this->get(route($route))->assertRedirect(route('login'));
})->with(['admin.products.index', 'admin.orders.index', 'admin.customers.index', 'admin.b2b.clients', 'admin.abandoned-carts.index', 'admin.sync.index', 'admin.logs.index', 'admin.discounts.index']);

test('the dashboard shows sales stats, recent orders and sync runs', function () {
    Order::factory()->paid()->create(['total' => 11500, 'paid_at' => now()]);
    Order::factory()->create();
    ErpSyncRun::factory()->create();

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('stats.orders_today', 1)
        ->where('stats.revenue_today', 11500)
        ->where('stats.pending_orders', 1)
        ->has('recentOrders', 2)
        ->has('lastSyncRuns', 1));
});

test('lists and filters products, and updates the editable fields keeping the ERP metadata', function () {
    $product = Product::factory()->withVariant()->create(['title' => 'Bujía NGK', 'metadata' => ['COD_PRODUCTO' => 'A-1', 'NOMBRE_CATEGORIA' => 'MOTOR']]);
    Product::factory()->draft()->withVariant()->create(['title' => 'Oculto']);

    $this->get(route('admin.products.index', ['q' => 'Buj']))->assertInertia(fn (Assert $page) => $page
        ->component('admin/products/index')
        ->has('products.data', 1)
        ->where('products.data.0.title', 'Bujía NGK'));
    $this->get(route('admin.products.index', ['status' => 'draft']))->assertInertia(fn (Assert $page) => $page->has('products.data', 1));

    $this->put(route('admin.products.update', $product), ['title' => 'Bujía NGK Iridium', 'status' => 'draft', 'is_b2b' => 1, 'old_price' => 1500])
        ->assertRedirect(route('admin.products.edit', $product));

    $product->refresh();
    expect($product->title)->toBe('Bujía NGK Iridium');
    expect($product->status->value)->toBe('draft');
    expect($product->metadata)->toHaveKey('NOMBRE_CATEGORIA', 'MOTOR')->toHaveKey('is_b2b', true)->toHaveKey('OLD_PRICE', 1500)->toHaveKey('IS_PROMO', false);
});

test('lists orders with filters, shows the detail and applies state transitions', function () {
    $paid = Order::factory()->paid()->create(['order_number' => 'SH-1', 'email' => 'ana@example.com']);
    Order::factory()->create(['order_number' => 'SH-2']);

    $this->get(route('admin.orders.index', ['status' => 'paid']))->assertInertia(fn (Assert $page) => $page
        ->component('admin/orders/index')->has('orders.data', 1)->where('orders.data.0.order_number', 'SH-1'));
    $this->get(route('admin.orders.index', ['q' => 'ana@']))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));

    $this->get(route('admin.orders.show', $paid))->assertInertia(fn (Assert $page) => $page
        ->component('admin/orders/show')
        ->where('order.order_number', 'SH-1')
        ->where('order.allowed_transitions', ['fulfilled', 'refunded', 'canceled']));

    $this->post(route('admin.orders.transition', $paid), ['status' => 'fulfilled'])->assertRedirect(route('admin.orders.show', $paid));
    expect($paid->fresh())->status->toBe(OrderStatus::Fulfilled)->fulfilled_at->not->toBeNull();

    $this->post(route('admin.orders.transition', $paid), ['status' => 'canceled'])->assertSessionHasErrors('status');
    expect($paid->fresh()->status)->toBe(OrderStatus::Fulfilled);
});

test('resending the confirmation clears the sent marker and queues the email job', function () {
    Queue::fake();
    $order = Order::factory()->paid()->create(['metadata' => ['confirmation_sent_at' => '2026-01-01T00:00:00Z']]);

    $this->post(route('admin.orders.resend-confirmation', $order))->assertRedirect();

    expect($order->fresh()->metadata)->not->toHaveKey('confirmation_sent_at');
    Queue::assertPushed(SendOrderConfirmationJob::class);
});

test('exports the filtered orders as CSV', function () {
    Order::factory()->paid()->create(['order_number' => 'SH-EXPORT', 'total' => 11500]);
    Order::factory()->create(['order_number' => 'SH-PENDING']);

    $response = $this->get(route('admin.orders.export', ['status' => 'paid']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('order_number,created_at,status')->toContain('SH-EXPORT')->toContain('115.00')->not->toContain('SH-PENDING');
});

test('lists, shows and soft deletes customers', function () {
    $customer = Customer::factory()->b2b()->create(['email' => 'ana@example.com']);
    B2bClient::factory()->forCustomer($customer)->create(['type_client' => 'DI']);
    Customer::factory()->create();

    $this->get(route('admin.customers.index', ['b2b' => 1]))->assertInertia(fn (Assert $page) => $page
        ->component('admin/customers/index')->has('customers.data', 1)->where('customers.data.0.email', 'ana@example.com'));
    $this->get(route('admin.customers.show', $customer))->assertInertia(fn (Assert $page) => $page
        ->component('admin/customers/show')->where('customer.b2b.policy_type', 'DM'));

    $this->delete(route('admin.customers.destroy', $customer))->assertRedirect(route('admin.customers.index'));
    $this->assertSoftDeleted($customer);
});

test('renders the B2B pages', function () {
    B2bClient::factory()->count(2)->create();
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3]);
    B2bTransportista::factory()->create();

    $this->get(route('admin.b2b.clients'))->assertInertia(fn (Assert $page) => $page->component('admin/b2b/clients')->has('clients.data', 2));
    $this->get(route('admin.b2b.policies'))->assertInertia(fn (Assert $page) => $page->component('admin/b2b/policies')->has('policies', 1));
    $this->get(route('admin.b2b.transportistas'))->assertInertia(fn (Assert $page) => $page->component('admin/b2b/transportistas')->has('transportistas', 1));
});

test('lists abandoned carts and sends a manual reminder', function () {
    Mail::fake();
    $cart = Cart::factory()->create(['email' => 'ana@example.com']);
    CartItem::factory()->for($cart)->create();
    Cart::factory()->create(['email' => null]);

    $this->get(route('admin.abandoned-carts.index'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/abandoned-carts/index')->has('carts.data', 1)->where('carts.data.0.email', 'ana@example.com'));

    $this->post(route('admin.abandoned-carts.send', $cart))->assertRedirect(route('admin.abandoned-carts.index'));

    Mail::assertSent(AbandonedCartMail::class, fn (AbandonedCartMail $mail) => $mail->hasTo('ana@example.com'));
    expect($cart->fresh()->abandoned_count)->toBe(1);
});

test('shows sync runs and queues a sync on demand', function () {
    Queue::fake();
    ErpSyncRun::factory()->count(2)->create();

    $this->get(route('admin.sync.index'))->assertInertia(fn (Assert $page) => $page->component('admin/sync/index')->has('runs.data', 2));

    $this->post(route('admin.sync.run'), ['type' => 'products'])->assertRedirect(route('admin.sync.index'));
    $this->post(route('admin.sync.run'), ['type' => 'abandoned_carts'])->assertRedirect();
    $this->post(route('admin.sync.run'), ['type' => 'nope'])->assertSessionHasErrors('type');

    Queue::assertPushed(SyncProductsJob::class);
    Queue::assertPushed(DetectAbandonedCartsJob::class);
});

test('lists integration logs with filters', function () {
    IntegrationLog::factory()->create(['integration' => 'datafast', 'event' => 'authorize_fail', 'level' => 'error']);
    IntegrationLog::factory()->create(['integration' => 'shineray_erp', 'event' => 'invoice_ok', 'level' => 'info']);

    $this->get(route('admin.logs.index', ['level' => 'error']))->assertInertia(fn (Assert $page) => $page
        ->component('admin/logs/index')->has('logs.data', 1)->where('logs.data.0.event', 'authorize_fail'));
    $this->get(route('admin.logs.index', ['integration' => 'shineray_erp']))->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
});

test('manages discount codes end to end', function () {
    $this->get(route('admin.discounts.create'))->assertInertia(fn (Assert $page) => $page->component('admin/discounts/form')->where('discount', null));

    $this->post(route('admin.discounts.store'), ['code' => 'verano10', 'type' => 'percentage', 'value' => 10, 'is_active' => 1])
        ->assertRedirect(route('admin.discounts.index'));
    $discount = Discount::query()->where('code', 'VERANO10')->firstOrFail();
    expect($discount->isUsable())->toBeTrue();

    $this->post(route('admin.discounts.store'), ['code' => 'VERANO10', 'type' => 'percentage', 'value' => 150])
        ->assertSessionHasErrors(['code', 'value']);
    $this->post(route('admin.discounts.store'), ['code' => 'ENVIO', 'type' => 'free_shipping'])->assertRedirect();

    $this->put(route('admin.discounts.update', $discount), ['code' => 'VERANO10', 'type' => 'fixed', 'value' => 500, 'is_active' => 0])->assertRedirect();
    expect($discount->fresh())->type->value->toBe('fixed')->is_active->toBeFalse();

    $this->get(route('admin.discounts.index'))->assertInertia(fn (Assert $page) => $page->component('admin/discounts/index')->has('discounts', 2));
    $this->delete(route('admin.discounts.destroy', $discount))->assertRedirect();
    $this->assertModelMissing($discount);
});
