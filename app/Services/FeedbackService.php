<?php

namespace App\Services;

use App\Repositories\FeedbackRepositoryModel;
use App\Services\Providers\CloudinaryService;
use App\Validators\FeedbackValidator;
use Throwable;

class FeedbackService
{
    protected $feedbackModel;
    protected $cloudinary;
    protected $validator;

    protected $autoReplies = [
        'transfer_issue' => 'Thank you for taking the time to send this transfer issue. We have received it and will act on it accordingly. Please send a screenshot of proof of payment to info@dominahl.com. Thank you once again.',
        'complaint'      => 'Thank you for taking the time to send this complaint. We have received it and will act on it accordingly. Thank you once again.',
        'feedback'       => 'Thank you for taking the time to send this feedback. We have received it and will act on it accordingly. Thank you once again.',
    ];

    public function __construct(
        FeedbackRepositoryModel $feedbackModel,
        CloudinaryService $cloudinary,
        FeedbackValidator $validator,
    ) {
        $this->feedbackModel = $feedbackModel;
        $this->cloudinary    = $cloudinary;
        $this->validator     = $validator;
    }

    public function createFeedback($request)
    {
        $this->validator->validateFeedbackCreation($request);

        try {
            $user     = auth()->user();
            $proofUrl = null;

            if ($request->filled('proof')) {
                $file     = $request->proof;
                $proofUrl = $this->cloudinary->uploadBase64Image($file);
            }

            $data = [
                'user_id'  => $user->id,
                'category' => $request->category,
                'message'  => $request->message,
                'proof_url' => $proofUrl,
                'status'   => false,
            ];

            $feedback = $this->feedbackModel->createFeedback($data);

            // Send the user's opening message as first reply
            $this->feedbackModel->sendReply($user, $feedback->id, $request->message);

            return response()->json([
                'status'  => true,
                'message' => 'Feedback submitted successfully.',
                'data'    => $feedback,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function getUserFeedbacks()
    {
        try {
            $user    = auth()->user();
            $feedbacks = $this->feedbackModel->getFeedbacks($user);

            $data = [];
            foreach ($feedbacks as $feedback) {
                $data[] = $this->formatFeedback($feedback);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'User feedbacks retrieved.',
                'data'       => $data,
                'pagination' => $this->buildPagination($feedbacks),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error processing request',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getFeedback($id)
    {
        $user     = auth()->user();
        $feedback = $this->feedbackModel->getFeedbackById($user, $id);

        if (!$feedback) {
            return response()->json([
                'status'  => false,
                'message' => 'Feedback not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Feedback retrieved.',
            'data'    => $this->formatFeedback($feedback),
        ], 200);
    }

    public function sendReply($request, $feedbackId)
    {
        $this->validator->validateMessageSending($request);

        try {
            $user     = auth()->user();
            $feedback = $this->feedbackModel->getFeedbackById($user, $feedbackId);

            if (!$feedback) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Feedback not found',
                ], 404);
            }

            $textMessage = $request->filled('message') ? $request->message : null;

            $isImage  = false;
            $imageUrl = null;
            // $message  = $request->message;

            if ($request->filled('image')) {
                $image    = $request->image;
                $imageUrl = $this->cloudinary->uploadBase64Image($image);
                $isImage  = true;
                // $message  = $imageUrl;
            }

            if (!$textMessage && !$imageUrl) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Reply must contain a message or an image.',
                ], 422);
            }

            $this->feedbackModel->sendReply(
                $user,
                $feedback->id,
                $textMessage,
                $isImage,
                $imageUrl
            );

            $feedback->status = true;
            $feedback->save();

            $replies = $this->getRepliesFormatted($feedbackId);

            return response()->json([
                'status'  => true,
                'message' => 'Reply sent successfully',
                'data'    => $replies,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function getReplies($feedbackId)
    {
        $user     = auth()->user();
        $feedback = $this->feedbackModel->getFeedbackById($user, $feedbackId);

        if (!$feedback) {
            return response()->json([
                'status'  => false,
                'message' => 'Feedback not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Replies retrieved successfully',
            'data'    => $this->getRepliesFormatted($feedbackId),
        ]);
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function getRepliesFormatted($feedbackId)
    {
        $replies = $this->feedbackModel->getReplies($feedbackId);

        $data = [];
        foreach ($replies as $reply) {

            $type = 'text';
            if ($reply->image_url && $reply->text_message) {
                $type = 'mixed';    // both text and image
            } elseif ($reply->image_url) {
                $type = 'image';    // image only
            }
            $data[] = [
                'id'          => $reply->id,
                'sender_id'   => $reply->user_id,
                'sender_name' => in_array($reply->user->role, ['admin', 'super_admin'])
                    ? 'Freebyz Support'
                    : $reply->user->name,
                'sender_role' => $reply->user->role,
                'type'        => $type,
                'message'     => $reply->text_message ?? $reply->message,
                // 'is_image'    => (bool) $reply->is_image,
                'image_url'   => $reply->image_url,
                'created_at'  => $reply->created_at,
                'updated_at'  => $reply->updated_at,
            ];
        }

        return [
            'replies'    => $data,
            'pagination' => $this->buildPagination($replies),
        ];
    }

    private function formatFeedback($feedback)
    {
        return [
            'id'         => $feedback->id,
            'user_id'    => $feedback->user_id,
            'category'   => $feedback->category,
            'message'    => $feedback->message,
            'proof_url'  => $feedback->proof_url,
            'status'     => $feedback->status,
            'created_at' => $feedback->created_at,
            'updated_at' => $feedback->updated_at,
        ];
    }

    private function buildPagination($paginator)
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
