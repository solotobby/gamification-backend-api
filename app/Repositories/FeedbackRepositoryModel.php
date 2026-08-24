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
        return FeedbackReplies::create([
            'feedback_id'  => $feedbackId,
            'user_id'      => $user->id,
            'message'      => $message ?? $imageUrl,
            'text_message' => $message,
            'is_image'     => $isImage,
            'image_url'    => $imageUrl,
            'read_at'      => null,
        ]);
    }

    public function getReplies($feedbackId, $page = null)
    {
        return FeedbackReplies::where('feedback_id', $feedbackId)
            ->with('user:id,name,role')
            ->latest()
            ->paginate(10, ['*'], 'page', $page);
    }

    /**
     * Mark every reply NOT sent by $viewerUserId as read.
     * Called whenever a party opens/polls a thread — marks the other party's messages seen.
     */
    public function markRepliesAsRead($feedbackId, $viewerUserId): int
    {
        return FeedbackReplies::where('feedback_id', $feedbackId)
            ->where('user_id', '!=', $viewerUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Count of unread replies in a thread, from the viewer's perspective
     * (i.e. messages sent by the OTHER party that viewer hasn't read yet).
     */
    public function getUnreadCount($feedbackId, $viewerUserId): int
    {
        return FeedbackReplies::where('feedback_id', $feedbackId)
            ->where('user_id', '!=', $viewerUserId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Bulk unread counts keyed by feedback_id, for a list of feedback IDs — avoids N+1
     * when rendering a ticket list with per-ticket unread badges.
     */
    public function getUnreadCountsBulk(array $feedbackIds, $viewerUserId): array
    {
        return FeedbackReplies::whereIn('feedback_id', $feedbackIds)
            ->where('user_id', '!=', $viewerUserId)
            ->whereNull('read_at')
            ->selectRaw('feedback_id, COUNT(*) as unread_count')
            ->groupBy('feedback_id')
            ->pluck('unread_count', 'feedback_id')
            ->all();
    }
}
