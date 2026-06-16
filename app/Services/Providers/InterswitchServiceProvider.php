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
        $this->baseUrl      = config('services.interswitch.base_url');
        $this->passportUrl  = config('services.interswitch.passport_url');
        $this->merchantCode = config('services.interswitch.merchant_code');
        $this->payableCode  = config('services.interswitch.payable_code');
        $this->providerCode = config('services.interswitch.provider_code');
    }

    // ── Auth ──────────────────────────────────────────────────────────────

    protected function getAccessToken(): ?string
    {
        return Cache::remember('interswitch_access_token', 3000, function () {
            $url = "{$this->passportUrl}/passport/oauth/token";

            $res = Http::withHeaders($this->buildHeaders('POST', $url))
                ->asForm()
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'profile',
                ]);

            Log::info('Interswitch Auth Response: ' . $res->body());

            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    /**
     * Build Interswitch-required auth headers.
     *
     * Per docs: https://interswitch-docs.readme.io/reference/header-computation
     *
     * InterswitchAuth: Base64(client_id)
     * Signature: Base64(SHA512(verb + "&" + percentEncode(url) + "&" + timestamp + "&" + nonce + "&" + client_id + "&" + secret))
     */
    protected function buildHeaders(string $verb, string $fullUrl): array
    {
        $timestamp = (string) time();
        $nonce     = Str::random(32);

        $stringToBeSigned = strtoupper($verb)
            . '&' . rawurlencode($fullUrl)
            . '&' . $timestamp
            . '&' . $nonce
            . '&' . $this->clientId
            . '&' . $this->clientSecret;

        $signature = base64_encode(hash('sha512', $stringToBeSigned, true));

        return [
            'InterswitchAuth' => base64_encode($this->clientId),
            'Authorization'   => 'Bearer ' . $this->getAccessToken(),
            'Timestamp'       => $timestamp,
            'Nonce'           => $nonce,
            'Signature'       => $signature,
            'SignatureMethod' => 'SHA512',
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    // ── Virtual Account (NGN) ─────────────────────────────────────────────

    /**
     * Create/get virtual account for a user.
     * Endpoint: POST /api/v1/payments/customer-virtual-account
     *
     * @param array $data [
     *   'customer_id'  => string,  // phone number or unique ID
     *   'first_name'   => string,
     *   'last_name'    => string,
     * ]
     */
    public function createVirtualAccount(array $data): ?array
    {
        // $url = "{$this->baseUrl}/api/v1/payments/customer-virtual-account";

        // $res = Http::withHeaders($this->buildHeaders('POST', $url))
        //     ->post($url, [
        //         'customerId'   => $data['customer_id'],
        //         'customerName' => trim($data['first_name'] . ' ' . $data['last_name']),
        //         'providerCode' => $this->providerCode,
        //     ]);

        // Log::info('Interswitch Virtual Account Response: ' . $res->body());

        $this->getBanks(); // Call getBanks() to retrieve the list of banks
        // return $res->successful() ? $res->json() : null;
    }

    public function getVirtualAccount(string $customerId): ?array
    {
        $url = "{$this->baseUrl}/api/v1/payments/customer-virtual-account/{$customerId}";

        $res = Http::withHeaders($this->buildHeaders('GET', $url))
            ->get($url);

        Log::info('Interswitch Get Virtual Account Response: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }

    // ── Payment Initiation (multi-currency via IPG) ───────────────────────

    /**
     * Initialize a payment via Interswitch Payment Gateway.
     * Interswitch IPG uses a redirect flow — returns a payment URL.
     *
     * Supports: NGN, KES, GHS, ZAR, USD
     *
     * @param array $data [
     *   'reference'    => string,
     *   'amount'       => float,
     *   'currency'     => string,
     *   'email'        => string,
     *   'callback_url' => string,
     * ]
     */
    public function initializePayment(array $data): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/payments/initiate";

        $res = Http::withHeaders($this->buildHeaders('POST', $url))
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
        // $this->getBanks();
        return $res->successful() ? $res->json() : null;
    }

    /**
     * Verify a payment by transaction reference.
     */
    public function verifyPayment(string $reference): ?array
    {
        $url = "{$this->baseUrl}/collections/api/v1/merchant/transactions/query?transactionReference={$reference}";

        $res = Http::withHeaders($this->buildHeaders('GET', $url))
            ->get($url);

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

    public function getBanks(): ?array
    {
        $url = "{$this->baseUrl}/api/v1/payments/banks";

        $res = Http::withHeaders($this->buildHeaders('GET', $url))
            ->get($url);

        Log::info('Interswitch Banks: ' . $res->body());

        return $res->successful() ? $res->json() : null;
    }
}
