<?php

use App\Domain\ErpSync\Clients\ShinerayErpClient;
use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\Exceptions\ErpRequestException;
use App\Enums\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.shineray_erp.base_url', 'http://erp.test');
    config()->set('services.shineray_erp.username', 'user');
    config()->set('services.shineray_erp.password', 'secret');
    config()->set('services.shineray_erp.token_ttl_seconds', 0);
    Http::preventStrayRequests();
});

function fakeErp(array $overrides = []): void
{
    Http::fake(array_merge([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/all_parts' => Http::response(file_get_contents(base_path('tests/Fixtures/erp/all_parts.json')), 200, ['Content-Type' => 'application/json']),
        'erp.test/api/checkStock' => Http::response(file_get_contents(base_path('tests/Fixtures/erp/check_stock.json')), 200, ['Content-Type' => 'application/json']),
    ], $overrides));
}

test('the container resolves the real ERP client', function () {
    expect(app(ErpClientContract::class))->toBeInstanceOf(ShinerayErpClient::class);
});

test('maps the catalog feed into products: dedup by SKU, net price in cents and ERP metadata keys', function () {
    fakeErp();

    $products = app(ErpClientContract::class)->fetchProducts();

    expect($products)->toHaveCount(3);

    $piston = $products->firstWhere('sku', 'MOT-0001');
    expect($piston)
        ->title->toBe('Pistón 150cc')
        ->handle->toBe('piston-150cc-mot-0001')
        ->priceCents->toBe(1130)
        ->stockQuantity->toBe(12)
        ->weightKg->toBe(1.0)
        ->thumbnail->toBe('https://shineray-public.s3.amazonaws.com/repuestos/MOT-0001.jpg');
    expect($piston->metadata)
        ->toHaveKey('COD_PRODUCTO', 'MOT-0001')
        ->toHaveKey('NOMBRE_CATEGORIA', 'CONJUNTO DE MOTOR')
        ->toHaveKey('NIVEL_2', 'MOTOR')
        ->toHaveKey('NIVEL_3', null);

    expect($products->firstWhere('sku', 'TAN-0003'))
        ->priceCents->toBe(0)
        ->stockQuantity->toBe(3);
    expect($products->firstWhere('sku', 'ELE-0002'))
        ->weightKg->toBeNull()
        ->stockQuantity->toBe(0);
});

test('requests the token once and checks stock in a single batch for the whole feed', function () {
    fakeErp();

    app(ErpClientContract::class)->fetchProducts();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/checkStock')
        && count($request->data()) === 3
        && $request->hasHeader('Authorization', 'Bearer tok-1'));
});

test('skips the stock check when asked for the feed without stock', function () {
    fakeErp();

    $products = app(ErpClientContract::class)->fetchProducts(withStock: false);

    expect($products)->toHaveCount(3);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/checkStock'));
});

test('reuses a cached token across client instances when a TTL is configured', function () {
    config()->set('services.shineray_erp.token_ttl_seconds', 600);
    fakeErp(['erp.test/api/get_info_transportista_ecommerce' => Http::response(['transportistas' => []])]);

    app(ErpClientContract::class)->fetchTransportistas();
    app(ErpClientContract::class)->fetchTransportistas();

    expect(Cache::get(ShinerayErpClient::TOKEN_CACHE_KEY))->toBe('tok-1');
    Http::assertSentCount(3);
});

test('refreshes the token once when the ERP answers 401', function () {
    Http::fake([
        'erp.test/get-token' => Http::sequence()->push('"expired"')->push('"fresh"'),
        'erp.test/api/get_info_transportista_ecommerce' => Http::sequence()
            ->push('', 401)
            ->push(['transportistas' => [['RUC' => '0999', 'RAZON_SOCIAL' => 'Trans SA']]]),
    ]);

    $transportistas = app(ErpClientContract::class)->fetchTransportistas();

    expect($transportistas)->toHaveCount(1);
    expect($transportistas->first())->ruc->toBe('0999')->businessName->toBe('Trans SA');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fresh'));
});

test('wraps ERP failures in a domain exception', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/all_parts' => Http::response('boom', 500),
    ]);

    expect(fn () => app(ErpClientContract::class)->fetchProducts())
        ->toThrow(ErpRequestException::class);
});

test('flattens one credit policy per installments option', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/politicas_b2b_ecommerce' => Http::response([
            ['COD_CLIENTEH' => 'DM', 'cuotas_info' => [
                ['es_activo' => 1, 'factor_credito' => '1.0500', 'num_cuotas' => 3],
                ['es_activo' => 0, 'factor_credito' => '1.1000', 'num_cuotas' => 6],
            ]],
            ['COD_CLIENTEH' => 'DP', 'cuotas_info' => []],
        ]),
    ]);

    $policies = app(ErpClientContract::class)->fetchPoliciesB2b();

    expect($policies)->toHaveCount(2);
    expect($policies[0])->clientType->toBe('DM')->isActive->toBeTrue()->creditFactor->toBe(1.05)->installments->toBe(3);
    expect($policies[1])->isActive->toBeFalse()->installments->toBe(6);
});

test('maps B2B clients from the ERP', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/get_all_ruc_b2b_customer' => Http::response(['clientes' => [
            ['id' => 'RUC1', 'tipo_cliente' => 'DM', 'email' => 'a@b.c', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'celular' => '099', 'activo' => 'true', 'direcciones' => ['city' => 'Quito']],
        ]]),
    ]);

    $clients = app(ErpClientContract::class)->fetchClientsB2b();

    expect($clients->first())
        ->idClient->toBe('RUC1')
        ->typeClient->toBe('DM')
        ->email->toBe('a@b.c')
        ->active->toBeTrue()
        ->address->toBe(['city' => 'Quito']);
});

test('returns null for a billing client the ERP does not know', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/get_info_cliente_facturacion*' => Http::response(['cliente' => ['estado' => 'NO REGISTRADO']]),
    ]);

    expect(app(ErpClientContract::class)->getInfoClient(1, '0999999999'))->toBeNull();
});

test('returns the billing client when the ERP knows it', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/get_info_cliente_facturacion*' => Http::response(['cliente' => [
            'estado' => 'REGISTRADO', 'id' => '0999999999', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'direccion' => 'Calle 1',
        ]]),
    ]);

    $client = app(ErpClientContract::class)->getInfoClient(1, '0999999999');

    expect($client)->toBeInstanceOf(ClientInfo::class)
        ->id->toBe('0999999999')
        ->fullName()->toBe('Ana Pérez')
        ->address->toBe('Calle 1');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'type_id=1') && str_contains($request->url(), 'id=0999999999'));
});

test('saves a new billing client with the consumidor final type', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/save_new_data_client' => Http::response(['ok' => true]),
    ]);

    app(ErpClientContract::class)->saveInfoClient(new ClientInfo(1, '0999', 'Ana', 'Pérez', 'Quito, Calle 1', '099', 'a@b.c'));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'save_new_data_client')
        && $request['type_client'] === 'CF' && $request['id'] === '0999' && $request['type_id'] === 1);
});

test('posts the invoice to the endpoint of each gateway with amounts as two-decimal strings', function (PaymentGateway $gateway, string $endpoint) {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/save_invoice/*' => Http::response(['numero_factura' => 'F-1']),
    ]);

    $payload = new InvoicePayload(
        gateway: $gateway,
        transactionId: 'tx-1',
        client: new ClientInfo(1, '0999', 'Ana', 'Pérez', 'Quito'),
        items: [['sku' => 'MOT-0001', 'line_total_cents' => 2260, 'quantity' => 2]],
        totalCents: 3029,
        subtotalCents: 2260,
        discountCents: 0,
        shippingCostCents: 430,
        guideNumber: null,
        paymentBrand: 'VISA',
        paymentType: 'DB',
        card: ['cardType' => 'VISA', 'bin' => '411111', 'last4Digits' => '1111', 'holder' => 'ANA', 'expiryMonth' => '01', 'expiryYear' => '2030'],
        transportistaId: 'T1',
        transportistaName: 'Trans',
        installments: 3,
    );

    $result = app(ErpClientContract::class)->saveInvoice($payload);

    expect($result)->success->toBeTrue()->invoiceNumber->toBe('F-1');
    Http::assertSent(function (Request $request) use ($endpoint, $gateway) {
        $body = $request->data();

        return str_ends_with($request->url(), $endpoint)
            && $body['total'] === '30.29'
            && $body['costShipingCalculate'] === '4.30'
            && $body['idGuiaServientrega'] === '000000'
            && $body['cod_products'][0] === ['codProducto' => 'MOT-0001', 'price' => '22.60', 'quantity' => '2']
            && $body['client']['clientId'] === '0999'
            && ($gateway === PaymentGateway::CreditoB2b
                ? ($body['transactionId'] === 'tx-1' && $body['cuotas'] === 3 && ! isset($body['card']))
                : ($body['id'] === 'tx-1' && $body['batchNo'] === '01010000' && $body['card']['acquirerCode'] === 'DTF'));
    });
})->with([
    'datafast' => [PaymentGateway::Datafast, '/api/save_invoice/cf/parts'],
    'deuna' => [PaymentGateway::Deuna, '/api/save_invoice/cf1/parts'],
    'datafast b2b' => [PaymentGateway::DatafastB2b, '/api/save_invoice/datafast_b2b'],
    'deuna b2b' => [PaymentGateway::DeunaB2b, '/api/save_invoice/deuna_b2b'],
    'credito b2b' => [PaymentGateway::CreditoB2b, '/api/save_invoice/cf1/credito_directo'],
]);

test('filters recommended SKUs by the client RUC', function () {
    Http::fake([
        'erp.test/get-token' => Http::response('"tok-1"'),
        'erp.test/api/parts_ecommerce_recomended_b2b' => Http::response([
            ['COD_PERSONA' => 'RUC1', 'COD_PRODUCTO' => 'A'],
            ['COD_PERSONA' => 'RUC2', 'COD_PRODUCTO' => 'B'],
            ['COD_PERSONA' => 'RUC1', 'COD_PRODUCTO' => 'C'],
        ]),
    ]);

    expect(app(ErpClientContract::class)->fetchRecommendedSkusB2b('RUC1'))->toBe(['A', 'C']);
});
