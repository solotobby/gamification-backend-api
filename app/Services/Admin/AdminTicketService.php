<?php

namespace App\Services\Admin;

use App\Repositories\TicketRepositoryModel;
use App\Services\Providers\AWSServiceProvider;
use App\Services\TicketService;
use App\Validators\TicketValidator;
use Throwable;

class AdminTicketService
{
    protected $ticketModel;
    protected $awsService;
    protected $validator;
    protected $ticketService;
    public function __construct(
        TicketRepositoryModel $ticketModel,
        AWSServiceProvider $awsService,
        TicketValidator $validator,
        TicketService $ticketService,
    ) {
        $this->ticketModel = $ticketModel;
        $this->awsService = $awsService;
        $this->validator = $validator;
        $this->ticketService  = $ticketService;
    }

    public function createTicket($request)
    {

        $this->validator->validateTicketCreation($request);
        try {
            $user = auth()->user();
            $proofUrl = 'no image';
            if ($request->hasFile('proof')) {
                $file = $request->hasFile('proof');
                $filePath = 'proofs/' . time() . '_' . $file->extension();
                $proofUrl = $this->awsService->uploadImage($file, $filePath);
            }
            $data = [
                'user_id' => $user->id,
                'subject' => $request->subject,
                'message' => $request->message,
                'proof_url' => $proofUrl,
                'status' => 'Open'
            ];
            $ticket = $this->ticketModel->createTicket($data);

            $this->ticketModel->sendMessage($user, $ticket->id, $request);

            return response()->json([
                'status' => true,
                'message' => 'Ticket raised successfully.',
                'data' => $ticket,

            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function getUsersTickets()
    {
        $tickets = $this->ticketModel->getTicketsByAdmin();

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = [
                'id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'user_name' => $ticket->user->name,
                'user_email' => $ticket->user->email,
                'subject' => $ticket->subject,
                'message' => $ticket->message,
                'proof_url' => $ticket->proof_url,
                'status' => ($ticket->status),
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at
            ];
        }
        $pagination = [
            'total' => $tickets->total(),
            'per_page' => $tickets->perPage(),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'from' => $tickets->firstItem(),
            'to' => $tickets->lastItem(),
        ];
        return response()->json([
            'status' => true,
            'message' => 'Users tickets retrieved.',
            'data' => $data,
            'pagination' => $pagination,
        ], 200);
    }

    public function sendMessage($request, $ticketId)
    {
        // return $ticketId;
        $this->validator->validateMessageSending($request);
        try {
            $user = auth()->user();
            $ticket = $this->ticketModel->getTicketByAdmin($ticketId);
            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            $this->ticketModel->sendMessage($user, $ticket->id, $request);

            if ($ticket->status === 'open') {
                $ticket->update(['status' => 'in_progress']);
            }
            $messages = $this->ticketService->messages($ticketId);
            return response()->json([
                'status' => true,
                'message' => 'Message sent successfully',
                'data' => $messages
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request'
            ], 500);
        }
    }

    // Get messages for a ticket
    public function getMessages($ticketId)
    {
        $ticket = $this->ticketModel->getTicketByAdmin($ticketId);
        if (!$ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        $messages = $this->ticketService->messages($ticketId);

        return response()->json([
            'status' => true,
            'message' => 'Messages retrieved successfully',
            'data' => $messages
        ]);
    }

    // Close a ticket
    public function closeTicket($ticketId)
    {
        $ticket = $this->ticketModel->getTicketByAdmin($ticketId);
        if (!$ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        $ticket->update(['status' => 'closed']);
        return response()->json([
            'status' => true,
            'message' => 'Ticket closed successfully'
        ]);
    }
}
