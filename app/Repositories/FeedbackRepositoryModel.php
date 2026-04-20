<?php

namespace App\Repositories;

use App\Models\Feedback;
use App\Models\FeedbackReplies;

class FeedbackRepositoryModel
{
    public function createFeedback($data)
    {
        return Feedback::create($data);
    }

    public function getFeedbacks($user, $page = null)
    {
        return Feedback::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'page', $page);
    }

    public function getFeedbacksByAdmin($page = null)
    {
        return Feedback::orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'page', $page);
    }

    public function getFeedbackById($user, $id)
    {
        return Feedback::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
    }

    public function getFeedbackByAdmin($id)
    {
        return Feedback::where('id', $id)->first();
    }

    public function sendReply($user, $feedbackId, $message, $isImage = false, $imageUrl = null)
    {
        // return FeedbackReplies::create([
        //     'feedback_id'   => $feedbackId,
        //     'user_id'       => $user->id,
        //     'message'       => $message ?? $imageUrl,
        //     'is_image'      => $isImage,
        //     'image_url'     => $imageUrl,
        // ]);

         return FeedbackReplies::create([
        'feedback_id'  => $feedbackId,
        'user_id'      => $user->id,
        'message'      => $message ?? $imageUrl,        // legacy fallback
        'text_message' => $message,        // new text field
        'is_image'     => $isImage,
        'image_url'    => $imageUrl,
    ]);
    }

    public function getReplies($feedbackId, $page = null)
    {
        return FeedbackReplies::where('feedback_id', $feedbackId)
            ->with('user:id,name,role')
            ->latest()
            ->paginate(10, ['*'], 'page', $page);
    }
}
