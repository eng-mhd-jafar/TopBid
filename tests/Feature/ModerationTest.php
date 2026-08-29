<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use App\Notifications\AuctionStatusNotification;
use Illuminate\Support\Facades\Notification;

it('activates an auction on approval and stamps the timing', function () {
    Notification::fake();

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
    Notification::fake();

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
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", ['reason' => 'Prohibited item'], jwtHeaders($admin))
        ->assertOk();

    $auction->refresh();

    expect($auction->moderation_status)->toBe('flagged')
        ->and($auction->is_active)->toBeFalse()
        ->and($auction->rejection_reason)->toBe('Prohibited item');
});

it('reports a rejected auction as rejected in the api', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $owner->id]);

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", ['reason' => 'Missing photos'], jwtHeaders($admin))
        ->assertOk();

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($owner))
        ->assertOk()
        ->assertJsonPath('data.status.moderation', 'rejected')
        ->assertJsonPath('data.status.rejection_reason', 'Missing photos');
});

it('requires a reason to reject an auction', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", [], jwtHeaders($admin))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($auction->fresh()->moderation_status)->toBe('pending');
});

it('clears a stale rejection reason when later approved', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->pending()->create();

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", ['reason' => 'Bad photo'], jwtHeaders($admin))->assertOk();
    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($admin))->assertOk();

    expect($auction->fresh()->rejection_reason)->toBeNull();
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

// ---------------------------------------------------------- إشعارا نتيجة المراجعة

it('tells the seller their auction was approved', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $seller->id]);

    $this->postJson("/api/admin/auctions/{$auction->id}/approve", [], jwtHeaders($admin))->assertOk();

    Notification::assertSentTo(
        $seller,
        AuctionStatusNotification::class,
        fn ($notification) => $notification->toArray($seller)['status'] === 'auction_approved'
    );
});

it('tells the seller their auction was rejected, with the reason', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $seller->id]);

    $this->postJson("/api/admin/auctions/{$auction->id}/reject", ['reason' => 'Counterfeit item'], jwtHeaders($admin))
        ->assertOk();

    Notification::assertSentTo(
        $seller,
        AuctionStatusNotification::class,
        function ($notification) use ($seller) {
            $data = $notification->toArray($seller);

            return $data['status'] === 'auction_rejected' && $data['reason'] === 'Counterfeit item';
        }
    );
});

// -------------------------------------------------------------- طابور الإشراف

it('lists auctions for moderation regardless of status by default', function () {
    $admin = User::factory()->admin()->create();
    Auction::factory()->pending()->create();
    Auction::factory()->approved()->create();
    Auction::factory()->rejected()->create();

    $response = $this->getJson('/api/admin/auctions', jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(3);
});

it('filters the moderation queue by status', function () {
    $admin = User::factory()->admin()->create();
    Auction::factory()->count(2)->pending()->create();
    Auction::factory()->approved()->create();

    $response = $this->getJson('/api/admin/auctions?status=pending', jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(2);
});

it('filters the moderation queue by title search', function () {
    $admin = User::factory()->admin()->create();
    Auction::factory()->pending()->create(['title' => 'Vintage Camera']);
    Auction::factory()->pending()->create(['title' => 'Gaming Laptop']);

    $response = $this->getJson('/api/admin/auctions?search=camera', jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(1)
        ->and($response->json('data.data.0.title'))->toBe('Vintage Camera');
});

it('filters the moderation queue by category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    Auction::factory()->pending()->create(['category_id' => $category->id]);
    Auction::factory()->pending()->create();

    $response = $this->getJson("/api/admin/auctions?category_id={$category->id}", jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(1);
});

it('forbids a non admin from browsing the moderation queue', function () {
    $user = User::factory()->create();

    $this->getJson('/api/admin/auctions', jwtHeaders($user))->assertForbidden();
});

it('rejects an unauthenticated moderation queue request', function () {
    $this->getJson('/api/admin/auctions')->assertUnauthorized();
});
