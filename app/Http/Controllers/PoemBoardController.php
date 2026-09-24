<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Poem;
use App\Models\Student;
use App\Support\PoemProgress;
use Illuminate\Http\Request;

/**
 * لوحة طلاب خاصة بالمتون (S24، الجزء الثاني). أُعيد تصميم فلترتها بالكامل في
 * تعديل لاحق بطلب صريح من يحيى (2026-09-23): كانت اللوحة تقتصر على متن واحد
 * فقط عبر تبويب (poem_id مفرد إجباري)، فلا طريقة لعرض كل الطلاب معًا أو
 * تضييق القائمة بنسبة تقدّم. صارت الآن:
 *
 *  - فلتر "متن" متعدّد الاختيار (poem_id[]) — فارغ يعني كل المتون معًا (نفس
 *    منطق "بلا فلتر = بلا قيد" في StudentController::index()).
 *  - فلتر "حلقة" متعدّد الاختيار + "بلا حلقة" (circle_id[])، نفس نمط
 *    StudentController::index() حرفيًا (اختيار متعدّد لا فردي).
 *  - فلتر نطاق نسبة التقدّم (progress_min/progress_max) — يحدّد أيّ طالب
 *    يظهر، لا ما يُعرض من الشارات (راجع أدناه).
 *
 * الصفّ الواحد الآن لكل طالب لا لكل (طالب، متن) — بطلب صريح من يحيى: "صفّ
 * واحد لكل طالب، وكل متن شارة بنسبته". شارات المتون المعروضة لطالب مطابق =
 * كل متن من ضمن فلتر "متن" يتتبّعه هذا الطالب فعليًا (بصرف النظر عن نسبته
 * الفردية)؛ فلتر النسبة يقرّر فقط ظهور الطالب من عدمه (يكفي أن يقع متن واحد
 * من متونه ضمن النطاق)، لا يُخفي شارات متونه الأخرى — حتى يرى المعلّم صورة
 * كاملة عن تقدّم الطالب في كل متن يتابعه، لا جزءًا مبتورًا منها. هذا قرار
 * تصميمي اتُّخذ هنا لعدم ورود تفصيل أدقّ من يحيى؛ قابل للتعديل لو أراد خلاف
 * ذلك.
 *
 * "poemRecitationLogs" مُحمَّلة مسبقًا مفلترة بمتون الفلتر الحالي فقط (لا كل
 * سجلّات الطالب) — يكفي لمعرفة أيّ متون من الفلتر يتتبّعها كل طالب بلا
 * استعلام Student::trackedPoems() منفصل لكل طالب (N+1). حساب النسبة نفسه
 * (PoemProgress::percentage()) يبقى استعلامًا مستقلًا لكل (طالب، متن) مطابق
 * كما كان في التصميم الأصلي لهذه اللوحة — لم يُستحدَث نمط N+1 جديد هنا، فقط
 * امتدّ نفس النمط القائم أصلًا لمتن واحد ليشمل عدّة متون.
 *
 * حضور اليوم (طلب صريح من يحيى 2026-09-23: "المفترض أن يكون هناك زر
 * للتحضير في صفحة المتون كما هو موجود في صفحة القرآن"، مع التوضيح أن
 * الحضور "حاضر تلقائيًا... لأن في سجلّ القرآن يعتبر حاضر" حتى إن لم يُسجَّل
 * للطالب أي متن اليوم): سجلّ Attendance مشترك بين كل الصفحات أصلًا (صفّ واحد
 * لكل طالب/يوم، بصرف النظر عن المادة — راجع AttendanceController)، فلا حاجة
 * لأي عمود أو منطق جديد في قاعدة البيانات، فقط قراءة نفس Attendance::
 * todayStatusFor() المستخدَمة في StudentController::index()/dashboard
 * وAdminOverviewController، وعرضها هنا بنفس زرّ/نافذة "تسجيل الحضور"
 * (open-attendance-modal + attendanceModalBackdrop) المستنسخين حرفيًا من
 * dashboard.blade.php — فإن سجّل المعلّم حضور الطالب من القرآن (درسًا أو
 * مراجعة) ستظهر هنا "حاضر" تلقائيًا دون أي فعل إضافي، وتبقى قابلة للتعديل من
 * هنا مباشرة أيضًا (الحفظ يذهب لنفس الصفّ في الجدول، فآخر تعديل من أي صفحة
 * هو الذي يظهر في الأخرى).
 */
class PoemBoardController extends Controller
{
    public function index(Request $request)
    {
        $poems = Poem::orderBy('name')->get();
        $circles = Circle::orderBy('name')->get();

        $selectedPoemIds = array_values(array_filter(
            array_map('intval', (array) $request->query('poem_id', [])),
            fn ($v) => $v > 0
        ));

        // فلتر المتن الفعّال: المتون المختارة، أو كل المتون إن لم يُختَر شيء
        // ("عرض الكل" بطلب يحيى).
        $filterPoems = $selectedPoemIds
            ? $poems->whereIn('id', $selectedPoemIds)->values()
            : $poems;

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

        $filterPoemIds = $filterPoems->pluck('id');

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

        $students = $studentsQuery->get()
            ->map(function (Student $student) use ($filterPoems, $poemProgress) {
                $trackedIds = $student->poemRecitationLogs->pluck('poem_id')->unique();

                $poemEntries = $filterPoems
                    ->filter(fn (Poem $poem) => $trackedIds->contains($poem->id))
                    ->map(fn (Poem $poem) => (object) [
                        'poem'    => $poem,
                        'percent' => $poemProgress->percentage($student, $poem),
                    ])
                    ->values();

                return (object) ['student' => $student, 'poem_entries' => $poemEntries];
            })
            ->filter(function ($entry) use ($progressMin, $progressMax) {
                if ($progressMin === null && $progressMax === null) {
                    return true;
                }

                return $entry->poem_entries->contains(function ($e) use ($progressMin, $progressMax) {
                    if ($progressMin !== null && $e->percent < $progressMin) {
                        return false;
                    }
                    if ($progressMax !== null && $e->percent > $progressMax) {
                        return false;
                    }

                    return true;
                });
            })
            ->values();

        $today = now()->toDateString();
        $attendanceTodayByStudent = Attendance::todayStatusFor(
            $students->pluck('student.student_id')
        );

        return view('poems.index', compact(
            'poems', 'circles', 'selectedPoemIds', 'circleIds', 'progressMin', 'progressMax', 'students',
            'today', 'attendanceTodayByStudent'
        ));
    }
}
