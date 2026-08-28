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

    expect($response->json('data.meta.total'))->toBe(2);
});

// كانت هذه النقطة ترجع مرقّم لارافيل الخام، بغلاف مخالف لبقية القوائم
it('wraps notifications in the same envelope as every other list', function () {
    $user = User::factory()->create();
    makeNotification($user);

    $response = $this->getJson('/api/notifications', jwtHeaders($user))->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('data.data'))->toHaveCount(1)
        ->and($response->json('data.meta.per_page'))->toBe(10)
        ->and($response->json('data.links'))->not->toBeNull();
});

it('shapes each notification for direct display', function () {
    $user = User::factory()->create();
    makeNotification($user);

    $item = $this->getJson('/api/notifications', jwtHeaders($user))->json('data.data.0');

    expect($item['message'])->toBe('You won the auction')
        ->and($item['status'])->toBe('won')
        ->and($item['is_read'])->toBeFalse()
        ->and($item['read_at'])->toBeNull()
        ->and($item['type'])->toBe('AuctionStatusNotification')
        ->and($item['created_at'])->not->toBeNull();
});

it('reports the unread count for a badge', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);
    makeNotification($user, readAt: now()->toDateTimeString());

    $response = $this->getJson('/api/notifications', jwtHeaders($user))->assertOk();

    expect($response->json('data.unread_count'))->toBe(2)
        ->and($response->json('data.meta.total'))->toBe(3);
});

it('drops the unread count to zero after marking all read', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);

    $this->postJson('/api/notifications/read-all', [], jwtHeaders($user))->assertOk();

    expect($this->getJson('/api/notifications', jwtHeaders($user))->json('data.unread_count'))
        ->toBe(0);
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
