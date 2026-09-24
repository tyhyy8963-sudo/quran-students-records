<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\Student;
use App\Models\User;
use App\Support\PoemProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * حساب نسبة حفظ متن (S15) — نفس منطق MemorizationProgress لكن على وحدة
 * "بيت" واحدة (لا مجموع خطوات على عدّة سور).
 *
 * (S24 — بطلب صريح من يحيى): اختبار "أرضية المتن" اليدوية (كان هنا) حُذف —
 * الأرضية أُلغيت نهائيًا، راجع تعليق PoemProgress::class للتفصيل الكامل.
 */
class PoemProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Poem $poem;
    private PoemProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->poem = Poem::where('name', 'تحفة الأطفال')->firstOrFail();
        $this->progress = app(PoemProgress::class);
    }

    private function log(int $to, ?int $from = null, string $type = 'حفظ', ?string $loggedAt = null): void
    {
        $this->student->poemRecitationLogs()->create([
            'poem_id'   => $this->poem->id,
            'from_bayt' => $from,
            'to_bayt'   => $to,
            'type'      => $type,
            'logged_at' => $loggedAt ?? now()->toDateString(),
        ]);

        $this->progress->forget($this->student, $this->poem);
    }

    /** @test */
    public function the_five_fixed_poems_exist_with_the_documented_bayt_counts(): void
    {
        $this->assertSame(61, Poem::where('name', 'تحفة الأطفال')->firstOrFail()->bayt_count);
        $this->assertSame(107, Poem::where('name', 'الجزرية')->firstOrFail()->bayt_count);
        $this->assertSame(1173, Poem::where('name', 'الشاطبية')->firstOrFail()->bayt_count);
        $this->assertSame(241, Poem::where('name', 'الدرة المضية')->firstOrFail()->bayt_count);
        $this->assertSame(1014, Poem::where('name', 'طيبة النشر')->firstOrFail()->bayt_count);
    }

    /** @test */
    public function percentage_is_bayt_covered_over_bayt_count(): void
    {
        $this->log(31); // نصف تحفة الأطفال تقريبًا (61 بيتًا)

        $this->assertSame(round(31 / 61 * 100, 1), $this->progress->percentage($this->student, $this->poem));
    }

    /** @test */
    public function overlapping_sessions_are_counted_once(): void
    {
        $this->log(20, 1);
        $this->log(40, 15);

        $this->assertSame(40, $this->progress->coverage($this->student, $this->poem));
    }

    /** @test */
    public function review_and_recitation_logs_do_not_raise_coverage(): void
    {
        $this->log(30, 1, 'حفظ');
        $before = $this->progress->percentage($this->student, $this->poem);

        $this->log(61, 1, 'مراجعة');
        $this->log(61, 1, 'تسميع');

        $this->assertSame($before, $this->progress->percentage($this->student, $this->poem));
    }

    /** @test */
    public function coverage_never_exceeds_the_poems_bayt_count(): void
    {
        $this->log(61, 1);
        $this->log(61, 1);

        $this->assertSame(61, $this->progress->coverage($this->student, $this->poem));
        $this->assertSame(100.0, $this->progress->percentage($this->student, $this->poem));
    }

    /** @test */
    public function timeline_reports_the_cumulative_percentage_as_of_each_logged_date(): void
    {
        $this->log(20, null, 'حفظ', '2026-01-01');
        $this->log(40, 21, 'حفظ', '2026-01-08');

        $timeline = $this->progress->timeline($this->student, $this->poem);

        $this->assertSame(2, $timeline->count());
        $this->assertSame(['date' => '2026-01-01', 'percent' => round(20 / 61 * 100, 1)], $timeline[0]);
        $this->assertSame(['date' => '2026-01-08', 'percent' => round(40 / 61 * 100, 1)], $timeline[1]);
    }

    /** @test */
    public function multiple_logs_on_the_same_day_keep_only_that_days_final_percentage(): void
    {
        $this->log(20, 1, 'حفظ', '2026-02-01');
        $this->log(40, 21, 'حفظ', '2026-02-01');

        $timeline = $this->progress->timeline($this->student, $this->poem);

        $this->assertSame(1, $timeline->count());
        $this->assertSame(['date' => '2026-02-01', 'percent' => round(40 / 61 * 100, 1)], $timeline[0]);
    }
}
