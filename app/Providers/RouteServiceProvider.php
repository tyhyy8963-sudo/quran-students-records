<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        /* -----------------------------------------------------------------
         | حدود المحاولات (العطل C-05)
         |
         | تُستخدم محدِّدات مُسمّاة لا الصيغة المختصرة throttle:5,1، لأن الصيغة
         | المختصرة تبني مفتاحها من (النطاق + عنوان IP) فقط دون مسار الطلب،
         | فتتشارك كل المسارات عدّادًا واحدًا: ثلاث محاولات إرسال رمز كانت
         | تستهلك حصّة محاولات التحقق. المحدِّد المُسمّى يُدخل اسمه في المفتاح،
         | فيصبح لكل مسار عدّاده المستقل.
         |-----------------------------------------------------------------*/

        // تسجيل الدخول: خمس محاولات في الدقيقة لكل (اسم مستخدم + IP).
        // صار المفتاح على username لا email بعد إغلاق نظام الحسابات (S13):
        // لو بقي على حقل لم يعد يُرسَل، لأصبح المفتاح "|IP" لكل المحاولات —
        // حدٌّ واحد مشترك بين كل من يقف خلف نفس الشبكة.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip());
        });

        // تغيير كلمة المرور (المدير لحسابه) وإعادة تعيينها لمعلّم: خمس محاولات
        // في الدقيقة لكل مستخدم. لم يعد هناك محدِّد "register" ولا
        // "password-reset" — المسارات نفسها أُزيلت مع نظام التسجيل الذاتي.
        RateLimiter::for('password-update', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
