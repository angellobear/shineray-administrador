# Módulo: Órdenes y checkout

## Referencia en `medusa-shineray`

No hay un archivo único de "checkout" en el código Node — el flujo vive
repartido entre el core de Medusa (que no se audita aquí, es comportamiento
estándar de framework) y los payment processors, que son los que de verdad
definen qué pasa al confirmar una orden:

- `src/services/datafast-payment-processor.ts` — método `authorizePayment`,
  líneas ~424-769. Ver `modulos/04-pagos.md` para el detalle completo.
- `src/services/deuna-payment-processor.ts` — equivalente para DeUna.
- `src/services/credito-b2b-payment-processor.ts` — flujo de crédito B2B sin
  pasarela externa.

El patrón a extraer de estos archivos (no el código en sí, la **secuencia**):

1. Validar pago con el gateway.
2. Crear guía de envío (Servientrega) — con tolerancia a fallo.
3. Actualizar datos de envío en la orden/carrito.
4. Facturar en el sistema de bodega Shineray — con tolerancia a fallo.
5. Confirmar la orden como pagada independientemente de si (2) o (4)
   fallaron (decisión de negocio actual, ver `00-lineamientos.md` para la
   pregunta pendiente de si se mantiene tal cual o se mejora con reintentos).

## Esquema de datos

`orders`, `order_items`, `addresses` (polimórfica) — ver `02-modelo-datos.md`.

## Diseño en Laravel

- `app/Domain/Orders/Services/CheckoutService.php` — orquesta:
    1. Congela el carrito en una orden (`cart_id` → nueva fila en `orders` +
       copia de `cart_items` a `order_items` con snapshot de precio).
    2. Llama al `PaymentGatewayContract` correspondiente para `authorize()`.
    3. Si el pago es exitoso, marca la orden `paid` y dispara
       `event(new PaymentAuthorized($order, $payment))`.
    4. Los listeners de ese evento (en cola, ver `01-arquitectura.md`) se
       encargan de guía + factura + notificación — **no** el `CheckoutService`
       directamente. Esto es lo que reemplaza el acoplamiento actual dentro del
       payment processor.
- El estado de la orden (`pending`,`paid`,`fulfilled`,`canceled`,`refunded`)
  se modela como enum simple + eventos de transición
  (`OrderPlaced`,`OrderFulfilled`,`OrderCanceled`), no como una máquina de
  estados compleja — no hace falta replicar el motor de estados de Medusa
  para el volumen de este negocio.

## Decisiones pendientes

- Confirmar si existe algún flujo de checkout que hoy NO pase por un payment
  processor custom (ej. `medusa-payment-manual`, usado en `medusa-config.js`
  como plugin activo) — si se usa en algún caso real (pruebas internas,
  pedidos manuales), hay que decidir su equivalente en Laravel (ej. una orden
  creada directo desde el admin sin pasar por gateway).
