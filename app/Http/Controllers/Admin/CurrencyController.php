<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Admin\CurrencyService;

class CurrencyController extends Controller
{
    protected $currency;
    public function __construct(CurrencyService $currency){
        $this->middleware("isAdmin");
        $this->currency = $currency;

    }

    public function getCurrency($id){
        return $this->currency->getCurrency($id);
    }
    public function getCurrenciesList(){
        return $this->currency->getCurrenciesList();
    }


}
