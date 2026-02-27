<?php

namespace App\Repositories;

use App\DTOs\BidData;
use App\Models\Bid;

class BidRepository
{
    public function __construct()
    {

    }
    public function create(BidData $data)
    {
        return Bid::create([
            'auction_id' => $data->auctionId,
            'amount' => $data->amount,
            'user_id' => $data->userId
        ]);
    }

}
