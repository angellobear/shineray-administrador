# Fases y cronograma

Con un solo desarrollador (sin experiencia previa en Laravel a gran escala,
a confirmar) y PM acompañando decisiones de negocio. Los rangos son
estimados de esfuerzo real de construcción; no incluyen tiempo de espera de
terceros (credenciales de sandbox, respuestas del proveedor de Servientrega
sobre qué endpoint es el canónico, etc.), que deben gestionarse en paralelo
desde el día 1 porque bloquean las fases 4 y 5.

## Fase 0 — Fundaciones (1-2 semanas)

- Scaffold del proyecto Laravel, `config/shineray.php`, conexión a Postgres,
  Redis, colas, Horizon.
- Migraciones completas de `02-modelo-datos.md` (todas las tablas, sin
  datos).
- `ServiceProvider` de bindings de contratos (`03-contratos.md`) — aunque las
  implementaciones concretas aún no existan, dejar la estructura de
  interfaces + un stub que lance `NotImplementedException`, para que el
  resto del código pueda desarrollarse contra el contrato desde ya.
- CI básico: lint (Pint), tests (Pest/PHPUnit) corriendo en cada push.

## Fase 1 — Catálogo + búsqueda (2-3 semanas)

Depende de: Fase 0. Ver `modulos/01-productos-catalogo.md`,
`modulos/07-busqueda-meilisearch.md`.

- Modelos `Product`/`ProductVariant`.
- `ShinerayErpClient::fetchProducts()` + `SyncProductsJob` (con
  `DB::transaction()`, preservando `allow_backorder`).
- Integración Scout + Meilisearch con el `toSearchableArray()` completo.
- Esta fase es la base de todo lo demás — sin catálogo no hay carrito ni
  órdenes que probar de extremo a extremo.

## Fase 2 — Carrito + carrito abandonado (1-2 semanas)

Depende de: Fase 1. Ver `modulos/02-carrito-abandonado.md`.

- Modelos `Cart`/`CartItem`, endpoints de storefront para armar carrito.
- `DetectAbandonedCartsJob` + email de recuperación.

## Fase 3 — Órdenes/checkout (esqueleto sin pago real) (1 semana)

Depende de: Fase 2. Ver `modulos/03-ordenes-checkout.md`.

- `CheckoutService`, modelos `Order`/`OrderItem`, transición de estados,
  evento `PaymentAuthorized` ya declarado (sin listeners reales todavía).
- Aquí se puede probar el flujo completo con un gateway "fake"/manual antes
  de meter Datafast/DeUna reales — reduce el riesgo de depurar checkout y
  pasarela de pago al mismo tiempo.

## Fase 4 — Pagos (Datafast → DeUna → Crédito B2B) (4-6 semanas)

Depende de: Fase 3. **Bloqueada por credenciales de sandbox reales** —
gestionar en paralelo desde la Fase 0. Ver `modulos/04-pagos.md`.

- Empezar por Datafast B2C (el más completo/documentado), luego replicar el
  patrón para B2B y DeUna (más rápido, ya con el patrón establecido).
- Crédito B2B al final (no depende de pasarela externa, es el más simple de
  los cinco).

## Fase 5 — Envíos (Servientrega) (2-3 semanas)

Puede empezar en paralelo con la Fase 4 una vez resuelta la Fase 3, pero el
listener `CreateServientregaGuideListener` que las conecta depende de ambas.
**Bloqueada por la decisión de negocio de qué endpoint/credenciales de
Servientrega son los reales** — resolver esto lo antes posible, es la
decisión pendiente de mayor impacto en el cronograma. Ver
`modulos/05-envios-servientrega.md`.

## Fase 6 — B2B completo (2-3 semanas)

Depende de: Fases 1, 3, 4 (crédito B2B). Ver `modulos/06-b2b.md`.

- Modelos y relaciones `ClientB2b`/`PolicyB2b`/`TransportistaB2b`.
- Sync jobs B2B (`modulos/09-erp-sync.md`) con transacciones reales.
- Flujo de verificación/creación de cliente B2B (con la decisión de
  password ya tomada).

## Fase 7 — Notificaciones (1 semana)

Puede empezar tan pronto exista al menos un evento de dominio disparándose
(Fase 3). Ver `modulos/08-notificaciones.md`.

## Fase 8 — Panel de administración (2-3 semanas)

Puede empezar en paralelo desde que existen los modelos base (Fase 1-2) y
se va completando a medida que existen más recursos que administrar. Ver
`modulos/10-admin-panel.md`.

## Fase 9 — Seguridad, auditoría final, hardening (1-2 semanas)

Corre en paralelo a todas las fases (el checklist de
`modulos/11-auth-seguridad.md` se aplica módulo por módulo, no al final),
pero se reserva tiempo dedicado al cierre para una pasada completa de
verificación antes de producción.

## Fase 10 — Migración de datos + corte (2-4 semanas)

Depende de: todas las anteriores funcionalmente completas. Ver
`05-migracion-datos-corte.md`.

## Total estimado

**16-24 semanas (4-6 meses) de desarrollo**, para un desarrollador full-time,
sin contar tiempos de espera de terceros ni el trabajo de adaptación del
frontend Next.js (fuera de alcance de este plan, pero debe coordinarse en
paralelo desde la Fase 4 en adelante, porque el frontend necesita saber la
forma final de la API de pagos/checkout antes del corte).

Recomendación: tratar la **Fase 1 completa** como el primer hito de
validación real de velocidad — al terminarla se sabrá con mucha más
precisión si el resto del cronograma es realista para este equipo.
