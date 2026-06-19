<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InterswitchServiceProvider
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;
    protected string $passportUrl;
    protected string $merchantCode;
    protected string $payableCode;
    protected string $providerCode;

    public function __construct()
    {
        $this->clientId     = config('services.interswitch.client_id');
        $this->clientSecret = config('services.interswitch.client_secret');
        $this->baseUrl      = config('services.interswitch.base_url');       // https://qa.interswitchng.com
        $this->passportUrl  = config('services.interswitch.passport_url');   // https://sandbox.interswitchng.com
        $this->merchantCode = config('services.interswitch.merchant_code');
        $this->payableCode  = config('services.interswitch.payable_code');
        $this->providerCode = config('services.interswitch.provider_code', 'WEMA');
    }

    // ── OAuth 2.0 Token ───────────────────────────────────────────────────

    protected function getAccessToken(): ?string
    {
        return Cache::remember('interswitch_access_token', 39000, function () {
            $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

            $res = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])
            ->asForm()
            ->post("{$this->passportUrl}/passport/oauth/token", [
                'grant_type' => 'client_credentials',
            ]);

            Log::info('Interswitch Auth Response: ' . $res->body());

            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    // ── OAuth 2.0 headers (for payment gateway endpoints) ─────────────────

    protected function oauthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    // ── InterswitchAuth headers (for legacy endpoints) ────────────────────
    // Used by: virtual account, transfers, SVA endpoints
    // Signature: Base64(SHA1("METHOD&urlEncode(url)&timestamp&nonce&clientId&secret"))

    protected function legacyHeaders(string $method, string $url): array
    {
        $timestamp = (string) time();
        $nonce     = Str::random(32); // max 64 chars

        $signatureString = strtoupper($method)
            . '&' . urlencode($url)
            . '&' . $timestamp
            . '&' . $nonce
            . '&' . $this->clientId
            . '&' . $this->clientSecret;

        $signature = base64_encode(sha1($signatureString, true));

        return [
            'Authorization'   => 'Bearer ' . $this->getAccessToken(), // ← OAuth token, not InterswitchAuth
        // 'Authorization'   => 'InterswitchAuth ' . base64_encode($this->clientId),
            'Timestamp'       => $timestamp,
            'Nonce'           => $nonce,
            'Signature'       => $signature,
            'SignatureMethod'  => 'SHA1',
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    // ── Virtual Account (NGN static) ──────────────────────────────────────

    public function createVirtualAccount(string $accountName, ?string $provider = null): ?array
    {
        $url = "{$this->baseUrl}/paymentgateway/api/v1/payable/virtualaccount";

        $payload = [
            'accountName'  => $accountName,
            // 'merchantCode' => (string)$this->merchantCode,
            'merchantCode' => 'MX162952'
        ];

        $provider = $provider ?? $this->providerCode;
        if ($provider) {
            $payload['provider'] = $provider;
        }

        Log::info('Interswitch Create Virtual Account URL: ' . $url);

        $res = Http::withHeaders($this->legacyHeaders('POST', $url))
            ->post($url, $payload);

        Log::info('Interswitch Create Virtual Account Response: ' . $res);

        return $res->successful() ? $res->json() : null;
    }

    // ── Payment Initiation (multi-currency) ───────────────────────────────

    public function initializePayment(array $data): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/payments/initiate";

        $res = Http::withHeaders($this->oauthHeaders())
            ->post($url, [
                'merchantCode'         => $this->merchantCode,
                'payableCode'          => $this->payableCode,
                'transactionReference' => $data['reference'],
                'amount'               => (string) ((int) ($data['amount'] * 100)),
                'currencyCode'         => $this->currencyCode($data['currency']),
                'customerEmail'        => $data['email'],
                'redirectUrl'          => $data['callback_url'],
                'siteRedirectUrl'      => $data['callback_url'],
            ]);

        Log::info('Interswitch Initialize Payment Response: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }

    // ── Verify Payment ────────────────────────────────────────────────────

    public function verifyPayment(string $reference): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/merchant/transactions/query?transactionReference={$reference}";

        $res = Http::withHeaders($this->oauthHeaders())->get($url);

        Log::info('Interswitch Verify Payment Response: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function currencyCode(string $currency): string
    {
        return match (strtoupper($currency)) {
            'NGN' => '566',
            'USD' => '840',
            'GHS' => '936',
            'KES' => '404',
            'ZAR' => '710',
            default => '566',
        };
    }
}
