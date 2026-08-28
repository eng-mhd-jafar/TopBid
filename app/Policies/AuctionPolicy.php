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

        return $auction->moderation_status === 'approved'
            && $auction->is_active
            && $auction->expires_at !== null
            && $auction->expires_at->isFuture();
    }

    public function bid(User $user, Auction $auction): bool
    {
        if ($auction->user_id === $user->id) {
            return false;
        }

        return $auction->moderation_status === 'approved'
            && $auction->is_active
            && $auction->expires_at !== null
            && $auction->expires_at->isFuture();
    }

    public function moderate(User $user, Auction $auction): bool
    {
        return $user->is_admin;
    }
}
