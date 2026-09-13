# Módulo: Envíos (Servientrega)

## Referencia en `medusa-shineray`

- `src/services/servientrega-fulfillment.ts` (194 líneas,
  `AbstractFulfillmentService`) — **6 de sus 12 métodos son stubs** que
  lanzan `Error("not implemented")` (cancelación, devoluciones, documentos/
  etiquetas) — hoy no existen ni en v1 ni en producción, no hay nada que
  "preservar" ahí, es funcionalidad ausente si se quiere paridad real.
    - `calculatePrice` (líneas ~77-141): cotización SOAP real, con reglas de
      negocio: peso mínimo forzado a 2kg si el total pesa menos (líneas
      ~99-101), peso de variante sin dato se asume 1kg (línea ~91), origen
      hardcodeado a `"GUAYAQUIL"` (línea ~106), dimensiones hardcodeadas a `1`
      (líneas ~110-112) — **preservar estas reglas**, no son bugs, son
      aproximaciones de negocio deliberadas (documentar el porqué si se puede
      confirmar con quien las escribió).
    - `createFulfillment` (líneas ~15-63): **usa datos de prueba hardcodeados,
      nunca corre en producción** — la creación de guía real ocurre en un
      archivo separado, ver abajo. No portar este método como si fuera el
      flujo real.
    - **Traga cualquier excepción y devuelve $5.00 fijo** en `calculatePrice`
      (líneas ~150-152) sin loguear nada — en Laravel, el equivalente debe
      **loguear el fallo** (a `payment_logs`/un log dedicado) antes de aplicar
      cualquier fallback, para no repetir el silencio actual.
- `src/utils/servientrega-guide.ts` (93 líneas) — **el código que sí corre en
  producción** para crear la guía real, invocado directo desde los payment
  processors de Datafast/DeUna (no desde el fulfillment service). Duplica
  casi literal la plantilla SOAP de cotización del archivo anterior, con
  **endpoint y credenciales distintas**:
    - Cotización: mismo WSDL SOAP `cotizador_ser_recaudo.php`.
    - Creación de guía real: `POST https://swservicli.servientrega.com.ec:5052/api/guiawebs`
      con `login_creacion: "MOT.0928430156"` / `password: "massline"`.
    - El `servientrega-fulfillment.ts` en cambio postea a
      `https://181.39.87.158:8021/api/guiawebs` (IP directa, puerto distinto,
      credenciales de prueba `motorcycle.assembly`/`123456`) — **este segundo
      endpoint parece no ser el real**; confirmar con Servientrega/negocio cuál
      de los dos es el vigente antes de implementar el cliente nuevo. No
      implementar ambos "por si acaso".
    - `peso_fisico: Number((totalWeight / 10).toFixed(2))` en la creación de
      guía real — **nota de inconsistencia**: esto divide el peso entre 10,
      mientras que `calculatePrice` no lo hace. Confirmar con negocio si es
      intencional (unidades distintas entre cotización y guía) antes de portar
      — no asumir que es un bug ni asumir que es correcto.
- Credenciales SOAP de cotización hardcodeadas en ambos archivos: usuario
  `PRUEBA`, password `s12345ABCDe`, token
  `1593aaeeb60a560c156387989856db6be7edc8dc220f9feae3aea237da6a951d` — mover
  todo a `.env`.
- `rejectUnauthorized: false` en 4 lugares entre los dos archivos — **no
  portar**; en Laravel, si el certificado de Servientrega da problemas de
  verificación, resolverlo con el CA correcto, no desactivando la
  verificación TLS.
- Remitente hardcodeado (Shineray S.A., dirección fija, teléfono fijo) en el
  payload de creación de guía dentro de los payment processors — esto sí es
  correcto preservarlo tal cual (es un dato real de la empresa, no un bug),
  pero debe vivir en config (`config/shineray.php`), no repetido en cada
  gateway de pago.

## Diseño en Laravel

- `app/Domain/Shipping/Providers/ServientregaProvider.php`, implementa
  `ShippingProviderContract` (`03-contratos.md`).
- `quote()` — traduce `calculatePrice`: mismo XML SOAP (usar el cliente HTTP
  de Laravel con headers manuales, igual que hoy, no hace falta una librería
  SOAP tipada porque la actual tampoco la usa), mismo parseo con una
  librería de XML de PHP (`simplexml_load_string` o `Spatie\ArrayToXml`
  inverso) reemplazando `xml2js`+`he.decode`. **Loguear cualquier fallo antes
  de aplicar el fallback**, a diferencia de hoy.
- `createGuide()` — traduce `servientrega-guide.ts` (el que sí es real), no
  `servientrega-fulfillment.ts::createFulfillment`. Usa el remitente fijo
  desde `config/shineray.php`.
- `cancelGuide()` — no existe implementación real hoy; documentar como
  pendiente si el negocio lo necesita (Servientrega puede o no soportar
  anulación vía este mismo canal SOAP/REST — confirmar).

## Acoplamiento cruzado a resolver

Hoy, "crear guía tras pago" vive dentro del payment processor de Datafast/
DeUna, no en el fulfillment service. En Laravel esto se convierte en el
listener `CreateServientregaGuideListener` (ver `01-arquitectura.md` y
`modulos/03-ordenes-checkout.md`), que depende del `ShippingProviderContract`
— nunca se llama al provider directo desde un gateway de pago.

Los flujos B2B (`datafast-b2b`, `deuna-b2b`) hoy **nunca** generan guía real
(usan placeholder `"000000"`) — en el nuevo esquema esto se modela como
`shipments.guide_number = null` + `status = 'pending'` explícito, no un
string mágico. Confirmar con negocio si el listener de creación de guía debe
saltarse para pedidos B2B (proceso manual) o si ahora sí debe intentarlo.

## Decisiones pendientes

- Cuál de los dos endpoints/credenciales de creación de guía es el real.
- Si `peso_fisico / 10` es intencional o un bug histórico.
- Si se implementa cancelación de guía.
- Si los pedidos B2B deben empezar a generar guía real automáticamente.
