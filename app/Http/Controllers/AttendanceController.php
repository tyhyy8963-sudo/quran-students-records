<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Student;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * سجلّ الحضور والغياب — عرض فقط (S9، أُعيدت لهذا الغرض تحديدًا في تصحيح
     * S23: كانت هذه الشاشة موضع تجربة موسَّعة أضافت إليها تسجيل الدرس
     * والمراجعة بالخطأ، بناءً على فهم خاطئ لمكان "الصفحة الرئيسية" التي
     * يقصدها يحيى — تصحيح صريح منه: "تبويب الحضور والغياب هو فقط لعرض سجلات
     * الحضور والغياب". القائمة الموحّدة الفعلية (الدرس + المراجعة + الحضور)
     * انتقلت بالكامل إلى StudentController::index() (/dashboard)، وهذه
     * الشاشة عادت لعرض سجلّ يوم واحد فقط بلا أي تسجيل أو تعديل من هنا.
     */
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $circleId = $request->query('circle_id');

        // بحث بالاسم (طلب صريح من يحيى: "ضيف بحث باسم الطالب زي الموجود في
        // صفحة القرآن في المتون والتحضير والسجلات") — نفس نمط RecordsController.
        $search = trim((string) $request->query('q'));

        // whereDate لا where('date', $date): نفس عطل كاست "date" الموثَّق في
        // store() أدناه.
        $studentsQuery = Student::with([
                'circle',
                'attendances' => fn ($q) => $q->whereDate('date', $date),
            ])
            ->orderBy('student_name');

        if ($circleId) {
            $studentsQuery->where('circle_id', $circleId);
        }

        if ($search !== '') {
            $studentsQuery->where('student_name', 'like', '%'.$search.'%');
        }

        $students = $studentsQuery->get();
        $circles = Circle::orderBy('name')->get();

        // شارة عدد الطلاب في الشريط العلوي (طلب صريح من يحيى) — نفس استعلام
        // StudentController::index() (Student::count()، معزول تلقائيًا
        // بحلقة المعلّم عبر TeacherScope).
        $studentsCount = Student::count();

        return view('attendance.index', [
            'students'      => $students,
            'circles'       => $circles,
            'date'          => $date,
            'circleId'      => $circleId,
            'search'        => $search,
            'studentsCount' => $studentsCount,
        ]);
    }

    public function store(StoreAttendanceRequest $request)
    {
        $validated = $request->validated();
        $date = $validated['date'];

        // لا نستخدم updateOrCreate بمطابقة مباشرة على عمود "date": الإسناد
        // (setAttribute) يمرّ بتحويل تاريخ/وقت كامل (fromDateTime) حتى مع
        // كاست "date"، بينما شرط البحث في updateOrCreate يقارن النص الخام
        // بلا هذا التحويل. على MySQL عمود DATE الحقيقي يتجاهل جزء الوقت
        // فيختفي الفرق صدفةً، لكن SQLite (بيئة الاختبار) يخزّن النص كما هو
        // فيفشل البحث ويحاول إنشاء سطر مكرّر يصطدم بقيد unique. whereDate()
        // يقارن التاريخ الفعلي بصرف النظر عن أي جزء وقت عالق، فيعمل على
        // المحرّكين معًا بلا اعتماد على تسامح MySQL وحده.
        //
        // هذا المسار يبقى بلا تغيير رغم انتقال شاشة العرض (S23): أزرار تسجيل
        // الحضور في اللوحة الرئيسية (/dashboard) ترسل إلى هذا المسار نفسه.
        $saved = collect($validated['entries'])->map(function (array $entry) use ($date) {
            $attendance = Attendance::where('student_id', $entry['student_id'])
                ->whereDate('date', $date)
                ->first();

            $attributes = [
                'student_id' => $entry['student_id'],
                'date' => $date,
                'status' => $entry['status'],
                'notes' => $entry['notes'] ?? null,
            ];

            if ($attendance) {
                $attendance->update($attributes);

                return $attendance;
            }

            return Attendance::create($attributes);
        });

        return response()->json([
            'data' => ['date' => $date, 'count' => $saved->count()],
            'message' => $saved->count() === 1 ? 'تم حفظ الحضور.' : 'تم حفظ حضور '.$saved->count().' طالبًا.',
        ]);
    }
}
