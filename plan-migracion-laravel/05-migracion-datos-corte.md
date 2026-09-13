# Migración de datos existentes y estrategia de corte

## Por qué no hay migración automática

Medusa v1 usa TypeORM sobre Postgres con un esquema propio (tablas `product`,
`product_variant`, `cart`, `order`, `customer`, más las 4 tablas custom
`client_b2b`, `policies_b2b`, `transportistas_b2b`, `onboarding_state`). El
esquema destino (`02-modelo-datos.md`) es deliberadamente distinto (más
simple, sin las tablas de multi-región de Medusa, con FKs reales donde hoy no
las hay). No tiene sentido intentar correr Eloquent contra el esquema viejo:
se hace un **ETL de una sola pasada**.

## Estrategia recomendada: script de ETL dedicado, no un comando Artisan de producción

1. **Extracción**: un script (puede ser un comando Artisan de un solo uso,
   `migrate:legacy-data`, que luego se elimina/archiva) que lee directo de
   la base Postgres de Medusa v1 (conexión de solo lectura, separada de la
   conexión principal de la app nueva) usando una segunda conexión de
   Eloquent apuntando al mismo Postgres pero con el `schema`/tablas viejas.
2. **Transformación**: mapeo tabla por tabla:
   - `product`/`product_variant` (Medusa) → `products`/`product_variants`
     (nuevo) — cuidado con la forma de `metadata` (Medusa guarda jsonb,
     mapeo debería ser casi directo).
   - `cart` (con las 4 columnas `abandoned_*`) → `carts` — directo.
   - `order`/`line_item` → `orders`/`order_items`.
   - `customer` → `customers`.
   - `client_b2b`, `policies_b2b`, `transportistas_b2b` → tablas homónimas
     nuevas, **poblando las FKs que hoy no existen** (`client_b2b.customer_id`
     buscando el customer por email; `policies_b2b.client_b2b_id` buscando
     por el `cod_cliente` actual contra el `id_client` del nuevo
     `client_b2b`).
   - `onboarding_state` — opcional, ver `02-modelo-datos.md`.
   - Historial de pagos: si se decide preservar el histórico de
     `logs/payments.jsonl` (archivo local), importarlo a `payment_logs` como
     parte del ETL, no descartarlo silenciosamente.
3. **Carga**: inserciones en lote (`insert()` masivo, no `save()` uno por
   uno) dentro de `DB::transaction()` por tabla, con validación de conteo
   final (comparar `count()` origen vs. destino) antes de considerar cada
   tabla migrada.
4. **Verificación**: un comando de verificación que compare muestras
   aleatorias de órdenes/clientes entre ambos sistemas (totales, emails,
   SKUs) antes de dar luz verde al corte.

## Estrategia de corte (cutover)

No se recomienda correr ambos backends contra la misma base de datos en
paralelo (los esquemas son incompatibles). En cambio:

1. **Ambiente de staging con datos ya migrados** (vía el ETL) corriendo
   contra sandbox de Datafast/DeUna/Servientrega, validado de punta a punta
   por QA manual antes de cualquier fecha de corte.
2. **Congelar escritura en el Medusa v1 de producción** (ventana corta,
   anunciada) → correr el ETL final incremental (solo lo que cambió desde
   el último ETL de prueba) → apuntar DNS/frontend al backend Laravel nuevo.
3. **Mantener Medusa v1 accesible en solo lectura** por un período de
   respaldo (semanas) por si hace falta consultar un pedido histórico que el
   ETL no haya migrado correctamente.
4. Los **jobs de sync ERP** (`modulos/09-erp-sync.md`) deben apagarse en el
   sistema viejo en el mismo instante en que se activan en el nuevo — nunca
   ambos corriendo a la vez contra el mismo ERP (riesgo de datos duplicados
   o inconsistentes en Shineray/Massline).

## Coordinación con el frontend

El frontend Next.js actual consume la API `/store/...` de Medusa v1. El
corte de backend implica que, o el frontend se adapta a la nueva forma de
API antes del corte (recomendado, en paralelo a la Fase 4 en adelante de
`04-fases-y-cronograma.md`), o el backend Laravel expone una capa de
compatibilidad temporal con la forma actual — evaluar el costo de cada
opción con el equipo de frontend antes de fijar fecha de corte.

## Riesgos específicos de esta fase

- Órdenes con `metadata` inconsistente entre B2C y B2B (campos distintos en
  cada uno, ver `02-modelo-datos.md`) — el mapeo debe manejar ambos casos
  explícitamente, no asumir una forma única.
- Clientes B2B sin `customer` asociado hoy (relación solo por email) —
  decidir qué hacer si el email no matchea ningún customer existente
  (¿crear uno nuevo en el ETL? ¿marcar para revisión manual?).
- Guías de envío con el placeholder `"000000"` — al migrar a `shipments`,
  convertir explícitamente a `guide_number = null` (ver
  `modulos/05-envios-servientrega.md`), no arrastrar el string mágico.
