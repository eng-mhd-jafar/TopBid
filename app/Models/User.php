<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'jwt_token_version',
        'phone_number',
        'OTP',
        'email_verified_at',
        'verification_code_expires_at',
        'last_otp_at',
        'failed_attempts',
        'avatar',
        'address',
        'city',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_otp_at' => 'datetime',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'token_version' => (int) $this->jwt_token_version,
        ];
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class, 'user_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function hasActiveActivity(): bool
    {
        $hasActiveAuctions = $this->auctions()->active()->exists();
        $hasActiveBids = $this->bids()->whereHas('auction', function ($q) {
            $q->active();
        })->exists();

    return $hasActiveAuctions || $hasActiveBids;
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function updateDeviceToken(array $data): void
    {
        // 1. تنظيف: لو التوكن مسجل لمستخدم آخر، نحذفه منه (لأن الجهاز صار مع شخص جديد)
        UserDevice::where('fcm_token', $data['fcm_token'])
            ->where('user_id', '!=', $this->id)
            ->delete();

        // 2. تحديث أو إنشاء: نربط التوكن بالمستخدم الحالي
        $this->devices()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']], // ابحث بهذا الشرط
            [
                'device_type' => $data['device_type'] ?? null,
                'device_name' => $data['device_name'] ?? null,
            ]
        );
    }
    public function routeNotificationForFcm()
    {
        // جلب جميع قيم fcm_token من جدول الأجهزة المرتبط بالمستخدم
        return $this->devices()->pluck('fcm_token')->toArray();
    }

}
