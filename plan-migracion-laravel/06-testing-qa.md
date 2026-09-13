# Testing y QA

## Punto de partida: cero red de seguridad

El código Node actual tiene prácticamente cero tests de comportamiento real
(un solo test de servicio placeholder, y un test que verifica _texto fuente_
del sync de productos, no ejecuta lógica). Esto significa que **no hay
especificación ejecutable** de qué es "correcto" — solo el código en sí y
esta auditoría. La migración a Laravel es la oportunidad de construir esa
red de seguridad desde cero, y debe tratarse como parte del trabajo de cada
módulo, no como una fase aparte al final.

## Regla no negociable

**Ningún módulo se da por terminado sin tests que cubran, como mínimo:**

- El "camino feliz" de cada método público del contrato correspondiente
  (`03-contratos.md`).
- Los casos de fallo del proveedor externo (timeout, respuesta con error,
  respuesta con forma inesperada) — usando `Http::fake()` para simular al
  proveedor, nunca pegándole de verdad en un test automatizado.
- Las reglas de negocio "no obvias" identificadas en cada módulo (ej.
  `allow_backorder` en catálogo, el fallback `deriveSubsistema` en búsqueda,
  el peso mínimo de 2kg en Servientrega) — estas reglas son exactamente las
  que se pierden en una reescritura si no quedan fijadas en un test.

## Estrategia por tipo de integración

- **Gateways de pago** (`modulos/04-pagos.md`): grabar fixtures de
  respuestas reales de OPPWa/DeUna (sanitizando cualquier dato sensible) y
  usarlas en `Http::fake()` — cubrir tanto el código de éxito exacto como
  al menos un código de "no éxito" para confirmar que el `PaymentResult` de
  fallo se arma bien.
- **Servientrega** (`modulos/05-envios-servientrega.md`): fixture del XML
  SOAP de respuesta real (doblemente anidado) para validar el parseo
  completo, incluyendo el caso de fallo (verificar que ahora sí se loguea,
  a diferencia del comportamiento actual).
- **ERP Shineray** (`modulos/09-erp-sync.md`): fixture de una respuesta real
  de `/api/all_parts` (los scripts `validate-v2-fields.js`/
  `verify-migration.js` del repo Node ya muestran la forma real) para probar
  el mapeo completo a `Product`/`ProductVariant`, incluyendo la dedup por
  `COD_PRODUCTO` y la regla de soft-delete con la guarda de `count >= 50`.
- **Jobs con `DB::transaction()`** (`modulos/09-erp-sync.md`): test explícito
  que fuerza un fallo a mitad de la sincronización y verifica que la tabla
  queda en su estado anterior (no vacía) — esto es literalmente la prueba de
  regresión del bug que estamos corrigiendo.

## Tipos de test por capa

- **Unit**: DTOs, mapeos puros (ej. el transformer de Meilisearch, el cálculo
  de IVA), sin tocar base de datos ni HTTP.
- **Feature/HTTP**: endpoints de `/api/store/*` y `/api/admin/*` con
  `RefreshDatabase`, verificando status codes, forma de respuesta, y que los
  guards de Sanctum bloquean correctamente lo que deben bloquear (ver
  checklist de `modulos/11-auth-seguridad.md`).
- **Integración de contrato**: para cada implementación de
  `PaymentGatewayContract`/`ShippingProviderContract`/`ErpClientContract`,
  una suite que corre los mismos casos de prueba contra la interfaz (útil
  también si en el futuro se agrega un segundo proveedor de pago o envío).

## QA manual antes del corte

Además de los tests automatizados, antes de la Fase 10
(`05-migracion-datos-corte.md`) se necesita una pasada manual de QA sobre
staging con datos migrados, cubriendo al menos:

- Checkout completo B2C con Datafast y con DeUna (tarjeta real de prueba en
  sandbox).
- Checkout completo B2B con crédito.
- Generación de guía Servientrega real en sandbox (si el proveedor lo
  permite) o al menos contra el endpoint confirmado como canónico.
- Flujo de carrito abandonado (forzando el tiempo de inactividad en
  staging).
- Sync de productos/B2B contra el ERP real (en un ambiente que no afecte
  producción) verificando conteos antes/después.
