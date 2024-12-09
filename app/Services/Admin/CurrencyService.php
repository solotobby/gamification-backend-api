<?php

namespace App\Services\Admin;
use PHPUnit\Event\Code\Throwable;
use App\Exceptions\BadRequestException;

class CurrencyService
{
    public function __construct() {}


    public function getCurrenciesList() {

        try{

        }
        catch (Throwable $e) {
            // return $e;
             throw new BadRequestException('Error processing request');
         }
    }


    public function getCurrency($id) {}
}
