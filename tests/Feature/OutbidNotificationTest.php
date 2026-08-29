<?php

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * أهم إشعار كان مفقوداً: من يفقد الصدارة لا يعلم، وهو المحرك الأساسي
 * لعودة المزايد إلى المزاد.
 */

it('tells the previous leader they have been outbid', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $leader = User::factory()->create();
    $challenger = User::factory()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $leader->id,
        'amount' => 200,
    ]);
    $auction->update(['current_price' => 200]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 300,
    ], jwtHeaders($challenger))->assertOk();

    Notification::assertSentTo($leader, OutbidNotification::class);
    Notification::assertNotSentTo($challenger, OutbidNotification::class);
});

it('carries the new amount in the outbid payload', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $leader = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 150]);
    $auction->update(['current_price' => 150]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 640,
    ], jwtHeaders(User::factory()->create()))->assertOk();

    Notification::assertSentTo(
        $leader,
        OutbidNotification::class,
        function ($notification) use ($leader) {
            $data = $notification->toArray($leader);

            return $data['status'] === 'outbid' && $data['amount'] === 640.0;
        }
    );
});

it('sends nothing when the first bid arrives', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 150,
    ], jwtHeaders(User::factory()->create()))->assertOk();

    Notification::assertNothingSent();
});

// المزايدة على نفسك ترفع سعرك، ولا معنى لإشعارك بأنك تجاوزت نفسك
it('does not notify a bidder who raises their own bid', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount' => 200]);
    $auction->update(['current_price' => 200]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 300,
    ], jwtHeaders($bidder))->assertOk();

    Notification::assertNothingSent();
});

it('notifies only the leader, not every earlier bidder', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $earlyBidder = User::factory()->create();
    $leader = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $earlyBidder->id, 'amount' => 150]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 250]);
    $auction->update(['current_price' => 250]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 400,
    ], jwtHeaders(User::factory()->create()))->assertOk();

    Notification::assertSentTo($leader, OutbidNotification::class);
    Notification::assertNotSentTo($earlyBidder, OutbidNotification::class);
});

it('sends no outbid notice when the bid is rejected', function () {
    Notification::fake();
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 500]);
    $leader = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 500]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 400, // أقل من السعر الحالي
    ], jwtHeaders(User::factory()->create()))->assertStatus(422);

    Notification::assertNothingSent();
});

it('surfaces an outbid notice in the notifications feed', function () {
    stubFcmChannel(); // إشعار حقيقي هنا، لنتحقق من كتابته في قاعدة البيانات
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $leader = User::factory()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 200]);
    $auction->update(['current_price' => 200]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 300,
    ], jwtHeaders(User::factory()->create()))->assertOk();

    $feed = $this->getJson('/api/notifications', jwtHeaders($leader))->assertOk();

    expect($feed->json('data.unread_count'))->toBe(1)
        ->and($feed->json('data.data.0.status'))->toBe('outbid')
        ->and($feed->json('data.data.0.type'))->toBe('OutbidNotification');
});

// المزايدة نجحت فعلاً؛ تعطّل خدمة الدفع الخارجية يجب ألا يظهر للمزايد كفشل
it('still accepts the bid when the push channel blows up', function () {
    Event::fake([BidPlaced::class]);

    Notification::extend('fcm', fn () => new class
    {
        public function send($notifiable, $notification): void
        {
            throw new RuntimeException('FCM is down');
        }
    });

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $leader = User::factory()->create();
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 200]);
    $auction->update(['current_price' => 200]);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 300,
    ], jwtHeaders(User::factory()->create()))->assertOk();

    expect((float) $auction->fresh()->current_price)->toBe(300.0)
        ->and(Bid::where('auction_id', $auction->id)->count())->toBe(2);
});

// ------------------------------------------------------------ حمولة البث

it('broadcasts the bidder and bid id, not just an amount', function () {
    Notification::fake();

    $auction = Auction::factory()->approved()->create(['current_price' => 100]);
    $bidder = User::factory()->create(['name' => 'Ahmad Ali']);

    $this->postJson('/api/bids', [
        'auction_id' => $auction->id,
        'amount' => 250,
    ], jwtHeaders($bidder))->assertOk();

    $bid = Bid::first();
    $payload = (new BidPlaced($bid))->broadcastWith();

    expect($payload['bid_id'])->toBe($bid->id)
        ->and($payload['auction_id'])->toBe($auction->id)
        ->and($payload['amount'])->toBe(250.0)
        ->and($payload['bidder']['id'])->toBe($bidder->id)
        ->and($payload['bidder']['name'])->toBe('Ahmad Ali')
        ->and($payload['created_at'])->not->toBeNull();
});
