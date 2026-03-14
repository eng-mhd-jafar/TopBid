<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Models\Auction;

class AuctionModerationController extends Controller
{
    public function approve(int $id)
    {
        $auction = Auction::findOrFail($id);

        $auction->update([
            'moderation_status' => 'approved',
            'is_active' => true,
        ]);

        return ApiResponse::success('Auction approved successfully');
    }

    public function reject(int $id)
    {
        $auction = Auction::findOrFail($id);

        $auction->update([
            'moderation_status' => 'rejected',
            'is_active' => false,
        ]);

        return ApiResponse::success('Auction rejected successfully');
    }
}

