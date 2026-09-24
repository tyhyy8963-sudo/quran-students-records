<?php

namespace App\Support;

use App\Models\Quarter;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use App\Support\Concerns\MergesAyahRanges;
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
 *
 * ═══ منطق الدمج/التقاطع مشترك ═══
 * راجع App\Support\Concerns\MergesAyahRanges — نفس الخوارزمية يستعملها الآن
 * MemorizationProgress أيضًا (منذ تحويل نسبة الحفظ للأرباع)، فبقيت نسخة واحدة
 * مُختبَرة لا نسختان قد تنحرفان عن بعضهما لاحقًا.
 */
class ReviewProgress
{
    use MergesAyahRanges;

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
            ->get(['surah_id', 'to_surah_id', 'from_ayah', 'to_ayah']);

        $globalRanges = [];

        foreach ($logs as $log) {
            $fromStart = $this->surahStarts()[$log->surah_id] ?? null;

            if ($fromStart === null) {
                continue;
            }

            // مراجعة عابرة لعدّة سور (S16): to_surah_id قد يختلف عن surah_id،
            // فتُحسَب البداية والنهاية كلٌّ من بدايتها المطلقة الخاصة بها لا
            // بمقارنة رقمي آية على محورين مختلفين.
            $toSurahId = $log->to_surah_id ?? $log->surah_id;
            $toStart = $this->surahStarts()[$toSurahId] ?? null;

            if ($toStart === null) {
                continue;
            }

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            $globalFrom = $fromStart + $from - 1;
            $globalTo = $toStart + $to - 1;

            if ($globalTo < $globalFrom) {
                continue;
            }

            $globalRanges[] = [$globalFrom, $globalTo];
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

    /**
     * عدد الأرباع المكتملة بالكامل من 240 — معلومة عرضية فوق النسبة نفسها
     * ("راجع 12 من 240 ربعًا" أوضح للمعلّم من رقم مئوي مجرّد وحده)، تلبيةً
     * لطلب عرض "عدد الأرباع/الأحزاب التي تمّت مراجعتها". لا تدخل حساب
     * percentage() (تلك تُحتسِب الأرباع الجزئية بنسبتها أيضًا)، عرض فقط.
     */
    public function quartersFullyReviewed(Student $student): int
    {
        $coverage = $this->coverage($student);
        $count = 0;

        foreach ($this->quarters() as $quarter) {
            $length = $quarter->range[1] - $quarter->range[0] + 1;

            if ($length > 0 && ($coverage[$quarter->quarter_number] ?? 0) >= $length) {
                $count++;
            }
        }

        return $count;
    }

    public function forget(Student $student): void
    {
        unset($this->coverageCache[(int) $student->student_id]);
    }

    /**
     * نشاط المراجعة الشهري (S16، استُبدل بها منحنى تراكمي سابق بعد ملاحظة
     * صاحب المنظومة أنه لا يعكس طبيعة المراجعة الحقيقية): عدد الأرباع التي
     * مسّتها أي مراجعة خلال كل شهر من آخر $months أشهر (بما فيها الشهر
     * الحالي) — أعمدة شهرية للمقارنة، لا نسبة تراكمية مستمرّة.
     *
     * ═══ لماذا أعمدة شهرية لا منحنى تراكمي ═══
     * المراجعة نشاط متكرّر بطبيعته: نفس الربع قد يُراجَع عدّة مرّات عبر عدّة
     * أشهر، فرسمها كنسبة تراكمية (كما في منحنى الحفظ) يخلط "كم أُنجِز حتى
     * الآن إجمالًا" بـ"كم عمل فعليًا هذا الشهر تحديدًا" — والثاني هو ما يريد
     * المعلّم مقارنته شهرًا بشهر، لا الأول.
     *
     * ═══ "مسّته" لا "اكتمل بالكامل" ═══
     * ربع يُحسَب لشهر لو كان له أي تقاطع فعلي مع مراجعات ذلك الشهر، ولو
     * جزئيًا — معيار أخفّ عمدًا من quartersFullyReviewed() (ذاك عن الإتقان،
     * هذا عن حجم النشاط الشهري).
     *
     * @return Collection<int, array{month: string, label: string, quarters: int}>
     */
    public function monthlyActivity(Student $student, int $months = 6): Collection
    {
        $logs = RecitationLog::query()
            ->where('student_id', (int) $student->student_id)
            ->where('type', 'مراجعة')
            ->whereNotNull('surah_id')
            ->whereNotNull('logged_at')
            ->get(['surah_id', 'to_surah_id', 'from_ayah', 'to_ayah', 'logged_at']);

        $starts = $this->surahStarts();
        $rangesByMonth = [];

        foreach ($logs as $log) {
            $fromStart = $starts[$log->surah_id] ?? null;

            if ($fromStart === null) {
                continue;
            }

            $toSurahId = $log->to_surah_id ?? $log->surah_id;
            $toStart = $starts[$toSurahId] ?? null;

            if ($toStart === null) {
                continue;
            }

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            $globalFrom = $fromStart + $from - 1;
            $globalTo = $toStart + $to - 1;

            if ($globalTo < $globalFrom) {
                continue;
            }

            $monthKey = $log->logged_at->format('Y-m');
            $rangesByMonth[$monthKey][] = [$globalFrom, $globalTo];
        }

        $quarters = $this->quarters();
        $result = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $merged = $this->mergeRanges($rangesByMonth[$monthKey] ?? []);

            $count = 0;
            foreach ($quarters as $quarter) {
                if ($this->intersectionLength($merged, $quarter->range) > 0) {
                    $count++;
                }
            }

            $result->push([
                'month'    => $monthKey,
                'label'    => $month->translatedFormat('F Y'),
                'quarters' => $count,
            ]);
        }

        return $result;
    }

    /**
     * منحنى تقدّم المراجعة: نسبة تراكمية عند كل تاريخ سُجِّلت فيه مراجعة جديدة
     * (S23.5 — إعادة التصميم بنمط هرماس؛ طلب صريح من يحيى إضافة منحنى مراجعة
     * موازٍ لمنحنى الحفظ الموجود أصلًا في MemorizationProgress::timeline()،
     * ليظهرا جنبًا لجنب في شبكة "التحليلات والإحصائيات" — لم يكن هذا المنحنى
     * موجودًا من قبل، فقط أعمدة monthlyActivity() الشهرية أعلاه، وهي تبقى
     * كما هي بلا أي تغيير لأنها تجيب سؤالًا مختلفًا: "كم رُوجع هذا الشهر
     * تحديدًا" لا "أين وصلت النسبة الإجمالية عبر الزمن").
     *
     * نفس مبدأ منحنى الحفظ حرفيًا (راجع تعليق MemorizationProgress::timeline()):
     * إعادة تشغيل السجلّ زمنيًا، وعند كل سطر تُحسَب نسبة الأرباع من كل مدى
     * مراجعة مُدمَج حتى تلك اللحظة — بنفس صيغة percentage()/coverage() أعلاه
     * تمامًا (تقاطع كل ربع مع المدى المُدمَج ÷ طول الربع)، لا صيغة موازية قد
     * تنحرف عنها لاحقًا.
     *
     * @return Collection<int, array{date: string, percent: float}>
     */
    public function timeline(Student $student): Collection
    {
        $logs = $student->recitationLogs()
            ->reorder('logged_at')
            ->orderBy('id')
            ->where('type', 'مراجعة')
            ->whereNotNull('surah_id')
            ->get(['surah_id', 'to_surah_id', 'from_ayah', 'to_ayah', 'logged_at']);

        $starts = $this->surahStarts();
        $globalRanges = [];
        $points = [];

        foreach ($logs as $log) {
            $fromStart = $starts[$log->surah_id] ?? null;

            if ($fromStart === null) {
                continue;
            }

            // مراجعة عابرة لعدّة سور (S16) — نفس منطق coverage() أعلاه بالضبط.
            $toSurahId = $log->to_surah_id ?? $log->surah_id;
            $toStart = $starts[$toSurahId] ?? null;

            if ($toStart === null) {
                continue;
            }

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            $globalFrom = $fromStart + $from - 1;
            $globalTo = $toStart + $to - 1;

            if ($globalTo >= $globalFrom) {
                $globalRanges[] = [$globalFrom, $globalTo];
            }

            $merged = $this->mergeRanges($globalRanges);

            // يوم واحد قد يحمل عدّة أسطر — تُبقى آخر نسبة في اليوم لا كل سطر،
            // فالمنحنى يوميّ لا لكل إدخال (نفس مبدأ منحنى الحفظ).
            $points[optional($log->logged_at)->toDateString()] = $this->percentageFromMergedRanges($merged);
        }

        return collect($points)
            ->map(fn (float $percent, string $date) => ['date' => $date, 'percent' => $percent])
            ->values();
    }

    /**
     * نسبة المراجعة من مدى مطلق مُدمَج جاهز — استُخرجت من percentage() لتُستعمَل
     * أيضًا في timeline() بلا تكرار نفس حلقة الأرباع مرّتين بصيغتين مختلفتين.
     *
     * @param  array<int, array{0: int, 1: int}>  $mergedRanges
     */
    private function percentageFromMergedRanges(array $mergedRanges): float
    {
        $steps = 0.0;

        foreach ($this->quarters() as $quarter) {
            $length = $quarter->range[1] - $quarter->range[0] + 1;

            if ($length < 1) {
                continue;
            }

            $steps += min(1.0, $this->intersectionLength($mergedRanges, $quarter->range) / $length);
        }

        return round(min($steps / Quarter::COUNT * 100, 100), 1);
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
