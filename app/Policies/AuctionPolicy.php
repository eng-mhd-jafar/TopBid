<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    public function view(User $user, Auction $auction): bool
    {
        if ($auction->user_id === $user->id) {
            return true;
        }

        return $auction->isLive();
    }

    public function bid(User $user, Auction $auction): bool
    {
        if ($auction->user_id === $user->id) {
            return false;
        }

        return $auction->isLive();
    }

    public function moderate(User $user, Auction $auction): bool
    {
        return $user->is_admin;
    }
}
