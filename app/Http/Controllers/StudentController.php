<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Support\MemorizationProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * عرض لوحة الطلاب.
     *
     * بحث بالاسم + فلتر بالحلقة والحالة + فرز عبر رأس الأعمدة (S11)، فوق
     * ترقيم الصفحات الحالي — لا ترتيب ثابت وحيد بعد الآن، لكن الاسم يبقى
     * الافتراضي. عزل بيانات المعلّم يجري تلقائيًا عبر TeacherScope على
     * الموديل، لا شرطًا مكتوبًا هنا (B-04).
     *
     * تحميل مسبق لـ circle وlatestMemorizationLog.surah (S6/S7) يمنع
     * استعلامًا إضافيًا لكل طالب عند بناء عمود "آخر موضع" (N+1).
     */
    public function index(Request $request)
    {
        $query = Student::with(['circle', 'latestMemorizationLog.surah']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where('student_name', 'like', '%'.$search.'%');
        }

        if ($circleFilter = $request->query('circle_id')) {
            $circleFilter === 'none'
                ? $query->whereNull('circle_id')
                : $query->where('circle_id', $circleFilter);
        }

        if ($statusFilter = $request->query('status')) {
            $query->where('status', $statusFilter);
        }

        $sort = $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'status') {
            $query->orderBy('status', $dir)->orderBy('student_name');
        } else {
            $query->orderBy('student_name', $dir);
        }

        $students = $query->paginate(50)->withQueryString();

        // تحميل تغطية الحفظ لكل طلاب الصفحة باستعلام واحد قبل بناء الصفوف
        // (S14) — بلا هذا يصبح لكل صفّ استعلامه عند قراءة نسبته.
        app(MemorizationProgress::class)->warmFor($students->getCollection());

        $studentsCount = Student::count();
        $circles = Circle::orderBy('name')->get();

        // ترتيب الحفظ لا ترتيب المصحف (S14): قائمة السور في اللوحة تبدأ
        // بالناس صعودًا، لأن هذا هو التسلسل الذي يختار منه المعلّم فعلًا،
        // والفاتحة في آخرها لأنها لا تُحتسب في النسبة.
        $surahs = Surah::inMemorizationOrder()->get(['id', 'name', 'ayah_count', 'excluded_from_progress']);

        // منطق المؤشّر نفسه تستخدمه لوحة التقارير الإحصائية (S10) —
        // مستخرَج إلى Attendance::alertsFor() حتى لا يتكرّر الحساب في مكانين.
        $attendanceAlerts = Attendance::alertsFor($students->pluck('student_id'));

        return view('dashboard', compact('students', 'studentsCount', 'circles', 'surahs', 'attendanceAlerts', 'sort', 'dir'));
    }

    /**
     * صفحة سجلّ طالب واحد — الخط الزمني الكامل ومؤشر التقدّم (S8).
     */
    public function show($id)
    {
        $student = Student::with('circle')->findOrFail($id);

        $this->authorize('update', $student);

        $logs = $student->recitationLogs()->with('surah')->paginate(20);
        $surahs = Surah::inMemorizationOrder()->get(['id', 'number', 'name', 'ayah_count', 'memorization_order', 'excluded_from_progress']);

        // المنحنى صار بالنسبة المئوية لا بعدد الآيات التراكمي (S14): إعادة
        // تشغيل السجلّ زمنيًا داخل MemorizationProgress::timeline() تعطي نسبة
        // الطالب كما كانت في كل يوم فعلًا — بنفس الصيغة المعروضة في الحلقة
        // أعلى الصفحة، فلا يقرأ المعلّم رقمين مختلفين لنفس الشيء.
        $progress = app(MemorizationProgress::class);
        $chartPoints = $progress->timeline($student);

        $month = request()->query('month');
        try {
            $monthStart = $month ? Carbon::parse($month.'-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Exception $e) {
            $monthStart = now()->startOfMonth();
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        // whereDate لا whereBetween: نفس عطل كاست "date" الموثَّق في
        // AttendanceController::store() — whereBetween يقارن نص التاريخ الخام
        // فيستثني سطر آخر يوم في الشهر على SQLite لأن قيمته المخزَّنة تحمل
        // وقتًا خلف التاريخ يجعلها "أكبر" نصيًا من حد النهاية بلا وقت.
        $attendanceByDate = $student->attendances()
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->get()
            ->keyBy(fn (Attendance $a) => $a->date->toDateString());

        $completedSurahs = $progress->completedSurahs($student);
        $furthestSurah = $progress->furthestSurah($student);

        return view('students.show', compact(
            'student', 'logs', 'surahs', 'chartPoints', 'monthStart', 'attendanceByDate',
            'completedSurahs', 'furthestSurah'
        ));
    }

    public function store(StoreStudentRequest $request)
    {
        $validated = $request->validated();

        $student = DB::transaction(function () use ($validated) {
            $student = Student::create([
                'student_name' => $validated['student_name'],
                'circle_id'    => $validated['circle_id'] ?? null,
            ]);

            // نقطة بداية اختيارية عند الإضافة — أول سطر في السجلّ الزمني (S7)،
            // لا حقل مستقل على الطالب.
            if (! empty($validated['surah_id']) && ! empty($validated['the_ayah'])) {
                $student->recitationLogs()->create([
                    'surah_id'  => $validated['surah_id'],
                    'to_ayah'   => $validated['the_ayah'],
                    'type'      => 'حفظ',
                    'logged_at' => now()->toDateString(),
                ]);
            }

            return $student;
        });

        return response()->json([
            'data'    => new StudentResource($student->load(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تمت إضافة الطالب بنجاح.',
        ], 201);
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        $student = Student::findOrFail($id);

        $this->authorize('update', $student);

        $validated = $request->validated();

        $student->update([
            'student_name' => $validated['student_name'],
            'circle_id'    => array_key_exists('circle_id', $validated) ? $validated['circle_id'] : $student->circle_id,
            'status'       => $validated['status'] ?? $student->status,
        ]);

        return response()->json([
            'data'    => new StudentResource($student->fresh(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تم حفظ البيانات بنجاح.',
        ]);
    }

    /**
     * حذف ناعم — قابل للتراجع عبر students.restore (B-05).
     */
    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        $this->authorize('delete', $student);

        $student->delete();

        return response()->json([
            'data'    => ['id' => $student->student_id],
            'message' => 'تم حذف الطالب. يمكنك التراجع خلال هذه الجلسة.',
        ]);
    }

    /**
     * التراجع عن حذف طالب.
     */
    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);

        $this->authorize('restore', $student);

        $student->restore();

        return response()->json([
            'data'    => new StudentResource($student->load(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تم استرجاع الطالب.',
        ]);
    }

    /**
     * استيراد طلاب من ملف CSV (S11): عمود أول "اسم الطالب" (إلزامي)، عمود
     * ثانٍ اختياري باسم حلقة موجودة مسبقًا. صف ترويسة بعنوان "اسم الطالب"
     * حرفيًا في أول سطر يُتجاهل تلقائيًا، وأي صف لاحق بلا اسم يُسجَّل في
     * الملاحظات ولا يوقف بقية الاستيراد — عطل صف واحد لا يُسقط الملف كله.
     *
     * مطابقة الحلقة بالاسم لا بمعرّف رقمي (الملف مصدره خارج النظام)، فاسم
     * غير موجود يُضيف الطالب بلا حلقة مع ملاحظة صريحة بدل فشل الصف بالكامل
     * أو اختراع حلقة جديدة بصمت.
     */
    public function import(ImportStudentsRequest $request)
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');

        // BOM UTF-8 شائع في ملفات CSV المصدَّرة من إكسل — إزالته حتى لا
        // يلتصق بأول اسم في الملف فيفشل اكتشاف صف الترويسة أو اسم الطالب.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $circleIdsByName = [];
        foreach (Circle::all() as $circle) {
            $circleIdsByName[mb_strtolower(trim($circle->name))] = $circle->id;
        }

        $created = 0;
        $notes = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $name = trim((string) ($row[0] ?? ''));
            $circleName = trim((string) ($row[1] ?? ''));

            if ($rowNumber === 1 && $name === 'اسم الطالب') {
                continue;
            }

            if ($name === '') {
                if (count($row) > 1 || $circleName !== '') {
                    $notes[] = "السطر {$rowNumber}: تجاهُل — لا يوجد اسم طالب.";
                }
                continue;
            }

            if (mb_strlen($name) > 255) {
                $notes[] = "السطر {$rowNumber}: تجاهُل — الاسم طويل جدًا.";
                continue;
            }

            $circleId = null;
            if ($circleName !== '') {
                $circleId = $circleIdsByName[mb_strtolower($circleName)] ?? null;
                if ($circleId === null) {
                    $notes[] = "السطر {$rowNumber}: أُضيف \"{$name}\" بلا حلقة — الحلقة \"{$circleName}\" غير موجودة.";
                }
            }

            Student::create(['student_name' => $name, 'circle_id' => $circleId]);
            $created++;
        }

        fclose($handle);

        return response()->json([
            'data' => ['created' => $created, 'notes' => $notes],
            'message' => $created > 0
                ? "تمت إضافة {$created} طالبًا."
                : 'لم تتم إضافة أي طالب — تحقّق من محتوى الملف.',
        ]);
    }
}
