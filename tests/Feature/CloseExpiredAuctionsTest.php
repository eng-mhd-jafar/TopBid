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

    Notification::assertSentTo($highestBidder, AuctionStatusNotification::class);
    Notification::assertNotSentTo($laterButLowerBidder, AuctionStatusNotification::class);
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
