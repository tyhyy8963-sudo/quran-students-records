<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecitationLogRequest;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Support\MemorizationProgress;

/**
 * السجلّ الزمني لطالب (S7) — إضافة سطر جديد أو التراجع عن آخر سطر أُضيف
 * بالخطأ. لا تعديل على سطر قائم: تاريخ يُعدَّل بعد وقته ليس تاريخًا أمينًا.
 */
class RecitationLogController extends Controller
{
    public function store(StoreRecitationLogRequest $request, $studentId)
    {
        $student = Student::findOrFail($studentId);

        $this->authorize('update', $student);

        $validated = $request->validated();

        $log = $student->recitationLogs()->create([
            'surah_id'  => $validated['surah_id'],
            'from_ayah' => $validated['from_ayah'] ?? null,
            'to_ayah'   => $validated['to_ayah'],
            'type'      => $validated['type'],
            'grade'     => $validated['grade'] ?? null,
            'notes'     => $validated['notes'] ?? null,
            'logged_at' => $validated['logged_at'] ?? now()->toDateString(),
        ]);

        // إسقاط التغطية المحفوظة في الذاكرة قبل قراءة النسبة (S14): الخدمة
        // singleton داخل الطلب الواحد، فبلا هذا قد تُعاد نسبة ما قبل الإضافة.
        app(MemorizationProgress::class)->forget($student);

        return response()->json([
            'data'    => [
                'id'               => $log->id,
                'progress_percent' => $student->refresh()->progressPercentage(),
            ],
            'message' => 'تمت إضافة السجلّ بنجاح.',
        ], 201);
    }

    public function destroy($studentId, $logId)
    {
        $student = Student::findOrFail($studentId);
        $log = RecitationLog::where('student_id', $student->student_id)->findOrFail($logId);

        $this->authorize('delete', $log);

        $log->delete();

        app(MemorizationProgress::class)->forget($student);

        return response()->json([
            'data'    => ['id' => $log->id],
            'message' => 'تم حذف السجلّ.',
        ]);
    }
}
