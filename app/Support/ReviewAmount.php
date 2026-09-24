<?php

namespace App\Support;

use App\Models\Quarter;
use App\Models\RecitationLog;
use App\Models\Surah;
use App\Support\Concerns\MergesAyahRanges;
use Illuminate\Support\Collection;

/**
 * مقدار سجلّ مراجعة واحد بوحدة الجزء (S23) — "ربع جزء"/"نصف جزء"/
 * "ثلاثة أرباع جزء"/"جزء كامل"/"جزء ونصف"...، محسوب من موضع السجلّ الفعلي
 * (من/إلى) لا إدخالًا يدويًا من المعلّم (طلب صريح من يحيى، بند 3 من تقرير
 * التطوير).
 *
 * ═══ لماذا ليس ReviewProgress ═══
 * ReviewProgress يحسب نسبة تراكمية عبر كل سجلّات "مراجعة" للطالب مدمَجة معًا
 * (شريط تقدّم واحد للطالب). هذا الصنف يحسب مقدار سجلّ واحد بمفرده — لا دمج
 * ولا تراكم — لعرضه في عمود "المراجعة" بقائمة التحضير الموحّدة. حاجة مختلفة
 * تمامًا رغم الاعتماد على نفس مرجع الأرباع (جدول quarters) ونفس فكرة تحويل
 * (سورة، آية) لموضع مطلق على محور القرآن كلّه.
 *
 * نسخة مستقلّة عمدًا لا مصنع مشترك مع ReviewProgress: ReviewProgress صنف
 * مُختبَر يعمل فعليًا منذ S16، وأي تغيير فيه (كاستخراج منطق مشترك) يحمل خطر
 * انحراف غير مقصود في حساب موجود بالفعل ومُعتمَد عليه. التكرار هنا صغير
 * (تحويل سورة/آية لموضع مطلق + تحميل الأرباع الـ240) ومقبول مقابل صفر خطر
 * على حساب قائم.
 *
 * ═══ الوحدة: "ربع" هنا في جدول quarters = ربع الحِزب لا ربع الجزء ═══
 * الجزء = حزبان = 8 "أرباع" كما هي مخزَّنة في جدول quarters (240 = 60 حزبًا
 * × 4). طلب يحيى يريد الوحدة بمقياس الجزء (ربع/نصف/ثلاثة أرباع/جزء)، فكل
 * "ربع جزء" معروض = ربعا حزب مخزَّنان معًا.
 */
class ReviewAmount
{
    use MergesAyahRanges;

    private ?Collection $surahCache = null;

    /** @var array<int, int>|null */
    private ?array $surahStartCache = null;

    private ?Collection $quartersCache = null;

    /**
     * مقدار سجلّ واحد كنص عربي جاهز للعرض ("نصف جزء"، "جزء وربع"، "جزءان"...).
     * "—" لو تعذّر حساب موضع مطلق للسجلّ (بيانات ناقصة لا يُفترَض وقوعها فعليًا).
     */
    public function label(RecitationLog $log): string
    {
        $touchedHizbQuarters = $this->rawQuartersTouched($log);

        if ($touchedHizbQuarters === null) {
            return '—';
        }

        // كل "ربع جزء" معروض = ربعا حزب مخزَّنان (الجزء = حزبان = 8 أرباع حزب)،
        // فتُقرَّب القراءة الخام لأقرب زوج (أقرب وحدة "ربع جزء" مُسمّاة فعليًا).
        $quarterOfJuzUnits = (int) round($touchedHizbQuarters / 2);

        if ($quarterOfJuzUnits <= 0) {
            return 'أقل من ربع جزء';
        }

        $juz = intdiv($quarterOfJuzUnits, 4);
        $remainder = $quarterOfJuzUnits % 4;

        $fractionLabels = [0 => '', 1 => 'ربع جزء', 2 => 'نصف جزء', 3 => 'ثلاثة أرباع جزء'];

        if ($juz === 0) {
            return $fractionLabels[$remainder];
        }

        $juzLabel = match (true) {
            $juz === 1 => 'جزء',
            $juz === 2 => 'جزءان',
            default    => "{$juz} أجزاء",
        };

        if ($remainder === 0) {
            return $juz === 1 ? 'جزء كامل' : "{$juzLabel} كاملة";
        }

        return "{$juzLabel} و{$fractionLabels[$remainder]}";
    }

    /** عدد أرباع الحزب (المخزَّنة فعليًا في جدول quarters) التي تقاطعت مع مدى هذا السجلّ. */
    private function rawQuartersTouched(RecitationLog $log): ?int
    {
        if ($log->surah_id === null || $log->to_ayah === null) {
            return null;
        }

        $starts = $this->surahStarts();
        $fromStart = $starts[$log->surah_id] ?? null;

        if ($fromStart === null) {
            return null;
        }

        $toSurahId = $log->to_surah_id ?? $log->surah_id;
        $toStart = $starts[$toSurahId] ?? null;

        if ($toStart === null) {
            return null;
        }

        $from = max(1, (int) ($log->from_ayah ?: 1));
        $to = (int) $log->to_ayah;

        $globalFrom = $fromStart + $from - 1;
        $globalTo = $toStart + $to - 1;

        if ($globalTo < $globalFrom) {
            return null;
        }

        $count = 0;

        foreach ($this->quarters() as $quarter) {
            if ($this->intersectionLength([[$globalFrom, $globalTo]], $quarter->range) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * بداية كل سورة على محور الآيات المطلق (1..6236) — نسخة مطابقة لتلك في
     * ReviewProgress (راجع تعليق الصنف أعلاه لسبب عدم توحيدهما).
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

    /**
     * الأرباع الـ240 مرتَّبة برقم الربع، ومعها مداها المطلق [بداية، نهاية].
     *
     * @return Collection<int, object{quarter_number: int, range: array{0: int, 1: int}}>
     */
    private function quarters(): Collection
    {
        if ($this->quartersCache !== null) {
            return $this->quartersCache;
        }

        $rows = Quarter::query()->orderBy('quarter_number')->get(['quarter_number', 'start_global_ayah']);
        $totalAyat = (int) array_sum($this->surahs()->pluck('ayah_count')->all());

        $withRange = $rows->map(function ($quarter, $index) use ($rows, $totalAyat) {
            $next = $rows->get($index + 1);
            $end = $next !== null ? $next->start_global_ayah - 1 : $totalAyat;

            return (object) [
                'quarter_number' => $quarter->quarter_number,
                'range'          => [$quarter->start_global_ayah, $end],
            ];
        });

        return $this->quartersCache = $withRange;
    }

    /**
     * @return Collection<int, Surah>
     */
    private function surahs(): Collection
    {
        return $this->surahCache ??= Surah::query()->orderBy('number')->get()->keyBy('id');
    }
}
