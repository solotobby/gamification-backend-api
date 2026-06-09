<?php

namespace App\Repositories\Admin;

use App\Models\Currency;
use App\Models\ConversionRate;
use Illuminate\Support\Facades\Cache;

class CurrencyRepositoryModel
{
    private const CACHE_TTL = 3600;

    public function getCurrenciesList()
    {
        return Cache::remember(
            'currencies.all',
            self::CACHE_TTL,
            fn() =>
            Currency::orderByDesc('created_at')->get()
        );
    }

    public function getActiveCurrenciesList()
    {
        return Cache::remember(
            'currencies.active',
            self::CACHE_TTL,
            fn() =>
            Currency::where('is_active', true)->orderByDesc('created_at')->get()
        );
    }

    public function getCurrencyById($id)
    {
        return Cache::remember(
            "currencies.id.{$id}",
            self::CACHE_TTL,
            fn() =>
            Currency::find($id)
        );
    }

    public function getCurrencyByCode(string $code)
    {
        $code = strtoupper($code);
        return Cache::remember(
            "currencies.code.{$code}",
            self::CACHE_TTL,
            fn() =>
            Currency::where('code', $code)->where('is_active', true)->first()
        );
    }

    public function convertCurrency($from, $to)
    {
        $key = "conversion_rate.{$from}.{$to}";
        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn() =>
            ConversionRate::where('from', $from)->where('to', $to)->first()
        );
    }

    public function mapRateCurrency(string $currency): string|false
    {
        return match (strtolower($currency)) {
            'ngn' => 'NGN',
            'usd' => 'USD',
            default => false,
        };
    }

    /**
     * Call when a currency is created/updated/deleted
     */
    public function clearCache(?string $code = null, ?int $id = null): void
    {
        Cache::forget('currencies.all');
        Cache::forget('currencies.active');

        if ($id) Cache::forget("currencies.id.{$id}");
        if ($code) Cache::forget("currencies.code." . strtoupper($code));
    }
}
// class CurrencyRepositoryModel
// {

//     public function getCurrenciesList()
//     {
//         return Currency::orderBy(
//             'created_at',
//             'DESC'
//         )->get();
//     }

//     public function getActiveCurrenciesList()
//     {
//         return Currency::where(
//             'is_active',
//             true
//         )->orderBy('created_at', 'DESC')->get();
//     }

//     public function getCurrencyById($id)
//     {
//         return Currency::where(
//             'id',
//             $id
//         )->first();
//     }

//     public function getCurrencyByCode($code)
//     {
//         return Currency::where(
//             'code',
//             $code
//         )->where(
//             'is_active',
//             true
//         )->first();
//     }

//     public function convertCurrency($from, $to)
//     {
//         return ConversionRate::where(
//             'from',
//             $from
//         )->where(
//             'to',
//             $to
//         )->first();
//     }

//     public function mapRateCurrency($currency)
//     {
//         switch (strtolower($currency)) {

//             case 'ngn':
//                 return 'NGN';

//             case 'usd':
//                 return 'USD';

//             default:
//                 return false;
//         }
//     }
// }
