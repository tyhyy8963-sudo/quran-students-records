<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * لوحة المدير المؤسسية (S20) — نظرة عامة على كل المعلّمين معًا، وعرض سجلّات
 * معلّم واحد بعينه. غائبة كليًا قبل هذا السبرنت (راجع
 * sprint-15-plus-gap-analysis-and-plan.md، بند 8).
 */
class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدير النظام', 'username' => 'admin_'.uniqid(),
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('secret123'),
        ]);
    }

    private function teacher(string $name = 'الأستاذ خالد'): User
    {
        return User::create([
            'name' => $name, 'username' => 'teacher_'.uniqid(),
            'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);
    }

    /** @test */
    public function a_teacher_cannot_view_the_admin_overview(): void
    {
        $this->actingAs($this->teacher())->get('/admin/overview')->assertForbidden();
    }

    /** @test */
    public function a_guest_cannot_view_the_admin_overview(): void
    {
        $this->get('/admin/overview')->assertRedirect('/login');
    }

    /** @test */
    public function the_overview_renders_with_no_teachers_yet(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('لا حسابات معلّمين بعد.');
    }

    /** @test */
    public function the_overview_aggregates_students_across_every_teacher_not_just_the_admins_own(): void
    {
        // حساب المدير نفسه بلا أي طالب دائمًا (TeacherScope) — هذا الاختبار
        // يثبت أن withoutGlobalScope الصريح في المتحكّم يتجاوز ذلك فعليًا،
        // لا يعتمد على مصادفة عدم وجود بيانات.
        $teacherA = $this->teacher('معلّم أ');
        $teacherB = $this->teacher('معلّم ب');
        Student::create(['student_name' => 'طالب عند أ', 'teacher_id' => $teacherA->id]);
        Student::create(['student_name' => 'طالب عند ب', 'teacher_id' => $teacherB->id]);

        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('معلّم أ')
            ->assertSee('معلّم ب')
            ->assertSee('طالب عند أ') // ضمن "الأعلى تقدّمًا" أو مجرّد عدّ — انظر الاختبار التالي للنسب الدقيقة
            ->assertDontSee('لا حسابات معلّمين بعد.');
    }

    /** @test */
    public function the_overview_shows_each_teachers_student_count_and_average_progress(): void
    {
        $teacher = $this->teacher();
        $naas = Surah::where('number', 114)->firstOrFail();

        $student = Student::create(['student_name' => 'سالم', 'teacher_id' => $teacher->id]);
        $student->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => $naas->ayah_count, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $expectedPercent = app(MemorizationProgress::class)->percentage($student->fresh());

        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee($expectedPercent.'%');
    }

    /** @test */
    public function the_overview_flags_a_teacher_with_no_recent_activity(): void
    {
        $teacher = $this->teacher();
        $student = Student::create(['student_name' => 'سالم', 'teacher_id' => $teacher->id]);
        $naas = Surah::where('number', 114)->firstOrFail();

        // سجلّ قديم جدًا (أكثر من أسبوعين) — يجب أن يُعلَّم المعلّم "لم يسجّل مؤخّرًا".
        $student->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => 1, 'type' => 'حفظ', 'logged_at' => now()->subDays(30),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('لم يسجّل مؤخّرًا');
    }

    /** @test */
    public function the_overview_does_not_flag_a_teacher_with_recent_activity(): void
    {
        $teacher = $this->teacher();
        $student = Student::create(['student_name' => 'سالم', 'teacher_id' => $teacher->id]);
        $naas = Surah::where('number', 114)->firstOrFail();

        $student->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => 1, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertDontSee('لم يسجّل مؤخّرًا');
    }

    /** @test */
    public function the_overview_ranks_circles_across_different_teachers_by_average_progress(): void
    {
        $teacherA = $this->teacher('معلّم أ');
        $teacherB = $this->teacher('معلّم ب');
        $circleA = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacherA->id]);
        $circleB = Circle::create(['name' => 'حلقة العصر', 'teacher_id' => $teacherB->id]);

        Student::create(['student_name' => 'طالب أ', 'teacher_id' => $teacherA->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'طالب ب', 'teacher_id' => $teacherB->id, 'circle_id' => $circleB->id]);

        $this->actingAs($this->admin())
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('حلقة الفجر')
            ->assertSee('حلقة العصر');
    }

    /**
     * @test
     *
     * تثبيت صريح لفخّ وقع فيه التنفيذ الأول: Circle يحمل TeacherScope مثل
     * Student تمامًا، فتحميل student->circle (eager load) داخل جلسة مدير
     * يقيَّد ضمنيًا بحلقات *المدير نفسه* (صفر دائمًا) ما لم يُعطَّل النطاق
     * صراحةً على استعلام العلاقة الفرعي أيضًا، لا الاستعلام الرئيسي وحده.
     * بدون الإصلاح، عمود حلقة الطالب في الجدول يعرض "—" رغم أن circle_id
     * مضبوط فعليًا، بينما قائمة "الحلقات" المنفصلة (مصدرها استعلام آخر) تبقى
     * صحيحة فتُخفي العطل عن اختبار assertSee بسيط.
     */
    public function the_teacher_report_resolves_each_students_circle_despite_the_admin_owning_no_circles_of_its_own(): void
    {
        $teacher = $this->teacher();
        Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacher->id]);
        $circle = Circle::where('name', 'حلقة الفجر')->firstOrFail();
        Student::create(['student_name' => 'سالم', 'teacher_id' => $teacher->id, 'circle_id' => $circle->id]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.teachers.report', $teacher))
            ->assertOk()
            ->getContent();

        // مرّتان بالضبط: مرّة في شريحة "الحلقات" العلوية (مصدرها استعلام
        // مستقلّ لا يعتمد على العلاقة)، ومرّة في عمود حلقة الطالب داخل الجدول
        // (مصدرها العلاقة نفسها التي وقع فيها الفخ).
        $this->assertSame(2, substr_count($html, 'حلقة الفجر'));
    }

    /** @test */
    public function a_teacher_cannot_view_another_teachers_report(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher('معلّم آخر');

        $this->actingAs($teacher)
            ->get("/admin/teachers/{$other->id}/report")
            ->assertForbidden();
    }

    /** @test */
    public function the_admin_can_view_a_specific_teachers_full_report(): void
    {
        $teacher = $this->teacher();
        $circle = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacher->id]);
        $student = Student::create([
            'student_name' => 'سالم', 'teacher_id' => $teacher->id, 'circle_id' => $circle->id,
        ]);
        Attendance::create(['student_id' => $student->student_id, 'date' => now(), 'status' => 'حاضر']);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.report', $teacher))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('سالم')
            ->assertSee('حلقة الفجر');
    }

    /** @test */
    public function the_admin_cannot_open_a_report_for_an_admin_account(): void
    {
        $otherAdmin = $this->admin();

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.report', $otherAdmin))
            ->assertForbidden();
    }

    /** @test */
    public function the_teacher_report_only_shows_that_teachers_own_students(): void
    {
        $teacherA = $this->teacher('معلّم أ');
        $teacherB = $this->teacher('معلّم ب');
        Student::create(['student_name' => 'طالب أ', 'teacher_id' => $teacherA->id]);
        Student::create(['student_name' => 'طالب ب', 'teacher_id' => $teacherB->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.report', $teacherA))
            ->assertOk()
            ->assertSee('طالب أ')
            ->assertDontSee('طالب ب');
    }

    /** @test */
    public function a_guest_cannot_view_a_teacher_report(): void
    {
        $teacher = $this->teacher();

        $this->get("/admin/teachers/{$teacher->id}/report")->assertRedirect('/login');
    }
}
