@extends('layouts.app')

@section('title', 'لوحة متابعة الطلاب - رِواق')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">لوحة متابعة الطلاب</h1>
        <div class="cluster">
            {{-- استيراد CSV (S11) أو إكسل حقيقي .xlsx/.xls (S17) — نفس المسار
                 ونفس نموذج الأعمدة (اسم الطالب، الحلقة)، الصيغة تُكتشَف من
                 امتداد الملف في StudentController::import(). --}}
            <button type="button" class="btn btn-secondary" id="importStudentsBtn">استيراد من ملف (CSV أو Excel)</button>
            <input type="file" id="importFileInput"
                   accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                   hidden>
            {{-- زرّ "+ إضافة طالب" ونافذة "⚙ إدارة الحلقات" أُزيلا من هنا
                 (طلب صريح من يحيى 2026-09-23): الأول صار حصرًا في صفحة
                 "السجلات" (records.index)، والثانية صارت صفحة/تبويب مستقلّ
                 "الحلقات" (circles.index) في الشريط العلوي. --}}
        </div>
    </div>

    {{-- بحث وفلترة (S11، وسِّعت في S18: اختيار متعدّد للحلقة/الحالة + نطاق
         تاريخ نشاط + نطاق نسبة تقدّم + حضور اليوم) --}}
    @php
        // (array) تقبل الصيغة الفردية القديمة (?circle_id=5) والجديدة معًا
        // (?circle_id[]=5&circle_id[]=7) دون تمييز في العرض.
        $selectedCircleIds = array_map('strval', (array) request('circle_id', []));
        $selectedStatuses = (array) request('status', []);
        $hasAnyFilter = request('q') || $selectedCircleIds || $selectedStatuses
            || request('activity_from') || request('activity_to')
            || request('progress_min') || request('progress_max')
            || request('attendance_today');
    @endphp
    <form method="GET" action="{{ route('dashboard') }}" class="search-filter-form">
        <label class="sr-only" for="dashboardSearch">ابحث باسم الطالب</label>
        <input class="input" type="search" id="dashboardSearch" name="q" value="{{ request('q') }}" placeholder="ابحث باسم الطالب">

        {{-- اختيار متعدّد للحلقة (S18) — كان مقصورًا على حلقة واحدة فقط. --}}
        <details class="filter-dropdown">
            <summary class="btn btn-sm btn-secondary">
                الحلقة @if ($selectedCircleIds) ({{ count($selectedCircleIds) }}) @endif
            </summary>
            <div class="filter-dropdown-panel">
                <label>
                    <input type="checkbox" name="circle_id[]" value="none" @checked(in_array('none', $selectedCircleIds, true))>
                    بلا حلقة
                </label>
                @foreach ($circles as $circle)
                    <label>
                        <input type="checkbox" name="circle_id[]" value="{{ $circle->id }}" @checked(in_array((string) $circle->id, $selectedCircleIds, true))>
                        {{ $circle->name }}
                    </label>
                @endforeach
            </div>
        </details>

        {{-- اختيار متعدّد للحالة (S18) — نفس فكرة الحلقة أعلاه. --}}
        <details class="filter-dropdown">
            <summary class="btn btn-sm btn-secondary">
                الحالة @if ($selectedStatuses) ({{ count($selectedStatuses) }}) @endif
            </summary>
            <div class="filter-dropdown-panel">
                @foreach (\App\Models\Student::STATUSES as $value => $label)
                    <label>
                        <input type="checkbox" name="status[]" value="{{ $value }}" @checked(in_array($value, $selectedStatuses, true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </details>

        {{-- نطاق تاريخ نشاط (S18) — أي سطر حفظ/مراجعة/تسميع مسجَّل في الفترة. --}}
        <details class="filter-dropdown">
            <summary class="btn btn-sm btn-secondary">
                نشاط بين تاريخين @if (request('activity_from') || request('activity_to')) (مُفعَّل) @endif
            </summary>
            <div class="filter-dropdown-panel is-narrow">
                <label class="sr-only" for="dashboardActivityFrom">من تاريخ</label>
                <input class="input" type="date" id="dashboardActivityFrom" name="activity_from" value="{{ request('activity_from') }}" placeholder="من تاريخ">
                <label class="sr-only" for="dashboardActivityTo">إلى تاريخ</label>
                <input class="input" type="date" id="dashboardActivityTo" name="activity_to" value="{{ request('activity_to') }}" placeholder="إلى تاريخ">
            </div>
        </details>

        {{-- نطاق نسبة تقدّم (S18) — نسبة الحفظ الموزونة بالأرباع (S15/S16). --}}
        <details class="filter-dropdown">
            <summary class="btn btn-sm btn-secondary">
                نسبة التقدّم @if (request('progress_min') !== null && request('progress_min') !== '' || request('progress_max') !== null && request('progress_max') !== '') (مُفعَّل) @endif
            </summary>
            <div class="filter-dropdown-panel is-narrow">
                <label class="sr-only" for="dashboardProgressMin">الحد الأدنى %</label>
                <input class="input" type="number" min="0" max="100" id="dashboardProgressMin" name="progress_min" value="{{ request('progress_min') }}" placeholder="الحد الأدنى %">
                <label class="sr-only" for="dashboardProgressMax">الحد الأقصى %</label>
                <input class="input" type="number" min="0" max="100" id="dashboardProgressMax" name="progress_max" value="{{ request('progress_max') }}" placeholder="الحد الأقصى %">
            </div>
        </details>

        <label class="sr-only" for="dashboardAttendanceToday">فلترة بحضور اليوم</label>
        <select class="input" id="dashboardAttendanceToday" name="attendance_today">
            <option value="">حضور اليوم: الكل</option>
            <option value="not_recorded" @selected(request('attendance_today') === 'not_recorded')>لم يُسجَّل بعد</option>
            @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('attendance_today') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-secondary btn-sm">بحث</button>
        @if ($hasAnyFilter)
            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">إعادة ضبط</a>
        @endif
    </form>

    {{--
        اللوحة الرئيسية الموحّدة (S23، تصحيح مكان): الدرس + المراجعة + الحضور
        لكل طلاب الصفحة معًا، بدل ثلاث شاشات منفصلة — هذه هي "الصفحة
        الرئيسية" التي كان يقصدها يحيى طوال الفترة الماضية (صفحة "الطلاب")،
        لا /attendance (تصحيح صريح منه بعد أن بُنيت أول مرة بالخطأ هناك).
        تبويبات الحلقات وفلترا الأولوية أدناه خاصّان بهذه القائمة تحديدًا،
        منفصلان عن فلاتر البحث الإدارية أعلاه التي تبقى كما هي.
    --}}
    <div class="attendance-toolbar">
        <div class="field">
            <label class="field-label" for="memorizationPriority">أولوية حالة الحفظ اليوم</label>
            <select class="input" id="memorizationPriority">
                <option value="">بدون أولوية</option>
                <option value="لم يسمع بعد" @selected($memorizationPriority === 'لم يسمع بعد')>لم يسمع بعد</option>
                <option value="غير حافظ" @selected($memorizationPriority === 'غير حافظ')>غير حافظ</option>
                <option value="حافظ" @selected($memorizationPriority === 'حافظ')>حافظ</option>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="attendancePriority">أولوية حضور اليوم</label>
            <select class="input" id="attendancePriority">
                <option value="">بدون أولوية</option>
                @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($attendancePriority === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- تبويبات الحلقات الفعلية (S23، بند 8 من تقرير التطوير) — أسماء حلقات
         المعلّم كما أدخلها بالضبط، لا فئة جديدة. "الكل" تبويب افتراضي بلا
         circle_id. رابط بسيط (?circle_id=X) لا مصفوفة: القيمة القياسية
         (array) في StudentController::index() تقبل الصيغة الفردية القديمة
         تلقائيًا بلا أي تغيير خلفي. --}}
    @php
        $tabParams = collect(request()->query())->except(['circle_id', 'page'])->all();
    @endphp
    <div class="circle-tabs">
        <a class="circle-tab {{ ! $selectedCircleIds ? 'active' : '' }}"
           href="{{ route('dashboard') }}?{{ http_build_query($tabParams) }}">الكل</a>
        @foreach ($circles as $circle)
            <a class="circle-tab {{ in_array((string) $circle->id, $selectedCircleIds, true) ? 'active' : '' }}"
               href="{{ route('dashboard') }}?{{ http_build_query($tabParams + ['circle_id' => $circle->id]) }}">{{ $circle->name }}</a>
        @endforeach
    </div>

    {{--
        (تصحيح بصري رابع، بطلب صريح من يحيى مع صورة مرجعية): تسميات أعمدة
        ثابتة فوق البطاقات — نصّ فقط، بلا روابط فرز ولا خلفية، بنفس ترتيب
        الصفّ نفسه (اسم الطالب ← الدرس ← المراجعة ← الحالة ← السجلّ). ليست
        رأس الجدول القابل للنقر القديم الذي أُزيل سابقًا (ذاك كان يفرز عبر
        ?sort=/&dir=؛ هذا للعرض فقط) — البطاقات متفاوتة العرض فلا تصطفّ هذه
        التسميات تمامًا تحت كل بطاقة، تمامًا كما في الصورة المرجعية نفسها.
        الفرز عبر الرابط ما زال يعمل من الخلفية دون واجهة نقر هنا.
    --}}
    <div class="students-table-head quran-columns" aria-hidden="true">
        <span>اسم الطالب</span>
        <span>الدرس</span>
        <span>المراجعة</span>
        <span>الحالة</span>
        <span></span>
        <span>السجلّ</span>
    </div>

    <div class="students-table" id="studentsTable">
        @forelse ($students as $student)
            @php
                $todayLesson = $todayLogsByStudent[$student->student_id]['حفظ'] ?? null;
                $todayReview = $todayLogsByStudent[$student->student_id]['مراجعة'] ?? null;
                $lessonState = $todayLesson === null
                    ? 'لم يسمع بعد'
                    : ($todayLesson->status === 'غير حافظ' ? 'غير حافظ' : 'حافظ');
                $reviewState = $todayReview === null
                    ? 'لم يسمع بعد'
                    : ($todayReview->status === 'غير حافظ' ? 'غير حافظ' : 'حافظ');
                $todayAttendanceStatus = $attendanceTodayByStudent[$student->student_id] ?? null;
                $studentNext = $nextPositions[$student->student_id] ?? ['lesson' => null, 'review' => null];
            @endphp
            {{--
                (تصحيح ثامن — مطابقة 100% لمخطّط يحيى الجديد، بطلب صريح: "خليه
                مطابق 100%"): الصفّ المطوي عند الغياب (شارة غياب عريضة واحدة
                تحلّ محلّ بطاقتَي الدرس/المراجعة، وتلوين الصفّ كاملًا أصفر —
                من التصحيح البصري الثاني/الثالث السابق) أُلغي نهائيًا. مخطّط
                يحيى الجديد يُظهر "غائب" كبطاقة فردية صغيرة بنفس شكل/موضع
                "حاضر" تمامًا ضمن عمود "حضور اليوم"، مع بقاء بطاقتَي الدرس
                والمراجعة ظاهرتَين دومًا بغضّ النظر عن حالة الحضور — فلا حاجة
                بعد الآن لأي تفرّع بين حالتَي حاضر/غائب في هذا الصفّ، ولا
                لصنف `.row-absent` (التلوين الأصفر الكامل) الذي لم يظهر في أي
                من صور يحيى الجديدة. الترتيب من اليمين لليسار (يطابق dir=rtl
                فلا حاجة لعكسه): الاسم ← الدرس ← المراجعة ← حضور اليوم ← السجلّ.
                عمود "الحلقة" ومحرِّر التعديل المضمَّن وزرّ "حذف" لا يزالان
                غائبَين عمدًا (لم يظهرا في أي من الصور المرجعية القديمة أو
                الجديدة) — بانتظار توضيح يحيى بشأن مكانهما الجديد المحتمل.
            --}}
            <div class="student-row quran-columns"
                 data-id="{{ $student->student_id }}">
                {{-- (S24، الجزء الثالث — طلب صريح من يحيى): شارتا "انقطاع" و"عدد
                     المتون المتتبَّعة" اللتان كانتا هنا حُذفتا نهائيًا — لا علاقة
                     لهما بلوحة "قرآن" تحديدًا (الانقطاع مؤشّر حضور عام، والمتون
                     لها لوحتها الخاصّة الآن `/poems`). --}}
                <div class="chip chip-name">
                    <span class="chip-name-text">{{ $student->student_name }}</span>
                </div>

                {{-- (S24، الجزء الثالث — طلب صريح من يحيى بعد ملاحظته "التسجيل
                     الحالي فيه مشاكل... أبغى التسجيل السريع الموجود في المتون
                     يكون موجود في القرآن"): بطاقتا الدرس/المراجعة لم تعودا
                     قابلتَين للنقر (كانتا open-log-modal + أيقونة ✎ منفصلة
                     لكل منهما) — أصبحتا عرضًا للحالة الحالية فقط، بلا زرّ
                     حضور اليوم الذي بقي كما هو تمامًا بطلب صريح ("خلي زر
                     الحضور كما هو"). التسجيل/التعديل صار عبر زرّ "تسجيل
                     سريع" واحد أسفل الصفّ (راجع openQuickLogModal في قسم
                     السكربت) يفتح نفس نافذة logModalBackdrop، بعد إضافة
                     قائمة "النوع" لها لتختار حفظ/مراجعة داخل نافذة واحدة. --}}
                <div class="chip {{ $lessonState === 'حافظ' ? 'chip-range' : ($lessonState === 'غير حافظ' ? 'chip-simple chip-simple--danger' : 'chip-simple') }}">
                    <span class="chip-state" data-state="{{ $lessonState }}"></span>
                    @if ($lessonState === 'لم يسمع بعد')
                        لم يسمع بعد
                    @elseif ($lessonState === 'غير حافظ')
                        لم يحفظ
                    @else
                        <x-recitation-range :log="$todayLesson" />
                    @endif
                </div>

                <div class="chip {{ $reviewState === 'حافظ' ? 'chip-range' : ($reviewState === 'غير حافظ' ? 'chip-simple chip-simple--danger' : 'chip-simple') }}">
                    <span class="chip-state" data-state="{{ $reviewState }}"></span>
                    @if ($reviewState === 'لم يسمع بعد')
                        لم يسمع بعد
                    @elseif ($reviewState === 'غير حافظ')
                        لم يحفظ
                    @else
                        {{-- (القرار #54، طلب صريح من يحيى): كانت هنا تسمية
                             محسوبة تلقائيًا مثل "(ربع جزء)" أسفل المدى — حُذفت
                             نهائيًا، فيُعرض المدى الخام فقط بلا أي حساب كمّية
                             ضمني، بصرف النظر عن طريقة إدخال هذا السجلّ (من
                             سورة أو بالجزء/الحزب الجديد أدناه). --}}
                        <x-recitation-range :log="$todayReview" />
                    @endif
                </div>

                {{-- ألوان الحالة عبر data-status (نفس نمط .status-pill/.calendar-day
                     القائم أصلًا) لا صنف CSS مُشتقّ من النصّ العربي —
                     يتّسع تلقائيًا لأيّ حالة مستقبلية بلا كود إضافي. تُغطّي
                     .chip-attendance في app.css الآن أربع الحالات كلّها
                     (حاضر/مستأذن/غائب بعذر/غائب بدون عذر) بشكل كبسولة موحّد،
                     لا شارة عريضة منفصلة لحالة الغياب بعد الآن. (لم يُمَسّ
                     هذا الزرّ بطلب صريح من يحيى: "خلي زر الحضور كما هو".) --}}
                <button type="button" class="chip chip-attendance open-attendance-modal"
                        title="حضور اليوم"
                        data-status="{{ $todayAttendanceStatus ?? '' }}"
                        data-student-id="{{ $student->student_id }}"
                        data-student-name="{{ $student->student_name }}">
                    {{ $todayAttendanceStatus ? \App\Models\Attendance::STATUSES[$todayAttendanceStatus] : 'لم يُسجَّل بعد' }}
                </button>

                {{-- زرّ التسجيل السريع الموحَّد (الدرس + المراجعة معًا) — يحمل
                     بيانات سجلَّي اليوم (إن وُجدا) واقتراح الموضع التالي لكل
                     نوع، فتقرّر applyQuickLogType() في السكربت وضع تعديل أو
                     تسجيل جديد بمجرّد اختيار النوع داخل النافذة، بلا حاجة
                     لزرّين منفصلين كما كان سابقًا. --}}
                <button type="button" class="chip chip-action chip-action-primary open-quick-log-modal"
                        data-student-id="{{ $student->student_id }}" data-student-name="{{ $student->student_name }}"
                        data-lesson-log-id="{{ $todayLesson->id ?? '' }}"
                        data-lesson-surah-id="{{ $todayLesson->surah_id ?? '' }}"
                        data-lesson-to-surah-id="{{ $todayLesson->to_surah_id ?? '' }}"
                        data-lesson-from-ayah="{{ $todayLesson->from_ayah ?? '' }}"
                        data-lesson-to-ayah="{{ $todayLesson->to_ayah ?? '' }}"
                        data-lesson-status="{{ $todayLesson->status ?? '' }}"
                        data-lesson-next-surah="{{ $studentNext['lesson']['surah_id'] ?? '' }}"
                        data-lesson-next-ayah="{{ $studentNext['lesson']['ayah'] ?? '' }}"
                        data-review-log-id="{{ $todayReview->id ?? '' }}"
                        data-review-surah-id="{{ $todayReview->surah_id ?? '' }}"
                        data-review-to-surah-id="{{ $todayReview->to_surah_id ?? '' }}"
                        data-review-from-ayah="{{ $todayReview->from_ayah ?? '' }}"
                        data-review-to-ayah="{{ $todayReview->to_ayah ?? '' }}"
                        data-review-status="{{ $todayReview->status ?? '' }}"
                        data-review-next-surah="{{ $studentNext['review']['surah_id'] ?? '' }}"
                        data-review-next-ayah="{{ $studentNext['review']['ayah'] ?? '' }}">تسجيل سريع</button>

                <a href="{{ route('students.show', $student->student_id) }}" class="chip chip-action">السجلّ</a>
            </div>
        @empty
            <div class="empty-state" id="emptyState">
                <p class="empty-emoji">🌱</p>
                <p>
                    @if ($hasAnyFilter)
                        لا نتائج مطابقة لهذا البحث/الفلتر.
                    @else
                        لا يوجد طلاب بعد. ابدأ بإضافة أول طالب في حلقتك.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{ $students->links('vendor.pagination.custom') }}

    {{--
        نافذة تسجيل الدرس/المراجعة (S23، بطلب صريح من يحيى: "ما تخلي الإضافة
        لبيانات الحفظ والمراجعة بالطريقة البدائية ذي خليها نافذة js") — نافذة
        واحدة مشتركة لكل الصفحة بدل نموذج مضمَّن في كل صفّ. عناصرها مستمعات
        أحداثها كلها مربوطة بعناصر محدَّدة (النافذة/الأزرار)، لا بـ document،
        حتى لا يتكرّر العطل U-01 (راجع DashboardViewTest).
    --}}
    <div class="modal-backdrop" id="logModalBackdrop" hidden>
        <div class="modal log-modal" role="dialog" aria-modal="true" aria-labelledby="logModalTitle">
            <div class="modal-head">
                <h2 id="logModalTitle">تسجيل</h2>
                <button type="button" class="modal-close" id="logModalClose" aria-label="إغلاق"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
            </div>
            <form id="logModalForm">
                <p class="text-muted" id="logModalSubtitle"></p>
                {{-- (S24، الجزء الثالث — طلب صريح من يحيى): قائمة "النوع" جديدة
                     — قبل هذا التصحيح كانت النافذة تُفتح مسبَقة النوع من البطاقة
                     المنقورة نفسها (open-log-modal لكل من الدرس/المراجعة على
                     حدة). الآن تُفتح من زرّ "تسجيل سريع" واحد لا يحمل نوعًا
                     مسبقًا، فالنوع يُختار هنا؛ تبديله يُحمِّل بيانات ذلك النوع
                     (تعديل إن وُجد سجلّ اليوم له، وإلا تسجيل جديد) عبر
                     applyQuickLogType() في السكربت. --}}
                <div class="field">
                    <label class="field-label" for="logModalTypeSelect">النوع</label>
                    <select class="input" id="logModalTypeSelect">
                        <option value="حفظ">حفظ (درس)</option>
                        <option value="مراجعة">مراجعة</option>
                    </select>
                </div>
                {{-- (القرار #54، طلب صريح من يحيى): طريقة إدخال ثانية للمراجعة
                     فقط — اختيار مباشر بالجزء/الحزب/نصف الحزب/ربع الحزب بلا
                     حاجة لتحديد رقم آية دقيق، بجانب طريقة "من سورة" الأصلية
                     التي تبقى كما هي دون أي تغيير في سلوكها. الحفظ (الدرس)
                     لا يعرض هذا الخيار إطلاقًا — يبقى بسورة/آية فقط كما كان،
                     راجع syncLogModalEntryMode() في السكربت. --}}
                <div class="field" id="logModalEntryModeField" hidden>
                    <label class="field-label" for="logModalEntryMode">طريقة الإدخال</label>
                    <select class="input" id="logModalEntryMode">
                        <option value="surah">من سورة</option>
                        <option value="unit">بالجزء/الحزب</option>
                    </select>
                </div>
                <div id="logModalSurahFields">
                    <div class="field">
                        <label class="field-label" for="logModalSurah">من سورة</label>
                        <select class="input" id="logModalSurah" required></select>
                    </div>
                    <div class="field" id="logModalToSurahField" hidden>
                        <label class="field-label" for="logModalToSurah">إلى سورة (مراجعة عابرة لأكثر من سورة، اختياري)</label>
                        <select class="input" id="logModalToSurah"></select>
                    </div>
                    <div class="cluster">
                        <div class="field">
                            <label class="field-label" for="logModalFromAyah">من آية (اختياري)</label>
                            <input class="input" type="number" min="1" id="logModalFromAyah">
                        </div>
                        <div class="field">
                            <label class="field-label" for="logModalToAyah">إلى آية</label>
                            <input class="input" type="number" min="1" id="logModalToAyah" required>
                        </div>
                    </div>
                </div>
                {{-- وحدات الجزء/الحزب (القرار #54) — "من"/"إلى" من نفس نوع
                     الوحدة (تمامًا كـ"من سورة/إلى سورة" أعلاه)، و"إلى" تبقى
                     "— نفس البداية —" افتراضًا لاختيار وحدة واحدة فقط بلا أي
                     خطوة إضافية (الحالة الأكثر شيوعًا)، وتُغيَّر فقط لمراجعة
                     مدى يمتدّ لعدّة وحدات معًا في سجلّ واحد. --}}
                <div id="logModalUnitFields" hidden>
                    <div class="field">
                        <label class="field-label" for="logModalUnitType">نوع الوحدة</label>
                        <select class="input" id="logModalUnitType">
                            <option value="juz">جزء</option>
                            <option value="hizb">حزب</option>
                            <option value="half_hizb">نصف حزب</option>
                            <option value="quarter_hizb">ربع حزب</option>
                        </select>
                    </div>
                    <div class="cluster">
                        <div class="field">
                            <label class="field-label" for="logModalUnitFrom">من</label>
                            <select class="input" id="logModalUnitFrom"></select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="logModalUnitTo">إلى</label>
                            <select class="input" id="logModalUnitTo"></select>
                        </div>
                    </div>
                    <p class="text-muted" id="logModalUnitPreview"></p>
                </div>
                <div class="field">
                    <label class="field-label" for="logModalStatus">حالة الحفظ (اختياري)</label>
                    <select class="input" id="logModalStatus">
                        <option value="">— بدون تحديد —</option>
                        @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="field-error" id="logModalError"></span>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" id="logModalSave">حفظ</button>
                    <button type="button" class="btn btn-ghost" id="logModalCancel">إلغاء</button>
                    {{-- (تصحيح صريح من يحيى، "خيار حذف منفصل بداخلها إن احتاجه"):
                         يظهر فقط حين يوجد سجلّ اليوم بالفعل للنوع المختار حاليًا
                         (وضع تعديل)، راجع applyQuickLogType() أدناه. --}}
                    <button type="button" class="btn btn-ghost btn-danger-ghost" id="logModalDelete" hidden>حذف السجلّ</button>
                </div>
            </form>
        </div>
    </div>

    {{--
        نافذة تسجيل الحضور (تصحيح لاحق من يحيى: "نفس الحالة أبغاها مع حالة
        الحضور أو الغياب" — نفس أسلوب نافذة الدرس/المراجعة أعلاه، لا أزرار
        ظاهرة دومًا داخل الصفّ). نقر أيّ حالة هنا يحفظها فورًا (نفس سلوك
        الأزرار الأربعة القديمة، بلا زرّ "حفظ" منفصل) ويُغلق النافذة.
    --}}
    <div class="modal-backdrop" id="attendanceModalBackdrop" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
            <div class="modal-head">
                <h2 id="attendanceModalTitle">تسجيل الحضور</h2>
                <button type="button" class="modal-close" id="attendanceModalClose" aria-label="إغلاق"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
            </div>
            <p class="text-muted" id="attendanceModalSubtitle"></p>
            <div class="attendance-status-group" id="attendanceModalButtons">
                @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                    <button type="button" class="status-btn" data-status="{{ $value }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="attendanceModalCancel">إلغاء</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetch, apiFetchQueueable, toast, confirmDialog, withButtonLoading } = window.KeshfApp;

    const table = document.getElementById('studentsTable');
    const TODAY = @json($today);

    {{-- مرتّبة بترتيب الحفظ (الناس أولًا) من الخادم — نفس ترتيب قائمة السورة
         في صفحة الطالب، فلا يختلف الترتيب بين شاشتين. --}}
    @php
        $surahsForJs = $surahs->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name.($s->excluded_from_progress ? ' (لا تُحتسب في النسبة)' : ''),
            'ayah_count' => $s->ayah_count,
        ]);
    @endphp
    const SURAHS = @json($surahsForJs);
    const ATTENDANCE_LABELS = @json(\App\Models\Attendance::STATUSES);
    {{-- (القرار #54): وحدات الجزء/الحزب/نصف الحزب/ربع الحزب بحدودها الفعلية
         (سورة/آية)، محسوبة مرّة واحدة في الخادم (QuranUnitReference) —
         تُستهلَك في نافذة تسجيل المراجعة بلا أي طلب شبكة إضافي عند تبديل
         نوع الوحدة أو تغيير "من"/"إلى". --}}
    const QURAN_UNITS = @json($quranUnits);

    async function deleteRow(row) {
        const id = row.dataset.id;
        if (!id) {
            row.remove();
            return;
        }

        const confirmed = await confirmDialog('هل تريد حذف هذا الطالب؟');
        if (!confirmed) return;

        try {
            const res = await apiFetch(`/dashboard/${id}`, { method: 'DELETE' });
            row.remove();
            toast(res.message ?? 'تم حذف الطالب.', 'success', {
                action: {
                    label: 'تراجع',
                    onClick: async () => {
                        try {
                            await apiFetch(`/dashboard/${id}/restore`, { method: 'POST' });
                            window.location.reload();
                        } catch (e) {
                            toast(e.message, 'error');
                        }
                    },
                },
            });
            if (!table.querySelector('.student-row')) {
                table.innerHTML = `
                    <div class="empty-state" id="emptyState">
                        <p class="empty-emoji">🌱</p>
                        <p>لا يوجد طلاب بعد. ابدأ بإضافة أول طالب في حلقتك.</p>
                    </div>`;
            }
        } catch (error) {
            if (error.status !== 419) toast(error.message, 'error');
        }
    }

    {{--
        (تصحيح بصري ثالث) الحفظ التلقائي المضمَّن لاسم/حلقة الطالب الحالي
        (كان هنا منذ S11) أُزيل مع حقلَي الاسم/الحلقة القابلَين للتعديل من
        صفّ اللوحة الرئيسية نفسه — لم يظهر أيّ منهما في الصور المرجعية التي
        أرسلها يحيى. `deleteRow()` أعلاه بقيت معرَّفة (لم تُستدعَ من أي مكان
        حاليًا بعد إزالة زرّ "حذف" من الصفّ) تحسّبًا لعودة حذف الطالب لواجهة
        ما هنا لاحقًا — لا خطر من إبقائها. راجع ردّي على يحيى/توثيق المشروع
        بخصوص أين يُفترض أن يُعدَّل الآن اسم/حلقة/حالة طالب موجود أصلًا.

        (طلب صريح من يحيى 2026-09-23: "أحذف زر إضافة الطالب من تبويب القرآن
        وخليه فقط في السجلات"): صفّ "إضافة طالب" الجديد (كان يُبنى هنا عبر
        buildRow()/bindNewRow()/saveNewRow() وأصدقائها) أُزيل بالكامل من هذه
        الصفحة — الإضافة الآن حصرًا من صفحة "السجلات" (records/index.blade.php)
        التي تملك نسختها الخاصة المستقلّة من نفس التدفّق (لا تعتمد على DOM
        هذه الصفحة إطلاقًا)، فإزالته هنا بلا أي أثر جانبي.
    --}}

    /* ===== أولوية العرض (S23) — إعادة تحميل بفلترة/ترتيب مختلف ===== */
    const memorizationPrioritySelect = document.getElementById('memorizationPriority');
    const attendancePrioritySelect = document.getElementById('attendancePriority');

    function reloadWithPriority() {
        const params = new URLSearchParams(window.location.search);
        if (memorizationPrioritySelect.value) params.set('memorization_priority', memorizationPrioritySelect.value);
        else params.delete('memorization_priority');
        if (attendancePrioritySelect.value) params.set('attendance_priority', attendancePrioritySelect.value);
        else params.delete('attendance_priority');
        params.delete('page');
        window.location.search = params.toString();
    }

    memorizationPrioritySelect.addEventListener('change', reloadWithPriority);
    attendancePrioritySelect.addEventListener('change', reloadWithPriority);

    /**
     * نافذة حضور اليوم (تصحيح لاحق من يحيى: "نفس الحالة أبغاها مع حالة
     * الحضور أو الغياب") — كانت أربعة أزرار حالة ظاهرة دومًا داخل الصفّ
     * نفسه (منطق الشاشة السريعة القديمة S9)، فتكسّرت بصريًا في عمود ضيّق.
     * الآن: شارة واحدة تعرض حالة اليوم، والنقر عليها يفتح نافذة مشتركة —
     * نقر حالة داخل النافذة يحفظها فورًا بلا زرّ "حفظ" منفصل (نفس سلوك
     * الأزرار القديمة نفسه، فقط انتقل مكانها). مستمعاتها كلها مربوطة
     * بعناصر النافذة نفسها لا بـ document (راجع DashboardViewTest).
     *
     * (تصحيح ثامن): الصفّ لم يعد يُطوى/يُلوَّن عند الغياب (راجع تعليق الصفّ
     * في القالب أعلاه) — فتحديث `data-status`/النصّ على الشارة نفسها بعد
     * الحفظ كافٍ وحده لتحديث شكلها فورًا عبر محدِّدات CSS، بلا أي تلاعب
     * بصنف الصفّ (ذلك الصنف الخاص بتلوين الصفّ بالكامل عند الغياب حُذف
     * نهائيًا من ملف الأنماط).
     */
    const attendanceModalBackdrop = document.getElementById('attendanceModalBackdrop');
    const attendanceModalSubtitle = document.getElementById('attendanceModalSubtitle');
    const attendanceModalButtons = document.getElementById('attendanceModalButtons');
    const attendanceModalClose = document.getElementById('attendanceModalClose');
    const attendanceModalCancel = document.getElementById('attendanceModalCancel');

    let attendanceModalTriggerBtn = null;

    function openAttendanceModal(btn) {
        attendanceModalTriggerBtn = btn;
        attendanceModalSubtitle.textContent = btn.dataset.studentName ?? '';
        attendanceModalButtons.querySelectorAll('.status-btn').forEach((b) => {
            b.classList.toggle('selected', b.dataset.status === btn.dataset.status);
        });
        attendanceModalBackdrop.hidden = false;
    }

    function closeAttendanceModal() {
        attendanceModalBackdrop.hidden = true;
        attendanceModalTriggerBtn = null;
    }

    async function saveAttendance(status) {
        if (!attendanceModalTriggerBtn) return;
        const triggerBtn = attendanceModalTriggerBtn;
        const studentId = Number(triggerBtn.dataset.studentId);

        try {
            const res = await apiFetchQueueable('/attendance', {
                method: 'POST',
                body: {
                    date: TODAY,
                    entries: [{ student_id: studentId, status }],
                },
            }, `حضور طالب رقم ${studentId}`);

            triggerBtn.dataset.status = status;
            triggerBtn.textContent = ATTENDANCE_LABELS[status] ?? status;
            if (!res.queued) toast('تم حفظ الحضور.', 'success');
            closeAttendanceModal();
        } catch (error) {
            if (error.status === 422 && error.errors) {
                toast(Object.values(error.errors)[0][0], 'error');
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    }

    table.addEventListener('click', (e) => {
        const btn = e.target.closest('.open-attendance-modal');
        if (!btn) return;
        openAttendanceModal(btn);
    });

    attendanceModalButtons.addEventListener('click', (e) => {
        const btn = e.target.closest('.status-btn');
        if (!btn) return;
        saveAttendance(btn.dataset.status);
    });

    attendanceModalClose.addEventListener('click', closeAttendanceModal);
    attendanceModalCancel.addEventListener('click', closeAttendanceModal);
    attendanceModalBackdrop.addEventListener('click', (e) => {
        if (e.target === attendanceModalBackdrop) closeAttendanceModal();
    });

    /**
     * نافذة تسجيل الدرس/المراجعة (S23، ثم توحيد الزرّ في S24 الجزء الثالث) —
     * نافذة واحدة مشتركة يُعاد ملؤها في كل مرة تُفتَح، بدل نموذج مضمَّن في كل
     * صفّ ("ما تخلي الإضافة لبيانات الحفظ والمراجعة بالطريقة البدائية ذي
     * خليها نافذة js" — طلب صريح من يحيى). كل مستمعاتها مربوطة بعناصر
     * النافذة نفسها لا بـ document، حتى لا يتكرّر العطل U-01 (راجع
     * DashboardViewTest).
     *
     * (S24، الجزء الثالث — طلب صريح من يحيى: "أبغى التسجيل السريع الموجود
     * في المتون يكون موجود في القرآن بدل التسجيل الحالي"): كان يفتحها زرّان
     * منفصلان لكل نوع (open-log-modal لكل بطاقة + أيقونة ✎ لتعديلها) —
     * استُبدلا بزرّ "تسجيل سريع" واحد لكل طالب (خصائص data-lesson-* و
     * data-review-* تحمل بيانات سجلَّي اليوم لكلا النوعين معًا)، وأُضيفت قائمة "النوع"
     * داخل النافذة نفسها. زرّ الحضور المنفصل (open-attendance-modal) لم
     * يتأثّر إطلاقًا — طلب يحيى صراحةً إبقاءه كما هو.
     */
    const logModalBackdrop = document.getElementById('logModalBackdrop');
    const logModalTitle = document.getElementById('logModalTitle');
    const logModalSubtitle = document.getElementById('logModalSubtitle');
    const logModalForm = document.getElementById('logModalForm');
    const logModalTypeSelect = document.getElementById('logModalTypeSelect');
    const logModalEntryModeField = document.getElementById('logModalEntryModeField');
    const logModalEntryMode = document.getElementById('logModalEntryMode');
    const logModalSurahFields = document.getElementById('logModalSurahFields');
    const logModalSurah = document.getElementById('logModalSurah');
    const logModalToSurahField = document.getElementById('logModalToSurahField');
    const logModalToSurah = document.getElementById('logModalToSurah');
    const logModalFromAyah = document.getElementById('logModalFromAyah');
    const logModalToAyah = document.getElementById('logModalToAyah');
    const logModalUnitFields = document.getElementById('logModalUnitFields');
    const logModalUnitType = document.getElementById('logModalUnitType');
    const logModalUnitFrom = document.getElementById('logModalUnitFrom');
    const logModalUnitTo = document.getElementById('logModalUnitTo');
    const logModalUnitPreview = document.getElementById('logModalUnitPreview');
    const logModalStatus = document.getElementById('logModalStatus');
    const logModalError = document.getElementById('logModalError');
    const logModalClose = document.getElementById('logModalClose');
    const logModalCancel = document.getElementById('logModalCancel');
    const logModalDelete = document.getElementById('logModalDelete');

    let logModalStudentId = null;
    let logModalType = null;
    // null = وضع "تسجيل جديد" (POST)، رقم = وضع "تعديل" لسجلّ قائم بهذا
    // المعرّف (PATCH) — يُحدَّد الآن تلقائيًا حسب النوع المختار، راجع
    // applyQuickLogType() ومستمع submit أدناه.
    let logModalEditingId = null;
    // بيانات سجلَّي اليوم (حفظ/مراجعة) لكلا النوعين معًا، تُقرَأ مرّة واحدة
    // عند فتح النافذة من data-lesson-*/data-review-* الخاصّة بزرّ الصفّ
    // المنقور، ثم يستهلكها applyQuickLogType() عند كل تبديل للنوع.
    let quickLogTodayData = { 'حفظ': {}, 'مراجعة': {} };

    function fillSurahSelects() {
        const options = SURAHS.map((s) => `<option value="${s.id}" data-ayah-count="${s.ayah_count}">${s.name}</option>`).join('');
        logModalSurah.innerHTML = `<option value="">— اختر سورة —</option>${options}`;
        logModalToSurah.innerHTML = `<option value="">— بلا (نفس سورة البداية) —</option>${options}`;
    }

    function syncLogModalAyahMax() {
        const fromOpt = logModalSurah.selectedOptions[0];
        const toOpt = logModalToSurah.selectedOptions[0];
        logModalFromAyah.max = fromOpt?.dataset.ayahCount || '';
        logModalToAyah.max = (logModalToSurah.value ? toOpt?.dataset.ayahCount : fromOpt?.dataset.ayahCount) || '';
    }

    function syncLogModalToSurahVisibility() {
        const isReview = logModalType === 'مراجعة';
        logModalToSurahField.hidden = ! isReview;
        if (! isReview) logModalToSurah.value = '';
        syncLogModalAyahMax();
    }

    logModalSurah.addEventListener('change', syncLogModalAyahMax);
    logModalToSurah.addEventListener('change', syncLogModalAyahMax);

    /**
     * (القرار #54) طريقة إدخال ثانية للمراجعة فقط: اختيار مباشر بالجزء/
     * الحزب/نصف الحزب/ربع الحزب بدل "من سورة/إلى سورة" اليدوية. القوائم
     * تُبنى محليًا من QURAN_UNITS (محسوبة في الخادم مرّة واحدة) بلا أي طلب
     * شبكة. "إلى" تبقى "— نفس البداية —" افتراضًا (وحدة واحدة فقط، الحالة
     * الشائعة)؛ تغييرها يوسّع المدى لوحدة نهاية مختلفة — تمامًا كمبدأ حقل
     * "إلى سورة" الحالي (فارغ = نفس سورة البداية).
     */
    function fillUnitSelects() {
        const units = QURAN_UNITS[logModalUnitType.value] || [];
        const options = units.map((u) => `<option value="${u.number}">${u.label}</option>`).join('');
        logModalUnitFrom.innerHTML = `<option value="">— اختر —</option>${options}`;
        logModalUnitTo.innerHTML = `<option value="">— نفس البداية —</option>${options}`;
        logModalUnitFrom.value = '';
        logModalUnitTo.value = '';
        updateUnitPreview();
    }

    function findUnit(units, number) {
        return units.find((u) => String(u.number) === String(number));
    }

    /** يعرض المدى الفعلي (سورة/آية) الذي ستنتجه الوحدة/الوحدتان المختارتان حاليًا — تأكيد بصري قبل الحفظ. */
    function updateUnitPreview() {
        const units = QURAN_UNITS[logModalUnitType.value] || [];
        const fromUnit = findUnit(units, logModalUnitFrom.value);

        if (! fromUnit) {
            logModalUnitPreview.textContent = '';
            return;
        }

        const toUnit = findUnit(units, logModalUnitTo.value) || fromUnit;
        const fromSurah = SURAHS.find((s) => s.id === fromUnit.from_surah_id);
        const toSurah = SURAHS.find((s) => s.id === toUnit.to_surah_id);

        logModalUnitPreview.textContent = `المدى الفعلي: من ${fromSurah?.name ?? ''} - ${fromUnit.from_ayah} إلى ${toSurah?.name ?? ''} - ${toUnit.to_ayah}`;
    }

    logModalUnitType.addEventListener('change', fillUnitSelects);
    logModalUnitFrom.addEventListener('change', updateUnitPreview);
    logModalUnitTo.addEventListener('change', updateUnitPreview);

    /**
     * يُظهر قائمة "طريقة الإدخال" للمراجعة فقط (الحفظ يبقى بسورة/آية حصرًا
     * كما كان)، ويبدّل بين مجموعتَي الحقول (سورة/آية أو جزء/حزب) حسب
     * الاختيار الحالي. يُستدعى عند فتح النافذة/تبديل النوع (يُعاد الضبط
     * دومًا لـ"من سورة" الافتراضية — تعديل سجلّ قائم يُعرض بصورته المخزَّنة
     * الفعلية دومًا لا بتخمين طريقة إدخاله الأصلية) وعند تبديل القائمة نفسها.
     */
    function syncLogModalEntryMode() {
        const isReview = logModalType === 'مراجعة';
        logModalEntryModeField.hidden = ! isReview;
        if (! isReview) logModalEntryMode.value = 'surah';

        const isUnitMode = isReview && logModalEntryMode.value === 'unit';
        logModalSurahFields.hidden = isUnitMode;
        logModalUnitFields.hidden = ! isUnitMode;

        // ملاحظة مهمّة: `hidden` على الحاوية الأصل لا يُعفي تلقائيًا حقولها
        // من فحص `required` في كل المتصفّحات (تحقّقنا فعليًا: `checkValidity()`
        // يبقى `false` رغم أن الحقل غير مرئي فعلًا) — فيمنع الحفظ بصمت بلا أي
        // رسالة خطأ ظاهرة. لذا يُبدَّل `required` صراحةً هنا مع كل تبديل وضع.
        logModalSurah.required = ! isUnitMode;
        logModalToAyah.required = ! isUnitMode;

        if (isUnitMode) fillUnitSelects();
    }

    logModalEntryMode.addEventListener('change', syncLogModalEntryMode);

    /**
     * (S24، الجزء الثالث) تُستدعى عند فتح النافذة وعند كل تبديل لقائمة
     * "النوع" — تقرأ quickLogTodayData[النوع الحالي] وتقرّر: إن وُجد سجلّ
     * اليوم لهذا النوع (id غير فارغ) تدخل وضع التعديل (تعبئة بياناته + إظهار
     * "حذف السجلّ")، وإلا وضع تسجيل جديد (تعبئة اقتراح الموضع التالي إن وُجد).
     */
    function applyQuickLogType() {
        logModalType = logModalTypeSelect.value;
        const data = quickLogTodayData[logModalType] || {};
        const isEditing = !! data.logId;
        logModalEditingId = isEditing ? data.logId : null;

        logModalTitle.textContent = isEditing
            ? (logModalType === 'مراجعة' ? 'تعديل سجلّ المراجعة' : 'تعديل سجلّ الدرس')
            : (logModalType === 'مراجعة' ? 'تسجيل مراجعة' : 'تسجيل درس جديد');
        logModalError.textContent = '';
        logModalDelete.hidden = ! isEditing;

        // (القرار #54) تبدأ دومًا بطريقة "من سورة" الافتراضية — لا نخمّن
        // طريقة إدخال سجلّ قائم عند التعديل، تُعرَض بصورتها المخزَّنة الفعلية.
        logModalEntryMode.value = 'surah';
        syncLogModalEntryMode();

        fillSurahSelects();
        syncLogModalToSurahVisibility();

        logModalSurah.value = '';
        logModalToSurah.value = '';
        logModalFromAyah.value = '';
        logModalToAyah.value = '';
        logModalStatus.value = '';

        if (isEditing) {
            logModalSurah.value = data.surahId || '';
            logModalToSurah.value = data.toSurahId || '';
            logModalFromAyah.value = data.fromAyah || '';
            logModalToAyah.value = data.toAyah || '';
            logModalStatus.value = data.status || '';
        } else if (data.nextSurah) {
            logModalSurah.value = data.nextSurah;
            logModalToAyah.value = data.nextAyah || '';
        }
        syncLogModalAyahMax();
    }

    logModalTypeSelect.addEventListener('change', applyQuickLogType);

    function openQuickLogModal(btn) {
        logModalStudentId = btn.dataset.studentId;
        logModalSubtitle.textContent = btn.dataset.studentName ?? '';
        quickLogTodayData = {
            'حفظ': {
                logId: btn.dataset.lessonLogId || null,
                surahId: btn.dataset.lessonSurahId,
                toSurahId: btn.dataset.lessonToSurahId,
                fromAyah: btn.dataset.lessonFromAyah,
                toAyah: btn.dataset.lessonToAyah,
                status: btn.dataset.lessonStatus,
                nextSurah: btn.dataset.lessonNextSurah,
                nextAyah: btn.dataset.lessonNextAyah,
            },
            'مراجعة': {
                logId: btn.dataset.reviewLogId || null,
                surahId: btn.dataset.reviewSurahId,
                toSurahId: btn.dataset.reviewToSurahId,
                fromAyah: btn.dataset.reviewFromAyah,
                toAyah: btn.dataset.reviewToAyah,
                status: btn.dataset.reviewStatus,
                nextSurah: btn.dataset.reviewNextSurah,
                nextAyah: btn.dataset.reviewNextAyah,
            },
        };

        logModalTypeSelect.value = 'حفظ';
        applyQuickLogType();

        logModalBackdrop.hidden = false;
        logModalTypeSelect.focus();
    }

    function closeLogModal() {
        logModalBackdrop.hidden = true;
        logModalStudentId = null;
        logModalType = null;
        logModalEditingId = null;
    }

    table.addEventListener('click', (e) => {
        const quickLogBtn = e.target.closest('.open-quick-log-modal');
        if (quickLogBtn) openQuickLogModal(quickLogBtn);
    });

    logModalClose.addEventListener('click', closeLogModal);
    logModalCancel.addEventListener('click', closeLogModal);
    logModalBackdrop.addEventListener('click', (e) => {
        if (e.target === logModalBackdrop) closeLogModal();
    });

    logModalDelete.addEventListener('click', async () => {
        if (! logModalEditingId || ! logModalStudentId) return;
        const confirmed = await confirmDialog('حذف هذا السجلّ؟ لا يمكن التراجع.');
        if (! confirmed) return;

        try {
            await apiFetch(`/dashboard/${logModalStudentId}/logs/${logModalEditingId}`, { method: 'DELETE' });
            toast('تم حذف السجلّ.', 'success');
            window.location.reload();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    logModalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (! logModalStudentId) return;

        let payload;

        // (القرار #54) وضع الجزء/الحزب: تُحلّ الوحدتان المختارتان لسورة/آية
        // فعليّتين هنا (نفس طريقة عمل QuranUnitReference في الخادم، بلا أي
        // حساب إضافي في JS — القيم جاهزة أصلًا في QURAN_UNITS)، ثم تُرسَل عبر
        // نفس نقطة النهاية ونفس حقول الطلب تمامًا كوضع "من سورة" — لا تغيير
        // في الخادم إطلاقًا.
        if (logModalType === 'مراجعة' && logModalEntryMode.value === 'unit') {
            const units = QURAN_UNITS[logModalUnitType.value] || [];
            const fromUnit = findUnit(units, logModalUnitFrom.value);

            if (! fromUnit) {
                logModalError.textContent = 'اختر الوحدة (الجزء/الحزب) أولًا.';
                return;
            }

            const toUnit = findUnit(units, logModalUnitTo.value) || fromUnit;

            payload = {
                surah_id: fromUnit.from_surah_id,
                to_surah_id: toUnit.to_surah_id,
                from_ayah: fromUnit.from_ayah,
                to_ayah: toUnit.to_ayah,
                type: logModalType,
                status: logModalStatus.value || null,
                logged_at: TODAY,
            };
        } else {
            payload = {
                surah_id: logModalSurah.value || null,
                to_surah_id: logModalToSurah.value || null,
                from_ayah: logModalFromAyah.value || null,
                to_ayah: logModalToAyah.value || null,
                type: logModalType,
                status: logModalStatus.value || null,
                logged_at: TODAY,
            };
        }
        Object.keys(payload).forEach((k) => { if (payload[k] === null) delete payload[k]; });

        const saveBtn = document.getElementById('logModalSave');
        logModalError.textContent = '';

        // (تصحيح صريح من يحيى، أيقونة تعديل ✎): وضع التعديل يرسل PATCH لنفس
        // السجلّ بدل POST سجلّ جديد — راجع RecitationLogController::update().
        const isEditing = !! logModalEditingId;
        const url = isEditing
            ? `/dashboard/${logModalStudentId}/logs/${logModalEditingId}`
            : `/dashboard/${logModalStudentId}/logs`;
        const method = isEditing ? 'PATCH' : 'POST';

        try {
            // Queueable (S12، نفس فلسفة نموذج "إضافة سجلّ" في صفحة الطالب):
            // بلا اتصال يُحفَظ محليًا ويُرسَل تلقائيًا عند عودة الشبكة.
            const res = await withButtonLoading(saveBtn, () => apiFetchQueueable(url, { method, body: payload }, 'سجلّ حفظ/مراجعة'));
            if (res.queued) {
                toast('لا يوجد اتصال — سيُحفَظ السجلّ تلقائيًا عند عودته.', 'info');
                closeLogModal();
            } else {
                toast(isEditing ? 'تمّ تعديل السجلّ.' : 'تمّ حفظ السجلّ.', 'success');
                window.location.reload();
            }
        } catch (error) {
            if (error.status === 422 && error.errors) {
                logModalError.textContent = Object.values(error.errors)[0][0];
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    });

    /* ===== استيراد الطلاب من ملف — CSV (S11) أو Excel حقيقي (S17) ===== */
    const importBtn = document.getElementById('importStudentsBtn');
    const importInput = document.getElementById('importFileInput');

    importBtn.addEventListener('click', () => importInput.click());

    importInput.addEventListener('change', async () => {
        const file = importInput.files[0];
        if (!file) return;

        const meta = document.querySelector('meta[name="csrf-token"]');
        const formData = new FormData();
        formData.append('file', file);

        importBtn.disabled = true;
        importBtn.textContent = 'جارٍ الاستيراد…';

        try {
            const response = await fetch('/dashboard/import', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': meta?.content ?? '', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
                credentials: 'same-origin',
            });
            const json = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(json?.message ?? 'تعذّر استيراد الملف.');
            }

            toast(json.message, 'success', { duration: 4500 });
            if (json.data?.notes?.length) {
                setTimeout(() => toast(json.data.notes[0] + (json.data.notes.length > 1 ? ` (و${json.data.notes.length - 1} ملاحظة أخرى)` : ''), 'warning', { duration: 6000 }), 400);
            }
            if (json.data?.created > 0) {
                setTimeout(() => window.location.reload(), 1200);
            }
        } catch (error) {
            toast(error.message ?? 'تعذّر استيراد الملف.', 'error');
        } finally {
            importBtn.disabled = false;
            importBtn.textContent = 'استيراد من ملف (CSV أو Excel)';
            importInput.value = '';
        }
    });

    {{-- إدارة الحلقات (إضافة/حذف) لم تعد هنا — راجع comment أعلى الصفحة
         وresources/views/circles/index.blade.php للنسخة الحالية من هذا
         المنطق في صفحتها المستقلّة الجديدة. --}}
});
</script>
@endpush
