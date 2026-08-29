<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(protected UserRepository $userRepository)
    {
    }

    public function list(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->userRepository->paginate($search, $perPage);
    }

    public function find(int $id): User
    {
        return $this->userRepository->findOrFail($id);
    }

    /**
     * منع الأدمن من سحب صلاحيته عن نفسه، تجنباً لحالة يقفل فيها كل الأدمن
     * أنفسهم خارج لوحة التحكم بلا وسيلة استرجاع سوى الوصول المباشر لقاعدة البيانات.
     */
    public function setAdminFlag(User $actingAdmin, int $targetId, bool $isAdmin): User
    {
        if ($actingAdmin->id === $targetId && ! $isAdmin) {
            throw ValidationException::withMessages([
                'is_admin' => 'You cannot remove your own admin access.',
            ]);
        }

        $target = $this->userRepository->findOrFail($targetId);

        return $this->userRepository->setAdmin($target, $isAdmin);
    }
}
