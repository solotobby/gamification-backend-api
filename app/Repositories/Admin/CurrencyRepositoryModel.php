<?php

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;
use App\Models\Currency;

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
}
