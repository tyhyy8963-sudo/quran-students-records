<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تقرير الحضور والغياب المستقلّ (S37 — بند 3 من خطّة التقارير المعتمَدة) —
 * امتداد لمؤشّر "طلاب بحاجة إلى متابعة" الصغير في لوحة التقارير الرئيسية
 * (راجع ReportTest) إلى تقرير كامل بنطاق تاريخ وفلترة حلقة/حالة، بنفس نمط
 * تقرير الفترة (period/exportCsv) — راجع ReportController::attendanceRows().
 */
class ReportsAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ نايف', 'username' => 'nayef_attendance', 'password' => Hash::make('secret123'),
        ]);
        $this->student = Student::create(['student_name' => 'سالم', 'teacher_id' => $this->teacher->id]);
    }

    /** @test */
    public function guests_cannot_view_the_attendance_report(): void
    {
        $this->get('/reports/attendance')->assertRedirect('/login');
    }

    /** @test */
    public function it_defaults_to_the_current_month_and_summarizes_attendance_counts(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now()->startOfMonth()->addDay(), 'status' => 'حاضر']);
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now()->startOfMonth()->addDays(2), 'status' => 'غائب بدون عذر']);

        $this->actingAs($this->teacher)
            ->get('/reports/attendance')
            ->assertOk()
            ->assertSee('سالم')
            ->assertSee('50%');
    }

    /** @test */
    public function it_respects_an_explicit_date_range(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => '2026-01-05', 'status' => 'حاضر']);
        // خارج المدى المطلوب أدناه — لا يجب أن تُحتسَب.
        Attendance::create(['student_id' => $this->student->student_id, 'date' => '2026-03-05', 'status' => 'غائب بدون عذر']);

        $this->actingAs($this->teacher)
            ->get('/reports/attendance?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertSee('100%');
    }

    /** @test */
    public function it_flags_students_needing_attention_with_two_consecutive_absences(): void
    {
        $this->student->attendances()->create(['date' => now()->subDays(2), 'status' => 'غائب بدون عذر']);
        $this->student->attendances()->create(['date' => now()->subDay(), 'status' => 'غائب بدون عذر']);

        $other = Student::create(['student_name' => 'راشد', 'teacher_id' => $this->teacher->id]);
        $other->attendances()->create(['date' => now(), 'status' => 'حاضر']);

        $response = $this->actingAs($this->teacher)->get('/reports/attendance');
        $content = $response->getContent();

        $response->assertOk();
        // سالم (متتابع الغياب) قبل راشد في ترتيب الأسماء الأبجدي أصلًا هنا،
        // فيكفي التحقّق من ظهور شارة "بحاجة إلى متابعة" مرّة واحدة بالضبط
        // (لسالم لا لراشد).
        $this->assertSame(1, substr_count($content, 'title="آخر سطرَي حضور مسجَّلين له كلاهما «غائب»"'));
    }

    /** @test */
    public function circle_filter_narrows_the_rows(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'طالب داخل الحلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);

        $this->actingAs($this->teacher)
            ->get("/reports/attendance?circle_id[]={$circleA->id}")
            ->assertOk()
            ->assertSee('طالب داخل الحلقة')
            ->assertDontSee('سالم');
    }

    /** @test */
    public function csv_export_downloads_a_real_csv_file_with_a_bom_and_the_data(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now(), 'status' => 'حاضر']);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/attendance/export?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('سالم', $content);
        $this->assertStringContainsString('بحاجة إلى متابعة', $content);
    }
}
