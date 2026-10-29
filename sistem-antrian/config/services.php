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

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_url' => env('WHATSAPP_API_URL', 'https://api.fonnte.com/send'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'KlinikAntrianBot'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'link_token_ttl' => (int) env('TELEGRAM_LINK_TOKEN_TTL', 15),
    ],

    'random_forest' => [
        'enabled' => env('RANDOM_FOREST_ENABLED', false),
        'api_url' => env('RANDOM_FOREST_API_URL', 'http://127.0.0.1:8000/predict'),
        'connect_timeout' => (int) env('RANDOM_FOREST_CONNECT_TIMEOUT', 1),
        'timeout' => (int) env('RANDOM_FOREST_TIMEOUT', 2),
    ],

];
