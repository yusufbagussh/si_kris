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

    'connection' => env('EDC_CONNECTION', 'wifi'), // wifi, serialUSB, bluetooth
    'timeout' => env('EDC_TIME_OUT', 30), // seconds
    'secret_key' => env('EDC_SECRET_KEY', 'ECR2022secretKey'),
    'webhook_url' => env('EDC_WEBHOOK_BASE_URL'),
    'webhook_secret' => env('EDC_WEBHOOK_SECRET'),

    // WebSocket Configuration
    'wifi' => [
        'edc_address' => env('EDC_WEBSOCKET_ADDRESS', 'localhost'),
        'port' => env('EDC_WEBSOCKET_PORT', '6745'), //6745, 6746
        'pos_address' => env('EDC_POS_WEBSOCKET_ADDRESS', 'localhost'),
        'secure' => env('EDC_WEBSOCKET_SECURE', false)
    ],

    // USB Serial Configuration
    'usb' => [
        'port' => env('EDC_USB_COM_PORT', null), // null for auto-detection
        'baud_rate' => env('EDC_USB_BAUD_RATE', 115200),
        'data_bits' => env('EDC_USB_DATA_BITS', 8),
        'stop_bits' => env('EDC_USB_STOP_BITS', 1),
        'parity' => env('EDC_USB_PARITY', 0), // 0=None, 1=Odd, 2=Even
        'auto_detect' => env('EDC_USB_AUTO_DETECT', true),
    ],

    // Bluetooth Configuration
    'bluetooth' => [
        'port' => env('EDC_BLUETOOTH_PORT', '00:11:22:33:44:55'), // Bluetooth MAC address
    ],
];
