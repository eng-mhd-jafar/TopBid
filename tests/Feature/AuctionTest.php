<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ------------------------------------------------------------ إنشاء المزاد

it('creates an auction in pending state', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->postJson('/api/auctions', [
        'title' => 'MacBook Pro',
        'description' => 'Barely used',
        'category_id' => $category->id,
        'starting_price' => 500,
        'duration_hours' => 48,
    ], jwtHeaders($user))->assertCreated();

    $auction = Auction::first();

    expect($auction->moderation_status)->toBe('pending')
        ->and($auction->is_active)->toBeFalse()
        ->and((float) $auction->current_price)->toBe(500.0)
        ->and($auction->user_id)->toBe($user->id);
});

it('stores the uploaded auction image', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->postJson('/api/auctions', [
        'title' => 'MacBook Pro',
        'description' => 'Barely used',
        'category_id' => $category->id,
        'starting_price' => 500,
        'duration_hours' => 24,
        'image' => UploadedFile::fake()->create('laptop.jpg', 100, 'image/jpeg'),
    ], jwtHeaders($user))->assertCreated();

    Storage::disk('public')->assertExists(Auction::first()->image_path);
});

it('rejects a duration longer than one week', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->postJson('/api/auctions', [
        'title' => 'MacBook Pro',
        'description' => 'Barely used',
        'category_id' => $category->id,
        'starting_price' => 500,
        'duration_hours' => 200,
    ], jwtHeaders($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('duration_hours');
});

it('rejects an unknown category', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auctions', [
        'title' => 'MacBook Pro',
        'description' => 'Barely used',
        'category_id' => 9999,
        'starting_price' => 500,
        'duration_hours' => 24,
    ], jwtHeaders($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('rejects an unauthenticated auction creation', function () {
    $this->postJson('/api/auctions', [])->assertUnauthorized();
});

// ----------------------------------------------------------- قائمة المزادات

it('lists only approved, active and unexpired auctions', function () {
    Auction::factory()->approved()->create(['title' => 'Visible']);
    Auction::factory()->pending()->create(['title' => 'Pending']);
    Auction::factory()->expired()->create(['title' => 'Expired']);
    Auction::factory()->flagged()->create(['title' => 'Flagged']);

    $response = $this->getJson('/api/auctions')->assertOk();

    expect($response->json('data.data'))->toHaveCount(1)
        ->and($response->json('data.data.0.title'))->toBe('Visible');
});

// انحدار للإصلاح رقم ٨: تغليف المجموعة كان يبتلع links و meta
it('returns pagination metadata with the auction list', function () {
    Auction::factory()->count(3)->approved()->create();

    $response = $this->getJson('/api/auctions?per_page=2')->assertOk();

    expect($response->json('data.meta.total'))->toBe(3)
        ->and($response->json('data.meta.per_page'))->toBe(2)
        ->and($response->json('data.data'))->toHaveCount(2)
        ->and($response->json('data.links'))->not->toBeNull();
});

// -------------------------------------------------------------- عرض مزاد

it('lets the owner view their own pending auction', function () {
    $owner = User::factory()->create();
    $auction = Auction::factory()->pending()->create(['user_id' => $owner->id]);

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($owner))
        ->assertOk()
        ->assertJsonPath('data.id', $auction->id);
});

it('forbids a stranger from viewing a pending auction', function () {
    $stranger = User::factory()->create();
    $auction = Auction::factory()->pending()->create();

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($stranger))
        ->assertForbidden();
});

it('lets anyone authenticated view an approved auction', function () {
    $viewer = User::factory()->create();
    $auction = Auction::factory()->approved()->create();

    $this->getJson("/api/auctions/{$auction->id}", jwtHeaders($viewer))->assertOk();
});

// ------------------------------------------------------- المزادات حسب التصنيف

// انحدار للإصلاح رقم ٣: category_id يأتي من المسار ولم يكن يصل للتحقق إطلاقاً
it('returns auctions for a category', function () {
    $category = Category::factory()->create();
    Auction::factory()->count(2)->approved()->create(['category_id' => $category->id]);
    Auction::factory()->approved()->create(); // تصنيف آخر

    $response = $this->getJson("/api/auctions/category/{$category->id}")->assertOk();

    expect($response->json('data.data'))->toHaveCount(2)
        ->and($response->json('data.meta.total'))->toBe(2);
});

it('rejects an unknown category id in the path', function () {
    $this->getJson('/api/auctions/category/9999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('excludes non approved auctions from the category listing', function () {
    $category = Category::factory()->create();
    Auction::factory()->pending()->create(['category_id' => $category->id]);

    $response = $this->getJson("/api/auctions/category/{$category->id}")->assertOk();

    expect($response->json('data.data'))->toHaveCount(0);
});

// ------------------------------------------------------------- مزاداتي

it('returns only the callers own auctions', function () {
    $user = User::factory()->create();
    Auction::factory()->count(2)->approved()->create(['user_id' => $user->id]);
    Auction::factory()->approved()->create(); // لمستخدم آخر

    $response = $this->getJson('/api/my-auctions', jwtHeaders($user))->assertOk();

    expect($response->json('data.meta.total'))->toBe(2);
});

it('filters my auctions by status', function () {
    $user = User::factory()->create();
    Auction::factory()->approved()->create(['user_id' => $user->id]);
    Auction::factory()->pending()->create(['user_id' => $user->id]);
    Auction::factory()->flagged()->create(['user_id' => $user->id]);
    Auction::factory()->expired()->create(['user_id' => $user->id]);

    $count = fn (string $status) => $this
        ->getJson("/api/my-auctions?status={$status}", jwtHeaders($user))
        ->assertOk()
        ->json('data.meta.total');

    expect($count('active'))->toBe(1)
        ->and($count('pending'))->toBe(1)
        ->and($count('rejected'))->toBe(1) // rejected يُخزَّن كـ flagged
        ->and($count('expired'))->toBe(1);
});

// المزاد قيد المراجعة يملك expires_at منذ لحظة إنشائه، فإذا طالت المراجعة
// تجاوز ذلك الوقت. يجب أن يبقى معلّقاً لا منتهياً.
it('does not report a stale pending auction as expired', function () {
    $user = User::factory()->create();

    Auction::factory()->pending()->create([
        'user_id' => $user->id,
        'expires_at' => now()->subDay(),
    ]);

    $count = fn (string $status) => $this
        ->getJson("/api/my-auctions?status={$status}", jwtHeaders($user))
        ->assertOk()
        ->json('data.meta.total');

    expect($count('expired'))->toBe(0)
        ->and($count('pending'))->toBe(1);
});

it('reports an auction closed by the scheduler as expired', function () {
    $user = User::factory()->create();

    // ما يتركه أمر auctions:close خلفه: معتمد، منتهي الوقت، ومُعطَّل
    Auction::factory()->expired()->create([
        'user_id' => $user->id,
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/my-auctions?status=expired', jwtHeaders($user))->assertOk();

    expect($response->json('data.meta.total'))->toBe(1);
});

it('rejects an unknown status filter', function () {
    $user = User::factory()->create();

    $this->getJson('/api/my-auctions?status=bogus', jwtHeaders($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});
