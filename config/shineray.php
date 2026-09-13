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
        'quote_fallback_cost_cents' => (int) env('SHINERAY_SHIPPING_QUOTE_FALLBACK_CENTS', 500),
        'sender' => [
            'name' => env('SHINERAY_SENDER_NAME', 'Shineray S.A.'),
            'address' => env('SHINERAY_SENDER_ADDRESS', ''),
            'city' => env('SHINERAY_SENDER_CITY', 'GUAYAQUIL'),
            'phone' => env('SHINERAY_SENDER_PHONE', ''),
            'dni' => env('SHINERAY_SENDER_DNI', ''),
        ],
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
        'inactivity_minutes' => (int) env('SHINERAY_ABANDONED_CART_INACTIVITY_MINUTES', 60),
        'max_emails' => (int) env('SHINERAY_ABANDONED_CART_MAX_EMAILS', 3),
        'retry_interval_minutes' => (int) env('SHINERAY_ABANDONED_CART_RETRY_MINUTES', 1440),
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
