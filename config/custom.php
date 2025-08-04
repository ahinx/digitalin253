<?php

return [
    'app_name_admin' => env('APP_NAME_ADMIN', 'Admin Panel'),
    'app_name_public' => env('APP_NAME_PUBLIC', 'Toko Digitalin'),

    'midtrans' => [
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'mode' => env('MIDTRANS_MODE', 'sandbox'), // or 'production'
    ],

    'whatsapp' => [
        'api_url' => env('WA_API_URL'),
        'api_token' => env('WA_API_TOKEN'),
    ],
];
