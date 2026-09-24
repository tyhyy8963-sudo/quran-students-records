<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Poem;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تصدير/طباعة سجلّ طالب واحد كاملًا (S37 — بند 2 من خطّة التقارير المعتمَدة)
 * — StudentController::report()/reportExportCsv(): كل السجلّات دفعة واحدة
 * بلا ترقيم صفحات، بخلاف StudentController::show() المختبَرة في
 * StudentShowPageTest (صفحة تصفّح تفاعلية، لا تصدير).
 */
class StudentReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Surah $surah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ فهد', 'username' => 'fahad_report', 'password' => Hash::make('secret123'),
        ]);
        $this->student = Student::create(['student_name' => 'سالم', 'teacher_id' => $this->teacher->id]);
        $this->surah = Surah::where('number', 114)->firstOrFail();
    }

    /** @test */
    public function guests_cannot_view_the_student_report(): void
    {
        $this->get("/dashboard/{$this->student->student_id}/report")->assertRedirect('/login');
    }

    /** @test */
    public function a_teacher_cannot_view_another_teachers_student_report(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other_report_view', 'password' => Hash::make('secret123'),
        ]);

        // نفس سلوك show() (StudentShowPageTest): TeacherScope على Student
        // يجعل استعلام findOrFail() نفسه لا يجد الطالب أصلًا لمعلّم آخر (404)،
        // فلا تُستشار authorize('update') إطلاقًا — لا 403.
        $this->actingAs($other)
            ->get("/dashboard/{$this->student->student_id}/report")
            ->assertNotFound();
    }

    /** @test */
    public function the_report_shows_all_logs_without_pagination(): void
    {
        // صفحة show() العادية مُرقَّمة بـ20 سطرًا لكل صفحة — 25 سطرًا هنا
        // يثبت أن هذه الصفحة (report) لا تُرقِّم إطلاقًا.
        foreach (range(1, 25) as $i) {
            $this->student->recitationLogs()->create([
                'surah_id' => $this->surah->id, 'to_ayah' => $i, 'type' => 'حفظ', 'status' => 'حافظ',
                'logged_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}/report");

        $response->assertOk();
        // report.blade.php لا يحمل أي قائمة سور منسدلة (بخلاف show()) — اسم
        // السورة يظهر مرّة واحدة فقط لكل سطر سجلّ ضمن عمود "المدى".
        $this->assertSame(25, substr_count($response->getContent(), $this->surah->name));
    }

    /** @test */
    public function csv_export_downloads_a_real_csv_file_with_logs_and_attendance(): void
    {
        $this->student->recitationLogs()->create([
            'surah_id' => $this->surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'status' => 'حافظ',
            'logged_at' => now()->toDateString(),
        ]);
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now(), 'status' => 'حاضر']);

        $response = $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}/report/export");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString($this->surah->name, $content);
        $this->assertStringContainsString('سجلّ الحضور', $content);
    }

    /** @test */
    public function the_report_shows_tracked_poems(): void
    {
        $poem = Poem::firstOrFail();
        $this->student->poemRecitationLogs()->create([
            'poem_id' => $poem->id, 'to_bayt' => 5, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}/report")
            ->assertOk()
            ->assertSee($poem->name);
    }
}
