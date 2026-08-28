<?php

namespace App\Repositories;

use App\DTOs\AuctionData;
use App\Models\Auction;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class AuctionRepository
{
    public function create(AuctionData $data, $endAt): Auction
    {
        return Auction::create([
            'title' => $data->title,
            'description' => $data->description,
            'category_id' => $data->categoryId,
            'user_id' => $data->userId,
            'starting_price' => $data->startingPrice,
            'current_price' => $data->startingPrice,
            'duration_hours' => $data->duration_hours,
            'expires_at' => $endAt,
            'specs' => $data->specs,
            'image_path' => $data->imagePath,
            'moderation_status' => Auction::STATUS_PENDING,
        ]);
    }

    public function findById(int $id): ?Auction
    {
        return Auction::where('id', $id)->lockForUpdate()->first();
    }

    public function getActiveAuctions($perPage = 10)
    {
        return Auction::with(['user', 'category'])
            ->live()
            ->latest()
            ->paginate($perPage);
    }

    public function update(Auction $auction, float $newPrice): void
    {
        $auction->current_price = $newPrice;
        $auction->save();
    }

    public function getAuctionsByCategory($categoryId, $perPage = 10)
    {
        try {
            $auctions = Auction::with(['user', 'category'])
                ->where('category_id', $categoryId)
                ->live()
                ->latest()
                ->paginate($perPage);
            return $auctions;
        } catch (Exception $e) {
            Log::error('Error fetching auctions by category: ' . $e->getMessage());
            throw new Exception('Failed to load auctions. Please try again.');
        }
    }

    public function getByUserId(int $userId, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = Auction::where('user_id', $userId)
            ->with(['category', 'user']) // Eager Loading للأداء
            ->latest();

        return $this->applyStatusFilter($query, $status)->paginate($perPage);
    }

    /** المزادات التي فاز بها المستخدم بعد إغلاقها */
    public function getWonByUserId(int $userId, int $perPage): LengthAwarePaginator
    {
        return Auction::wonBy($userId)
            ->with(['category', 'user', 'winningBid'])
            ->orderByDesc('closed_at')
            ->paginate($perPage);
    }

    private function applyStatusFilter($query, ?string $status)
    {
        $query->when($status, fn ($q) => match ($status) {
            'active' => $q->live(),
            'expired' => $q->ended(),
            'pending' => $q->pendingReview(),
            'approved' => $q->approved(),
            'rejected' => $q->rejected(),
            default => $q,
        });

        return $query;
    }
}