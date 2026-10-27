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

    /*
    | AI worker MySQL accounts (DEC-08), created by `php artisan atlantech:worker-accounts`.
    */
    'ai_workers' => [
        'db_passwords' => [
            'w_part1' => env('WORKER_PART1_PASSWORD'),
            'w_part2' => env('WORKER_PART2_PASSWORD'),
            'w_yard' => env('WORKER_YARD_PASSWORD'),
            'w_edi' => env('WORKER_EDI_PASSWORD'),
            'w_validate' => env('WORKER_VALIDATE_PASSWORD'),
            'w_part3' => env('WORKER_PART3_PASSWORD'),
        ],
    ],

];
