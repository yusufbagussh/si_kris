<?php
return [
    'notify' => [
        'webhook' => [
            'url' => env('NOTIFY_WEBHOOK_URL'),
            'secret' => env('NOTIFY_WEBHOOK_SECRET'),
            'api_key' => env('NOTIFY_WEBHOOK_API_KEY'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Data Partner (Bank BRI dalam hal ini)
    |--------------------------------------------------------------------------
    |
    | Informasi tentang client yang diizinkan untuk mengakses API Anda
    */
    'partners' => [
        'bri' => [
            'base_url' => env('QRIS_BASE_URL', 'https://api.bri.co.id/'),
            'client_id' => env('QRIS_CLIENT_ID'),
            'client_secret' => env('QRIS_CLIENT_SECRET'),
            'partner_id' => env('QRIS_PARTNER_ID'),
            'channel_id' => env('QRIS_CHANNEL_ID'),
            'terminal_id' => env('QRIS_TERMINAL_ID'),
            'merchant_id' => env('QRIS_MERCHANT_ID'),
            'public_key' => file_get_contents(storage_path('app/private/keys/public_key.pem')), //Local
            'private_key' => file_get_contents(storage_path('app/private/keys/private_key.pem')),
            'webhook' => [
                'client_id' => env('BRI_CLIENT_ID'),
                'client_secret' => env('BRI_CLIENT_SECRET'),
                'partner_id' => env('BRI_PARTNER_ID'),
                'public_key' => file_get_contents(storage_path('app/private/keys/bri/droensolo.Pub.pem')), //Public Key BRI V1
                // 'public_key' => file_get_contents(storage_path('app/private/keys/bri/private_key_droenSoloBaru.pub.pem')), //Public Key BRI V1
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Client (Partner yang Terhubung ke API Anda)
    |--------------------------------------------------------------------------
    |
    | Informasi tentang partner yang akan melakukan callback ke API Anda
    */

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Token
    |--------------------------------------------------------------------------
    |
    | Konfigurasi terkait token
    |
    */
    'token' => [
        'expires_in' => 899, // 15 menit - 1 detik
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware tambahan yang digunakan untuk API ini
    |
    */
    'middleware' => [
        // tambahkan middleware jika diperlukan
    ],
];
