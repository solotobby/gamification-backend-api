<?php

use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TicketController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix(
    'ticket'
)->group(function () {

    // Route::patch('/close/{ticketId}', [TicketController::class, 'closeTicket']);

    Route::post('/create', [FeedbackController::class, 'createFeedback']);
    Route::get('/', [FeedbackController::class, 'getUserFeedbacks']);
    Route::get('/details/{id}', [FeedbackController::class, 'getFeedback']);
    Route::post('/send-message/{feedbackId}', [FeedbackController::class, 'sendReply']);
    Route::get('/messages/{feedbackId}', [FeedbackController::class, 'getReplies']);
    Route::post('/mark-read/{feedbackId}', [FeedbackController::class, 'markAsRead']); 
});
