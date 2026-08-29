<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

// ------------------------------------------------------------ سجل المزايدات

it('lets a guest read the bid history of a live auction', function () {
    $auction = Auction::factory()->approved()->create();
    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 200]);

    $this->getJson("/api/auctions/{$auction->id}/bids")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);
});

it('orders the bid history from highest to lowest', function () {
    $auction = Auction::factory()->approved()->create();
    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 100]);
    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 900]);
    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 400]);

    $amounts = $this->getJson("/api/auctions/{$auction->id}/bids")
        ->assertOk()
        ->json('data.data.*.amount');

    expect(array_map('floatval', $amounts))->toBe([900.0, 400.0, 100.0]);
});

it('names the bidder on each entry without leaking contact details', function () {
    $bidder = User::factory()->create(['name' => 'Ahmad Ali']);
    $auction = Auction::factory()->approved()->create();
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount' => 300]);

    $entry = $this->getJson("/api/auctions/{$auction->id}/bids")->json('data.data.0');

    expect($entry['bidder']['id'])->toBe($bidder->id)
        ->and($entry['bidder']['name'])->toBe('Ahmad Ali')
        ->and($entry)->not->toHaveKey('auction')
        ->and($entry['bidder'])->not->toHaveKey('email')
        ->and($entry['bidder'])->not->toHaveKey('phone_number');
});

it('excludes bids belonging to other auctions', function () {
    $auction = Auction::factory()->approved()->create();
    Bid::factory()->create(['auction_id' => $auction->id]);
    Bid::factory()->create(); // مزاد آخر

    $this->getJson("/api/auctions/{$auction->id}/bids")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);
});

it('hides the bid history of a pending auction from a guest', function () {
    $auction = Auction::factory()->pending()->create();

    $this->getJson("/api/auctions/{$auction->id}/bids")->assertForbidden();
});

it('still shows the owner the bid history of their own pending auction', function () {
    $owner = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $owner->id]);

    $this->getJson("/api/auctions/{$auction->id}/bids", jwtHeaders($owner))->assertOk();
});

it('returns 404 for the bid history of an unknown auction', function () {
    $this->getJson('/api/auctions/9999/bids')->assertNotFound();
});

it('keeps my-bids showing the auction rather than the bidder', function () {
    $user = User::factory()->create();
    Bid::factory()->create(['user_id' => $user->id]);

    $entry = $this->getJson('/api/bids', jwtHeaders($user))->json('data.data.0');

    expect($entry['auction']['id'])->not->toBeNull()
        ->and($entry)->not->toHaveKey('bidder');
});

// ------------------------------------------- عدد المزايدات وصاحب أعلى مزايدة

it('reports the bid count and the highest bidder on auction detail', function () {
    $leader = User::factory()->create();
    $auction = Auction::factory()->approved()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 100]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $leader->id, 'amount' => 800]);

    $response = $this->getJson("/api/auctions/{$auction->id}")->assertOk();

    expect($response->json('data.bids_count'))->toBe(2)
        ->and($response->json('data.highest_bidder_id'))->toBe($leader->id);
});

it('reports a null highest bidder before anyone bids', function () {
    $auction = Auction::factory()->approved()->create();

    $response = $this->getJson("/api/auctions/{$auction->id}")->assertOk();

    expect($response->json('data.bids_count'))->toBe(0)
        ->and($response->json('data.highest_bidder_id'))->toBeNull();
});

it('carries the bid count through the public listing', function () {
    $auction = Auction::factory()->approved()->create();
    Bid::factory()->count(3)->create(['auction_id' => $auction->id]);

    $response = $this->getJson('/api/auctions')->assertOk();

    expect($response->json('data.data.0.bids_count'))->toBe(3);
});

// تعادل المبلغ يُحسم بالأحدث، وإلا صار صاحب الصدارة غير محدد
it('breaks a tie on amount by the newest bid', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $auction = Auction::factory()->approved()->create();

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 500]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 500]);

    expect($this->getJson("/api/auctions/{$auction->id}")->json('data.highest_bidder_id'))
        ->toBe($second->id);
});
