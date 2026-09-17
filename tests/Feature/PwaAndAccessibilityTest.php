<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PWA (بطاقة تطبيق قابل للتثبيت + عامل خدمة) والوضع الليلي ولمسات الوصولية
 * الإضافية (S12).
 *
 * ملفات public/manifest.json وpublic/sw.js ثابتة يخدمها خادم الويب مباشرة —
 * LinkIntegrityTest تتجاهلها عمدًا لأن عميل اختبار HTTP يمرّ بنواة Laravel
 * فلا يصل إليها (تحقّق فعلي عبر خادم حقيقي أثبت أنها تعمل، موثَّق هناك).
 * هذا الملف يتحقّق من محتواها مباشرة من القرص، ومن سلوكيات JS/Blade التي لا
 * يمكن اختبارها إلا كحراسة مصدر (نفس أسلوب DashboardViewTest لعطل U-01).
 */
class PwaAndAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_pwa_manifest_is_valid_json_with_the_required_fields(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertNotNull($manifest, 'manifest.json ليس JSON صالحًا.');
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertArrayHasKey('start_url', $manifest);
    }

    /** @test */
    public function the_service_worker_never_intercepts_non_get_requests(): void
    {
        $source = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString(
            "request.method !== 'GET'",
            $source,
            'عامل الخدمة يجب ألا يتدخّل في أي طلب كتابة (POST/PATCH/DELETE) — تلك مسؤولية طابور app.js لا التخزين المؤقت.'
        );
    }

    /** @test */
    public function authenticated_pages_link_the_manifest_and_include_the_theme_toggle(): void
    {
        $teacher = User::create([
            'name' => 'الأستاذ فهد', 'username' => 'fahad_pwa', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('id="themeToggle"', false);
    }

    /** @test */
    public function the_theme_is_applied_before_paint_via_a_synchronous_head_script(): void
    {
        // سكربت وحدة Vite المؤجَّل (type="module") يُنفَّذ بعد الرسم، فلا يمنع
        // الوميض — لا بدّ من سكربت عادي متزامن في <head> قبل @vite تحديدًا.
        foreach (['layouts/app.blade.php', 'layouts/guest.blade.php'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}"));
            $viteAt = strpos($source, '@vite(');
            $themeAt = strpos($source, "localStorage.getItem('keshf-theme')");

            $this->assertNotFalse($themeAt, "{$view}: سكربت تطبيق الوضع الليلي مفقود.");
            $this->assertLessThan($viteAt, $themeAt, "{$view}: سكربت الوضع الليلي يجب أن يسبق @vite لتفادي وميض الوضع الخاطئ.");
        }
    }

    /** @test */
    public function the_dashboard_row_selects_have_accessible_names_even_though_their_visual_label_is_hidden_on_desktop(): void
    {
        // انحدار وصولية: column-label مخفي بـ display:none على الشاشة
        // الواسعة (@media max-width:760px فقط يُظهره)، فلا يُقرأ اسمًا لقارئ
        // الشاشة هناك — القوائم المنسدلة (خلافًا لحقل الاسم النصّي الذي
        // يستفيد من placeholder كاسم بديل) لا تملك أي اسم بديل بلا aria-label.
        $teacher = User::create([
            'name' => 'الأستاذ سلطان', 'username' => 'sultan_pwa', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);
        $student = Student::create(['student_name' => 'وليد', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee("aria-label=\"حلقة {$student->student_name}\"", false)
            ->assertSee("aria-label=\"حالة {$student->student_name}\"", false);
    }

    /** @test */
    public function the_dark_theme_css_block_exists_and_can_win_over_the_system_preference(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(':root[data-theme="dark"]', $css);
    }
}
