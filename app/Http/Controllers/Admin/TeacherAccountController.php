<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherAccountRequest;
use App\Models\User;
use App\Support\AccountPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * إدارة حسابات المعلّمين — الشاشة الوحيدة التي تُنشأ منها الحسابات (S13).
 *
 * كلمة المرور تُعرض مرّة واحدة فقط بعد الإنشاء أو إعادة التعيين، ثم لا سبيل
 * لاستعادتها (مخزَّنة مُجزّأة). هذا مقصود: البديل هو حفظها بنص صريح في مكان ما
 * ليتمكّن المدير من مراجعتها لاحقًا — وهو أسوأ ما يمكن فعله بكلمات مرور.
 * فقدانها يعني إعادة تعيين، لا استرجاعًا.
 */
class TeacherAccountController extends Controller
{
    public function index()
    {
        $teachers = User::teachers()
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $admins = User::admins()->orderBy('name')->get();

        return view('admin.teachers.index', compact('teachers', 'admins'));
    }

    public function create()
    {
        return view('admin.teachers.create');
    }

    public function store(StoreTeacherAccountRequest $request)
    {
        $data = $request->validated();

        // كلمة مرور مولَّدة عند تركها فارغة — الحالة الغالبة. (?? لا ?: وحدها:
        // الحقل الاختياري غير المُرسَل لا يظهر في validated() أصلًا.)
        $plainPassword = ($data['password'] ?? null) ?: AccountPassword::generate();

        $teacher = User::create([
            'name'      => $data['name'],
            'username'  => $data['username'],
            'mosque'    => $data['mosque'] ?? null,
            'classroom' => $data['classroom'] ?? null,
            'role'      => User::ROLE_TEACHER,
            'is_active' => true,
            'password'  => Hash::make($plainPassword),
        ]);

        return redirect()
            ->route('admin.teachers.index')
            ->with('issued_credentials', [
                'name'     => $teacher->name,
                'username' => $teacher->username,
                'password' => $plainPassword,
                'context'  => 'created',
            ]);
    }

    /**
     * إعادة تعيين كلمة مرور معلّم — المسار الوحيد المتاح له عند نسيانها،
     * إذ لا استعادة ذاتية في النظام (بلا بريد ولا رابط).
     */
    public function resetPassword(Request $request, User $teacher)
    {
        $this->assertManageable($teacher);

        $validated = $request->validate(
            ['password' => ['nullable', 'string', 'min:6', 'max:100']],
            ['password.min' => 'كلمة المرور يجب أن تكون 6 رموز أو أكثر.']
        );

        $plainPassword = ($validated['password'] ?? null) ?: AccountPassword::generate();

        $teacher->forceFill(['password' => Hash::make($plainPassword)])->save();

        return redirect()
            ->route('admin.teachers.index')
            ->with('issued_credentials', [
                'name'     => $teacher->name,
                'username' => $teacher->username,
                'password' => $plainPassword,
                'context'  => 'reset',
            ]);
    }

    /**
     * تفعيل/تعطيل حساب — البديل الآمن عن الحذف: المعلّم المتوقّف يفقد الدخول
     * فورًا بينما يبقى سجلّ طلابه وحضورهم كما هو.
     */
    public function toggleActive(User $teacher)
    {
        $this->assertManageable($teacher);

        $teacher->forceFill(['is_active' => ! $teacher->is_active])->save();

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', $teacher->is_active
                ? "تم تفعيل حساب {$teacher->name}."
                : "تم تعطيل حساب {$teacher->name} — لن يتمكّن من الدخول.");
    }

    /**
     * حذف حساب معلّم.
     *
     * ممنوع ما دام له طلاب: مفتاح teacher_id في جدول الطلاب بـ onDelete('cascade')،
     * فحذف المعلّم يمحو طلابه وسجلّاتهم وحضورهم كلها بلا رجعة وبلا تحذير. التعطيل
     * هو التصرّف الصحيح لمعلّم توقّف، والحذف لحساب أُنشئ خطأً فقط.
     */
    public function destroy(User $teacher)
    {
        $this->assertManageable($teacher);

        $studentsCount = $teacher->students()->count();

        if ($studentsCount > 0) {
            return redirect()
                ->route('admin.teachers.index')
                ->withErrors(['delete' => "لا يمكن حذف {$teacher->name}: لديه {$studentsCount} طالبًا. عطّل الحساب بدل حذفه حفاظًا على سجلّاتهم."]);
        }

        try {
            $teacher->delete();
        } catch (RuntimeException $e) {
            return redirect()->route('admin.teachers.index')->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', "تم حذف حساب {$teacher->name}.");
    }

    /**
     * هذه الشاشة تدير حسابات المعلّمين فقط. حساب مدير آخر لا يُدار من هنا
     * (حراسة آخر مدير في الموديل تمنع الحالة الكارثية، وهذا يمنع الحالة
     * الفوضوية: مديران يعطّل أحدهما الآخر من الواجهة).
     */
    private function assertManageable(User $user): void
    {
        abort_unless($user->isTeacher(), 403, 'تُدار من هذه الشاشة حسابات المعلّمين فقط.');
    }
}
