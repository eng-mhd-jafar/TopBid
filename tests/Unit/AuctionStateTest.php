<?php

use App\Models\Auction;

/**
 * حالة المزاد مشتقة من ثلاثة أعمدة معاً. هذه الاختبارات تثبّت التعريف الواحد
 * الذي يستهلكه الجميع، بحيث لا يعيد أي استعلام تركيب الشروط بنفسه.
 */

// ------------------------------------------------------------ نطاق live

it('counts only approved, active and unexpired auctions as live', function () {
    $live = Auction::factory()->approved()->create();
    Auction::factory()->pending()->create();
    Auction::factory()->rejected()->create();
    Auction::factory()->expired()->create();
    Auction::factory()->approved()->create(['is_active' => false]);

    $ids = Auction::live()->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($live->id);
});

// ------------------------------------------------------------ نطاق ended

it('counts an approved auction past its expiry as ended', function () {
    $ended = Auction::factory()->expired()->create();

    expect(Auction::ended()->pluck('id')->all())->toBe([$ended->id]);
});

it('still counts an auction closed by the scheduler as ended', function () {
    Auction::factory()->expired()->create(['is_active' => false]);

    expect(Auction::ended()->count())->toBe(1);
});

// المزاد قيد المراجعة يملك expires_at منذ إنشائه، فطول المراجعة يتجاوزه.
// هذه هي الحالة التي كان الفلتر القديم يصنّفها منتهية عن خطأ.
it('never counts a stale pending or rejected auction as ended', function () {
    Auction::factory()->pending()->create(['expires_at' => now()->subDay()]);
    Auction::factory()->rejected()->create(['expires_at' => now()->subDay()]);

    expect(Auction::ended()->count())->toBe(0);
});

it('never counts a live auction as ended', function () {
    Auction::factory()->approved()->create();

    expect(Auction::ended()->count())->toBe(0);
});

// -------------------------------------------------- نطاق awaitingClosure

it('selects auctions still flagged active whose time is up', function () {
    $due = Auction::factory()->expired()->create();
    Auction::factory()->approved()->create();                          // ما زال يعمل
    Auction::factory()->expired()->create(['is_active' => false]);      // أُغلق سابقاً

    expect(Auction::awaitingClosure()->pluck('id')->all())->toBe([$due->id]);
});

// ------------------------------------------------- نطاقات حالة المراجعة

it('separates review states', function () {
    Auction::factory()->pending()->create();
    Auction::factory()->approved()->create();
    Auction::factory()->rejected()->create();

    expect(Auction::pendingReview()->count())->toBe(1)
        ->and(Auction::approved()->count())->toBe(1)
        ->and(Auction::rejected()->count())->toBe(1);
});

it('stores a rejected auction as flagged', function () {
    $auction = Auction::factory()->rejected()->create();

    expect($auction->moderation_status)->toBe('flagged')
        ->and(Auction::STATUS_REJECTED)->toBe('flagged');
});

// ------------------------------------------------------------ المسنِدات

it('answers the instance predicates for a live auction', function () {
    $auction = Auction::factory()->approved()->create();

    expect($auction->isLive())->toBeTrue()
        ->and($auction->isApproved())->toBeTrue()
        ->and($auction->isRejected())->toBeFalse()
        ->and($auction->hasEnded())->toBeFalse();
});

it('treats a missing expiry as ended', function () {
    $auction = Auction::factory()->approved()->create(['expires_at' => null]);

    expect($auction->hasEnded())->toBeTrue()
        ->and($auction->isLive())->toBeFalse();
});

it('does not consider an approved but deactivated auction live', function () {
    $auction = Auction::factory()->approved()->create(['is_active' => false]);

    expect($auction->isApproved())->toBeTrue()
        ->and($auction->isLive())->toBeFalse();
});

// -------------------------------------------- التطابق بين النطاق والمسنِد

// جوهر إعادة الهيكلة: الاستعلام والنسخة المحمَّلة يجب أن يصنّفا المزاد نفسه
// بالطريقة نفسها. أي انحراف بينهما هو عودة إلى المشكلة الأصلية.
it('classifies every auction identically by query and by instance', function () {
    $auctions = collect([
        Auction::factory()->approved()->create(),
        Auction::factory()->pending()->create(),
        Auction::factory()->rejected()->create(),
        Auction::factory()->expired()->create(),
        Auction::factory()->approved()->create(['is_active' => false]),
        Auction::factory()->approved()->create(['expires_at' => null]),
    ]);

    $liveByQuery = Auction::live()->pluck('id')->sort()->values()->all();
    $liveByPredicate = $auctions->filter->isLive()->pluck('id')->sort()->values()->all();

    expect($liveByQuery)->toBe($liveByPredicate);
});
