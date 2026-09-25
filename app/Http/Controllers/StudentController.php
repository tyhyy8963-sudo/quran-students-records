<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Poem;
use App\Models\PoemChapter;
use App\Models\PoemRecitationLog;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\Surah;
use App\Support\MemorizationProgress;
use App\Support\PoemProgress;
use App\Support\QuranUnitReference;
use App\Support\ReviewProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * عرض اللوحة الرئيسية الموحّدة — لوحة الطلاب.
     *
     * بحث بالاسم + فلترة متعدّدة الاختيار بالحلقة والحالة + نطاق تاريخ نشاط
     * + نطاق نسبة تقدّم + حضور اليوم (S18، فوق فلترة S11 الفردية) + فرز عبر
     * رأس الأعمدة، فوق ترقيم الصفحات — لا ترتيب ثابت وحيد بعد الآن، لكن
     * الاسم يبقى الافتراضي. عزل بيانات المعلّم يجري تلقائيًا عبر TeacherScope
     * على الموديل، لا شرطًا مكتوبًا هنا (B-04).
     *
     * منذ S23 (تصحيح): هذه هي الصفحة التي كان يقصدها يحيى دائمًا بـ"الصفحة
     * الرئيسية للدرس والمراجعة" — كانت أُضيفت بالخطأ إلى /attendance بناءً
     * على فهم خاطئ لمكان "الرئيسية"؛ صحّح يحيى صراحةً: "طول الفترة الماضية
     * [كنت أقصد] صفحة إلي اسمها الطلاب". الجدول هنا يضيف الآن عمودَي
     * "الدرس"/"المراجعة" (حالة اليوم لكل نوع) وعمود "حضور اليوم" (يحلّ محلّ
     * محرِّر حالة العضوية القديم في نفس الخلية — تلك الحالة انتقلت لصفحة
     * الطالب نفسها)، وتبويبات حلقات فعلية فوق الجدول — كل أدوات الإدارة
     * الحالية (البحث والفلاتر وإدارة الحلقات والاستيراد) تبقى بلا أي تغيير.
     *
     * تحميل مسبق لـ circle وlatestMemorizationLog.surah وlatestReviewLog.surah
     * (S6/S7/S18) يمنع استعلامًا إضافيًا لكل طالب عند حساب الموضع التالي
     * للاستكمال التلقائي في نافذة تسجيل الدرس/المراجعة (N+1).
     */
    public function index(Request $request)
    {
        $query = Student::with(['circle', 'latestMemorizationLog.surah', 'latestReviewLog.surah']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where('student_name', 'like', '%'.$search.'%');
        }

        // اختيار متعدّد للحلقة والحالة معًا (S18) — كانت فلترة S11 تقصر
        // الاختيار على قيمة واحدة فقط، فمعلّم يريد رؤية حلقتين أو حالتين معًا
        // يُضطرّ لبحثين منفصلين. (array) يحوّل القيمة القديمة الفردية
        // (?circle_id=5) لعنصر واحد تلقائيًا، فلا ينكسر أي رابط أو اختبار قديم
        // يستعمل الصيغة الفردية.
        $circleIds = array_values(array_filter(
            (array) $request->query('circle_id', []),
            fn ($v) => $v !== null && $v !== ''
        ));

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

        $statuses = array_values(array_filter(
            (array) $request->query('status', []),
            fn ($v) => $v !== null && $v !== ''
        ));

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        // نطاق تاريخ نشاط (S18): "نشاط" = أي سطر حفظ/مراجعة/تسميع مسجَّل
        // للطالب — لا الحضور (فلتر مستقلّ أدناه) — يفيد إيجاد من له نشاط في
        // فترة معيّنة (حصة أمس، أسبوع قبل الاختبار...).
        $activityFrom = $request->query('activity_from');
        $activityTo = $request->query('activity_to');

        if ($activityFrom || $activityTo) {
            $query->whereHas('recitationLogs', function ($q) use ($activityFrom, $activityTo) {
                if ($activityFrom) {
                    $q->whereDate('logged_at', '>=', $activityFrom);
                }
                if ($activityTo) {
                    $q->whereDate('logged_at', '<=', $activityTo);
                }
            });
        }

        // فلترة بحضور اليوم تحديدًا (S18) — "لم يُسجَّل" حالة خامسة لا تقابل
        // أي صفّ فعلي في Attendance::STATUSES: غياب سطر اليوم كليًا من الجدول،
        // لا قيمة status مخزَّنة.
        $attendanceToday = $request->query('attendance_today');
        $today = now()->toDateString();

        if ($attendanceToday === 'not_recorded') {
            $query->whereDoesntHave('attendances', fn ($q) => $q->whereDate('date', $today));
        } elseif ($attendanceToday && array_key_exists($attendanceToday, Attendance::STATUSES)) {
            $query->whereHas('attendances', fn ($q) => $q->whereDate('date', $today)->where('status', $attendanceToday));
        }

        $sort = $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'status') {
            $query->orderBy('status', $dir)->orderBy('student_name');
        } else {
            $query->orderBy('student_name', $dir);
        }

        // نطاق نسبة تقدّم دنيا/قصوى (S18) — النسبة محسوبة في PHP (أوزان
        // الأرباع الـ240، MemorizationProgress) لا عمودًا مخزَّنًا يُفلتَر
        // بـWHERE مباشرة في القاعدة. حين يُطلَب هذا الفلتر تُجلَب كل الصفوف
        // المطابقة لبقية الفلاتر معًا دفعة واحدة (بلا ترقيم صفحات على مستوى
        // القاعدة)، تُحسَب نسبها كلّها معًا عبر warmFor()، ثم تُرشَّح وتُرقَّم
        // يدويًا — الترتيب الوحيد الصحيح الممكن هنا: ترقيم صفحات القاعدة قبل
        // حساب النسبة قد يُقصي طالبًا مطابقًا فعليًا لمجرّد أن اسمه يسبق غيره
        // أبجديًا فيقع خارج الصفحة الأولى.
        $progressMin = $request->query('progress_min');
        $progressMax = $request->query('progress_max');
        $hasProgressFilter = ($progressMin !== null && $progressMin !== '') || ($progressMax !== null && $progressMax !== '');

        // فلتر أولوية حالة الحفظ/الحضور اليوم (S23، بند 9 من تقرير التطوير) —
        // قائمتان مستقلّتان تعملان معًا أو منفردتين، تُعيدان ترتيب الطلاب
        // المطابقين إلى أعلى القائمة بلا إخفاء أحد (هذا "ترتيب" لا "استبعاد":
        // اللوحة الرئيسية للعمل اليومي على كل الطلاب، فإخفاء طالب منها لمجرّد
        // حالته يتعارض مع غرضها). نفس مبدأ فلتر نسبة التقدّم أعلاه بالضبط:
        // حين يُفعَّل الترتيب تُجلَب كل الصفوف المطابقة لبقية الفلاتر معًا
        // دفعة واحدة (بلا ترقيم صفحات على مستوى القاعدة)، تُرتَّب في الذاكرة،
        // ثم تُرقَّم يدويًا — ترقيم صفحات القاعدة قبل الترتيب قد يُبقي طالبًا
        // ذا أولوية خارج الصفحة الأولى تمامًا.
        $memorizationPriority = $request->query('memorization_priority');
        $attendancePriority = $request->query('attendance_priority');
        $hasPriority = $memorizationPriority || $attendancePriority;

        $today = now()->toDateString();
        $progress = app(MemorizationProgress::class);

        if ($hasProgressFilter || $hasPriority) {
            $allMatching = $query->get();
            $progress->warmFor($allMatching);

            if ($hasProgressFilter) {
                $min = ($progressMin !== null && $progressMin !== '') ? (float) $progressMin : 0.0;
                $max = ($progressMax !== null && $progressMax !== '') ? (float) $progressMax : 100.0;

                $allMatching = $allMatching
                    ->filter(fn (Student $s) => $s->progressPercentage() >= $min && $s->progressPercentage() <= $max)
                    ->values();
            }

            if ($hasPriority) {
                $allIds = $allMatching->pluck('student_id');
                $attendanceForSort = Attendance::todayStatusFor($allIds);
                $logsForSort = RecitationLog::todayLogsFor($allIds, $today);

                // تطابق الحضور له وزن أعلى من تطابق الحفظ فقط لترتيب ثابت
                // متوقَّع حين يُفعَّل الفلتران معًا؛ sortByDesc في Collection
                // مستقرّ (يحافظ على الترتيب الأصلي عند تساوي النقاط).
                $allMatching = $allMatching->sortByDesc(function (Student $s) use (
                    $memorizationPriority, $attendancePriority, $attendanceForSort, $logsForSort
                ) {
                    $score = 0;
                    $todayAttendance = $attendanceForSort[$s->student_id] ?? null;
                    $todayLesson = $logsForSort[$s->student_id]['حفظ'] ?? null;
                    $lessonState = $todayLesson === null
                        ? 'لم يسمع بعد'
                        : ($todayLesson->status === 'غير حافظ' ? 'غير حافظ' : 'حافظ');

                    if ($attendancePriority && $todayAttendance === $attendancePriority) {
                        $score += 2;
                    }
                    if ($memorizationPriority && $lessonState === $memorizationPriority) {
                        $score += 1;
                    }

                    return $score;
                })->values();
            }

            $page = max(1, (int) $request->query('page', 1));
            $perPage = 50;

            $students = new \Illuminate\Pagination\LengthAwarePaginator(
                $allMatching->forPage($page, $perPage)->values(),
                $allMatching->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );
            $students->appends($request->query());
        } else {
            $students = $query->paginate(50)->withQueryString();

            // تحميل تغطية الحفظ لكل طلاب الصفحة باستعلام واحد قبل بناء الصفوف
            // (S14) — بلا هذا يصبح لكل صفّ استعلامه عند قراءة نسبته.
            $progress->warmFor($students->getCollection());
        }

        $studentsCount = Student::count();
        $circles = Circle::orderBy('name')->get();

        // ترتيب الحفظ لا ترتيب المصحف (S14): قائمة السور في اللوحة تبدأ
        // بالناس صعودًا، لأن هذا هو التسلسل الذي يختار منه المعلّم فعلًا،
        // والفاتحة في آخرها لأنها لا تُحتسب في النسبة. memorization_order لازم
        // هنا (S23) لحساب الموضع التالي عبر nextPosition() أدناه — لم تكن
        // مطلوبة في هذا الاستعلام قبل انتقال منطق الاستكمال التلقائي إليه.
        $surahs = Surah::inMemorizationOrder()->get(['id', 'name', 'ayah_count', 'excluded_from_progress', 'memorization_order']);

        $pageStudentIds = $students->pluck('student_id');

        // (S24، الجزء الثالث — طلب صريح من يحيى بعد رؤية لوحة "قرآن" فعليًا):
        // شارة "انقطاع" (مؤشّر غياب جلستَين متتاليتَين) وشارة "عدد المتون
        // المتتبَّعة" كانتا تُعرَضان هنا تحت اسم الطالب — يحيى: "حالة الطالب...
        // المفترض هنا مش مكانها" و"لو كان عند الطالب متن ليش يطلع المتن تحت
        // اسم الطالب؟!". حُذف حسابهما من هنا نهائيًا؛ استعلام Attendance::
        // alertsFor() نفسه لا يزال يغذّي قائمة الانتباه في لوحة التقارير
        // (ReportController::index())، وStudent::trackedPoemCountsFor() لا
        // يزال يغذّي عمود التصدير في التقارير — لم يُمَسّا، فقط استعمالهما هنا.
        $attendanceTodayByStudent = Attendance::todayStatusFor($pageStudentIds);

        // سجلّا اليوم (حفظ/مراجعة) لكل طالب من طلاب الصفحة (S23) — يغذّي
        // عمودَي "الدرس"/"المراجعة" الجديدين، بنفس مبدأ الأسطر أعلاه.
        $todayLogsByStudent = RecitationLog::todayLogsFor($pageStudentIds, $today);

        // (القرار #54): وحدات الجزء/الحزب/نصف الحزب/ربع الحزب الجاهزة لنموذج
        // تسجيل المراجعة — مرجع ثابت لا يعتمد على طلاب الصفحة، يُحسَب مرّة
        // واحدة فقط ويُمرَّر كاملًا للواجهة (تُبنى قوائم "من"/"إلى" في JS منه
        // مباشرة، بلا أي طلب شبكة إضافي عند تبديل نوع الوحدة).
        $quranUnits = app(QuranUnitReference::class)->all();

        // الموضع التالي المقترَح لكل طالب (S23) — يملأ نافذة تسجيل الدرس/
        // المراجعة تلقائيًا فلا يُعيد المعلّم إدخاله من الصفر؛ يُحسَب من آخر
        // سجلّ فعلي على الإطلاق (latestMemorizationLog/latestReviewLog
        // المحمَّلتان مسبقًا أعلاه) لا من سجلّ اليوم، فيبقى الاقتراح صحيحًا
        // حتى لو لم يُسجَّل شيء اليوم بعد.
        $nextPositions = [];
        foreach ($students as $s) {
            $nextPositions[$s->student_id] = [
                'lesson' => $this->nextPosition($s->latestMemorizationLog, $surahs),
                'review' => $this->nextPosition($s->latestReviewLog, $surahs),
            ];
        }

        return view('dashboard', compact(
            'students', 'studentsCount', 'circles', 'surahs', 'sort', 'dir',
            'attendanceTodayByStudent', 'todayLogsByStudent', 'quranUnits',
            'nextPositions', 'today', 'memorizationPriority', 'attendancePriority'
        ));
    }

    /**
     * موضع الاستكمال التلقائي (S23، منقول من AttendanceController بعد تصحيح
     * مكان القائمة الموحّدة — بند 2/3 من تقرير التطوير: "حفظ آخر بيانات
     * للدرس/المراجعة تلقائيًا فلا يعيد المعلّم إدخالها من الصفر") — الآية التي
     * تلي مباشرة آخر سجلّ من نفس النوع. لا يُلزِم المعلّم بهذا الموضع، فقط
     * يملأ نافذة التسجيل مسبقًا فيبقى التعديل اليدوي متاحًا دائمًا.
     *
     * تجاوز نهاية السورة (أتمّ الطالب السورة بالضبط أمس مثلًا) ينتقل لبداية
     * السورة التالية بترتيب الحفظ المعكوس (نفس تسلسل الحفظ الفعلي في هذا
     * البرنامج، لا ترتيب المصحف)، وإلا يبقى في نفس السورة بلا تخمين اتجاه آخر.
     *
     * @return array{surah_id: int, ayah: int}|null
     */
    private function nextPosition(?RecitationLog $latest, Collection $surahs): ?array
    {
        if ($latest === null) {
            return null;
        }

        $surahId = $latest->to_surah_id ?? $latest->surah_id;
        $surah = $surahs->firstWhere('id', $surahId);

        if ($surah === null) {
            return null;
        }

        $nextAyah = (int) $latest->to_ayah + 1;

        if ($nextAyah <= $surah->ayah_count) {
            return ['surah_id' => $surah->id, 'ayah' => $nextAyah];
        }

        // السورة اكتملت بالضبط: التالية بترتيب الحفظ المعكوس (memorization_order)،
        // أو البقاء في نفس السورة (آخر آية) لو لم يكن لها ترتيب حفظ مضبوط
        // (الفاتحة مثلاً) — لا تخمين لاتجاه لا يملك أساسًا واضحًا في البيانات.
        if ($surah->memorization_order === null) {
            return ['surah_id' => $surah->id, 'ayah' => $surah->ayah_count];
        }

        $next = $surahs
            ->where('memorization_order', '>', $surah->memorization_order)
            ->sortBy('memorization_order')
            ->first();

        if ($next === null) {
            // آخر سورة في تسلسل الحفظ (البقرة) اكتملت بالضبط: لا "تالية" فعلًا.
            return ['surah_id' => $surah->id, 'ayah' => $surah->ayah_count];
        }

        return ['surah_id' => $next->id, 'ayah' => 1];
    }

    /**
     * صفحة سجلّ طالب واحد — الخط الزمني الكامل ومؤشر التقدّم (S8).
     *
     * منذ S15 تحمل البطاقة الأولى أيضًا آخر موضع مراجعة (لا الحفظ فقط)،
     * وقسمًا كاملًا للمتون التي يتتبّعها الطالب (تتبّع متوازٍ: أيّ عدد من
     * الخمسة معًا، لا متن واحد نشط في كل مرة).
     */
    public function show($id)
    {
        $student = Student::with(['circle', 'latestMemorizationLog.surah', 'latestReviewLog.surah'])->findOrFail($id);

        $this->authorize('update', $student);

        // (تصحيح خلل، بلاغ يحيى: "المفترض أن يكون السجل الزمني شاملًا للقرآن
        // درس ومراجعة وللمتون حفظ ومراجعة") — كانت "السجلّات الأخيرة" تعرض
        // سجلّات القرآن فقط (recitation_logs)، فتختفي منها كل سجلّات المتون
        // (poem_recitation_logs) رغم ظهورها في قسم "المتون" أعلى الصفحة. صارت
        // الآن قائمة واحدة مدموجة من الجدولين معًا، مرتَّبة بالتاريخ تنازليًا
        // (logged_at ثم id، نفس ترتيب كلا العلاقتين في Student) ثم مقسَّمة
        // صفحات يدويًا — جدولان مختلفان لا يمكن ترقيمهما معًا على مستوى
        // القاعدة مباشرة، فيُجلبان كاملَين (سجلّات طالب واحد، عدد محدود
        // بطبيعته) ويُدمَجان في الذاكرة، بنفس أسلوب الترقيم اليدوي المستعمَل
        // أصلًا في index() لحالة الفلترة المتقدّمة.
        $quranLogs = $student->recitationLogs()->with(['surah', 'toSurah'])->get();
        $poemLogsForTimeline = $student->poemRecitationLogs()->with('poem')->get();

        $allLogs = $quranLogs->concat($poemLogsForTimeline)
            ->sortByDesc(fn ($log) => $log->logged_at->format('Y-m-d').'-'.str_pad($log->id, 10, '0', STR_PAD_LEFT))
            ->values();

        // (S25 — بطلب صريح من يحيى: "السجلّات الأخيرة يجمع لك كل التسجيلات
        // مهما كان عددها، المفترض يكون في القائمة الوحدة 12 ... بعدين فيه
        // قائمة ثانية فيها 12 أو الباقي"): كانت 20 سجلًا لكل صفحة — صارت 12
        // كما طلب، والترقيم القائم أصلًا (Paginator) يتكفّل تلقائيًا بصفحة
        // ثانية للباقي (أو أقل من 12 لو كان هذا آخر ما تبقّى).
        $logsPage = max(1, (int) request()->query('page', 1));
        $logsPerPage = 12;
        $logs = new \Illuminate\Pagination\LengthAwarePaginator(
            $allLogs->forPage($logsPage, $logsPerPage)->values(),
            $allLogs->count(),
            $logsPerPage,
            $logsPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
        $logs->appends(request()->query());

        $surahs = Surah::inMemorizationOrder()->get(['id', 'number', 'name', 'ayah_count', 'memorization_order', 'excluded_from_progress']);

        // المنحنى صار بالنسبة المئوية لا بعدد الآيات التراكمي (S14): إعادة
        // تشغيل السجلّ زمنيًا داخل MemorizationProgress::timeline() تعطي نسبة
        // الطالب كما كانت في كل يوم فعلًا — بنفس الصيغة المعروضة في الحلقة
        // أعلى الصفحة، فلا يقرأ المعلّم رقمين مختلفين لنفس الشيء.
        $progress = app(MemorizationProgress::class);
        $chartPoints = $progress->timeline($student);

        $month = request()->query('month');
        try {
            $monthStart = $month ? Carbon::parse($month.'-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Exception $e) {
            $monthStart = now()->startOfMonth();
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        // whereDate لا whereBetween: نفس عطل كاست "date" الموثَّق في
        // AttendanceController::store() — whereBetween يقارن نص التاريخ الخام
        // فيستثني سطر آخر يوم في الشهر على SQLite لأن قيمته المخزَّنة تحمل
        // وقتًا خلف التاريخ يجعلها "أكبر" نصيًا من حد النهاية بلا وقت.
        $attendanceByDate = $student->attendances()
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->get()
            ->keyBy(fn (Attendance $a) => $a->date->toDateString());

        $completedSurahs = $progress->completedSurahs($student);
        $furthestSurah = $progress->furthestSurah($student);

        // بطاقات إحصائية أعلى الصفحة (S23.5 — إعادة التصميم بنمط هرماس، مرحلة
        // مستأنَفة بعد توقّف مؤقّت لصالح المتون/التقارير): مخطّط يحيى المرجعي
        // يعرض أرقامًا صريحة (لا نسبًا فقط) لأيام الحضور وإجمالي المراجعات —
        // عدّان بسيطان من بيانات موجودة أصلًا في القاعدة، لا ميزة جديدة.
        // "إجمالي السور" يُعاد استعمال $completedSurahs أعلاه لنفس الغرض بلا
        // استعلام إضافي. عدّ إجمالي (كل الوقت) لا مقيَّد بالشهر المعروض في
        // التقويم أسفله عمدًا — الغرض هنا ملخّص عام لا مرتبط بنطاق تقويم شهر
        // واحد قد يتغيّر بالتصفّح.
        $attendanceDaysCount = $student->attendances()->where('status', 'حاضر')->count();
        $totalReviewsCount = $student->recitationLogs()->where('type', 'مراجعة')->count();

        // نسبة المراجعة (S16): موزونة بالأرباع الـ240 مثل الحفظ تمامًا (راجع
        // ReviewProgress)، مع عدد الأرباع المكتملة بالكامل مقسَّمًا إلى أحزاب
        // وأرباع متبقّية — عرض أوضح تربويًا من نسبة مئوية مجرّدة وحدها.
        $reviewProgress = app(ReviewProgress::class);
        $reviewPercent = $reviewProgress->percentage($student);
        $reviewQuartersDone = $reviewProgress->quartersFullyReviewed($student);
        $reviewHizbDone = intdiv($reviewQuartersDone, 4);
        $reviewQuarterRemainder = $reviewQuartersDone % 4;

        // منحنى تقدّم المراجعة (S23.5 — إعادة التصميم بنمط هرماس؛ طلب صريح
        // من يحيى بعد عرض التعارض عليه: لا يوجد منحنى تراكمي لنسبة المراجعة
        // عبر الزمن مقابل منحنى الحفظ الموجود أصلًا، فقط أعمدة شهرية — راجع
        // ReviewProgress::timeline() الجديدة، نفس مبدأ منحنى الحفظ حرفيًا).
        // (تعديل لاحق، نفس S23.5): طلب يحيى إلغاء بطاقة "نشاط المراجعة
        // الشهري" (الأعمدة الشهرية) من هذه الصفحة نهائيًا والإبقاء فقط على
        // منحنيَي التقدّم التراكميَّين (حفظ + مراجعة) — ReviewProgress::monthlyActivity()
        // نفسها لم تُمَسّ (لا تزال مُختبَرة مباشرة في ReviewProgressTest.php)،
        // فقط توقّفت هذه الصفحة عن استدعائها وعرض نتيجتها.
        $reviewChartPoints = $reviewProgress->timeline($student);

        // قسم المتون (S15): لكل متن يتتبّعه الطالب فعليًا — نسبته وآخر موضع
        // حفظ ومراجعة له. مبنيّ هنا لا في النموذج لأنه يحتاج جمع بيانات من
        // عدّة مصادر (PoemProgress + سجلّات) في بنية واحدة جاهزة للعرض.
        // (S24 — بطلب صريح من يحيى): "أرضية المتن" اليدوية أُلغيت نهائيًا —
        // راجع تعليق Student::trackedPoems() للتفصيل الكامل.
        $poemProgress = app(PoemProgress::class);
        $trackedPoems = $student->trackedPoems();

        // (S25 — بطلب صريح من يحيى "أبغى للمتن منحيين زي ما للدرس منحنى
        // وللمراجعة منحنى"): كل متن متتبَّع يحمل الآن نسبتين ومنحنيين، تمامًا
        // كمثيلَي القرآن أعلاه (percent/chartPoints لحفظ، reviewPercent/
        // reviewChartPoints لمراجعة) — نفس صنف PoemProgress يخدم الاثنين
        // بمعامل $type (راجع تعليق الصنف).
        $poemsData = $trackedPoems->map(function (Poem $poem) use ($student, $poemProgress) {
            $latestMemorization = $student->latestPoemLog($poem->id, 'حفظ');
            $latestReview = $student->latestPoemLog($poem->id, 'مراجعة');

            return (object) [
                'poem'                => $poem,
                'percent'             => $poemProgress->percentage($student, $poem, 'حفظ'),
                'latest_memorization' => $latestMemorization,
                'latest_review'       => $latestReview,
                // منحنى حفظ هذا المتن تحديدًا (S16) — يُعرض في بطاقة صغيرة
                // داخل قسم المتون، مفتاحه معرّف المتن في JS (خرائط لا مصفوفة).
                'chart_points'        => $poemProgress->timeline($student, $poem, 'حفظ'),
                // نسبة ومنحنى المراجعة (S25) — نظيرا الحفظ أعلاه تمامًا، بنوع
                // "مراجعة" بدل "حفظ".
                'review_percent'      => $poemProgress->percentage($student, $poem, 'مراجعة'),
                'review_chart_points' => $poemProgress->timeline($student, $poem, 'مراجعة'),
                // "الباب الحالي" (S39 — بطلب يحيى، مستند "أبواب المتون
                // الستة"): الباب الذي يقع فيه آخر بيت وصل إليه الطالب في كل
                // نوع (حفظ/مراجعة) — نفس آخر سطر معروض أصلًا (latest_*)
                // أعلاه، لا حساب إضافي مستقل قد يعطي رقمًا مختلفًا. null إن
                // لم يسجَّل شيء بعد لهذا النوع، أو إن كان البيت خارج كل
                // الأبواب المزروعة لهذا المتن (راجع تعليق Poem::chapterAt()).
                'current_chapter_memorization' => $latestMemorization
                    ? $poem->chapterAt((int) $latestMemorization->to_bayt)
                    : null,
                'current_chapter_review' => $latestReview
                    ? $poem->chapterAt((int) $latestReview->to_bayt)
                    : null,
            ];
        });

        // كل المتون الخمسة (لا المتتبَّعة فقط) لازمة لقائمة "إضافة سجلّ متن" —
        // يمكن اختيار متن لم يبدأ الطالب تتبّعه بعد.
        // eager-load الأبواب (S40 — طلب صريح من يحيى): زرّ "مراجعة بالأبواب"
        // الجديد في نموذج المراجعة يحتاج قائمة أبواب كل متن في الواجهة
        // (مصدرها JSON مُضمَّن في القالب لا استعلام إضافي)، فبلا هذا الـ
        // eager-load كانت كل بطاقة متن ستُطلق استعلام chapters منفصل (N+1).
        $allPoems = Poem::with('chapters')->orderBy('name')->get();

        // خريطة معرّف متن ⇐ أبوابه (S40 — "مراجعة بالأبواب") — تُحسب هنا لا
        // داخل @json(...) مباشرة في القالب: توجيه compileJson في Blade
        // (Illuminate\View\Compilers\Concerns\CompilesJson::compileJson)
        // يُقسّم وسيطه بفاصلة عادية (explode(',', ...)) بلا وعي بالأقواس
        // إطلاقًا، فأي تعبير PHP بمصفوفة متعددة المفاتيح (أي فاصلة) داخل
        // @json(...) مباشرة يُفسِد السطر المُصرَّف (بالضبط الخطأ الذي واجهه
        // يحيى: "Unclosed '[' ... does not match ')'"). الحل: نفس نمط
        // $quranUnits أدناه — القيمة جاهزة كمتغيّر عادي بلا أي فاصلة في
        // استدعاء @json نفسه.
        $poemChapters = $allPoems->mapWithKeys(fn (Poem $poem) => [
            $poem->id => $poem->chapters->map(fn (PoemChapter $chapter) => [
                'name'      => $chapter->name,
                'from_bayt' => $chapter->from_bayt,
                'to_bayt'   => $chapter->to_bayt,
            ]),
        ]);

        // قائمة الحلقات لزرّ "تعديل" الجديد (اسم الطالب + الحلقة معًا) —
        // انتقل هذا التعديل إلى هنا من صفّ اللوحة الرئيسية بطلب صريح من
        // يحيى (راجع تعليقَي زرّي "تعديل"/"حذف" في القالب). نفس استعلام
        // Circle::orderBy('name')->get() المستعمل أصلًا في index() — العزل
        // بين المعلّمين تلقائي عبر Scope الموديل، لا شرط مكتوب هنا.
        $circles = Circle::orderBy('name')->get();

        // (تصحيح خلل: نموذج "إضافة سجلّ مراجعة" في هذه الصفحة كان يعرض
        // "من سورة/إلى سورة" فقط بلا خيار الجزء/الحزب المتاح أصلًا في نافذة
        // التسجيل السريع بلوحة "قرآن" — نفس المرجع الثابت (القرار #54)
        // يُمرَّر هنا الآن أيضًا حتى يتطابق الخياران في كل نقاط الدخول.
        $quranUnits = app(QuranUnitReference::class)->all();

        return view('students.show', compact(
            'student', 'logs', 'surahs', 'chartPoints', 'monthStart', 'attendanceByDate',
            'completedSurahs', 'furthestSurah', 'poemsData', 'allPoems', 'circles',
            'reviewPercent', 'reviewQuartersDone', 'reviewHizbDone', 'reviewQuarterRemainder',
            'attendanceDaysCount', 'totalReviewsCount', 'reviewChartPoints', 'quranUnits',
            'poemChapters'
        ));
    }

    /**
     * تصدير/طباعة سجلّ طالب واحد كاملًا (S37 — بند 2 من خطّة التقارير
     * المعتمَدة): كل سجلّات الحفظ والمراجعة والحضور دفعة واحدة بلا ترقيم
     * صفحات (بخلاف show() أعلاه، المخصَّصة للتصفّح التفاعلي اليومي)، لعرض
     * قراءة/طباعة (report) أو تنزيل CSV (reportExportCsv) — نفس مصادر بيانات
     * show() (progressPercentage/ReviewProgress/PoemProgress) بلا حساب موازٍ.
     */
    public function report($id)
    {
        $student = Student::with(['circle', 'latestMemorizationLog.surah', 'latestReviewLog.surah'])->findOrFail($id);

        $this->authorize('update', $student);

        $logs = $student->recitationLogs()->with(['surah', 'toSurah'])->get();
        $attendances = $student->attendances()->orderByDesc('date')->get();

        $percent = app(MemorizationProgress::class)->percentage($student);
        $reviewPercent = app(ReviewProgress::class)->percentage($student);

        $poemProgress = app(PoemProgress::class);
        $poemsData = $student->trackedPoems()->map(fn (Poem $poem) => (object) [
            'poem'    => $poem,
            'percent' => $poemProgress->percentage($student, $poem),
        ]);

        $attendanceDaysCount = $attendances->where('status', 'حاضر')->count();
        $totalReviewsCount = $logs->where('type', 'مراجعة')->count();

        return view('students.report', compact(
            'student', 'logs', 'attendances', 'percent', 'reviewPercent', 'poemsData',
            'attendanceDaysCount', 'totalReviewsCount'
        ));
    }

    public function reportExportCsv($id)
    {
        $student = Student::with('circle')->findOrFail($id);

        $this->authorize('update', $student);

        $logs = $student->recitationLogs()->with(['surah', 'toSurah'])->get();
        $attendances = $student->attendances()->orderByDesc('date')->get();

        $filename = 'سجل-'.$student->student_name.'.csv';

        return response()->streamDownload(function () use ($logs, $attendances) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['سجلّات الحفظ والمراجعة']);
            fputcsv($handle, ['التاريخ', 'النوع', 'من سورة', 'إلى سورة', 'من آية', 'إلى آية', 'الحالة']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    optional($log->logged_at)->toDateString(),
                    $log->typeLabel(),
                    $log->surah->name ?? '—',
                    $log->spansMultipleSurahs() ? ($log->toSurah->name ?? '—') : ($log->surah->name ?? '—'),
                    $log->from_ayah ?? '—',
                    $log->to_ayah,
                    $log->statusLabel(),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['سجلّ الحضور']);
            fputcsv($handle, ['التاريخ', 'الحالة']);
            foreach ($attendances as $attendance) {
                fputcsv($handle, [$attendance->date->toDateString(), $attendance->status]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreStudentRequest $request)
    {
        $validated = $request->validated();

        $student = DB::transaction(function () use ($validated) {
            $student = Student::create([
                'student_name' => $validated['student_name'],
                'circle_id'    => $validated['circle_id'] ?? null,
            ]);

            // نقطة بداية اختيارية عند الإضافة — أول سطر في السجلّ الزمني (S7)،
            // لا حقل مستقل على الطالب.
            if (! empty($validated['surah_id']) && ! empty($validated['the_ayah'])) {
                $student->recitationLogs()->create([
                    'surah_id'  => $validated['surah_id'],
                    'to_ayah'   => $validated['the_ayah'],
                    'type'      => 'حفظ',
                    'logged_at' => now()->toDateString(),
                ]);
            }

            return $student;
        });

        return response()->json([
            'data'    => new StudentResource($student->load(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تمت إضافة الطالب بنجاح.',
        ], 201);
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        $student = Student::findOrFail($id);

        $this->authorize('update', $student);

        $validated = $request->validated();

        $student->update([
            'student_name' => $validated['student_name'],
            'circle_id'    => array_key_exists('circle_id', $validated) ? $validated['circle_id'] : $student->circle_id,
            'status'       => $validated['status'] ?? $student->status,
        ]);

        return response()->json([
            'data'    => new StudentResource($student->fresh(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تم حفظ البيانات بنجاح.',
        ]);
    }

    /**
     * حذف ناعم — قابل للتراجع عبر students.restore (B-05).
     */
    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        $this->authorize('delete', $student);

        $student->delete();

        return response()->json([
            'data'    => ['id' => $student->student_id],
            'message' => 'تم حذف الطالب. يمكنك التراجع خلال هذه الجلسة.',
        ]);
    }

    /**
     * التراجع عن حذف طالب.
     */
    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);

        $this->authorize('restore', $student);

        $student->restore();

        return response()->json([
            'data'    => new StudentResource($student->load(['circle', 'latestMemorizationLog.surah'])),
            'message' => 'تم استرجاع الطالب.',
        ]);
    }

    /**
     * استيراد طلاب من ملف CSV (S11) أو إكسل حقيقي .xlsx/.xls (S17): عمود أول
     * "اسم الطالب" (إلزامي)، عمود ثانٍ اختياري باسم حلقة موجودة مسبقًا. صف
     * ترويسة بعنوان "اسم الطالب" حرفيًا في أول سطر يُتجاهل تلقائيًا، وأي صف
     * لاحق بلا اسم يُسجَّل في الملاحظات ولا يوقف بقية الاستيراد — عطل صف واحد
     * لا يُسقط الملف كله.
     *
     * الصيغتان تُقرآن إلى نفس شكل الصفوف (مصفوفة أعمدة مفهرَسة رقميًا لكل صف)
     * ثم تُعالَجان بمنطق واحد مطابق تمامًا في processImportRows() — نسختان
     * منفصلتان من نفس التحقّقات (طول الاسم، مطابقة الحلقة...) كانتا ستنحرفان
     * عن بعضهما حتمًا مع أول تعديل مستقبلي على إحداهما دون الأخرى.
     */
    public function import(ImportStudentsRequest $request)
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            // toArray(null, ..., false) بدل قراءة الخلايا خلية-خلية: يُعيد
            // مصفوفة صفوف مفهرَسة رقميًا من 0 مباشرة (نفس شكل fgetcsv تمامًا)،
            // فلا داعٍ لمنطق مطابقة إحداثيات خلايا منفصل هنا.
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } else {
            $rows = $this->csvRows($file->getRealPath());
        }

        $result = $this->processImportRows($rows);

        return response()->json([
            'data' => $result,
            'message' => $result['created'] > 0
                ? "تمت إضافة {$result['created']} طالبًا."
                : 'لم تتم إضافة أي طالب — تحقّق من محتوى الملف.',
        ]);
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        // BOM UTF-8 شائع في ملفات CSV المصدَّرة من إكسل — إزالته حتى لا
        // يلتصق بأول اسم في الملف فيفشل اكتشاف صف الترويسة أو اسم الطالب.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($row = fgetcsv($handle)) !== false) {
            yield $row;
        }

        fclose($handle);
    }

    /**
     * منطق معالجة صفوف الاستيراد المشترك بين CSV وإكسل (S17) — مطابقة الحلقة
     * بالاسم لا بمعرّف رقمي (الملف مصدره خارج النظام)، فاسم غير موجود يُضيف
     * الطالب بلا حلقة مع ملاحظة صريحة بدل فشل الصف بالكامل أو اختراع حلقة
     * جديدة بصمت.
     *
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return array{created: int, notes: array<int, string>}
     */
    private function processImportRows(iterable $rows): array
    {
        $circleIdsByName = [];
        foreach (Circle::all() as $circle) {
            $circleIdsByName[mb_strtolower(trim($circle->name))] = $circle->id;
        }

        $created = 0;
        $notes = [];
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;

            $name = trim((string) ($row[0] ?? ''));
            $circleName = trim((string) ($row[1] ?? ''));

            if ($rowNumber === 1 && $name === 'اسم الطالب') {
                continue;
            }

            if ($name === '') {
                if (count($row) > 1 || $circleName !== '') {
                    $notes[] = "السطر {$rowNumber}: تجاهُل — لا يوجد اسم طالب.";
                }
                continue;
            }

            if (mb_strlen($name) > 255) {
                $notes[] = "السطر {$rowNumber}: تجاهُل — الاسم طويل جدًا.";
                continue;
            }

            $circleId = null;
            if ($circleName !== '') {
                $circleId = $circleIdsByName[mb_strtolower($circleName)] ?? null;
                if ($circleId === null) {
                    $notes[] = "السطر {$rowNumber}: أُضيف \"{$name}\" بلا حلقة — الحلقة \"{$circleName}\" غير موجودة.";
                }
            }

            Student::create(['student_name' => $name, 'circle_id' => $circleId]);
            $created++;
        }

        return ['created' => $created, 'notes' => $notes];
    }
}
