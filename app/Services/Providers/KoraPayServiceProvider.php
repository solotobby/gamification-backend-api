<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class KoraPayServiceProvider
{
    protected string $secretKey;
    protected string $baseUrl = 'https://api.korapay.com/merchant/api/v1';

    public function __construct()
    {
        $this->secretKey = config('services.korapay.secret_key');
    }

    public function initializeCharge(array $payload): ?string
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/charges/initialize", $payload);

        return $res->successful() ? $res->json('data.checkout_url') : null;
    }

    public function verifyCharge(string $reference): ?array
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get("{$this->baseUrl}/charges/{$reference}");

        return $res->successful() ? $res->json() : null;
    }
}
