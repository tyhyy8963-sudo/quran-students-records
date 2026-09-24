<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تبويب/صفحة "الحلقات" المستقلّة — طلب صريح من يحيى (2026-09-23): "ضيف
 * تبويب الحلقات في الأعلى مع القران والمتون والتحضير والتقارير والسجلات
 * وأحذفه من صفحة القرآن وضبط الصفحة حقت إدارة الحلقات". كانت إدارة الحلقات
 * (إضافة/حذف) محصورة في نافذة منبثقة داخل dashboard.blade.php (S24، الجزء
 * الثالث) خلف زرّ "⚙ إدارة الحلقات"؛ صار لها الآن مسار/صفحة/تبويب مستقلّ.
 * منطق الإضافة/الحذف نفسه (POST/DELETE /circles) لم يتغيّر ومُختبَر أصلًا في
 * CircleTest — هذا الملفّ يغطّي الصفحة الجديدة وإزالة العناصر القديمة فقط.
 */
class CirclesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ ياسر', 'username' => 'yasser_circles',
            'password' => Hash::make('secret123'),
            'mosque' => 'جامع النور', 'classroom' => 'حلقة العصر',
        ]);
    }

    /** @test */
    public function guests_cannot_view_the_circles_page(): void
    {
        $this->get('/circles')->assertRedirect('/login');
    }

    /** @test */
    public function the_page_lists_the_teachers_circles(): void
    {
        Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/circles');

        $response->assertOk();
        $response->assertSee('حلقة الفجر');
    }

    /** @test */
    public function the_circles_page_does_not_list_another_teachers_circles(): void
    {
        $otherTeacher = User::create([
            'name' => 'الأستاذ آخر', 'username' => 'other_circles', 'password' => Hash::make('secret123'),
        ]);
        Circle::create(['name' => 'حلقة الغريب', 'teacher_id' => $otherTeacher->id]);
        Circle::create(['name' => 'حلقتي', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/circles');

        $response->assertSee('حلقتي');
        $response->assertDontSee('حلقة الغريب');
    }

    /** @test */
    public function an_empty_circles_page_shows_the_empty_state_not_an_error(): void
    {
        $response = $this->actingAs($this->teacher)->get('/circles');

        $response->assertOk();
        $response->assertSee('لا حلقات بعد');
    }

    /** @test */
    public function the_page_offers_a_form_to_add_a_new_circle(): void
    {
        $response = $this->actingAs($this->teacher)->get('/circles');

        $response->assertOk();
        $response->assertSee('addCircleForm', false);
        $response->assertSee('+ إضافة حلقة');
    }

    /** @test */
    public function the_page_offers_a_delete_button_for_each_circle(): void
    {
        $circle = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/circles');

        $response->assertOk();
        $response->assertSee("data-id=\"{$circle->id}\"", false);
        $response->assertSee('delete-circle', false);
    }

    /**
     * التنقّل العلوي (طلب صريح من يحيى): تبويب "الحلقات" يظهر بجانب
     * القرآن/المتون/التحضير/التقارير/السجلات لكل صفحات المعلّم.
     */
    /** @test */
    public function the_top_navigation_links_to_the_circles_page(): void
    {
        $response = $this->actingAs($this->teacher)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('circles.index'), false);
        $response->assertSee('الحلقات');
    }

    /**
     * إزالة إدارة الحلقات من لوحة "قرآن" (طلب صريح من يحيى: "أحذفه من صفحة
     * القرآن") — الزرّ والنافذة المنبثقة القديمان لم يعودا موجودين هناك،
     * الإدارة الآن حصرًا من صفحة /circles أعلاه.
     */
    /** @test */
    public function the_dashboard_no_longer_shows_the_manage_circles_button_or_modal(): void
    {
        $response = $this->actingAs($this->teacher)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('manageCirclesBtn', false);
        $response->assertDontSee('circlesModalBackdrop', false);
        $response->assertDontSee('إدارة الحلقات');
    }

    /**
     * إزالة "إضافة طالب" من لوحة "قرآن" (طلب صريح من يحيى: "أحذف زر إضافة
     * الطالب من تبويب القرآن وخليه فقط في السجلات") — الزرّ لم يعد موجودًا
     * هنا؛ صفحة "السجلات" وحدها تتيح الإضافة الآن (راجع RecordsTest).
     */
    /** @test */
    public function the_dashboard_no_longer_shows_the_add_student_button(): void
    {
        $response = $this->actingAs($this->teacher)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('addStudentBtn', false);
        $response->assertDontSee('+ إضافة طالب');
    }
}
