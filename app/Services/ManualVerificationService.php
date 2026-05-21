<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Mail\GeneralMail;
use App\Models\ManualVerification;
use App\Repositories\WalletRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Services\Providers\CloudinaryService;
use Database\Seeders\NotificationSeeder;
use Illuminate\Support\Facades\Storage;
use Mail;
use Throwable;

class ManualVerificationService
{
    public function __construct(
        protected WalletRepositoryModel $walletModel,
        protected AuthRepositoryModel $authModel,
        protected CloudinaryService $cloudinary
    ) {}

    public function submit($request)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:manual,bank_transfer,crypto,paystack,korapay',
            'reference'      => 'nullable|string',
            'proof_image'    => 'required|image|max:2048',
        ]);

        try {
            $user = auth()->user();

            // if ($user->is_verified) {
            //     return response()->json(['status' => false, 'message' => 'Account is already verified.'], 422);
            // }

            // Check no pending verification
            // $pending = ManualVerification::where('user_id', $user->id)
            //     ->where('status', 'pending')
            //     ->exists();

            // if ($pending) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'You already have a pending verification. Please wait for admin review.',
            //     ], 422);
            // }

            $file = $request->file('proof_image');
            $imagePath = $this->cloudinary->uploadImage($file);

            ManualVerification::create([
                'user_id'        => $user->id,
                'payment_method' => $request->payment_method,
                'reference'      => $request->reference,
                'proof_image'    => $imagePath,
                'amount'         => $request->amount,
                'currency'       => $this->walletModel->mapCurrency($user->wallet->base_currency),
                'status'         => 'pending',
            ]);

            // event(new NotificationEvent(
            //     user: $user,
            //     title: 'Verification Submitted',
            //     body: 'Your manual verification has been submitted and is under review.',
            //     type: 'verification',
            // ));

            app(NotificationService::class)->createNotification(
                user: $user,
                title: 'Verification Submitted',
                body: "Your manual verification has been submitted and is under review.",
                type: 'verification',
                // data: ['amount' => $amount, 'currency' => $currency, 'reference' => $reference],
            );

            $subject = 'New Manual Payment Submitted';
            $content = 'A manual verification has been submitted by ' . auth()->user()->name . '. Please attend to it.';
            $url = 'https://dashboard.freebyz.com/admin/manual-fundings';

            Mail::to('freebyzcom@gmail.com')
                ->bcc('favour@freebyztechnologies.com')
                ->send(new GeneralMail(auth()->user(), $content, $subject, $url));

            return response()->json([
                'status'  => true,
                'message' => 'Verification submitted. Admin will review within 24 hours.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error submitting verification.'
            ], 500);
        }
    }

    // Admin approves/rejects a manual verification
    public function review(int $verificationId, string $action, ?string $note = null)
    {
        try {
            $verification = ManualVerification::with('user')->findOrFail($verificationId);

            if ($verification->status !== 'pending') {
                return response()->json(['status' => false, 'message' => 'Already reviewed.'], 422);
            }

            $user = $verification->user;

            if ($action === 'approve') {
                $verification->update(['status' => 'approved', 'admin_note' => $note]);

                // Credit wallet + mark verified
                $this->walletModel->creditWallet($user, $verification->currency, $verification->amount);
                $this->authModel->updateUserVerification($user);

                event(new NotificationEvent(
                    user: $user,
                    title: 'Verification Approved',
                    body: "Your account has been verified and {$verification->currency} {$verification->amount} credited.",
                    type: 'verification',
                ));
            } else {
                $verification->update(['status' => 'rejected', 'admin_note' => $note]);

                event(new NotificationEvent(
                    user: $user,
                    title: 'Verification Rejected',
                    body: 'Your manual verification was rejected. ' . ($note ?? 'Contact support for details.'),
                    type: 'verification',
                ));
            }

            return response()->json(['status' => true, 'message' => "Verification {$action}d."]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error processing review.'], 500);
        }
    }
}
