# Módulo: Búsqueda (Meilisearch)

## Referencia en `medusa-shineray`

- `medusa-config.js` líneas 61-221 — configuración completa de
  `medusa-plugin-meilisearch`: `searchableAttributes`, `rankingRules`
  (incluye el custom `hasStock:desc` primero), `displayedAttributes`,
  `filterableAttributes`, `sortableAttributes`, y el `transformer` (líneas
  150-216) que:
    - Excluye productos no `published` salvo `MEILISEARCH_ALLOW_DRAFTS=true`.
    - Deriva `nombreSubsistema` con prioridad: `NIVEL_3` real → `NOMBRE_SUBSISTEMA`
      → función `deriveSubsistema(categoria)` (fallback por palabras clave de
      categoría, líneas 155-178) — **portar el fallback completo**, es
      lógica de negocio real usada porque el ERP no siempre manda
      `NOMBRE_SUBSISTEMA`.
    - `hasStock: product.variants[0].inventory_quantity > 0`.
    - `isB2b`, `isLandingPromo`, `OldPrice` desde `metadata`.
- `src/subscribers/meilisearch-cleanup.ts` (91 líneas) — limpia del índice
  productos que ya no están `published` en Postgres, con espera activa
  (`waitForMeiliTasks`, hasta 120s) a que terminen las tareas de indexado
  pendientes antes de comparar. Usa el cliente oficial `meilisearch`
  directamente, no el plugin.
- `src/scripts/reindex-meilisearch.ts` — script de reindex manual completo.
- `src/scripts/verify-migration.js` — script de diagnóstico que ya replica el
  transformer a mano para verificar campos `NIVEL_1..4` contra el índice real
  — útil como referencia de qué forma exacta de documento se espera en
  Meilisearch hoy (para no romper compatibilidad con el frontend al migrar).

## Diseño en Laravel

- `Product` usa `Laravel\Scout\Searchable`. `toSearchableArray()` replica el
  `transformer` completo — incluyendo `deriveSubsistema` portado como método
  privado del modelo o de un `CatalogTaxonomy` helper class (evitar el
  if-else anidado tal cual, usar un mapa de constantes categoría→subsistema).
- `shouldBeSearchable()` reemplaza el filtro `status !== 'published'` del
  transformer — Scout ya soporta esto nativamente, no hace falta un
  subscriber de limpieza aparte para eso.
- El equivalente de `meilisearch-cleanup.ts` (borrar del índice documentos
  huérfanos que quedaron de una migración de esquema o un fallo de sync) se
  implementa como un comando Artisan (`php artisan search:cleanup`) o un job
  programado, no como un listener permanente — es una tarea de mantenimiento,
  no un flujo de negocio recurrente por evento.
- La configuración de índice (`filterableAttributes`, `rankingRules`, etc.)
  va en `config/scout.php` bajo la key `meilisearch.index-settings.products`
  — Scout la aplica automáticamente al correr `scout:sync-index-settings`.

## Decisiones pendientes

- Confirmar que el paquete `meilisearch/meilisearch-php` + `laravel/scout`
  soportan exactamente las mismas `rankingRules` custom (`hasStock:desc`
  antepuesto a las reglas por defecto) — verificar contra la versión de
  Meilisearch en uso antes de asumir paridad total.
- Decidir si `reindex-meilisearch.ts` se convierte en comando Artisan
  (`php artisan scout:import`) estándar de Scout, o si se necesita lógica
  adicional que el script actual tenga y Scout no cubra por defecto (revisar
  ese script completo antes de descartarlo).
