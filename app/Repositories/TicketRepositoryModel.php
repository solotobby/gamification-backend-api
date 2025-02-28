<?php


namespace App\Repositories;

use App\Models\Ticket;
use App\Models\TicketMessage;

class TicketRepositoryModel
{

    public function createTicket($data)
    {

        return Ticket::create(
            $data
        );
    }

    public function getTickets($user, $page = null)
    {
        return Ticket::where(
            'user_id',
            $user->id
        )->orderBy(
            'created_at',
            'desc'
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }

    public function getTicketsByAdmin($page = null)
    {
        return Ticket::orderBy(
            'created_at',
            'desc'
        )->paginate(
            25,
            ['*'],
            'page',
            $page
        );
    }

    public function getTicketById($user, $id)
    {
        return Ticket::where(
            'user_id',
            $user->id
        )->where(
            'id',
            $id
        )->first();
    }

    public function getTicketByAdmin($id)
    {
        return Ticket::where(
            'id',
            $id
        )->first();
    }

    public function sendMessage($user, $ticketId, $request)
    {
        return TicketMessage::create([
            'ticket_id' => $ticketId,
            'sender_id' => $user->id,
            'message' => $request->message,
        ]);
    }

    public function getMessages($ticketId, $page = null)
    {
        return TicketMessage::where(
            'ticket_id',
            $ticketId
        )->with(
            'sender:id,name,role'
        )->orderBy(
            'created_at',
            'desc'
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }
}
