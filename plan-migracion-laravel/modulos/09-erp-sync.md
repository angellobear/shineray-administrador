# Módulo: Sincronización con el ERP Shineray (Massline)

## Referencia en `medusa-shineray`

- `src/utils/jobs/shineray-api.js` (352 líneas) — catálogo/stock: token,
  `getTransformedDataShineray()`, `checkStockAvailable()`. Bug conocido:
  `getTiposSubsistemas()` pasa `cod_categoria` en vez de `cod_modelo` —
  **corregir, no replicar**.
- `src/utils/jobs/shineray-b2b-api.js` (30 líneas) — clientes/políticas/
  transportistas B2B.
- `src/utils/shineray-billing.ts` — facturación (`getInfoClients`,
  `saveInfoClients`, `saveInvoiceDatafast[B2B]`) — usada desde los payment
  processors, ver `modulos/04-pagos.md`.
- `src/jobs/sync-products.ts` — ver detalle completo en
  `modulos/01-productos-catalogo.md`.
- `src/jobs/sync-clients-b2b.ts`, `sync-policies-b2b.ts`,
  `sync-transportistas-b2b.ts` — **bugs de atomicidad confirmados**:
  - `sync-transportistas-b2b.ts` línea ~20: `TransportistasB2bRepository.clear()`
    (TRUNCATE) + reinserción uno por uno **sin transacción** — si falla a
    mitad de camino, la tabla queda vacía. Además duplicado en el endpoint
    HTTP público `src/api/store/sync-transportista-b2b/index.ts` (sin
    autenticación).
  - `sync-clients-b2b.ts` y `sync-policies-b2b.ts` — `.delete()`/`.save()`
    llamados **sin `await`** dentro de un loop, sin transacción — condiciones
    de carrera y errores silenciosos. También duplicado en endpoints HTTP
    públicos gemelos.
  - **En Laravel, cada uno de estos tres syncs se envuelve en
    `DB::transaction()`** y usa `upsert()`/diffing en vez de
    truncate-and-reinsert — corrección no negociable, no es una mejora
    opcional.
- `MEDUSA_JOBS` env var — gate on/off de todos los jobs. En Laravel el
  equivalente es simplemente no registrar el schedule en un entorno, o una
  flag de config que el `Schedule::command(...)->when(...)` consulte.

## Diseño en Laravel

- `app/Domain/ErpSync/Clients/ShinerayErpClient.php` implementa
  `ErpClientContract` (`03-contratos.md`) — **unifica** los 3 clientes HTTP
  fragmentados actuales (`shineray-api.js`, `shineray-b2b-api.js`,
  `shineray-billing.ts`) en un solo cliente, con manejo de token
  centralizado (evaluar cache del token en Redis si el ERP lo permite).
- Jobs programados en `app/Domain/ErpSync/Jobs/`:
  - `SyncProductsJob` (mismo cron: `30 6,10,12,14,17 * * *`) — implementa la
    misma dedup por `COD_PRODUCTO`, la misma guarda `count >= 50` antes de
    soft-delete, y preserva `allow_backorder: true` (ver
    `modulos/01-productos-catalogo.md`).
  - `SyncImagesJob` (cron: `0 6,10,12,14,17 * * *`) — descarga imágenes →
    sube a S3.
  - `SyncClientsB2bJob`, `SyncPoliciesB2bJob`, `SyncTransportistasB2bJob`
    (cron: `*/5 * * * *` cada uno) — **con `DB::transaction()` real**,
    reemplazando el patrón truncate-and-reinsert por upsert cuando sea
    posible (evita la ventana de tabla vacía incluso durante la transacción
    en escenarios de solape de ejecuciones — considerar un lock, ej.
    `Cache::lock()`, para que dos ejecuciones del mismo job no se solapen).
- **No se exponen estos syncs como endpoints HTTP públicos** (a diferencia
  de hoy) — si se necesita disparo manual, va detrás de auth de admin en el
  panel Filament (un botón "Sincronizar ahora"), nunca una ruta pública sin
  autenticación.

## Decisiones pendientes

- Confirmar con el ERP si el token soporta cache (TTL) para reducir llamadas
  de autenticación repetidas.
- Decidir si el diffing de sync (detectar qué cambió) se hace comparando
  campo por campo o simplemente sobreescribiendo siempre — el volumen de
  productos del catálogo (visto en los scripts de diagnóstico) debería
  confirmarse para elegir la estrategia más barata.
