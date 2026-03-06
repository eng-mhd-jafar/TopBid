<?php

namespace App\Http\Controllers;

use App\DTOs\BidData;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreBidRequest;
use App\Services\BidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                (int) $validated['user_id']
            );

            $bid = $this->bidService->placeBid($bidData);

            return ApiResponse::successWithData($bid, 'Bid placed and broadcasted!');

        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
