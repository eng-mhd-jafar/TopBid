<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceTokenRequest;

class DeviceController extends Controller
{
    public function saveToken(StoreDeviceTokenRequest $request)
    {
        $request->user()->updateDeviceToken(
            $request->validated()
        );

        return response()->json([
            'message' => __('notifications.device_registered_successfully'),
        ]);
    }
    
}
