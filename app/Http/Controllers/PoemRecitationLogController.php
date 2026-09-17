<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePoemRecitationLogRequest;
use App\Models\PoemRecitationLog;
use App\Models\Poem;
use App\Models\Student;
use App\Support\PoemProgress;

/**
 * السجلّ الزمني لحفظ/مراجعة متن (S15) — نسخة من RecitationLogController بوحدة
 * "بيت" بدل "آية". لا تعديل على سطر قائم، فقط إضافة أو تراجع فوري.
 */
class PoemRecitationLogController extends Controller
{
    public function store(StorePoemRecitationLogRequest $request, $studentId)
    {
        $student = Student::findOrFail($studentId);

        $this->authorize('update', $student);

        $validated = $request->validated();
        $poem = Poem::findOrFail($validated['poem_id']);

        $log = $student->poemRecitationLogs()->create([
            'poem_id'   => $poem->id,
            'from_bayt' => $validated['from_bayt'] ?? null,
            'to_bayt'   => $validated['to_bayt'],
            'type'      => $validated['type'],
            'grade'     => $validated['grade'] ?? null,
            'notes'     => $validated['notes'] ?? null,
            'logged_at' => $validated['logged_at'] ?? now()->toDateString(),
        ]);

        app(PoemProgress::class)->forget($student, $poem);

        return response()->json([
            'data'    => [
                'id'               => $log->id,
                'progress_percent' => app(PoemProgress::class)->percentage($student, $poem),
            ],
            'message' => 'تمت إضافة السجلّ بنجاح.',
        ], 201);
    }

    public function destroy($studentId, $logId)
    {
        $student = Student::findOrFail($studentId);
        $log = PoemRecitationLog::where('student_id', $student->student_id)->findOrFail($logId);

        $this->authorize('delete', $log);

        $poem = $log->poem;
        $log->delete();

        app(PoemProgress::class)->forget($student, $poem);

        return response()->json([
            'data'    => ['id' => $log->id],
            'message' => 'تم حذف السجلّ.',
        ]);
    }
}
