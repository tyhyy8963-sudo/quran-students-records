<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\RecitationLog;
use App\Models\Scopes\TeacherScope;
use App\Models\Student;
use App\Models\User;
use App\Support\MemorizationProgress;
use Carbon\Carbon;

/**
 * لوحة المدير المؤسسية (S20) — رؤية شاملة على مستوى كل المعلّمين معًا
 * (index)، وعرض سجلّات معلّم واحد بعينه للمتابعة (show). كانت غير موجودة
 * إطلاقًا قبل هذا السبرنت (راجع sprint-15-plus-gap-analysis-and-plan.md، بند 8).
 *
 * تحدٍّ بنيوي واحد يحكم كل ميثود هنا: TeacherScope (المطبَّق على Student
 * وCircle) يُقيَّد تلقائيًا بـ`teacher_id = Auth::id()` بمجرّد وجود مستخدم
 * مسجّل دخوله — ومستخدم لوحة المدير هنا هو *المدير نفسه*، الذي لا يملك أي
 * طالب أو حلقة على الإطلاق. لولا withoutGlobalScope(TeacherScope::class)
 * الصريح في كل استعلام أدناه لعادت كل نتيجة فارغة دائمًا مهما وُجد من بيانات
 * فعلية — بالضبط نفس السبب الموثَّق في User::students(). يُستخدم الشكل
 * المحدَّد (لا withoutGlobalScopes() الجامعة) عمدًا حتى لا يُزال أيضًا نطاق
 * الحذف الناعم (SoftDeletingScope) على Student فتظهر طلاب محذوفون بالخطأ.
 */
class AdminOverviewController extends Controller
{
    /** عدد الأيام بلا أي سجلّ تسميع/حفظ/مراجعة قبل اعتبار معلّم "لم يسجّل مؤخّرًا". */
    private const INACTIVITY_THRESHOLD_DAYS = 14;

    /**
     * نظرة عامة على المنظومة كلّها: إحصاءات إجمالية، مقارنة بين المعلّمين
     * أنفسهم، مقارنة بين الحلقات بصرف النظر عن معلّمها، وأعلى الطلاب تقدّمًا
     * على مستوى المنظومة — أربع زوايا لنفس البيانات طلبها التحليل الأصلي
     * ("رؤية شاملة على مستوى المنظومة")، لا شاشة إحصاء واحدة سطحية.
     */
    public function index()
    {
        [$teachers, $teacherStats, $students, $studentIds, $attendance] = $this->teacherStatsRows();

        // مقارنة أداء الحلقات بصرف النظر عن معلّمها — حلقات بلا أي طالب نشط
        // تُستبعَد من الترتيب (لا معنى لمتوسّط تقدّم بلا طلاب) لكنها تبقى
        // ظاهرة بعدد طلابها.
        $circles = Circle::withoutGlobalScope(TeacherScope::class)->with('teacher')->get();
        $circleStats = $circles->map(function (Circle $circle) use ($students) {
            $circleStudents = $students->where('circle_id', $circle->id);
            $activeCircleStudents = $circleStudents->where('status', 'active');

            return [
                'circle' => $circle,
                'students_count' => $circleStudents->count(),
                'avg_progress' => $activeCircleStudents->isEmpty()
                    ? null
                    : round($activeCircleStudents->avg(fn (Student $s) => $s->progressPercentage()), 1),
            ];
        })->sortByDesc(fn ($row) => $row['avg_progress'] ?? -1)->values();

        // أعلى عشرة طلاب تقدّمًا على مستوى المنظومة كلّها (لا معلّم واحد) —
        // "ترتيب الطلاب الأعلى تقدّمًا" من خارطة الطريق الأصلية.
        $topStudents = $students->where('status', 'active')
            ->sortByDesc(fn (Student $s) => $s->progressPercentage())
            ->take(10)
            ->values();

        $byStatus = $students->groupBy('status')->map->count();
        $statusCounts = [];
        foreach (Student::STATUSES as $value => $label) {
            $statusCounts[$value] = ['label' => $label, 'count' => $byStatus->get($value, 0)];
        }

        $activeStudents = $students->where('status', 'active');
        $avgProgress = $activeStudents->isEmpty()
            ? null
            : round($activeStudents->avg(fn (Student $s) => $s->progressPercentage()), 1);

        // متوسّط نسبة التقدّم قبل 30 يومًا (مؤشّر الاتجاه v2 — تفعيل مؤجَّل
        // سابقًا لخطورته، طُبِّق الآن عبر MemorizationProgress::percentagesAsOf()
        // الجديدة بدل لمس خوارزمية الانسياب المُختبَرة نفسها: تُعيد بناء نفس
        // percentage() تمامًا لكن من سجلّات "حفظ" حتى ذلك التاريخ فقط — دالة
        // جديدة بالكامل تستدعي private helpers القائمة فعلًا (coverageFromLogs/
        // applyCascade/percentageFromLogs)، لا خوارزمية موازية جديدة، فلا خطر
        // على أي من عشرات اختبارات الانسياب القائمة. طالب بلا أي سجلّ قبل ذلك
        // التاريخ نسبته 0% حقيقةً (لا استبعاد) — هذا واقعه الفعلي وقتها.
        $previousAvgProgress = $activeStudents->isEmpty()
            ? null
            : (function () use ($activeStudents) {
                $asOf = app(MemorizationProgress::class)->percentagesAsOf($activeStudents, now()->subDays(30));

                return round($activeStudents->avg(fn (Student $s) => $asOf[(int) $s->student_id] ?? 0.0), 1);
            })();

        $attendanceRate = $attendance->isEmpty()
            ? null
            : round($attendance->where('status', 'حاضر')->count() / $attendance->count() * 100, 1);

        // نسبة الحضور للـ30 يومًا السابقة لفترة المقارنة أعلاه (مؤشّر الاتجاه
        // v2) — نفس مجموعة الطلاب ($studentIds)، نافذة زمنية سابقة مباشرة
        // بلا تداخل مع النافذة الحالية (اليوم-60 إلى اليوم-31، لا اليوم-30
        // حتى لا يُحتسَب يوم واحد ضمن الفترتين معًا).
        $previousAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereBetween('date', [now()->subDays(60)->toDateString(), now()->subDays(31)->toDateString()])
            ->get();
        $previousAttendanceRate = $previousAttendance->isEmpty()
            ? null
            : round($previousAttendance->where('status', 'حاضر')->count() / $previousAttendance->count() * 100, 1);

        return view('admin.overview', compact(
            'teachers', 'teacherStats', 'circleStats', 'topStudents',
            'statusCounts', 'avgProgress', 'previousAvgProgress', 'attendanceRate', 'previousAttendanceRate'
        ));
    }

    /**
     * جدول مقارنة المعلّمين نفسه — مُستخرَج من index() (S37 — بند 4 من خطّة
     * التقارير المعتمَدة) حتى تستعمله index() وexportCsv() معًا بلا نسخة
     * ثانية من نفس الحساب (نفس فلسفة periodRows() في ReportController).
     * تُعاد $students/$studentIds/$attendance أيضًا لأن index() لا تزال
     * تحتاجها مباشرة لحساب مقارنة الحلقات ومؤشّرات الاتجاه أدناه.
     *
     * @return array{0: \Illuminate\Support\Collection<int, User>, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection<int, Student>, 3: \Illuminate\Support\Collection, 4: \Illuminate\Support\Collection}
     */
    private function teacherStatsRows(): array
    {
        $teachers = User::teachers()->orderBy('name')->get();

        // Circle يحمل TeacherScope أيضًا — بلا تعطيله صراحةً هنا كذلك، يُحمَّل
        // كل student->circle ضمن استعلام فرعي مقيَّد بـ"حلقات المدير" (صفر
        // دائمًا)، فتظهر كل حلقة null رغم وجودها فعليًا (نفس فخ withoutGlobalScope
        // الجزئي، لا فقط على الاستعلام الرئيسي).
        $students = Student::withoutGlobalScope(TeacherScope::class)
            ->with(['circle' => fn ($q) => $q->withoutGlobalScope(TeacherScope::class)])
            ->get();

        // نسبة كل طالب مرّة واحدة لكل الاستعلام (لا استعلام لكل طالب) — نفس
        // مبدأ warmFor() المعتمد في StudentController/ReportController.
        app(MemorizationProgress::class)->warmFor($students);

        $studentIds = $students->pluck('student_id');

        $attendance = Attendance::whereIn('student_id', $studentIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get();

        // آخر نشاط تسميع/حفظ/مراجعة لكل طالب — استعلام واحد مجمَّع لا استعلام
        // لكل معلّم، ثم يُقرأ من الذاكرة لكل صفّ في الجدول أدناه. المتون لا
        // تُحتسَب هنا عمدًا (تبسيط معلَن): إشارة "نشاط عام" لا سجلًّا شاملًا.
        $lastActivityByStudent = RecitationLog::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, MAX(logged_at) as last_logged_at')
            ->groupBy('student_id')
            ->pluck('last_logged_at', 'student_id');

        $studentsByTeacher = $students->groupBy('teacher_id');
        $attendanceByStudentId = $attendance->groupBy('student_id');

        $teacherStats = $teachers->map(function (User $teacher) use ($studentsByTeacher, $attendanceByStudentId, $lastActivityByStudent) {
            $teacherStudents = $studentsByTeacher->get($teacher->id, collect());
            $activeStudents = $teacherStudents->where('status', 'active');
            $ids = $teacherStudents->pluck('student_id');

            $teacherAttendance = $ids->flatMap(fn ($id) => $attendanceByStudentId->get($id, collect()));
            $present = $teacherAttendance->where('status', 'حاضر')->count();
            $recorded = $teacherAttendance->count();

            $lastActivity = $ids
                ->map(fn ($id) => $lastActivityByStudent->get($id))
                ->filter()
                ->map(fn ($date) => Carbon::parse($date))
                ->sort()
                ->last();

            return [
                'teacher' => $teacher,
                'students_count' => $teacherStudents->count(),
                'avg_progress' => $activeStudents->isEmpty()
                    ? null
                    : round($activeStudents->avg(fn (Student $s) => $s->progressPercentage()), 1),
                'attendance_rate' => $recorded ? round($present / $recorded * 100, 1) : null,
                'last_activity' => $lastActivity,
                'inactive_recently' => $lastActivity === null
                    || $lastActivity->lt(now()->subDays(self::INACTIVITY_THRESHOLD_DAYS)),
            ];
        });

        return [$teachers, $teacherStats, $students, $studentIds, $attendance];
    }

    /**
     * تصدير جدول مقارنة المعلّمين (S37 — بند 4 من خطّة التقارير المعتمَدة) —
     * نفس teacherStatsRows() المستعمَلة في index() بالضبط، فلا يختلف رقم بين
     * الشاشة والملف. الطباعة تستعمل خاصية طباعة المتصفّح مباشرة على
     * admin.overview نفسها (@media print)، بلا مسار مستقلّ لها.
     */
    public function exportCsv()
    {
        [, $teacherStats] = $this->teacherStatsRows();

        $filename = 'نظرة-عامة-المعلمين-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($teacherStats) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'المعلّم', 'عدد الطلاب', 'متوسّط التقدّم %', 'نسبة الحضور % (30 يومًا)',
                'آخر نشاط', 'لم يسجّل مؤخّرًا',
            ]);

            foreach ($teacherStats as $row) {
                fputcsv($handle, [
                    $row['teacher']->name,
                    $row['students_count'],
                    $row['avg_progress'] ?? '—',
                    $row['attendance_rate'] ?? '—',
                    $row['last_activity'] ? $row['last_activity']->toDateString() : '—',
                    ($row['inactive_recently'] && $row['students_count'] > 0) ? 'نعم' : 'لا',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * تصدير جدول طلاب معلّم واحد (S37 — بند 4) — نفس صفوف الطلاب المعروضة في
     * admin.teacher-report (show() أدناه) بالضبط. الطباعة تستعمل خاصية طباعة
     * المتصفّح مباشرة على نفس شاشة show()، بلا مسار مستقلّ لها.
     */
    public function exportTeacherCsv(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 403, 'تُصدَّر سجلّات المعلّمين فقط.');

        $students = Student::withoutGlobalScope(TeacherScope::class)
            ->where('teacher_id', $teacher->id)
            ->with([
                'circle' => fn ($q) => $q->withoutGlobalScope(TeacherScope::class),
                'latestMemorizationLog.surah',
                'latestReviewLog.surah',
            ])
            ->orderBy('student_name')
            ->get();

        app(MemorizationProgress::class)->warmFor($students);

        $studentIds = $students->pluck('student_id');
        $attendanceTodayByStudent = Attendance::todayStatusFor($studentIds);
        $trackedPoemCounts = Student::trackedPoemCountsFor($studentIds);

        $filename = 'سجل-'.$teacher->name.'.csv';

        return response()->streamDownload(function () use ($students, $attendanceTodayByStudent, $trackedPoemCounts) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'الطالب', 'الحلقة', 'آخر موضع حفظ', 'آخر موضع مراجعة', 'الحالة',
                'حضور اليوم', 'المتون المتتبَّعة', 'نسبة التقدّم %',
            ]);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->student_name,
                    $student->circle->name ?? '—',
                    $student->latestMemorizationLog
                        ? ($student->latestMemorizationLog->surah->name ?? '—').' · آية '.$student->latestMemorizationLog->to_ayah
                        : '—',
                    $student->latestReviewLog
                        ? ($student->latestReviewLog->surah->name ?? '—').' · آية '.$student->latestReviewLog->to_ayah
                        : '—',
                    $student->statusLabel(),
                    $attendanceTodayByStudent[$student->student_id] ?? 'لم يُسجَّل',
                    $trackedPoemCounts[$student->student_id] ?? 0,
                    $student->progressPercentage(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * عرض سجلّات معلّم محدَّد — قراءة فقط بالكامل. لا نموذج تعديل ولا حذف ولا
     * رابط لصفحة سجلّ طالب فردية هنا (تلك خلف middleware('teacher') ولن
     * يصلها حساب مدير أصلًا) — هذا امتداد للتقارير لا بديل عن لوحة المعلّم
     * نفسه.
     */
    public function show(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 403, 'تعرض هذه الشاشة سجلّات المعلّمين فقط.');

        $students = Student::withoutGlobalScope(TeacherScope::class)
            ->where('teacher_id', $teacher->id)
            ->with([
                // نفس فخ TeacherScope الجزئي الموثَّق في index() أعلاه — حلقة
                // هذا المعلّم ليست حلقة المدير، فتبقى بحاجة لتعطيل صريح هنا أيضًا.
                'circle' => fn ($q) => $q->withoutGlobalScope(TeacherScope::class),
                'latestMemorizationLog.surah',
                'latestReviewLog.surah',
            ])
            ->orderBy('student_name')
            ->get();

        app(MemorizationProgress::class)->warmFor($students);

        $studentIds = $students->pluck('student_id');
        $circles = Circle::withoutGlobalScope(TeacherScope::class)
            ->where('teacher_id', $teacher->id)
            ->orderBy('name')
            ->get();

        $attendanceTodayByStudent = Attendance::todayStatusFor($studentIds);
        $trackedPoemCounts = Student::trackedPoemCountsFor($studentIds);
        $attendanceAlerts = Attendance::alertsFor($studentIds);

        $byStatus = $students->groupBy('status')->map->count();
        $statusCounts = [];
        foreach (Student::STATUSES as $value => $label) {
            $statusCounts[$value] = ['label' => $label, 'count' => $byStatus->get($value, 0)];
        }

        $activeStudents = $students->where('status', 'active');
        $avgProgress = $activeStudents->isEmpty()
            ? null
            : round($activeStudents->avg(fn (Student $s) => $s->progressPercentage()), 1);

        // مؤشّر اتجاه التقدّم (v2) — نفس منطق index() أعلاه بالضبط (راجع تعليقه
        // لتفصيل percentagesAsOf())، محصور بطلاب هذا المعلّم وحده.
        $previousAvgProgress = $activeStudents->isEmpty()
            ? null
            : (function () use ($activeStudents) {
                $asOf = app(MemorizationProgress::class)->percentagesAsOf($activeStudents, now()->subDays(30));

                return round($activeStudents->avg(fn (Student $s) => $asOf[(int) $s->student_id] ?? 0.0), 1);
            })();

        $recentAttendance = Attendance::whereIn('student_id', $studentIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get();
        $attendanceRate = $recentAttendance->isEmpty()
            ? null
            : round($recentAttendance->where('status', 'حاضر')->count() / $recentAttendance->count() * 100, 1);

        // مؤشّر اتجاه الحضور (v2) — نفس منطق index() أعلاه بالضبط، محصور بطلاب
        // هذا المعلّم وحده ($studentIds هنا).
        $previousAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereBetween('date', [now()->subDays(60)->toDateString(), now()->subDays(31)->toDateString()])
            ->get();
        $previousAttendanceRate = $previousAttendance->isEmpty()
            ? null
            : round($previousAttendance->where('status', 'حاضر')->count() / $previousAttendance->count() * 100, 1);

        $studentsNeedingAttention = $students
            ->filter(fn (Student $s) => $attendanceAlerts[$s->student_id] ?? false)
            ->values();

        return view('admin.teacher-report', compact(
            'teacher', 'students', 'circles', 'attendanceTodayByStudent', 'trackedPoemCounts',
            'statusCounts', 'avgProgress', 'previousAvgProgress', 'attendanceRate', 'previousAttendanceRate',
            'studentsNeedingAttention'
        ));
    }
}
