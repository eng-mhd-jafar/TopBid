<?php

namespace App\Http\Controllers\Api\User;

use App\DTOs\AuctionData;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\GetAuctionByCategoryRequest;
use App\Http\Requests\StoreAuctionRequest;
use App\Http\Resources\AuctionResource;
use App\Services\AuctionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuctionController extends Controller
{
    private $auctionService;

    public function __construct(AuctionService $auctionService)
    {
        $this->auctionService = $auctionService;
    }

    public function store(StoreAuctionRequest $request)
    {
        $validated = $request->validated();

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('auctions', 'public')
            : null;

        $auctionData = new AuctionData(
            title: $validated['title'],
            description: $validated['description'],
            categoryId: (int) $validated['category_id'],
            userId: (int) (Auth::id() ?? 1),
            startingPrice: (float) $validated['starting_price'],
            duration_hours: (int) $validated['duration_hours'],
            specs: $validated['specs'] ?? null,
            imagePath: $imagePath,
        );

        $this->auctionService->createAuction($auctionData);

        return ApiResponse::success('Auction created and pending review', 201);
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $auctions = $this->auctionService->getActiveAuctions($perPage);

        return ApiResponse::successWithData(
            data: AuctionResource::collection($auctions),
            message: 'Auctions retrieved successfully'
        );
    }

    public function show($id)
    {
        $auction = $this->auctionService->getAuctionById($id);
        if (! $auction) {
            return ApiResponse::error('Auction not found', 404);
        }

        return ApiResponse::successWithData(
            new AuctionResource($auction),
            'Auction retrieved successfully'
        );
    }

    public function getAuctionsByCategory(GetAuctionByCategoryRequest $request)
    {
        $validated = $request->validated();
        $perPage = $request->get('per_page', 10);
        try {
            $auctions = $this->auctionService->getAuctionsByCategory($validated['category_id'], $perPage);

            return ApiResponse::successWithData(
                AuctionResource::collection($auctions),
                'Auctions by category retrieved successfully'
            );
        } catch (Exception $e) {
            Log::error('Error fetching auctions by category: '.$e->getMessage());

            return ApiResponse::error('Failed to load auctions. Please try again.', 500);
        }
    }
}
