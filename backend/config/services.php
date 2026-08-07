<?php

return [

    'shsms' => [
        'endpoint' => env('SHSMS_ENDPOINT', 'https://shsms.ir/api/v1/sendms'),
        'token' => env('SHSMS_API_TOKEN', env('SHSMS_TOKEN')),
        'text_template' => env('SHSMS_TEXT_TEMPLATE'),
        'sandbox' => filter_var(env('SMS_SEND_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
        'sender' => env('SHSMS_SENDER'),
        'treatment_link' => env('TREATMENT_GUIDE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

];
