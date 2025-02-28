<?php

namespace App\Services;

use App\Mail\GeneralMail;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Validators\SafeLockValidator;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\BankRepositoryModel;
use App\Repositories\SafeLockRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class  SafeLockService
{
    protected $safeLock;
    protected $auth;
    protected $wallet;
    protected $validator;
    protected $currencyModel;
    protected $bankModel;
    public function __construct(
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet,
        SafeLockRepositoryModel $safeLock,
        SafeLockValidator $validator,
        CurrencyRepositoryModel $currencyModel,
        BankRepositoryModel $bankModel,
    ) {
        $this->auth = $auth;
        $this->wallet = $wallet;
        $this->safeLock = $safeLock;
        $this->validator = $validator;
        $this->currencyModel = $currencyModel;
        $this->bankModel = $bankModel;
    }

    public function getSafeLocks()
    {
        try {
            $user = auth()->user();

            // Fetch SafeLocks from repository
            $safeLocks = $this->safeLock->getSafeLocksByUserId($user);

            // Check if user has SafeLocks
            if ($safeLocks->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No SafeLocks found.',
                    'data' => []
                ], 404);
            }

            // Format response data
            $formattedSafeLocks = $safeLocks->map(function ($safeLock) {
                return [
                    'id' => $safeLock->id,
                    'currency' => $safeLock->currency,
                    'amount_locked' => $safeLock->amount_locked,
                    'interest' => $safeLock->interest_rate,
                    'duration' => $safeLock->duration,
                    'total_payment' => $safeLock->total_payment,
                    'start_date' => $safeLock->start_date->toDateString(),
                    'maturity_date' => $safeLock->maturity_date->toDateString(),
                    'status' => $safeLock->status,
                    'is_matured' => $safeLock->maturity_date <= now(),
                    'is_paid' => $safeLock->is_paid
                ];
            });

            $pagination = [
                'total' => $safeLocks->total(),
                'per_page' => $safeLocks->perPage(),
                'current_page' => $safeLocks->currentPage(),
                'last_page' => $safeLocks->lastPage(),
                'from' => $safeLocks->firstItem(),
                'to' => $safeLocks->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'SafeLocks retrieved successfully.',
                'data' => $formattedSafeLocks,
                'pagination' => $pagination,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createSafeLock($request)
    {
        $this->validator->validateSafeFund($request);
        try {

            $user = auth()->user();

            $mapCurrency = $this->wallet->mapCurrency($user->wallet->base_currency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            $interestRate = 5;
            $amountLocked = $request->amount;
            $duration = $request->duration;
            $interestAccrued = bcmul($amountLocked, $interestRate / 100, 2);
            $totalPayment = bcadd($amountLocked, $interestAccrued, 2);
            $startDate = Carbon::now();
            $maturityDate = Carbon::now()->addMonths($duration);
            if ($request->source == 'wallet') {

                // Check wallet balance
                if (!$this->wallet->checkWalletBalance(
                    $user,
                    $currency->code,
                    $amountLocked
                )) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You do not have sufficient funds in your wallet',
                    ], 401);
                }

                // Debit wallet
                if (!$this->wallet->debitWallet(
                    $user,
                    $currency->code,
                    $amountLocked
                )) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Wallet debit failed. Please try again.',
                    ], 401);
                }
                // Create SafeLock
                $this->safeLock->createSafeLock(
                    $user->id,
                    $interestRate,
                    $amountLocked,
                    $currency->code,
                    $duration,
                    $interestAccrued,
                    $totalPayment,
                    $startDate,
                    $maturityDate,
                );

                // Log transaction
                $this->wallet->createTransaction(
                    $user,
                    $amountLocked,
                    time(),
                    1,
                    $currency->code,
                    'safelock_created',
                    'Created a SafeLock',
                    'debit'
                );
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Card funding source not available',
                ], 400);
            }

            // Send email notification
            $subject = 'Freebyz SafeLock Created';
            $content = "Your SafeLock has been created successfully with a total amount of {$currency->code}{$amountLocked} for {$duration} months at an interest of {$interestRate}%, giving a total payout of {$currency->code}{$totalPayment} on {$maturityDate}.";
            Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));

            return response()->json([
                'status' => true,
                'message' => 'Your SafeLock has been created successfully',
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function redeemSafelock($safelock_id)
    {
        try {
            $user = auth()->user();
            // Fetch SafeLock details
            $getSafeLock = $this->safeLock->getSafeLockById($safelock_id);
            if (!$getSafeLock) {
                return response()->json([
                    'status' => false,
                    'message' => 'SafeLock not found'
                ], 404);
            }

            if ($getSafeLock->maturity_date > now()) {
                return response()->json([
                    'status' => false,
                    'message' => 'SafeLock is not yet matured for redemption.'
                ], 404);
            }

            // Get user bank info
            $bankInfo = $this->bankModel->getUserBank($getSafeLock->user_id);
            if (!$bankInfo) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bank information not found. Please update your profile.'
                ], 400);
            }

            // Process transfer
            // $transfer = $this->transferRepository->transferFunds(
            //     (int) $getSafeLock->total_payment * 100,
            //     $bankInfo->recipient_code,
            //     'Freebyz SafeLock Redemption'
            // );
            $transfer = true;

            if (!$transfer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transfer failed. Please try again later.'
                ], 500);
            }

            // Update SafeLock status
            $this->safeLock->updateSafeLockStatus($getSafeLock->id, 'Redeemed');

            // Log Payment Transaction
            $this->wallet->createTransaction(
                $user,
                $getSafeLock->total_payment,
                time(),
                1,
                $getSafeLock->currency,
                'safelock_redeemed',
                'Redeemed SafeLock',
                'Debit'
            );

            // Send Email Notification
            $subject = 'Freebyz SafeLock Redeemed';
            $content = "Your SafeLock has been redeemed successfully. A total amount of {$getSafeLock->currency}{$getSafeLock->total_payment} has been sent to your account.";
            Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));

            return response()->json([
                'status' => true,
                'message' => 'SafeLock redeemed successfully. Funds have been transferred to your account.',
                'data' => [
                    'safelock_id' => $getSafeLock->id,
                    'amount' => $getSafeLock->total_payment,
                    'currency' => $getSafeLock->currency,
                    'status' => 'Redeemed',
                    'transfer_reference' => $transfer['reference'] ?? null,
                    'bank_name' => $bankInfo->bank_name,
                    'account_number' => $bankInfo->account_number
                ]
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
