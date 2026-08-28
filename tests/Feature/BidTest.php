<?php

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * ملاحظة على رموز الحالة: StoreBidRequest::authorize يشغّل AuctionPolicy::bid
 * قبل التحقق من البيانات، لذلك رفض الملكية أو الحالة يظهر كـ 403 وليس 422.
 * الشرط الوحيد الذي يصل إلى BidService عبر HTTP هو مقارنة السعر.
 */

it('places a valid bid and updates the current price', function () {
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ], jwtHeaders($bidder))->assertOk();

    expect((float) $auction->fresh()->current_price)->toBe(150.0);

    $this->assertDatabaseHas('bids', [
        'auction_id' => $auction->id,
        'user_id' => $bidder->id,
        'amount' => 150,
    ]);

    Event::assertDispatched(BidPlaced::class);
});

it('forbids bidding on your own auction', function () {
    $owner = User::factory()->create();
    $auction = Auction::factory()->approved()->create([
        'user_id' => $owner->id,
        'current_price' => 100,
    ]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ], jwtHeaders($owner))->assertForbidden();

    expect(Bid::count())->toBe(0);
});

it('forbids bidding on a pending auction', function () {
    $auction = Auction::factory()->pending()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ], jwtHeaders($bidder))->assertForbidden();
});

it('forbids bidding on an expired auction', function () {
    $auction = Auction::factory()->expired()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ], jwtHeaders($bidder))->assertForbidden();
});

it('forbids bidding on an auction that does not exist', function () {
    $bidder = User::factory()->create();

    $this->postJson('/api/bids', [
        'auction_id' => 9999,
        'amount' => 150,
    ], jwtHeaders($bidder))->assertForbidden();
});

it('rejects a bid at or below the current price', function () {
    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 100,
    ], jwtHeaders($bidder))->assertStatus(422);

    expect((float) $auction->fresh()->current_price)->toBe(100.0)
        ->and(Bid::count())->toBe(0);
});

it('rejects an unauthenticated bid', function () {
    $auction = Auction::factory()->approved()->create();

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ])->assertUnauthorized();
});

// -------------------------------------------------------- قائمة مزايداتي

it('lists only the callers own bids', function () {
    $user = User::factory()->create();
    Bid::factory()->count(2)->create(['user_id' => $user->id]);
    Bid::factory()->create(); // لمستخدم آخر

    $response = $this->getJson('/api/bids', jwtHeaders($user))->assertOk();

    expect($response->json('data.meta.total'))->toBe(2)
        ->and($response->json('data.data.0.auction.id'))->not->toBeNull();
});

it('rejects an unauthenticated bid listing', function () {
    $this->getJson('/api/bids')->assertUnauthorized();
});
