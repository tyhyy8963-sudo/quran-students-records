<?php

namespace App\Support;

use App\Models\Quarter;
use App\Models\Surah;
use Illuminate\Support\Collection;

/**
 * مرجع وحدات المراجعة الجاهزة (جزء/حزب/نصف حزب/ربع حزب) بحدودها الفعلية
 * (سورة وآية البداية والنهاية) — القرار #54: طلب يحيى إضافة خيار اختيار
 * مباشر بهذه الوحدات في نموذج تسجيل المراجعة بدل حصر الإدخال على "من
 * سورة/إلى سورة" فقط، بلا حاجة لتحديد رقم آية دقيق.
 *
 * ═══ لا بيانات جديدة ═══
 * كل الوحدات الأربع إعادة تجميع لنفس جدول `quarters` المرجعي الثابت (240
 * ربع حزب، مؤكَّد مطابقًا حرفًا بحرف لتقسيمات الأجزاء المعروفة — راجع القرار
 * #21): جزء = 8 أرباع متتالية، حزب = 4، نصف حزب = 2، ربع حزب = 1 (الوحدة
 * الأصغر، مطابقة لصفّ الجدول مباشرة). 240 يقبل القسمة على 8/4/2/1 كلّها بلا
 * باقٍ، فلا حاجة لأي معالجة خاصّة لوحدة أخيرة غير مكتملة.
 *
 * ═══ لماذا نسخة مستقلّة لا امتداد لـReviewAmount ═══
 * ReviewAmount يحسب مقدار سجلّ موجود بالفعل (سورة→ربع حزب). هذا الصنف
 * يعمل بالاتجاه المعاكس تمامًا: من رقم وحدة (جزء/حزب...) إلى (سورة، آية)
 * ليُستهلَك في واجهة الإدخال قبل إنشاء أي سجلّ. حاجة مختلفة (اتجاه معكوس)
 * رغم الاعتماد على نفس المرجع (`quarters`) — نفس مبدأ الفصل الموثَّق في
 * تعليق ReviewAmount نفسه.
 */
class QuranUnitReference
{
    /** @var Collection<int, Surah>|null */
    private ?Collection $surahCache = null;

    /** @var array<int, int>|null */
    private ?array $surahStartCache = null;

    /**
     * القوائم الأربع الجاهزة للعرض/الإرسال للواجهة، كل عنصر:
     * {number, label, from_surah_id, from_ayah, to_surah_id, to_ayah}.
     *
     * @return array{juz: array, hizb: array, half_hizb: array, quarter_hizb: array}
     */
    public function all(): array
    {
        $quarters = $this->quartersWithBounds();

        return [
            'juz'          => $this->group($quarters, 8, fn ($n) => "الجزء {$n}"),
            'hizb'         => $this->group($quarters, 4, fn ($n) => "الحزب {$n}"),
            'half_hizb'    => $this->group($quarters, 2, fn ($n) => "نصف الحزب {$n}"),
            'quarter_hizb' => $this->group($quarters, 1, fn ($n) => "ربع الحزب {$n}"),
        ];
    }

    /**
     * يجمع كل $size ربع حزب متتاليًا (مرتَّبة أصلًا برقم الربع) في وحدة
     * واحدة: بدايتها بداية أول ربع فيها، ونهايتها نهاية آخر ربع فيها.
     *
     * @param  Collection<int, array{from_surah_id:int,from_ayah:int,to_surah_id:int,to_ayah:int}>  $quarters
     * @return array<int, array{number:int,label:string,from_surah_id:int,from_ayah:int,to_surah_id:int,to_ayah:int}>
     */
    private function group(Collection $quarters, int $size, callable $label): array
    {
        $units = [];
        $count = $quarters->count();
        $number = 1;

        for ($i = 0; $i < $count; $i += $size, $number++) {
            $first = $quarters[$i];
            $last = $quarters[min($i + $size - 1, $count - 1)];

            $units[] = [
                'number'        => $number,
                'label'         => $label($number),
                'from_surah_id' => $first['from_surah_id'],
                'from_ayah'     => $first['from_ayah'],
                'to_surah_id'   => $last['to_surah_id'],
                'to_ayah'       => $last['to_ayah'],
            ];
        }

        return $units;
    }

    /**
     * الأرباع الـ240 بحدودها الكاملة (بداية ونهاية، سورة وآية) — النهاية
     * محسوبة من بداية الربع التالي مطروحًا منها آية واحدة (أو آخر آية في
     * القرآن كلّه للربع الأخير)، بنفس منطق ReviewAmount::quarters() تمامًا
     * لكن مع تحويل إضافي من موضع مطلق إلى (سورة، آية) لازم هنا تحديدًا.
     *
     * @return Collection<int, array{from_surah_id:int,from_ayah:int,to_surah_id:int,to_ayah:int}>
     */
    private function quartersWithBounds(): Collection
    {
        $rows = Quarter::query()->orderBy('quarter_number')->get(['start_surah_id', 'start_ayah', 'start_global_ayah'])->values();
        $totalAyat = (int) array_sum($this->surahs()->pluck('ayah_count')->all());

        return $rows->map(function ($quarter, $index) use ($rows, $totalAyat) {
            $next = $rows->get($index + 1);
            $endGlobal = $next !== null ? $next->start_global_ayah - 1 : $totalAyat;
            [$toSurahId, $toAyah] = $this->fromGlobalAyah($endGlobal);

            return [
                'from_surah_id' => $quarter->start_surah_id,
                'from_ayah'     => $quarter->start_ayah,
                'to_surah_id'   => $toSurahId,
                'to_ayah'       => $toAyah,
            ];
        });
    }

    /**
     * يحوّل موضعًا مطلقًا على محور الآيات كلّه (1..6236) إلى (رقم سورة، رقم
     * آية داخلها) — معكوس تمامًا لعملية surahStarts() أدناه.
     *
     * @return array{0: int, 1: int}
     */
    private function fromGlobalAyah(int $global): array
    {
        $starts = $this->surahStarts();
        $surahId = null;

        foreach ($starts as $id => $start) {
            if ($start > $global) {
                break;
            }
            $surahId = $id;
        }

        return [$surahId, $global - $starts[$surahId] + 1];
    }

    /**
     * بداية كل سورة على محور الآيات المطلق (1..6236) — نسخة مطابقة لتلك في
     * ReviewAmount/ReviewProgress (راجع تعليق ReviewAmount لسبب عدم توحيدها
     * في مصنع مشترك).
     *
     * @return array<int, int>
     */
    private function surahStarts(): array
    {
        if ($this->surahStartCache !== null) {
            return $this->surahStartCache;
        }

        $running = 1;
        $starts = [];

        foreach ($this->surahs() as $surah) {
            $starts[$surah->id] = $running;
            $running += $surah->ayah_count;
        }

        return $this->surahStartCache = $starts;
    }

    /** @return Collection<int, Surah> */
    private function surahs(): Collection
    {
        return $this->surahCache ??= Surah::query()->orderBy('number')->get()->keyBy('id');
    }
}
