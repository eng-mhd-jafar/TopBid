<?php
namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        return User::withCount(['auctions', 'bids'])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(int $id): User
    {
        return User::withCount(['auctions', 'bids'])->findOrFail($id);
    }

    public function setAdmin(User $user, bool $isAdmin): User
    {
        $user->update(['is_admin' => $isAdmin]);

        return $user;
    }
}
