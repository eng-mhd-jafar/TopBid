<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    /**
     * التخزين يستخدم flagged بينما مفردات الـ API تستخدم rejected.
     * هذا الثابت هو المكان الوحيد الذي يجب أن يعرف هذا الفرق.
     */
    public const STATUS_REJECTED = 'flagged';

    protected $fillable = [
        'category_id',
        'user_id',
        'winner_id',
        'winning_bid_id',
        'title',
        'description',
        'image_path',
        'specs',
        'starting_price',
        'current_price',
        'final_price',
        'duration_hours',
        'is_active',
        'moderation_status',
        'rejection_reason',
        'started_at',
        'expires_at',
        'closed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    protected $casts = [
        'specs' => 'array',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * أعلى مزايدة على المزاد.
     *
     * المعرّف يكسر التعادل: مزايدتان بنفس المبلغ تجعلان MAX(amount) وحده
     * غير حاسم، فتُرجَع الأحدث منهما.
     */
    public function highestBid()
    {
        return $this->hasOne(Bid::class)->ofMany([
            'amount' => 'max',
            'id' => 'max',
        ]);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function winningBid()
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    /*
    |--------------------------------------------------------------------------
    | نطاقات الحالة
    |--------------------------------------------------------------------------
    |
    | حالة المزاد مشتقة من ثلاثة أعمدة معاً. تُعرَّف هنا مرة واحدة فقط،
    | ولا يجوز لأي استعلام أن يعيد تركيب هذه الشروط بنفسه.
    |
    */

    /** معتمد ونشط ولم ينته وقته بعد */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_APPROVED)
            ->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    /** معتمد وانقضى وقته، سواء أغلقه المجدول أم لم يصل إليه بعد */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_APPROVED)
            ->where('expires_at', '<=', now());
    }

    /**
     * ما ينتظر أمر الإغلاق: ما زال مُعلَّماً كنشط لكن وقته انتهى.
     * يتعمّد عدم اشتراط الاعتماد، فأي مزاد نشط منتهٍ يجب أن يُغلق.
     */
    public function scopeAwaitingClosure(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '<=', now());
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_REJECTED);
    }

    /** المزادات التي أُغلقت لصالح هذا المستخدم */
    public function scopeWonBy(Builder $query, int $userId): Builder
    {
        return $query->where('winner_id', $userId);
    }

    /**
     * ترتيب قائمة المزادات.
     *
     * المعرّف يُضاف دائماً كمعيار ثانٍ: بدونه تتساوى صفوف في عمود الترتيب
     * فيصير توزيعها على الصفحات غير مستقر، وقد يتكرر صف أو يسقط بين صفحتين.
     *
     * ending_soon مخصّص للقوائم الحية حيث expires_at مضمون غير فارغ.
     */
    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'ending_soon' => $query->orderBy('expires_at')->orderBy('id'),
            'price_asc' => $query->orderBy('current_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('current_price')->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /**
     * كل ما يحتاجه AuctionResource لعرض مزاد كاملاً.
     *
     * يُعرَّف هنا مرة واحدة حتى لا ينسى أي استعلام تحميل علاقة فينتج
     * استعلامات N+1 أو حقول ناقصة تختلف من نقطة نهاية لأخرى.
     */
    public function scopeWithListingData(Builder $query): Builder
    {
        return $query->with(['user', 'category', 'highestBid'])->withCount('bids');
    }

    /*
    |--------------------------------------------------------------------------
    | مسنِدات الحالة
    |--------------------------------------------------------------------------
    |
    | نفس التعريف أعلاه لكن على نسخة محمَّلة، لتستهلكها السياسات والخدمات
    | بدل مقارنة النصوص يدوياً.
    |
    */

    public function isApproved(): bool
    {
        return $this->moderation_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->moderation_status === self::STATUS_REJECTED;
    }

    public function hasEnded(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isLive(): bool
    {
        return $this->isApproved() && $this->is_active && ! $this->hasEnded();
    }
}
