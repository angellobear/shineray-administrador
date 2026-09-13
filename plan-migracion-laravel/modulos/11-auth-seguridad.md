# Módulo: Autenticación y correcciones de seguridad

Este módulo no es "opcional" ni "limpieza posterior" — son correcciones que
deben aplicarse **durante** la construcción de cada módulo correspondiente,
listadas aquí como checklist consolidado para no perder ninguna.

## Hallazgos confirmados en `medusa-shineray` (con evidencia de línea) que NO se deben portar tal cual

1. **Credenciales OPPWa/Datafast hardcodeadas en texto plano**, duplicadas en
   `datafast-payment-processor.ts` y `datafast-b2b-payment-processor.ts`
   (`entityId`, Bearer token, `SHOPPER_MID/TID/ECI/PSERV`). → `.env` +
   `config/services.php`. Ver `modulos/04-pagos.md`.
2. **Credenciales SOAP de Servientrega hardcodeadas** (usuario `PRUEBA`,
   password `s12345ABCDe`, token fijo), + password de creación de guía real
   (`massline`) embebido en los payment processors. → `.env`. Ver
   `modulos/05-envios-servientrega.md`.
3. **`rejectUnauthorized: false`** (4 instancias, deshabilita validación TLS
   hacia Servientrega). → nunca deshabilitar verificación TLS; resolver el
   problema de certificado de raíz si existe.
4. **Credenciales de admin hardcodeadas en `deuna-webhook`**
   (`jimmy@blubear.io` / `12345678`) usadas para pedir un token admin desde
   dentro del webhook. → el webhook de DeUna en Laravel debe autenticar la
   petición entrante (firma/secreto compartido del webhook, ya existe algo
   similar hoy vía `MEDUSA_ACCESS_API_KEY`) sin necesitar loguearse como
   admin para nada — si necesita escribir en la orden, lo hace directo vía
   Eloquent, no vía una llamada HTTP a sí mismo autenticada como admin.
5. **Hash de password fijo para clientes B2B autocreados**
   (`client-b2b-verify`). → ver `modulos/06-b2b.md`, decisión pendiente de
   negocio sobre el flujo de reemplazo.
6. **CORS abierto (`*`) manual en casi todas las rutas `store/*` B2B**
   (`get-address`, `get-cuota-b2b`, `get-transportistas-b2b`,
   `client-b2b-verify`, `shineray/stock`). → en Laravel, CORS se configura
   una sola vez de forma centralizada (`config/cors.php`) restringido a los
   orígenes reales del frontend — nunca `Access-Control-Allow-Origin: *`
   puesto a mano por endpoint.
7. **Endpoints de sync B2B expuestos sin autenticación** que ejecutan
   operaciones destructivas (`sync-transportista-b2b`, etc.) → en Laravel no
   existen como rutas públicas en absoluto (ver `modulos/09-erp-sync.md`).
8. **Shineray ERP sobre HTTP plano** (`http://wspback1.massline.com.ec:5000`)
   — fuera de nuestro control (es el ERP del proveedor), pero se documenta
   como riesgo aceptado y conocido, no se "arregla" desde este lado; si se
   puede negociar HTTPS con el proveedor del ERP, es una mejora externa al
   alcance de este backend.
9. **`ssl: { rejectUnauthorized: false }`** en la config de conexión a
   Postgres cuando la DB no es localhost — evaluar si el proveedor de DB en
   el nuevo despliegue permite usar un CA válido en vez de desactivar la
   verificación.

## Diseño de autenticación en Laravel

- **Sanctum**, dos guards:
    - `admin` — staff, protege todas las rutas `/api/admin/*` y el panel
      Filament.
    - `customer` — clientes B2C/B2B, protege `/api/store/*` que requieran
      identidad (carrito propio, direcciones, cuotas B2B, etc.); las rutas de
      catálogo/búsqueda pueden quedar públicas (equivalente a hoy).
- Webhooks externos (DeUna) se autentican por **firma/secreto compartido**
  verificado en middleware dedicado, nunca pidiendo un token de admin desde
  dentro del handler.
- Todos los secretos (credenciales de gateways, ERP, AWS) viven en `.env`,
  nunca en código fuente ni en archivos de config commiteados con valores
  reales — `config/services.php` solo referencia `env(...)`.

## Checklist de verificación antes de dar por cerrado cada módulo

- [ ] ¿Alguna credencial quedó como literal en el código en vez de `env()`?
- [ ] ¿Alguna ruta `store/*` o `admin/*` quedó sin guard de Sanctum cuando
      debería tenerlo?
- [ ] ¿Algún CORS quedó abierto a `*` en vez de resuelto por
      `config/cors.php`?
- [ ] ¿Alguna llamada HTTPS saliente deshabilita verificación de certificado?
