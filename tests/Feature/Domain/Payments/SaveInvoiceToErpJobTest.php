<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\Services\InvoicePayloadFactory;
use App\Domain\Payments\Jobs\SaveInvoiceToErpJob;
use App\Enums\PaymentGateway;
use App\Models\Address;
use App\Models\Discount;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Tests\Support\FakeErpClient;

beforeEach(function () {
    $this->erp = new FakeErpClient;
    $this->app->instance(ErpClientContract::class, $this->erp);
});

function paidOrder(array $metadata = [], ?PaymentGateway $gateway = null): array
{
    $order = Order::factory()->paid()->create([
        'subtotal' => 10000, 'discount_total' => 0, 'shipping_total' => 430, 'tax_total' => 1565, 'total' => 11995,
        'metadata' => array_merge(['dni' => '0999999999', 'dni_type' => 1, 'city_id' => 42, 'gateway' => ($gateway ?? PaymentGateway::Datafast)->value], $metadata),
    ]);
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'city' => 'QUITO', 'address_1' => 'Calle 1', 'phone' => '0999']);
    $order->update(['shipping_address_id' => $address->id]);
    OrderItem::factory()->for($order)->create(['sku' => 'MOT-1', 'quantity' => 2, 'unit_price' => 5000, 'metadata' => ['cod_producto' => 'MOT-1']]);
    $payment = Payment::factory()->for($order)->authorized()->gateway($gateway ?? PaymentGateway::Datafast)->create([
        'raw_response' => ['id' => 'TX-1', 'paymentType' => 'DB', 'paymentBrand' => 'VISA', 'currency' => 'USD', 'card' => ['bin' => '411111', 'last4Digits' => '1111', 'holder' => 'ANA', 'expiryMonth' => '12', 'expiryYear' => '2030']],
    ]);

    return [$order->fresh(), $payment];
}

test('registers an unknown B2C client as consumidor final and invoices the order', function () {
    [$order, $payment] = paidOrder();

    (new SaveInvoiceToErpJob($order, $payment))->handle($this->erp, app(InvoicePayloadFactory::class));

    expect($this->erp->savedClients)->toHaveCount(1);
    expect($this->erp->savedClients[0])->id->toBe('0999999999')->typeClient->toBe('CF')->idType->toBe(1);
    expect($this->erp->savedInvoices)->toHaveCount(1);
    $invoice = $this->erp->savedInvoices[0];
    expect($invoice)
        ->gateway->toBe(PaymentGateway::Datafast)
        ->transactionId->toBe('TX-1')
        ->totalCents->toBe(11995)
        ->subtotalCents->toBe(10000)
        ->shippingCostCents->toBe(430)
        ->shippingDiscountCents->toBe(0)
        ->paymentBrand->toBe('VISA')
        ->guideNumber->toBeNull();
    expect($invoice->client->address)->toBe('QUITO, Calle 1');
    expect($invoice->card['last4Digits'])->toBe('1111');
    expect($invoice->items[0])->toBe(['sku' => 'MOT-1', 'line_total_cents' => 10000, 'quantity' => 2]);
    expect($order->fresh()->metadata)->toHaveKey('erp_invoice_number', 'FAC-1');
    expect(IntegrationLog::query()->where('event', 'invoice_ok')->exists())->toBeTrue();
});

test('uses the ERP data when the client is already registered', function () {
    [$order, $payment] = paidOrder();
    $this->erp->clients['0999999999'] = new ClientInfo(1, '0999999999', 'Ana María', 'Pérez López', 'Av. Principal');

    (new SaveInvoiceToErpJob($order, $payment))->handle($this->erp, app(InvoicePayloadFactory::class));

    expect($this->erp->savedClients)->toHaveCount(0);
    expect($this->erp->savedInvoices[0]->client)->firstName->toBe('Ana María')->address->toBe('QUITO, Av. Principal');
});

test('reports free shipping as the fixed cost and discount the ERP expects', function () {
    [$order, $payment] = paidOrder();
    $order->discounts()->attach(Discount::factory()->freeShipping()->create());
    $order->discounts()->attach(Discount::factory()->create(['value' => 10]));
    $order->update(['discount_total' => 1000]);

    (new SaveInvoiceToErpJob($order->fresh(), $payment))->handle($this->erp, app(InvoicePayloadFactory::class));

    expect($this->erp->savedInvoices[0])
        ->shippingCostCents->toBe(430)
        ->shippingDiscountCents->toBe(430)
        ->discountPercentage->toBe(10.0)
        ->discountCents->toBe(1000);
});

test('invoices B2B credit orders with the RUC and transport data without looking the client up', function () {
    [$order, $payment] = paidOrder(['is_b2b' => true, 'ruc' => '0999999999001', 'transportista_id' => 'T1', 'transportista_name' => 'Trans SA', 'installments' => 3, 'address' => 'Bodega norte'], PaymentGateway::CreditoB2b);

    (new SaveInvoiceToErpJob($order, $payment))->handle($this->erp, app(InvoicePayloadFactory::class));

    expect($this->erp->savedClients)->toHaveCount(0);
    expect($this->erp->savedInvoices[0])
        ->gateway->toBe(PaymentGateway::CreditoB2b)
        ->transportistaId->toBe('T1')
        ->installments->toBe(3)
        ->shippingCostCents->toBe(430);
    expect($this->erp->savedInvoices[0]->client)->id->toBe('0999999999001')->address->toBe('Bodega norte');
});

test('logs and rethrows when the ERP rejects the invoice so the queue retries', function () {
    [$order, $payment] = paidOrder();
    $this->erp->invoiceFails = true;

    expect(fn () => (new SaveInvoiceToErpJob($order, $payment))->handle($this->erp, app(InvoicePayloadFactory::class)))
        ->toThrow(RuntimeException::class);

    expect(IntegrationLog::query()->where('event', 'invoice_fail')->first()->loggable_id)->toBe($order->id);
    expect($order->fresh()->metadata)->not->toHaveKey('erp_invoice_number');
});

test('does not invoice twice nor invoice manual orders', function () {
    [$order, $payment] = paidOrder(['erp_invoice_number' => 'FAC-9']);
    (new SaveInvoiceToErpJob($order, $payment))->handle($this->erp, app(InvoicePayloadFactory::class));

    [$manualOrder, $manualPayment] = paidOrder([], PaymentGateway::Manual);
    (new SaveInvoiceToErpJob($manualOrder, $manualPayment))->handle($this->erp, app(InvoicePayloadFactory::class));

    expect($this->erp->savedInvoices)->toHaveCount(0);
});
