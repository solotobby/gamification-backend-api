<?php

namespace App\Http\Middleware;

use App\Exceptions\BadRequestException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $token = $request->input('cf-turnstile-response');

        // Skip Turnstile verification if no token was provided.
        if (blank($token)) {
            // Log::info($request);
            return $next($request);
        }

        // Log::info($token);
        $response = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret'   => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]
        );

        if (! $response->successful() || ! $response->json('success')) {
            throw new BadRequestException('Captcha verification failed.');
        }

        return $next($request);
    }
}
