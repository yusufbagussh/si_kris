<?php
return [
    'soba' => [
        env('SOBA_ID') => [
            'secret_key' => env('SOBA_SECRET_KEY'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Data Partner
    |--------------------------------------------------------------------------
    |
    | Informasi tentang client yang diizinkan untuk mengakses API Anda
    */

    /*
    |--------------------------------------------------------------------------
    | Data Client
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
