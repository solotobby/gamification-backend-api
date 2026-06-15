<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class InterswitchServiceProvider
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;
    protected string $passportUrl;

    public function __construct()
    {
        $this->clientId     = config('services.interswitch.client_id');
        $this->clientSecret = config('services.interswitch.client_secret');
        $this->baseUrl      = config('services.interswitch.base_url');      // https://qa.interswitchng.com or https://api.interswitchgroup.com
        $this->passportUrl  = config('services.interswitch.passport_url');  // https://passport.interswitchng.com
    }

    // ── Auth ──────────────────────────────────────────────────────────────

    protected function getAccessToken(): ?string
    {
        return Cache::remember('interswitch_access_token', 3000, function () {
            $res = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->passportUrl}/passport/oauth/token", [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'profile',
                ]);

            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    // ── Virtual Account (NGN) ─────────────────────────────────────────────

    /**
     * Create a virtual account for a user (NGN only via Interswitch)
     *
     * @param array $data [
     *   'customer_id'    => string,
     *   'first_name'     => string,
     *   'last_name'      => string,
     *   'email'          => string,
     *   'phone'          => string,
     *   'bvn'            => string,  // required by Interswitch
     * ]
     */
    public function createVirtualAccount(array $data): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/api/v2/quickteller/payments/transfers/virtual-accounts", [
                'customerId'  => $data['customer_id'],
                'firstName'   => $data['first_name'],
                'lastName'    => $data['last_name'],
                'email'       => $data['email'],
                'phoneNumber' => $data['phone'],
                'bvn'         => $data['bvn'] ?? null,
                'currency'    => 'NGN',
            ]);

        return $res->successful() ? $res->json() : null;
    }

    public function getVirtualAccount(string $customerId): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/v2/quickteller/payments/transfers/virtual-accounts/{$customerId}");

        return $res->successful() ? $res->json() : null;
    }

    // ── Payment Initiation (multi-currency) ───────────────────────────────

    /**
     * Initialize a checkout/payment.
     * Supports NGN, KES (Kenya), GHS (Ghana), ZAR (South Africa), USD
     *
     * @param array $data [
     *   'reference'    => string,
     *   'amount'       => float,
     *   'currency'     => string,   // NGN | KES | GHS | ZAR | USD
     *   'email'        => string,
     *   'callback_url' => string,
     * ]
     */
    public function initializePayment(array $data): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/api/v2/quickteller/payments/initiate", [
                'merchantCode'          => config('services.interswitch.merchant_code'),
                'payableCode'           => config('services.interswitch.payable_code'),
                'transactionReference'  => $data['reference'],
                'amount'                => (int) ($data['amount'] * 100), // lowest denomination
                'currencyCode'          => $this->currencyCode($data['currency']),
                'customerEmail'         => $data['email'],
                'redirectUrl'           => $data['callback_url'],
                'siteRedirectUrl'       => $data['callback_url'],
            ]);

        return $res->successful() ? $res->json() : null;
    }

    public function verifyPayment(string $reference): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/v2/quickteller/transactions/{$reference}");

        return $res->successful() ? $res->json() : null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Map currency string to Interswitch numeric currency code
     */
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
