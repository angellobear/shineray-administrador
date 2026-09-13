# Migración Shineray: Medusa v1 (Node) → Laravel a medida

## Qué es esto

Este es el plan de migración completa del backend de e-commerce de Shineray Ecuador,
desde el Medusa v1 actual (Node/TypeScript/TypeORM) hacia un backend Laravel
construido a medida — **no** una réplica de la arquitectura de Medusa (sin su
sistema de módulos/plugins, sin multi-región, sin motor de workflows). Se toma
Medusa como *referencia de dominio* (qué conceptos existen: producto, variante,
carrito, orden, carrito abandonado, cliente B2B, etc.) y como *especificación
funcional* de lo que ya funciona en producción (integraciones con Datafast,
Servientrega, el ERP Shineray/Massline). No es una migración automática de
código: es una reescritura completa, informada por una auditoría línea por
línea del código Node actual.

## Los dos repositorios

- **Origen (solo lectura, referencia)**: `jimovi1994/medusa-shineray`, rama
  `release/v2`. Medusa v1.20.6 puro. Contiene la implementación actual
  en producción — es la fuente de verdad de "qué debe hacer" cada pieza.
- **Destino (donde se escribe código nuevo)**: `angellobear/shineray-administrador`.
  Vacío al día de escribir esto. Aquí vive el backend Laravel nuevo y esta
  carpeta `plan-migracion-laravel/`.

**Regla para cualquier agente que trabaje en un módulo**: no cargues los dos
repos completos en contexto. Para implementar un módulo:
1. Lee este archivo (`00-lineamientos.md`) una sola vez, al empezar.
2. Lee el archivo del módulo específico en `plan-migracion-laravel/modulos/`.
3. Abre **solo** los archivos de referencia que ese módulo lista explícitamente
   dentro de `medusa-shineray` (con ruta y, cuando aplica, número de línea) —
   no explores el resto del repo Node salvo que el módulo te lo pida.
4. Implementa en `shineray-administrador` siguiendo la estructura de carpetas
   objetivo que indica `01-arquitectura.md` y el propio módulo.
5. Si encuentras algo en el código Node que el módulo no documenta y que
   parece relevante, anótalo en el módulo (no lo asumas ni lo inventes) y
   pregunta antes de decidir un comportamiento nuevo.

Todos los hallazgos de auditoría citados en estos documentos (líneas exactas,
credenciales, bugs, hardcodes) vienen de un análisis previo completo de
`medusa-shineray` hecho antes de escribir este plan — están verificados, no
son suposiciones.

## Decisiones de alcance ya tomadas (no reabrir sin discutirlo)

- **Una sola moneda: USD.** Ecuador usa dólar oficialmente — no hay
  multi-moneda ni multi-región. Esto elimina de raíz toda la complejidad de
  `money_amount`/price lists por región que tiene Medusa. Los precios son
  enteros en centavos, sin `currency_code` variable (se guarda un campo fijo
  `"USD"` por claridad histórica, no por necesidad funcional).
- **Un solo país/tax rate: Ecuador, IVA 15%.** No hay motor de impuestos por
  región. El IVA se centraliza en **un solo lugar de configuración**
  (`config/shineray.php: iva_rate`), a diferencia del código Node actual donde
  `1.15`/`15%` está hardcodeado y duplicado en al menos 4 archivos distintos
  (Meilisearch transformer, Datafast processor, Servientrega quote, y el
  cálculo de precio de sync-products). Esta migración es la oportunidad de
  arreglar eso.
- **No se replica el sistema de módulos/plugins de Medusa.** Se usa
  arquitectura Laravel idiomática: Eloquent, Service classes, Contracts
  (interfaces PHP), Events/Listeners para desacoplar efectos secundarios,
  Jobs/Queues para trabajo asíncrono, Scheduler para cron.
- **No se replica el motor de Workflows de Medusa v2** (no vamos a Medusa v2,
  vamos a Laravel). Donde Medusa usaría un workflow (ej. "tras pago exitoso:
  crear guía + facturar + notificar"), Laravel usa **eventos + listeners en
  cola**, que da el mismo desacople y capacidad de reintento sin traer una
  dependencia nueva.
- **Todo integración externa se define primero como interfaz (contrato PHP)**,
  con la implementación concreta inyectada por el container de Laravel. Ver
  `03-contratos.md`. Esto es no negociable: ningún servicio externo
  (pasarela de pago, Servientrega, ERP Shineray, Meilisearch) se llama nunca
  directo desde un controlador o un job — siempre a través de su contrato.
- **Se corrigen, no se replican, los bugs de atomicidad conocidos** (el
  `.clear()` sin transacción de `transportistas_b2b`, los `.save()`/`.delete()`
  sin `await` en sync de clientes/políticas B2B) — en Laravel esto se resuelve
  con `DB::transaction()` explícito, documentado módulo por módulo.
- **Se corrigen, no se replican, las credenciales hardcodeadas** (Datafast
  OPPWa, Servientrega, admin de `deuna-webhook`, password fijo de B2B) — todas
  van a `.env`/config, nunca a código fuente. Ver `modulos/11-auth-seguridad.md`.
- **Cero tests hoy → tests desde el día uno.** El código Node actual tiene
  prácticamente cero cobertura de comportamiento real. En Laravel, cada
  contrato de integración externa se prueba con `Http::fake()` (Datafast,
  Servientrega, ERP Shineray, Meilisearch) desde el primer módulo que se
  construya. Ver `06-testing-qa.md`.

## Decisiones pendientes (requieren que las resuelva el negocio/PM, no el código)

Estas ya se identificaron en la auditoría del código Node como comportamientos
ambiguos. **No se deben resolver "a criterio del agente"** al portar — cada
módulo las señala en su sección "Decisiones pendientes":
- Servientrega tiene dos endpoints/credenciales de creación de guía distintos
  y activos simultáneamente en el código actual — hay que confirmar cuál es
  el real con el proveedor antes de implementar el nuevo cliente.
- Los flujos B2B (Datafast B2B, DeUna B2B) nunca generan guía real de
  Servientrega hoy (usan un placeholder) — confirmar si es un proceso manual
  intencional o una brecha a cerrar.
- El costo de envío gratis hardcodeado a $4.30 en vez de cotizar real — decidir
  si se mantiene o se corrige a cotización dinámica.
- Si un fallo en la creación de guía o en la facturación al ERP debe seguir
  sin bloquear la confirmación del pago (comportamiento actual) o si ahora,
  con colas y reintentos disponibles en Laravel, se prefiere una estrategia
  de reintento con alerta en vez de fallo silencioso.

## Índice de documentos

- `01-arquitectura.md` — stack Laravel, estructura de carpetas, paquetes.
- `02-modelo-datos.md` — esquema de base de datos completo.
- `03-contratos.md` — todas las interfaces PHP del sistema.
- `04-fases-y-cronograma.md` — orden de construcción, dependencias, estimado.
- `05-migracion-datos-corte.md` — ETL de datos existentes y estrategia de corte.
- `06-testing-qa.md` — estrategia de pruebas.
- `07-verificacion-codigo.md` — hallazgos verificados contra el código Node real.
- `modulos/01-productos-catalogo.md`
- `modulos/02-carrito-abandonado.md`
- `modulos/03-ordenes-checkout.md`
- `modulos/04-pagos.md`
- `modulos/05-envios-servientrega.md`
- `modulos/06-b2b.md`
- `modulos/07-busqueda-meilisearch.md`
- `modulos/08-notificaciones.md`
- `modulos/09-erp-sync.md`
- `modulos/10-admin-panel.md`
- `modulos/11-auth-seguridad.md`
