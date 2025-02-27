<?php


namespace App\Repositories;

use App\Models\Ticket;

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

    public function getTicketById($user, $id)
    {
        return Ticket::where(
            'user_id',
            $user->id
        )->where(
            'id',
            $id
        )->get();
    }
}
