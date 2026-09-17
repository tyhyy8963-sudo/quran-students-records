<?php

namespace App\Support;

use App\Models\Quarter;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use Illuminate\Support\Collection;

/**
 * نسبة المراجعة بوزن الأرباع لا بعدد الآيات الخام (S16).
 *
 * ═══ لماذا الأرباع لا الآيات ═══
 * سورة الشعراء (227 آية) ليست "أطول" من أول البقرة (141 آية) بضِعف واقعي في
 * زمن المراجعة أو ثقلها التربوي — التفاوت الحقيقي بين السور فوضوي (من 3 آيات
 * إلى 286). الأرباع (240 ربعًا، جدول quarters) تقسيم تقليدي شبه متساوي الطول
 * فعلًا، فوزن كل ربع 1/240 يعكس الجهد الحقيقي أفضل من وزن كل آية 1/6236.
 *
 * ═══ الصيغة ═══
 * كل سجلّ "مراجعة" يُحوَّل من (سورة، من آية، إلى آية) إلى مدى مطلق على محور
 * الآيات كلّه (1..6236) عبر مجموع ayah_count التراكمي لما قبل السورة. تُدمَج
 * كل مديات الطالب المطلقة (نفس فكرة اتحاد المديات المتداخلة، عبر القرآن كلّه
 * لا لكل سورة). لكل ربع من الـ240 مدى مطلق ثابت [بدايته، بداية التالي - 1]؛
 * تغطيته = تقاطع مدى الطالب المُدمَج معه ÷ طول الربع (بين 0 و1).
 *
 *     نسبة المراجعة = (مجموع تغطية كل ربع) ÷ 240 × 100
 *
 * ═══ "مراجعة" فقط ═══
 * "تسميع" فحص لما سبق حفظه لا مراجعة، و"حفظ" ليس مراجعة بالتعريف — كلاهما
 * مستثنى من هذا الحساب (بخلاف MemorizationProgress الذي يستثنيهما لسبب معكوس:
 * هناك لأنهما ليسا تقدّمًا جديدًا، هنا لأنهما ليسا مراجعة أصلًا).
 *
 * ═══ لا أرضية ═══
 * بخلاف الحفظ والمتون، لا "آخر ربع رُوجع قبل الانضمام" مفهوم واحد واضح (المراجعة
 * نشاط متكرّر لا تسلسل صاعد له نقطة توقّف واحدة) — الرقم من السجلّات المسجَّلة
 * فعليًا فقط، بلا تلفيق.
 */
class ReviewProgress
{
    /** @var Collection<int, Surah>|null */
    private ?Collection $surahCache = null;

    /** @var array<int, int>|null بداية كل سورة المطلقة: [surah_id => بدايتها] */
    private ?array $surahStartCache = null;

    /** @var Collection<int, Quarter>|null مرتَّبة برقم الربع */
    private ?Collection $quartersCache = null;

    /** @var array<int, array<int, int>> */
    private array $coverageCache = [];

    /**
     * تغطية كل ربع بالآيات: [quarter_number => آيات مغطّاة داخل الربع].
     *
     * @return array<int, int>
     */
    public function coverage(Student $student): array
    {
        $studentId = (int) $student->student_id;

        if (isset($this->coverageCache[$studentId])) {
            return $this->coverageCache[$studentId];
        }

        $logs = RecitationLog::query()
            ->where('student_id', $studentId)
            ->where('type', 'مراجعة')
            ->whereNotNull('surah_id')
            ->get(['surah_id', 'from_ayah', 'to_ayah']);

        $globalRanges = [];

        foreach ($logs as $log) {
            $start = $this->surahStarts()[$log->surah_id] ?? null;

            if ($start === null) {
                continue;
            }

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            if ($to < $from) {
                continue;
            }

            $globalRanges[] = [$start + $from - 1, $start + $to - 1];
        }

        $merged = $this->mergeRanges($globalRanges);

        $coverage = [];

        foreach ($this->quarters() as $quarter) {
            $coverage[$quarter->quarter_number] = $this->intersectionLength($merged, $quarter->range);
        }

        return $this->coverageCache[$studentId] = $coverage;
    }

    public function percentage(Student $student): float
    {
        $coverage = $this->coverage($student);

        $steps = 0.0;

        foreach ($this->quarters() as $quarter) {
            $length = $quarter->range[1] - $quarter->range[0] + 1;

            if ($length < 1) {
                continue;
            }

            $steps += min(1.0, ($coverage[$quarter->quarter_number] ?? 0) / $length);
        }

        return round(min($steps / Quarter::COUNT * 100, 100), 1);
    }

    public function forget(Student $student): void
    {
        unset($this->coverageCache[(int) $student->student_id]);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $ranges
     * @return array<int, array{0: int, 1: int}> مُدمَجة ومُرتَّبة
     */
    private function mergeRanges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        [$currentStart, $currentEnd] = $ranges[0];

        for ($i = 1; $i < count($ranges); $i++) {
            [$start, $end] = $ranges[$i];

            if ($start <= $currentEnd + 1) {
                $currentEnd = max($currentEnd, $end);
                continue;
            }

            $merged[] = [$currentStart, $currentEnd];
            [$currentStart, $currentEnd] = [$start, $end];
        }

        $merged[] = [$currentStart, $currentEnd];

        return $merged;
    }

    /**
     * طول تقاطع مديات مُدمَجة مع مدى واحد (مدى الربع).
     *
     * @param  array<int, array{0: int, 1: int}>  $mergedRanges
     * @param  array{0: int, 1: int}  $target
     */
    private function intersectionLength(array $mergedRanges, array $target): int
    {
        $total = 0;

        foreach ($mergedRanges as [$start, $end]) {
            $overlapStart = max($start, $target[0]);
            $overlapEnd = min($end, $target[1]);

            if ($overlapEnd >= $overlapStart) {
                $total += $overlapEnd - $overlapStart + 1;
            }
        }

        return $total;
    }

    /**
     * بداية كل سورة على محور الآيات المطلق (1..6236)، من ayah_count التراكمي.
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
     * الأرباع الـ240 مرتَّبة برقم الربع، ومعها مداها المطلق [بداية، نهاية]
     * محسوبًا من بداية الربع التالي (والربع الأخير ينتهي عند آخر آية).
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
