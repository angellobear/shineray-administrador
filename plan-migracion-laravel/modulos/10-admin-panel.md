# Módulo: Panel de administración

## Referencia en `medusa-shineray`

- `src/admin/` — widgets/rutas React custom sobre `@medusajs/admin`.
- `src/api/admin/abandoned-cart/index.ts` — listar carritos abandonados +
  disparar email de recuperación manual.
- `src/api/admin/custom/index.ts` — endpoint trivial, sin lógica real.
- `src/api/admin/customers/[customerId]/index.ts` — DELETE de cliente.
- `src/api/admin/get-variants-by-sku/index.ts` — búsqueda de variante por
  SKU (usado también por `sync-products.ts`, ver `modulos/09-erp-sync.md`).
- `src/api/admin/onboarding/index.ts` — wizard de onboarding del admin
  (`OnboardingState`).
- `src/strategies/custom-order-export-strategy.ts` — exporta órdenes a CSV
  vía `BatchJobService`/`csv-writer`, usa `atomicPhase_` correctamente (el
  único código de admin bien construido según la auditoría original).

## Diseño en Laravel

- **Filament** como panel de administración completo, no una reconstrucción
  de cada endpoint uno por uno:
    - `ProductResource`, `OrderResource`, `CustomerResource`,
      `ClientB2bResource`, `PolicyB2bResource`, `TransportistaB2bResource` —
      CRUD generado sobre Eloquent, con las relaciones ya definidas en
      `02-modelo-datos.md`.
    - Página custom "Carritos abandonados" con acción "Reenviar email" —
      equivalente a `admin/abandoned-cart`.
    - Exportación de órdenes a CSV — Filament trae exportación de tablas de
      forma nativa (`ExportAction`), probablemente cubre el caso de uso de
      `custom-order-export-strategy.ts` sin código custom.
    - Página/wizard de onboarding — solo si se decide mantenerlo (ver
      `02-modelo-datos.md`, es la pieza de menor prioridad de todo el sistema).
- El acceso al panel usa el guard `admin` de Sanctum/sesión de Laravel
  (Filament ya trae su propio sistema de autenticación integrado con los
  guards de Laravel) — reemplaza la auth admin de Medusa sin trabajo extra.

## Decisiones pendientes

- Revisar `src/admin/` completo (no cubierto en profundidad en la auditoría
  inicial) para confirmar si hay algún widget con lógica de negocio propia
  más allá de lo ya listado arriba, antes de asumir que Filament out-of-the-box
  cubre el 100% del admin actual.
