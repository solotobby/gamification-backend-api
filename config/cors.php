<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://freebyz.com',
        'https://www.freebyz.com',
        'https://dashboard.freebyz.com',
        'https://cv.freebyz.com',
        'http://localhost',
        'http://localhost:8000',
        'http://localhost:8081',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8081',
        'http://127.0.0.1:8001',
    ],

    'allowed_origins_patterns' => [
        '#^https?://([a-zA-Z0-9-]+\.)*freebyz\.(com|test|ng)$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
