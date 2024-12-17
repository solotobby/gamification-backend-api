<?php

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;
use App\Models\Currency;
use App\Models\ConversionRate;

class CurrencyRepositoryModel
{

    public function getCurrenciesList()
    {
        return Currency::orderBy('created_at', 'DESC')->get();
    }

    public function getActiveCurrenciesList()
    {
        return Currency::where('is_active', true)->orderBy('created_at', 'DESC')->get();
    }

    public function getCurrencyById($id)
    {
        return Currency::where('id', $id)->first();
    }

    public function getCurrencyByCode($code)
    {
        return Currency::where(
            'code',
            $code
        )->where(
            'is_active',
            true
        )->first();
    }

    public function convertCurrency($from, $to)
    {

      // $mapFrom = $this->mapRateCurrency($from);
      // $mapTo = $this->mapRateCurrency($to);
        return ConversionRate::where(
            'from',
            $from
        )->where(
            'to',
            $to
        )->first();
    }

    public function mapRateCurrency($currency)
    {
        switch (strtolower($currency)) {

            case 'ngn':
                return 'NGN';

            case 'usd':
                return 'USD';

            default:
                return false;
        }
    }

}
