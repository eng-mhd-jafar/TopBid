<?php

namespace App\Policies;

use App\Models\User;

class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
