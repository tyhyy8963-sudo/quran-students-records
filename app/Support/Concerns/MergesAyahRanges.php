<?php

namespace App\Support\Concerns;

/**
 * دمج وتقاطع مديات على محور آيات مطلق واحد (1..6236) — منطق مشترك بين
 * ReviewProgress (نسبة المراجعة) وMemorizationProgress (نسبة الحفظ، منذ
 * التصحيح الثاني: موزونة بالأرباع الـ240 لا بعدد السور)، فكلتاهما تحتاج نفس
 * عملية "ادمج مديات متداخلة ثم قِس تقاطعها مع مدى ثابت (الربع)". نسخة واحدة
 * مُختبَرة عبر ReviewProgressTest أصلًا بدل تكرار نفس الخوارزمية في صنفين.
 */
trait MergesAyahRanges
{
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
     * طول تقاطع مديات مُدمَجة مع مدى واحد (مدى الربع مثلًا).
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
}
