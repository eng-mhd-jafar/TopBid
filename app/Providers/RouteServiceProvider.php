<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        // نصيحة إضافية: لضمان عمل قاعدة البيانات بنفس التوقيت
        config(['database.connections.mysql.timezone' => '+00:00']);

        // لا تُضبط استجابة مخصّصة على أي محدد، فتُرمى
        // TooManyRequestsHttpException ويخرجها المعالج الموحّد بالشكل نفسه.
        RateLimiter::for('otp-limiter', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by(self::emailAndIp($request)));

        RateLimiter::for('auth-forgot-password', fn (Request $request) => Limit::perMinute(3)->by(self::emailAndIp($request)));

        RateLimiter::for('auth-reset-password', fn (Request $request) => Limit::perMinute(5)->by(self::emailAndIp($request)));
    }

    /** مفتاح مركّب حتى لا يحجب مهاجم واحد كل المستخدمين خلف نفس العنوان */
    private static function emailAndIp(Request $request): string
    {
        return ($request->input('email') ?: 'guest').'|'.$request->ip();
    }
}
