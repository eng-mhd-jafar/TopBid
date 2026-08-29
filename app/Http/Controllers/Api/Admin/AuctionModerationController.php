<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\GetAdminAuctionsRequest;
use App\Http\Requests\RejectAuctionRequest;
use App\Http\Resources\AuctionResource;
use App\Models\Auction;
use App\Notifications\AuctionStatusNotification;
use App\Services\AuctionService;

class AuctionModerationController extends Controller
{
    public function __construct(protected AuctionService $auctionService)
    {
    }

    public function index(GetAdminAuctionsRequest $request)
    {
        $auctions = $this->auctionService->getAuctionsForModeration($request->validated());

        return ApiResponse::successWithData(
            AuctionResource::collection($auctions)->response()->getData(true),
            'Auctions retrieved successfully'
        );
    }

    public function approve(int $id)
    {
        $auction = Auction::findOrFail($id);
        $this->authorize('moderate', $auction);

        $auction->update([
            'moderation_status' => Auction::STATUS_APPROVED,
            'is_active' => true,
            // إزالة أي سبب رفض سابق؛ لا معنى له بعد الموافقة
            'rejection_reason' => null,
        ]);

        $auction->user->notify(new AuctionStatusNotification(
            $auction,
            AuctionStatusNotification::STATUS_AUCTION_APPROVED
        ));

        return ApiResponse::success('Auction approved successfully');
    }

    public function reject(RejectAuctionRequest $request, int $id)
    {
        $auction = Auction::findOrFail($id);
        $this->authorize('moderate', $auction);

        $reason = $request->validated('reason');

        $auction->update([
            'moderation_status' => Auction::STATUS_REJECTED,
            'is_active' => false,
            'rejection_reason' => $reason,
        ]);

        $auction->user->notify(new AuctionStatusNotification(
            $auction,
            AuctionStatusNotification::STATUS_AUCTION_REJECTED,
            $reason
        ));

        return ApiResponse::success('Auction rejected successfully');
    }
}
