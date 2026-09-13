<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'datafast' => [
        'base_url' => env('DATAFAST_BASE_URL', 'https://eu-test.oppwa.com'),
        'entity_id' => env('DATAFAST_ENTITY_ID'),
        'entity_id_b2b' => env('DATAFAST_ENTITY_ID_B2B'),
        'bearer_token' => env('DATAFAST_BEARER_TOKEN'),
        'shopper_mid' => env('DATAFAST_SHOPPER_MID'),
        'shopper_tid' => env('DATAFAST_SHOPPER_TID'),
        'shopper_eci' => env('DATAFAST_SHOPPER_ECI'),
        'shopper_pserv' => env('DATAFAST_SHOPPER_PSERV'),
        'timeout' => (int) env('DATAFAST_TIMEOUT', 30),
    ],

    'deuna' => [
        'base_url' => env('DEUNA_BASE_URL'),
        'api_key' => env('DEUNA_API_KEY'),
        'api_secret' => env('DEUNA_API_SECRET'),
        'point_of_sale' => env('DEUNA_POINT_OF_SALE'),
        // Clave que DeUna manda en `Authorization`/`x-api-key` al webhook (hoy MEDUSA_ACCESS_API_KEY).
        'webhook_secret' => env('DEUNA_WEBHOOK_SECRET'),
        'timeout' => (int) env('DEUNA_TIMEOUT', 30),
    ],

    'servientrega' => [
        'quote_url' => env('SERVIENTREGA_QUOTE_URL', 'https://servientrega-ecuador.appsiscore.com:443/app/ws/cotizador_ser_recaudo.php'),
        'quote_user' => env('SERVIENTREGA_QUOTE_USER'),
        'quote_password' => env('SERVIENTREGA_QUOTE_PASSWORD'),
        'quote_token' => env('SERVIENTREGA_QUOTE_TOKEN'),
        'guide_url' => env('SERVIENTREGA_GUIDE_URL', 'https://swservicli.servientrega.com.ec:5052/api/guiawebs'),
        'guide_login' => env('SERVIENTREGA_GUIDE_LOGIN'),
        'guide_password' => env('SERVIENTREGA_GUIDE_PASSWORD'),
        'timeout' => (int) env('SERVIENTREGA_TIMEOUT', 30),
    ],

    'shineray_erp' => [
        'base_url' => env('SHINERAY_ERP_BASE_URL'),
        'username' => env('SHINERAY_ERP_USERNAME'),
        'password' => env('SHINERAY_ERP_PASSWORD'),
        'token_ttl_seconds' => (int) env('SHINERAY_ERP_TOKEN_TTL', 0),
        'timeout' => (int) env('SHINERAY_ERP_TIMEOUT', 60),
    ],

];
