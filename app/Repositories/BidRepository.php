<?php

namespace App\Repositories;

use App\DTOs\BidData;
use App\Models\Bid;
use Illuminate\Pagination\LengthAwarePaginator;

class BidRepository
{
    public function create(BidData $data): Bid
    {
        return Bid::create([
            'auction_id' => $data->auctionId,
            'amount' => $data->amount,
            'user_id' => $data->userId,
        ]);
    }

    public function getByUserId(int $userId, int $perPage): LengthAwarePaginator
    {
        return Bid::where('user_id', $userId)
            ->with('auction')
            ->latest()
            ->paginate($perPage);
    }
}
