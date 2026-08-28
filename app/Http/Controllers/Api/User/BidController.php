<?php

namespace App\Http\Controllers\Api\User;

use App\DTOs\BidData;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreBidRequest;
use App\Http\Resources\BidResource;
use App\Services\BidService;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function __construct(protected BidService $bidService)
    {
    }

    public function index(Request $request)
    {
        $bids = $this->bidService->getUserBids(
            (int) $request->user()->id,
            (int) $request->get('per_page', 10)
        );

        return ApiResponse::successWithData(
            BidResource::collection($bids)->response()->getData(true),
            'Bids retrieved successfully'
        );
    }

    public function store(StoreBidRequest $request)
    {
        $validated = $request->validated();
        try {
            $bidData = new BidData(
                (int) $validated['auction_id'],
                (float) $validated['amount'],
                (int) $request->user()->id
            );
            $this->bidService->placeBid($bidData);
            return ApiResponse::success('Bid placed and broadcasted!', 200);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}

