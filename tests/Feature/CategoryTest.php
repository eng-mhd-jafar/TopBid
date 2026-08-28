<?php

use App\Models\Category;
use App\Models\User;

it('exposes the category list publicly', function () {
    Category::factory()->count(3)->create();

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lets an admin create categories in bulk', function () {
    $admin = User::factory()->admin()->create();

    $this->postJson('/api/categories', [
        ['name' => 'Laptops', 'slug' => 'laptops'],
        ['name' => 'Phones', 'slug' => 'phones'],
    ], jwtHeaders($admin))->assertOk();

    expect(Category::count())->toBe(2);
    $this->assertDatabaseHas('categories', ['slug' => 'laptops']);
});

// انحدار للإصلاح رقم ٩: insert الخام كان يترك أعمدة الوقت فارغة
it('populates timestamps on bulk created categories', function () {
    $admin = User::factory()->admin()->create();

    $this->postJson('/api/categories', [
        ['name' => 'Laptops', 'slug' => 'laptops'],
    ], jwtHeaders($admin))->assertOk();

    $category = Category::first();

    expect($category->created_at)->not->toBeNull()
        ->and($category->updated_at)->not->toBeNull();
});

it('forbids a non admin from creating categories', function () {
    $user = User::factory()->create();

    $this->postJson('/api/categories', [
        ['name' => 'Laptops', 'slug' => 'laptops'],
    ], jwtHeaders($user))->assertForbidden();

    expect(Category::count())->toBe(0);
});

it('rejects an unauthenticated category creation', function () {
    $this->postJson('/api/categories', [
        ['name' => 'Laptops', 'slug' => 'laptops'],
    ])->assertUnauthorized();
});

it('rejects a duplicate slug', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['slug' => 'laptops']);

    $this->postJson('/api/categories', [
        ['name' => 'Laptops', 'slug' => 'laptops'],
    ], jwtHeaders($admin))->assertStatus(422);
});
