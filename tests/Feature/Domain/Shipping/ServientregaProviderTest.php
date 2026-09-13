<?php

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Domain\Shipping\Exceptions\ShippingProviderException;
use App\Domain\Shipping\Providers\ServientregaProvider;
use App\Models\IntegrationLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.servientrega', [
        'quote_url' => 'https://cotizador.test/app/ws/cotizador_ser_recaudo.php', 'quote_user' => 'USER', 'quote_password' => 'PASS', 'quote_token' => 'TOKEN',
        'guide_url' => 'https://guias.test/api/guiawebs', 'guide_login' => 'LOGIN', 'guide_password' => 'SECRET', 'timeout' => 30,
    ]);
    config()->set('shineray.shipping.sender', ['id' => 'Shineray', 'business_name' => 'Shineray S.A.', 'first_name' => 'Shineray', 'last_name' => 'Repuestos', 'address' => 'Av. 14 S-E - Galo Pl. Lasso 13', 'city' => 'GUAYAQUIL', 'phone' => '09968767485']);
    Http::preventStrayRequests();
});

test('the container resolves the Servientrega provider', function () {
    expect(app(ShippingProviderContract::class))->toBeInstanceOf(ServientregaProvider::class);
});

test('quotes through the SOAP cotizador and returns the flete in cents', function () {
    Http::fake(['cotizador.test/*' => Http::response(file_get_contents(base_path('tests/Fixtures/servientrega/quote_response.xml')), 200, ['Content-Type' => 'text/xml'])]);

    $quote = app(ShippingProviderContract::class)->quote(new ShippingQuoteRequest(3.5, 'QUITO', 'PICHINCHA', 11500));

    expect($quote)->costCents->toBe(430)->isFallback->toBeFalse();
    Http::assertSent(function (Request $request) {
        $body = $request->body();

        return $request->hasHeader('SOAPAction')
            && str_contains($body, '<producto>MERCANCIA PREMIER</producto>')
            && str_contains($body, '<origen>GUAYAQUIL</origen>')
            && str_contains($body, '<destino>QUITO-PICHINCHA</destino>')
            && str_contains($body, '<valor_mercaderia>115.00</valor_mercaderia>')
            && str_contains($body, '<peso>3.5</peso>')
            && str_contains($body, '<usu>USER</usu>')
            && str_contains($body, '<tokn>TOKEN</tokn>');
    });
});

test('forces the minimum weight when the cart is lighter', function () {
    Http::fake(['cotizador.test/*' => Http::response(file_get_contents(base_path('tests/Fixtures/servientrega/quote_response.xml')), 200)]);

    app(ShippingProviderContract::class)->quote(new ShippingQuoteRequest(0.4, 'QUITO', 'PICHINCHA', 1000));

    Http::assertSent(fn (Request $request) => str_contains($request->body(), '<peso>2</peso>'));
});

test('falls back to the configured cost and logs when the cotizador fails', function (callable $response) {
    Http::fake(['cotizador.test/*' => $response()]);

    $quote = app(ShippingProviderContract::class)->quote(new ShippingQuoteRequest(2, 'QUITO', 'PICHINCHA', 1000));

    expect($quote)->costCents->toBe(500)->isFallback->toBeTrue();
    expect(IntegrationLog::query()->where('event', 'quote_fallback')->exists())->toBeTrue();
})->with([
    'SOAP fault' => [fn () => Http::response(file_get_contents(base_path('tests/Fixtures/servientrega/quote_error.xml')), 200)],
    'HTTP 500' => [fn () => Http::response('boom', 500)],
    'connection error' => [fn () => Http::failedConnection()],
    'garbage body' => [fn () => Http::response('not xml', 200)],
]);

test('creates a guide with the sender data from config and the recipient from the request', function () {
    Http::fake(['guias.test/api/guiawebs' => Http::response(file_get_contents(base_path('tests/Fixtures/servientrega/guide_created.json')), 200, ['Content-Type' => 'application/json'])]);

    $guide = app(ShippingProviderContract::class)->createGuide(new ShippingGuideRequest(
        orderNumber: 'SH-1', recipientName: 'Ana', recipientLastName: 'Pérez', recipientDni: '0999999999', recipientPhone: '0999',
        recipientAddress: 'Calle 1', destinationCity: 'QUITO', destinationProvince: 'PICHINCHA', totalWeightKg: 25,
        declaredValueCents: 11995, destinationCityId: '42',
    ));

    expect($guide->guideNumber)->toBe('0123456789');
    Http::assertSent(fn (Request $request) => $request['id_tipo_logistica'] === 2
        && $request['id_ciudad_origen'] === 1
        && $request['id_ciudad_destino'] === 42
        && $request['id_destinatario_ne_cl'] === '0999999999'
        && $request['nombre_destinatario_ne'] === 'Ana'
        && $request['apellido_destinatar_ne'] === 'Pérez'
        && $request['razon_social_remite'] === 'Shineray S.A.'
        && $request['direccion1_remite'] === 'Av. 14 S-E - Galo Pl. Lasso 13'
        && $request['telefono1_remite'] === '09968767485'
        && $request['contenido'] === 'Repuestos'
        && $request['valor_mercancia'] === '119.95'
        && $request['peso_fisico'] === 2.5
        && $request['login_creacion'] === 'LOGIN'
        && $request['password'] === 'SECRET');
});

test('fails loudly when the guide endpoint errors or returns no id', function () {
    Http::fake(['guias.test/*' => Http::sequence()->push('down', 500)->push(['mensaje' => 'error', 'id' => ''])]);
    $request = new ShippingGuideRequest('SH-1', 'Ana', 'Pérez', '0999', '0999', 'Calle', 'QUITO', 'PICHINCHA', 2, 1000, destinationCityId: '1');

    expect(fn () => app(ShippingProviderContract::class)->createGuide($request))->toThrow(ShippingProviderException::class);
    expect(fn () => app(ShippingProviderContract::class)->createGuide($request))->toThrow(ShippingProviderException::class, 'sin número de guía');
});
