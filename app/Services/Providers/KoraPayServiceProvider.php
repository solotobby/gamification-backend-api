<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KoraPayServiceProvider
{
    protected ?string $secretKey;
    protected ?string $publicKey;
    protected string $baseUrl = 'https://api.korapay.com/merchant/api/v1';

    public function __construct()
    {
        $this->secretKey = config('services.korapay.secret_key');
        $this->publicKey = config('services.korapay.public_key');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    public function initializeCharge(array $payload): ?string
    {
        $res = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/charges/initialize", $payload);

        return $res->successful() ? $res->json('data.checkout_url') : null;
    }

    public function verifyCharge(string $reference): ?array
    {
        $res = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/charges/{$reference}");

        return $res->successful() ? $res->json() : null;
    }

    /**
     * Get list of supported banks from Korapay for a given country code (e.g. NG, GH, KE, ZA).
     */
    public function getBanks(string $countryCode, ?string $currency = null): ?array
    {
        try {
            $queryParams = ['countryCode' => strtoupper($countryCode)];
            if ($currency) {
                $queryParams['currency'] = strtoupper($currency);
            }

            $res = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/misc/banks", $queryParams);

            Log::info('KoraPay Get Banks Response for ' . $countryCode . ': ' . $res->body());

            if (!$res->successful()) {
                return null;
            }

            $banks = $res->json('data') ?? [];
            if (!is_array($banks)) {
                return null;
            }

            return collect($banks)
                ->map(fn($bank) => [
                    'id'        => $bank['code'] ?? $bank['id'] ?? '',
                    'name'      => $bank['name'] ?? '',
                    'code'      => (string) ($bank['code'] ?? ''),
                    'slug'      => $bank['slug'] ?? null,
                    'bank_code' => (string) ($bank['code'] ?? ''),
                ])
                ->filter(fn($bank) => !empty($bank['code']) && !empty($bank['name']))
                ->sortBy(fn($bank) => strtoupper(trim($bank['name'] ?? '')))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('KoraPay getBanks error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get list of supported Mobile Money Operators from Korapay.
     */
    public function getMobileMoneyOperators(string $countryCode): ?array
    {
        try {
            $res = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/misc/mobile-money", [
                    'countryCode' => strtoupper($countryCode),
                ]);

            Log::info('KoraPay Get MMO Response for ' . $countryCode . ': ' . $res->body());

            if (!$res->successful()) {
                return null;
            }

            $operators = $res->json('data') ?? [];
            if (!is_array($operators)) {
                return null;
            }

            return collect($operators)
                ->map(fn($op) => [
                    'id'        => $op['code'] ?? $op['slug'] ?? '',
                    'name'      => $op['name'] ?? '',
                    'code'      => (string) ($op['code'] ?? $op['slug'] ?? ''),
                    'slug'      => $op['slug'] ?? '',
                    'bank_code' => (string) ($op['code'] ?? $op['slug'] ?? ''),
                ])
                ->filter(fn($op) => !empty($op['name']))
                ->sortBy(fn($op) => strtoupper(trim($op['name'] ?? '')))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('KoraPay getMobileMoneyOperators error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve bank account name using Korapay's bank resolution endpoint.
     */
    public function resolveAccount(string $accountNumber, string $bankCode, string $currency = 'NGN', ?string $countryCode = null): ?array
    {
        try {
            $payload = [
                'bank'     => (string) $bankCode,
                'account'  => (string) $accountNumber,
                'currency' => strtoupper($currency),
            ];

            $res = Http::withHeaders($this->headers())
                ->timeout(12)
                ->post("{$this->baseUrl}/misc/banks/resolve", $payload);

            Log::info('KoraPay Resolve Account Response: ' . $res->body());

            if ($res->successful()) {
                $data = $res->json('data');
                $accountName = $data['account_name'] ?? null;
                if (!empty($accountName)) {
                    return [
                        'status'         => true,
                        'account_name'   => $accountName,
                        'account_number' => $data['account_number'] ?? $accountNumber,
                        'bank_code'      => $data['bank_code'] ?? $bankCode,
                        'bank_name'      => $data['bank_name'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('KoraPay resolveAccount error: ' . $e->getMessage());
        }

        return null;
    }
}
