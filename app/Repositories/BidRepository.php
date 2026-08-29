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

    /** سجل مزايدات مزاد واحد، الأعلى أولاً */
    public function getByAuctionId(int $auctionId, int $perPage): LengthAwarePaginator
    {
        return Bid::where('auction_id', $auctionId)
            ->with('user')
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** أعلى مزايدة قائمة على المزاد، تُقرأ قبل تسجيل مزايدة جديدة */
    public function findHighestForAuction(int $auctionId): ?Bid
    {
        return Bid::where('auction_id', $auctionId)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->first();
    }
}
