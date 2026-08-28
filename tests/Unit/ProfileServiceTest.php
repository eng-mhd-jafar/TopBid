<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Models\UserRefreshToken;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(ProfileService::class);
});

it('blocks identity fields while the user has an active auction', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    Auction::factory()->approved()->create(['user_id' => $user->id]);

    expect(fn () => $this->service->updateProfile($user, ['name' => 'New Name']))
        ->toThrow(ValidationException::class);
});

it('blocks identity fields while the user has a bid on a running auction', function () {
    $user = User::factory()->create();
    Bid::factory()->create([
        'user_id' => $user->id,
        'auction_id' => Auction::factory()->approved()->create()->id,
    ]);

    expect(fn () => $this->service->updateProfile($user, ['email' => 'new@example.com']))
        ->toThrow(ValidationException::class);
});

it('allows identity fields when nothing is active', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    Auction::factory()->expired()->create(['user_id' => $user->id]);

    $updated = $this->service->updateProfile($user, ['name' => 'New Name']);

    expect($updated->name)->toBe('New Name');
});

it('allows non identity fields even while active', function () {
    $user = User::factory()->create();
    Auction::factory()->approved()->create(['user_id' => $user->id]);

    $updated = $this->service->updateProfile($user, ['bio' => 'Updated bio']);

    expect($updated->bio)->toBe('Updated bio');
});

it('does not throw when an identity field is submitted unchanged', function () {
    $user = User::factory()->create(['name' => 'Same Name']);
    Auction::factory()->approved()->create(['user_id' => $user->id]);

    $updated = $this->service->updateProfile($user, ['name' => 'Same Name']);

    expect($updated->name)->toBe('Same Name');
});

// ------------------------------------------------------------ كلمة السر

it('rejects a wrong old password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);

    expect(fn () => $this->service->changePassword($user, 'WrongPassword', 'NewPassword@123'))
        ->toThrow(ValidationException::class);
});

it('bumps the token version and revokes refresh tokens on password change', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);
    $versionBefore = $user->jwt_token_version;

    UserRefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'token-one'),
        'expires_at' => now()->addDay(),
        'jwt_token_version' => $versionBefore,
    ]);

    $this->service->changePassword($user, 'OldPassword@123', 'NewPassword@123');

    $user->refresh();

    expect($user->jwt_token_version)->toBe($versionBefore + 1)
        ->and(Hash::check('NewPassword@123', $user->password))->toBeTrue()
        ->and(UserRefreshToken::whereNull('revoked_at')->count())->toBe(0);
});
