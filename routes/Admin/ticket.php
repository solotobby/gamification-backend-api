<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\TicketController;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin/tickets'
)->group(function () {
   // Route::post('/create', [TicketController::class, 'createTicket']);
   // Route::get('/details/{id}', [TicketController::class, 'getTicket']);

   Route::get('/', [TicketController::class, 'getUserTickets']);
    Route::post('/send-message/{ticketId}', [TicketController::class, 'sendMessage']);
    Route::get('/messages/{ticketId}', [TicketController::class, 'getMessages']);
    Route::patch('/close/{ticketId}', [TicketController::class, 'closeTicket']);
});
