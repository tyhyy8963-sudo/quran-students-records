<?php

namespace App\Support;

use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use Illuminate\Support\Collection;

/**
 * حساب نسبة الحفظ بترتيب الحفظ المعكوس (S14) — المصدر الوحيد للرقم.
 *
 * ═══ السلّم ═══
 * ترتيب المصحف يبدأ بالفاتحة، لكن الحفظ يبدأ من آخره: الناس أولًا صعودًا إلى
 * البقرة. لكل سورة (عدا الفاتحة) رتبة في هذا التسلسل مخزَّنة في العمود
 * memorization_order: الناس = 1 … البقرة = 113. فإتمام الناس وحدها ≈ 1%،
 * وإتمام كل شيء = 100%، تمامًا كما هو مطلوب.
 *
 * ═══ لماذا "مجموع التغطية" لا "رتبة أبعد سورة" ═══
 * لو كانت النسبة = رتبة أبعد سورة أُتمّت، لقفز طالب حفظ البقرة وحدها (نقلًا من
 * حلقة أخرى) إلى 100% وهو لم يحفظ سواها. ولو كانت مجموع نِسَب السور المكتملة،
 * لتجاوز المجموع 100% بكثير (مجموع الرتب 1..113 يعطي 5700%). الصيغة الصحيحة:
 *
 *     كل سورة تساوي خطوة واحدة من 113 خطوة.
 *     نسبة الطالب = (مجموع تغطية كل سورة) ÷ 113 × 100
 *     تغطية السورة = عدد آياتها المحفوظة ÷ عدد آياتها (بين 0 و1)
 *
 * فالحفظ التسلسلي المعتاد يعطي بالضبط سلّم الناس=1% … البقرة=100%، وفي الوقت
 * نفسه لا تتجمّد النسبة شهورًا أثناء حفظ سورة طويلة كالبقرة: تتحرّك بمقدار ما
 * أُنجز منها فعلًا.
 *
 * ═══ اتحاد المدى لا جمع الأسطر ═══
 * البقرة لا تُحفَظ في جلسة واحدة، بل عبر عشرات الأسطر المتداخلة أحيانًا
 * (1–20 ثم 15–40). جمع أطوال الأسطر يحتسب الآيات المكرَّرة مرّتين ويعطي تغطية
 * أكبر من الحقيقة، لذا تُدمَج المديات المتداخلة قبل القياس.
 *
 * ═══ الفاتحة ═══
 * مستثناة كليًا: لا تدخل المقام (113) ولا تضيف شيئًا للنسبة، مهما سُجِّلت أو
 * روجعت. تبقى قابلة للتسجيل في السجلّ الزمني كأي سورة.
 *
 * ═══ نوع "حفظ" وحده ═══
 * "مراجعة" و"تسميع" أحداث على مقطع سبق حفظه، لا تقدّم جديد — إدخالها في
 * التغطية يجعل مراجعة الطالب لما حفظه ترفع نسبته مرّة ثانية.
 */
class MemorizationProgress
{
    /** @var Collection<int, Surah>|null */
    private ?Collection $surahCache = null;

    /** @var array<int, array<int, int>> تغطية محسوبة مسبقًا لكل طالب */
    private array $coverageCache = [];

    /**
     * تغطية كل سورة بالآيات: [surah_id => عدد الآيات المحفوظة].
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
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->get(['student_id', 'surah_id', 'from_ayah', 'to_ayah']);

        $coverage = $this->coverageFromLogs($logs);
        $this->applyBaseline($coverage, $student);

        return $this->coverageCache[$studentId] = $coverage;
    }

    /**
     * تحميل تغطية مجموعة طلاب باستعلام واحد.
     *
     * لوحة فيها خمسون طالبًا تطلب نسبة كل واحد منهم؛ بلا هذا يصبح لكل صفّ
     * استعلامه (N+1). تُستدعى مرّة من المتحكّم قبل بناء الاستجابة، وتبقى بقية
     * الشيفرة تنادي percentage() كما هي بلا معرفة بالفرق.
     *
     * @param  iterable<Student>  $students
     */
    public function warmFor(iterable $students): void
    {
        $studentsById = collect($students)
            ->keyBy(fn (Student $s) => (int) $s->student_id)
            ->reject(fn (Student $s) => isset($this->coverageCache[(int) $s->student_id]));

        if ($studentsById->isEmpty()) {
            return;
        }

        $ids = $studentsById->keys()->values();

        $logsByStudent = RecitationLog::query()
            ->whereIn('student_id', $ids)
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->get(['student_id', 'surah_id', 'from_ayah', 'to_ayah'])
            ->groupBy('student_id');

        foreach ($studentsById as $id => $student) {
            $coverage = $this->coverageFromLogs($logsByStudent->get($id) ?? collect());
            $this->applyBaseline($coverage, $student);
            $this->coverageCache[$id] = $coverage;
        }
    }

    /**
     * إسقاط التغطية المخزَّنة لطالب بعد تغيير سجلّه في نفس الطلب (إضافة/حذف
     * سطر) — الخدمة singleton، فبلا هذا قد يُقرأ رقم ما قبل التغيير.
     */
    public function forget(Student $student): void
    {
        unset($this->coverageCache[(int) $student->student_id]);
    }

    /**
     * @param  Collection<int, RecitationLog>  $logs
     * @return array<int, int>
     */
    private function coverageFromLogs(Collection $logs): array
    {
        $ranges = [];

        foreach ($logs as $log) {
            // سطر بلا آية بداية يعني "بلغ الآية كذا" — أي من أول السورة إليها،
            // وهو شكل كل السجلّات المهاجَرة من student_data القديم (S7).
            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            if ($to < $from) {
                continue;
            }

            $ranges[$log->surah_id][] = [$from, $to];
        }

        $coverage = [];

        foreach ($ranges as $surahId => $surahRanges) {
            $surah = $this->surahs()->get($surahId);

            if ($surah === null) {
                continue;
            }

            $coverage[$surahId] = min(
                $this->mergedLength($surahRanges),
                $surah->ayah_count
            );
        }

        return $coverage;
    }

    /**
     * أرضية الحفظ (S16): "آخر سورة أتمّها الطالب قبل الانضمام" — تُطبَّق كحدّ
     * أدنى للتغطية على كل سورة تسبقها في تسلسل الحفظ (أو تساويها)، لا كسجلّ
     * مُلفَّق. max لا استبدال: سجلّ فعلي يتجاوز الأرضية يبقى كما هو.
     *
     * @param  array<int, int>  $coverage
     */
    private function applyBaseline(array &$coverage, Student $student): void
    {
        $baselineId = $student->quran_baseline_surah_id;

        if ($baselineId === null) {
            return;
        }

        $baselineSurah = $this->surahs()->get($baselineId);

        if ($baselineSurah === null || $baselineSurah->excluded_from_progress || $baselineSurah->memorization_order === null) {
            return;
        }

        foreach ($this->surahs() as $surah) {
            if ($surah->excluded_from_progress || $surah->memorization_order === null) {
                continue;
            }

            if ($surah->memorization_order > $baselineSurah->memorization_order) {
                continue;
            }

            $coverage[$surah->id] = max($coverage[$surah->id] ?? 0, $surah->ayah_count);
        }
    }

    /**
     * نسبة الحفظ من 100 — الرقم المعروض في كل شاشة.
     */
    public function percentage(Student $student): float
    {
        return $this->percentageFromCoverage($this->coverage($student));
    }

    /**
     * @param  array<int, int>  $coverage
     */
    public function percentageFromCoverage(array $coverage): float
    {
        $steps = 0.0;

        foreach ($coverage as $surahId => $ayahCovered) {
            $surah = $this->surahs()->get($surahId);

            if ($surah === null || $surah->excluded_from_progress || $surah->ayah_count < 1) {
                continue;
            }

            $steps += min(1.0, $ayahCovered / $surah->ayah_count);
        }

        return round(min($steps / Surah::COUNTABLE_COUNT * 100, 100), 1);
    }

    /**
     * السور المكتملة (كل آياتها محفوظة) — أساس عدّاد "أتمّ كذا سورة".
     *
     * @return Collection<int, Surah>
     */
    public function completedSurahs(Student $student): Collection
    {
        $coverage = $this->coverage($student);

        return $this->surahs()
            ->filter(function (Surah $surah) use ($coverage) {
                return ! $surah->excluded_from_progress
                    && ($coverage[$surah->id] ?? 0) >= $surah->ayah_count;
            })
            ->sortBy('memorization_order')
            ->values();
    }

    /**
     * أبعد سورة بلغها الطالب في تسلسل الحفظ (مكتملة أو قيد الحفظ) — تُقرأ
     * تربويًا أوضح من النسبة وحدها: "وصل إلى سورة كذا".
     */
    public function furthestSurah(Student $student): ?Surah
    {
        $coverage = $this->coverage($student);

        return $this->surahs()
            ->filter(fn (Surah $s) => ! $s->excluded_from_progress && ($coverage[$s->id] ?? 0) > 0)
            ->sortByDesc('memorization_order')
            ->first();
    }

    /**
     * منحنى التقدّم: نسبة تراكمية عند كل تاريخ سُجِّل فيه حفظ جديد.
     *
     * يُبنى بإعادة تشغيل السجلّ زمنيًا وتجميع التغطية تدريجيًا — فالنقطة على
     * المنحنى هي نسبة الطالب في ذلك اليوم فعلًا، لا رقم مشتقّ من حالته اليوم.
     *
     * @return Collection<int, array{date: string, percent: float}>
     */
    public function timeline(Student $student): Collection
    {
        $logs = $student->recitationLogs()
            ->reorder('logged_at')
            ->orderBy('id')
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->get(['surah_id', 'from_ayah', 'to_ayah', 'logged_at']);

        $ranges = [];
        $points = [];

        foreach ($logs as $log) {
            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            if ($to >= $from) {
                $ranges[$log->surah_id][] = [$from, $to];
            }

            $coverage = [];

            foreach ($ranges as $surahId => $surahRanges) {
                $surah = $this->surahs()->get($surahId);

                if ($surah !== null) {
                    $coverage[$surahId] = min($this->mergedLength($surahRanges), $surah->ayah_count);
                }
            }

            // يوم واحد قد يحمل عدّة أسطر — تُبقى آخر نسبة في اليوم لا كل سطر،
            // فالمنحنى يوميّ لا لكل إدخال.
            $points[optional($log->logged_at)->toDateString()] = $this->percentageFromCoverage($coverage);
        }

        return collect($points)
            ->map(fn (float $percent, string $date) => ['date' => $date, 'percent' => $percent])
            ->values();
    }

    /**
     * طول اتحاد مديات متداخلة: [[1,20],[15,40]] ⇒ 40 لا 46.
     *
     * @param  array<int, array{0: int, 1: int}>  $ranges
     */
    private function mergedLength(array $ranges): int
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $total = 0;
        $currentStart = null;
        $currentEnd = null;

        foreach ($ranges as [$start, $end]) {
            if ($currentStart === null) {
                [$currentStart, $currentEnd] = [$start, $end];
                continue;
            }

            // +1 لأن [1,5] و[6,9] متجاوران بلا فجوة فعلية بينهما.
            if ($start <= $currentEnd + 1) {
                $currentEnd = max($currentEnd, $end);
                continue;
            }

            $total += $currentEnd - $currentStart + 1;
            [$currentStart, $currentEnd] = [$start, $end];
        }

        if ($currentStart !== null) {
            $total += $currentEnd - $currentStart + 1;
        }

        return $total;
    }

    /**
     * مرجع السور مرّة واحدة لكل نسخة من الخدمة — 114 صفًّا ثابتة تُقرأ مرارًا
     * في كل حساب، فتحميلها لكل طالب على حدة في لوحة فيها 50 طالبًا يعني 50
     * استعلامًا بلا داعٍ.
     *
     * @return Collection<int, Surah>
     */
    private function surahs(): Collection
    {
        return $this->surahCache ??= Surah::all()->keyBy('id');
    }
}
