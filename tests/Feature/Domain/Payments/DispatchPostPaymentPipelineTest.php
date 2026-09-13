<?php

use App\Domain\Payments\Jobs\SaveInvoiceToErpJob;
use App\Domain\Shipping\Jobs\CreateShipmentGuideJob;
use App\Events\PaymentAuthorized;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Bus;

test('a payment authorization chains the post-payment jobs', function () {
    Bus::fake();
    $order = Order::factory()->paid()->create();
    $payment = Payment::factory()->for($order)->authorized()->create();

    PaymentAuthorized::dispatch($order, $payment);

    Bus::assertChained([CreateShipmentGuideJob::class, SaveInvoiceToErpJob::class]);
});
