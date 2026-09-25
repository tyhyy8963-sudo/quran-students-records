<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * تبويب "السجلات" (S26).
 *
 * ⚠️ إعادة كتابة كاملة (2026-09-23) بعد أن وضّح يحيى أن التنفيذ الأوّل (قائمة
 * زمنية موحَّدة تدمج حفظ/مراجعة/متون/حضور لكل الطلاب معًا) كان فهمًا خاطئًا
 * لمقصده. نصّه الحرفي: "لا لا انت مش فاهم فكرة السجلات السجل الواحد للطالب
 * عبارة عن صورة رسلتها لك فأبغى التبويب يكون فيه كل الطلاب وأقدر أخش على
 * سجلاتهم ومن هناك يكون إضافة طالب جديد" — مرفقًا صورتين لصفحة الطالب الحالية
 * (students.show) كمرجع لمعنى "السجلّ الواحد". بعد سؤال توضيحي أكّد الخيار:
 * **دليل بسيط بكل الطلاب** (اسم + رابط "سجلّ" يفتح صفحة الطالب الكاملة +
 * إمكانية إضافة طالب جديد من نفس الصفحة) — لا محتوى مدمَج من عدّة جداول كما
 * في المحاولة الأولى.
 *
 * الفلترة (بحث بالاسم + الحلقة، بنفس نمط StudentController::index() حرفيًا)
 * بقيت كما اتُّفق عليها أصلًا (القرار #37) — لم تكن جزءًا من سوء الفهم، فقط
 * "محتوى" التبويب هو ما تغيّر.
 */
class RecordsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        // نفس نمط الاختيار المتعدّد للحلقة + "بلا حلقة" المستعمل حرفيًا في
        // StudentController::index()/PoemBoardController::index() — تناسقًا
        // مع بقية الصفحات.
        $circleIds = array_values(array_filter(
            (array) $request->query('circle_id', []),
            fn ($v) => $v !== null && $v !== ''
        ));

        $circles = Circle::orderBy('name')->get();

        $query = Student::with('circle')->orderBy('student_name');

        if ($search !== '') {
            $query->where('student_name', 'like', '%'.$search.'%');
        }

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

        // نفس حجم الصفحة المستعمل أصلًا في StudentController::index() (50) —
        // تناسقًا مع لوحة "قرآن"، لا قرارًا منتجًا مستقلًا.
        $students = $query->paginate(50)->withQueryString();

        // شارة عدد الطلاب في الشريط العلوي (طلب صريح من يحيى: "ضيف عداد عدد
        // الطلاب الموجود في تبويب القرآن لبقية التبويبات") — نفس استعلام
        // StudentController::index() حرفيًا.
        $studentsCount = Student::count();

        return view('records.index', [
            'students'      => $students,
            'circles'       => $circles,
            'search'        => $search,
            'circleIds'     => $circleIds,
            'studentsCount' => $studentsCount,
        ]);
    }
}
