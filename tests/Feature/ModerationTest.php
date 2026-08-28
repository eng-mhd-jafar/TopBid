<?php

use App\Models\Auction;
use App\Models\User;

it('activates an auction on approval and stamps the timing', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create(['duration_hours' => 48]);

    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($admin))
        ->assertOk();

    $auction->refresh();

    expect($auction->moderation_status)->toBe('approved')
        ->and($auction->is_active)->toBeTrue()
        ->and($auction->started_at)->not->toBeNull()
        ->and($auction->expires_at)->not->toBeNull()
        ->and($auction->expires_at->diffInHours($auction->started_at, true))->toEqualWithDelta(48, 1);
});

it('does not restart the clock when approving twice', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create(['duration_hours' => 24]);

    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($admin))->assertOk();
    $firstStartedAt = $auction->fresh()->started_at;

    $this->travel(2)->hours();

    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($admin))->assertOk();

    expect($auction->fresh()->started_at->timestamp)->toBe($firstStartedAt->timestamp);
});

// انحدار للإصلاح رقم ٢: الكود القديم كان يكتب 'rejected' وهي خارج قيم العمود
it('rejects an auction and persists the status', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", [], jwtHeaders($admin))
        ->assertOk();

    $auction->refresh();

    expect($auction->moderation_status)->toBe('flagged')
        ->and($auction->is_active)->toBeFalse();
});

it('reports a rejected auction as rejected in the api', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $owner->id]);

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", [], jwtHeaders($admin))->assertOk();

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($owner))
        ->assertOk()
        ->assertJsonPath('data.status.moderation', 'rejected');
});

it('forbids a non admin from moderating', function () {
    $user = User::factory()->create();
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($user))
        ->assertForbidden();

    expect($auction->fresh()->moderation_status)->toBe('pending');
});

it('rejects unauthenticated moderation', function () {
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/approve")->assertUnauthorized();
});

it('returns 404 for an unknown auction', function () {
    $admin = User::factory()->admin()->create();

    $this->postJson('/api/admin/auctions/9999/approve', [], jwtHeaders($admin))
        ->assertNotFound();
});
