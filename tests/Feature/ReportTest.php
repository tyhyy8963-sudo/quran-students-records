<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * لوحة المعلّم والتقارير (S10) — اللوحة الإحصائية، تقرير الفترة، وتصدير CSV.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Surah $surah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ ماجد',
            'username'     => 'majed',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع النور',
            'classroom' => 'حلقة العصر',
        ]);

        $this->student = Student::create(['student_name' => 'سالم', 'teacher_id' => $this->teacher->id]);
        $this->surah = Surah::first() ?? Surah::create(['number' => 1, 'name' => 'الفاتحة', 'ayah_count' => 7]);
    }

    /** @test */
    public function guests_cannot_view_the_reports_dashboard(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    /** @test */
    public function the_dashboard_only_counts_the_current_teachers_students(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other_report', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);
        Student::create(['student_name' => 'طالب آخر', 'teacher_id' => $other->id]);

        $this->actingAs($this->teacher)
            ->get('/reports')
            ->assertOk()
            ->assertSee('إجمالي الطلاب')
            ->assertSee('نشط: 1', false);
    }

    /** @test */
    public function students_with_two_consecutive_absences_appear_in_the_attention_list(): void
    {
        $this->student->attendances()->create(['date' => now()->subDays(2), 'status' => 'غائب']);
        $this->student->attendances()->create(['date' => now()->subDay(), 'status' => 'غائب']);

        $this->actingAs($this->teacher)
            ->get('/reports')
            ->assertOk()
            ->assertSee('سالم');
    }

    /** @test */
    public function students_without_an_absence_streak_do_not_appear_in_the_attention_list(): void
    {
        $this->student->attendances()->create(['date' => now()->subDay(), 'status' => 'حاضر']);

        $this->actingAs($this->teacher)
            ->get('/reports')
            ->assertOk()
            ->assertSee('لا يوجد طلاب بحاجة إلى متابعة حاليًا.');
    }

    /** @test */
    public function the_period_report_defaults_to_the_current_month_and_summarizes_attendance_and_logs(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now()->startOfMonth()->addDay(), 'status' => 'حاضر']);
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now()->startOfMonth()->addDays(2), 'status' => 'غائب']);
        RecitationLog::create([
            'student_id' => $this->student->student_id,
            'surah_id'   => $this->surah->id,
            'to_ayah'    => 5,
            'type'       => 'حفظ',
            'logged_at'  => now()->startOfMonth()->addDay(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/reports/period')
            ->assertOk()
            ->assertSee('سالم')
            ->assertSee('50%'); // حاضر مرة واحدة من سطرين مسجَّلين
    }

    /** @test */
    public function the_period_report_respects_an_explicit_date_range(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => '2026-01-05', 'status' => 'حاضر']);
        // خارج المدى المطلوب أدناه — لا يجب أن تُحتسَب.
        Attendance::create(['student_id' => $this->student->student_id, 'date' => '2026-03-05', 'status' => 'غائب']);

        $this->actingAs($this->teacher)
            ->get('/reports/period?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertSee('100%');
    }

    /** @test */
    public function csv_export_downloads_a_real_csv_file_with_a_bom_and_the_students_data(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now(), 'status' => 'حاضر']);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/period/export?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('سالم', $content);
        $this->assertStringContainsString('الطالب', $content); // رأس الأعمدة
    }
}
