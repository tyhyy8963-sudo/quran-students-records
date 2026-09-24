<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\ReviewProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /** @test */
    public function a_review_spanning_two_surahs_is_treated_as_one_continuous_range(): void
    {
        // مراجعة عابرة لعدّة سور (S16): الأعلى (87) كاملة ثم الغاشية (88)
        // كاملة في سطر واحد. الربع 237 يبدأ عند الأعلى:1 بالضبط (بيانات
        // quarters الحقيقية)، وطوله يتّسع للسورتين معًا (يشاركه أصلًا
        // الغاشية والفجر في التصميم الحقيقي للمصحف) — تحقّق دفاعي بدل افتراض
        // صامت قد ينكسر لو تغيّرت بيانات المرجع يومًا ما.
        $ala = $this->surah(87);
        $ghashiyah = $this->surah(88);

        $quarter237 = Quarter::where('quarter_number', 237)->firstOrFail();
        $quarter238 = Quarter::where('quarter_number', 238)->firstOrFail();
        $this->assertSame(1, $quarter237->start_ayah);
        $this->assertSame($ala->id, $quarter237->start_surah_id);

        $quarter237Length = $quarter238->start_global_ayah - $quarter237->start_global_ayah;
        $totalCovered = $ala->ayah_count + $ghashiyah->ayah_count;

        $this->assertLessThanOrEqual(
            $quarter237Length,
            $totalCovered,
            'الافتراض: الأعلى والغاشية بالكامل تقعان داخل الربع 237 نفسه.'
        );

        $this->student->recitationLogs()->create([
            'surah_id'    => $ala->id,
            'to_surah_id' => $ghashiyah->id,
            'from_ayah'   => 1,
            'to_ayah'     => $ghashiyah->ayah_count,
            'type'        => 'مراجعة',
            'logged_at'   => now()->toDateString(),
        ]);
        $this->progress->forget($this->student);

        // لو استُعمِلت نهاية "الأعلى" نفسها خطأً (تجاهُل to_surah_id) لكانت
        // التغطية محصورة بعدد آيات الأعلى فقط لا مجموع السورتين معًا.
        $this->assertSame($totalCovered, $this->progress->coverage($this->student)[237]);

        $expected = round(($totalCovered / $quarter237Length) / Quarter::COUNT * 100, 1);
        $this->assertSame($expected, $this->progress->percentage($this->student));
    }

    /** @test */
    public function quarters_fully_reviewed_counts_only_complete_quarters(): void
    {
        $this->log(2, 26, 43); // الربع 2 كاملًا (18 آية)
        $this->log(2, 44, 50); // جزء فقط من الربع 3 (7 من 16 آية)

        $this->assertSame(1, $this->progress->quartersFullyReviewed($this->student));
    }

    /** @test */
    public function monthly_activity_counts_any_quarter_touched_within_that_calendar_month(): void
    {
        Carbon::setTestNow('2026-06-15');

        // الربع 2 (26..43) — تسجيل مراجعة جزئية فقط (9 من 18 آية) لا تزال
        // "تمسّ" الربع فتُحتسَب، خلافًا لـquartersFullyReviewed() التي تشترط
        // الاكتمال الكامل.
        $this->log(2, 26, 34);

        $activity = $this->progress->monthlyActivity($this->student, 3);

        $this->assertSame(3, $activity->count());
        $this->assertSame('2026-06', $activity->last()['month']);
        $this->assertSame(1, $activity->last()['quarters']);

        Carbon::setTestNow();
    }

    /** @test */
    public function monthly_activity_does_not_count_a_review_toward_an_adjacent_month(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->student->recitationLogs()->create([
            'surah_id' => $this->surah(2)->id, 'from_ayah' => 26, 'to_ayah' => 43,
            'type' => 'مراجعة', 'logged_at' => '2026-05-10',
        ]);
        $this->progress->forget($this->student);

        $activity = $this->progress->monthlyActivity($this->student, 3);
        $byMonth = $activity->keyBy('month');

        $this->assertSame(0, $byMonth['2026-06']['quarters']);
        $this->assertSame(1, $byMonth['2026-05']['quarters']);
        $this->assertSame(0, $byMonth['2026-04']['quarters']);

        Carbon::setTestNow();
    }

    /** @test */
    public function monthly_activity_counts_distinct_quarters_not_log_lines(): void
    {
        Carbon::setTestNow('2026-06-15');

        // سطران مختلفان لكن كلاهما يمسّان الربع نفسه (2) فقط — يُحتسَب مرّة
        // واحدة لا مرّتين.
        $this->student->recitationLogs()->create([
            'surah_id' => $this->surah(2)->id, 'from_ayah' => 26, 'to_ayah' => 30,
            'type' => 'مراجعة', 'logged_at' => '2026-06-05',
        ]);
        $this->student->recitationLogs()->create([
            'surah_id' => $this->surah(2)->id, 'from_ayah' => 31, 'to_ayah' => 34,
            'type' => 'مراجعة', 'logged_at' => '2026-06-12',
        ]);
        $this->progress->forget($this->student);

        $activity = $this->progress->monthlyActivity($this->student, 1);

        $this->assertSame(1, $activity->count());
        $this->assertSame(1, $activity->first()['quarters']);

        Carbon::setTestNow();
    }

    /** @test */
    public function monthly_activity_defaults_to_six_months_ordered_oldest_first(): void
    {
        Carbon::setTestNow('2026-06-15');

        $activity = $this->progress->monthlyActivity($this->student);

        $expectedLabel = Carbon::create(2026, 6, 15)->translatedFormat('F Y');

        $this->assertSame(6, $activity->count());
        $this->assertSame('2026-01', $activity->first()['month']);
        $this->assertSame('2026-06', $activity->last()['month']);
        $this->assertSame($expectedLabel, $activity->last()['label']);

        Carbon::setTestNow();
    }
}
