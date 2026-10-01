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

    'atmorouter' => [
        'api_key' => env('ATMOROUTER_API_KEY'),
        'base_url' => env('ATMOROUTER_BASE_URL', 'https://atmorouter.dev/v1'),
        'model' => env('ATMOROUTER_MODEL', 'atmo/deepseek-v4.1-flash'),
        'reasoning_effort' => env('ATMOROUTER_REASONING_EFFORT', 'medium'),
        'guest_token_limit' => (int) env('ATMOROUTER_GUEST_TOKEN_LIMIT', 3000),
    ],

];
