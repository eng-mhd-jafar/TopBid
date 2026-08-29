<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

it('lists users with pagination', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(4)->create();

    $response = $this->getJson('/api/admin/users?per_page=2', jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(5) // ٤ + الأدمن نفسه
        ->and($response->json('data.data'))->toHaveCount(2);
});

it('searches users by name or email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Ahmad Ali', 'email' => 'ahmad@example.com']);
    User::factory()->create(['name' => 'Sara Khaled', 'email' => 'sara@example.com']);

    $response = $this->getJson('/api/admin/users?search=ahmad', jwtHeaders($admin))->assertOk();

    expect($response->json('data.meta.total'))->toBe(1)
        ->and($response->json('data.data.0.name'))->toBe('Ahmad Ali');
});

it('shows a single user with their activity counts', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    Auction::factory()->approved()->create(['user_id' => $user->id]);
    Bid::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson("/api/admin/users/{$user->id}", jwtHeaders($admin))->assertOk();

    expect($response->json('data.id'))->toBe($user->id)
        ->and($response->json('data.auctions_count'))->toBe(1)
        ->and($response->json('data.bids_count'))->toBe(1);
});

it('returns 404 for an unknown user', function () {
    $admin = User::factory()->admin()->create();

    $this->getJson('/api/admin/users/9999', jwtHeaders($admin))->assertNotFound();
});

it('grants admin access to a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->patchJson("/api/admin/users/{$user->id}/admin", ['is_admin' => true], jwtHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.is_admin', true);

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('revokes admin access from another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->patchJson("/api/admin/users/{$otherAdmin->id}/admin", ['is_admin' => false], jwtHeaders($admin))
        ->assertOk();

    expect($otherAdmin->fresh()->is_admin)->toBeFalse();
});

// الحارس الأهم: منع القفل الذاتي خارج لوحة التحكم
it('forbids an admin from revoking their own access', function () {
    $admin = User::factory()->admin()->create();

    $this->patchJson("/api/admin/users/{$admin->id}/admin", ['is_admin' => false], jwtHeaders($admin))
        ->assertStatus(422);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('allows an admin to confirm their own admin flag unchanged', function () {
    $admin = User::factory()->admin()->create();

    $this->patchJson("/api/admin/users/{$admin->id}/admin", ['is_admin' => true], jwtHeaders($admin))
        ->assertOk();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('validates the is_admin field', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->patchJson("/api/admin/users/{$user->id}/admin", [], jwtHeaders($admin))
        ->assertStatus(422)
        ->assertJsonValidationErrors('is_admin');
});

it('forbids a non admin from browsing users', function () {
    $user = User::factory()->create();

    $this->getJson('/api/admin/users', jwtHeaders($user))->assertForbidden();
});

it('forbids a non admin from granting admin access', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->patchJson("/api/admin/users/{$target->id}/admin", ['is_admin' => true], jwtHeaders($user))
        ->assertForbidden();

    expect($target->fresh()->is_admin)->toBeFalse();
});

it('rejects an unauthenticated user listing request', function () {
    $this->getJson('/api/admin/users')->assertUnauthorized();
});
