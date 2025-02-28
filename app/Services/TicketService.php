<?php

namespace App\Services;

use App\Repositories\TicketRepositoryModel;
use App\Services\Providers\AWSServiceProvider;
use App\Validators\TicketValidator;
use Throwable;

class TicketService
{
    protected $ticketModel;
    protected $awsService;
    protected $validator;
    public function __construct(
        TicketRepositoryModel $ticketModel,
        AWSServiceProvider $awsService,
        TicketValidator $validator,
    ) {
        $this->ticketModel = $ticketModel;
        $this->awsService = $awsService;
        $this->validator = $validator;
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

    public function getUserTickets()
    {
        $user = auth()->user();
        $tickets = $this->ticketModel->getTickets($user);

        $data = [];

        foreach ($tickets as $ticket) {
            $data[] = [
                'id' => $ticket->id,
                'user_id' => $ticket->user_id,
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
            'message' => 'User tickets retrieved.',
            'data' => $data,
            'pagination' => $pagination,
        ], 200);
    }

    public function getTicket($id)
    {
        $user = auth()->user();
        $ticket = $this->ticketModel->getTicketById($user, $id);

        return response()->json([
            'status' => true,
            'message' => 'User ticket retrieved.',
            'data' => $ticket,
        ], 200);
    }

    public function sendMessage($request, $ticketId)
    {
       // return $ticketId;
        $this->validator->validateMessageSending($request);
        try {
            $user = auth()->user();
            $ticket = $this->ticketModel->getTicketById($user, $ticketId);
            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            $message = $this->ticketModel->sendMessage($user, $ticket->id, $request);
           // return $message;

           $messages = $this->messages($ticketId);
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
        $user = auth()->user();
        $ticket = $this->ticketModel->getTicketById($user, $ticketId);
        if (!$ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        $messages = $this->messages($ticketId);

        return response()->json([
            'status' => true,
            'message' => 'Messages retrieved successfully',
            'data' => $messages
        ]);
    }

    // function to format messages
    public function messages($ticketId){
        $messages = $this->ticketModel->getMessages($ticketId);

        foreach($messages as $message){
            $data[] = [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name,
                'sender_role' => $message->sender->role,
                'message' => $message->message,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at
            ];

        }
        $pagination = [
            'total' => $messages->total(),
            'per_page' => $messages->perPage(),
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'from' => $messages->firstItem(),
            'to' => $messages->lastItem(),
        ];

        return [
            'messages' => $data,
            'pagination' => $pagination,
        ];
    }
}
