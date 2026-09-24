<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Poem;
use App\Models\PoemRecitationLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تقرير المتون المستقلّ (S37 — بند 1 من خطّة التقارير المعتمَدة): نفس فلاتر
 * لوحة "المتون" التفاعلية (راجع PoemBoardTest) — متن/حلقة متعدّدَا الاختيار
 * + نطاق نسبة — لكن الناتج هنا صفّ مستقلّ لكل (طالب، متن) يصلح للطباعة
 * والتصدير المسطّح، لا شارات متعدّدة داخل صفّ طالب واحد كما في اللوحة
 * التفاعلية. راجع تعليق ReportController::poemsRows() للتفصيل الكامل.
 */
class ReportsPoemsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Poem $poem;      // تحفة الأطفال — 61 بيتًا
    private Poem $otherPoem; // الجزرية — 107 أبيات

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ سعيد', 'username' => 'saeed_report', 'password' => Hash::make('secret123'),
        ]);

        $this->poem = Poem::where('name', 'تحفة الأطفال')->firstOrFail();
        $this->otherPoem = Poem::where('name', 'الجزرية')->firstOrFail();
    }

    private function trackPoem(Student $student, ?Poem $poem = null, int $toBayt = 10): PoemRecitationLog
    {
        return PoemRecitationLog::create([
            'student_id' => $student->student_id,
            'poem_id'    => ($poem ?? $this->poem)->id,
            'to_bayt'    => $toBayt,
            'type'       => 'حفظ',
            'logged_at'  => now()->toDateString(),
        ]);
    }

    /** @test */
    public function guests_cannot_view_the_poems_report(): void
    {
        $this->get('/reports/poems')->assertRedirect('/login');
    }

    /** @test */
    public function it_produces_one_row_per_student_poem_pair_not_one_row_per_student(): void
    {
        $student = Student::create(['student_name' => 'متعدّد المتون', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->poem, 10);
        $this->trackPoem($student, $this->otherPoem, 10);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/poems?'.http_build_query(['poem_id' => [$this->poem->id, $this->otherPoem->id]]));

        $response->assertOk();
        // اسم الطالب يظهر مرّتين: مرّة لكل متن (صفّان منفصلان)، لا شارتين
        // داخل صفّ واحد كما في لوحة المتون التفاعلية.
        $this->assertSame(2, substr_count($response->getContent(), 'متعدّد المتون'));
        $response->assertSee($this->poem->name);
        $response->assertSee($this->otherPoem->name);
    }

    /** @test */
    public function a_student_not_tracking_the_filtered_poem_is_excluded(): void
    {
        $student = Student::create(['student_name' => 'يتابع الجزرية فقط', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->otherPoem);

        $response = $this->actingAs($this->teacher)->get('/reports/poems?poem_id='.$this->poem->id);

        $response->assertDontSee('يتابع الجزرية فقط');
    }

    /** @test */
    public function circle_filter_narrows_the_rows(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        $circleB = Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);

        $inA = Student::create(['student_name' => 'من حلقة أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        $inB = Student::create(['student_name' => 'من حلقة ب', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleB->id]);
        $this->trackPoem($inA);
        $this->trackPoem($inB);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/poems?poem_id='.$this->poem->id.'&circle_id='.$circleA->id);

        $response->assertSee('من حلقة أ');
        $response->assertDontSee('من حلقة ب');
    }

    /** @test */
    public function progress_range_filter_applies_to_the_row_itself(): void
    {
        $high = Student::create(['student_name' => 'تقدّمه عالٍ', 'teacher_id' => $this->teacher->id]);
        $low = Student::create(['student_name' => 'تقدّمه منخفض', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($high, $this->poem, 55); // 55/61 → 90.2%
        $this->trackPoem($low, $this->poem, 5);   // 5/61 → 8.2%

        $response = $this->actingAs($this->teacher)
            ->get('/reports/poems?poem_id='.$this->poem->id.'&progress_min=50');

        $response->assertSee('تقدّمه عالٍ');
        $response->assertDontSee('تقدّمه منخفض');
    }

    /** @test */
    public function no_poem_filter_includes_rows_for_every_tracked_poem(): void
    {
        // بلا poem_id إطلاقًا = "كل المتون" (نفس منطق لوحة المتون التفاعلية) —
        // لا تصفية ضمنية على متن واحد. اسم مركّب مقصود (لا "طالب" المجرّدة)
        // حتى لا يتطابق جزئيًا مع عمود "الطالب" في رأس الجدول (تحتوي
        // "الطالب" فعليًا على "طالب" كسلسلة فرعية).
        $student = Student::create(['student_name' => 'متعدّد المتون هنا', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->poem);
        $this->trackPoem($student, $this->otherPoem);

        $response = $this->actingAs($this->teacher)->get('/reports/poems');

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'متعدّد المتون هنا'));
        $response->assertSee($this->poem->name);
        $response->assertSee($this->otherPoem->name);
    }

    /** @test */
    public function csv_export_downloads_a_real_csv_file_with_the_row_data(): void
    {
        $student = Student::create(['student_name' => 'سالم', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->poem, 10);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/poems/export?poem_id='.$this->poem->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('سالم', $content);
        $this->assertStringContainsString($this->poem->name, $content);
    }
}
