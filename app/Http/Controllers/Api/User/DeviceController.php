<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreDeviceTokenRequest;

class DeviceController extends Controller
{
    public function saveToken(StoreDeviceTokenRequest $request)
    {
        $request->user()->updateDeviceToken(
            $request->validated()
        );

        return ApiResponse::success('Device registered successfully');
    }
}
