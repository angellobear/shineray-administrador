# Módulo: Notificaciones (email)

## Referencia en `medusa-shineray`

- `src/services/custom-ses.ts` (651 líneas, `AbstractNotificationService`) —
  reemplaza los plugins comentados `medusa-plugin-mailjet`/`medusa-plugin-ses`.
  Compila plantillas Handlebars desde disco (`SES_TEMPLATE_PATH`), mapea
  eventos a plantillas: `order.placed`, `customer.password_reset`,
  `user.password_reset`, `abandoned.cart`. Tiene:
    - BCC hardcodeado a 4 direcciones internas en `order.placed` (líneas
      ~21-26) — **mover a `.env`/config**, no hardcodear direcciones internas
      en código.
    - `resendNotification` con lógica de recompilación/parcheo de totales —
      confirmar si este flujo de reenvío manual desde el admin se sigue
      necesitando antes de portarlo (puede ser una función de admin poco usada).
    - Resuelve dependencias directo de `container` (`container.orderService`)
      en vez de destructuring tipado — sin equivalente necesario en Laravel,
      aquí simplemente se inyectan los modelos/servicios que se necesiten.
- `src/subscribers/notification.ts` — suscribe `custom-ses` a los 3 eventos
  de arriba sobre el `NotificationService` core de Medusa.
- `data/templates/*.hbs` — las plantillas Handlebars en sí
  (`order_placed`, `customer_password_reset`, `user_password_reset`,
  `abandoned_cart`).

## Diseño en Laravel

- No se define un contrato propio (ver `03-contratos.md`) — se usa
  `Illuminate\Notifications\Notification` + Mailables nativos.
- `app/Domain/Notifications/Mail/OrderPlacedMail.php`,
  `AbandonedCartMail.php`, y las de reset de password (Laravel Fortify/
  Breeze/Sanctum ya traen su propio flujo de password reset — evaluar si se
  reusa el de Laravel en vez de reimplementar `customer.password_reset`/
  `user.password_reset` a mano).
- Las plantillas `.hbs` se traducen a Blade Markdown Mailables
  (`php artisan make:mail OrderPlacedMail --markdown=...`), preservando el
  contenido y las variables usadas en cada una.
- Se disparan desde los eventos de dominio ya definidos en otros módulos
  (`PaymentAuthorized` → `OrderPlacedMail`, el job de abandoned-cart →
  `AbandonedCartMail`) — no desde un subscriber genérico que escuche "todos
  los eventos posibles", como hace Medusa.
- El BCC interno de `order.placed` se configura en
  `config/shineray.php: notifications.order_placed_bcc` (array de emails).

## Decisiones pendientes

- Confirmar si `resendNotification` (reenvío manual con recálculo de
  totales) es una función usada activamente por el equipo de soporte/admin
  antes de decidir si se porta como una acción del panel Filament o se
  descarta.
