<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\AuctionStatusNotification;
use Illuminate\Support\Facades\Notification;

/**
 * انحدار للإصلاح رقم ١: الأمر كان يحمّل علاقة seller غير الموجودة،
 * فينهار بـ RelationNotFoundException كل دقيقة ولا يُغلق أي مزاد إطلاقاً.
 */

it('closes an expired auction and notifies the winner', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $auction = Auction::factory()->expired()->create(['user_id' => $seller->id]);

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $winner->id,
        'amount' => 500,
    ]);

    $this->artisan('auctions:close')->assertSuccessful();

    expect($auction->fresh()->is_active)->toBeFalse();

    Notification::assertSentTo(
        $winner,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($winner)['status'] === 'won'
    );
});

// انحدار للترتيب: الفائز هو صاحب أعلى مبلغ، وليس آخر من زايد
it('picks the highest bid as the winner, not the newest', function () {
    Notification::fake();

    $auction = Auction::factory()->expired()->create();
    $highestBidder = User::factory()->create();
    $laterButLowerBidder = User::factory()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $highestBidder->id,
        'amount' => 900,
        'created_at' => now()->subMinutes(10),
    ]);

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $laterButLowerBidder->id,
        'amount' => 300,
        'created_at' => now()->subMinute(),
    ]);

    $this->artisan('auctions:close')->assertSuccessful();

    expect($auction->fresh()->winner_id)->toBe($highestBidder->id);

    Notification::assertSentTo(
        $highestBidder,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($highestBidder)['status'] === 'won'
    );

    Notification::assertSentTo(
        $laterButLowerBidder,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($laterButLowerBidder)['status'] === 'lost'
    );
});

it('notifies the seller when an auction expires with no bids', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $auction = Auction::factory()->expired()->create(['user_id' => $seller->id]);

    $this->artisan('auctions:close')->assertSuccessful();

    expect($auction->fresh()->is_active)->toBeFalse();

    Notification::assertSentTo(
        $seller,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($seller)['status'] === 'expired_no_bids'
    );
});

it('leaves a running auction untouched', function () {
    Notification::fake();

    $auction = Auction::factory()->approved()->create();

    $this->artisan('auctions:close')->assertSuccessful();

    expect($auction->fresh()->is_active)->toBeTrue();

    Notification::assertNothingSent();
});

it('ignores an auction that is already closed', function () {
    Notification::fake();

    Auction::factory()->create([
        'moderation_status' => 'approved',
        'is_active' => false,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('auctions:close')->assertSuccessful();

    Notification::assertNothingSent();
});

// ------------------------------------------------------- حفظ نتيجة المزاد

it('records the winner, the winning bid and the final price', function () {
    Notification::fake();

    $winner = User::factory()->create();
    $auction = Auction::factory()->expired()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => User::factory(), 'amount' => 300]);
    $winningBid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $winner->id,
        'amount' => 750,
    ]);

    $this->artisan('auctions:close')->assertSuccessful();

    $auction->refresh();

    expect($auction->winner_id)->toBe($winner->id)
        ->and($auction->winning_bid_id)->toBe($winningBid->id)
        ->and((float) $auction->final_price)->toBe(750.0)
        ->and($auction->closed_at)->not->toBeNull()
        ->and($auction->is_active)->toBeFalse();
});

it('leaves the result empty when nobody bid', function () {
    Notification::fake();

    $auction = Auction::factory()->expired()->create();

    $this->artisan('auctions:close')->assertSuccessful();

    $auction->refresh();

    expect($auction->winner_id)->toBeNull()
        ->and($auction->winning_bid_id)->toBeNull()
        ->and($auction->final_price)->toBeNull()
        ->and($auction->closed_at)->not->toBeNull();
});

// ------------------------------------------------------ حالات الإشعار الأربع

// البائع لم يكن يُشعَر ببيع مزاده إطلاقاً
it('tells the seller their auction sold', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $auction = Auction::factory()->expired()->create(['user_id' => $seller->id]);
    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 500]);

    $this->artisan('auctions:close')->assertSuccessful();

    Notification::assertSentTo(
        $seller,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($seller)['status'] === 'sold'
    );
});

it('notifies every losing bidder exactly once', function () {
    Notification::fake();

    $auction = Auction::factory()->expired()->create();
    $winner = User::factory()->create();
    $loser = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $winner->id, 'amount' => 900]);
    // مزايدتان لنفس الخاسر: يجب أن يصله إشعار واحد لا اثنان
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $loser->id, 'amount' => 200]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $loser->id, 'amount' => 300]);

    $this->artisan('auctions:close')->assertSuccessful();

    Notification::assertSentToTimes($loser, AuctionStatusNotification::class, 1);
    Notification::assertSentToTimes($winner, AuctionStatusNotification::class, 1);
});

it('carries the final price inside the notification payload', function () {
    Notification::fake();

    $winner = User::factory()->create();
    $auction = Auction::factory()->expired()->create();
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $winner->id, 'amount' => 640]);

    $this->artisan('auctions:close')->assertSuccessful();

    Notification::assertSentTo(
        $winner,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($winner)['final_price'] === 640.0
    );
});

// ------------------------------------------------------------ المزادات المربوحة

it('lists the auctions a user has won', function () {
    Notification::fake();

    $winner = User::factory()->create();
    $wonAuction = Auction::factory()->expired()->create();
    Bid::factory()->create(['auction_id' => $wonAuction->id, 'user_id' => $winner->id, 'amount' => 500]);

    // مزاد فاز به شخص آخر
    $otherAuction = Auction::factory()->expired()->create();
    Bid::factory()->create(['auction_id' => $otherAuction->id, 'amount' => 500]);

    $this->artisan('auctions:close')->assertSuccessful();

    $response = $this->getJson('/api/my-wins', jwtHeaders($winner))->assertOk();

    expect($response->json('data.meta.total'))->toBe(1)
        ->and($response->json('data.data.0.id'))->toBe($wonAuction->id)
        ->and($response->json('data.data.0.result.winner_id'))->toBe($winner->id)
        ->and((float) $response->json('data.data.0.result.final_price'))->toBe(500.0);
});

it('returns nothing for a user who has won no auctions', function () {
    $user = User::factory()->create();

    expect($this->getJson('/api/my-wins', jwtHeaders($user))->json('data.meta.total'))->toBe(0);
});

it('rejects an unauthenticated wins request', function () {
    $this->getJson('/api/my-wins')->assertUnauthorized();
});

// ------------------------------------------------------------ المعالجة بدفعات

it('closes more auctions than a single chunk', function () {
    Notification::fake();

    Auction::factory()->count(120)->expired()->create();

    $this->artisan('auctions:close')->assertSuccessful();

    expect(Auction::awaitingClosure()->count())->toBe(0)
        ->and(Auction::whereNotNull('closed_at')->count())->toBe(120);
});
