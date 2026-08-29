<?php
namespace App\Services;

use App\DTOs\BidData;
use App\Events\BidPlaced;
use App\Notifications\OutbidNotification;
use App\Repositories\AuctionRepository;
use App\Repositories\BidRepository;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BidService
{
    public function __construct(protected AuctionRepository $auctionRepository, protected BidRepository $bidRepository)
    {
    }
    public function placeBid(BidData $data)
    {
        $result = DB::transaction(function () use ($data) {
            $auction = $this->auctionRepository->findById($data->auctionId);
            if ($auction === null) {
                throw new Exception('Auction not found.');
            }
            if ($auction->user_id === $data->userId) {
                throw new Exception('You cannot bid on your own auction.');
            }
            if (! $auction->isApproved() || ! $auction->is_active) {
                throw new Exception('Auction is not open for bidding.');
            }
            if ($auction->hasEnded()) {
                throw new Exception('Auction is closed.');
            }
            if ($data->amount <= $auction->current_price) {
                throw new Exception('Bid amount must be higher than current price.');
            }

            // يُقرأ قبل تسجيل المزايدة الجديدة، وإلا صارت هي نفسها الأعلى
            $previousHighest = $this->bidRepository->findHighestForAuction($auction->id);

            $newbid = $this->bidRepository->create($data);
            $this->auctionRepository->update($auction, $data->amount);

            // لو فشل البث، سترمي دالة broadcast استثناء ويتم عمل rollback تلقائياً للترانزاكشن
            broadcast(new BidPlaced($newbid));

            return [
                'bid' => $newbid,
                'auction' => $auction,
                // من زايد على نفسه لا يُشعَر بأنه تجاوز نفسه
                'outbidUser' => $previousHighest !== null && $previousHighest->user_id !== $data->userId
                    ? $previousHighest->user
                    : null,
            ];
        });

        $this->notifyOutbidUser($result);

        return $result['bid'];
    }

    /**
     * إشعار تجاوز المزايدة أثر جانبي يقع بعد نجاح الترانزاكشن.
     *
     * فشل خدمة الدفع الخارجية لا يجوز أن يُظهر للمزايد أن مزايدته فشلت
     * وهي مسجّلة فعلاً، ولا أن يُرجِع ترانزاكشن نجحت.
     *
     * @param  array{bid: \App\Models\Bid, auction: \App\Models\Auction, outbidUser: ?\App\Models\User}  $result
     */
    private function notifyOutbidUser(array $result): void
    {
        if ($result['outbidUser'] === null) {
            return;
        }

        try {
            $result['outbidUser']->notify(
                new OutbidNotification($result['auction'], $result['bid'])
            );
        } catch (Throwable $e) {
            Log::error(
                "Outbid notification failed for auction {$result['auction']->id}: ".$e->getMessage()
            );
        }
    }

    public function getUserBids(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->bidRepository->getByUserId($userId, $perPage);
    }

    public function getAuctionBids(int $auctionId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->bidRepository->getByAuctionId($auctionId, $perPage);
    }
}
