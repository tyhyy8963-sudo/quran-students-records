<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * تعطيل الحساب يسري فورًا (S13).
 *
 * فحص is_active عند الدخول وحده لا يكفي: المعلّم الذي عطّله المدير قد تكون
 * جلسته مفتوحة على هاتفه منذ الصباح، فيواصل التسجيل والتعديل حتى تنتهي الجلسة
 * وحدها. هذا الوسيط يُنهي جلسته عند أول طلب بعد التعطيل.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'تم تعطيل هذا الحساب. راجع مدير النظام.');
        }

        return $next($request);
    }
}
