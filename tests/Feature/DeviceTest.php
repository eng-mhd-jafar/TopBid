<?php

use App\Models\User;
use App\Models\UserDevice;

/**
 * انحدار للإصلاح رقم ٦: DeviceController كان مكتوباً بالكامل لكن بلا مسار مسجل،
 * كما أن StoreDeviceTokenRequest::authorize كان يرجع false.
 */

it('registers an fcm token for the authenticated user', function () {
    $user = User::factory()->create();

    $this->postJson('/api/devices', [
        'fcm_token' => 'token-abc',
        'device_type' => 'android',
        'device_name' => 'Pixel 8',
    ], jwtHeaders($user))->assertOk();

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'fcm_token' => 'token-abc',
        'device_type' => 'android',
    ]);
});

it('updates the metadata when the same user re registers a token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/devices', [
        'fcm_token' => 'token-abc',
        'device_type' => 'android',
    ], jwtHeaders($user))->assertOk();

    $this->postJson('/api/devices', [
        'fcm_token' => 'token-abc',
        'device_type' => 'ios',
    ], jwtHeaders($user))->assertOk();

    expect(UserDevice::count())->toBe(1)
        ->and(UserDevice::first()->device_type)->toBe('ios');
});

it('moves a token to the new owner when the device changes hands', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->postJson('/api/devices', ['fcm_token' => 'token-abc'], jwtHeaders($first))->assertOk();
    $this->postJson('/api/devices', ['fcm_token' => 'token-abc'], jwtHeaders($second))->assertOk();

    expect(UserDevice::count())->toBe(1)
        ->and(UserDevice::first()->user_id)->toBe($second->id);
});

it('exposes registered tokens through the fcm routing method', function () {
    $user = User::factory()->create();

    $this->postJson('/api/devices', ['fcm_token' => 'token-abc'], jwtHeaders($user))->assertOk();

    expect($user->routeNotificationForFcm())->toBe(['token-abc']);
});

it('validates the device type', function () {
    $user = User::factory()->create();

    $this->postJson('/api/devices', [
        'fcm_token' => 'token-abc',
        'device_type' => 'blackberry',
    ], jwtHeaders($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_type');
});

it('requires an fcm token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/devices', [], jwtHeaders($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fcm_token');
});

it('rejects an unauthenticated device registration', function () {
    $this->postJson('/api/devices', ['fcm_token' => 'token-abc'])->assertUnauthorized();
});
