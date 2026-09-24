<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Poem;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use App\Support\ReviewProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * لوحة المعلّم والتقارير (S10) — اللوحة الإحصائية، تقرير الفترة، وتصدير
 * CSV/.xlsx. وُسِّعت في S19 بفلترة الحلقة/الحالة على اللوحتين معًا + عمودَي
 * نسبة التقدّم وعدد المتون المتتبَّعة في تقرير الفترة (راجع
 * StudentAdvancedFilterTest لنفس منطق الاختيار المتعدّد المطبَّق أصلًا في لوحة
 * الطلاب S18)، ثم بتصدير .xlsx حقيقي موسّع في S17 (راجع اختبارات xlsx أسفل
 * الملف — تقرأ الملف المُصدَّر فعليًا بـPhpSpreadsheet بدل الاكتفاء بفحص رأس
 * الاستجابة، فتتحقّق من محتوى الخلايا الحقيقي لا مجرّد نجاح التنزيل).
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

    /**
     * @test
     *
     * (S25 — بطلب صريح من يحيى): "غائب" انقسم لعمودين مستقلّين (معذور/غير
     * معذور) بدل دمجهما، وأُضيف عمودا حالة الحفظ — هذا الاختبار يتحقّق من
     * القيم الفعلية المحسوبة لا وجود عناوين الأعمدة فقط.
     */
    public function period_report_splits_absence_by_excuse_and_counts_memorization_status(): void
    {
        $today = now()->startOfMonth()->addDay();
        Attendance::create(['student_id' => $this->student->student_id, 'date' => $today, 'status' => 'غائب بعذر']);
        Attendance::create(['student_id' => $this->student->student_id, 'date' => $today->copy()->addDay(), 'status' => 'غائب بدون عذر']);
        Attendance::create(['student_id' => $this->student->student_id, 'date' => $today->copy()->addDays(2), 'status' => 'غائب بدون عذر']);

        RecitationLog::create([
            'student_id' => $this->student->student_id, 'surah_id' => $this->surah->id,
            'to_ayah' => 5, 'type' => 'حفظ', 'status' => 'حافظ', 'logged_at' => $today,
        ]);
        RecitationLog::create([
            'student_id' => $this->student->student_id, 'surah_id' => $this->surah->id,
            'to_ayah' => 6, 'type' => 'حفظ', 'status' => 'غير حافظ', 'logged_at' => $today,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/period/export?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $rows = array_map('str_getcsv', explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));
        $header = $rows[0];
        $studentRow = $rows[1];

        $this->assertSame(1, (int) $studentRow[array_search('غائب بعذر', $header, true)]);
        $this->assertSame(2, (int) $studentRow[array_search('غائب بدون عذر', $header, true)]);
        $this->assertSame(1, (int) $studentRow[array_search('حافظ', $header, true)]);
        $this->assertSame(1, (int) $studentRow[array_search('غير حافظ', $header, true)]);
        $this->assertFalse(in_array('متأخر', $header, true));
        $this->assertFalse(in_array('تسميع', $header, true));
    }

    /** @test */
    public function the_dashboard_stats_can_be_filtered_by_circle(): void
    {
        // اللوحة كانت إحصاءات ثابتة بلا أي فلاتر إطلاقًا قبل S19.
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        $circleB = Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);

        Student::create(['student_name' => 'طالب في أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'طالب في ب', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleB->id]);
        // $this->student (سالم) بلا حلقة أصلًا — يُستبعد أيضًا حين تُحدَّد حلقة أ فقط.

        $this->actingAs($this->teacher)
            ->get("/reports?circle_id[]={$circleA->id}")
            ->assertOk()
            ->assertSee('نشط: 1', false);
    }

    /** @test */
    public function the_period_report_can_be_filtered_by_circle_and_status(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'طالب داخل الحلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);

        $this->actingAs($this->teacher)
            ->get("/reports/period?circle_id[]={$circleA->id}")
            ->assertOk()
            ->assertSee('طالب داخل الحلقة')
            ->assertDontSee('سالم'); // $this->student بلا حلقة، فيُستبعد حين تُحدَّد حلقة أ فقط.
    }

    /** @test */
    public function the_period_report_shows_the_progress_percentage_column(): void
    {
        $naas = Surah::where('number', 114)->firstOrFail();
        $this->student->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => $naas->ayah_count, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        // مشتقّة من نفس منطق الحساب الحيّ لا رقمًا مكتوبًا يدويًا — تفاديًا
        // لانحراف الاختبار عن الحقيقة لو تغيّر وزن الأرباع مستقبلًا.
        $expected = app(MemorizationProgress::class)->percentage($this->student->fresh());

        $this->actingAs($this->teacher)
            ->get('/reports/period')
            ->assertOk()
            ->assertSee('نسبة الحفظ %', false)
            ->assertSee($expected.'%');
    }

    /** @test */
    public function csv_export_includes_the_progress_percentage_and_tracked_poem_count_columns(): void
    {
        $naas = Surah::where('number', 114)->firstOrFail();
        $this->student->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => $naas->ayah_count, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $poem = Poem::firstOrFail();
        $this->student->poemRecitationLogs()->create([
            'poem_id' => $poem->id, 'to_bayt' => 5, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $expectedPercent = app(MemorizationProgress::class)->percentage($this->student->fresh());

        $response = $this->actingAs($this->teacher)
            ->get('/reports/period/export?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $content = $response->streamedContent();
        $this->assertStringContainsString('نسبة الحفظ %', $content);
        $this->assertStringContainsString('عدد المتون المتتبَّعة', $content);
        // آخر عمودين في صفّ الطالب: نسبة الحفظ ثم عدد المتون (1) — تحقّق من
        // القيمتين معًا متجاورتين لا وجود الرقمين في أي مكان في الملف.
        $this->assertStringContainsString($expectedPercent.','.'1', $content);
    }

    /**
     * يحفظ محتوى الاستجابة المُتدفِّق في ملف مؤقّت ويقرأه بـPhpSpreadsheet
     * فعليًا (لا افتراض أن نجاح التنزيل يعني ملفًا سليمًا) — يعيد مصفوفة صفوف
     * مفهرَسة رقميًا، نفس شكل periodRows() قبل التحويل لأعمدة.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readXlsxRows($response): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'keshf_xlsx_export_');
        file_put_contents($tempPath, $response->streamedContent());

        $rows = IOFactory::load($tempPath)->getActiveSheet()->toArray(null, true, true, false);

        @unlink($tempPath);

        return $rows;
    }

    /** @test */
    public function xlsx_export_downloads_a_real_xlsx_file_with_the_students_data(): void
    {
        Attendance::create(['student_id' => $this->student->student_id, 'date' => now(), 'status' => 'حاضر']);

        $response = $this->actingAs($this->teacher)
            ->get('/reports/period/export-xlsx?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->readXlsxRows($response);

        // (S25 — بطلب صريح من يحيى): "متأخر"/"تسميع" حُذفا، "غائب" انقسم
        // لعمودين، وأُضيف عمودا حالة الحفظ — راجع تعليق periodRows() في
        // ReportController للتفصيل الكامل.
        $this->assertSame([
            'الطالب', 'الحلقة', 'حاضر', 'غائب بعذر', 'غائب بدون عذر', 'مستأذن',
            'نسبة الحضور %', 'حفظ جديد', 'مراجعة', 'حافظ', 'غير حافظ',
            'نسبة الحفظ %', 'آخر موضع مراجعة', 'نسبة المراجعة %', 'عدد المتون المتتبَّعة',
        ], $rows[0]);

        $studentRow = $rows[1];
        $this->assertSame('سالم', $studentRow[0]);
        $this->assertEquals(1, $studentRow[2]); // حاضر: مرّة واحدة
        $this->assertEquals(100, $studentRow[6]); // نسبة الحضور %: 1 من 1
    }

    /**
     * @test
     *
     * العمودان اللذان يميّزان .xlsx عن CSV (S17) — periodRows() تحسبهما
     * دائمًا الآن، لكن CSV يتجاهلهما عمدًا (راجع exportCsv()). هذا الاختبار
     * يثبت أنهما يصلان فعليًا إلى الملف الحقيقي بقيمتين صحيحتين لا مجرّد
     * عنوانَي عمود بلا بيانات.
     */
    public function xlsx_export_includes_the_latest_review_position_and_review_percentage_columns(): void
    {
        $baqarah = Surah::where('number', 2)->firstOrFail();
        $this->student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'from_ayah' => 1, 'to_ayah' => 20, 'type' => 'مراجعة', 'logged_at' => now(),
        ]);

        $expectedReviewPercent = app(ReviewProgress::class)->percentage($this->student->fresh());

        $response = $this->actingAs($this->teacher)
            ->get('/reports/period/export-xlsx?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $rows = $this->readXlsxRows($response);
        $header = $rows[0];
        $reviewPositionCol = array_search('آخر موضع مراجعة', $header, true);
        $reviewPercentCol = array_search('نسبة المراجعة %', $header, true);

        $studentRow = $rows[1];
        $this->assertSame($baqarah->name.' · آية 20', $studentRow[$reviewPositionCol]);
        $this->assertEquals($expectedReviewPercent, $studentRow[$reviewPercentCol]);
    }
}
