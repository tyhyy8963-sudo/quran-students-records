<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\ReviewProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * نسبة المراجعة بوزن الأرباع (S16).
 *
 * الحدود المستعملة هنا (سورة البقرة، آية 26 بداية الربع 2، آية 44 بداية
 * الربع 3، آية 60 بداية الربع 4) هي بيانات quarters الحقيقية المزروعة —
 * منها: طول الربع 2 = 18 آية (26..43)، وطول الربع 3 = 16 آية (44..59).
 */
class ReviewProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private ReviewProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->progress = app(ReviewProgress::class);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    private function log(int $surahNumber, int $from, int $to, string $type = 'مراجعة'): void
    {
        $this->student->recitationLogs()->create([
            'surah_id' => $this->surah($surahNumber)->id,
            'from_ayah' => $from, 'to_ayah' => $to,
            'type' => $type, 'logged_at' => now()->toDateString(),
        ]);

        $this->progress->forget($this->student);
    }

    /** @test */
    public function memorization_and_recitation_logs_do_not_count_as_review(): void
    {
        $this->log(2, 26, 43, 'حفظ');
        $this->log(2, 26, 43, 'تسميع');

        $this->assertSame(0, $this->progress->coverage($this->student)[2]);
        $this->assertSame(0.0, $this->progress->percentage($this->student));
    }

    /** @test */
    public function reviewing_half_a_quarter_gives_half_its_weight(): void
    {
        // الربع 2 طوله 18 آية (26..43) — نصفها 9 آيات فقط.
        $this->log(2, 26, 34);

        $this->assertSame(9, $this->progress->coverage($this->student)[2]);
    }

    /** @test */
    public function a_review_spanning_two_quarters_distributes_by_actual_overlap(): void
    {
        // 30..50 يمتدّ عبر الربعين 2 (26..43) و3 (44..59): تقاطعه مع الأول
        // 30..43 (14 آية)، ومع الثاني 44..50 (7 آيات) — لا يُحتسَب دفعة واحدة
        // لربع واحد فقط.
        $this->log(2, 30, 50);

        $coverage = $this->progress->coverage($this->student);

        $this->assertSame(14, $coverage[2]);
        $this->assertSame(7, $coverage[3]);
    }

    /** @test */
    public function a_fully_reviewed_quarter_reaches_its_full_weight(): void
    {
        $this->log(2, 26, 43); // الربع 2 كاملًا

        $expected = round(1 / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->progress->percentage($this->student));
    }

    /** @test */
    public function overlapping_review_sessions_are_counted_once(): void
    {
        $this->log(2, 26, 34);
        $this->log(2, 30, 43);

        $this->assertSame(18, $this->progress->coverage($this->student)[2]);
    }

    /** @test */
    public function a_student_with_no_review_logs_has_zero_percent(): void
    {
        $this->assertSame(0.0, $this->progress->percentage($this->student));
    }
}
