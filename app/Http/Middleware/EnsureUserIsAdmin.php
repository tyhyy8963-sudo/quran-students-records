<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * منطقة المدير (S13).
 *
 * 403 لا إعادة توجيه صامتة: المعلّم الذي يفتح رابط إدارة الحسابات يجب أن يعرف
 * أن المسار موجود لكنه ليس له، لا أن يُقذف إلى شاشة أخرى بلا سبب ظاهر.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'هذه الصفحة مخصّصة لمدير النظام.');
        }

        return $next($request);
    }
}
