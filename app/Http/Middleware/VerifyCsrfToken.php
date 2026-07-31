<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'virtual/account/webhook',
        'webhooks/paystack',
        'webhooks/korapay',
        'webhooks/stripe',
        'webhooks/zeptomail',
        'webhooks/interswitch',
        'webhooks/interswitch/callback',
    ];
}
