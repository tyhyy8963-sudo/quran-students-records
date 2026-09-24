<?php

namespace App\Support;

use App\Models\Quarter;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use App\Support\Concerns\MergesAyahRanges;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * نسبة الحفظ بترتيب الحفظ المعكوس، موزونة بالأرباع الـ240 لا بعدد السور
 * (تصحيح ثانٍ بعد S15).
 *
 * ═══ السلّم: من الناس صعودًا إلى البقرة ═══
 * ترتيب المصحف يبدأ بالفاتحة، لكن الحفظ يبدأ من آخره: الناس أولًا صعودًا إلى
 * البقرة. لكل سورة (عدا الفاتحة) رتبة في هذا التسلسل مخزَّنة في العمود
 * memorization_order: الناس = 1 … البقرة = 113. هذا الترتيب نفسه يبقى أساس
 * تحديد "أبعد سورة بلغها الطالب" (furthestSurah) وقائمة السور المكتملة
 * (completedSurahs) — كلاهما معلومة عرضية لا تتأثر بما يلي.
 *
 * ═══ الانسياب التلقائي من أبعد خطوة (S15، مصحَّح) — لا يزال كما هو ═══
 * تسجيل حفظ في سورة متأخرة في الترتيب يعني ضمنيًا أن كل خطوة أسبق منها أُتمّت
 * فعلًا — فالمعلّم لا يحتاج شيئًا غير تسجيل موضع الطالب الحالي كأي سجلّ "حفظ"
 * عادي، بلا أي إدخال يدوي منفصل ("أرضية"). التطبيق: "أبعد سورة" = أدنى رقم
 * سورة (mushaf) عليه سجلّ "حفظ" فعلي > 0 (لأن الترتيب معكوس: كل ما بعدها في
 * ترتيب المصحف كان يجب حفظه أولًا). كل سورة بعدها في ترتيب المصحف (رقمها
 * أكبر) تُحتسب كاملة تلقائيًا؛ السورة الأبعد نفسها تبقى بتغطيتها الفعلية
 * المسجَّلة فقط — لا تُقفز إلى كاملة قبل اكتمالها فعلًا.
 *
 * ═══ لماذا الأرباع لا عدد السور (التصحيح الثاني) ═══
 * الصيغة الأولى (S14/S15) اعتبرت كل سورة "خطوة" واحدة متساوية القيمة من 113،
 * بصرف النظر عن طولها الفعلي. هذا يُنتج رقمًا كاذبًا تربويًا: طالب أتمّ 89
 * سورة من جزأي عمّ وتبارك (سور قصيرة جدًا) يظهر عنده ~79%، بينما ما تبقّى من
 * سور طويلة (كالبقرة وآل عمران والشعراء) يمثّل أغلب حجم المصحف الفعلي (نحو
 * 60% من صفحاته) ولم يُحفَظ منه شيء بعد — النسبة الحقيقية بحجم المصحف أقرب
 * إلى 40%. نفس المشكلة التي عولجت في ReviewProgress بوزن الأرباع الـ240 بدل
 * عدد الآيات الخام (سورة الشعراء 227 آية ≈ نصف جزء فقط، بينما 141 آية من أول
 * البقرة = جزء كامل) تنطبق هنا حرفيًا. الحل: نفس الأرباع الـ240 بالضبط.
 *
 * ═══ الصيغة ═══
 * تُبنى مديات الطالب المطلقة (1..6236) من مصدرين، تُدمَجان معًا:
 *  1. المدى الفعلي لكل سجلّ "حفظ" (سورة، من آية، إلى آية) — يشمل تغطية
 *     السورة الأبعد الجزئية كما هي حرفيًا.
 *  2. مدى الانسياب: من بداية السورة التالية لأبعد سورة (بترتيب المصحف) إلى
 *     آخر آية في القرآن — يمثّل كل ما "قبلها" في ترتيب الحفظ.
 * لكل ربع من الـ240 مدى مطلق ثابت [بدايته، بداية التالي − 1]؛ تغطيته =
 * تقاطع مدى الطالب المُدمَج معه ÷ طول الربع "القابل للعدّ" (بين 0 و1).
 *
 *     نسبة الحفظ = (مجموع تغطية كل ربع) ÷ 240 × 100
 *
 * ═══ الفاتحة ═══
 * الربع الأول فقط يحوي آيات الفاتحة السبع مختلطة مع أول 25 آية من البقرة
 * (الربع الثاني يبدأ عند البقرة:26)، فطول ذلك الربع "القابل للعدّ" هنا تحديدًا
 * يُنقَص بمقدار 7 آيات — الفاتحة نفسها مستثناة كليًا: لا تدخل مدى الطالب (تُهمَل
 * سجلّاتها كليًا عند البناء) ولا في تحديد "أبعد سورة"، تمامًا كما كانت قبل هذا
 * التصحيح.
 *
 * ═══ نوع "حفظ" وحده ═══
 * "مراجعة" و"تسميع" أحداث على مقطع سبق حفظه، لا تقدّم جديد — مستبعدة من هذا
 * الحساب كليًا (لا تدخل مدى الطالب ولا تُفعِّل الانسياب التلقائي أبدًا).
 *
 * ═══ منطق الدمج/التقاطع مشترك مع ReviewProgress ═══
 * راجع App\Support\Concerns\MergesAyahRanges — النسخة نفسها تستعملها نسبة
 * المراجعة أيضًا، فلا خوارزميتان قد تنحرفان عن بعضهما لاحقًا.
 */
class MemorizationProgress
{
    use MergesAyahRanges;

    /** @var Collection<int, Surah>|null */
    private ?Collection $surahCache = null;

    /** @var array<int, int>|null بداية كل سورة المطلقة: [surah_id => بدايتها] */
    private ?array $surahStartCache = null;

    /** @var Collection<int, Quarter>|null مرتَّبة برقم الربع */
    private ?Collection $quartersCache = null;

    /** @var array<int, array<int, int>> تغطية كل سورة بالآيات (بعد الانسياب) لكل طالب — أساس completedSurahs/furthestSurah فقط */
    private array $coverageCache = [];

    /** @var array<int, float> نسبة الحفظ الموزونة بالأرباع، مخزَّنة لكل طالب */
    private array $percentageCache = [];

    /**
     * تغطية كل سورة بالآيات بعد الانسياب التلقائي: [surah_id => عدد الآيات
     * المحفوظة]. لا تُستعمَل لحساب النسبة (راجع percentage())، بل أساس
     * completedSurahs() وfurthestSurah() فقط — معلومة "أين وصل الطالب" عرضية
     * منفصلة عن رقم النسبة.
     *
     * @return array<int, int>
     */
    public function coverage(Student $student): array
    {
        $studentId = (int) $student->student_id;

        if (isset($this->coverageCache[$studentId])) {
            return $this->coverageCache[$studentId];
        }

        $logs = $this->memorizationLogsFor($studentId);
        $coverage = $this->coverageFromLogs($logs);
        $this->applyCascade($coverage);

        return $this->coverageCache[$studentId] = $coverage;
    }

    /**
     * نسبة الحفظ من 100، موزونة بالأرباع الـ240 — الرقم المعروض في كل شاشة.
     */
    public function percentage(Student $student): float
    {
        $studentId = (int) $student->student_id;

        if (isset($this->percentageCache[$studentId])) {
            return $this->percentageCache[$studentId];
        }

        $logs = $this->memorizationLogsFor($studentId);

        return $this->percentageCache[$studentId] = $this->percentageFromLogs($logs, $this->coverage($student));
    }

    /**
     * تحميل تغطية ونسبة مجموعة طلاب باستعلام واحد.
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
            ->reject(fn (Student $s) => isset($this->coverageCache[(int) $s->student_id])
                && isset($this->percentageCache[(int) $s->student_id]));

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
            $logs = $logsByStudent->get($id) ?? collect();

            $coverage = $this->coverageFromLogs($logs);
            $this->applyCascade($coverage);
            $this->coverageCache[$id] = $coverage;

            $this->percentageCache[$id] = $this->percentageFromLogs($logs, $coverage);
        }
    }

    /**
     * نسبة الحفظ "كما كانت" حتى تاريخ سابق، لمجموعة طلاب — أساس مؤشّر الاتجاه
     * (v2) لبطاقة "متوسّط نسبة التقدّم". لا خوارزمية موازية جديدة إطلاقًا: نفس
     * coverageFromLogs()/applyCascade()/percentageFromLogs() المستعملة في
     * percentage()/warmFor()/timeline() بالضبط، فقط على سجلّات "حفظ" المسجَّلة
     * حتى $asOf (تاريخًا) دون ما بعده — بمنطق الانسياب نفسه، فطالب بلغ سورة
     * متأخّرة بعد $asOf لا يُحتسَب هنا كأنه بلغها فعلًا في ذلك التاريخ.
     * استعلام واحد لكل الطلاب (لا استعلام لكل طالب)، بنفس مبدأ warmFor()
     * تمامًا. طالب بلا أي سجلّ حفظ قبل $asOf غائب عن المصفوفة المُعادة (لا
     * صفر ضمنيًا) — القرار متروك للمستدعي (صفر فعلي، أو استبعاد من المتوسّط).
     *
     * whereDate لا مقارنة خام على logged_at للسبب نفسه الموثَّق في
     * ReportController::periodRows()/AttendanceController::store(): كاست
     * "date" يخزّن وقتًا كاملاً خلف التاريخ على SQLite، فمقارنة خام قد تستثني
     * سطور اليوم الأخير من المدى.
     *
     * @param  iterable<Student>  $students
     * @return array<int, float> [student_id => نسبة الحفظ في ذلك التاريخ]
     */
    public function percentagesAsOf(iterable $students, Carbon $asOf): array
    {
        $ids = collect($students)->map(fn (Student $s) => (int) $s->student_id)->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $logsByStudent = RecitationLog::query()
            ->whereIn('student_id', $ids)
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->whereDate('logged_at', '<=', $asOf->toDateString())
            ->get(['student_id', 'surah_id', 'from_ayah', 'to_ayah'])
            ->groupBy('student_id');

        $result = [];

        foreach ($logsByStudent as $studentId => $logs) {
            $coverage = $this->coverageFromLogs($logs);
            $this->applyCascade($coverage);
            $result[(int) $studentId] = $this->percentageFromLogs($logs, $coverage);
        }

        return $result;
    }

    /**
     * إسقاط ما هو مخزَّن لطالب بعد تغيير سجلّه في نفس الطلب (إضافة/حذف سطر)
     * — الخدمة singleton، فبلا هذا قد يُقرأ رقم ما قبل التغيير.
     */
    public function forget(Student $student): void
    {
        $studentId = (int) $student->student_id;

        unset($this->coverageCache[$studentId], $this->percentageCache[$studentId]);
    }

    /**
     * سجلّات "حفظ" الخام لطالب واحد — مصدر مشترك لـ coverage() وpercentage()
     * حتى لا يُكتَب نفس الاستعلام مرّتين.
     *
     * @return Collection<int, RecitationLog>
     */
    private function memorizationLogsFor(int $studentId): Collection
    {
        return RecitationLog::query()
            ->where('student_id', $studentId)
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->get(['student_id', 'surah_id', 'from_ayah', 'to_ayah']);
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
                $this->mergedSurahLength($surahRanges),
                $surah->ayah_count
            );
        }

        return $coverage;
    }

    /**
     * الانسياب التلقائي — راجع تعليق الصنف أعلاه للتفصيل الكامل. يُعدِّل
     * $coverage في مكانه: كل خطوة أسبق من "أبعد خطوة فعلية" تُصبح كاملة،
     * والخطوة الأبعد نفسها لا تُمَسّ. يُستعمَل لبناء coverage()/completedSurahs()
     * فقط — percentage() يبني مداه المطلق بمنطق مواز (globalRangesFromLogs())
     * لأن السورة الواحدة قد تمتدّ عبر عدّة أرباع، فرقمها الإجمالي (coverage()،
     * عدد آيات) لا يكفي وحده لمعرفة أي الأرباع بالضبط تغطّيها.
     *
     * @param  array<int, int>  $coverage
     */
    private function applyCascade(array &$coverage): void
    {
        $furthestOrder = null;

        foreach ($coverage as $surahId => $ayahCovered) {
            if ($ayahCovered <= 0) {
                continue;
            }

            $surah = $this->surahs()->get($surahId);

            if ($surah === null || $surah->excluded_from_progress || $surah->memorization_order === null) {
                continue;
            }

            if ($furthestOrder === null || $surah->memorization_order > $furthestOrder) {
                $furthestOrder = $surah->memorization_order;
            }
        }

        if ($furthestOrder === null) {
            return;
        }

        foreach ($this->surahs() as $surah) {
            if ($surah->excluded_from_progress || $surah->memorization_order === null) {
                continue;
            }

            if ($surah->memorization_order >= $furthestOrder) {
                continue;
            }

            $coverage[$surah->id] = max($coverage[$surah->id] ?? 0, $surah->ayah_count);
        }
    }

    /**
     * السور المكتملة (كل آياتها محفوظة، فعليًا أو بالانسياب التلقائي) — معلومة
     * عرضية ("89 من 113 سورة مكتملة")، منفصلة عن نسبة الحفظ نفسها منذ صارت
     * موزونة بالأرباع لا بعدد السور.
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
     * تربويًا أوضح من النسبة وحدها: "وصل إلى سورة كذا". محسوبة من التغطية
     * الخام (قبل الانسياب) ضمنيًا: الانسياب لا يضيف أبدًا سورة برتبة أعلى من
     * الأبعد الفعلية (هو مبنيّ عليها)، فالنتيجة واحدة سواء قُرئت قبله أو بعده.
     */
    public function furthestSurah(Student $student): ?Surah
    {
        return $this->furthestSurahFromCoverage($this->coverage($student));
    }

    /**
     * @param  array<int, int>  $coverage
     */
    private function furthestSurahFromCoverage(array $coverage): ?Surah
    {
        return $this->surahs()
            ->filter(fn (Surah $s) => ! $s->excluded_from_progress && ($coverage[$s->id] ?? 0) > 0)
            ->sortByDesc('memorization_order')
            ->first();
    }

    /**
     * نسبة الحفظ الموزونة بالأرباع من سجلّات "حفظ" خام + تغطية سور (لتحديد
     * أبعد سورة فقط) — طبقة مشتركة بين percentage() وwarmFor() وtimeline().
     *
     * @param  Collection<int, RecitationLog>  $logs
     * @param  array<int, int>  $coverage
     */
    private function percentageFromLogs(Collection $logs, array $coverage): float
    {
        return $this->percentageFromRanges($this->globalRangesFromLogs($logs, $coverage));
    }

    /**
     * يبني مدى الطالب المطلق (1..6236) من مصدرين مُدمَجين: المدى الفعلي لكل
     * سجلّ (يشمل تغطية أبعد سورة الجزئية كما هي)، ومدى الانسياب (من بداية
     * السورة التالية لأبعد سورة إلى آخر آية في القرآن). راجع تعليق الصنف
     * أعلاه للتفصيل الكامل.
     *
     * @param  Collection<int, RecitationLog>  $logs
     * @param  array<int, int>  $coverage
     * @return array<int, array{0: int, 1: int}>
     */
    private function globalRangesFromLogs(Collection $logs, array $coverage): array
    {
        $starts = $this->surahStarts();
        $ranges = [];

        foreach ($logs as $log) {
            $surah = $this->surahs()->get($log->surah_id);

            // الفاتحة مستثناة كليًا: لا تدخل مدى الطالب مهما سُجِّلت.
            if ($surah === null || $surah->excluded_from_progress) {
                continue;
            }

            $start = $starts[$surah->id] ?? null;

            if ($start === null) {
                continue;
            }

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            if ($to < $from) {
                continue;
            }

            $ranges[] = [$start + $from - 1, $start + $to - 1];
        }

        $furthest = $this->furthestSurahFromCoverage($coverage);

        if ($furthest !== null) {
            $next = $this->surahs()->firstWhere('number', $furthest->number + 1);

            if ($next !== null) {
                $ranges[] = [$starts[$next->id], $this->totalAyat()];
            }
        }

        return $this->mergeRanges($ranges);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $mergedRanges
     */
    private function percentageFromRanges(array $mergedRanges): float
    {
        $steps = 0.0;

        foreach ($this->quarters() as $quarter) {
            if ($quarter->countable_length < 1) {
                continue;
            }

            $covered = $this->intersectionLength($mergedRanges, $quarter->range);
            $steps += min(1.0, $covered / $quarter->countable_length);
        }

        return round(min($steps / Quarter::COUNT * 100, 100), 1);
    }

    /**
     * منحنى التقدّم: نسبة تراكمية عند كل تاريخ سُجِّل فيه حفظ جديد.
     *
     * يُبنى بإعادة تشغيل السجلّ زمنيًا: عند كل سطر تُحسَب نسبة الأرباع من كل
     * ما سُجِّل حتى تلك اللحظة (بما فيها الانسياب من أبعد سورة وصلها الطالب
     * حتى ذلك التاريخ) — فالنقطة على المنحنى هي نسبة الطالب في ذلك اليوم
     * فعلًا بنفس القاعدة المستعملة للرقم الحالي.
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

        $seenLogs = collect();
        $surahRanges = [];
        $points = [];

        foreach ($logs as $log) {
            $seenLogs->push($log);

            $from = max(1, (int) ($log->from_ayah ?: 1));
            $to = (int) $log->to_ayah;

            if ($to >= $from) {
                $surahRanges[$log->surah_id][] = [$from, $to];
            }

            $coverage = [];

            foreach ($surahRanges as $surahId => $ranges) {
                $surah = $this->surahs()->get($surahId);

                if ($surah !== null) {
                    $coverage[$surahId] = min($this->mergedSurahLength($ranges), $surah->ayah_count);
                }
            }

            // يوم واحد قد يحمل عدّة أسطر — تُبقى آخر نسبة في اليوم لا كل سطر،
            // فالمنحنى يوميّ لا لكل إدخال.
            $points[optional($log->logged_at)->toDateString()] = $this->percentageFromLogs($seenLogs, $coverage);
        }

        return collect($points)
            ->map(fn (float $percent, string $date) => ['date' => $date, 'percent' => $percent])
            ->values();
    }

    /**
     * طول اتحاد مديات متداخلة داخل سورة واحدة: [[1,20],[15,40]] ⇒ 40 لا 46.
     * (اسم مُفرَّق عن mergeRanges() في MergesAyahRanges عمدًا: هذه تُرجع طولًا
     * واحدًا لمديات محلّية داخل سورة، تلك تُرجع قائمة مديات مطلقة عبر القرآن.)
     *
     * @param  array<int, array{0: int, 1: int}>  $ranges
     */
    private function mergedSurahLength(array $ranges): int
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

    /** إجمالي آيات القرآن — نفس Student::TOTAL_AYAT، عبر دالة لا يستورد بها هذا الصنف Student لغرض ثابت واحد. */
    private function totalAyat(): int
    {
        return Student::TOTAL_AYAT;
    }

    /**
     * بداية كل سورة على محور الآيات المطلق (1..6236)، من ayah_count التراكمي.
     * (نفس منطق ReviewProgress::surahStarts() تمامًا — لا استخراج إلى تعريف
     * مشترك لأنها 6 أسطر تعتمد على surahs() الخاصة بكل صنف بترتيبها الخاص.)
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
     * الأرباع الـ240 مرتَّبة برقم الربع، ومعها مداها المطلق [بداية، نهاية]،
     * وطولها "القابل للعدّ" (طول الربع ناقص أي تداخل مع آيات الفاتحة السبع —
     * لا يقع هذا إلا في الربع الأول، راجع تعليق الصنف).
     *
     * @return Collection<int, object{quarter_number: int, range: array{0: int, 1: int}, countable_length: int}>
     */
    private function quarters(): Collection
    {
        if ($this->quartersCache !== null) {
            return $this->quartersCache;
        }

        $rows = Quarter::query()->orderBy('quarter_number')->get(['quarter_number', 'start_global_ayah']);
        $totalAyat = $this->totalAyat();
        $fatihaAyat = Surah::where('excluded_from_progress', true)->sum('ayah_count');

        $withRange = $rows->map(function ($quarter, $index) use ($rows, $totalAyat, $fatihaAyat) {
            $next = $rows->get($index + 1);
            $end = $next !== null ? $next->start_global_ayah - 1 : $totalAyat;
            $length = $end - $quarter->start_global_ayah + 1;

            $fatihaOverlap = max(0, min($end, $fatihaAyat) - max($quarter->start_global_ayah, 1) + 1);

            return (object) [
                'quarter_number'   => $quarter->quarter_number,
                'range'            => [$quarter->start_global_ayah, $end],
                'countable_length' => $length - $fatihaOverlap,
            ];
        });

        return $this->quartersCache = $withRange;
    }

    /**
     * مرجع السور مرّة واحدة لكل نسخة من الخدمة — 114 صفًّا ثابتة تُقرأ مرارًا
     * في كل حساب، فتحميلها لكل طالب على حدة في لوحة فيها 50 طالبًا يعني 50
     * استعلامًا بلا داعٍ. مرتَّبة برقم السورة صراحةً: عليها يعتمد الترتيب
     * التراكمي الصحيح في surahStarts().
     *
     * @return Collection<int, Surah>
     */
    private function surahs(): Collection
    {
        return $this->surahCache ??= Surah::query()->orderBy('number')->get()->keyBy('id');
    }
}
