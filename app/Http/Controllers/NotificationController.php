<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return $this->notificationService->getUserNotifications();
    }

    public function markRead(int $id)
    {
        return $this->notificationService->markRead($id);
    }

    public function markAllRead()
    {
        return $this->notificationService->markAllRead();
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        return $this->notificationService->updateFcmToken($request->fcm_token);
    }
}
