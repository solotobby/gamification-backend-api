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

       $mapFrom = $this->mapRateCurrency($from);
       $mapTo = $this->mapRateCurrency($to);
        return ConversionRate::where(
            'from',
            $mapFrom
        )->where(
            'to',
            $mapTo
        )->first();
    }

    public function mapRateCurrency($currency)
    {
        switch (strtolower($currency)) {

            case 'ngn':
                return 'Naira';

            case 'usd':
                return 'Dollar';

            default:
                return false;
        }
    }

}
