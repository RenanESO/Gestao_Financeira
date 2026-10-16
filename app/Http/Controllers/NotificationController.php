<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function read(Notification $notification, NotificationService $service): JsonResponse
    {
        $notificacao = $service->markRead($notification);

        return response()->json(['id' => $notificacao->id, 'read_at' => $notificacao->read_at]);
    }
}
