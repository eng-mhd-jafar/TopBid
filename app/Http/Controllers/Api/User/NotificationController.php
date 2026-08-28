<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(
            (int) $request->get('per_page', 10)
        );

        $payload = NotificationResource::collection($notifications)->response()->getData(true);
        $payload['unread_count'] = $user->unreadNotifications()->count();

        return ApiResponse::successWithData($payload, 'Notifications retrieved successfully');
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success('All notifications marked as read');
    }
}
