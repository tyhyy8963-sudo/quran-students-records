<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePoemBaselineRequest;
use App\Models\Poem;
use App\Models\Student;
use App\Models\StudentPoemBaseline;
use App\Support\PoemProgress;

/**
 * أرضية متن لطالب (S15) — "آخر بيت أتمّه الطالب من هذا المتن قبل الانضمام"،
 * نظير حقل Student::quran_baseline_surah_id لكن لكل متن على حدة (جدول
 * student_poem_baselines، صفّ واحد لكل زوج طالب/متن).
 *
 * upsert لا إنشاء متكرّر: تحديد الأرضية مرّة ثانية لنفس المتن يُحدِّث القيمة
 * السابقة بدل أن يضيف سطرًا موازيًا يُربك حساب PoemProgress.
 */
class PoemBaselineController extends Controller
{
    public function upsert(StorePoemBaselineRequest $request, $student, $poem)
    {
        $student = Student::findOrFail($student);

        $this->authorize('update', $student);

        $poem = Poem::findOrFail($poem);
        $validated = $request->validated();

        $baseline = StudentPoemBaseline::updateOrCreate(
            ['student_id' => $student->student_id, 'poem_id' => $poem->id],
            ['baseline_bayt' => $validated['baseline_bayt']]
        );

        app(PoemProgress::class)->forget($student, $poem);

        return response()->json([
            'data' => [
                'poem_id'          => $poem->id,
                'baseline_bayt'    => $baseline->baseline_bayt,
                'progress_percent' => app(PoemProgress::class)->percentage($student, $poem),
            ],
            'message' => 'تم حفظ أرضية المتن.',
        ]);
    }
}
