<?php
namespace App\Services;

use App\DTOs\AuctionData;
use App\Http\Helpers\ApiResponse;
use App\Repositories\AuctionRepository;
use Exception;
use Illuminate\Support\Facades\Log;

class AuctionService
{
    public function __construct(
        protected AuctionRepository $auctionRepository
    ) {
    }
    public function createAuction(AuctionData $data)
    {
        try {
            // حساب وقت النهاية بإضافة الساعات القادمة من الـ DTO إلى الوقت الحالي 🕰️
            $endAt = now()->addHours($data->duration_hours);
            $auction = $this->auctionRepository->create($data, $endAt);

            // هنا مستقبلاً: سنقوم باستدعاء وظيفة فحص الـ AI فوراً
            // أو تركها للمهمة المجدولة (Cron Job) التي تناقشنا فيها.

            Log::info("New auction created ID: {$auction->id} by User: {$data->userId}");

            return $auction;
        } catch (Exception $e) {
            Log::error("Error creating auction: " . $e->getMessage());
            throw new Exception("Could not save auction. Please try again.");
        }
    }

    public function getActiveAuctions(int $perPage = 10)
    {
        // هنا يمكننا إضافة منطق إضافي مثل التخزين المؤقت (Caching) لاحقاً لزيادة السرعة
        try {
            $auctions = $this->auctionRepository->getActiveAuctions($perPage);

            return $auctions;
        } catch (Exception $e) {
            Log::error("Error fetching active auctions: " . $e->getMessage());
            throw new Exception("Failed to load auctions. Please try again.");
        }
    }


    public function getAuctionById(int $id)
    {
        try {
            $auction = $this->auctionRepository->findById($id);
            if (!$auction) {
                throw new Exception("Auction not found.");
            }
            return $auction;
        } catch (Exception $e) {
            Log::error("Error fetching auction by ID: " . $e->getMessage());
            throw new Exception("Could not retrieve auction. Please try again.");
        }
    }

}
