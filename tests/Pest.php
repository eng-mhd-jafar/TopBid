<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * ترويسة مصادقة بتوكن JWT حقيقي.
 *
 * ملاحظة أولى: actingAs($user, 'jwt') لا ينفع مع المسارات المحمية،
 * لأن EnsureJwtTokenVersionMatches يستدعي JWTAuth::parseToken()
 * التي تحتاج ترويسة Authorization فعلية وترمي 401 بدونها.
 *
 * ملاحظة ثانية: في الاختبارات تبقى الحاوية نفسها حية عبر عدة طلبات، بينما
 * كل طلب حقيقي يحصل على حاوية جديدة. لذلك يجب تصفير حالتين قبل كل طلب:
 * الـ guard الذي يخزّن المستخدم بعد أول استدعاء، و singleton الـ JWT الذي
 * يخزّن التوكن المُحلَّل. بدون ذلك يُصادَق الطلب الثاني كالمستخدم الأول.
 *
 * انتبه: الواجهة JWTAuth تشير إلى tymon.jwt.auth بينما الـ guard يستخدم
 * كائناً مختلفاً هو tymon.jwt، وهو الذي يجب تصفيره هنا.
 *
 * @return array<string, string>
 */
/**
 * يستبدل قناة fcm بقناة صامتة.
 *
 * تحتاجه أي اختبار يرسل إشعاراً حقيقياً بدل Notification::fake، لأن القناة
 * الحقيقية تتصل بفايربيز فترمي "Driver [fcm] not supported" في بيئة الاختبار.
 * القناتان database و broadcast تبقيان حقيقيتين، والثانية على السائق null.
 */
function stubFcmChannel(): void
{
    Illuminate\Support\Facades\Notification::extend('fcm', fn () => new class
    {
        public function send($notifiable, $notification): void
        {
        }
    });
}

function jwtHeaders(App\Models\User $user): array
{
    app('auth')->forgetGuards();
    app(PHPOpenSourceSaver\JWTAuth\JWT::class)->unsetToken();

    return [
        'Authorization' => 'Bearer '.PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($user),
        'Accept' => 'application/json',
    ];
}
