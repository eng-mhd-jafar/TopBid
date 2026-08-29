<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

class AdminStatsController extends Controller
{
    public function index()
    {
        $mostActive = Auction::withCount('bids')
            ->orderByDesc('bids_count')
            ->limit(5)
            ->get()
            ->map(fn (Auction $auction) => [
                'id' => $auction->id,
                'title' => $auction->title,
                'bids_count' => $auction->bids_count,
                'current_price' => (float) $auction->current_price,
            ]);

        return ApiResponse::successWithData([
            'auctions' => [
                'pending' => Auction::pendingReview()->count(),
                'live' => Auction::live()->count(),
                'ended' => Auction::ended()->count(),
                'rejected' => Auction::rejected()->count(),
                'total' => Auction::count(),
            ],
            'users' => [
                'total' => User::count(),
                'admins' => User::where('is_admin', true)->count(),
            ],
            'bids' => [
                'total' => Bid::count(),
            ],
            'most_active_auctions' => $mostActive,
        ], 'Stats retrieved successfully');
    }
}
