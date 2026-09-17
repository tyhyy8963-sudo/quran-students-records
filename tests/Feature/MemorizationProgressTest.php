<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * نسبة الحفظ بترتيب الحفظ المعكوس (S14).
 *
 * السلّم المطلوب حرفيًا: الناس ≈ 1% والبقرة = 100%، والفاتحة خارج العدّ.
 */
class MemorizationProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private MemorizationProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->progress = app(MemorizationProgress::class);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    private function log(int $surahNumber, int $to, ?int $from = null, string $type = 'حفظ', ?string $date = null): void
    {
        $this->student->recitationLogs()->create([
            'surah_id'  => $this->surah($surahNumber)->id,
            'from_ayah' => $from,
            'to_ayah'   => $to,
            'type'      => $type,
            'logged_at' => $date ?? now()->toDateString(),
        ]);

        $this->progress->forget($this->student);
    }

    /* ===================== السلّم نفسه ===================== */

    /** @test */
    public function the_ladder_runs_from_an_nas_upwards_to_al_baqarah(): void
    {
        $this->assertSame(1, $this->surah(114)->memorization_order, 'الناس يجب أن تكون أول خطوة في ترتيب الحفظ.');
        $this->assertSame(113, $this->surah(2)->memorization_order, 'البقرة يجب أن تكون الخطوة الأخيرة.');

        $this->assertEqualsWithDelta(0.9, $this->surah(114)->progressPercent(), 0.05);
        $this->assertSame(100.0, $this->surah(2)->progressPercent());
    }

    /** @test */
    public function al_fatiha_is_outside_the_ladder_entirely(): void
    {
        $fatiha = $this->surah(1);

        $this->assertTrue($fatiha->excluded_from_progress);
        $this->assertNull($fatiha->memorization_order);
        $this->assertSame(0.0, $fatiha->progressPercent());
    }

    /** @test */
    public function the_countable_constant_matches_the_reference_table(): void
    {
        // 113 ليس رقمًا مكتوبًا بالصدفة: هو عدد السور القابلة للعدّ فعلًا.
        $this->assertSame(Surah::COUNTABLE_COUNT, Surah::countable()->count());
        $this->assertSame(114, Surah::count());
    }

    /* ===================== نسبة الطالب ===================== */

    /** @test */
    public function a_student_who_finished_only_an_nas_is_at_about_one_percent(): void
    {
        $this->log(114, 6); // الناس كاملة

        $this->assertEqualsWithDelta(0.9, $this->student->progressPercentage(), 0.05);
    }

    /** @test */
    public function reciting_al_fatiha_adds_nothing_to_the_percentage(): void
    {
        $this->log(1, 7);          // الفاتحة كاملة حفظًا
        $this->log(1, 7, 1, 'مراجعة');
        $this->log(1, 7, 1, 'تسميع');

        $this->assertSame(0.0, $this->student->progressPercentage());
        $this->assertCount(0, $this->progress->completedSurahs($this->student));
    }

    /** @test */
    public function finishing_every_countable_surah_gives_exactly_one_hundred(): void
    {
        foreach (Surah::countable()->get() as $surah) {
            $this->student->recitationLogs()->create([
                'surah_id' => $surah->id, 'from_ayah' => 1, 'to_ayah' => $surah->ayah_count,
                'type' => 'حفظ', 'logged_at' => now()->toDateString(),
            ]);
        }
        $this->progress->forget($this->student);

        $this->assertSame(100.0, $this->student->progressPercentage());
        $this->assertCount(Surah::COUNTABLE_COUNT, $this->progress->completedSurahs($this->student));
    }

    /** @test */
    public function a_long_surah_in_progress_moves_the_percentage_proportionally(): void
    {
        // نصف البقرة تقريبًا: خطوة نصفية من 113 — بلا هذا تتجمّد النسبة شهورًا
        // كاملة أثناء حفظ أطول سورة في المصحف.
        $this->log(2, 143, 1);

        $expected = round((143 / 286) / Surah::COUNTABLE_COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertCount(0, $this->progress->completedSurahs($this->student), 'سورة لم تكتمل يجب ألا تُعدّ مكتملة.');
    }

    /** @test */
    public function overlapping_sessions_are_counted_once(): void
    {
        // 1–20 ثم 15–40 = 40 آية لا 46: جمع أطوال الأسطر يضاعف المكرَّر.
        $this->log(2, 20, 1);
        $this->log(2, 40, 15);

        $coverage = $this->progress->coverage($this->student);

        $this->assertSame(40, $coverage[$this->surah(2)->id]);
    }

    /** @test */
    public function adjacent_sessions_merge_without_a_gap(): void
    {
        $this->log(2, 5, 1);
        $this->log(2, 9, 6);

        $this->assertSame(9, $this->progress->coverage($this->student)[$this->surah(2)->id]);
    }

    /** @test */
    public function a_log_without_a_starting_ayah_counts_from_the_first_ayah(): void
    {
        // شكل كل السجلّات المهاجَرة من student_data القديم (S7): "بلغ الآية 50".
        $this->log(2, 50);

        $this->assertSame(50, $this->progress->coverage($this->student)[$this->surah(2)->id]);
    }

    /** @test */
    public function review_and_recitation_logs_do_not_raise_the_percentage(): void
    {
        $this->log(114, 6);
        $before = $this->student->progressPercentage();

        $this->log(113, 5, 1, 'مراجعة');
        $this->log(112, 4, 1, 'تسميع');

        $this->assertSame($before, $this->student->progressPercentage());
    }

    /** @test */
    public function the_percentage_never_exceeds_one_hundred(): void
    {
        foreach (Surah::countable()->get() as $surah) {
            // كل سورة مسجَّلة مرّتين — التكرار لا يرفع النسبة فوق سقفها.
            foreach ([1, 2] as $ignored) {
                $this->student->recitationLogs()->create([
                    'surah_id' => $surah->id, 'from_ayah' => 1, 'to_ayah' => $surah->ayah_count,
                    'type' => 'حفظ', 'logged_at' => now()->toDateString(),
                ]);
            }
        }
        $this->progress->forget($this->student);

        $this->assertSame(100.0, $this->student->progressPercentage());
    }

    /* ===================== قراءات تربوية ===================== */

    /** @test */
    public function the_furthest_surah_follows_memorization_order_not_mushaf_order(): void
    {
        $this->log(114, 6);  // الناس — الخطوة 1
        $this->log(110, 3);  // النصر — الخطوة 5

        $this->assertSame('النصر', $this->progress->furthestSurah($this->student)->name);
    }

    /** @test */
    public function completed_surahs_are_listed_in_memorization_order(): void
    {
        $this->log(112, 4, 1); // الإخلاص — الخطوة 3
        $this->log(114, 6, 1); // الناس — الخطوة 1

        $names = $this->progress->completedSurahs($this->student)->pluck('name')->all();

        $this->assertSame(['الناس', 'الإخلاص'], $names);
    }

    /* ===================== المنحنى ===================== */

    /** @test */
    public function the_timeline_is_cumulative_and_expressed_in_percent(): void
    {
        $this->log(114, 6, 1, 'حفظ', '2026-01-01');
        $this->log(113, 5, 1, 'حفظ', '2026-01-08');
        $this->log(112, 4, 1, 'حفظ', '2026-01-15');

        $points = $this->progress->timeline($this->student);

        $this->assertCount(3, $points);
        $this->assertSame('2026-01-01', $points[0]['date']);

        // كل نقطة هي نسبة الطالب في ذلك اليوم فعلًا: خطوة، خطوتان، ثلاث.
        $this->assertEqualsWithDelta(1 / 113 * 100, $points[0]['percent'], 0.05);
        $this->assertEqualsWithDelta(2 / 113 * 100, $points[1]['percent'], 0.05);
        $this->assertEqualsWithDelta(3 / 113 * 100, $points[2]['percent'], 0.05);
    }

    /** @test */
    public function several_logs_on_the_same_day_collapse_into_one_point(): void
    {
        $this->log(114, 6, 1, 'حفظ', '2026-02-01');
        $this->log(113, 5, 1, 'حفظ', '2026-02-01');

        $points = $this->progress->timeline($this->student);

        $this->assertCount(1, $points);
        $this->assertEqualsWithDelta(2 / 113 * 100, $points[0]['percent'], 0.05);
    }

    /* ===================== الشاشات ===================== */

    /** @test */
    public function the_student_page_shows_the_new_ladder_and_its_explanation(): void
    {
        $this->log(114, 6, 1);

        $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}")
            ->assertOk()
            ->assertSee('سورة مكتملة', false)
            ->assertSee('الناس', false)
            ->assertSee('الفاتحة مستثناة من العدّ', false);
    }

    /** @test */
    public function the_surah_picker_starts_at_an_nas_not_al_fatiha(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}")
            ->assertOk();

        $html = $response->getContent();

        $nasPosition = strpos($html, 'الناس');
        $baqarahPosition = strpos($html, 'البقرة');

        $this->assertNotFalse($nasPosition);
        $this->assertNotFalse($baqarahPosition);
        $this->assertLessThan(
            $baqarahPosition,
            $nasPosition,
            'قائمة السور يجب أن تبدأ بترتيب الحفظ (الناس) لا بترتيب المصحف.'
        );
    }

    /** @test */
    public function the_dashboard_reports_the_same_percentage_as_the_student_page(): void
    {
        $this->log(2, 143, 1);

        $expected = $this->student->progressPercentage();

        $this->actingAs($this->teacher)
            ->getJson("/dashboard/{$this->student->student_id}/logs", [])
            ->assertStatus(405); // لا مسار GET — الفحص التالي هو المقصود

        $response = $this->actingAs($this->teacher)->postJson(
            "/dashboard/{$this->student->student_id}/logs",
            ['surah_id' => $this->surah(2)->id, 'to_ayah' => 200, 'from_ayah' => 144, 'type' => 'حفظ']
        )->assertCreated();

        $this->assertGreaterThan($expected, $response->json('data.progress_percent'));
        $this->assertSame(
            $this->student->fresh()->progressPercentage(),
            $response->json('data.progress_percent'),
            'الرقم العائد من الحفظ يجب أن يطابق ما تحسبه الخدمة نفسها.'
        );
    }
}
