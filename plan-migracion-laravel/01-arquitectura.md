# Arquitectura Laravel

## Stack

- **Laravel** — última versión estable LTS/actual disponible al arrancar el
  proyecto (PHP 8.2+). Backend headless (API-first); no se usa Blade para
  storefront (el frontend Next.js existente en
  `/Users/jimmy/GitHub/shineray-ecommerce-front` sigue siendo el consumidor de
  la API — ver nota de coordinación al final de este archivo).
- **Base de datos**: PostgreSQL (se mantiene el motor actual, cambia el ORM de
  TypeORM a Eloquent). Ya existe experiencia con Postgres en el equipo.
- **Cache/colas**: Redis (igual que hoy).
- **Colas + monitoreo**: Laravel Queues sobre Redis + **Laravel Horizon**
  (reemplaza el `MEDUSA_JOBS=true/false` por control real de workers).
- **Scheduler**: Laravel Task Scheduling (`routes/console.php` /
  `bootstrap/app.php` según versión) para los cron de sync ERP — reemplaza los
  `ScheduledJobConfig` de Medusa.
- **Auth**: Laravel Sanctum. Dos "guards": `admin` (staff, panel de
  administración) y `customer` (clientes B2C/B2B en el storefront). Reemplaza
  el JWT/cookie propio de Medusa.
- **Búsqueda**: Laravel Scout + driver oficial de Meilisearch
  (`meilisearch/meilisearch-php` + `laravel/scout`).
- **Admin panel**: **Filament** (CRUD administrativo sobre Eloquent, con
  páginas custom para los flujos que no son CRUD simple: verificación B2B,
  dashboard de carritos abandonados, onboarding). Reemplaza el dashboard
  custom de `@medusajs/admin` — con un solo desarrollador, evita reconstruir
  UI de administración desde cero.
- **HTTP saliente**: el facade `Http` de Laravel (Guzzle por debajo) para
  todas las integraciones externas (Datafast, Servientrega, ERP Shineray).
  Se usa `Http::fake()` en tests — ver `06-testing-qa.md`.
- **Storage**: filesystem de Laravel con driver S3 (`league/flysystem-aws-s3-v3`),
  mismo bucket/credenciales que hoy.
- **Mail**: Laravel Mail con transporte SES nativo (`ses` mailer), plantillas
  como Blade/Markdown mailables (reemplazan los templates Handlebars).
- **Testing**: Pest (recomendado por legibilidad) o PHPUnit — a elección del
  equipo, pero obligatorio desde el primer módulo.

## Principio rector de la estructura de carpetas

Organización **por dominio**, no por tipo técnico — evita el problema actual
de Medusa donde toda la lógica de negocio de pago+envío+facturación vive
enredada dentro de un único archivo de "payment processor". Cada dominio es
autocontenido: sus modelos, sus contratos, sus implementaciones, sus jobs.

```
app/
  Domain/
    Catalog/              # productos, variantes, opciones
      Models/
      Actions/
    Cart/                 # carrito, carrito abandonado
      Models/
      Services/
    Orders/                # órdenes, checkout
      Models/
      Services/
    Payments/
      Contracts/
        PaymentGatewayContract.php
      Gateways/
        DatafastGateway.php
        DeunaGateway.php
        CreditoB2bGateway.php
      Models/              # Payment, PaymentLog
    Shipping/
      Contracts/
        ShippingProviderContract.php
      Providers/
        ServientregaProvider.php
      Models/              # Shipment
    B2b/
      Models/              # ClientB2b, PoliciesB2b, TransportistaB2b
      Services/
    Search/
      Contracts/
        SearchIndexerContract.php
      (Scout se integra vía trait Searchable directo en Product,
       el contrato existe para poder cambiar de motor sin tocar el dominio)
    ErpSync/
      Contracts/
        ErpClientContract.php
      Clients/
        ShinerayErpClient.php
      Jobs/                # jobs de sync programados
    Notifications/
      Mail/
      Notifications/
  Http/
    Controllers/
      Admin/
      Store/
  Filament/                # recursos/páginas del admin panel
Events/                    # PaymentAuthorized, OrderPlaced, CartAbandoned, ...
Listeners/                 # CreateServientregaGuide, SaveInvoiceToShineray, ...
config/
  shineray.php             # iva_rate, moneda, credenciales de negocio no-secretas
```

Regla: un Controller nunca llama directo a un Gateway/Provider/Client — llama
a un método de un Service del dominio, que a su vez depende del *contrato*
(interfaz), nunca de la clase concreta. El binding contrato→implementación
concreta se declara en un `ServiceProvider` (`AppServiceProvider` o uno
dedicado, `IntegrationsServiceProvider`).

## Reemplazo del patrón "workflow" de Medusa: eventos + listeners en cola

El hallazgo más importante de la auditoría del código Node fue que el flujo
"pago confirmado → crear guía Servientrega → actualizar carrito → facturar en
Shineray" vive **acoplado dentro del payment processor**, con un antipatrón de
auto-llamada HTTP de Medusa a sí mismo. En Laravel esto se modela así:

```php
// Tras autorizar el pago con éxito, dentro del Service de Orders:
event(new PaymentAuthorized($order, $payment));
```

```php
// EventServiceProvider
PaymentAuthorized::class => [
    CreateServientregaGuideListener::class,
    SaveInvoiceToShinerayListener::class,
    SendOrderConfirmationEmailListener::class,
],
```

Cada listener implementa `ShouldQueue`, tiene su propio manejo de fallo
(`failed()`), y usa reintentos de la cola en vez de un `try/catch` silencioso
que solo escribe a un archivo local. Esto preserva la decisión de negocio
actual ("un fallo de envío/factura no debe bloquear el pago") mejorándola:
ahora el fallo queda en una cola con reintentos y visibilidad en Horizon, no
perdido en un log de archivo por instancia.

## Convención de nombres de API

Para minimizar el trabajo de adaptación del frontend Next.js existente
(que hoy consume las rutas `/store/...` y `/admin/...` de Medusa), se
recomienda mantener el mismo prefijo/forma donde sea razonable:
`/api/store/...`, `/api/admin/...`. El mapeo exacto ruta-por-ruta se
detalla en cada módulo. **Esto es una recomendación, no una obligación
estricta** — si el frontend también se va a tocar en paralelo, se puede
optimizar la forma de la API sin atarse a la de Medusa.

## Nota de coordinación (fuera del alcance de este backend)

El frontend Next.js seguirá funcionando solo si la API nueva expone formas de
datos compatibles (o el frontend se adapta en paralelo). Este plan no cubre
cambios al frontend — se menciona aquí únicamente como dependencia a
coordinar antes del corte de producción (ver `05-migracion-datos-corte.md`).
