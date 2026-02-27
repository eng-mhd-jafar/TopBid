<?php

namespace App\Repositories;

use App\Models\Auction;
use App\Models\User;

class AuctionRepository
{
    public function __construct(protected Auction $auction)
    {
    }

    public function findOrFail(int $id)
    {
        return $this->auction->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    public function update(Auction $auction, float $newPrice)
    {
        $auction->current_price = $newPrice;
        $auction->save();
    }
}
