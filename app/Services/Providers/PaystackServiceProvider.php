<?php
namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class PaystackServiceProvider
{
    private $publicKey, $secretKey, $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('services.paystack.publicKey');
        $this->secretKey = config('services.paystack.secretKey');
        $this->baseUrl = config('services.paystack.baseUrl');
    }

    public function bankList()
    {
        $url = $this->baseUrl.'/bank';

        //return $url;
        $res = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->secretKey
        ])->get($url)->throw();

        return json_decode($res->getBody()->getContents(), true)['data'];
    }

    public function resolveAccountName($accountNumber, $bankCode)
    {
        $url = $this->baseUrl.'/bank/resolve?account_number='.$accountNumber.'&bank_code='.$bankCode;

        $res = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->secretKey
        ])->get($url);
       // return $res;
        return json_decode($res->getBody()->getContents(), true);
    }

    public function recipientCode($name, $account_number, $bank_code)
    {

        $url = $this->baseUrl.'/transferrecipient';
        $res = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->secretKey
        ])->post($url, [
            "type"=> "nuban",
            "name"=> $name,
            "account_number"=> $account_number,
            "bank_code"=> $bank_code,
            "currency"=> "NGN"
        ]);

        return json_decode($res->getBody()->getContents(), true);

    }

}
