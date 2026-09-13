# Módulo: Carrito y carrito abandonado

## Referencia en `medusa-shineray`

- `src/models/cart.ts` (extensión de `Cart` core de Medusa) — las 4 columnas
  de abandoned-cart: `abandoned_completed_at`, `abandoned_count`,
  `abandoned_last_interval`, `abandoned_lastdate`. Se portan literal a la
  tabla `carts` (ver `02-modelo-datos.md`).
- `src/services/abandoned-cart.ts` (404 líneas, `TransactionBaseService`) —
  el motor real: `retrieveAbandonedCarts` (lee intervalos configurables de
  `options_`), `sendAbandonedCartEmail`, `setCartsAsCompleted`. Usa
  `CartRepository` core de Medusa directamente.
- `src/jobs/schedule-abandoned.ts` — cron `*/5 * * * *`, dispara el motor
  anterior; usa `Promise.all(promises)` correctamente (sin el problema de
  atomicidad que sí tienen los jobs de B2B).
- `medusa-plugin-abandoned-cart` (plugin de terceros, en `package.json`) —
  puede que parte de la orquestación de intervalos venga de configuración de
  este plugin, no solo del servicio custom; si al leer `abandoned-cart.ts` hay
  referencias a opciones que no se explican ahí, revisar cómo se registra
  este plugin en `medusa-config.js` antes de asumir el comportamiento.
- `data/templates/abandoned_cart` (Handlebars) — plantilla del email.

## Esquema de datos

`carts` (con las 4 columnas de abandoned-cart) — ver `02-modelo-datos.md`.
No hace falta tabla aparte: es un carrito con estado.

## Diseño en Laravel

- **Regla de estado**: un carrito pasa a considerarse "abandonado" cuando
  lleva más de N minutos/horas sin actividad y tiene items — el intervalo
  exacto y el número de reintentos de email deben leerse de la configuración
  actual del plugin/servicio Node (`abandoned-cart.ts`) antes de portar, no
  inventarse un valor nuevo.
- Un **Job programado** (`app/Domain/Cart/Jobs/DetectAbandonedCartsJob.php`),
  agendado cada 5 minutos igual que hoy, que:
    1. Encuentra carritos activos sin actividad reciente y sin
       `abandoned_lastdate`, los marca y dispara el primer email.
    2. Para carritos ya marcados, aplica la lógica de reintento
       (`abandoned_count`, `abandoned_last_interval`) para decidir si toca
       reenviar o ya se debe marcar `abandoned_completed_at`.
    3. Envía el email vía el sistema de notificaciones de Laravel (ver
       `modulos/08-notificaciones.md`), no acoplado a un servicio de mail
       propio.
- Un carrito que se completa (pasa a `orders`) debe limpiar/journal-ear su
  estado de abandono — replicar `setCartsAsCompleted` como parte del flujo
  de checkout (`modulos/03-ordenes-checkout.md`), no como un paso aparte.

## Decisiones pendientes

- **Verificado (2026-09-13):** no hay intervalos configurados en ningún
  lado (`options_.intervals = []` en `abandoned-cart.ts` y el plugin no está
  en `medusa-config.js`). El cron termina sin hacer nada; hoy solo existe el
  envío manual desde el admin. Negocio debe definir los intervalos (ej. 1 h,
  24 h, 72 h) antes de activar el job en Laravel. La lógica de reintento sí
  está implementada y se documenta en `07-verificacion-codigo.md`.
