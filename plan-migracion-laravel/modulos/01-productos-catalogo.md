# Módulo: Productos y catálogo

## Referencia en `medusa-shineray` (leer solo esto para este módulo)

- `src/jobs/sync-products.ts` (176 líneas) — job que trae productos del ERP y
  los crea/actualiza en Medusa vía **su propia API HTTP admin** (`axios`
  contra `MEDUSA_URL_BACK`), no vía servicios inyectados. Contiene reglas de
  negocio importantes:
  - Línea ~26-28: dedup por `COD_PRODUCTO` cuando el mismo producto viene en
    varias bodegas/modelos — "primera fila gana".
  - Línea ~34: `shouldSoftDelete = uniqueProducts.length >= 50` — guarda de
    seguridad para no marcar todo como `draft` si el ERP devuelve una
    respuesta vacía/corrupta.
  - Líneas ~107-108: `allow_backorder: true, manage_inventory: true` en cada
    variante — **regla de negocio crítica**, no un descuido. Existe un test
    dedicado en el repo Node (`src/utils/jobs/__tests__/allow-backorder.spec.ts`)
    que documenta por qué: si `allow_backorder` es `false`, `confirmInventory()`
    de Medusa puede rechazar el checkout con 409 **después** de que el
    payment processor ya cobró y ya facturó en el ERP — el stock real lo
    controla el ERP externo, no Medusa. **Esta regla debe preservarse
    explícitamente en Laravel**: el checkout nunca debe bloquear por stock
    local: `product_variants.manage_inventory` puede incluso omitirse del
    todo si se decide no llevar control de inventario local en absoluto (a
    decidir en este módulo).
  - Líneas ~144-163: soft-delete = poner `status: "draft"` a productos
    publicados que ya no vinieron en el feed del ERP.
- `src/utils/jobs/shineray-api.js` (352 líneas) — cliente HTTP del ERP para
  catálogo: obtención de token, `getTransformedDataShineray()` (transforma
  filas del ERP a la forma de producto que espera la API de Medusa),
  `checkStockAvailable()`. Contiene el bug documentado de
  `getTiposSubsistemas()` (pasa `cod_categoria` en vez de `cod_modelo` como
  query param) — **no replicar ese bug**, corregirlo al portar.
- `medusa-config.js` líneas 61-221 — el `transformer` de
  `medusa-plugin-meilisearch`: aquí vive la lógica de taxonomía (nivel1-4,
  fallback `deriveSubsistema`), cálculo de `hasStock`, flags `isB2b`/
  `isLandingPromo`. Esta lógica **no es de Meilisearch en sí**, es lógica de
  catálogo — en Laravel vive en el modelo `Product` (`toSearchableArray()`),
  no en el módulo de búsqueda. Ver también `modulos/07-busqueda-meilisearch.md`.
- `src/scripts/validate-v2-fields.js` y `src/scripts/verify-migration.js` —
  scripts de diagnóstico (no producción) que documentan la forma real de la
  respuesta del ERP incluyendo los campos `NIVEL_1..4`/`COD_NIVEL_1..4` — útil
  como referencia de la forma exacta del payload del ERP, sin necesidad de
  volver a pedirle acceso al ERP para descubrirla.
- Precio: fórmula documentada en `CLAUDE.md` del repo Node:
  `Math.round((PRECIO / 1.15) * 100)` — quita el IVA ecuatoriano del precio
  del ERP y lo pasa a centavos. **En Laravel usa `config('shineray.iva_rate')`,
  no `1.15` hardcodeado.**

## Esquema de datos

Ver `02-modelo-datos.md` secciones `products`, `product_variants`,
`product_options`/`product_variant_options`.

## Diseño en Laravel

- `app/Domain/Catalog/Models/Product.php`, `ProductVariant.php`.
- `Product` usa el trait `Searchable` de Scout directamente (ver
  `modulos/07-busqueda-meilisearch.md`) — el `toSearchableArray()` replica el
  `transformer` actual, incluyendo la lógica de `deriveSubsistema` como método
  privado o un `Enum`/mapa de constantes (no si-else anidado suelto).
- La regla `allow_backorder`/sin bloqueo de stock local se documenta como
  comentario explícito en el modelo o el `ProductVariant`, citando el porqué
  (el mismo que el test Node), para que nadie la "corrija" pensando que es un
  descuido en una futura limpieza de código.

## Decisiones pendientes para este módulo

- ¿Se necesitan `product_options`/`product_variant_options` reales (color,
  talla) o cada producto del catálogo de motopartes es 1:1 con una sola
  variante? Confirmar con negocio antes de modelar la tabla — si la respuesta
  es "no hay variantes reales", se elimina una capa de complejidad completa.
- ¿Se mantiene el patrón "draft = soft delete" para compatibilidad con la
  búsqueda, o se usa el soft-delete nativo de Eloquent (`deleted_at`) y se
  filtra por eso en Scout? Cualquiera de las dos funciona; hay que elegir una
  sola convención y no mezclar ambas como hoy.
