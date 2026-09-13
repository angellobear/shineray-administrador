<?php

use App\Domain\Notifications\Jobs\SendOrderConfirmationJob;
use App\Mail\OrderPlacedMail;
use App\Models\Address;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use Illuminate\Support\Facades\Mail;

function confirmableOrder(): Order
{
    $order = Order::factory()->paid()->create(['order_number' => 'SH-260913-ABC123', 'email' => 'ana@example.com', 'subtotal' => 10000, 'discount_total' => 500, 'shipping_total' => 430, 'tax_total' => 1490, 'total' => 11420]);
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id, 'first_name' => 'Ana', 'city' => 'QUITO']);
    $order->update(['shipping_address_id' => $address->id]);
    OrderItem::factory()->for($order)->create(['title' => 'Bujía NGK', 'quantity' => 2, 'unit_price' => 5000]);

    return $order->fresh();
}

test('the confirmation email lists the order, the totals and the tracking link when a guide exists', function () {
    config()->set('shineray.notifications.order_placed_bcc', ['ventas@example.com', 'bodega@example.com']);
    $order = confirmableOrder();
    Shipment::factory()->for($order)->created()->create(['guide_number' => '0123456789']);

    $mail = new OrderPlacedMail($order->fresh());

    $mail->assertHasSubject('Tu orden #SH-260913-ABC123 - Confirmación de compra');
    $mail->assertHasBcc('ventas@example.com');
    $mail->assertHasBcc('bodega@example.com');
    $mail->assertSeeInHtml('Bujía NGK')
        ->assertSeeInHtml('$114.20')
        ->assertSeeInHtml('-$5.00')
        ->assertSeeInHtml('0123456789')
        ->assertSeeInHtml('https://www.servientrega.com.ec/Tracking/?guia=0123456789&tipo=GUIA')
        ->assertSeeInText('Hola Ana');
});

test('without a guide the email says it will be generated later', function () {
    config()->set('shineray.notifications.order_placed_bcc', []);

    (new OrderPlacedMail(confirmableOrder()))
        ->assertSeeInHtml('se generará en las próximas horas')
        ->assertDontSeeInHtml('Rastrear mi envío');
});

test('the confirmation job sends once and records it on the order', function () {
    Mail::fake();
    $order = confirmableOrder();

    SendOrderConfirmationJob::dispatchSync($order);
    SendOrderConfirmationJob::dispatchSync($order->fresh());

    Mail::assertSent(OrderPlacedMail::class, 1);
    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('ana@example.com'));
    expect($order->fresh()->metadata)->toHaveKey('confirmation_sent_at');
});

test('a mail transport failure is logged and rethrown for the queue to retry', function () {
    $order = confirmableOrder();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SES down'));

    expect(fn () => SendOrderConfirmationJob::dispatchSync($order))->toThrow(RuntimeException::class, 'SES down');

    expect(IntegrationLog::query()->where('event', 'order_email_fail')->exists())->toBeTrue();
    expect($order->fresh()->metadata)->not->toHaveKey('confirmation_sent_at');
});
