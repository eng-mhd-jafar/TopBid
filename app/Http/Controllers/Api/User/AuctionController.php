<?php

namespace App\Http\Controllers\Api\User;

use App\DTOs\AuctionData;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\GetAuctionByCategoryRequest;
use App\Http\Requests\GetAuctionsRequest;
use App\Http\Requests\GetMyAuctionsRequest;
use App\Http\Requests\StoreAuctionRequest;
use App\Http\Resources\AuctionResource;
use App\Models\Auction;
use App\Services\AuctionService;
use Exception;
use Illuminate\Http\JsonResponse;
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
            userId: (int) Auth::id(),
            startingPrice: (float) $validated['starting_price'],
            duration_hours: (int) $validated['duration_hours'],
            specs: $validated['specs'] ?? null,
            imagePath: $imagePath,
        );

        $this->auctionService->createAuction($auctionData);

        return ApiResponse::success('Auction created and pending review', 201);
    }

    public function index(GetAuctionsRequest $request)
    {
        $auctions = $this->auctionService->getActiveAuctions($request->validated());

        return ApiResponse::successWithData(
            data: AuctionResource::collection($auctions)->response()->getData(true),
            message: 'Auctions retrieved successfully'
        );
    }

    public function show(int $id)
    {
        $auction = Auction::withListingData()->findOrFail($id);
        $this->authorize('view', $auction);

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
                AuctionResource::collection($auctions)->response()->getData(true),
                'Auctions by category retrieved successfully'
            );
        } catch (Exception $e) {
            Log::error('Error fetching auctions by category: ' . $e->getMessage());

            return ApiResponse::error('Failed to load auctions. Please try again.', 500);
        }
    }

    public function getMyAuctions(GetMyAuctionsRequest $request): JsonResponse
    {
        $auctions = $this->auctionService->getUserAuctions(
            auth()->id(),
            $request->validated()
        );

        return ApiResponse::successWithData(
            AuctionResource::collection($auctions)->response()->getData(true),
            'Auctions retrieved successfully'
        );
    }

    public function getMyWins(Request $request): JsonResponse
    {
        $auctions = $this->auctionService->getUserWins(
            (int) auth()->id(),
            (int) $request->get('per_page', 10)
        );

        return ApiResponse::successWithData(
            AuctionResource::collection($auctions)->response()->getData(true),
            'Won auctions retrieved successfully'
        );
    }
}
