# Módulo: Pagos (Datafast, DeUna, Crédito B2B)

## Referencia en `medusa-shineray`

- `src/services/datafast-payment-processor.ts` (890 líneas, B2C) y
  `datafast-b2b-payment-processor.ts` (631 líneas, B2B) — ambos
  `AbstractPaymentProcessor`. De los 10 métodos del contrato v1, **solo
  `initiatePayment` y `authorizePayment` tienen lógica real**; los otros 8
  (`capturePayment`, `cancelPayment`, `refundPayment`, `retrievePayment`,
  `updatePayment`, `deletePayment`, `getPaymentStatus`, `updatePaymentData`)
  son no-ops o casi-stubs hoy — **no hay reembolso/cancelación real que
  preservar**, es una decisión de producto pendiente si se implementan en
  Laravel o se documentan como no soportado.
- `src/services/deuna-payment-processor.ts` (522 líneas) y
  `deuna-b2b-payment-processor.ts` (336 líneas) — mismo patrón que Datafast,
  pasarela distinta.
- `src/services/credito-b2b-payment-processor.ts` (293 líneas) — "pago" a
  crédito B2B sin pasarela externa, integra `shineray-billing.ts` para
  registrar cliente/factura directo.
- `src/config/datafast-config.ts` — **código muerto**, no se importa desde
  ningún lado (confirmado). No portar, solo sirve como evidencia de que hubo
  credenciales de sandbox alguna vez.
- `src/utils/payment-logger.ts` — logger a archivo local
  `logs/payments.jsonl`. En Laravel se reemplaza por la tabla `payment_logs`
  (ver `02-modelo-datos.md`).
- `src/utils/shineray-billing.ts` — cliente HTTP de facturación al ERP
  (`getInfoClients`, `saveInfoClients`, `saveInvoiceDatafast[B2B]`). Esta
  lógica es agnóstica de Medusa y se porta casi literal al
  `ErpClientContract` (ver `03-contratos.md` y `modulos/09-erp-sync.md`).

### Credenciales hardcodeadas a NO portar como texto plano (mover a `.env`)

- Datafast: `entityId` (línea ~95/90), Bearer token OPPWa (línea ~137-138/132-133),
  parámetros `SHOPPER_MID/TID/ECI/PSERV` (líneas ~115-118/110-113) —
  duplicados idénticos en ambos archivos.
- Password de creación de guía Servientrega embebido en el payload de pago:
  `login_creacion: "MOT.0928430156"` / `password: "massline"` (línea ~536-537
  del processor principal) — mover a `.env`, usarlo desde el
  `ShippingProviderContract`, no desde el gateway de pago.

### Lógica de negocio a preservar (agnóstica de Medusa, portar casi literal)

- Cálculo de IVA/subtotal desde el monto total: `amountSubTotal = amount/1.15`,
  `amountIva = amountSubTotal * 0.15` — **usar `config('shineray.iva_rate')`
  en vez de literales**.
- Mapeo de campos de cliente/dirección hacia los parámetros de OPPWa
  (`customer.*`, `billing.*`, `shipping.*`, `cart.items[]` en notación de
  query-string).
- Validación de éxito por código de respuesta exacto: `"000.200.100"` en
  initiate, `"000.000.000"` en authorize — **nota de riesgo**: OPPWa tiene
  decenas de códigos de "pendiente"/"en revisión" (3-DS) que el código actual
  trata todos como fallo genérico; decidir si en Laravel se amplía el manejo
  de códigos o se mantiene la simplificación actual (afecta UX en pagos con
  3-D Secure).
- Costo de envío gratis hardcodeado a `"4.30"` cuando aplica descuento de
  envío gratis, con la llamada real al cotizador de Servientrega
  **deliberadamente comentada al lado** en el código actual — decisión
  pendiente, ver `00-lineamientos.md`.
- Recalculo manual de reglas de descuento (`free_shipping`, `percentage`,
  `fixed`) leyendo `cart.discounts[].rule` — en Laravel esto se reemplaza
  por el modelo `discounts`/`cart_discounts` de `02-modelo-datos.md`, ya
  calculado antes de llegar al gateway (el gateway no debería recalcular
  descuentos, solo leer el total ya resuelto por `CheckoutService`).
- Postal code fijo `"000000"` — preservar (no es un bug, ver
  `02-modelo-datos.md`).
- **Antipatrón a NO portar**: el processor actual actualiza el carrito
  llamando a la propia API de Medusa por HTTP (`medusa-js`,
  `medusa.carts.update(...)`) en vez de usar el servicio inyectado — en
  Laravel esto se resuelve escribiendo directo en el modelo `Order`/`Cart`
  desde el `CheckoutService`, sin ninguna llamada HTTP de vuelta a sí mismo.

## Diseño en Laravel

- `app/Domain/Payments/Gateways/DatafastGateway.php` (y `DatafastB2bGateway`,
  `DeunaGateway`, `DeunaB2bGateway`, `CreditoB2bGateway`), todas implementan
  `PaymentGatewayContract` (`03-contratos.md`).
- `initiate()` y `authorize()` son los únicos con lógica de integración real
  — portar la construcción de payload y el parseo de respuesta casi
  literal (traducido de axios a `Http::` de Laravel).
- `capture()`/`cancel()`/`refund()`/`status()` — implementar de verdad solo si
  el negocio lo requiere; si no, documentar explícitamente
  `throw new PaymentOperationNotSupportedException` en vez de un `return {}`
  silencioso como hoy (mejor que un stub silencioso: falla ruidoso si algo
  intenta usarlo).
- El bloque de post-autorización (guía + factura) **no vive en el gateway**:
  el gateway solo autoriza el pago y retorna `PaymentResult`; el
  `CheckoutService` dispara `PaymentAuthorized` y los listeners en cola hacen
  el resto (ver `modulos/03-ordenes-checkout.md` y `01-arquitectura.md`).

## Decisiones pendientes

- ¿Se implementan reembolsos/cancelaciones reales contra OPPWa/DeUna, o se
  documenta como limitación conocida igual que hoy?
- Manejo de códigos de respuesta "pendiente"/3-DS de OPPWa: ¿se amplía o se
  mantiene la simplificación binaria actual?
- Confirmar con Datafast/DeUna si hay credenciales de sandbox reales
  disponibles para desarrollo (el `datafast-config.ts` actual es código
  muerto, no confirmar que esas credenciales de sandbox sigan siendo válidas
  sin probarlas primero).
