<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سلامة بيانات جدول quarters المزروعة (S16) — فحص بنيوي على الاتساق مع
 * surahs، لا مطابقة كل رقم من الـ240 يدويًا.
 */
class QuartersSeedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function there_are_exactly_two_hundred_and_forty_quarters(): void
    {
        $this->assertSame(240, Quarter::count());
        $this->assertSame(Quarter::COUNT, Quarter::count());
    }

    /** @test */
    public function quarter_numbers_are_unique_and_sequential_from_one_to_two_hundred_forty(): void
    {
        $numbers = Quarter::query()->orderBy('quarter_number')->pluck('quarter_number')->all();

        $this->assertSame(range(1, 240), $numbers);
    }

    /** @test */
    public function the_first_quarter_starts_at_the_very_first_ayah(): void
    {
        $first = Quarter::where('quarter_number', 1)->firstOrFail();

        $this->assertSame(Surah::where('number', 1)->firstOrFail()->id, $first->start_surah_id);
        $this->assertSame(1, $first->start_ayah);
        $this->assertSame(1, $first->start_global_ayah);
    }

    /** @test */
    public function hizb_and_quarter_in_hizb_are_derived_correctly_from_the_quarter_number(): void
    {
        $q61 = Quarter::where('quarter_number', 61)->firstOrFail(); // بداية الجزء 8 (الأعراف)
        $this->assertSame(16, $q61->hizb_number);
        $this->assertSame(1, $q61->quarter_in_hizb);

        $q240 = Quarter::where('quarter_number', 240)->firstOrFail();
        $this->assertSame(60, $q240->hizb_number);
        $this->assertSame(4, $q240->quarter_in_hizb);
    }

    /** @test */
    public function the_quarters_span_the_entire_quran_with_no_gap_or_overlap(): void
    {
        $totalAyat = (int) Surah::sum('ayah_count');
        $this->assertSame(6236, $totalAyat);

        $starts = Quarter::query()->orderBy('quarter_number')->pluck('start_global_ayah')->all();

        $this->assertSame(1, $starts[0]);

        // كل حدّ لاحق أكبر تمامًا ممّا قبله (لا تراكب)، والفارق بينهما هو طول
        // الربع السابق فعليًا — لا فجوة ولا تراكب على محور الآيات المطلق.
        for ($i = 1; $i < count($starts); $i++) {
            $this->assertGreaterThan($starts[$i - 1], $starts[$i]);
        }

        $this->assertLessThan($totalAyat, $starts[array_key_last($starts)]);
    }
}
