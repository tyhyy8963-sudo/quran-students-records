<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Support\MemorizationProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * لوحة المعلّم والتقارير (S10).
 *
 * ملاحظة توثيقية عن التصدير: الخطة الأصلية تذكر "تصدير PDF/Excel"، لكن هذه
 * البيئة لا تصل إلى Packagist (مستودع حزم Composer) فلا يمكن تثبيت مكتبات
 * توليد PDF/Excel حقيقية (dompdf، PhpSpreadsheet). البديل هنا صادق لا يدّعي
 * ما لا يفعله: تصدير CSV حقيقي (يُفتح مباشرة في إكسل) + عرض تقرير مهيّأ
 * للطباعة (@media print) يحوّله المتصفّح نفسه إلى PDF عبر "طباعة → حفظ كـ PDF"
 * بلا أي خادم توليد PDF وسيط.
 */
class ReportController extends Controller
{
    /**
     * لوحة إحصائية عامة — صورة الآن للطلاب، لا ضمن فترة محدَّدة (ذلك دور
     * تقرير الفترة أدناه). عزل بيانات المعلّم يجري تلقائيًا عبر TeacherScope
     * على Student، فكل الاستعلامات هنا محصورة بطلابه فقط دون شرط يتكرّر.
     */
    public function index()
    {
        $students = Student::with('latestMemorizationLog.surah')->get();
        $studentIds = $students->pluck('student_id');

        // نسبة كل طالب تُقرأ أدناه في متوسّط التقدّم — استعلام واحد لكلّهم
        // بدل استعلام لكل طالب (S14).
        app(MemorizationProgress::class)->warmFor($students);

        $byStatus = $students->groupBy('status')->map->count();
        $statusCounts = [];
        foreach (Student::STATUSES as $value => $label) {
            $statusCounts[$value] = ['label' => $label, 'count' => $byStatus->get($value, 0)];
        }

        $activeStudents = $students->where('status', 'active');
        $avgProgress = $activeStudents->isEmpty()
            ? null
            : round($activeStudents->avg(fn (Student $s) => $s->progressPercentage()), 1);

        $circlesCount = Circle::count();

        $recentAttendance = Attendance::whereIn('student_id', $studentIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get();
        $attendanceRate = $recentAttendance->isEmpty()
            ? null
            : round($recentAttendance->where('status', 'حاضر')->count() / $recentAttendance->count() * 100, 1);

        $alerts = Attendance::alertsFor($studentIds);
        $studentsNeedingAttention = $students->filter(fn (Student $s) => $alerts[$s->student_id] ?? false)->values();

        return view('reports.index', compact(
            'statusCounts', 'avgProgress', 'circlesCount', 'attendanceRate', 'studentsNeedingAttention'
        ));
    }

    /**
     * تقرير فترة: حضور وسجلّ زمني لكل طالب بين تاريخين، لعرض ويب وطباعة
     * (period) أو تنزيل CSV (exportCsv) — نفس بناء الصفوف بالضبط في الحالتين
     * عبر periodRows() حتى لا يختلف الملف المُصدَّر عمّا يظهر على الشاشة.
     */
    public function period(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->periodRows($from, $to);

        return view('reports.period', compact('rows', 'from', 'to'));
    }

    public function exportCsv(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->periodRows($from, $to);

        $filename = 'تقرير-الفترة-'.$from->toDateString().'-الى-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8: بدونها يعرض إكسل النص العربي رموزًا مشوَّهة رغم أن
            // الملف نفسه مُرمَّز UTF-8 بشكل صحيح — سلوك إكسل عند فتح CSV بلا
            // تحديد ترميز صريح، لا عطل في الملف.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'الطالب', 'الحلقة', 'حاضر', 'غائب', 'متأخر', 'مستأذن',
                'نسبة الحضور %', 'حفظ جديد', 'مراجعة', 'تسميع',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->student_name,
                    $row['student']->circle->name ?? '—',
                    $row['present'],
                    $row['absent'],
                    $row['late'],
                    $row['excused'],
                    $row['attendance_rate'] ?? '—',
                    $row['memorization_count'],
                    $row['review_count'],
                    $row['recitation_count'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function periodRows(Carbon $from, Carbon $to)
    {
        $students = Student::with('circle')->orderBy('student_name')->get();
        $studentIds = $students->pluck('student_id');

        // whereDate (لا whereBetween على العمود الخام) للسبب نفسه الموثَّق في
        // AttendanceController::store(): كاست "date" يخزّن وقتًا كاملاً خلف
        // التاريخ على SQLite، فتقارن whereBetween النص الخام وتستثني سطور
        // اليوم الأخير من المدى (تاريخه "أكبر" نصيًا من حد النهاية بلا وقت).
        // whereDate يقارن التاريخ الفعلي فيعمل بصرف النظر عن أي وقت عالق.
        $attendanceByStudent = Attendance::whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->groupBy('student_id');

        $logsByStudent = RecitationLog::whereIn('student_id', $studentIds)
            ->whereDate('logged_at', '>=', $from->toDateString())
            ->whereDate('logged_at', '<=', $to->toDateString())
            ->get()
            ->groupBy('student_id');

        return $students->map(function (Student $student) use ($attendanceByStudent, $logsByStudent) {
            $attendance = $attendanceByStudent->get($student->student_id, collect());
            $logs = $logsByStudent->get($student->student_id, collect());
            $recorded = $attendance->count();
            $present = $attendance->where('status', 'حاضر')->count();

            return [
                'student' => $student,
                'present' => $present,
                'absent' => $attendance->where('status', 'غائب')->count(),
                'late' => $attendance->where('status', 'متأخر')->count(),
                'excused' => $attendance->where('status', 'مستأذن')->count(),
                'attendance_rate' => $recorded ? round($present / $recorded * 100, 1) : null,
                'memorization_count' => $logs->where('type', 'حفظ')->count(),
                'review_count' => $logs->where('type', 'مراجعة')->count(),
                'recitation_count' => $logs->where('type', 'تسميع')->count(),
            ];
        })->values();
    }

    /**
     * افتراضي بلا معطيات: الشهر الحالي. try/catch يحمي من نص تاريخ غير
     * صالح في الرابط بدل رمي استثناء غير معالَج (نفس نمط StudentController
     * مع ?month= في صفحة الطالب).
     */
    private function resolveRange(Request $request): array
    {
        try {
            $from = $request->query('from')
                ? Carbon::parse($request->query('from'))->startOfDay()
                : now()->startOfMonth();
            $to = $request->query('to')
                ? Carbon::parse($request->query('to'))->endOfDay()
                : now()->endOfDay();
        } catch (\Exception $e) {
            $from = now()->startOfMonth();
            $to = now()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
