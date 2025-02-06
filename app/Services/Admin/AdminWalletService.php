<?php

namespace App\Services\Admin;

use App\Mail\GeneralMail;
use App\Repositories\BankRepositoryModel;
use App\Repositories\CampaignRepositoryModel;
use App\Repositories\LogRepositoryModel;
use App\Services\CampaignService;
use App\Repositories\WalletRepositoryModel;
use App\Validators\WalletValidator;
use App\Repositories\WithdrawalRepositoryModel;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminWalletService
{
    protected $withdrawalModel, $bankModel, $validator, $logModel, $walletModel;
    public function __construct(
        WalletRepositoryModel $walletModel,
        WithdrawalRepositoryModel $withdrawalModel,
        WalletValidator $validator,
        BankRepositoryModel $bankModel,
        LogRepositoryModel $logModel,
    ) {
        $this->validator = $validator;
        $this->walletModel = $walletModel;
        $this->withdrawalModel = $withdrawalModel;
        $this->bankModel = $bankModel;
        $this->logModel = $logModel;
    }


    public function getWithdrawalByStatus($request)
    {
        try {
            $statusQuery = strtolower($request->query('status'));

            if ($statusQuery === 'paid') {
                $status = true;
            } elseif ($statusQuery === 'pending') {
                $status = false;
            } else {
                $withdrawals = $this->withdrawalModel->adminWithdrawalRequestLists(); // Fetch all withdrawals
            }

            if (isset($status)) {
                $withdrawals = $this->withdrawalModel->adminWithdrawalRequestLists($status);
            }

            $data = $withdrawals->map(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'user_id' => $withdrawal->user_id,
                    'user_name' => $withdrawal->user->name,
                    'amount' => $withdrawal->amount,
                    'currency' => $withdrawal->base_currency ?? $withdrawal->user->wallet->base_currency,
                    'payment_date' => $withdrawal->next_payment_date,
                    'withdrawal_created_at' => $withdrawal->created_at,
                    'status' => $withdrawal->status ? 'Paid' : 'Pending',
                ];
            });

            $pagination = [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Withdrawals retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }


    public function approveWithdrawal($request)
    {
        $this->validator->AdminDecisionOnWithdrawal($request);

        try {
            $withdrawal = $this->withdrawalModel->getWithdrawalById($request->withdrawal_id, $request->user_id);
            if (!$withdrawal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Withdrawal not found'
                ], 404);
            }

            if ($withdrawal->status) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment has already been processed'
                ], 400);
            }

            $user = $withdrawal->user;
            $bankInformation = $this->bankModel->getUserBank($request->user_id);

            if (!$bankInformation) {
                return response()->json([
                    'status' => false,
                    'message' => 'User bank information not found'
                ], 400);
            }

            // Process Transfer
            // $transfer = $this->transferFund(
            //     $withdrawal->amount * 100,
            //     $bankInformation->recipient_code,
            //     'Freebyz Withdrawal'
            // );

            //response if transfer fail
            // if (!isset($transfer['data']['status']) || !in_array($transfer['data']['status'], ['success', 'pending'])) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Withdrawal processing failed'
            //     ], 500);
            // }

            // Update Withdrawal Status
            $withdrawal->status = true;
            $withdrawal->save();

            // Log activity
            $this->logModel->createLogForWithdrawalPayment($user, $withdrawal->base_currency, $withdrawal->amount);
            // Send email notification
            $content = 'Your withdrawal request has been approved and your account credited successfully. Thank you for choosing Freebyz.com.';
            $subject = 'Withdrawal Request Approved';
            Mail::to($user->email)->send(new GeneralMail(
                $user,
                $content,
                $subject,
                ''
            ));

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal approved successfully',
                'data' => $withdrawal
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
