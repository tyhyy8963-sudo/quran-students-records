<?php

namespace Tests\Feature;

use App\Models\RecitationLog;
use App\Models\Surah;
use App\Support\ReviewAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مقدار سجلّ مراجعة واحد بوحدة الجزء (S23، بند 3 من تقرير التطوير).
 *
 * نفس حدود الأرباع الحقيقية المستعملة في ReviewProgressTest (سورة البقرة):
 * الربع 1 = آية 1..25، الربع 2 = 26..43، الربع 3 = 44..59، الربع 4 يبدأ من
 * آية 60. لا حاجة لحفظ RecitationLog في القاعدة هنا: ReviewAmount يقرأ
 * خصائص الموديل فقط (surah_id/to_ayah/from_ayah)، فموديل في الذاكرة يكفي.
 */
class ReviewAmountTest extends TestCase
{
    use RefreshDatabase;

    private ReviewAmount $amount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amount = app(ReviewAmount::class);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    private function log(int $surahNumber, int $from, int $to): RecitationLog
    {
        return new RecitationLog([
            'surah_id' => $this->surah($surahNumber)->id,
            'from_ayah' => $from,
            'to_ayah' => $to,
            'type' => 'مراجعة',
        ]);
    }

    /** @test */
    public function a_review_touching_a_single_raw_quarter_is_a_quarter_of_a_juz(): void
    {
        // كامل الربع 2 (26..43) — ربع حزب واحد فقط = ربع جزء (كل "ربع جزء"
        // معروض = ربعا حزب).
        $this->assertSame('ربع جزء', $this->amount->label($this->log(2, 26, 43)));
    }

    /** @test */
    public function a_review_touching_two_raw_quarters_is_still_a_quarter_of_a_juz(): void
    {
        // يمسّ الربعين 2 و3 (30..50) — لا يُحسَب تراكميًا كنصف جزء لمجرّد
        // مسّ ربعين خام: 2 ÷ 2 = ربع جزء واحد بالتقريب.
        $this->assertSame('ربع جزء', $this->amount->label($this->log(2, 30, 50)));
    }

    /** @test */
    public function four_raw_quarters_make_half_a_juz(): void
    {
        // الأرباع 1..4 (آية 1 إلى 59) — 4 أرباع حزب = نصف جزء بالضبط.
        $this->assertSame('نصف جزء', $this->amount->label($this->log(2, 1, 59)));
    }

    /** @test */
    public function eight_raw_quarters_make_a_full_juz(): void
    {
        // الأرباع 1..8 (آية 1 إلى بداية الربع 9 - 1) — جزء كامل بالضبط.
        // البقرة أول سورة تبدأ بعد الفاتحة على المحور المطلق، فبدايتها
        // المطلقة = عدد آيات الفاتحة + 1 — لا رقم مكتوب بالصدفة.
        $ninthQuarterStart = \App\Models\Quarter::where('quarter_number', 9)->firstOrFail()->start_global_ayah;
        $baqarahGlobalStart = $this->surah(1)->ayah_count + 1;
        $lastAyahOfEighthQuarter = ($ninthQuarterStart - 1) - ($baqarahGlobalStart - 1);

        $this->assertSame('جزء كامل', $this->amount->label($this->log(2, 1, $lastAyahOfEighthQuarter)));
    }

    /** @test */
    public function a_range_crossing_two_surahs_is_computed_on_the_absolute_axis(): void
    {
        // نهاية البقرة إلى بداية آل عمران — مدى عابر لسورتين محسوب على محور
        // مطلق واحد لا لكل سورة منفصلة (نفس منطق ReviewProgress).
        $baqarah = $this->surah(2);

        $log = new RecitationLog([
            'surah_id' => $baqarah->id,
            'to_surah_id' => $this->surah(3)->id,
            'from_ayah' => $baqarah->ayah_count - 5,
            'to_ayah' => 5,
            'type' => 'مراجعة',
        ]);

        $this->assertNotSame('—', $this->amount->label($log));
    }

    /** @test */
    public function an_incomplete_log_returns_a_placeholder(): void
    {
        $log = new RecitationLog(['type' => 'مراجعة']);

        $this->assertSame('—', $this->amount->label($log));
    }
}
