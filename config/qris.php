<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Data Client (Partner yang Terhubung ke API Anda)
    |--------------------------------------------------------------------------
    |
    | Informasi tentang client yang diizinkan untuk mengakses API Anda
    | client_key : Adalah nama PJP sebagai identifier client
    | public_key : Kunci publik untuk verifikasi signature
    |
    */
    'clients' => [
        'bri' => [
            'client_id' => env('BRI_CLIENT_ID'),
            'client_secret' => env('BRI_CLIENT_SECRET'),
            'partner_id' => env('BRI_PARTNER_ID'),
            // 'public_key' => file_get_contents(storage_path('app/private/keys/public_key.pem')),
            // 'public_key' => file_get_contents(storage_path('app/private/keys/bri/droensolo.Pub.pem')),
            'public_key' => file_get_contents(storage_path('app/private/keys/bri/private_key_droenSoloBaru.pub.pem')),
            'private_key' => file_get_contents(storage_path('app/private/keys/private_key.pem')),
            'description' => 'Deskripsi Client'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Partner (Bank BRI dalam hal ini)
    |--------------------------------------------------------------------------
    |
    | Informasi tentang partner yang akan melakukan callback ke API Anda
    | partner_id : ID Partner yang diberikan oleh partner
    | public_key : Kunci publik untuk verifikasi signature
    |
    */
    'partners' => [
        'PARTNER_ID_FROM_BRI' => [
            'public_key' => '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
-----END PUBLIC KEY-----',
            'description' => 'Bank BRI'
        ],
    ],

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
