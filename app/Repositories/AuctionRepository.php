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
            'moderation_status' => 'pending',
        ]);
    }

    public function findById(int $id): ?Auction
    {
        return Auction::where('id', $id)->lockForUpdate()->first();
    }

    public function getActiveAuctions($perPage = 10)
    {
        return Auction::with(['user', 'category'])
            ->where('is_active', true)
            ->where('moderation_status', 'approved')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
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
            $auctions = Auction::where('category_id', $categoryId)
                ->active()
                ->orderBy('created_at', 'desc')
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

        $query->when($status, function ($q) use ($status) {
            return match ($status) {
                'active' => $q->where('moderation_status', 'approved')
                    ->where('is_active', true)
                    ->where('expires_at', '>', now()),
                'expired' => $q->where(function ($sub) {
                        $sub->where('expires_at', '<=', now())
                        ->orWhere('is_active', false);
                    }),
                'pending' => $q->where('moderation_status', 'pending'),
                'approved' => $q->where('moderation_status', 'approved'),
                'rejected' => $q->where('moderation_status', 'flagged'),
                default => $q,
            };
        });

        return $query->paginate($perPage);
    }
}