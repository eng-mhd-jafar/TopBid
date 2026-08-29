<?php

use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(AdminUserService::class);
});

it('throws when an admin tries to revoke their own access', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $this->service->setAdminFlag($admin, $admin->id, false))
        ->toThrow(ValidationException::class);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('lets an admin revoke another admins access', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->service->setAdminFlag($admin, $otherAdmin->id, false);

    expect($otherAdmin->fresh()->is_admin)->toBeFalse();
});

it('lets an admin grant access to a plain user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->service->setAdminFlag($admin, $user->id, true);

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('does not throw when an admin confirms their own flag unchanged', function () {
    $admin = User::factory()->admin()->create();

    $result = $this->service->setAdminFlag($admin, $admin->id, true);

    expect($result->is_admin)->toBeTrue();
});
