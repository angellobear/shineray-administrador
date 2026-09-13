# Modelo de datos

Esquema relacional plano, sin las tablas de multi-región/multi-moneda de
Medusa (`region`, `money_amount` por región, `currency`, `price_list`, etc. no
existen aquí — una sola moneda USD, un solo país). Todos los montos son
enteros en centavos (`integer`, no `decimal`), igual que la convención actual.

Convención de claves: `id` `bigint` autoincremental está bien para todas las
tablas nuevas (no hace falta el prefijo tipo `prod_xxx` de Medusa; es una
convención cosmética de Medusa, no un requisito funcional). Se usa
`ulid`/`uuid` únicamente si se necesita exponer el ID en URLs públicas sin
filtrar volumen de negocio (a decidir por tabla si aplica).

## Catálogo

**`products`**
- `id`, `title`, `description` (text), `handle` (slug único), `thumbnail`
  (url), `status` (enum: `draft`,`published`)
- `metadata` (jsonb) — campos ricos del ERP: `codigo_marca`, `moto_modelo`,
  `codigo_categoria`, `nombre_categoria`, `codigo_subsistema`,
  `nivel_1..4`/`cod_nivel_1..4`, `anio_desde`, `anio_hasta`, `cod_producto`,
  `id_item_ns`, `is_b2b`, `is_landing_promo`, `old_price` — se mantienen en
  jsonb (igual que hoy en Medusa) en vez de columnas propias, porque el ERP
  puede agregar campos y no queremos migración de esquema cada vez.
- timestamps + `deleted_at` (soft delete — reemplaza el "draft como soft
  delete" actual del sync de productos por un soft-delete real de Eloquent,
  a decidir en `modulos/01-productos-catalogo.md` si se mantiene el patrón
  "draft" por compatibilidad con el filtro de Meilisearch/frontend).

**`product_variants`**
- `id`, `product_id` FK, `sku` (único, es el `COD_PRODUCTO` del ERP),
  `title`, `price` (integer, centavos, USD), `inventory_quantity` (integer),
  `allow_backorder` (boolean, default `true` — ver nota de negocio abajo),
  `manage_inventory` (boolean, default `true`), `weight` (decimal, kg)

  > **Nota de negocio a preservar**: en el código actual, `allow_backorder:
  > true` es deliberado, no un descuido — evita que el checkout rechace una
  > orden por falta de stock local cuando el stock real lo controla el ERP
  > externo. Ver `modulos/01-productos-catalogo.md` y
  > `modulos/09-erp-sync.md` para el detalle completo y la regla de negocio
  > que depende de esto (evitar el caso "se cobró pero no se creó la orden").

**`product_options`** / **`product_variant_options`** — solo si el catálogo de
motopartes realmente necesita variantes con opciones (color/talla). A
confirmar con negocio en `modulos/01-productos-catalogo.md`; si no aplica,
se omiten estas dos tablas y `product_variants` es 1:1 con `products`.

## Carrito

**`carts`**
- `id`, `customer_id` FK nullable (carrito de invitado permitido),
  `email`, `status` (enum: `active`,`completed`,`abandoned`),
  `subtotal`, `shipping_total`, `tax_total`, `discount_total`, `total`
  (todos integer, centavos, USD — sin `currency_code` variable),
  `shipping_address_id` FK nullable, `metadata` (jsonb)
- Columnas de abandoned-cart (portadas 1:1 desde `cart.abandoned_*` de
  Medusa): `abandoned_completed_at` (timestamp nullable),
  `abandoned_count` (integer, default 0),
  `abandoned_last_interval` (bigint nullable),
  `abandoned_lastdate` (timestamp nullable)

**`cart_items`**
- `id`, `cart_id` FK, `product_variant_id` FK, `quantity`,
  `unit_price` (snapshot al momento de agregar, no recalculado del producto)

## Órdenes

**`orders`**
- `id`, `order_number` (único, visible al cliente), `cart_id` FK nullable,
  `customer_id` FK, `status` (enum: `pending`,`paid`,`fulfilled`,`canceled`,
  `refunded`), `subtotal`, `shipping_total`, `tax_total`, `discount_total`,
  `total` (integer, centavos), `shipping_address_id` FK, `billing_address_id`
  FK nullable, `metadata` (jsonb — aquí van los campos hoy sueltos en
  `cart.shipping_address.metadata`: `city_id`, `dni`, `servientrega_guide_id`,
  para B2B también `transportista_id`, `numero_cuota`, `cod_client`, `ruc`)

**`order_items`**
- `id`, `order_id` FK, `product_variant_id` FK, `quantity`, `unit_price`,
  `title` (snapshot del nombre del producto al momento de la compra)

## Direcciones

**`addresses`**
- `id`, `addressable_type`/`addressable_id` (polimórfica: cart, order,
  customer), `first_name`, `last_name`, `phone`, `address_1`, `city`,
  `province`, `country_code` (fijo `EC`), `postal_code` (default `"000000"`
  — se preserva el valor porque Ecuador no usa códigos postales de forma
  consistente en el flujo de compra, no es un bug), `metadata` (jsonb —
  `dni`, `city_id` del ERP)

## Clientes

**`customers`**
- `id`, `email` (único), `password` (nullable si el flujo B2B autocreación
  sigue existiendo — ver `modulos/06-b2b.md` para la decisión de si se
  mantiene un password compartido o se fuerza reset individual, **corrigiendo**
  el hash fijo hardcodeado actual), `first_name`, `last_name`, `phone`,
  `is_b2b` (boolean), `dni` nullable

## B2B

**`client_b2b`**
- `id`, `customer_id` FK **nullable pero recomendado poblarlo siempre**
  (corrige la falta de relación real que existe hoy entre `ClientB2b` y
  `Customer`, que hoy se resuelve en runtime por email), `id_client` (código
  externo Shineray/RUC, único), `type_client`, `first_name`, `last_name`,
  `phone_number`, `email`, `address` (jsonb), `active` (boolean)

**`policies_b2b`** (implementada como `b2b_policies`)
- `id`, `client_b2b_id` FK (**corrige** el `cod_cliente varchar` suelto de
  hoy, que no es una FK real), `es_activo` (boolean), `factor_credito`
  (decimal), `num_cuotas` (integer)
- **Varias por cliente**: el ERP manda `cuotas_info[]` con una política por
  número de cuotas. Único `(client_b2b_id, num_cuotas)`. Ver
  `07-verificacion-codigo.md`.

**`transportistas_b2b`**
- `id`, `razon_social`, `ruc`

## Pagos

**`payments`**
- `id`, `order_id` FK, `gateway` (enum: `datafast`,`datafast_b2b`,`deuna`,
  `deuna_b2b`,`credito_b2b`), `status` (enum: `pending`,`authorized`,
  `captured`,`failed`,`refunded`,`canceled`), `amount` (integer, centavos),
  `gateway_reference` (string — `checkoutId`/`id` de OPPWa, id de DeUna),
  `raw_request` (jsonb), `raw_response` (jsonb), `authorized_at` (timestamp
  nullable)

**`payment_logs`**
- `id`, `payment_id` FK nullable, `gateway`, `event` (string —
  `servientrega_fail`, `invoice_fail`, etc., mismo vocabulario que el logger
  actual), `payload` (jsonb), `created_at`
- **Reemplaza** el archivo local `logs/payments.jsonl` (`fs.appendFileSync`)
  por una tabla real — necesario en cuanto haya más de una instancia del
  backend corriendo (el archivo local no escala ni es consultable).

## Envíos

**`shipments`**
- `id`, `order_id` FK, `provider` (enum: `servientrega`), `guide_number`
  (string, nullable — `null` explícito en vez del string `"000000"` actual
  cuando no hay guía; ver `modulos/05-envios-servientrega.md`), `status`
  (enum: `pending`,`created`,`failed`,`canceled`), `cost` (integer, centavos),
  `raw_response` (jsonb)

## Descuentos

**`discounts`**
- `id`, `code` (único), `type` (enum: `percentage`,`fixed`,`free_shipping`),
  `value` (integer — porcentaje o centavos según `type`), `is_active`
  (boolean), fechas de vigencia si aplica

**`cart_discounts`** / **`order_discounts`** — pivote simple `cart_id`/
`order_id` + `discount_id`. Sustituye el modelo de `discount.rule.type/value`
de Medusa por algo plano — el código Node actual ya reimplementaba esta
lógica manualmente en el processor de Datafast en vez de confiar en el
cálculo de Medusa, así que no hay pérdida de funcionalidad al simplificar.

## Onboarding (admin)

**`onboarding_state`** — singleton: `id`, `current_step`, `is_complete`
(boolean), `product_id` FK nullable. Puede implementarse directo como un
recurso de Filament con un solo registro, o descartarse si el wizard de
onboarding no se considera prioritario para el MVP (a decidir, es la pieza
de menor riesgo/valor de todo el sistema).

## Relación con `05-migracion-datos-corte.md`

Este esquema es el **destino** del ETL de datos existentes. El mapeo
columna-por-columna desde las tablas actuales de Postgres (TypeORM) hacia
este esquema se detalla en `05-migracion-datos-corte.md`, no aquí.
