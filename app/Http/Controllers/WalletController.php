<?php

namespace App\Http\Controllers;

use App\Helpers\PaystackHelpers;
use App\Helpers\Sendmonny;
use App\Helpers\SystemActivities;
use App\Mail\GeneralMail;
use App\Models\BankInformation;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\BankService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class WalletController extends Controller
{
    protected $walletService;
    protected $bankService;
    public function __construct(WalletService $walletService, BankService $bankService)
    {
        $this->middleware('auth');
        $this->walletService = $walletService;
        $this->bankService = $bankService;
    }

    public function fundWallet(Request $request)
    {

        return $this->walletService->fundWallet($request);
    }

    public function processWithdrawals(Request $request)
    {
        return $this->walletService->processWithdrawals($request);
    }

    public function getUserWithdrawals()
    {
        return $this->walletService->getWithdrawals();
    }

    public function getUserTransactions()
    {
        return $this->walletService->getTransactions();
    }

    public function getBankLists(){
        return $this->bankService->getBankList();
    }

    public function getAccountName(Request $request){
        return $this->bankService->getAccountDetails($request);
    }

    public function createBankDetails(Request $request)
    {
        return $this->bankService->saveUserAccountDetails($request);
    }

    public function storeFund(Request $request)
    {
        // $location = PaystackHelpers::getLocation();
        if (auth()->user()->wallet->base_currency == 'Naira') {
            $ref = time();

            $percent = 3 / 100 * $request->balance;
            $amount = $request->balance + $percent;

            //     $payload = [
            //         'tx_ref' => time(),
            //         'amount'=> $amount,
            //         'currency'=> "NGN",
            //         'redirect_url'=> url('flutterwave/wallet/top'),
            //         'meta'=> [
            //             'consumer_id' => auth()->user()->id,
            //             'consumer_mac'=> ''
            //         ],
            //         'customer'=> [
            //             'email'=> auth()->user()->email,
            //             'phonenumber'=> auth()->user()->phone,
            //             'name'=> auth()->user()->name,
            //         ],
            //         'customizations'=>[
            //             'title'=> "Wallet Top Up",
            //             'logo'=> "http://www.piedpiper.com/app/themes/joystick-v27/images/logo.png"
            //         ]
            //     ];
            // $url = flutterwavePaymentInitiation($payload)['data']['link'];

            $url = PaystackHelpers::initiateTrasaction($ref, $amount, '/wallet/topup');

            PaystackHelpers::paymentTrasanction(auth()->user()->id, '1', $ref, $request->balance, 'unsuccessful', 'wallet_topup', 'Wallet Topup', 'Payment_Initiation', 'regular');

            return redirect($url);
        } else {

            $curLocation = currentLocation();

            if ($curLocation == 'Nigeria') {
                return back()->with('error', 'You are not allowed to use this feature. Kindly top up with your Virtual Account.');
            }

            $percent = 5 / 100 * $request->balance;
            $amount = $request->balance + $percent + 0.4;
            $ref = time();
            $payload = [
                'tx_ref' => $ref,
                'amount' => $amount,
                'currency' => "USD",
                'redirect_url' => url('flutterwave/wallet/top'),
                'meta' => [
                    'consumer_id' => auth()->user()->id,
                    'consumer_mac' => ''
                ],
                'customer' => [
                    'email' => auth()->user()->email,
                    'phonenumber' => auth()->user()->phone,
                    'name' => auth()->user()->name,
                ],
                'customizations' => [
                    'title' => "Wallet Top Up",
                    // 'logo'=> "http://www.piedpiper.com/app/themes/joystick-v27/images/logo.png"
                ]
            ];
            $url = flutterwavePaymentInitiation($payload)['data']['link'];

            // $url = PaystackHelpers::initiateTrasaction($ref, $amount, '/wallet/topup');
            //Admin Transaction Tablw
            PaymentTransaction::create([
                'user_id' => auth()->user()->id,
                'campaign_id' => '1',
                'reference' => $ref,
                'amount' => $amount,
                'status' => 'unsuccessful',
                'currency' => 'USD',
                'channel' => 'flutterwave',
                'type' => 'wallet_topup',
                'description' => 'Wallet Top Up',
                'tx_type' => 'Credit',
                'user_type' => 'regular'
            ]);

            //PaystackHelpers::paymentTrasanction(auth()->user()->id, '1', $ref, $request->balance, 'unsuccessful', 'wallet_topup', 'Wallet Topup', 'Credit', 'Payment_Initiation', 'regular');

            return redirect($url);

            // return redirect('https://flutterwave.com/pay/topuponfreebyz');
            // $percent = 5/100 * $request->balance;
            // $am = $request->balance + $percent + 1;
            //  $result = paypalPayment($am, '/paypal/return');
            //  if($result['status'] == 'CREATED'){
            //     $url = $result['links'][1]['href'];
            //     PaystackHelpers::paymentTrasanction(auth()->user()->id, '1', $result['id'], $request->balance, 'unsuccessful', 'wallet_topup', 'Wallet Topup', 'Payment_Initiation', 'regular');
            //     return redirect($url);
            //  }

        }
    }

    public function capturePaypal()
    {
        $url = request()->fullUrl();
        $url_components = parse_url($url);
        parse_str($url_components['query'], $params);

        $id = $params['token'];

        $response = capturePaypalPayment($id);

        $user = Auth::user();
        if ($response['status'] == 'COMPLETED') {

            //$ref = $response['purchase_units'][0]['reference_id'];

            // $sellerReceivableBreakdown = $response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'];

            // Access individual values
            // $grossAmount = $sellerReceivableBreakdown['gross_amount']['value'];
            // $paypalFee = $sellerReceivableBreakdown['paypal_fee']['value'];
            // $netAmount = $sellerReceivableBreakdown['net_amount']['value'];

            // $currency = $response['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'];

            // $data['ref'] = $ref;
            // $data['currency'] = $currency;
            // $data['net'] = $netAmount;
            // $data['amount'] = $grossAmount;
            // $data['fee'] = $paypalFee;

            $update = PaymentTransaction::where('reference', $response['id'])->first();
            $update->status = 'successful';
            $update->reference = $response['purchase_units'][0]['reference_id'];
            $update->save();

            $wallet = Wallet::where('user_id', auth()->user()->id)->first();
            $wallet->usd_balance += $update->amount;
            $wallet->save();
            // $name = SystemActivities::getInitials(auth()->user()->name);
            SystemActivities::activityLog(auth()->user(), 'wallet_topup', auth()->user()->name . ' topped up wallet ', 'regular');

            systemNotification($user, 'success', 'Wallet Topup', '$' . $update->amount . ' Wallet Topup Successful');

            return redirect('success');
        } else {
            return redirect('error');
        }
    }

    public function walletTop()
    {
        $url = request()->fullUrl();
        $url_components = parse_url($url);
        parse_str($url_components['query'], $params);

        $ref = $params['trxref']; //paystack
        $res = PaystackHelpers::verifyTransaction($ref); //

        $amount = $res['data']['amount'];

        $percent = 2.90 / 100 * $amount;
        $formatedAm = $percent;
        $newamount = $amount - $formatedAm; //verify transaction
        $creditAmount = $newamount / 100;

        $user = Auth::user();

        if ($res['data']['status'] == 'success') //success - paystack
        {

            PaystackHelpers::paymentUpdate($ref, 'successful'); //update transaction

            $wallet = Wallet::where('user_id', auth()->user()->id)->first();
            $wallet->balance += $creditAmount;
            $wallet->save();

            $name = SystemActivities::getInitials(auth()->user()->name);
            SystemActivities::activityLog(auth()->user(), 'wallet_topup', $name . ' topped up wallet ', 'regular');

            systemNotification($user, 'success', 'Wallet Topup', 'NGN' . $creditAmount . ' Wallet Topup Successful');

            return back()->with('success', 'Wallet Topup Successful'); //redirect('success');
        } else {
            return redirect('error');
        }
    }

    public function flutterwaveWalletTopUp()
    {

        $url = request()->fullUrl();
        $url_components = parse_url($url);
        parse_str($url_components['query'], $params);
        $status = $params['status'];
        if ($status == 'cancelled') {
            return back()->with('error', 'Transaction terminated');
        }
        $tx_id = $params['transaction_id'];
        $ref = $params['tx_ref'];
        $res = flutterwaveVeryTransaction($tx_id);

        if ($res['status'] == 'success') {
            $ver = PaystackHelpers::paymentUpdate($ref, 'successful');

            // $wallet = Wallet::where('user_id', auth()->user()->id)->first();
            // $wallet->balance += $res['data']['amount_settled'];//->amount;
            // $wallet->save();
            creditWallet(auth()->user(), 'Dollar', $res['data']['amount_settled']);

            $name = SystemActivities::getInitials(auth()->user()->name);
            SystemActivities::activityLog(auth()->user(), 'wallet_topup', $name . ' topped up wallet ', 'regular');

            systemNotification(auth()->user(), 'success', 'Wallet Topup', 'NGN' . $ver->amount . ' Wallet Topup Successful');

            return back()->with('success', 'Wallet Topup Successful');
        }
    }

    public function storeWithdraw(Request $request)
    {

        if (auth()->user()->wallet->base_currency == 'Naira') {
            $request->validate([
                'balance' => 'required',
            ]);
            $wallet = Wallet::where('user_id', auth()->user()->id)->first();
            if ($wallet->balance < $request->balance) {
                return back()->with('error', 'Insufficient balance');
            }
            $bankInformation = BankInformation::where('user_id', auth()->user()->id)->first();
            if ($bankInformation) {
                $this->processWithdrawals($request, 'NGN', 'paystack');
                return back()->with('success', 'Withdrawal Successfully queued');
                //  $bankList = PaystackHelpers::bankList();
                //  return view('user.bank_information', ['bankList' => $bankList]);
            } else {
                return redirect('profile')->with('info', 'Please scroll down to Bank Account Details to update your information');
            }
        } else {

            return $request;

            $wallet = Wallet::where('user_id', auth()->user()->id)->first();
            if ($wallet->usd_balance < $request->balance) {
                return back()->with('error', 'Insufficient balance');
            }
            $this->processWithdrawals($request, 'USD', 'paystack');
            return back()->with('success', 'Withdrawal Successfully queued');
        }
    }
    public function switchWallet(Request $request)
    {
        auth()->user()->wallet()->update(['base_currency' => $request->currency]);
        systemNotification(Auth::user(), 'success', 'Currency Switch', 'Currency switched to ' . $request->currency);

        return back()->with('success', 'Currency switched successfully');
    }
}
