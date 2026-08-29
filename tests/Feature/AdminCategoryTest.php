<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;

it('lets an admin create a single category', function () {
    $admin = User::factory()->admin()->create();

    $this->postJson('/api/admin/categories', [
        'name' => 'Laptops',
        'slug' => 'laptops',
    ], jwtHeaders($admin))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Laptops');

    $this->assertDatabaseHas('categories', ['slug' => 'laptops']);
});

it('rejects a duplicate slug on single create', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['slug' => 'laptops']);

    $this->postJson('/api/admin/categories', [
        'name' => 'Laptops',
        'slug' => 'laptops',
    ], jwtHeaders($admin))->assertStatus(422);
});

it('forbids a non admin from creating a single category', function () {
    $user = User::factory()->create();

    $this->postJson('/api/admin/categories', [
        'name' => 'Laptops',
        'slug' => 'laptops',
    ], jwtHeaders($user))->assertForbidden();
});

it('lets an admin update a category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

    $this->putJson("/api/admin/categories/{$category->id}", [
        'name' => 'New Name',
    ], jwtHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($category->fresh()->slug)->toBe('old-slug'); // لم يُطلب تغييره
});

it('lets a category keep its own slug on update', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'laptops']);

    $this->putJson("/api/admin/categories/{$category->id}", [
        'slug' => 'laptops',
    ], jwtHeaders($admin))->assertOk();
});

it('rejects updating to a slug already used by another category', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['slug' => 'phones']);
    $category = Category::factory()->create(['slug' => 'laptops']);

    $this->putJson("/api/admin/categories/{$category->id}", [
        'slug' => 'phones',
    ], jwtHeaders($admin))->assertStatus(422);
});

it('returns 404 when updating an unknown category', function () {
    $admin = User::factory()->admin()->create();

    $this->putJson('/api/admin/categories/9999', ['name' => 'X'], jwtHeaders($admin))
        ->assertNotFound();
});

it('forbids a non admin from updating a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'X'], jwtHeaders($user))
        ->assertForbidden();
});

it('lets an admin delete an unused category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->deleteJson("/api/admin/categories/{$category->id}", [], jwtHeaders($admin))->assertOk();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

it('blocks deleting a category that has auctions', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    Auction::factory()->create(['category_id' => $category->id]);

    $this->deleteJson("/api/admin/categories/{$category->id}", [], jwtHeaders($admin))
        ->assertStatus(422);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

it('returns 404 when deleting an unknown category', function () {
    $admin = User::factory()->admin()->create();

    $this->deleteJson('/api/admin/categories/9999', [], jwtHeaders($admin))->assertNotFound();
});

it('forbids a non admin from deleting a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->deleteJson("/api/admin/categories/{$category->id}", [], jwtHeaders($user))
        ->assertForbidden();

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

it('rejects unauthenticated single category creation', function () {
    $this->postJson('/api/admin/categories', ['name' => 'X', 'slug' => 'x'])->assertUnauthorized();
});
