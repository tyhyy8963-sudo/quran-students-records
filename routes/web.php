<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PoemController;
use App\Http\Controllers\Admin\TeacherAccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\RecitationLogController;
use App\Http\Controllers\PoemRecitationLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileEditController;
use App\Http\Controllers\PasswordController;

/* =========================================================================
 | منظومة مغلقة (S13)
 |
 | لا مسار تسجيل ذاتي، ولا استعادة كلمة مرور بالبريد، ولا أي مسار عام يُنشئ
 | حسابًا أو يغيّر بيانات اعتماد. الحسابات تُنشأ من لوحة المدير وحدها، وكلمة
 | المرور المنسيّة تُعاد من هناك أيضًا. لمن ضاع دخوله كمدير: أمر
 | `php artisan keshf:reset-password` من الخادم مباشرة، خارج الويب كليًا.
 |=========================================================================*/

/* الصفحة الرئيسية */
Route::get('/', function () {
    return view('welcome0');
});

/* ===== تسجيل الدخول ===== */
Route::get('/login', [LoginController::class, 'create'])->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');

/* ===== تسجيل الخروج ===== */
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/* =========================================================================
 | المنطقة المحمية
 |
 | auth.session يجعل Auth::logoutOtherDevices() فعّالًا: يقارن بصمة كلمة المرور
 | المحفوظة في الجلسة ببصمتها في قاعدة البيانات، فتسقط جلسات الأجهزة الأخرى
 | فور تغيير كلمة المرور.
 |
 | active: تعطيل حساب من لوحة المدير يسري على جلسة مفتوحة بالفعل، لا عند
 | محاولة الدخول التالية فقط.
 |=========================================================================*/
Route::middleware(['auth', 'auth.session', 'active'])->group(function () {

    /* ================= منطقة المدير ================= */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/teachers', [TeacherAccountController::class, 'index'])->name('teachers.index');
        Route::get('/teachers/create', [TeacherAccountController::class, 'create'])->name('teachers.create');
        Route::post('/teachers', [TeacherAccountController::class, 'store'])->name('teachers.store');

        Route::patch('/teachers/{teacher}/password', [TeacherAccountController::class, 'resetPassword'])
            ->middleware('throttle:password-update')
            ->name('teachers.password');

        Route::patch('/teachers/{teacher}/active', [TeacherAccountController::class, 'toggleActive'])->name('teachers.active');
        Route::delete('/teachers/{teacher}', [TeacherAccountController::class, 'destroy'])->name('teachers.destroy');

        /* مرجع المتون (S15) — إضافة فقط، لا حذف ولا تعديل من الواجهة. */
        Route::get('/poems', [PoemController::class, 'index'])->name('poems.index');
        Route::post('/poems', [PoemController::class, 'store'])->name('poems.store');
    });

    /* تغيير كلمة المرور — للمدير على حسابه فقط.
     | المعلّم لا يملك هذا المسار إطلاقًا (قرار مؤسسي صريح): كلمته تُعاد من
     | لوحة المدير، فلا شاشة ولا زرّ ولا مسار خلفي له. */
    Route::middleware('admin')->group(function () {
        Route::get('/edit-password', [PasswordController::class, 'edit'])->name('edit.password');

        Route::post('/edit-password/update', [PasswordController::class, 'update'])
            ->middleware('throttle:password-update')
            ->name('password.update');
    });

    /* ================= منطقة المعلّم ================= */
    Route::middleware('teacher')->group(function () {

        /* لوحة الطلاب — كان اسمها المسار "dashbord" (خطأ إملائي مترسّخ)، صُحِّح
         | إلى "dashboard" مع إعادة توجيه من الاسم القديم أسفل هذا الملف (S5). */
        Route::get('/dashboard', [StudentController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/create', [StudentController::class, 'store'])->name('students.store');
        Route::post('/dashboard/import', [StudentController::class, 'import'])->name('students.import');
        Route::patch('/dashboard/{id}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/dashboard/{id}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/dashboard/{id}/restore', [StudentController::class, 'restore'])->name('students.restore');

        /* صفحة سجلّ طالب واحد — الخط الزمني ومؤشر التقدّم (S8) */
        Route::get('/dashboard/{id}', [StudentController::class, 'show'])->name('students.show');

        /* الحلقات (S6) */
        Route::post('/circles', [CircleController::class, 'store'])->name('circles.store');
        Route::delete('/circles/{id}', [CircleController::class, 'destroy'])->name('circles.destroy');

        /* السجلّ الزمني لطالب (S7) */
        Route::post('/dashboard/{student}/logs', [RecitationLogController::class, 'store'])->name('logs.store');
        Route::delete('/dashboard/{student}/logs/{log}', [RecitationLogController::class, 'destroy'])->name('logs.destroy');

        /* السجلّ الزمني لحفظ/مراجعة متن (S15) — مستقلّ عن logs.* أعلاه */
        Route::post('/dashboard/{student}/poem-logs', [PoemRecitationLogController::class, 'store'])->name('poem-logs.store');
        Route::delete('/dashboard/{student}/poem-logs/{log}', [PoemRecitationLogController::class, 'destroy'])->name('poem-logs.destroy');

        /* الحضور — شاشة التحضير السريعة (S9) */
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

        /* لوحة التقارير والتصدير (S10) */
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/period', [ReportController::class, 'period'])->name('reports.period');
        Route::get('/reports/period/export', [ReportController::class, 'exportCsv'])->name('reports.period.export');
    });

    /* الملف الشخصي — للدورين معًا (بيانات عرض لا بيانات اعتماد) */
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileEditController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/edit', [ProfileEditController::class, 'update'])->name('profile.update');
});

/* =========================================================================
 | توافق مع الروابط القديمة (S5)
 |
 | "dashbord" كان خطأ إملائيًا في المسار واسم الراوت والملف — صُحِّح إلى
 | "dashboard" في كل مكان. هذا التوجيه فقط لمن حفظ الرابط القديم في متصفّحه.
 |=========================================================================*/
Route::get('/dashbord', function () {
    return redirect()->route('dashboard');
});
