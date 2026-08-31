<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveServiceProvider
{
    protected string $secretKey;
    protected string $baseUrl = 'https://api.flutterwave.com/v3';

    public const VIRTUAL_ACCOUNT_CURRENCIES = ['GHS'];

    /**
     * No dynamic listing endpoint exists on Flutterwave's side for mobile
     * money — this is a fixed, documented set of supported networks per
     * country. South Africa is deliberately absent: Flutterwave does not
     * support ZAR mobile money at all, only bank transfer.
     */
    public const MOBILE_MONEY_NETWORKS = [
        'GH' => [
            ['code' => 'MTN',        'name' => 'MTN Mobile Money'],
            ['code' => 'VODAFONE',   'name' => 'Vodafone Cash'],
            ['code' => 'AIRTELTIGO', 'name' => 'AirtelTigo Money'],
        ],
        'KE' => [
            ['code' => 'MPESA', 'name' => 'M-Pesa'],
            ['code' => 'AIRTEL', 'name' => 'Airtel Money'],
        ],
    ];

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type'  => 'application/json',
        ];
    }

    public function createVirtualAccount(array $data): ?array
    {
        if (($data['currency'] ?? null) !== 'GHS') {
            Log::error('Flutterwave: virtual account requested for unsupported currency', $data);
            return null;
        }

        $payload = array_filter([
            'email'        => $data['email'],
            'currency'     => 'GHS',
            'amount'       => $data['amount'] ?? 1,
            'tx_ref'       => $data['tx_ref'],
            'is_permanent' => true,
            'firstname'    => $data['firstname'] ?? null,
            'lastname'     => $data['lastname'] ?? null,
            'narration'    => $data['narration'] ?? 'Wallet Funding Account',
        ]);

        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/virtual-account-numbers", $payload);

        Log::info('Flutterwave Create Virtual Account Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    public function initializePayment(array $data): ?array
    {
        $payload = [
            'tx_ref'         => $data['reference'],
            'amount'         => $data['amount'],
            'currency'       => $data['currency'],
            'redirect_url'   => $data['callback_url'],
            'customer'       => [
                'email' => $data['email'],
                'name'  => $data['name'] ?? null,
            ],
            'customizations' => ['title' => 'Freebyz Wallet Top Up'],
        ];

        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments", $payload);

        Log::info('Flutterwave Initialize Payment Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    public function verifyPayment(string $reference): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/transactions/verify_by_reference", ['tx_ref' => $reference]);

        Log::info('Flutterwave Verify Payment Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    // ── Bank list / name resolution (bank method only) ──────────────────

    public function getBanks(string $countryCode): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/banks/{$countryCode}");

        Log::info('Flutterwave Get Banks Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    public function resolveAccount(string $accountNumber, string $bankCode): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/accounts/resolve", [
                'account_number' => $accountNumber,
                'account_bank'   => $bankCode,
            ]);

        Log::info('Flutterwave Resolve Account Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    /**
     * Ghana bank/mobile-money transfers need this extra step: the bank/
     * network `id` from getBanks() must be exchanged for a branch code
     * before it can be used in an actual transfer. Only relevant once
     * payouts are wired up — not needed for saving/displaying details.
     */
    public function getBranchCode(string $bankId): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/banks/{$bankId}/branches");

        Log::info('Flutterwave Get Branch Code Response: ' . $res->body());

        return $res->successful() ? $res->json('data') : null;
    }

    // ── Mobile money networks (static list, no resolve endpoint exists) ──

    public function getMobileMoneyNetworks(string $countryCode): array
    {
        return self::MOBILE_MONEY_NETWORKS[$countryCode] ?? [];
    }
}