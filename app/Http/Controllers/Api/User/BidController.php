<?php

namespace App\Http\Controllers\Api\User;

use App\DTOs\BidData;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreBidRequest;
use App\Services\BidService;

class BidController extends Controller
{
    public function __construct(protected BidService $bidService)
    {
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

