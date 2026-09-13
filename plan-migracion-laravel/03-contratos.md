# Contratos (interfaces)

Regla general: **ninguna integración externa se invoca directo**. Todo pasa
por una interfaz PHP, con el binding a la implementación concreta declarado
en un Service Provider. Esto permite testear con fakes/mocks (ver
`06-testing-qa.md`) y cambiar de proveedor sin tocar el dominio.

## `PaymentGatewayContract`

Ubicación: `app/Domain/Payments/Contracts/PaymentGatewayContract.php`

```php
interface PaymentGatewayContract
{
    /** Inicia la sesión de pago (equivalente a initiatePayment de Medusa). */
    public function initiate(Cart $cart, array $context): PaymentSessionData;

    /** Confirma/autoriza el pago tras el flujo del gateway (3DS, redirect, etc.). */
    public function authorize(array $sessionData): PaymentResult;

    /** Puede ser no-op si el gateway no soporta captura diferida (documentarlo). */
    public function capture(Payment $payment): PaymentResult;

    public function refund(Payment $payment, int $amountCents): PaymentResult;

    public function cancel(Payment $payment): PaymentResult;

    /** Consulta de estado real contra el gateway, no un valor fijo. */
    public function status(Payment $payment): PaymentStatus;
}
```

Implementaciones: `DatafastGateway`, `DatafastB2bGateway`, `DeunaGateway`,
`DeunaB2bGateway`, `CreditoB2bGateway`. Cada una vive en
`app/Domain/Payments/Gateways/`. Ver `modulos/04-pagos.md` para el detalle de
qué preservar de cada una (incluyendo qué métodos son legítimamente "no
soportado por el gateway" vs. qué hay que implementar de verdad).

`PaymentSessionData`, `PaymentResult`, `PaymentStatus` son DTOs simples
(`readonly class` de PHP 8.2+ o `Spatie\LaravelData` si el equipo prefiere una
librería de DTOs), no Eloquent models.

## `ShippingProviderContract`

Ubicación: `app/Domain/Shipping/Contracts/ShippingProviderContract.php`

```php
interface ShippingProviderContract
{
    public function quote(ShippingQuoteRequest $request): ShippingQuote;

    public function createGuide(Order $order): ShippingGuide;

    /** Hoy no existe implementación real en Node — decidir si se implementa. */
    public function cancelGuide(string $guideNumber): void;
}
```

Implementación: `ServientregaProvider` en `app/Domain/Shipping/Providers/`.
`ShippingQuoteRequest` lleva los primitivos necesarios (peso total, ciudad,
provincia, valor de mercadería) — nunca un `Cart` completo, para mantener el
provider desacoplado del resto del dominio (a diferencia del
`AbstractFulfillmentService` de Medusa v1, que recibe el `Cart` entero).

## `ErpClientContract`

Ubicación: `app/Domain/ErpSync/Contracts/ErpClientContract.php`

```php
interface ErpClientContract
{
    public function fetchProducts(): Collection;          // reemplaza getTransformedDataShineray
    public function fetchClientsB2b(): Collection;
    public function fetchPoliciesB2b(): Collection;
    public function fetchTransportistas(): Collection;
    public function checkStock(string $sku): StockResult;
    public function getInfoClient(string $idClient): ?ClientInfo;
    public function saveInfoClient(ClientInfo $client): void;
    public function saveInvoice(InvoicePayload $payload): InvoiceResult;
}
```

Implementación: `ShinerayErpClient` en `app/Domain/ErpSync/Clients/`. Un solo
cliente HTTP para todo el ERP (hoy está fragmentado entre `shineray-api.js`,
`shineray-b2b-api.js` y `shineray-billing.ts` con lógica de token duplicada en
más de un lugar) — se unifica el manejo de token/autenticación en un solo
sitio con cache (el token del ERP se puede cachear con Redis en vez de
pedirlo en cada llamada, si el ERP lo permite — confirmar TTL del token con
el proveedor).

## `SearchIndexerContract`

Ubicación: `app/Domain/Search/Contracts/SearchIndexerContract.php`

```php
interface SearchIndexerContract
{
    public function index(Product $product): void;
    public function remove(Product $product): void;
    public function reindexAll(): void;
}
```

En la práctica, Laravel Scout ya provee esto a través del trait
`Searchable` en el modelo `Product` (`toSearchableArray()`,
`shouldBeSearchable()`). El contrato existe igual como capa fina para que el
resto del dominio (ej. el listener que reindexed tras un cambio de stock) no
dependa directamente de Scout, sino de esta interfaz — si el día de mañana se
cambia de motor de búsqueda, el dominio no se entera.

## Notificaciones

No se define un contrato propio: se usa el sistema nativo de Laravel
(`Illuminate\Notifications\Notification` + `Mail`), que ya es una
abstracción sobre el canal de envío. Ver `modulos/08-notificaciones.md`.

## Reglas para los DTOs de request/response

- Todos son inmutables (`readonly`), sin lógica de negocio dentro — solo
  transporte de datos entre la capa de integración y el dominio.
- Ningún DTO expone directamente la forma cruda de la respuesta del proveedor
  externo (ej. no se pasa el XML/SOAP parseado de Servientrega tal cual al
  dominio) — cada implementación de contrato es responsable de mapear la
  respuesta cruda a su DTO de dominio antes de devolverla.
