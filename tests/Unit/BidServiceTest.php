<?php

use App\DTOs\BidData;
use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Support\Facades\Event;

/**
 * هذه الاختبارات تستدعي BidService مباشرةً، لأن StoreBidRequest::authorize
 * يوقف معظم هذه الحالات بـ 403 قبل أن تصل الخدمة عبر HTTP.
 */

beforeEach(function () {
    $this->service = app(BidService::class);
});

it('throws when the auction does not exist', function () {
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData(9999, 150.0, $bidder->id)))
        ->toThrow(Exception::class, 'Auction not found.');
});

it('throws when bidding on your own auction', function () {
    $owner = User::factory()->create();
    $auction = Auction::factory()->approved()->create([
        'user_id' => $owner->id,
        'current_price' => 100,
    ]);

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 150.0, $owner->id)))
        ->toThrow(Exception::class, 'You cannot bid on your own auction.');
});

it('throws when the auction is not approved', function () {
    $auction = Auction::factory()->pending()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 150.0, $bidder->id)))
        ->toThrow(Exception::class, 'Auction is not open for bidding.');
});

it('throws when the auction is approved but inactive', function () {
    $auction = Auction::factory()->approved()->create([
        'is_active' => false,
        'current_price' => 100,
    ]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 150.0, $bidder->id)))
        ->toThrow(Exception::class, 'Auction is not open for bidding.');
});

it('throws when the auction has already expired', function () {
    $auction = Auction::factory()->expired()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 150.0, $bidder->id)))
        ->toThrow(Exception::class, 'Auction is closed.');
});

it('throws when the auction has no expiry set', function () {
    $auction = Auction::factory()->approved()->create([
        'expires_at' => null,
        'current_price' => 100,
    ]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 150.0, $bidder->id)))
        ->toThrow(Exception::class, 'Auction is closed.');
});

it('throws when the amount equals the current price', function () {
    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 100.0, $bidder->id)))
        ->toThrow(Exception::class, 'Bid amount must be higher than current price.');
});

it('throws when the amount is below the current price', function () {
    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    expect(fn () => $this->service->placeBid(new BidData($auction->id, 50.0, $bidder->id)))
        ->toThrow(Exception::class, 'Bid amount must be higher than current price.');
});

it('stores the bid against the user carried by the dto', function () {
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    $bid = $this->service->placeBid(new BidData($auction->id, 150.0, $bidder->id));

    expect($bid)->toBeInstanceOf(Bid::class)
        ->and($bid->user_id)->toBe($bidder->id)
        ->and((float) $bid->amount)->toBe(150.0)
        ->and((float) $auction->fresh()->current_price)->toBe(150.0);

    Event::assertDispatched(BidPlaced::class);
});

it('rolls back the whole transaction when a guard fails', function () {
    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    try {
        $this->service->placeBid(new BidData($auction->id, 50.0, $bidder->id));
    } catch (Exception) {
        // متوقع
    }

    expect(Bid::count())->toBe(0)
        ->and((float) $auction->fresh()->current_price)->toBe(100.0);
});

it('returns only the bids of the requested user', function () {
    $user = User::factory()->create();
    Bid::factory()->count(3)->create(['user_id' => $user->id]);
    Bid::factory()->create();

    $bids = $this->service->getUserBids($user->id, 10);

    expect($bids->total())->toBe(3);
});
