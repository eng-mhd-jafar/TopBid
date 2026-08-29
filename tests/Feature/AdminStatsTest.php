<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

it('reports auction counts by state', function () {
    $admin = User::factory()->admin()->create();

    Auction::factory()->count(2)->pending()->create();
    Auction::factory()->count(3)->approved()->create();
    Auction::factory()->expired()->create();
    Auction::factory()->rejected()->create();

    $response = $this->getJson('/api/admin/stats', jwtHeaders($admin))->assertOk();

    expect($response->json('data.auctions.pending'))->toBe(2)
        ->and($response->json('data.auctions.live'))->toBe(3)
        ->and($response->json('data.auctions.ended'))->toBe(1)
        ->and($response->json('data.auctions.rejected'))->toBe(1)
        ->and($response->json('data.auctions.total'))->toBe(7);
});

it('reports user and admin counts', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(4)->create();

    $response = $this->getJson('/api/admin/stats', jwtHeaders($admin))->assertOk();

    expect($response->json('data.users.total'))->toBe(5)
        ->and($response->json('data.users.admins'))->toBe(1);
});

it('reports the total number of bids', function () {
    $admin = User::factory()->admin()->create();
    Bid::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/stats', jwtHeaders($admin))->assertOk();

    expect($response->json('data.bids.total'))->toBe(3);
});

it('ranks the most active auctions by bid count', function () {
    $admin = User::factory()->admin()->create();

    $busy = Auction::factory()->approved()->create(['title' => 'Busy Auction']);
    Bid::factory()->count(3)->create(['auction_id' => $busy->id]);

    $quiet = Auction::factory()->approved()->create(['title' => 'Quiet Auction']);
    Bid::factory()->create(['auction_id' => $quiet->id]);

    $response = $this->getJson('/api/admin/stats', jwtHeaders($admin))->assertOk();

    expect($response->json('data.most_active_auctions.0.title'))->toBe('Busy Auction')
        ->and($response->json('data.most_active_auctions.0.bids_count'))->toBe(3);
});

it('forbids a non admin from viewing stats', function () {
    $user = User::factory()->create();

    $this->getJson('/api/admin/stats', jwtHeaders($user))->assertForbidden();
});

it('rejects an unauthenticated stats request', function () {
    $this->getJson('/api/admin/stats')->assertUnauthorized();
});
