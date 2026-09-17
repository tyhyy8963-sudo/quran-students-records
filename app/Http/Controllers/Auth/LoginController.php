<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * تسجيل الدخول في منظومة مغلقة (S13).
 *
 * المعرّف اسم مستخدم لا بريد إلكتروني: لا حساب يُنشأ ذاتيًا، ولا رسالة تُرسَل
 * إلى أحد، فالبريد لم يعد له وظيفة واحدة في النظام (وقد حُذف عموده أصلًا).
 *
 * شرط is_active يُفحص هنا لا في الاستعلام وحده: لو مُرِّر مع بيانات الاعتماد
 * إلى Auth::attempt() لأصبح حساب معلّم معطَّل يعطي نفس رسالة "كلمة مرور خاطئة"،
 * فيظنّ المعلّم أن المشكلة في كلمته ويكرّر المحاولة. الفصل يسمح برسالة صادقة
 * تدلّه على مراجعة المدير.
 */
class LoginController extends Controller
{
    public function create()
    {
        return view('login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'الرجاء إدخال اسم المستخدم.',
            'password.required' => 'الرجاء إدخال كلمة المرور.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // route('login') لا back(): back() تعتمد على ترويسة المُحيل، وهي
            // غائبة في بعض العملاء (وفي أي طلب مباشر)، فتقذف المستخدم إلى
            // الصفحة الرئيسية ومعه رسالة خطأ لا تُعرض فيها أصلًا.
            return redirect()->route('login')
                ->withInput($request->only('username'))
                ->with('error', 'اسم المستخدم أو كلمة المرور غير صحيحة');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // route('login') لا back(): back() تعتمد على ترويسة المُحيل، وهي
            // غائبة في بعض العملاء (وفي أي طلب مباشر)، فتقذف المستخدم إلى
            // الصفحة الرئيسية ومعه رسالة خطأ لا تُعرض فيها أصلًا.
            return redirect()->route('login')
                ->withInput($request->only('username'))
                ->with('error', 'هذا الحساب معطَّل. راجع مدير النظام لإعادة تفعيله.');
        }

        $request->session()->regenerate();

        // آخر دخول: يخدم متابعة الإدارة لمن لم يستخدم النظام منذ مدّة (يظهر في
        // لوحة المدير)، ويُحدَّث بلا لمس updated_at حتى لا يُقرأ تعديلًا للحساب.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.teachers.index') : route('dashboard')
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
