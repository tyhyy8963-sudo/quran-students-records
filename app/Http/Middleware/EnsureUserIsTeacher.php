<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * منطقة المعلّم (S13).
 *
 * المدير ليس ممنوعًا هنا لسبب أمني بل لأن هذه الشاشات لا معنى لها في حسابه:
 * الطلاب والحضور والتقارير كلها معزولة بـTeacherScope على معلّمها، فحساب
 * المدير يراها فارغة دائمًا. التوجيه إلى لوحته أصدق من عرض شاشة فارغة
 * توحي بضياع البيانات (الرؤية المؤسسية الشاملة مقرَّرة في S16).
 */
class EnsureUserIsTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.teachers.index');
        }

        return $next($request);
    }
}
