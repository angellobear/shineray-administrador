# Verificación del plan contra el código Node real

Fecha: 2026-09-13. Fuente leída: fork `angellobear/medusa-shinera-backup`,
rama `refactor/medusa-v2` (commit `8b1ddf5`, Medusa v1.20.6). Este documento
registra qué hallazgos del plan se confirmaron, cuáles estaban incompletos y
qué cambió en el código Laravel como consecuencia.

## Resumen

El plan es fiel al código en lo estructural. Las correcciones importantes:

| Tema | Plan decía | Código real | Acción |
| --- | --- | --- | --- |
| Políticas B2B | `ClientB2b hasOne PolicyB2b` con FK | `COD_CLIENTEH` es el **tipo** de cliente (`type_client`, ej. `DM`), no el cliente; `get-cuota-b2b` consulta por tipo con alias `DI → DM`; `cuotas_info[]` trae una política por número de cuotas | `b2b_policies(client_type, installments)` sin FK a clientes; `B2bClient::activePolicies()` resuelve el alias |
| Carrito abandonado | "portar los intervalos reales" | `options_.intervals = []` hardcodeado y el constructor ignora las opciones del plugin: **el cron sale sin hacer nada**; solo funciona el envío manual desde el admin | Config `intervals_minutes` vacío por defecto + decisión de negocio pendiente |
| Precio ERP | `round(PRECIO/1.15*100)` | `round(round(round(PRECIO,2)/1.15,2)*100)` (doble redondeo) | `TaxCalculator::erpGrossPriceToNetCents` replica la cadena exacta |
| Cotización Servientrega | flete → centavos | flete → centavos **+ 15% IVA** (`valorFinalAddIva`), timeout 6 s, fallback $5.00 sin log | `shipping.quote.add_iva = true` |
| Metadata de producto | se guarda tal cual | el sync **sobrescribe** `metadata` con las claves del ERP; `is_b2b`, `IS_PROMO`, `OLD_PRICE`, `ID_ITEM_NS` se editan a mano en el admin y sobreviven porque Medusa hace merge | `SyncProductsJob` debe hacer merge de metadata, nunca reemplazar |
| Check de stock | 1 llamada por producto | 1 llamada **y 1 token** por producto (~7 000 pares de requests por sync) | El endpoint acepta un array: hacer batch y cachear token |
| Clientes ERP | 3 clientes HTTP | **4** (`src/api/utils/shineray-api.js` también obtiene token) | Sin cambio: ya se unifica en `ErpClientContract` |

## Módulo 01 / 09 — Catálogo y sync (verificado)

- `sync-products.ts`: dedup por `COD_PRODUCTO` (primera fila gana), guarda
  `uniqueProducts.length >= 50`, `allow_backorder: true` + `manage_inventory: true`,
  soft-delete = `status: "draft"`. **Confirmado.**
- Cron `30 6,10,12,14,17 * * *` (productos) y `0 6,10,12,14,17 * * *`
  (imágenes). **Confirmado.**
- Forma del producto (`transformJsonMedusa`):
  - `handle = slug(NOMBRE_PRODUCTO + "-" + COD_PRODUCTO)`.
  - `thumbnail = https://shineray-public.s3.amazonaws.com/repuestos/{COD}.jpg`;
    el job de imágenes descarga de `{ERP}/imageApi/img?code={COD}` y sube a S3
    con reintentos (3) y validación de `Content-Length`.
  - `weight = PESO ? round(PESO) : 1` (unidad sin documentar; ver Servientrega).
  - `metadata` con claves **en mayúsculas del ERP** (`COD_PRODUCTO`,
    `CODIGO_MARCA`, `NOMBRE_CATEGORIA`, `NIVEL_1..4`, `COD_NIVEL_1..4`,
    `ANIO_DESDE/HASTA`, `MOTO_MODELO`, `CODIGO_MODELO_MOTO`, `NOMBRE_BODEGA`,
    `COD_AGENCIA`, `ICE`, `IVA`, `CONTROL_BUFFER`, `COD_UNIDAD`). El
    transformer de Meilisearch lee exactamente estas claves: **mantenerlas
    idénticas** en `products.metadata` para no romper el frontend.
  - `inventory_quantity = checkStock(COD).available`.
- ERP: `POST /get-token` `{username,password}` → token; `GET /api/all_parts`;
  `POST /api/checkStock` `[{cod_producto, quantity}]` → `[{available, complete_purchase}]`;
  dropdowns `/api/marcas|categories|modelos|subsistema|anio/dropdown`. El bug
  `cod_categoria` vs `cod_modelo` en `getTiposSubsistemas` **ya está
  corregido** en esta rama.
- `MEDUSA_JOBS !== "true"` desactiva todos los jobs. **Confirmado.**
- Test `allow-backorder.spec.ts` y migración `AllowBackorderOnVariants`:
  5 273 de 7 125 variantes ya no vienen en el feed; la migración las arregló
  en bloque. Para el ETL: **todas** las variantes migran con
  `allow_backorder = true`, sin excepción.

## Módulo 07 — Meilisearch (verificado)

- `searchableAttributes`, `rankingRules` (`hasStock:desc` primero),
  `displayedAttributes`, `filterableAttributes`, `sortableAttributes`
  copiados literal a `config/scout.php`.
- Transformer: excluye no `published` salvo `MEILISEARCH_ALLOW_DRAFTS=true`;
  `nombreSubsistema = NIVEL_3 || NOMBRE_SUBSISTEMA || deriveSubsistema(NOMBRE_CATEGORIA)`;
  `hasStock = variants[0].inventory_quantity > 0`; `isB2b = metadata.is_b2b`;
  `isLandingPromo = metadata.IS_PROMO`; `OldPrice = metadata.OLD_PRICE`;
  `idItemNS = metadata.ID_ITEM_NS`; `price = variants[0].prices[0].amount`.
- `deriveSubsistema` (mapa categoría → TANQUE / MOTOR / ESTETICO /
  TREN DELANTERO / TREN POSTERIOR / ESTRUCTURAL / ELECTRICO) está completo en
  `medusa-config.js` 155-178; se porta a `App\Domain\Catalog\CatalogTaxonomy`
  en la Fase 1 con test por cada rama.

## Módulo 02 — Carrito abandonado (corregido)

- Columnas `abandoned_*` **confirmadas** (`cart.ts`).
- Motor: `retrieveAbandonedCarts` filtra `email IS NOT NULL`, `email NOT LIKE
  '%storebotmail%'`, `completed_at IS NULL`, `created_at > now() - N días`
  (`days_to_track`, 30), `abandoned_completed_at IS NULL`, con items.
- Job `*/5 * * * *`: por carrito calcula el siguiente intervalo a partir de
  `abandoned_last_interval`; base = `created_at`, o `updated_at` si el carrito
  se editó más de `primer_intervalo + 5 min` después de crearse; si pasó
  `max_overdue` (2 h) del intervalo → `abandoned_completed_at`; no reenvía si
  `abandoned_lastdate` < 3 min; al enviar incrementa `abandoned_count`, fija
  `abandoned_lastdate/last_interval`, y si era el último intervalo marca
  `abandoned_completed_at`.
- **Hallazgo:** `intervals: []` → el job termina en "No intervals" siempre.
  En producción hoy **no se envían emails automáticos**; solo `POST
  admin/abandoned-cart {id}` manual. Negocio debe definir los intervalos
  antes de activar `SHINERAY_ABANDONED_CART_ENABLED`.
- Link de recuperación: `${STORE_CORS}/cart` (→ `shineray.storefront_url`).

## Módulo 03 / 04 — Checkout y pagos (verificado)

- Secuencia en `authorizePayment` de Datafast/DeUna B2C: (1) validar con el
  gateway → (2) crear guía Servientrega (try/catch, log `servientrega_fail`)
  → (3) `medusa.carts.update` con `metadata.servientrega_guide_id` → (4)
  `getInfoClients(dniType, dni)`; si `cliente.estado === "NO REGISTRADO"`
  → `saveInfoClients` con `type_client: "CF"` → (5) `saveInvoice*` (try/catch,
  log `invoice_fail`) → (6) `AUTHORIZED`. **Confirmado.**
- Datafast: `POST {oppwa}/v1/checkouts` form-urlencoded; éxito `000.200.100`;
  `SHOPPER_VAL_BASEIMP = amount/1.15`, `SHOPPER_VAL_IVA = base*0.15`;
  `merchantTransactionId = {cart_id}_{timestamp}`; `identificationDocId =
  dni[0:10]`. Authorize: `GET {oppwa}{transactionURL}?entityId=...`, éxito
  `000.000.000`; `transactionURL` lo pone el frontend vía
  `updatePaymentSession` (`resourcePath`). Credenciales hardcodeadas
  **confirmadas** (entityId, Bearer, MID/TID/ECI/PSERV).
- DeUna: `POST {URL_DEUNA}/payment/request` con `x-api-key`/`x-api-secret`,
  `pointOfSale`, `qrType: dynamic`, `internalTransactionReference =
  cart_id[0:20]`; devuelve `transactionId`, `qr`, `deeplink`. **IVA
  calculado como `amount*0.85` / `amount*0.15`** (distinto a Datafast, que
  divide por 1.15) — unificar en `TaxCalculator`.
- Webhook DeUna: valida `Authorization`/`x-api-key` contra
  `MEDUSA_ACCESS_API_KEY`; busca el carrito con `LIKE
  '%internalTransactionReference%'`; con `APPROVED|SUCCESS` completa el
  carrito y captura la orden logueándose como admin con credenciales
  hardcodeadas (**confirmado**). En Laravel: `payments.gateway_reference` =
  `transactionId` y `metadata.internal_reference` indexado.
- Crédito B2B: sin pasarela; `saveInvoiceCreditoB2B` con `transactionId`
  aleatorio de 32 hex, `idAgenciaTransporte`, `nombreAgenciaTransporte`,
  `cuotas`, cliente `{typeId:1, clientId: ruc}`. Guía siempre `"000000"`.
- **Payload de factura al ERP (todos los gateways):** `id, paymentType,
  paymentBrand, total, subTotal, discountPercentage, discountAmount,
  currency, batchNo "01010000", idGuiaServientrega, costShipingCalculate,
  shipingDiscount, card{cardType,bin,last4Digits,holder,expiryMonth,
  expiryYear,acquirerCode "DTF"}, client{typeId,name,lastName,clientId,
  address}, cod_products[{codProducto, price(original_total), quantity}]`.
  Endpoints: `save_invoice/cf/parts` (Datafast), `cf1/parts` (DeUna),
  `datafast_b2b`, `deuna_b2b`, `cf1/credito_directo`. `InvoicePayload` se
  ampliará en la Fase 4 con `card` y `client.typeId`.
- Envío gratis: `costShipingCalculate = "4.30"` y `shipingDiscount = "4.30"`
  en B2C; `"0"` en crédito B2B. **Confirmado.**
- `payment-logger.ts`: eventos `initiate`, `authorize_ok`, `authorize_fail`,
  `servientrega_fail`, `invoice_fail` → mismo vocabulario en `integration_logs.event`.

## Módulo 05 — Servientrega (verificado)

- Cotización SOAP `cotizador_ser_recaudo.php`: `producto MERCANCIA PREMIER`,
  `origen GUAYAQUIL`, `destino "{city}-{province}"`, `valor_mercaderia =
  cart.total/100`, `piezas 1`, `peso = max(2, Σ (weight||1) × qty)`,
  dimensiones 1, credenciales `PRUEBA/s12345ABCDe/token` hardcodeadas,
  `rejectUnauthorized: false`, timeout 6 s, respuesta XML doblemente anidada
  (`Result` → `he.decode` → `ConsultarResult.flete`). Fallback 500 centavos.
  El servicio de fulfillment **suma 15% de IVA** al flete; la copia en
  `servientrega-guide.ts` no (pero esa copia no se usa: la llamada está
  comentada).
- Guía real: `POST https://swservicli.servientrega.com.ec:5052/api/guiawebs`
  con `login_creacion MOT.0928430156 / password massline`,
  `id_tipo_logistica 2`, `id_ciudad_origen 1`, `id_ciudad_destino =
  shipping_address.metadata.city_id`, `id_destinatario_ne_cl = dni`,
  `razon_social_desti_ne "Shineray"`, remitente fijo (id `Shineray`,
  `Shineray S.A.`, `Shineray Repuestos`, `Av. 14 S-E - Galo Pl. Lasso 13`,
  `09968767485`), `id_producto 2`, `contenido Repuestos`,
  `valor_mercancia = total`, `peso_fisico = totalWeight/10`. Devuelve `id`.
  Todo en `config/shineray.php: shipping.guide/sender`. **Confirmado.**
- El endpoint `181.39.87.158:8021` con `motorcycle.assembly/123456` solo
  está en `createFulfillment` con datos de prueba fijos: **no es el real**.
- Los procesadores B2B (`datafast-b2b`, `deuna-b2b`, `credito-b2b`) nunca
  crean guía. **Confirmado.**

## Módulo 06 — B2B (corregido)

- Endpoints ERP: `get_all_ruc_b2b_customer` → `{clientes:[{id, tipo_cliente,
  email, nombres, apellidos, celular, activo, direcciones}]}`;
  `politicas_b2b_ecommerce` → `[{COD_CLIENTEH, cuotas_info:[{es_activo,
  factor_credito, num_cuotas}]}]`; `get_info_transportista_ecommerce` →
  `{transportistas:[{RAZON_SOCIAL, RUC}]}`; `get_client_orders_ecommerce/{ruc}`
  (deuda); `parts_ecommerce_recomended_b2b` → `[{COD_PERSONA, COD_PRODUCTO}]`
  (recomendados = filtro simple por RUC, sin lógica adicional).
- `get-cuota-b2b`: recibe `cod_cliente` = `customer.metadata.cod_client` = `type_client` del
  cliente B2B; si es `"DI"` consulta `"DM"`. Es decir, las políticas son **por tipo de cliente**
  (implementado en `B2bClient::normalizePolicyType()`).
- `client-b2b-verify`: password fijo `shineray2024` (hash scrypt) **y además**
  envía email de reset de contraseña. En Laravel: `Customer.password = null`
  + invitación con token de reset; no hace falta el hash fijo.
- Bugs de atomicidad `.delete()`/`.save()` sin `await` y `clear()` sin
  transacción **confirmados**. `client_b2b.active` es `string` en Node.
- Rutas store B2B sin auth + CORS `*` **confirmado**; `sync-*-b2b` públicos
  **confirmado**.

## Módulo 08 — Notificaciones (verificado)

- `custom-ses.ts` con SES SDK; templates Handlebars `html.hbs` + `subject.hbs`
  en `data/templates/{order_placed, customer_password_reset,
  user_password_reset, abandoned_cart}`.
- BCC de `order.placed` hardcodeado a 4 direcciones `@massline.com.ec`
  (**confirmado**) → `SHINERAY_ORDER_PLACED_BCC`.

## Variables de entorno del sistema actual (para el `.env` nuevo)

`SHINERAY_API_URL/USER/PASSWORD`, `URL_DEUNA`, `API_KEY_DEUNA`,
`API_KEY_SECRECT_DEUNA`, `POINT_OF_SALE_DEUNAN`, `MEDUSA_ACCESS_API_KEY`
(webhook DeUna), `MEILISEARCH_HOST/API_KEY/ALLOW_DRAFTS`, `SES_REGION/
ACCESS_KEY_ID/SECRET_ACCESS_KEY/FROM/TEMPLATE_PATH`, `AWS_*`/`S3_*`,
`STORE_CORS`, `MEDUSA_JOBS`. Mapeo a `config/services.php` y
`config/shineray.php` ya hecho; Datafast y Servientrega no tenían variables
(estaban en código).
