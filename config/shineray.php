<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moneda e impuestos
    |--------------------------------------------------------------------------
    |
    | Ecuador usa USD y un único IVA nacional. Todo monto se guarda en centavos
    | (integer). El IVA vive únicamente aquí: ningún módulo (búsqueda, pagos,
    | envíos, sync) debe repetir el literal 1.15 / 15%.
    |
    */

    'currency' => 'USD',

    'iva_rate' => (float) env('SHINERAY_IVA_RATE', 0.15),

    /*
    |--------------------------------------------------------------------------
    | Envíos
    |--------------------------------------------------------------------------
    |
    | Reglas de negocio de cotización/creación de guía (peso mínimo, peso por
    | defecto de una variante sin dato, origen fijo) y el remitente de la
    | empresa que va en cada guía. No son secretos, pero deben vivir en un
    | solo lugar en vez de repetirse en cada gateway de pago.
    |
    */

    'shipping' => [
        'origin_city' => env('SHINERAY_SHIPPING_ORIGIN_CITY', 'GUAYAQUIL'),
        'min_weight_kg' => (float) env('SHINERAY_SHIPPING_MIN_WEIGHT_KG', 2),
        'default_variant_weight_kg' => (float) env('SHINERAY_SHIPPING_DEFAULT_VARIANT_WEIGHT_KG', 1),
        'free_shipping_cost_cents' => (int) env('SHINERAY_FREE_SHIPPING_COST_CENTS', 430),
        'quote' => [
            'product' => 'MERCANCIA PREMIER',
            'timeout_seconds' => (int) env('SHINERAY_SHIPPING_QUOTE_TIMEOUT', 6),
            // El cotizador devuelve el flete sin IVA; hoy se le suma el IVA antes de cobrarlo.
            'add_iva' => true,
            'fallback_cost_cents' => (int) env('SHINERAY_SHIPPING_QUOTE_FALLBACK_CENTS', 500),
        ],
        'guide' => [
            'id_tipo_logistica' => 2,
            'id_ciudad_origen' => 1,
            'id_producto' => 2,
            'contenido' => 'Repuestos',
            'razon_social_destinatario' => 'Shineray',
            // Hoy la guía manda `peso_fisico = peso_total / 10` mientras la cotización manda el
            // peso sin dividir. Pendiente confirmar con negocio si es intencional.
            'weight_divisor' => (float) env('SHINERAY_SHIPPING_GUIDE_WEIGHT_DIVISOR', 10),
        ],
        'sender' => [
            'id' => env('SHINERAY_SENDER_ID', 'Shineray'),
            'business_name' => env('SHINERAY_SENDER_NAME', 'Shineray S.A.'),
            'first_name' => env('SHINERAY_SENDER_FIRST_NAME', 'Shineray'),
            'last_name' => env('SHINERAY_SENDER_LAST_NAME', 'Repuestos'),
            'address' => env('SHINERAY_SENDER_ADDRESS', 'Av. 14 S-E - Galo Pl. Lasso 13'),
            'city' => env('SHINERAY_SENDER_CITY', 'GUAYAQUIL'),
            'phone' => env('SHINERAY_SENDER_PHONE', '09968767485'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo
    |--------------------------------------------------------------------------
    */

    'catalog' => [
        // El sync de imágenes sube `{COD_PRODUCTO}.jpg` aquí; el producto apunta a esta URL.
        'thumbnail_base_url' => env('SHINERAY_THUMBNAIL_BASE_URL', 'https://shineray-public.s3.amazonaws.com/repuestos/'),
        'images_disk' => env('SHINERAY_IMAGES_DISK', 's3'),
        'images_path' => env('SHINERAY_IMAGES_PATH', 'repuestos'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronización con el ERP
    |--------------------------------------------------------------------------
    |
    | `enabled` reemplaza el MEDUSA_JOBS=true/false. `min_products_for_unpublish`
    | es la guarda de seguridad: si el ERP devuelve menos productos que esto, no
    | se despublica nada (respuesta vacía/corrupta).
    |
    */

    'erp_sync' => [
        'enabled' => (bool) env('SHINERAY_ERP_SYNC_ENABLED', false),
        'min_products_for_unpublish' => (int) env('SHINERAY_ERP_SYNC_MIN_PRODUCTS', 50),
        'products_cron' => env('SHINERAY_ERP_SYNC_PRODUCTS_CRON', '30 6,10,12,14,17 * * *'),
        'images_cron' => env('SHINERAY_ERP_SYNC_IMAGES_CRON', '0 6,10,12,14,17 * * *'),
        'b2b_cron' => env('SHINERAY_ERP_SYNC_B2B_CRON', '*/5 * * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrito abandonado
    |--------------------------------------------------------------------------
    |
    | Intervalos pendientes de confirmar contra la configuración real de
    | medusa-plugin-abandoned-cart (ver modulos/02-carrito-abandonado.md).
    |
    */

    'abandoned_cart' => [
        'enabled' => (bool) env('SHINERAY_ABANDONED_CART_ENABLED', false),
        // Minutos desde la creación/última actualización del carrito para cada email.
        // Hoy en producción NO hay intervalos configurados (el job sale sin hacer nada);
        // negocio debe definirlos antes de activar. Ej: "60,1440,4320".
        'intervals_minutes' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('SHINERAY_ABANDONED_CART_INTERVALS_MINUTES', ''))
        ))),
        // Si el carrito superó el último intervalo por más de esto, se marca completado.
        'max_overdue_minutes' => (int) env('SHINERAY_ABANDONED_CART_MAX_OVERDUE_MINUTES', 120),
        'set_as_completed_if_overdue' => true,
        // Solo se consideran carritos creados en los últimos N días.
        'days_to_track' => (int) env('SHINERAY_ABANDONED_CART_DAYS_TO_TRACK', 30),
        // No reenviar si el último email salió hace menos de esto.
        'recently_processed_minutes' => 3,
        // Carritos de bots de prueba que se excluyen por email.
        'excluded_email_pattern' => 'storebotmail',
        'subject' => '¡No dejes que tu moto espere!',
        'header' => '¡No dejes tus productos atrás! Completa tu compra hoy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'order_placed_bcc' => array_values(array_filter(
            explode(',', (string) env('SHINERAY_ORDER_PLACED_BCC', ''))
        )),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    */

    'storefront_url' => env('SHINERAY_STOREFRONT_URL', 'http://localhost:3000'),

];
