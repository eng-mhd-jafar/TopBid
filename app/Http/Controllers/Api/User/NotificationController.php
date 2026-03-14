<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(10);
        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء'
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json([
            'success' => true,
            'message' => 'تم تحديد جميع الإشعارات كمقروءة'
        ]);
    }
}

