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
});
