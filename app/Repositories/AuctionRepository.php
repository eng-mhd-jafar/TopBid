<?php
namespace App\Repositories;

use App\Models\Auction;
use App\DTOs\AuctionData;

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
        return Auction::findOrFail($id);
    }
    public function getActiveAuctions($perPage = 10)
    {
        return Auction::where('is_active', true)
            ->where('moderation_status', 'approved')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

}
