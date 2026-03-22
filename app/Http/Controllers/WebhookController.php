<?php

namespace App\Http\Controllers;

use App\Mail\GeneralMail;
use App\Mail\UpgradeUser;
use App\Models\PaymentTransaction;
use App\Models\Question;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    public function handle(Request $request){

        Question::create(['content' => $request]);

        $event = $request['event'];

        if($event == 'charge.success'){
            $amount = $request['data']['amount']/100;
            $status = $request['data']['status'];
            $reference = $request['data']['reference'];
            $channel = $request['data']['channel'];
            $currency = $request['data']['currency'];
            $email = $request['data']['customer']['email'];
            $customer_code = $request['data']['customer']['customer_code'];

            $virtualAccount = VirtualAccount::where('customer_id', $customer_code)->first();

            $user = User::where('id', $virtualAccount->user_id)->first();

            $creditUser = creditWallet($user, 'Naira', $amount);
            if($creditUser){

                $transaction = transactionProcessor($user, $reference, $amount, 'successful', $currency, $channel, 'transfer_topup', 'Cash transfer from '.$user->name, 'Credit', 'regular');

                if($transaction){
                    $subject = 'Wallet Credited';
                    $content = 'Congratulations, your wallet has been credited with NGN'.$amount;
                    Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));
                }

                //check wallet stat

                if($user->is_verified == false){
                    if($amount >= 1050){
                        $debitWallet = debitWallet($user, 'Naira', 1050);

                        if($debitWallet){

                            $upgrdate = userNairaUpgrade($user);

                            if($upgrdate){
                                Mail::to($user->email)->send(new UpgradeUser($user));
                            }

                        }
                    }
                }




            }
            return response()->json(['status' => 'success'], 200);

        }else{
            return response()->json(['status' => 'error'], 500);
        }

    }
}

// class WebhookController extends Controller
// {
//     public function __construct(
//         protected DepositService $depositService,
//         protected PaystackServiceProvider $paystack,
//         protected KoraPayServiceProvider $korapay,
//     ) {}

//     public function handlePaystack(Request $request)
//     {
//         // Verify signature
//         $signature = $request->header('x-paystack-signature');
//         $computed  = hash_hmac('sha512', $request->getContent(), config('services.paystack.secretKey'));

//         if ($signature !== $computed) {
//             return response()->json(['message' => 'Invalid signature.'], 401);
//         }

//         $event = $request->input('event');
//         $data  = $request->input('data');

//         if ($event === 'charge.success') {
//             $this->depositService->creditWalletAfterPayment(
//                 reference: $data['reference'],
//                 amount: $data['amount'] / 100,
//                 channel: 'paystack'
//             );
//         }

//         // Handle dedicated account credit
//         if ($event === 'dedicatedaccount.assign.success' || $event === 'charge.success') {
//             // already handled above
//         }

//         return response()->json(['status' => true]);
//     }

//     public function handleKoraPay(Request $request)
//     {
//         // KoraPay sends HMAC in x-korapay-signature
//         $signature = $request->header('x-korapay-signature');
//         $computed  = hash_hmac('sha256', $request->getContent(), config('services.korapay.secret_key'));

//         if ($signature !== $computed) {
//             return response()->json(['message' => 'Invalid signature.'], 401);
//         }

//         $data   = $request->input('data');
//         $status = $data['status'] ?? null;

//         if ($status === 'success') {
//             $this->depositService->creditWalletAfterPayment(
//                 reference: $data['reference'],
//                 amount: $data['amount'],
//                 channel: 'korapay'
//             );
//         }

//         return response()->json(['status' => true]);
//     }

//     public function handleStripe(Request $request)
//     {
//         $payload   = $request->getContent();
//         $sigHeader = $request->header('Stripe-Signature');

//         try {
//             $event = \Stripe\Webhook::constructEvent(
//                 $payload,
//                 $sigHeader,
//                 config('services.stripe.webhook_secret')
//             );
//         } catch (\Exception $e) {
//             return response()->json(['message' => 'Invalid signature.'], 401);
//         }

//         if ($event->type === 'checkout.session.completed') {
//             $session = $event->data->object;
//             if ($session->payment_status === 'paid') {
//                 $this->depositService->creditWalletAfterPayment(
//                     reference: $session->client_reference_id,
//                     amount: $session->amount_total / 100,
//                     channel: 'stripe'
//                 );
//             }
//         }

//         return response()->json(['status' => true]);
//     }
// }
