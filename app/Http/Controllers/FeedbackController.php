<?php

namespace App\Http\Controllers;

use App\Services\FeedbackService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    protected $feedbackService;

    public function __construct(FeedbackService $feedbackService)
    {
        $this->feedbackService = $feedbackService;
    }

    public function createFeedback(Request $request)
    {
        return $this->feedbackService->createFeedback($request);
    }

    public function getUserFeedbacks()
    {
        return $this->feedbackService->getUserFeedbacks();
    }

    public function getFeedback($id)
    {
        return $this->feedbackService->getFeedback($id);
    }

    public function sendReply(Request $request, $feedbackId)
    {
        return $this->feedbackService->sendReply($request, $feedbackId);
    }

    public function getReplies($feedbackId)
    {
        return $this->feedbackService->getReplies($feedbackId);
    }

    public function markAsRead($feedbackId)
    {
        return $this->feedbackService->markAsRead($feedbackId);
    }
}
