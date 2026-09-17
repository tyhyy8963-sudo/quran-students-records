<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * توحيد شكل استجابات الأخطاء لطلبات JSON بالعربية (S5 · B-07).
     *
     * بعض استثناءات إطار العمل (حدّ المحاولات، انعدام التوثيق، الصفحة غير
     * الموجودة) تحمل رسائل إنجليزية مُدمجة في الكود لا تمرّ عبر ملفات lang،
     * فتبقى إنجليزية مهما تغيّرت 'locale'. هذا التابع يعيد صياغتها فقط،
     * دون المساس بشكل {message, errors} القياسي الذي يبنيه Laravel أصلًا
     * لاستثناء التحقق (422) — فذاك يبقى كما هو.
     */
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()) {
            if ($e instanceof ThrottleRequestsException) {
                return new JsonResponse([
                    'message' => 'محاولات كثيرة جدًا. الرجاء الانتظار قليلًا ثم إعادة المحاولة.',
                ], 429, $e->getHeaders());
            }

            if ($e instanceof AuthenticationException) {
                return new JsonResponse([
                    'message' => 'يجب تسجيل الدخول للوصول إلى هذا المورد.',
                ], 401);
            }

            if ($e instanceof NotFoundHttpException) {
                return new JsonResponse([
                    'message' => 'العنصر المطلوب غير موجود.',
                ], 404);
            }
        }

        return parent::render($request, $e);
    }
}
