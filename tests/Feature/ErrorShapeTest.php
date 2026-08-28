<?php

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * كل خطأ يخرج من الـ API له شكل واحد:
 *
 *   { "success": false, "message": "نص", "errors": { "field": [...] } }
 *
 * مفتاح errors اختياري، و message نص دائماً لا كائن.
 */

it('returns validation errors in the unified shape', function () {
    $response = $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
        ->assertStatus(422);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()
        ->and($response->json('errors.password'))->toBeArray();
});

// كان هذا المسار يرجع message ككائن بدل نص، وهو الشكل الثالث المخالف
it('returns a string message when the old password is wrong', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);

    $response = $this->putJson('/api/me/password', [
        'old_password' => 'WrongPassword',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ], jwtHeaders($user))->assertStatus(422);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()
        ->and($response->json('errors.old_password'))->toBeArray();
});

it('returns the unified shape when unauthenticated', function () {
    $response = $this->getJson('/api/me')->assertUnauthorized();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()
        ->and($response->json('errors'))->toBeNull();
});

it('returns the unified shape when forbidden', function () {
    $stranger = User::factory()->create();
    $auction = Auction::factory()->pending()->create();

    $response = $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($stranger))
        ->assertForbidden();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString();
});

it('returns the unified shape for a missing record', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->postJson('/api/admin/auctions/9999/approve', [], jwtHeaders($admin))
        ->assertNotFound();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString();
});

it('returns the unified shape for an unknown endpoint', function () {
    $response = $this->getJson('/api/there-is-no-such-thing')->assertNotFound();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString();
});

it('returns the unified shape for a wrong http method', function () {
    $response = $this->deleteJson('/api/categories')->assertStatus(405);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString();
});

// المحددات لم تعد تصنع استجاباتها بنفسها، بل تُرمى وتمر على المعالج
it('returns the unified shape when throttled, with a retry hint', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ])->assertUnauthorized();
    }

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertStatus(429);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()
        ->and($response->json('errors.retry_after.0'))->toBeInt();
});

it('leaves web routes on the default html handling', function () {
    $this->get('/there-is-no-such-page')
        ->assertNotFound()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});
