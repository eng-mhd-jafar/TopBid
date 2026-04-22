<?php

namespace App\Repositories;

use App\DTOs\AuctionData;
use App\Models\Auction;
use Exception;
use Illuminate\Support\Facades\Log;

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
        return Auction::where('is_active', true)
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
            Log::error('Error fetching auctions by category: '.$e->getMessage());
            throw new Exception('Failed to load auctions. Please try again.');
        }
    }
}
