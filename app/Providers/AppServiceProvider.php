<?php

namespace App\Providers;

use App\Support\MemorizationProgress;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // نسخة واحدة لكل طلب (S14): تحمل مرجع السور الـ114 وتغطية الطلاب
        // المحسوبة مسبقًا، فلا تتكرّر قراءتهما لكل صفّ في لوحة فيها عشرات
        // الطلاب. singleton لا scoped: التطبيق لا يعمل تحت خادم مقيم
        // (Octane/Swoole)، فحياة النسخة هي حياة الطلب نفسه.
        $this->app->singleton(MemorizationProgress::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
