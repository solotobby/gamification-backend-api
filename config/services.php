<?php

return [

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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'paystack' => [
        'baseUrl' => env('PAYSTACK_PAYMENT_URL'),
        'publickey' => env('PAYSTACK_PUBLIC_KEY'),
        'secretKey' => env('PAYSTACK_SECRET_KEY'),
        'merchantEmail' => env('MERCHANT_EMAIL'),
        'callbackUrl' => env('PAYSTACK_CALLBACK_URL'),
    ],

    'korapay' => [
        'secret_key' => env('KORA_SEC'),
    ],
    'stripe' => [
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    // 'firebase' => [
    //     'server_key' => env('FIREBASE_SERVER_KEY'),
    // ],

    'public_api' => [
        'token' => env('PUBLIC_API_TOKEN'),
    ],
    
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),

    ],

    'interswitch' => [
        'client_id'     => env('INTERSWITCH_CLIENT_ID'),
        'client_secret' => env('INTERSWITCH_CLIENT_SECRET'),
        'webhook_secret' => env('INTERSWITCH_WEBHOOK_SECRET'),
        'merchant_code' => env('INTERSWITCH_MERCHANT_CODE'),
        'payable_code'  => env('INTERSWITCH_PAYABLE_CODE'),
        'provider_code' => env('INTERSWITCH_PROVIDER_CODE'),
        'base_url'      => env('INTERSWITCH_BASE_URL', 'https://qa.interswitchng.com'),
        'passport_url'  => env('INTERSWITCH_PASSPORT_URL', 'https://passport.interswitchng.com'),
    ],
];
