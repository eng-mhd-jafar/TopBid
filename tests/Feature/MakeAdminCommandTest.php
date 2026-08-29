<?php

use App\Models\User;

it('promotes an existing user to admin', function () {
    $user = User::factory()->create();

    $this->artisan('topbid:make-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('does nothing harmful when the user is already an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('topbid:make-admin', ['email' => $admin->email])
        ->assertSuccessful();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('fails cleanly for an unknown email', function () {
    $this->artisan('topbid:make-admin', ['email' => 'nobody@example.com'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});
