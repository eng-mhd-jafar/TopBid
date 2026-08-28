<?php

use App\Models\User;
use App\Notifications\AuctionStatusNotification;
use Illuminate\Support\Str;

/** إنشاء إشعار مخزّن في قاعدة البيانات مباشرةً دون المرور بقنوات الإرسال */
function makeNotification(User $user, ?string $readAt = null): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => AuctionStatusNotification::class,
        'data' => ['message' => 'You won the auction', 'status' => 'won'],
        'read_at' => $readAt,
    ]);

    return $id;
}

it('lists the notifications of the authenticated user', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);
    makeNotification(User::factory()->create()); // لمستخدم آخر

    $response = $this->getJson('/api/notifications', jwtHeaders($user))->assertOk();

    expect($response->json('data.total'))->toBe(2);
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $id = makeNotification($user);

    $this->postJson("/api/notifications/{$id}/read", [], jwtHeaders($user))->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks every notification as read', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);

    $this->postJson('/api/notifications/read-all', [], jwtHeaders($user))->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('cannot mark another users notification as read', function () {
    $user = User::factory()->create();
    $otherId = makeNotification(User::factory()->create());

    $this->postJson("/api/notifications/{$otherId}/read", [], jwtHeaders($user))
        ->assertNotFound();
});

it('rejects unauthenticated notification access', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});
