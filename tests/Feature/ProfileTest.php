<?php

use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('returns the authenticated profile', function () {
    $user = User::factory()->create(['city' => 'Damascus']);

    $this->getJson('/api/me', jwtHeaders($user))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects an unauthenticated profile request', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('updates non identity fields', function () {
    $user = User::factory()->create();

    $this->putJson('/api/me', [
        'bio' => 'Backend developer',
        'city' => 'Aleppo',
    ], jwtHeaders($user))->assertOk();

    $user->refresh();

    expect($user->bio)->toBe('Backend developer')
        ->and($user->city)->toBe('Aleppo');
});

it('allows identity changes when the user has no active activity', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->putJson('/api/me', ['name' => 'New Name'], jwtHeaders($user))->assertOk();

    expect($user->fresh()->name)->toBe('New Name');
});

it('blocks identity changes while the user has an active auction', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    Auction::factory()->approved()->create(['user_id' => $user->id]);

    $this->putJson('/api/me', ['name' => 'New Name'], jwtHeaders($user))
        ->assertStatus(422);

    expect($user->fresh()->name)->toBe('Old Name');
});

it('still allows non identity changes while an auction is active', function () {
    $user = User::factory()->create();
    Auction::factory()->approved()->create(['user_id' => $user->id]);

    $this->putJson('/api/me', ['city' => 'Homs'], jwtHeaders($user))->assertOk();

    expect($user->fresh()->city)->toBe('Homs');
});

it('replaces the old avatar on upload', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'stub');

    $this->putJson('/api/me', [
        'avatar' => UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
    ], jwtHeaders($user))->assertOk();

    Storage::disk('public')->assertMissing('avatars/old.jpg');
    Storage::disk('public')->assertExists($user->fresh()->avatar);
});

// ------------------------------------------------------------ كلمة السر

it('changes the password with the correct old password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);

    $this->putJson('/api/me/password', [
        'old_password' => 'OldPassword@123',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ], jwtHeaders($user))->assertOk();

    expect(Hash::check('NewPassword@123', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with a wrong old password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);

    $this->putJson('/api/me/password', [
        'old_password' => 'WrongPassword',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ], jwtHeaders($user))->assertStatus(422);

    expect(Hash::check('OldPassword@123', $user->fresh()->password))->toBeTrue();
});

it('requires the new password to be confirmed', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword@123')]);

    $this->putJson('/api/me/password', [
        'old_password' => 'OldPassword@123',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'Mismatch@123',
    ], jwtHeaders($user))->assertStatus(422);
});
