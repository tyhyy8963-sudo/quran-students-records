<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * حراسة على الروابط: كل رابط داخلي في أي صفحة يجب أن يقود إلى شيء موجود.
 *
 * أُضيف بعد أن كشف السبرنت الثاني أن ثابت RouteServiceProvider::HOME كان
 * يشير إلى '/home' — مسار غير موجود — فأي إعادة توجيه إليه تعطي 404 صامتًا.
 */
class LinkIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        return User::create([
            'name' => 'الأستاذ عمر', 'username' => 'omar',
            'password' => Hash::make('secret123'),
            'mosque' => 'جامع الإيمان', 'classroom' => 'حلقة الفجر',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'مدير النظام', 'username' => 'admin',
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('secret123'),
        ]);
    }

    /** @return string[] */
    private function internalLinks(string $html): array
    {
        preg_match_all('/href="([^"#]+)"/i', $html, $matches);

        return collect($matches[1])
            ->filter(fn ($href) => str_starts_with($href, '/') || str_starts_with($href, config('app.url')))
            ->map(fn ($href) => str_replace(config('app.url'), '', $href))
            // ملفات ثابتة في public/ يخدمها خادم الويب مباشرة (أو الخادم
            // المدمج في php artisan serve) دون المرور بتوجيه Laravel إطلاقًا
            // — طلبها عبر عميل اختبار HTTP الذي يمرّ بنواة Laravel مباشرة
            // يعطي 404 دائمًا بصرف النظر عن وجود الملف فعليًا (تحقّق يدوي
            // عبر خادم حقيقي أثبت أنها تُخدَم بنجاح، S12). manifest.json
            // وsw.js وأيقونات PWA (S12) امتداد لنفس استثناء css/js الأصلي
            // من S5 لنفس السبب بالضبط.
            ->reject(fn ($href) => str_starts_with($href, '/css/')
                || str_starts_with($href, '/js/')
                || str_starts_with($href, '/build/')
                || str_starts_with($href, '/images/')
                || in_array($href, ['/manifest.json', '/sw.js', '/favicon.ico', '/robots.txt'], true))
            ->unique()
            ->values()
            ->all();
    }

    /** @test */
    public function every_link_on_a_guest_page_resolves(): void
    {
        // صفحتان فقط للزائر بعد إغلاق النظام (S13): لا تسجيل ولا استعادة.
        foreach (['/', '/login'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ($this->internalLinks($html) as $link) {
                $status = $this->get($link)->getStatusCode();

                $this->assertNotContains(
                    $status,
                    [404, 500],
                    "الرابط {$link} في الصفحة {$page} يعطي {$status}."
                );
            }
        }
    }

    /** @test */
    public function every_link_on_an_authenticated_page_resolves(): void
    {
        $user = $this->teacher();

        foreach (['/dashboard', '/profile', '/profile/edit'] as $page) {
            $html = $this->actingAs($user)->get($page)->assertOk()->getContent();

            foreach ($this->internalLinks($html) as $link) {
                $status = $this->actingAs($user)->get($link)->getStatusCode();

                $this->assertNotContains(
                    $status,
                    [404, 500],
                    "الرابط {$link} في الصفحة {$page} يعطي {$status} لمعلّم مسجّل دخوله."
                );
            }
        }
    }

    /**
     * @test
     *
     * صفحات المدير لها روابطها الخاصة (حسابات المعلّمين، تغيير كلمة المرور)
     * ولا يصلها المعلّم أصلًا — فتُفحص بحساب مدير لا بحساب معلّم.
     */
    public function every_link_on_an_admin_page_resolves(): void
    {
        $admin = $this->admin();

        foreach (['/admin/teachers', '/admin/teachers/create', '/edit-password', '/profile'] as $page) {
            $html = $this->actingAs($admin)->get($page)->assertOk()->getContent();

            foreach ($this->internalLinks($html) as $link) {
                $status = $this->actingAs($admin)->get($link)->getStatusCode();

                $this->assertNotContains(
                    $status,
                    [404, 500],
                    "الرابط {$link} في الصفحة {$page} يعطي {$status} لمدير مسجّل دخوله."
                );
            }
        }
    }

    /** @test */
    public function the_home_constant_points_at_a_real_route(): void
    {
        $status = $this->actingAs($this->teacher())
                       ->get(\App\Providers\RouteServiceProvider::HOME)
                       ->getStatusCode();

        $this->assertNotSame(404, $status, 'ثابت HOME يشير إلى مسار غير موجود.');
    }
}
