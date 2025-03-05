<?php

namespace App\Http\Controllers;

use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function createTicket(Request $request)
    {
        return $this->ticketService->createTicket($request);
    }

    public function getUserTickets()
    {
        return $this->ticketService->getUserTickets();
    }

    public function getTicket($id)
    {
        return $this->ticketService->getTicket($id);
    }

    public function sendMessage(Request $request, $ticketId) {
        return $this->ticketService->sendMessage($request, $ticketId);
    }

    public function getMessages($ticketId) {
        return $this->ticketService->getMessages( $ticketId);
    }

}
