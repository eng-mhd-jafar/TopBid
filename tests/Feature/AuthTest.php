<?php

use App\Jobs\SendOtpJob;
use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/** حمولة تسجيل صالحة (phone_number مطلوب وفريد في JwtRegisterRequest) */
function validRegisterPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ahmad Ali',
        'email' => 'ahmad@example.com',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
        'phone_number' => '0999999999',
    ], $overrides);
}

// ---------------------------------------------------------------- التسجيل

it('registers a user as unverified and dispatches the otp job', function () {
    Queue::fake();

    $this->postJson('/api/auth/register', validRegisterPayload())
        ->assertCreated()
        ->assertJson(['success' => true]);

    $user = User::where('email', 'ahmad@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->OTP)->not->toBeNull()
        ->and($user->failed_attempts)->toBe(0);

    Queue::assertPushed(SendOtpJob::class);
});

it('rejects a duplicate email on register', function () {
    User::factory()->create(['email' => 'ahmad@example.com']);

    $this->postJson('/api/auth/register', validRegisterPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('requires a confirmed password on register', function () {
    $this->postJson('/api/auth/register', validRegisterPayload([
        'password_confirmation' => 'DifferentPassword',
    ]))->assertStatus(422)->assertJsonValidationErrors('password');
});

// ------------------------------------------------------------ تفعيل الحساب

it('verifies the otp and returns both tokens', function () {
    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'verification_code_expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/auth/verify-otp', [
        'email' => $user->email,
        'OTP' => '1234',
    ])->assertOk();

    expect($response->json('data.access_token'))->not->toBeEmpty()
        ->and($response->json('data.refresh_token'))->not->toBeEmpty();

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->OTP)->toBeNull();
});

it('increments failed attempts on a wrong otp', function () {
    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'verification_code_expires_at' => now()->addMinutes(5),
        'failed_attempts' => 0,
    ]);

    $this->postJson('/api/auth/verify-otp', [
        'email' => $user->email,
        'OTP' => '9999',
    ])->assertStatus(422);

    expect($user->fresh()->failed_attempts)->toBe(1)
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

it('blocks otp verification after three failed attempts', function () {
    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'verification_code_expires_at' => now()->addMinutes(5),
        'failed_attempts' => 3,
    ]);

    $this->postJson('/api/auth/verify-otp', [
        'email' => $user->email,
        'OTP' => '1234', // حتى الرمز الصحيح يُرفض
    ])->assertStatus(422);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects an expired otp', function () {
    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'verification_code_expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/verify-otp', [
        'email' => $user->email,
        'OTP' => '1234',
    ])->assertStatus(422);
});

it('does not resend an otp before the sixty second cooldown', function () {
    Queue::fake();

    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'last_otp_at' => now(),
    ]);

    $this->postJson('/api/auth/resend-otp', ['email' => $user->email])->assertOk();

    Queue::assertNotPushed(SendOtpJob::class);
    expect($user->fresh()->OTP)->toBe('1234');
});

it('resends an otp once the cooldown has passed', function () {
    Queue::fake();

    $user = User::factory()->unverified()->create([
        'OTP' => '1234',
        'last_otp_at' => now()->subMinutes(2),
    ]);

    $this->postJson('/api/auth/resend-otp', ['email' => $user->email])->assertOk();

    Queue::assertPushed(SendOtpJob::class);
    expect($user->fresh()->OTP)->not->toBe('1234');
});

// ------------------------------------------------------------- تسجيل الدخول

it('logs in a verified user', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertOk();

    expect($response->json('data.access_token'))->not->toBeEmpty()
        ->and($response->json('data.refresh_token'))->not->toBeEmpty()
        ->and($response->json('data.user.email'))->toBe($user->email);
});

it('refuses login for an unverified account', function () {
    $user = User::factory()->unverified()->create(['password' => Hash::make('Password@123')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertUnauthorized();
});

it('refuses login with a wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'WrongPassword',
    ])->assertUnauthorized();
});

// -------------------------------------------------------- دوران التوكن والخروج

it('rotates the refresh token and rejects replaying the old one', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    $first = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->json('data.refresh_token');

    $second = $this->postJson('/api/auth/refresh', ['refresh_token' => $first])
        ->assertOk()
        ->json('data.refresh_token');

    expect($second)->not->toBe($first);

    // إعادة استخدام التوكن القديم يجب أن تفشل
    $this->postJson('/api/auth/refresh', ['refresh_token' => $first])
        ->assertUnauthorized();
});

it('rejects a refresh token whose version no longer matches', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    $refreshToken = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->json('data.refresh_token');

    $user->increment('jwt_token_version');

    $this->postJson('/api/auth/refresh', ['refresh_token' => $refreshToken])
        ->assertUnauthorized();
});

it('revokes every refresh token on logout', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ]);

    expect(UserRefreshToken::whereNull('revoked_at')->count())->toBe(1);

    $this->deleteJson('/api/auth/logout', [], jwtHeaders($user))->assertOk();

    expect(UserRefreshToken::whereNull('revoked_at')->count())->toBe(0);
});

// -------------------------------------------------------- إبطال الجلسة بالإصدار

it('rejects an access token issued before a password change', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);
    $staleToken = JWTAuth::fromUser($user);

    $this->putJson('/api/me/password', [
        'old_password' => 'OldPassword@123',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ], jwtHeaders($user))->assertOk();

    $this->getJson('/api/me', ['Authorization' => "Bearer {$staleToken}"])
        ->assertUnauthorized();
});

// ------------------------------------------------------------ استعادة كلمة السر

it('returns the same generic response for a known and an unknown email', function () {
    $user = User::factory()->create();

    $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'));
});

it('resets the password and invalidates old sessions', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);
    $token = Password::broker()->createToken($user);
    $versionBefore = $user->jwt_token_version;

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertOk();

    $user->refresh();

    expect(Hash::check('NewPassword@123', $user->password))->toBeTrue()
        ->and($user->jwt_token_version)->toBeGreaterThan($versionBefore);
});

it('rejects a reset with an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => 'not-a-real-token',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertStatus(422);
});

// -------------------------------------------------------------- تحديد المعدل

it('throttles login after five attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('Password@123')]);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertStatus(429);
});
