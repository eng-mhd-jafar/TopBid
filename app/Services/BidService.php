<?php
namespace App\Services;

use App\DTOs\BidData;
use App\Events\BidPlaced;
use App\Repositories\AuctionRepository;
use App\Repositories\BidRepository;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BidService
{
    public function __construct(protected AuctionRepository $auctionRepository, protected BidRepository $bidRepository)
    {
    }
    public function placeBid(BidData $data)
    {
        $bid = DB::transaction(function () use ($data) {
            $auction = $this->auctionRepository->findById($data->auctionId);
            if ($auction === null) {
                throw new Exception('Auction not found.');
            }
            if ($auction->user_id === $data->userId) {
                throw new Exception('You cannot bid on your own auction.');
            }
            if ($auction->moderation_status !== 'approved' || ! $auction->is_active) {
                throw new Exception('Auction is not open for bidding.');
            }
            if ($auction->expires_at === null || $auction->expires_at->isPast()) {
                throw new Exception('Auction is closed.');
            }
            if ($data->amount <= $auction->current_price) {
                throw new Exception('Bid amount must be higher than current price.');
            }
            $newbid = $this->bidRepository->create($data);
            $this->auctionRepository->update($auction, $data->amount);

            // لو فشل البث، سترمي دالة broadcast استثناء ويتم عمل rollback تلقائياً للترانزاكشن
            broadcast(new BidPlaced($newbid));
            return $newbid;
        });
        return $bid;
    }

    public function getUserBids(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->bidRepository->getByUserId($userId, $perPage);
    }
}
