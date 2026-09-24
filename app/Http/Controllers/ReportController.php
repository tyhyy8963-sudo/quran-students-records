<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Poem;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Support\MemorizationProgress;
use App\Support\PoemProgress;
use App\Support\ReviewProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * لوحة المعلّم والتقارير (S10).
 *
 * ملاحظة توثيقية عن التصدير (S10 → مُحدَّثة S17): الخطة الأصلية ذكرت "تصدير
 * PDF/Excel"، لكن بيئة العمل وقتها لم تصل إلى Packagist فتعذَّر تثبيت مكتبات
 * توليد PDF/Excel حقيقية. البديل حينها كان صادقًا لا يدّعي ما لا يفعله: تصدير
 * CSV حقيقي + عرض مهيَّأ للطباعة (@media print) يحوّله المتصفّح نفسه إلى PDF.
 * **جهاز المستخدم متصل بالإنترنت فعليًا (S17)**، فتُرِّكب `phpoffice/phpspreadsheet`
 * بشكل طبيعي عبر Composer الآن — `exportXlsx()` أدناه يُصدِّر ملف .xlsx حقيقيًا
 * بتخطيط موسّع (عمودا مراجعة إضافيان لا يحملهما CSV)، وimport() في
 * StudentController يقبل .xlsx/.xls حقيقيَّين أيضًا. تصدير CSV والطباعة إلى
 * PDF يبقيان كما كانا (بلا أي تغيير) لعدم كسر أي رابط أو اختبار قائم.
 */
class ReportController extends Controller
{
    /**
     * لوحة إحصائية عامة — صورة الآن للطلاب، لا ضمن فترة محدَّدة (ذلك دور
     * تقرير الفترة أدناه). عزل بيانات المعلّم يجري تلقائيًا عبر TeacherScope
     * على Student، فكل الاستعلامات هنا محصورة بطلابه فقط دون شرط يتكرّر.
     *
     * فلترة بالحلقة/الحالة (S19) — كانت هذه اللوحة إحصاءات ثابتة بلا أي
     * فلاتر إطلاقًا؛ الآن يمكن حصرها بحلقة أو حالة معيّنة (مثلًا: "متوسّط
     * تقدّم حلقة الفجر وحدها")، بنفس منطق الاختيار المتعدّد في لوحة الطلاب
     * (S18، راجع applyCircleAndStatusFilters() أدناه). عدد الحلقات
     * ($circlesCount) يبقى إجماليًا غير مفلتَر عمدًا — إحصاءة عن المنظومة
     * كلّها لا عن الطلاب المعروضين.
     */
    public function index(Request $request)
    {
        $query = Student::with('latestMemorizationLog.surah');
        $this->applyCircleAndStatusFilters($query, $request);
        $students = $query->get();
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

        // مؤشّر اتجاه التقدّم (v2) — راجع تعليق AdminOverviewController::index()
        // لتفصيل percentagesAsOf() الجديدة في MemorizationProgress. محصور بنفس
        // $activeStudents بعد فلاتر الحلقة/الحالة أعلاه.
        $previousAvgProgress = $activeStudents->isEmpty()
            ? null
            : (function () use ($activeStudents) {
                $asOf = app(MemorizationProgress::class)->percentagesAsOf($activeStudents, now()->subDays(30));

                return round($activeStudents->avg(fn (Student $s) => $asOf[(int) $s->student_id] ?? 0.0), 1);
            })();

        $circlesCount = Circle::count();
        $circles = Circle::orderBy('name')->get();

        $recentAttendance = Attendance::whereIn('student_id', $studentIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get();
        $attendanceRate = $recentAttendance->isEmpty()
            ? null
            : round($recentAttendance->where('status', 'حاضر')->count() / $recentAttendance->count() * 100, 1);

        // مؤشّر الاتجاه (v2) — نفس المنطق المعتمد في AdminOverviewController،
        // محصور بـ$studentIds بعد تطبيق فلاتر الحلقة/الحالة نفسها أعلاه، حتى
        // تبقى نسبتا الحضور (الحالية والسابقة) عن نفس مجموعة الطلاب بالضبط.
        $previousAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereBetween('date', [now()->subDays(60)->toDateString(), now()->subDays(31)->toDateString()])
            ->get();
        $previousAttendanceRate = $previousAttendance->isEmpty()
            ? null
            : round($previousAttendance->where('status', 'حاضر')->count() / $previousAttendance->count() * 100, 1);

        $alerts = Attendance::alertsFor($studentIds);
        $studentsNeedingAttention = $students->filter(fn (Student $s) => $alerts[$s->student_id] ?? false)->values();

        return view('reports.index', compact(
            'statusCounts', 'avgProgress', 'previousAvgProgress', 'circlesCount', 'attendanceRate',
            'previousAttendanceRate', 'studentsNeedingAttention', 'circles'
        ));
    }

    /**
     * فلترة متعدّدة الاختيار بالحلقة (مع "بلا حلقة") والحالة معًا (S19) —
     * نفس منطق StudentController::index() بالضبط (S18)، مُستخرَج هنا محليًا
     * بدل استدعاء متحكّم آخر حتى لا يقترن المتحكّمان ببعضهما بلا داعٍ.
     * تُستعمل في لوحة الإحصاءات وتقرير الفترة معًا.
     */
    private function applyCircleAndStatusFilters($query, Request $request): void
    {
        $circleIds = array_values(array_filter(
            (array) $request->query('circle_id', []),
            fn ($v) => $v !== null && $v !== ''
        ));

        if ($circleIds) {
            $wantsNone = in_array('none', $circleIds, true);
            $realIds = array_values(array_diff($circleIds, ['none']));

            $query->where(function ($q) use ($wantsNone, $realIds) {
                if ($wantsNone) {
                    $q->orWhereNull('circle_id');
                }
                if ($realIds) {
                    $q->orWhereIn('circle_id', $realIds);
                }
            });
        }

        $statuses = array_values(array_filter(
            (array) $request->query('status', []),
            fn ($v) => $v !== null && $v !== ''
        ));

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
    }

    /**
     * تقرير فترة: حضور وسجلّ زمني لكل طالب بين تاريخين، لعرض ويب وطباعة
     * (period) أو تنزيل CSV (exportCsv) — نفس بناء الصفوف بالضبط في الحالتين
     * عبر periodRows() حتى لا يختلف الملف المُصدَّر عمّا يظهر على الشاشة.
     */
    public function period(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->periodRows($from, $to, $request);
        $circles = Circle::orderBy('name')->get();

        return view('reports.period', compact('rows', 'from', 'to', 'circles'));
    }

    public function exportCsv(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->periodRows($from, $to, $request);

        $filename = 'تقرير-الفترة-'.$from->toDateString().'-الى-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8: بدونها يعرض إكسل النص العربي رموزًا مشوَّهة رغم أن
            // الملف نفسه مُرمَّز UTF-8 بشكل صحيح — سلوك إكسل عند فتح CSV بلا
            // تحديد ترميز صريح، لا عطل في الملف.
            fwrite($handle, "\xEF\xBB\xBF");

            // (S25 — بطلب صريح من يحيى): "متأخر" و"تسميع" حُذفا نهائيًا (كلا
            // النوعين أُلغي من النظام في S22 ولا يمكن تسجيل سطر جديد بهما، فكانا
            // يظهران صفرًا دائمًا هنا)، و"غائب" انقسم إلى عمودين مستقلّين يعكسان
            // القيمتين الفعليتين المخزَّنتين اليوم، وأُضيف عمودا حالة الحفظ.
            fputcsv($handle, [
                'الطالب', 'الحلقة', 'حاضر', 'غائب بعذر', 'غائب بدون عذر', 'مستأذن',
                'نسبة الحضور %', 'حفظ جديد', 'مراجعة', 'حافظ', 'غير حافظ',
                'نسبة الحفظ %', 'عدد المتون المتتبَّعة',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->student_name,
                    $row['student']->circle->name ?? '—',
                    $row['present'],
                    $row['excused_absent'],
                    $row['unexcused_absent'],
                    $row['excused'],
                    $row['attendance_rate'] ?? '—',
                    $row['memorization_count'],
                    $row['review_count'],
                    $row['memorized_count'],
                    $row['not_memorized_count'],
                    $row['progress_percent'],
                    $row['tracked_poem_count'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * تصدير .xlsx حقيقي (S17) — نفس periodRows() المستعمَل في العرض وCSV
     * بالضبط، فلا يختلف رقم واحد بين الشاشة والملفين المُصدَّرين. تخطيطه
     * "موسّع" عن CSV بعمودين إضافيين (آخر موضع مراجعة ونسبة المراجعة) لأن
     * periodRows() يحسبهما دائمًا الآن (راجعها أدناه) — CSV تجاهلهما عمدًا
     * (لا يقرأ إلا المفاتيح التي يذكرها صراحةً) حتى لا ينكسر تخطيطه القائم
     * ولا يختلف عمّا تتوقّعه اختباراته أو أي استخدام خارجي معتاد عليه.
     */
    public function exportXlsx(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->periodRows($from, $to, $request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle('تقرير الفترة');

        // (S25) نفس تصحيح أعمدة CSV أعلاه (راجع تعليق exportCsv()) مطبَّق هنا حرفيًا.
        $headers = [
            'الطالب', 'الحلقة', 'حاضر', 'غائب بعذر', 'غائب بدون عذر', 'مستأذن',
            'نسبة الحضور %', 'حفظ جديد', 'مراجعة', 'حافظ', 'غير حافظ',
            'نسبة الحفظ %', 'آخر موضع مراجعة', 'نسبة المراجعة %', 'عدد المتون المتتبَّعة',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['student']->student_name,
                $row['student']->circle->name ?? '—',
                $row['present'],
                $row['excused_absent'],
                $row['unexcused_absent'],
                $row['excused'],
                $row['attendance_rate'] ?? '—',
                $row['memorization_count'],
                $row['review_count'],
                $row['memorized_count'],
                $row['not_memorized_count'],
                $row['progress_percent'],
                $row['latest_review_position'] ?? '—',
                $row['review_percent'],
                $row['tracked_poem_count'],
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'تقرير-الفترة-'.$from->toDateString().'-الى-'.$to->toDateString().'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * تقرير المتون المستقلّ (S37 — بند 1 من خطّة التقارير المعتمَدة): نفس
     * فلاتر لوحة "المتون" التفاعلية (PoemBoardController) — متن/حلقة متعدّدَا
     * الاختيار + نطاق نسبة — لكن بتخطيط مسطّح: صفّ واحد لكل (طالب، متن) لا
     * صفّ واحد للطالب بعدّة شارات، لأن هذا هو التخطيط الصالح للطباعة والتصدير
     * (شارات متعدّدة داخل خلية واحدة لا تُصدَّر بمعنى في CSV).
     */
    public function poems(Request $request)
    {
        $poems = Poem::orderBy('name')->get();
        $circles = Circle::orderBy('name')->get();
        $rows = $this->poemsRows($request, $poems);

        return view('reports.poems', compact('rows', 'poems', 'circles'));
    }

    public function poemsExportCsv(Request $request)
    {
        $poems = Poem::orderBy('name')->get();
        $rows = $this->poemsRows($request, $poems);

        $filename = 'تقرير-المتون-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['الطالب', 'الحلقة', 'المتن', 'الأبيات المحفوظة', 'إجمالي أبيات المتن', 'نسبة الحفظ %']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->student_name,
                    $row['student']->circle->name ?? '—',
                    $row['poem']->name,
                    $row['coverage'],
                    $row['poem']->bayt_count,
                    $row['percent'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * تقرير الحضور والغياب المستقلّ (S37 — بند 3): تطوير لمؤشّر "طلاب بحاجة
     * إلى متابعة" الصغير في لوحة التقارير الرئيسية إلى تقرير كامل بنطاق
     * تاريخ وفلترة حلقة/حالة، بنفس نمط تقرير الفترة (period/exportCsv) تمامًا
     * — عرض وتصدير من نفس دالة الصفوف حتى لا يختلف رقم بين الشاشة والملف.
     */
    public function attendance(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->attendanceRows($from, $to, $request);
        $circles = Circle::orderBy('name')->get();

        return view('reports.attendance', compact('rows', 'from', 'to', 'circles'));
    }

    public function attendanceExportCsv(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->attendanceRows($from, $to, $request);

        $filename = 'تقرير-الحضور-'.$from->toDateString().'-الى-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'الطالب', 'الحلقة', 'حاضر', 'غائب بعذر', 'غائب بدون عذر', 'مستأذن',
                'نسبة الحضور %', 'بحاجة إلى متابعة',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->student_name,
                    $row['student']->circle->name ?? '—',
                    $row['present'],
                    $row['excused_absent'],
                    $row['unexcused_absent'],
                    $row['excused'],
                    $row['attendance_rate'] ?? '—',
                    $row['needs_attention'] ? 'نعم' : 'لا',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * فلترة بالحلقة/الحالة + أعمدة نسبة التقدّم وعدد المتون المتتبَّعة (S19)
     * وآخر موضع مراجعة ونسبة المراجعة (S17، حصريًا لتصدير .xlsx الموسّع أدناه
     * — راجع exportXlsx()) — تقرير الفترة كان مقصورًا على نطاق تاريخ فقط، بلا
     * فلترة ولا أي معلومة عن تقدّم الطالب الفعلي أو متونه أو مراجعته. كل هذه
     * القيم لقطة الآن لا "خلال الفترة" عمدًا: كلّها تراكمية بطبيعتها (نفس
     * تعريفها في لوحة الطلاب S18/صفحة الطالب S16)، فحصرها بين تاريخين لا معنى
     * واقعيًا له كما للحضور/السجلّات.
     *
     * نسبة المراجعة تُحسَب باستعلام واحد لكل طالب (ReviewProgress::percentage
     * لا warmFor مُجمَّعة بعد) — مقبول هنا لأن هذا الاستعلام يُشغَّل عند طلب
     * تقرير صراحةً (فعل نادر) لا عند كل تحميل صفحة كما في اللوحة الرئيسية.
     */
    private function periodRows(Carbon $from, Carbon $to, Request $request)
    {
        $query = Student::with(['circle', 'latestReviewLog.surah']);
        $this->applyCircleAndStatusFilters($query, $request);
        $students = $query->orderBy('student_name')->get();
        $studentIds = $students->pluck('student_id');

        app(MemorizationProgress::class)->warmFor($students);
        $trackedPoemCounts = Student::trackedPoemCountsFor($studentIds);
        $reviewProgress = app(ReviewProgress::class);

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

        return $students->map(function (Student $student) use ($attendanceByStudent, $logsByStudent, $trackedPoemCounts, $reviewProgress) {
            $attendance = $attendanceByStudent->get($student->student_id, collect());
            $logs = $logsByStudent->get($student->student_id, collect());
            $recorded = $attendance->count();
            $present = $attendance->where('status', 'حاضر')->count();

            $latestReview = $student->latestReviewLog;
            $latestReviewPosition = $latestReview
                ? ($latestReview->surah->name ?? '—').' · آية '.$latestReview->to_ayah
                : null;

            // (S25 — بطلب صريح من يحيى، بعد أن أصبح عمودا "متأخر"/"تسميع" صفرًا
            // دائمًا منذ S22 لإلغاء كلا النوعين نهائيًا): حُذف العمودان كليًا،
            // و"غائب" انقسم إلى عمودين مستقلّين يطابقان قيمتَي الحالة الفعليتين
            // المخزَّنتين اليوم بدل دمجهما في رقم واحد ("غائب" القديمة قبل
            // الانقسام تُحتسَب دفاعيًا ضمن "غير معذور" لأي سطر تاريخي لم
            // يُرحَّل)، وأُضيف عمودا حالة الحفظ (حافظ/غير حافظ) لسجلّات الحفظ
            // والمراجعة معًا خلال الفترة نفسها.
            return [
                'student' => $student,
                'present' => $present,
                'excused_absent' => $attendance->where('status', 'غائب بعذر')->count(),
                'unexcused_absent' => $attendance->whereIn('status', ['غائب', 'غائب بدون عذر'])->count(),
                'excused' => $attendance->where('status', 'مستأذن')->count(),
                'attendance_rate' => $recorded ? round($present / $recorded * 100, 1) : null,
                'memorization_count' => $logs->where('type', 'حفظ')->count(),
                'review_count' => $logs->where('type', 'مراجعة')->count(),
                'memorized_count' => $logs->whereIn('type', ['حفظ', 'مراجعة'])->where('status', 'حافظ')->count(),
                'not_memorized_count' => $logs->whereIn('type', ['حفظ', 'مراجعة'])->where('status', 'غير حافظ')->count(),
                'progress_percent' => $student->progressPercentage(),
                'latest_review_position' => $latestReviewPosition,
                'review_percent' => $reviewProgress->percentage($student),
                'tracked_poem_count' => $trackedPoemCounts[$student->student_id] ?? 0,
            ];
        })->values();
    }

    /**
     * صفوف تقرير المتون (S37) — نفس منطق فلترة PoemBoardController::index()
     * بالضبط (متن/حلقة متعدّدَا الاختيار + نطاق نسبة)، لكن الناتج هنا صفّ
     * مستقلّ لكل (طالب، متن) مطابق بدل تجميعها في شارات ضمن صفّ طالب واحد —
     * راجع تعليق مسار reports.poems في routes/web.php للفارق بين اللوحتين.
     *
     * فلتر النسبة هنا يُطبَّق على الصفّ نفسه مباشرة (نسبة هذا المتن تحديدًا)،
     * لا على "أي متن من متون الطالب" كما في اللوحة التفاعلية — لأن كل صفّ هنا
     * يمثّل متنًا واحدًا فعليًا، فلا حاجة لمنطق "يكفي واحد ضمن النطاق".
     *
     * @return Collection<int, array{student: Student, poem: Poem, coverage: int, percent: float}>
     */
    private function poemsRows(Request $request, Collection $poems): Collection
    {
        $selectedPoemIds = array_values(array_filter(
            array_map('intval', (array) $request->query('poem_id', [])),
            fn ($v) => $v > 0
        ));

        $filterPoems = $selectedPoemIds ? $poems->whereIn('id', $selectedPoemIds)->values() : $poems;
        $filterPoemIds = $filterPoems->pluck('id');

        if ($filterPoemIds->isEmpty()) {
            return collect();
        }

        $circleIds = array_values(array_filter(
            (array) $request->query('circle_id', []),
            fn ($v) => $v !== null && $v !== ''
        ));
        $wantsNoneCircle = in_array('none', $circleIds, true);
        $realCircleIds = array_values(array_diff($circleIds, ['none']));

        $progressMin = $request->filled('progress_min')
            ? max(0.0, min(100.0, (float) $request->query('progress_min')))
            : null;
        $progressMax = $request->filled('progress_max')
            ? max(0.0, min(100.0, (float) $request->query('progress_max')))
            : null;

        $studentsQuery = Student::query()
            ->whereHas('poemRecitationLogs', fn ($q) => $q->whereIn('poem_id', $filterPoemIds))
            ->with(['circle', 'poemRecitationLogs' => fn ($q) => $q->whereIn('poem_id', $filterPoemIds)])
            ->orderBy('student_name');

        if ($circleIds) {
            $studentsQuery->where(function ($q) use ($wantsNoneCircle, $realCircleIds) {
                if ($wantsNoneCircle) {
                    $q->orWhereNull('circle_id');
                }
                if ($realCircleIds) {
                    $q->orWhereIn('circle_id', $realCircleIds);
                }
            });
        }

        $poemProgress = app(PoemProgress::class);
        $rows = collect();

        foreach ($studentsQuery->get() as $student) {
            $trackedIds = $student->poemRecitationLogs->pluck('poem_id')->unique();

            foreach ($filterPoems as $poem) {
                if (! $trackedIds->contains($poem->id)) {
                    continue;
                }

                $percent = $poemProgress->percentage($student, $poem);

                if ($progressMin !== null && $percent < $progressMin) {
                    continue;
                }
                if ($progressMax !== null && $percent > $progressMax) {
                    continue;
                }

                $rows->push([
                    'student'  => $student,
                    'poem'     => $poem,
                    'coverage' => $poemProgress->coverage($student, $poem),
                    'percent'  => $percent,
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * صفوف تقرير الحضور والغياب (S37) — امتداد لعمود "غائب" الصغير في لوحة
     * التقارير الرئيسية (Attendance::alertsFor) إلى تقرير كامل: نفس فلترة
     * الحلقة/الحالة (applyCircleAndStatusFilters) ونفس نطاق التاريخ المستعمل
     * في تقرير الفترة (periodRows) بالضبط، لكن مقصور على أعمدة الحضور فقط.
     *
     * @return Collection<int, array{student: Student, present: int, excused_absent: int, unexcused_absent: int, excused: int, attendance_rate: ?float, needs_attention: bool}>
     */
    private function attendanceRows(Carbon $from, Carbon $to, Request $request): Collection
    {
        $query = Student::with('circle');
        $this->applyCircleAndStatusFilters($query, $request);
        $students = $query->orderBy('student_name')->get();
        $studentIds = $students->pluck('student_id');

        // نفس عطل كاست "date" الموثَّق في periodRows()/AttendanceController::store():
        // whereDate لا whereBetween على العمود الخام.
        $attendanceByStudent = Attendance::whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->groupBy('student_id');

        // مؤشّر "بحاجة إلى متابعة" هنا هو نفسه Attendance::alertsFor() المستعمل
        // في لوحة التقارير — آخر سطرَي حضور على الإطلاق (لا داخل نطاق التقرير
        // فقط)، حتى يبقى معناه ثابتًا في كل الشاشات التي تعرضه.
        $alerts = Attendance::alertsFor($studentIds);

        return $students->map(function (Student $student) use ($attendanceByStudent, $alerts) {
            $attendance = $attendanceByStudent->get($student->student_id, collect());
            $recorded = $attendance->count();
            $present = $attendance->where('status', 'حاضر')->count();

            return [
                'student'          => $student,
                'present'          => $present,
                'excused_absent'   => $attendance->where('status', 'غائب بعذر')->count(),
                'unexcused_absent' => $attendance->whereIn('status', ['غائب', 'غائب بدون عذر'])->count(),
                'excused'          => $attendance->where('status', 'مستأذن')->count(),
                'attendance_rate'  => $recorded ? round($present / $recorded * 100, 1) : null,
                'needs_attention'  => $alerts[$student->student_id] ?? false,
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
