<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function verify(string $token, ?string $ip = null): bool
    {
        $response = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]
        );

        return $response->successful()
            && $response->json('success') === true;
    }
}
