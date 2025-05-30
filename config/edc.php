<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ECRLink Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration for the ECRLink EDC BRI integration.
    |
    */

    // EDC Address (IP of the EDC device)
    'edc_address' => env('EDC_ADDRESS', 'localhost'), //10.100.3.11
    // EDC Address (Port of the EDC device)
    'edc_port' => env('EDC_PORT', '6745'),
    // POS Address (IP of the POS/application/APM App)
    'pos_address' => env('EDC_POS_ADDRESS', 'localhost'), //192.167.4.250
    // Use secure WebSocket (WSS)
    'secure' => env('EDC_SECURE', false),
    // Secret key for encryption (development)
    'secret_key' => env('EDC_SECRET_KEY', 'ECR2022secretKey'),

    'webhook_url' => env('EDC_WEBHOOK_BASE_URL'),
    'webhook_secret' => env('EDC_WEBHOOK_SECRET'),
];
