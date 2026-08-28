<?php

use App\Models\Auction;

it('starts the clock when an auction moves to approved', function () {
    $auction = Auction::factory()->pending()->create(['duration_hours' => 48]);

    $auction->update(['moderation_status' => 'approved']);
    $auction->refresh();

    expect($auction->is_active)->toBeTrue()
        ->and($auction->started_at)->not->toBeNull()
        ->and($auction->expires_at)->not->toBeNull()
        ->and($auction->expires_at->diffInHours($auction->started_at, true))->toEqualWithDelta(48, 1);
});

it('does not restart an auction that has already started', function () {
    $auction = Auction::factory()->approved()->create();
    $originalStartedAt = $auction->started_at;
    $originalExpiresAt = $auction->expires_at;

    $this->travel(3)->hours();

    $auction->update(['moderation_status' => 'approved', 'is_active' => true]);
    $auction->refresh();

    expect($auction->started_at->timestamp)->toBe($originalStartedAt->timestamp)
        ->and($auction->expires_at->timestamp)->toBe($originalExpiresAt->timestamp);
});

it('does not start the clock when the auction is flagged', function () {
    $auction = Auction::factory()->pending()->create();

    $auction->update(['moderation_status' => 'flagged']);
    $auction->refresh();

    expect($auction->is_active)->toBeFalse()
        ->and($auction->started_at)->toBeNull()
        ->and($auction->expires_at)->toBeNull();
});

it('ignores updates that do not touch the moderation status', function () {
    $auction = Auction::factory()->pending()->create();

    $auction->update(['title' => 'A new title']);
    $auction->refresh();

    expect($auction->started_at)->toBeNull()
        ->and($auction->is_active)->toBeFalse();
});
