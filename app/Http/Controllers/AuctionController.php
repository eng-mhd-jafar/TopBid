<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreAuctionRequest;
use App\Services\AuctionService;
use App\DTOs\AuctionData;
use App\Http\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuctionController extends Controller
{
    public function __construct(protected AuctionService $auctionService)
    {
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
            userId: (int) (Auth::id() ?? 1), // تجريبي
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
            data: $auctions,
            message: 'Auctions retrieved successfully'
        );
    }

    public function show($id)
    {
        $auction = $this->auctionService->getAuctionById($id);
        if (!$auction) {
            return ApiResponse::error('Auction not found', 404);
        }
        return ApiResponse::successWithData($auction, 'Auction retrieved successfully');
    }
}
