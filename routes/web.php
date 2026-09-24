<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\PoemController;
use App\Http\Controllers\Admin\TeacherAccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\PoemBoardController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\RecitationLogController;
use App\Http\Controllers\PoemRecitationLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RecordsController;
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
        /* لوحة الإحصاءات المؤسسية (S20) — راجع AdminOverviewController للسياق
         | الكامل: كانت غائبة كليًا قبل هذا السبرنت. */
        Route::get('/overview', [AdminOverviewController::class, 'index'])->name('overview');

        /* تصدير نظرة عامة على كل المعلّمين معًا (S37 — بند 4 من خطّة التقارير
         | المعتمَدة): جدول مقارنة المعلّمين نفسه (AdminOverviewController::index()
         | → $teacherStats) بصيغة CSV — الطباعة تستعمل خاصية طباعة المتصفّح
         | مباشرة على الصفحة نفسها (@media print)، بلا مسار مستقلّ لها. */
        Route::get('/overview/export', [AdminOverviewController::class, 'exportCsv'])->name('overview.export');

        Route::get('/teachers', [TeacherAccountController::class, 'index'])->name('teachers.index');
        Route::get('/teachers/create', [TeacherAccountController::class, 'create'])->name('teachers.create');
        Route::post('/teachers', [TeacherAccountController::class, 'store'])->name('teachers.store');

        /* عرض سجلّات معلّم محدَّد (S20) — قراءة فقط، منفصل عمدًا عن مسارات
         | إدارة الحساب (password/active/destroy) أعلاه التي تُغيّر بيانات
         | اعتماد فعلية. */
        Route::get('/teachers/{teacher}/report', [AdminOverviewController::class, 'show'])->name('teachers.report');

        /* تصدير جدول طلاب معلّم واحد من شاشة "سجلّ المعلّم" (S37 — بند 4) —
         | نفس صفوف الطلاب المعروضة في admin.teacher-report بالضبط. */
        Route::get('/teachers/{teacher}/report/export', [AdminOverviewController::class, 'exportTeacherCsv'])->name('teachers.report.export');

        Route::patch('/teachers/{teacher}/password', [TeacherAccountController::class, 'resetPassword'])
            ->middleware('throttle:password-update')
            ->name('teachers.password');

        /* تعديل اسم مستخدم معلّم (طلب صريح من يحيى: التعديل من لوحة الأدمن
         | كان يشمل كلمة المرور فقط — لا اسم المستخدم). نفس قواعد التحقّق
         | المستعملة عند إنشاء الحساب (StoreTeacherAccountRequest::rules())،
         | باستثناء تجاهل اسم المستخدم الحالي لنفس المعلّم في فحص التفرّد. */
        Route::patch('/teachers/{teacher}/username', [TeacherAccountController::class, 'updateUsername'])->name('teachers.username');

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

        /* تصدير سجلّ طالب واحد كاملًا (S37 — القرار #54-التقارير، بند 2 من
         | خطّة التقارير المعتمَدة): صفحة قراءة/طباعة مستقلّة بكل بيانات الطالب
         | (حفظ + مراجعة + حضور + متون) دفعة واحدة، بلا ترقيم صفحات كما في
         | صفحته الرئيسية، + تنزيل CSV لنفس السجلّات. */
        Route::get('/dashboard/{id}/report', [StudentController::class, 'report'])->name('students.report');
        Route::get('/dashboard/{id}/report/export', [StudentController::class, 'reportExportCsv'])->name('students.report.export');

        /* لوحة طلاب خاصة بالمتون (S24، الجزء الثاني) — عكس صفحة الطالب: لكل
         | متن (تبويب)، من يتتبّعه فعلاً من الطلاب، مع تسجيل سريع من الصفّ
         | مباشرة. راجع PoemBoardController للتفصيل الكامل. منفصلة كليًا عن
         | admin.poems.index (مرجع المتون نفسه). */
        Route::get('/poems', [PoemBoardController::class, 'index'])->name('poems.index');

        /* الحلقات (S6، وصفحة مستقلّة بطلب صريح من يحيى 2026-09-23: تبويب
         | "الحلقات" في أعلى الصفحة بدل زرّ "⚙ إدارة الحلقات" داخل لوحة
         | القرآن — راجع CircleController::index() وresources/views/circles/index.blade.php. */
        Route::get('/circles', [CircleController::class, 'index'])->name('circles.index');
        Route::post('/circles', [CircleController::class, 'store'])->name('circles.store');
        Route::delete('/circles/{id}', [CircleController::class, 'destroy'])->name('circles.destroy');

        /* السجلّ الزمني لطالب (S7) */
        Route::post('/dashboard/{student}/logs', [RecitationLogController::class, 'store'])->name('logs.store');
        // (تصحيح صريح من يحيى): أيقونة تعديل ✎ بدل ✕ — راجع تعليق RecitationLogController::update().
        Route::match(['put', 'patch'], '/dashboard/{student}/logs/{log}', [RecitationLogController::class, 'update'])->name('logs.update');
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
        /* تصدير إكسل حقيقي .xlsx بتخطيط موسّع (S17) — راجع ReportController::exportXlsx(). */
        Route::get('/reports/period/export-xlsx', [ReportController::class, 'exportXlsx'])->name('reports.period.exportXlsx');

        /* تقرير المتون المستقلّ (S37 — بند 1 من خطّة التقارير المعتمَدة):
         | نفس فلاتر لوحة "المتون" (متن/حلقة/نطاق نسبة، PoemBoardController)
         | لكن صفّ واحد لكل (طالب، متن) — تخطيط جدولي مسطّح يصلح للطباعة
         | والتصدير، بخلاف لوحة المتون التفاعلية (صفّ واحد للطالب بعدّة شارات). */
        Route::get('/reports/poems', [ReportController::class, 'poems'])->name('reports.poems');
        Route::get('/reports/poems/export', [ReportController::class, 'poemsExportCsv'])->name('reports.poems.export');

        /* تقرير الحضور والغياب المستقلّ (S37 — بند 3): تطوير لمؤشّر "طلاب
         | بحاجة لمتابعة" الصغير في لوحة التقارير إلى تقرير كامل — نسب تفصيلية
         | لكل طالب خلال فترة + قائمة كاملة قابلة للتصدير والطباعة. */
        Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/attendance/export', [ReportController::class, 'attendanceExportCsv'])->name('reports.attendance.export');

        /* تبويب "السجلات" (S26) — عرض موحَّد لسجلّات كل الطلاب (حفظ + مراجعة
         | + متون + حضور معًا)، بفلترة اسم الطالب والحلقة فقط. طلب صريح من
         | يحيى، منفصل عن لوحة التقارير الإحصائية أعلاه. راجع RecordsController
         | للتفصيل الكامل. */
        Route::get('/records', [RecordsController::class, 'index'])->name('records.index');
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
