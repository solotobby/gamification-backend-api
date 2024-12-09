<?php

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;
use App\Models\Currency;
class CurrencyRepositoryModel
{

    public function getCurrenciesList(){
       return Currency::orderBy('created_at', 'DESC')->get();
    }

    public function getCurrencyById($id){
        return Currency::where('id', $id)->first();
    }
}
