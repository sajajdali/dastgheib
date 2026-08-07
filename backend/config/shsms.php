<?php

return [
    'api_token' => env('SHSMS_API_TOKEN', env('SHSMS_TOKEN')),
    'sandbox' => filter_var(env('SMS_SEND_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
    'endpoint' => env('SHSMS_ENDPOINT', 'https://shsms.ir/api/v1/sendms'),
    'text_template' => env('SHSMS_TEXT_TEMPLATE'),

    'templates' => [
        'login_android' => env('SMS_LOGIN_TEMPLATE_ANDROID'),
        'login_webapp' => env('SMS_LOGIN_TEMPLATE_WEBAPP'),
    ],
];
