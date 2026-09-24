<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تصدير تقارير المدير (S37 — بند 4 من خطّة التقارير المعتمَدة): تصدير جدول
 * مقارنة المعلّمين (AdminOverviewController::exportCsv) وجدول طلاب معلّم واحد
 * (exportTeacherCsv) — امتدادًا لشاشتَي admin.overview/admin.teachers.report
 * المختبَرتين أصلًا في AdminOverviewTest.php، بلا أي مسار عرض جديد هنا.
 */
class AdminOverviewExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدير النظام', 'username' => 'admin_export_'.uniqid(),
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('secret123'),
        ]);
    }

    private function teacher(string $name = 'الأستاذ خالد'): User
    {
        return User::create([
            'name' => $name, 'username' => 'teacher_export_'.uniqid(), 'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function a_teacher_cannot_export_the_admin_overview(): void
    {
        $this->actingAs($this->teacher())->get('/admin/overview/export')->assertForbidden();
    }

    /** @test */
    public function a_guest_cannot_export_the_admin_overview(): void
    {
        $this->get('/admin/overview/export')->assertRedirect('/login');
    }

    /** @test */
    public function it_exports_a_real_csv_file_with_every_teachers_row(): void
    {
        $teacherA = $this->teacher('معلّم أ');
        $teacherB = $this->teacher('معلّم ب');
        Student::create(['student_name' => 'طالب عند أ', 'teacher_id' => $teacherA->id]);
        Student::create(['student_name' => 'طالب عند ب', 'teacher_id' => $teacherB->id]);

        $response = $this->actingAs($this->admin())->get('/admin/overview/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('معلّم أ', $content);
        $this->assertStringContainsString('معلّم ب', $content);
    }

    /** @test */
    public function a_teacher_cannot_export_another_teachers_report(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher('معلّم آخر');

        $this->actingAs($teacher)
            ->get("/admin/teachers/{$other->id}/report/export")
            ->assertForbidden();
    }

    /** @test */
    public function it_exports_a_teachers_student_table_as_csv(): void
    {
        $teacher = $this->teacher();
        $circle = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacher->id]);
        Student::create(['student_name' => 'سالم', 'teacher_id' => $teacher->id, 'circle_id' => $circle->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teachers.report.export', $teacher));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('سالم', $content);
        $this->assertStringContainsString('حلقة الفجر', $content);
    }

    /** @test */
    public function it_cannot_export_a_report_for_an_admin_account(): void
    {
        $otherAdmin = $this->admin();

        $this->actingAs($this->admin())
            ->get(route('admin.teachers.report.export', $otherAdmin))
            ->assertForbidden();
    }
}
