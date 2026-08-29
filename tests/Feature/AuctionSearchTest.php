<?php

use App\Models\Auction;
use App\Models\Category;

// ------------------------------------------------------------------ البحث

it('finds auctions by title', function () {
    Auction::factory()->approved()->create(['title' => 'Vintage Camera']);
    Auction::factory()->approved()->create(['title' => 'Gaming Laptop']);

    $response = $this->getJson('/api/auctions?search=camera')->assertOk();

    expect($response->json('data.meta.total'))->toBe(1)
        ->and($response->json('data.data.0.title'))->toBe('Vintage Camera');
});

it('never surfaces a non live auction through search', function () {
    Auction::factory()->pending()->create(['title' => 'Hidden Camera']);
    Auction::factory()->expired()->create(['title' => 'Expired Camera']);
    Auction::factory()->rejected()->create(['title' => 'Rejected Camera']);

    expect($this->getJson('/api/auctions?search=camera')->json('data.meta.total'))->toBe(0);
});

it('returns an empty page when nothing matches', function () {
    Auction::factory()->approved()->create(['title' => 'Gaming Laptop']);

    $response = $this->getJson('/api/auctions?search=submarine')->assertOk();

    expect($response->json('data.meta.total'))->toBe(0)
        ->and($response->json('data.data'))->toBe([]);
});

// ---------------------------------------------------------------- الفلاتر

it('filters the listing by category', function () {
    $category = Category::factory()->create();
    Auction::factory()->count(2)->approved()->create(['category_id' => $category->id]);
    Auction::factory()->approved()->create();

    expect($this->getJson("/api/auctions?category_id={$category->id}")->json('data.meta.total'))
        ->toBe(2);
});

it('filters by price range', function () {
    Auction::factory()->approved()->create(['current_price' => 50]);
    Auction::factory()->approved()->create(['current_price' => 500]);
    Auction::factory()->approved()->create(['current_price' => 5000]);

    expect($this->getJson('/api/auctions?min_price=100&max_price=1000')->json('data.meta.total'))
        ->toBe(1);
});

// صفر سعر صالح: استخدام empty بدل isset كان سيُسقط هذا الفلتر بصمت
it('treats a zero minimum price as a real filter', function () {
    Auction::factory()->approved()->create(['current_price' => 0]);
    Auction::factory()->approved()->create(['current_price' => 100]);

    expect($this->getJson('/api/auctions?min_price=0&max_price=50')->json('data.meta.total'))
        ->toBe(1);
});

it('combines search with a price filter', function () {
    Auction::factory()->approved()->create(['title' => 'Cheap Camera', 'current_price' => 50]);
    Auction::factory()->approved()->create(['title' => 'Costly Camera', 'current_price' => 9000]);
    Auction::factory()->approved()->create(['title' => 'Cheap Laptop', 'current_price' => 60]);

    $response = $this->getJson('/api/auctions?search=camera&max_price=100')->assertOk();

    expect($response->json('data.meta.total'))->toBe(1)
        ->and($response->json('data.data.0.title'))->toBe('Cheap Camera');
});

// ---------------------------------------------------------------- الترتيب

// أهم ترتيب في منصة مزادات، ولم يكن موجوداً إطلاقاً
it('sorts by ending soonest first', function () {
    $late = Auction::factory()->approved()->create(['expires_at' => now()->addDays(5)]);
    $soon = Auction::factory()->approved()->create(['expires_at' => now()->addHour()]);
    $mid = Auction::factory()->approved()->create(['expires_at' => now()->addDay()]);

    $ids = $this->getJson('/api/auctions?sort=ending_soon')->assertOk()->json('data.data.*.id');

    expect($ids)->toBe([$soon->id, $mid->id, $late->id]);
});

it('sorts by price ascending and descending', function () {
    $cheap = Auction::factory()->approved()->create(['current_price' => 10]);
    $dear = Auction::factory()->approved()->create(['current_price' => 9000]);

    expect($this->getJson('/api/auctions?sort=price_asc')->json('data.data.*.id'))
        ->toBe([$cheap->id, $dear->id])
        ->and($this->getJson('/api/auctions?sort=price_desc')->json('data.data.*.id'))
        ->toBe([$dear->id, $cheap->id]);
});

it('defaults to newest first', function () {
    $older = Auction::factory()->approved()->create(['created_at' => now()->subDay()]);
    $newer = Auction::factory()->approved()->create(['created_at' => now()]);

    expect($this->getJson('/api/auctions')->json('data.data.*.id'))
        ->toBe([$newer->id, $older->id]);
});

// بلا معيار ثانٍ يصبح توزيع الصفوف المتساوية على الصفحات غير مستقر
it('paginates deterministically when the sort column ties', function () {
    Auction::factory()->count(6)->approved()->create(['current_price' => 100]);

    $firstPage = $this->getJson('/api/auctions?sort=price_asc&per_page=3')->json('data.data.*.id');
    $secondPage = $this->getJson('/api/auctions?sort=price_asc&per_page=3&page=2')->json('data.data.*.id');

    expect(array_intersect($firstPage, $secondPage))->toBe([])
        ->and(count(array_merge($firstPage, $secondPage)))->toBe(6);
});

// ---------------------------------------------------------------- التحقق

it('rejects an unknown sort value', function () {
    $this->getJson('/api/auctions?sort=cheapest')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

it('rejects an inverted price range', function () {
    $this->getJson('/api/auctions?min_price=500&max_price=100')
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_price');
});

it('accepts a maximum price on its own', function () {
    $this->getJson('/api/auctions?max_price=100')->assertOk();
});

it('rejects an unknown category filter', function () {
    $this->getJson('/api/auctions?category_id=9999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('rejects an oversized page size', function () {
    $this->getJson('/api/auctions?per_page=500')
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});
