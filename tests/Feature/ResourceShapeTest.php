<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;

// ------------------------------------------------------------ مورد المزاد

it('exposes the category and seller ids so the client can link to them', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();
    $auction = Auction::factory()->approved()->create([
        'user_id' => $seller->id,
        'category_id' => $category->id,
    ]);

    $this->getJson("/api/auctions/{$auction->id}")
        ->assertOk()
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.seller.id', $seller->id);
});

it('returns a usable image url alongside the stored path', function () {
    $auction = Auction::factory()->approved()->create(['image_path' => 'auctions/laptop.jpg']);

    $response = $this->getJson("/api/auctions/{$auction->id}")->assertOk();

    expect($response->json('data.image.path'))->toBe('auctions/laptop.jpg')
        ->and($response->json('data.image.url'))->toBe(asset('storage/auctions/laptop.jpg'));
});

it('returns a null image url when there is no image', function () {
    $auction = Auction::factory()->approved()->create(['image_path' => null]);

    expect($this->getJson("/api/auctions/{$auction->id}")->json('data.image.url'))->toBeNull();
});

// ------------------------------------------------- تفاصيل المزاد للزائر

it('lets a guest open a live auction', function () {
    $auction = Auction::factory()->approved()->create();

    $this->getJson("/api/auctions/{$auction->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $auction->id);
});

it('hides a pending auction from a guest', function () {
    $auction = Auction::factory()->pending()->create();

    $this->getJson("/api/auctions/{$auction->id}")->assertForbidden();
});

it('hides an ended auction from a guest', function () {
    $auction = Auction::factory()->expired()->create();

    $this->getJson("/api/auctions/{$auction->id}")->assertForbidden();
});

it('still lets the owner open their own pending auction', function () {
    $owner = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $owner->id]);

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($owner))->assertOk();
});

// ------------------------------------------------------------ المستخدم

it('tells the client whether the signed in user is an admin', function () {
    $admin = User::factory()->admin()->create();
    $plain = User::factory()->create();

    expect($this->getJson('/api/me', jwtHeaders($admin))->json('data.is_admin'))->toBeTrue()
        ->and($this->getJson('/api/me', jwtHeaders($plain))->json('data.is_admin'))->toBeFalse();
});

it('reports is_admin on the login payload too', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk();

    expect($response->json('data.user.is_admin'))->toBeTrue();
});

// كان يرجع نصاً عربياً، فتضطر الواجهة لمقارنة نصوص مترجمة
it('returns has_active_activity as a boolean', function () {
    $user = User::factory()->create();

    expect($this->getJson('/api/me', jwtHeaders($user))->json('data.has_active_activity'))
        ->toBeFalse();

    Auction::factory()->approved()->create(['user_id' => $user->id]);

    expect($this->getJson('/api/me', jwtHeaders($user))->json('data.has_active_activity'))
        ->toBeTrue();
});

it('exposes the profile id', function () {
    $user = User::factory()->create();

    $this->getJson('/api/me', jwtHeaders($user))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
