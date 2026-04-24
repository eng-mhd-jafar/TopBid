<?php
namespace App\DTOs;

class BidData
{
    public function __construct(
        public int $auctionId,
        public float $amount,
        public int $userId,

    ) {}
}
