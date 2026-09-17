<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * شكل استجابة موحّد للطالب (B-06).
 *
 * منذ S7، "السورة/الآية" لم تعودا حقلين على الطالب بل مُشتقّتين من آخر
 * سجلّ "حفظ" في recitation_logs — المصدر الوحيد للحقيقة هو السجلّ الزمني،
 * لا عمود يُستبدَل في كل مرة.
 */
class StudentResource extends JsonResource
{
    public function toArray($request): array
    {
        $log = $this->latestMemorizationLog;

        return [
            'id'               => $this->student_id,
            'student_name'     => $this->student_name,
            'circle'           => $this->circle ? [
                'id'   => $this->circle->id,
                'name' => $this->circle->name,
            ] : null,
            'current_position' => $log ? [
                'surah_id'   => $log->surah_id,
                'surah_name' => $log->surah?->name,
                'ayah'       => $log->to_ayah,
                'logged_at'  => optional($log->logged_at)->toDateString(),
            ] : null,
            'progress_percent' => $this->progressPercentage(),
            'status'           => $this->status,
            'status_label'     => $this->statusLabel(),
        ];
    }
}
