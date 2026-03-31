<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(protected ProfileService $profileService)
    {
    }

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());
        return ApiResponse::successWithData(new UserResource($user), 'Profile updated successfully');
    }

    public function changePassword(ChangePasswordRequest $request)
    {

        try {
            $this->profileService->changePassword(
                $request->user(),
                $request->old_password,
                $request->password
            );

            return ApiResponse::success('Password updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error($e->errors(), 422);
        }
    }
}