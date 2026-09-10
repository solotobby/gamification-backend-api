<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Microsoft Teams Logging Enabled
    |--------------------------------------------------------------------------
    |
    | Globally enable or disable sending logs to Microsoft Teams channels.
    | Useful for disabling Teams alerts in local or testing environments.
    |
    */
    'enabled' => env('TEAMS_LOGGING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Teams Webhook URLs
    |--------------------------------------------------------------------------
    |
    | You can configure a single default webhook URL, or dedicate separate
    | webhooks for error alerts vs. informational/transactional logs.
    |
    */
    'webhook_url' => env('TEAMS_WEBHOOK_URL', null),

    'error_webhook_url' => env('TEAMS_ERROR_WEBHOOK_URL', env('TEAMS_WEBHOOK_URL', null)),

    'info_webhook_url' => env('TEAMS_INFO_WEBHOOK_URL', env('TEAMS_WEBHOOK_URL', null)),

    /*
    |--------------------------------------------------------------------------
    | Minimum Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum log level to dispatch to Teams: debug, info, notice,
    | warning, error, critical, alert, emergency.
    |
    */
    'level' => env('TEAMS_LOG_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Queue Webhook Dispatches
    |--------------------------------------------------------------------------
    |
    | When set to true, log dispatches to Teams will be pushed onto your
    | queue instead of being sent synchronously.
    |
    */
    'queue' => env('TEAMS_LOGGING_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Timeout (Seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum time to wait for Microsoft Teams webhook response before timing
    | out. Kept low to prevent delaying API responses.
    |
    */
    'timeout' => (int) env('TEAMS_LOG_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes that should not be automatically reported to Teams
    | to avoid flooding channels with expected client-side errors (404s, 422s).
    |
    */
    'ignored_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \App\Exceptions\NotFoundException::class,
        \App\Exceptions\BadRequestException::class,
        \App\Exceptions\UnauthorizedException::class,
        \App\Exceptions\ForbiddenException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scrubbed / Redacted Fields
    |--------------------------------------------------------------------------
    |
    | Any request parameters matching these keys will be scrubbed/masked before
    | sending request context to Teams to avoid exposing credentials or PII.
    |
    */
    'scrub_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'authorization',
        'pin',
        'cvv',
        'card_number',
        'card_cvv',
        'card_pin',
        'account_number',
        'bvn',
        'nin',
        'private_key',
        'client_secret',
    ],
];
