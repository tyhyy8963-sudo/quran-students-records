<?php

namespace App\Support;

use App\Models\PoemRecitationLog;
use App\Models\Poem;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * حساب نسبة حفظ متن (S15) — نفس فلسفة MemorizationProgress لكن الوحدة "بيت"
 * لا "سورة": متن واحد، فلا حاجة لمجموع خطوات على عدّة وحدات.
 *
 * نسبة الطالب لمتن = (عدد الأبيات المحفوظة) ÷ (عدد أبيات المتن) × 100، بعد
 * اتحاد مديات from_bayt..to_bayt المتداخلة (نفس مشكلة تسجيل متن طويل عبر عدّة
 * جلسات متداخلة كما في السور).
 *
 * (S24 — بطلب صريح من يحيى): كانت "أرضية المتن" اليدوية (S16، student_poem_
 * baselines) تُطبَّق هنا كحدّ أدنى للتغطية. أُلغيت نهائيًا لنفس السبب الذي
 * أُلغيت به أرضية حفظ القرآن اليدوية سابقًا — التغطية تُحتسَب الآن حصرًا من
 * مديات from_bayt..to_bayt الفعلية المسجَّلة، بلا أي إدخال يدوي لـ"ما قبل
 * الانضمام".
 */
class PoemProgress
{
    /** @var array<string, int> تغطية محسوبة مسبقًا: "{studentId}:{poemId}" ⇐ أبيات */
    private array $coverageCache = [];

    public function coverage(Student $student, Poem $poem): int
    {
        $key = $this->cacheKey($student, $poem);

        if (isset($this->coverageCache[$key])) {
            return $this->coverageCache[$key];
        }

        $logs = PoemRecitationLog::query()
            ->where('student_id', (int) $student->student_id)
            ->where('poem_id', $poem->id)
            ->where('type', 'حفظ')
            ->get(['from_bayt', 'to_bayt']);

        $covered = min($this->mergedLength($this->rangesFromLogs($logs)), $poem->bayt_count);

        return $this->coverageCache[$key] = $covered;
    }

    public function percentage(Student $student, Poem $poem): float
    {
        if ($poem->bayt_count < 1) {
            return 0.0;
        }

        return round(min($this->coverage($student, $poem) / $poem->bayt_count * 100, 100), 1);
    }

    public function forget(Student $student, Poem $poem): void
    {
        unset($this->coverageCache[$this->cacheKey($student, $poem)]);
    }

    /**
     * منحنى حفظ متن تاريخي (S16) — نظير MemorizationProgress::timeline() على
     * وحدة "بيت" لمتن واحد: نسبة تراكمية عند كل تاريخ سُجِّل فيه حفظ جديد لهذا
     * المتن تحديدًا. (S24) لم تعد الأرضية اليدوية تُطبَّق هنا — راجع تعليق
     * الصنف أعلاه.
     *
     * @return Collection<int, array{date: string, percent: float}>
     */
    public function timeline(Student $student, Poem $poem): Collection
    {
        $logs = $student->poemRecitationLogs()
            ->reorder('logged_at')
            ->orderBy('id')
            ->where('poem_id', $poem->id)
            ->where('type', 'حفظ')
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

        return collect($points)
            ->map(fn (float $percent, string $date) => ['date' => $date, 'percent' => $percent])
            ->values();
    }

    private function cacheKey(Student $student, Poem $poem): string
    {
        return $student->student_id.':'.$poem->id;
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
