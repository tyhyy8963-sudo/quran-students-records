<?php

namespace App\Support;

use App\Models\PoemRecitationLog;
use App\Models\Poem;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * حساب نسبة حفظ/مراجعة متن (S15، وسِّع في S25) — نفس فلسفة MemorizationProgress/
 * ReviewProgress لكن الوحدة "بيت" لا "سورة"/"ربع": متن واحد، فلا حاجة لمجموع
 * خطوات على عدّة وحدات ولا وزن أرباع — نسبة خام بسيطة كافية.
 *
 * نسبة الطالب لمتن (لنوع معيّن، حفظ أو مراجعة) = (عدد الأبيات المغطّاة لذلك
 * النوع) ÷ (عدد أبيات المتن) × 100، بعد اتحاد مديات from_bayt..to_bayt
 * المتداخلة (نفس مشكلة تسجيل متن طويل عبر عدّة جلسات متداخلة كما في السور).
 *
 * (S25 — بطلب صريح من يحيى: "أبغى للمتن منحيين زي ما للدرس منحنى وللمراجعة
 * منحنى"): كان هذا الصنف يحسب نوع "حفظ" فقط (مقفلاً في الاستعلام). صار كل من
 * coverage()/percentage()/timeline() يقبل معامل $type اختياريًا (افتراضه
 * "حفظ" فلا ينكسر أي استدعاء قائم يعتمد على السلوك القديم)، فيُستعمَل الصنف
 * نفسه لمنحنى الحفظ ومنحنى المراجعة معًا — تمامًا كما بقيت MemorizationProgress
 * وReviewProgress صنفين منفصلين للقرآن، لكن هنا صنف واحد يكفي لأن الفرق بين
 * النوعين هو فقط شرط `type` في الاستعلام لا خوارزمية مختلفة (لا انسياب تلقائي
 * ولا وزن أرباع في أيّ من الحالتين).
 *
 * (S24 — بطلب صريح من يحيى): كانت "أرضية المتن" اليدوية (S16، student_poem_
 * baselines) تُطبَّق هنا كحدّ أدنى للتغطية. أُلغيت نهائيًا لنفس السبب الذي
 * أُلغيت به أرضية حفظ القرآن اليدوية سابقًا — التغطية تُحتسَب الآن حصرًا من
 * مديات from_bayt..to_bayt الفعلية المسجَّلة، بلا أي إدخال يدوي لـ"ما قبل
 * الانضمام".
 */
class PoemProgress
{
    /** @var array<string, int> تغطية محسوبة مسبقًا: "{studentId}:{poemId}:{type}" ⇐ أبيات */
    private array $coverageCache = [];

    public function coverage(Student $student, Poem $poem, string $type = 'حفظ'): int
    {
        $key = $this->cacheKey($student, $poem, $type);

        if (isset($this->coverageCache[$key])) {
            return $this->coverageCache[$key];
        }

        $logs = PoemRecitationLog::query()
            ->where('student_id', (int) $student->student_id)
            ->where('poem_id', $poem->id)
            ->where('type', $type)
            ->get(['from_bayt', 'to_bayt']);

        $covered = min($this->mergedLength($this->rangesFromLogs($logs)), $poem->bayt_count);

        return $this->coverageCache[$key] = $covered;
    }

    public function percentage(Student $student, Poem $poem, string $type = 'حفظ'): float
    {
        if ($poem->bayt_count < 1) {
            return 0.0;
        }

        return round(min($this->coverage($student, $poem, $type) / $poem->bayt_count * 100, 100), 1);
    }

    /**
     * تُسقِط تغطية النوعين معًا (حفظ ومراجعة) لهذا (طالب، متن) — لا فرق عمليًا
     * أيّ نوع تغيَّر فعلًا: الخدمة singleton والاستدعاء يأتي بعد أي إضافة/حذف
     * سطر بصرف النظر عن نوعه، فالأسلم إسقاط الاثنين معًا بدل تتبّع أيّهما
     * تغيَّر تحديدًا.
     */
    public function forget(Student $student, Poem $poem): void
    {
        unset(
            $this->coverageCache[$this->cacheKey($student, $poem, 'حفظ')],
            $this->coverageCache[$this->cacheKey($student, $poem, 'مراجعة')]
        );
    }

    /**
     * منحنى تقدّم متن تاريخي (S16، وسِّع في S25 لنوع "مراجعة" أيضًا) — نظير
     * MemorizationProgress::timeline()/ReviewProgress::timeline() على وحدة
     * "بيت" لمتن واحد: نسبة تراكمية عند كل تاريخ سُجِّل فيه هذا النوع
     * (حفظ أو مراجعة) لهذا المتن تحديدًا. (S24) لم تعد الأرضية اليدوية
     * تُطبَّق هنا — راجع تعليق الصنف أعلاه.
     *
     * @return Collection<int, array{date: string, percent: float}>
     */
    public function timeline(Student $student, Poem $poem, string $type = 'حفظ'): Collection
    {
        $logs = $student->poemRecitationLogs()
            ->reorder('logged_at')
            ->orderBy('id')
            ->where('poem_id', $poem->id)
            ->where('type', $type)
            ->get(['from_bayt', 'to_bayt', 'logged_at']);

        $ranges = [];
        $points = [];

        foreach ($logs as $log) {
            $from = max(1, (int) ($log->from_bayt ?: 1));
            $to = (int) $log->to_bayt;

            if ($to >= $from) {
                $ranges[] = [$from, $to];
            }

            $covered = min($this->mergedLength($ranges), $poem->bayt_count);
            $percent = $poem->bayt_count < 1 ? 0.0 : round(min($covered / $poem->bayt_count * 100, 100), 1);

            // يوم واحد قد يحمل عدّة أسطر — تُبقى آخر نسبة في اليوم لا كل سطر.
            $points[optional($log->logged_at)->toDateString()] = $percent;
        }

        // (S25 — نفس تعديل MemorizationProgress::timeline()/ReviewProgress::
        // timeline()، بطلب يحيى: صعوبة قراءة "2026-04-20" بسرعة) — label
        // عربي مقروء للعرض، date يبقى بصيغته الأصلية لمنطق الفرز/التجميع في
        // الواجهة الأمامية.
        return collect($points)
            ->map(fn (float $percent, string $date) => [
                'date'    => $date,
                'label'   => \Carbon\Carbon::parse($date)->translatedFormat('j F Y'),
                'percent' => $percent,
            ])
            ->values();
    }

    private function cacheKey(Student $student, Poem $poem, string $type): string
    {
        return $student->student_id.':'.$poem->id.':'.$type;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PoemRecitationLog>  $logs
     * @return array<int, array{0: int, 1: int}>
     */
    private function rangesFromLogs($logs): array
    {
        $ranges = [];

        foreach ($logs as $log) {
            $from = max(1, (int) ($log->from_bayt ?: 1));
            $to = (int) $log->to_bayt;

            if ($to >= $from) {
                $ranges[] = [$from, $to];
            }
        }

        return $ranges;
    }

    /**
     * طول اتحاد مديات متداخلة — نفس خوارزمية MemorizationProgress::mergedLength
     * (منسوخة هنا: هناك خاصة بسورة واحدة داخل حلقة، هنا القصيدة كلّها وحدة).
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
}
