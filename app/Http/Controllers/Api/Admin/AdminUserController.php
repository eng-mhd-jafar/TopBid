<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\AdminListUsersRequest;
use App\Http\Requests\UpdateUserAdminRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AdminUserService;

class AdminUserController extends Controller
{
    public function __construct(protected AdminUserService $adminUserService)
    {
    }

    public function index(AdminListUsersRequest $request)
    {
        $validated = $request->validated();

        $users = $this->adminUserService->list(
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 10)
        );

        return ApiResponse::successWithData(
            AdminUserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully'
        );
    }

    public function show(int $id)
    {
        $user = $this->adminUserService->find($id);

        return ApiResponse::successWithData((new AdminUserResource($user))->resolve(), 'User retrieved successfully');
    }

    public function updateAdmin(UpdateUserAdminRequest $request, int $id)
    {
        // ValidationException من setAdminFlag تصعد إلى المعالج الموحّد
        $user = $this->adminUserService->setAdminFlag(
            $request->user(),
            $id,
            $request->boolean('is_admin')
        );

        return ApiResponse::successWithData((new AdminUserResource($user))->resolve(), 'User updated successfully');
    }
}
