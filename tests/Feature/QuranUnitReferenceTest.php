<?php

namespace Tests\Feature;

use App\Models\Surah;
use App\Support\QuranUnitReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مرجع وحدات الجزء/الحزب/نصف الحزب/ربع الحزب (S23.5 — القرار #54) — يعيد
 * تجميع نفس جدول `quarters` الثابت (240 ربعًا) بمضاعفات مختلفة، بلا أي
 * بيانات جديدة. الحدود مؤكَّدة هنا مقابل حقائق أجزاء القرآن المعروفة، بنفس
 * الطريقة التي تحقّق بها القرار #21 من مطابقة جدول quarters نفسه أصلًا.
 */
class QuranUnitReferenceTest extends TestCase
{
    use RefreshDatabase;

    private QuranUnitReference $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = app(QuranUnitReference::class);
    }

    private function surahId(int $number): int
    {
        return Surah::where('number', $number)->firstOrFail()->id;
    }

    /** @test */
    public function it_produces_the_correct_count_for_each_unit_type(): void
    {
        $all = $this->ref->all();

        $this->assertCount(30, $all['juz']);
        $this->assertCount(60, $all['hizb']);
        $this->assertCount(120, $all['half_hizb']);
        $this->assertCount(240, $all['quarter_hizb']);
    }

    /** @test */
    public function juz_one_starts_at_the_fatiha_and_ends_at_baqarah_141(): void
    {
        $juz1 = $this->ref->all()['juz'][0];

        $this->assertSame($this->surahId(1), $juz1['from_surah_id']);
        $this->assertSame(1, $juz1['from_ayah']);
        $this->assertSame($this->surahId(2), $juz1['to_surah_id']);
        $this->assertSame(141, $juz1['to_ayah']);
    }

    /**
     * حدود مؤكَّدة صراحة من يحيى (القرار #21): "بداية الجزء 15 عند
     * الإسراء:1 = الربع رقم 113؛ بداية الجزء 16 عند الكهف:75 = الربع رقم
     * 121" — أي جزء 15 ينتهي عند الكهف:74 تحديدًا (الآية التي تسبق بداية
     * جزء 16 مباشرة).
     *
     * @test
     */
    public function juz_boundaries_match_the_confirmed_reference_image(): void
    {
        $juz = $this->ref->all()['juz'];

        $juz15 = $juz[14];
        $this->assertSame($this->surahId(17), $juz15['from_surah_id']); // الإسراء
        $this->assertSame(1, $juz15['from_ayah']);
        $this->assertSame($this->surahId(18), $juz15['to_surah_id']); // الكهف
        $this->assertSame(74, $juz15['to_ayah']);

        $juz16 = $juz[15];
        $this->assertSame($this->surahId(18), $juz16['from_surah_id']); // الكهف
        $this->assertSame(75, $juz16['from_ayah']);
    }

    /** @test */
    public function juz_thirty_ends_at_the_last_ayah_of_the_quran(): void
    {
        $juz30 = $this->ref->all()['juz'][29];

        $this->assertSame($this->surahId(114), $juz30['to_surah_id']); // الناس
        $this->assertSame(6, $juz30['to_ayah']);
    }

    /** @test */
    public function each_juz_covers_exactly_two_hizb_and_the_hizb_agree_on_boundaries(): void
    {
        $all = $this->ref->all();

        // كل جزء = حزبان متتاليان بحدود متطابقة تمامًا (بداية الجزء = بداية
        // أول حزب فيه، ونهايته = نهاية الحزب الثاني).
        foreach ($all['juz'] as $index => $juz) {
            $firstHizb = $all['hizb'][$index * 2];
            $secondHizb = $all['hizb'][$index * 2 + 1];

            $this->assertSame($juz['from_surah_id'], $firstHizb['from_surah_id']);
            $this->assertSame($juz['from_ayah'], $firstHizb['from_ayah']);
            $this->assertSame($juz['to_surah_id'], $secondHizb['to_surah_id']);
            $this->assertSame($juz['to_ayah'], $secondHizb['to_ayah']);
        }
    }

    /** @test */
    public function quarter_hizb_units_match_the_raw_quarters_table_one_to_one(): void
    {
        $quarterHizb = $this->ref->all()['quarter_hizb'];

        $quarter9 = $quarterHizb[8];
        $this->assertSame($this->surahId(2), $quarter9['from_surah_id']);
        $this->assertSame(142, $quarter9['from_ayah']);
    }
}
