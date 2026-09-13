<?php

namespace App\Domain\ErpSync\Clients;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\ErpClientB2b;
use App\Domain\ErpSync\DTOs\ErpPolicyB2b;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Domain\ErpSync\DTOs\ErpTransportistaB2b;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\DTOs\InvoiceResult;
use App\Domain\ErpSync\DTOs\StockResult;
use App\Domain\ErpSync\Exceptions\ErpRequestException;
use App\Enums\PaymentGateway;
use App\Support\TaxCalculator;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cliente HTTP del ERP Shineray/Massline. Reemplaza `shineray-api.js`,
 * `shineray-b2b-api.js`, `shineray-billing.ts` y `api/utils/shineray-api.js`.
 *
 * Diferencias deliberadas con el código Node:
 * - Un solo token por corrida (cacheado si `token_ttl_seconds > 0`) en vez de
 *   pedir un token nuevo en cada llamada.
 * - El stock se consulta en lotes (`/api/checkStock` acepta un array).
 */
final class ShinerayErpClient implements ErpClientContract
{
    public const TOKEN_CACHE_KEY = 'shineray-erp:token';

    private const STOCK_CHUNK_SIZE = 200;

    /**
     * Claves del feed que se preservan en `products.metadata` (mismos nombres
     * que hoy; el transformer de búsqueda y el frontend dependen de ellas).
     */
    private const METADATA_KEYS = [
        'ICE', 'IVA', 'ANIO_DESDE', 'ANIO_HASTA', 'COD_AGENCIA', 'MOTO_MODELO', 'CODIGO_MARCA', 'COD_PRODUCTO',
        'NOMBRE_MARCA', 'NOMBRE_BODEGA', 'CONTROL_BUFFER', 'CODIGO_CATEGORIA', 'NOMBRE_CATEGORIA', 'CODIGO_SUBSISTEMA',
        'CODIGO_MODELO_MOTO', 'NOMBRE_SUBSISTEMA', 'NIVEL_1', 'COD_NIVEL_1', 'NIVEL_2', 'COD_NIVEL_2', 'NIVEL_3',
        'COD_NIVEL_3', 'NIVEL_4', 'COD_NIVEL_4', 'COD_UNIDAD',
    ];

    private ?string $requestToken = null;

    public function __construct(
        private readonly TaxCalculator $tax,
        private readonly Cache $cache,
    ) {}

    public function fetchProducts(bool $withStock = true): Collection
    {
        $rows = $this->get('/api/all_parts')->json();

        if (! is_array($rows)) {
            throw new ErpRequestException('El ERP devolvió una respuesta inesperada para /api/all_parts.');
        }

        // Primera fila gana cuando el mismo COD_PRODUCTO viene en varias bodegas/modelos.
        $unique = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['COD_PRODUCTO'] ?? null))
            ->unique(fn (array $row): string => (string) $row['COD_PRODUCTO'])
            ->values();

        $stock = $withStock
            ? $this->checkStockMany(array_values($unique->map(fn (array $row): string => (string) $row['COD_PRODUCTO'])->all()))
            : [];

        return $unique->map(function (array $row) use ($stock): ErpProduct {
            $sku = (string) $row['COD_PRODUCTO'];
            $title = trim((string) ($row['NOMBRE_PRODUCTO'] ?? $sku));

            return new ErpProduct(
                sku: $sku,
                title: $title,
                handle: Str::slug($title.'-'.$sku),
                priceCents: isset($row['PRECIO']) && is_numeric($row['PRECIO'])
                    ? $this->tax->erpGrossPriceToNetCents((float) $row['PRECIO'])
                    : 0,
                stockQuantity: isset($stock[$sku]) ? $stock[$sku]->quantity : 0,
                thumbnail: config('shineray.catalog.thumbnail_base_url').$sku.'.jpg',
                weightKg: isset($row['PESO']) && is_numeric($row['PESO']) ? (float) round((float) $row['PESO']) : null,
                metadata: collect(self::METADATA_KEYS)
                    ->mapWithKeys(fn (string $key): array => [$key => $row[$key] ?? null])
                    ->all(),
            );
        });
    }

    public function fetchClientsB2b(): Collection
    {
        $clients = $this->get('/api/get_all_ruc_b2b_customer')->json('clientes');

        return collect(is_array($clients) ? $clients : [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['id'] ?? null))
            ->map(fn (array $row): ErpClientB2b => new ErpClientB2b(
                idClient: (string) $row['id'],
                typeClient: isset($row['tipo_cliente']) ? (string) $row['tipo_cliente'] : null,
                firstName: (string) ($row['nombres'] ?? ''),
                lastName: (string) ($row['apellidos'] ?? ''),
                email: isset($row['email']) ? (string) $row['email'] : null,
                phoneNumber: isset($row['celular']) ? (string) $row['celular'] : null,
                address: is_array($row['direcciones'] ?? null) ? $row['direcciones'] : [],
                active: filter_var($row['activo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ))
            ->values();
    }

    public function fetchPoliciesB2b(): Collection
    {
        $rows = $this->get('/api/politicas_b2b_ecommerce')->json();

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['COD_CLIENTEH'] ?? null))
            ->flatMap(function (array $row): array {
                $policies = [];

                foreach (is_array($row['cuotas_info'] ?? null) ? $row['cuotas_info'] : [] as $cuota) {
                    $policies[] = new ErpPolicyB2b(
                        idClient: (string) $row['COD_CLIENTEH'],
                        isActive: filter_var($cuota['es_activo'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        creditFactor: (float) ($cuota['factor_credito'] ?? 0),
                        installments: (int) ($cuota['num_cuotas'] ?? 1),
                    );
                }

                return $policies;
            })
            ->values();
    }

    public function fetchTransportistas(): Collection
    {
        $rows = $this->get('/api/get_info_transportista_ecommerce')->json('transportistas');

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['RUC'] ?? null))
            ->map(fn (array $row): ErpTransportistaB2b => new ErpTransportistaB2b(
                ruc: (string) $row['RUC'],
                businessName: (string) ($row['RAZON_SOCIAL'] ?? ''),
            ))
            ->values();
    }

    public function checkStock(string $sku): StockResult
    {
        return $this->checkStockMany([$sku])[$sku] ?? StockResult::unavailable($sku);
    }

    public function checkStockMany(array $skus): array
    {
        $results = [];

        foreach (array_chunk(array_values(array_unique($skus)), self::STOCK_CHUNK_SIZE) as $chunk) {
            $rows = $this->post('/api/checkStock', array_map(
                fn (string $sku): array => ['cod_producto' => $sku, 'quantity' => 1],
                $chunk,
            ))->json();

            foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $sku = (string) ($row['cod_producto'] ?? $chunk[$index] ?? '');

                if ($sku === '') {
                    continue;
                }

                $results[$sku] = new StockResult(
                    sku: $sku,
                    completePurchase: filter_var($row['complete_purchase'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    quantity: (int) ($row['available'] ?? 0),
                );
            }
        }

        return $results;
    }

    public function getInfoClient(int $idType, string $id): ?ClientInfo
    {
        $client = $this->get('/api/get_info_cliente_facturacion', ['type_id' => $idType, 'id' => $id])->json('cliente');

        if (! is_array($client) || ($client['estado'] ?? null) === 'NO REGISTRADO') {
            return null;
        }

        if (blank($client['nombres'] ?? null) || blank($client['apellidos'] ?? null) || blank($client['id'] ?? null)) {
            return null;
        }

        return new ClientInfo(
            idType: $idType,
            id: (string) $client['id'],
            firstName: (string) $client['nombres'],
            lastName: (string) $client['apellidos'],
            address: (string) ($client['direccion'] ?? ''),
            phone: isset($client['celular']) ? (string) $client['celular'] : null,
            email: isset($client['email']) ? (string) $client['email'] : null,
            typeClient: (string) ($client['type_client'] ?? ClientInfo::TYPE_CLIENT_CONSUMIDOR_FINAL),
        );
    }

    public function saveInfoClient(ClientInfo $client): void
    {
        $this->post('/api/save_new_data_client', [
            'id' => $client->id,
            'type_id' => $client->idType,
            'type_client' => $client->typeClient,
            'nombre' => $client->firstName,
            'apellidos' => $client->lastName,
            'direccion' => $client->address,
            'celular' => $client->phone,
            'email' => $client->email,
        ]);
    }

    public function saveInvoice(InvoicePayload $payload): InvoiceResult
    {
        $response = $this->post($this->invoiceEndpoint($payload->gateway), $this->invoiceBody($payload));
        $body = $response->json();

        return new InvoiceResult(
            success: true,
            invoiceNumber: is_array($body) ? (isset($body['numero_factura']) ? (string) $body['numero_factura'] : null) : null,
            raw: is_array($body) ? $body : ['body' => $response->body()],
        );
    }

    public function fetchRecommendedSkusB2b(string $ruc): array
    {
        $rows = $this->get('/api/parts_ecommerce_recomended_b2b')->json();

        return array_values(collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row) && (string) ($row['COD_PERSONA'] ?? '') === $ruc)
            ->map(fn (array $row): string => (string) $row['COD_PRODUCTO'])
            ->all());
    }

    public function fetchCreditDebt(string $ruc): array
    {
        $body = $this->get('/api/get_client_orders_ecommerce/'.rawurlencode($ruc))->json();

        return is_array($body) ? $body : [];
    }

    /**
     * Endpoint de facturación por gateway (`shineray-billing.ts`).
     */
    private function invoiceEndpoint(PaymentGateway $gateway): string
    {
        return match ($gateway) {
            PaymentGateway::Datafast => '/api/save_invoice/cf/parts',
            PaymentGateway::Deuna => '/api/save_invoice/cf1/parts',
            PaymentGateway::DatafastB2b => '/api/save_invoice/datafast_b2b',
            PaymentGateway::DeunaB2b => '/api/save_invoice/deuna_b2b',
            PaymentGateway::CreditoB2b => '/api/save_invoice/cf1/credito_directo',
        };
    }

    /**
     * Mismo `paymentData` que arman hoy los payment processors.
     *
     * @return array<string, mixed>
     */
    private function invoiceBody(InvoicePayload $payload): array
    {
        $body = [
            'total' => $this->dollars($payload->totalCents),
            'subTotal' => $this->dollars($payload->subtotalCents),
            'discountPercentage' => number_format($payload->discountPercentage, 2, '.', ''),
            'discountAmount' => $this->dollars($payload->discountCents),
            'currency' => $payload->currency,
            'idGuiaServientrega' => $payload->guideNumber ?? '000000',
            'costShipingCalculate' => $this->dollars($payload->shippingCostCents),
            'shipingDiscount' => $this->dollars($payload->shippingDiscountCents),
            'client' => [
                'typeId' => $payload->client->idType,
                'name' => $payload->client->firstName,
                'lastName' => $payload->client->lastName,
                'clientId' => $payload->client->id,
                'address' => $payload->client->address,
            ],
            'cod_products' => array_map(fn (array $item): array => [
                'codProducto' => $item['sku'],
                'price' => $this->dollars($item['line_total_cents']),
                'quantity' => (string) $item['quantity'],
            ], $payload->items),
        ];

        if ($payload->gateway === PaymentGateway::CreditoB2b) {
            return $body + [
                'transactionId' => $payload->transactionId,
                'idAgenciaTransporte' => $payload->transportistaId,
                'nombreAgenciaTransporte' => $payload->transportistaName,
                'cuotas' => $payload->installments,
            ];
        }

        $body += [
            'id' => $payload->transactionId,
            'paymentType' => $payload->paymentType,
            'paymentBrand' => $payload->paymentBrand,
            'batchNo' => '01010000',
        ];

        if ($payload->card !== null) {
            $body['card'] = $payload->card + ['acquirerCode' => 'DTF'];
        }

        if ($payload->gateway->isB2b()) {
            $body += [
                'idAgenciaTransporte' => $payload->transportistaId,
                'nombreAgenciaTransporte' => $payload->transportistaName,
                'cuotas' => $payload->installments,
            ];
        }

        return $body;
    }

    private function dollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->send(fn (PendingRequest $http): Response => $http->get($path, $query), $path);
    }

    /**
     * @param  array<mixed>  $body
     */
    private function post(string $path, array $body): Response
    {
        return $this->send(fn (PendingRequest $http): Response => $http->post($path, $body), $path);
    }

    /**
     * @param  \Closure(PendingRequest): Response  $request
     */
    private function send(\Closure $request, string $path): Response
    {
        try {
            $response = $request($this->http()->withToken($this->token()));

            // Token vencido: se pide uno nuevo una sola vez.
            if ($response->status() === 401) {
                $this->forgetToken();
                $response = $request($this->http()->withToken($this->token()));
            }

            return $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ErpRequestException(sprintf('Fallo del ERP en %s: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.shineray_erp.base_url'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.shineray_erp.timeout', 60));
    }

    private function token(): string
    {
        if ($this->requestToken !== null) {
            return $this->requestToken;
        }

        $ttl = (int) config('services.shineray_erp.token_ttl_seconds', 0);

        $token = $ttl > 0
            ? $this->cache->remember(self::TOKEN_CACHE_KEY, $ttl, fn (): string => $this->requestToken())
            : $this->requestToken();

        return $this->requestToken = (string) $token;
    }

    private function forgetToken(): void
    {
        $this->requestToken = null;
        $this->cache->forget(self::TOKEN_CACHE_KEY);
    }

    private function requestToken(): string
    {
        try {
            $response = $this->http()->post('/get-token', [
                'username' => config('services.shineray_erp.username'),
                'password' => config('services.shineray_erp.password'),
            ])->throw();
        } catch (Throwable $exception) {
            throw new ErpRequestException('No se pudo obtener el token del ERP: '.$exception->getMessage(), 0, $exception);
        }

        $token = $response->json();

        if (! is_string($token)) {
            $token = trim($response->body(), "\" \n\r\t");
        }

        if ($token === '') {
            throw new ErpRequestException('El ERP devolvió un token vacío.');
        }

        return $token;
    }
}
