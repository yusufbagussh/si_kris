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
    'edc_address' => env('ECRLINK_EDC_ADDRESS', 'localhost'), //10.100.3.11
    // EDC Address (Port of the EDC device)
    'edc_port' => env('ECRLINK_EDC_PORT', '6745'),
    // POS Address (IP of the POS/application/APM App)
    'pos_address' => env('ECRLINK_POS_ADDRESS', 'localhost'), //192.167.4.250
    // Use secure WebSocket (WSS)
    'secure' => env('ECRLINK_SECURE', false),
    // Secret key for encryption (development)
    'secret_key' => env('ECRLINK_SECRET_KEY', 'ECR2022secretKey'),

    'webhook_url' => env('ECRLINK_WEBHOOK_BASE_URL'),
    'webhook_secret' => env('ECRLINK_WEBHOOK_SECRET'),
];
