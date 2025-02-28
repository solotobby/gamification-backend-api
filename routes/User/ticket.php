<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TicketController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix(
    'ticket'
)->group(function () {
    Route::post('/create', [TicketController::class, 'createTicket']);
    Route::get('/', [TicketController::class, 'getUserTickets']);
    Route::get('/details/{id}', [TicketController::class, 'getTicket']);
    Route::post('/send-message/{ticketId}', [TicketController::class, 'sendMessage']);
    Route::get('/messages/{ticketId}', [TicketController::class, 'getMessages']);
    Route::patch('/close/{ticketId}', [TicketController::class, 'closeTicket']);
});
