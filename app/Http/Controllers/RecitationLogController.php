<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecitationLogRequest;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Support\MemorizationProgress;
use App\Support\ReviewProgress;
use Illuminate\Support\Facades\DB;

/**
 * السجلّ الزمني لطالب (S7) — إضافة سطر جديد، أو تصحيح/حذف سطر اليوم.
 *
 * (تصحيح صريح من يحيى — استبدال "✕" بأيقونة تعديل ✎): update() أدناه يمنح
 * المستخدم تجربة "تعديل" مباشرة (نفس نافذة التسجيل تُفتَح معبّأة بالسجلّ
 * الحالي)، لكنها منفَّذة داخليًا كحذف للسطر القديم وإنشاء سطر جديد مكانه —
 * لا UPDATE مباشر على سطر قائم — حفاظًا على المبدأ الأصلي الموثَّق هنا
 * سابقًا: تاريخ يُعدَّل بعد وقته ليس تاريخًا أمينًا. النتيجة نفس الأمانة،
 * بلا إجبار المستخدم على حذف يدوي ثم إعادة تسجيل من الصفر.
 */
class RecitationLogController extends Controller
{
    public function store(StoreRecitationLogRequest $request, $studentId)
    {
        $student = Student::findOrFail($studentId);

        $this->authorize('update', $student);

        $validated = $request->validated();

        // سورة نهاية صريحة (S16، مراجعة عابرة لعدّة سور) تُخزَّن فقط لو
        // اختلفت فعليًا عن سورة البداية — تساويهما يعني نفس ما كان يعنيه
        // NULL دائمًا: سجلّ محصور بسورة واحدة، فلا داعي لتمييز شكلي بينهما.
        $toSurahId = $validated['to_surah_id'] ?? null;
        if ($toSurahId !== null && (int) $toSurahId === (int) $validated['surah_id']) {
            $toSurahId = null;
        }

        $log = $student->recitationLogs()->create([
            'surah_id'    => $validated['surah_id'],
            'to_surah_id' => $toSurahId,
            'from_ayah'   => $validated['from_ayah'] ?? null,
            'to_ayah'     => $validated['to_ayah'],
            'type'        => $validated['type'],
            'status'      => $validated['status'] ?? null,
            'notes'       => $validated['notes'] ?? null,
            'logged_at'   => $validated['logged_at'] ?? now()->toDateString(),
        ]);

        // إسقاط التغطية المحفوظة في الذاكرة قبل قراءة النسبة (S14/S16): كلتا
        // الخدمتين singleton داخل الطلب الواحد، فبلا هذا قد تُعاد نسبة ما قبل
        // الإضافة.
        app(MemorizationProgress::class)->forget($student);
        app(ReviewProgress::class)->forget($student);

        return response()->json([
            'data'    => [
                'id'               => $log->id,
                'progress_percent' => $student->refresh()->progressPercentage(),
            ],
            'message' => 'تمت إضافة السجلّ بنجاح.',
        ], 201);
    }

    /**
     * تعديل سجلّ قائم (راجع تعليق الصنف أعلاه لتفصيل الآلية: حذف+إنشاء لا
     * UPDATE مباشر). logged_at الأصلي يُحفَظ ما لم يُرسِل الطلب قيمة جديدة
     * صراحة — التعديل يصحّح بيانات سجلّ اليوم، لا يغيّر تاريخه.
     */
    public function update(StoreRecitationLogRequest $request, $studentId, $logId)
    {
        $student = Student::findOrFail($studentId);
        $log = RecitationLog::where('student_id', $student->student_id)->findOrFail($logId);

        $this->authorize('update', $log);

        $validated = $request->validated();

        $toSurahId = $validated['to_surah_id'] ?? null;
        if ($toSurahId !== null && (int) $toSurahId === (int) $validated['surah_id']) {
            $toSurahId = null;
        }

        $originalLoggedAt = $log->logged_at;
        $newLog = null;

        DB::transaction(function () use ($log, $student, $validated, $toSurahId, $originalLoggedAt, &$newLog) {
            $log->delete();

            $newLog = $student->recitationLogs()->create([
                'surah_id'    => $validated['surah_id'],
                'to_surah_id' => $toSurahId,
                'from_ayah'   => $validated['from_ayah'] ?? null,
                'to_ayah'     => $validated['to_ayah'],
                'type'        => $validated['type'],
                'status'      => $validated['status'] ?? null,
                'notes'       => $validated['notes'] ?? null,
                'logged_at'   => $validated['logged_at'] ?? $originalLoggedAt,
            ]);
        });

        app(MemorizationProgress::class)->forget($student);
        app(ReviewProgress::class)->forget($student);

        return response()->json([
            'data'    => [
                'id'               => $newLog->id,
                'progress_percent' => $student->refresh()->progressPercentage(),
            ],
            'message' => 'تم تعديل السجلّ.',
        ]);
    }

    public function destroy($studentId, $logId)
    {
        $student = Student::findOrFail($studentId);
        $log = RecitationLog::where('student_id', $student->student_id)->findOrFail($logId);

        $this->authorize('delete', $log);

        $log->delete();

        app(MemorizationProgress::class)->forget($student);
        app(ReviewProgress::class)->forget($student);

        return response()->json([
            'data'    => ['id' => $log->id],
            'message' => 'تم حذف السجلّ.',
        ]);
    }
}
