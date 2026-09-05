<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $this->clientId = config('services.interswitch.client_id');
        $this->clientSecret = config('services.interswitch.client_secret');
        $this->baseUrl = config('services.interswitch.base_url');  // https://qa.interswitchng.com
        $this->passportUrl = config('services.interswitch.passport_url');  // https://sandbox.interswitchng.com
        $this->merchantCode = config('services.interswitch.merchant_code');
        $this->payableCode = config('services.interswitch.payable_code');
        $this->providerCode = config('services.interswitch.provider_code', 'WEMA');
    }

    // ── OAuth 2.0 Token ───────────────────────────────────────────────────

    protected function getAccessToken(): ?string
    {
        return Cache::remember('interswitch_access_token', 39000, function () {
            $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

            $res = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
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
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    // ── InterswitchAuth headers (for legacy endpoints) ────────────────────
    // Used by: virtual account, transfers, SVA endpoints
    // Signature: Base64(SHA1("METHOD&urlEncode(url)&timestamp&nonce&clientId&secret"))

    protected function legacyHeaders(string $method, string $url): array
    {
        // $timestamp = (string) time();
        // $nonce     = Str::random(32); // max 64 chars

        // $signatureString = strtoupper($method)
        //     . '&' . urlencode($url)
        //     . '&' . $timestamp
        //     . '&' . $nonce
        //     . '&' . $this->clientId
        //     . '&' . $this->clientSecret;

        // $signature = base64_encode(sha1($signatureString, true));

        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            // 'Authorization'   => 'InterswitchAuth ' . base64_encode($this->clientId),
            // 'Timestamp'       => $timestamp,
            // 'Nonce'           => $nonce,
            // 'Signature'       => $signature,
            // 'SignatureMethod'  => 'SHA1',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    // ── Virtual Account (NGN static) ──────────────────────────────────────

    public function createVirtualAccount(string $accountName, ?string $provider = null): ?array
    {
        $url = "{$this->baseUrl}/paymentgateway/api/v1/payable/virtualaccount";

        $payload = [
            'accountName' => 'Freebyz Technologies/' . $accountName,
            'merchantCode' => (string) $this->merchantCode,
        ];

        if ($this->payableCode) {
            $payload['payableCode'] = (string) $this->payableCode;
        }

        $provider = $provider ?? $this->providerCode;
        if ($provider) {
            $payload['provider'] = $provider;
        }

        try {
            $res = Http::withHeaders($this->oauthHeaders())
                ->timeout(20)
                ->post($url, $payload);

            Log::info('Interswitch Create Virtual Account Response: ' . $res->body());

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable $e) {
            Log::error('Interswitch Create Virtual Account Error: ' . $e->getMessage());
            return null;
        }
    }

    // ── Payment Initiation (multi-currency) ───────────────────────────────

    public function initializePayment(array $data): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/payments/initiate";

        $res = Http::withHeaders($this->oauthHeaders())
            ->post($url, [
                'merchantCode' => $this->merchantCode,
                'payableCode' => $this->payableCode,
                'transactionReference' => $data['reference'],
                'amount' => (string) ((int) ($data['amount'] * 100)),
                'currencyCode' => $this->currencyCode($data['currency']),
                'customerEmail' => $data['email'],
                'redirectUrl' => $data['callback_url'],
                'siteRedirectUrl' => $data['callback_url'],
            ]);

        Log::info('Interswitch Initialize Payment Response: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }

    // ── Verify Payment ────────────────────────────────────────────────────

    public function verifyPayment(string $reference, float $amount): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/gettransaction?merchantcode={$this->merchantCode}&transactionreference={$reference}&amount=" . (int) ($amount * 100);

        $res = Http::withHeaders($this->oauthHeaders())->get($url);

        Log::info('Interswitch Verify Payment Response: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }

    // ── Bank List & Name Enquiry (NGN) ─────────────────────────────────────
    // NOTE: these live on Interswitch's Transfer Service / generic-wallet API
    // line, not the paymentgateway/collections endpoints used above. Confirm
    // the exact base path against your Interswitch merchant dashboard/account
    // manager before going live — the path below reflects Interswitch's public
    // docs but may differ per merchant provisioning tier.

    public function getBanks(): ?array
    {
        $url = "{$this->baseUrl}/generic-wallet/api/v1/admin/account/banks";

        $res = Http::withHeaders($this->oauthHeaders())->get($url);

        Log::info('Interswitch Get Banks Response: ' . $res->body());

        return $res->successful() ? $res->json('data') ?? $res->json() : null;
    }

    public function resolveAccount(string $accountNumber, string $sortCode): ?array
    {
        $url = "{$this->baseUrl}/api/v1/inquiry/bank-code/{$sortCode}/account/{$accountNumber}";

        $res = Http::withHeaders($this->oauthHeaders())->get($url);

        Log::info('Interswitch Name Enquiry Response: ' . $res->body());

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
            'UGX' => '800',
            default => '566',
        };
    }
}
