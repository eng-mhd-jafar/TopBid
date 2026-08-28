<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/** مستخدم جوجل مزيّف بالشكل الذي يرجعه Socialite */
function fakeGoogleUser(string $email, string $name = 'Ahmad Ali'): SocialiteUser
{
    $user = new SocialiteUser;
    $user->map([
        'id' => '1234567890',
        'nickname' => null,
        'name' => $name,
        'email' => $email,
        'avatar' => 'https://lh3.googleusercontent.com/a/photo.jpg',
    ]);

    return $user;
}

/** يعترض Socialite::driver('google')->stateless()->user() */
function mockGoogleReturns(SocialiteUser $user): void
{
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('hands back a google authorization url', function () {
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $redirect = Mockery::mock();
    $redirect->shouldReceive('getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?x=1');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn($redirect);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->getJson('/api/auth/google')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://accounts.google.com/o/oauth2/auth?x=1');
});

it('registers a new verified user on first google login', function () {
    mockGoogleReturns(fakeGoogleUser('newcomer@example.com', 'Ahmad Ali'));

    $response = $this->getJson('/api/auth/google/callback')->assertOk();

    $user = User::where('email', 'newcomer@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ahmad Ali')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($response->json('data.access_token'))->not->toBeEmpty()
        ->and($response->json('data.refresh_token'))->not->toBeEmpty()
        ->and($response->json('data.user.email'))->toBe('newcomer@example.com');
});

it('signs in an existing user instead of duplicating them', function () {
    $existing = User::factory()->create(['email' => 'known@example.com', 'name' => 'Original Name']);

    mockGoogleReturns(fakeGoogleUser('known@example.com', 'Google Name'));

    $this->getJson('/api/auth/google/callback')->assertOk();

    expect(User::where('email', 'known@example.com')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Original Name'); // لا نكتب فوق بياناته
});

// جوجل تتحقق من ملكية البريد بطريقة أقوى من رمز البريد، فيُعتمد الحساب
it('verifies a pending account that signs in through google', function () {
    $pending = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'OTP' => '1234',
        'failed_attempts' => 2,
    ]);

    mockGoogleReturns(fakeGoogleUser('pending@example.com'));

    $this->getJson('/api/auth/google/callback')->assertOk();

    $pending->refresh();

    expect($pending->email_verified_at)->not->toBeNull()
        ->and($pending->OTP)->toBeNull()
        ->and($pending->failed_attempts)->toBe(0);
});

it('issues a usable session from a google login', function () {
    mockGoogleReturns(fakeGoogleUser('session@example.com'));

    $token = $this->getJson('/api/auth/google/callback')->json('data.access_token');

    $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.email', 'session@example.com');
});

it('records a refresh token for a google session', function () {
    mockGoogleReturns(fakeGoogleUser('refresh@example.com'));

    $refreshToken = $this->getJson('/api/auth/google/callback')->json('data.refresh_token');

    $this->postJson('/api/auth/refresh', ['refresh_token' => $refreshToken])->assertOk();
});

it('rejects a google account without an email', function () {
    mockGoogleReturns(fakeGoogleUser(''));

    $this->getJson('/api/auth/google/callback')->assertUnauthorized();

    expect(User::count())->toBe(0);
});

it('returns 401 when the google exchange fails', function () {
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new RuntimeException('invalid code'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->getJson('/api/auth/google/callback')->assertUnauthorized();

    expect(User::count())->toBe(0);
});
