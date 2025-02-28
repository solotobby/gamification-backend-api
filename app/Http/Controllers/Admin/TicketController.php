<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminTicketService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    protected $ticketService;

    public function __construct(AdminTicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function createTicket(Request $request)
    {
        return $this->ticketService->createTicket($request);
    }

    public function getUserTickets()
    {
        return $this->ticketService->getUsersTickets();
    }

    public function closeTicket($id)
    {
        return $this->ticketService->closeTicket($id);
    }

    public function sendMessage(Request $request, $ticketId) {
        return $this->ticketService->sendMessage($request, $ticketId);
    }

    public function getMessages($ticketId) {
        return $this->ticketService->getMessages( $ticketId);
    }

}
