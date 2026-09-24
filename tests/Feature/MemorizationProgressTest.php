<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * نسبة الحفظ بترتيب الحفظ المعكوس، موزونة بالأرباع الـ240 (تصحيح ثانٍ بعد
 * S15 — راجع تعليق صنف MemorizationProgress للتفصيل الكامل).
 *
 * القيم المتوقَّعة هنا تُشتقّ من حدود quarters الحقيقية المزروعة (migration
 * 2026_09_07_000003_create_quarters_table)، لا بأرقام مكتوبة بالصدفة: كل
 * اختبار يقرأ حدود الربع المعنيّ من القاعدة نفسها، ويُتحقَّق أحيانًا بتأكيد
 * صريح (assertSame) أن الافتراض البنيوي الذي يعتمد عليه الاختبار (مثل "هذه
 * السورة تبدأ عندها ربع كامل") لا يزال صحيحًا، فبدل حساب خاطئ صامت يفشل
 * الاختبار بوضوح إن تغيّرت البيانات المرجعية لاحقًا.
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

    /** بداية سورة على محور الآيات المطلق (1..6236)، من ayah_count التراكمي لما قبلها. */
    private function globalStart(int $surahNumber): int
    {
        return (int) Surah::where('number', '<', $surahNumber)->sum('ayah_count') + 1;
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
    }

    /** @test */
    public function al_fatiha_is_outside_the_ladder_entirely(): void
    {
        $fatiha = $this->surah(1);

        $this->assertTrue($fatiha->excluded_from_progress);
        $this->assertNull($fatiha->memorization_order);
    }

    /** @test */
    public function the_countable_constant_matches_the_reference_table(): void
    {
        // 113 ليس رقمًا مكتوبًا بالصدفة: هو عدد السور القابلة للعدّ فعلًا
        // (لعدّاد "السور المكتملة" العرضي — لا لمقام نسبة الحفظ نفسها بعد
        // الآن، فتلك موزونة بالأرباع الـ240 لا بعدد السور).
        $this->assertSame(Surah::COUNTABLE_COUNT, Surah::countable()->count());
        $this->assertSame(114, Surah::count());
    }

    /* ===================== نسبة الطالب ===================== */

    /** @test */
    public function a_student_who_finished_only_an_nas_is_at_a_tiny_fraction_of_one_percent(): void
    {
        // الناس آخر سورة في المصحف — لا سورة "بعدها" في ترتيب المصحف، فلا
        // انسياب تلقائي إطلاقًا عند تسجيلها وحدها. تقع بالكامل داخل الربع
        // الأخير (240، آخر ربع في القرآن)، فنسبتها 6 آيات فقط من طول ذلك
        // الربع كاملًا — كسر ضئيل من خطوة واحدة من 240، لا ~1% كما كان قبل
        // هذا التصحيح (حين كانت كل سورة خطوة متساوية بصرف النظر عن طولها).
        $nas = $this->surah(114);
        $this->log(114, $nas->ayah_count);

        $quarter240 = Quarter::where('quarter_number', 240)->firstOrFail();
        $quarterLength = Student::TOTAL_AYAT - $quarter240->start_global_ayah + 1;

        $expected = round(($nas->ayah_count / $quarterLength) / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertLessThan(1.0, $this->student->progressPercentage());
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
        // نصف البقرة تقريبًا: تسجيل حتى آية 143 من 286 — بلا هذا تتجمّد
        // النسبة شهورًا كاملة أثناء حفظ أطول سورة في المصحف. البقرة هي
        // الخطوة الأخيرة (113) في ترتيب الحفظ، فتسجيلها ولو جزئيًا يُفعِّل
        // الانسياب التلقائي على كل ما بعدها في ترتيب المصحف (السور 3..114
        // كاملة)، بينما البقرة نفسها تبقى بتغطيتها الجزئية الفعلية فقط.
        //
        // بمقارنة آية 143 بحدود quarters الحقيقية: الأرباع 1..8 تقع كاملة
        // قبلها (الربع 8 ينتهي عند آية 141)، والربع 9 يبدأ عند آية 142
        // فتُغطّى منه آيتان فقط (142 و143). الأرباع 10..19 (عشرة أرباع) تقع
        // كلّها بعد آية 143 وقبل بداية آل عمران، فتبقى بلا أي تغطية — فجوة
        // حقيقية لم يُحفَظ منها شيء بعد. الانسياب من آل عمران يبدأ في منتصف
        // الربع 20 (يبدأ الربع 21 عند آل عمران:15)، ثم الأرباع 21..240 كاملة.
        $this->log(2, 143, 1);

        $quarter9 = Quarter::where('quarter_number', 9)->firstOrFail();
        $quarter10 = Quarter::where('quarter_number', 10)->firstOrFail();
        $quarter20 = Quarter::where('quarter_number', 20)->firstOrFail();
        $quarter21 = Quarter::where('quarter_number', 21)->firstOrFail();

        $quarter9Length = $quarter10->start_global_ayah - $quarter9->start_global_ayah;
        $quarter20Length = $quarter21->start_global_ayah - $quarter20->start_global_ayah;

        $coveredInQuarter9 = 143 - $quarter9->start_ayah + 1;
        $coveredInQuarter20 = $quarter21->start_global_ayah - $this->globalStart(3);

        $steps = ($quarter9->quarter_number - 1)
            + ($coveredInQuarter9 / $quarter9Length)
            + ($coveredInQuarter20 / $quarter20Length)
            + (Quarter::COUNT - $quarter20->quarter_number);

        $expected = round($steps / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertCount(
            Surah::COUNTABLE_COUNT - 1,
            $this->progress->completedSurahs($this->student),
            'كل ما قبل البقرة مكتمل بالانسياب التلقائي، لكن البقرة نفسها لم تكتمل بعد فلا تُعدّ.'
        );
    }

    /** @test */
    public function overlapping_sessions_are_counted_once(): void
    {
        // 1–20 ثم 15–40 = 40 آية لا 46: جمع أطوال الأسطر يضاعف المكرَّر.
        // (يختبر coverage() السوري — لا يتأثر بوزن الأرباع.)
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

    /* ===================== قراءات تربوية (السور، لا الأرباع) ===================== */

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

        // الإخلاص (الخطوة 3) هي الأبعد، فيُنسحب الاكتمال تلقائيًا على كل خطوة
        // أسبق منها أيضًا — أي الفلق (الخطوة 2) — لا الناس وحدها. معلومة
        // "السور المكتملة" هذه عرضية، منفصلة عن نسبة الحفظ الموزونة بالأرباع.
        $names = $this->progress->completedSurahs($this->student)->pluck('name')->all();

        $this->assertSame(['الناس', 'الفلق', 'الإخلاص'], $names);
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

        // الناس والفلق والإخلاص متتاليات (114..112) وتقع كلّها داخل الربع
        // الأخير (240) — كل نقطة هي مجموعها التراكمي من ذلك الربع وحده.
        $quarter240 = Quarter::where('quarter_number', 240)->firstOrFail();
        $quarterLength = Student::TOTAL_AYAT - $quarter240->start_global_ayah + 1;

        $this->assertEqualsWithDelta(round((6 / $quarterLength) / Quarter::COUNT * 100, 1), $points[0]['percent'], 0.05);
        $this->assertEqualsWithDelta(round((11 / $quarterLength) / Quarter::COUNT * 100, 1), $points[1]['percent'], 0.05);
        $this->assertEqualsWithDelta(round((15 / $quarterLength) / Quarter::COUNT * 100, 1), $points[2]['percent'], 0.05);
    }

    /** @test */
    public function several_logs_on_the_same_day_collapse_into_one_point(): void
    {
        $this->log(114, 6, 1, 'حفظ', '2026-02-01');
        $this->log(113, 5, 1, 'حفظ', '2026-02-01');

        $points = $this->progress->timeline($this->student);

        $this->assertCount(1, $points);

        $quarter240 = Quarter::where('quarter_number', 240)->firstOrFail();
        $quarterLength = Student::TOTAL_AYAT - $quarter240->start_global_ayah + 1;

        $this->assertEqualsWithDelta(round((11 / $quarterLength) / Quarter::COUNT * 100, 1), $points[0]['percent'], 0.05);
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
