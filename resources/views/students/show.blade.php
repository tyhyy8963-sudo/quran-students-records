@extends('layouts.app')

@section('title', $student->student_name.' - سجلّ الطالب - رِواق')

@php
    $radius = 42;
    $circumference = 2 * pi() * $radius;
    $percent = $student->progressPercentage();
    $offset = $circumference * (1 - min($percent, 100) / 100);
@endphp

@section('content')
    <div class="page-title-row">
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-ghost">→ العودة للوحة</a>
        {{-- تصدير/طباعة سجلّ هذا الطالب كاملًا (S37 — بند 2 من خطّة التقارير
             المعتمَدة) — راجع StudentController::report()/students/report.blade.php. --}}
        <a href="{{ route('students.report', $student->student_id) }}" class="btn btn-secondary">تقرير الطالب / طباعة</a>
    </div>

    {{--
        بطاقات إحصائية أعلى الصفحة (S23.5 — إعادة التصميم بنمط هرماس، مستأنَفة
        بعد التوقّف المؤقّت لصالح المتون/التقارير): مخطّط يحيى المرجعي لصفحة
        الطالب يعرض أرقامًا صريحة (لا نسبًا فقط) في صفّ بطاقات أعلى الصفحة —
        نفس صنف `.stat-cards`/`.stat-card` المستعمل أصلًا في لوحة التقارير
        (`reports/index.blade.php`) لا صنف جديد، حفاظًا على تناسق الشكل بين
        صفحات البرنامج. الأرقام الثلاثة الأولى عدّادات خام من بيانات موجودة
        أصلًا (لا ميزة جديدة)؛ الرابع نفس نسبة الحفظ المعروضة بالحلقة أدناه —
        تكرار مقصود (رقم سريع أعلى الصفحة + حلقة تفصيلية أسفلها)، لا استبدال
        لقسم الحلقة/الحقائق الموجود، الذي بقي كما هو بلا أي تعديل.
    --}}
    <div class="stat-cards" style="margin-bottom: var(--space-5);">
        <div class="card card-pad stat-card">
            <div class="stat-label">نسبة الحفظ</div>
            <div class="stat-value">{{ $percent }}%</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">أيام الحضور</div>
            <div class="stat-value">{{ $attendanceDaysCount }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">إجمالي المراجعات</div>
            <div class="stat-value">{{ $totalReviewsCount }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">السور المكتملة</div>
            <div class="stat-value">{{ $completedSurahs->count() }}</div>
        </div>
    </div>

    <div class="card card-pad">
        <div class="student-header">
            <div>
                <h1 id="studentNameHeading">{{ $student->student_name }}</h1>
                <p class="text-muted" id="studentCircleLine">
                    <span id="studentCircleText">{{ $student->circle->name ?? 'بلا حلقة' }}</span>
                    @if ($student->hasCompletedQuran())
                        · <span class="badge badge-active">أتمّ حفظ القرآن</span>
                    @endif
                </p>

                {{--
                    (طلب صريح من يحيى): زرّا "تعديل" (اسم الطالب + الحلقة معًا)
                    و"حذف" الطالب — كانا على صفّ الطالب في اللوحة الرئيسية،
                    أُزيلا منه في تصحيح سابق (لغيابهما عن الصور المرجعية)
                    بانتظار مكان بديل. يحيى حسم المكان صراحةً: "داخل سجلّ
                    الطالب... أعلى اليسار". `.student-header` أصلًا
                    `display:flex; justify-content:space-between` فوضعهما هنا
                    كعنصر شقيق ثانٍ يضعهما تلقائيًا في الطرف المقابل من الاسم
                    (اليسار في dir=rtl) بلا CSS إضافي.
                --}}
                <form id="editStudentForm" class="form-grid-2" hidden style="margin-top: var(--space-3); max-width: 420px;">
                    <div class="field">
                        <label class="field-label" for="editStudentName">اسم الطالب</label>
                        <input class="input" type="text" id="editStudentName" name="student_name" required maxlength="255">
                        <span class="field-error" data-for="student_name"></span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="editStudentCircle">الحلقة</label>
                        <select class="input" id="editStudentCircle" name="circle_id">
                            <option value="">بلا حلقة</option>
                            @foreach ($circles as $circle)
                                <option value="{{ $circle->id }}">{{ $circle->name }}</option>
                            @endforeach
                        </select>
                        <span class="field-error" data-for="circle_id"></span>
                    </div>
                    <div style="grid-column: 1 / -1; display: flex; gap: var(--space-2);">
                        <button type="submit" class="btn btn-sm btn-primary" id="editStudentSaveBtn">حفظ</button>
                        <button type="button" class="btn btn-sm btn-ghost" id="editStudentCancelBtn">إلغاء</button>
                    </div>
                </form>

                {{--
                    محرِّر حالة العضوية (نشط/منقطع/منتقل) — انتقل إلى هنا من
                    صفّ الطالب في اللوحة الرئيسية (S23، تصحيح): ذلك العمود صار
                    لعرض/تسجيل حضور اليوم بدلًا من حالة العضوية. حفظ تلقائي
                    فور التغيير، بنفس مبدأ بقية اللوحة (S11).
                --}}
                <div class="field" id="studentStatusField" style="max-width: 220px; margin-top: var(--space-2);">
                    <label class="field-label" for="studentStatus">حالة العضوية</label>
                    <select class="input" id="studentStatus" aria-label="حالة عضوية {{ $student->student_name }}">
                        @foreach (\App\Models\Student::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($student->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="student-header-actions">
                <button type="button" class="btn btn-sm btn-ghost" id="editStudentBtn"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> تعديل</button>
                <button type="button" class="btn btn-sm btn-ghost btn-danger-ghost" id="deleteStudentBtn">حذف الطالب</button>
            </div>
        </div>

        <div class="progress-summary" style="margin-top: var(--space-5);">
            <div class="progress-ring-wrap">
                <svg viewBox="0 0 100 100" width="96" height="96">
                    <circle class="progress-ring-track" cx="50" cy="50" r="{{ $radius }}"></circle>
                    <circle class="progress-ring-value" cx="50" cy="50" r="{{ $radius }}"
                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"></circle>
                </svg>
                <div class="progress-ring-label">{{ $percent }}%</div>
            </div>
            <div class="progress-facts">
                @if ($student->latestMemorizationLog)
                    <div class="position">آخر موضع: {{ $student->latestMemorizationLog->surah->name ?? '—' }} · آية {{ $student->latestMemorizationLog->to_ayah }}</div>
                @else
                    <div class="position text-muted">لم يبدأ الحفظ بعد</div>
                @endif

                {{-- آخر موضع مراجعة (S15) — مستقلّ عن موضع الحفظ أعلاه، فقد
                     يراجع الطالب مقطعًا سابقًا بعيدًا عن آخر ما حفظه. --}}
                @if ($student->latestReviewLog)
                    <div class="position">
                        آخر مراجعة: {{ $student->latestReviewLog->surah->name ?? '—' }}
                        @if ($student->latestReviewLog->from_ayah)
                            — آية {{ $student->latestReviewLog->from_ayah }} إلى {{ $student->latestReviewLog->to_ayah }}
                        @else
                            — حتى آية {{ $student->latestReviewLog->to_ayah }}
                        @endif
                    </div>
                @else
                    <div class="position text-muted">لا مراجعة مسجَّلة بعد</div>
                @endif

                <div class="progress-stats">
                    <span class="progress-stat">
                        <span class="stat-value">{{ $completedSurahs->count() }}</span>
                        <span class="stat-label">من {{ \App\Models\Surah::COUNTABLE_COUNT }} سورة مكتملة</span>
                    </span>
                    @if ($furthestSurah)
                        <span class="progress-stat">
                            <span class="stat-value">{{ $furthestSurah->name }}</span>
                            <span class="stat-label">أبعد سورة في ترتيب الحفظ</span>
                        </span>
                    @endif
                    {{-- نسبة المراجعة (S16) — موزونة بالأرباع الـ240 مثل الحفظ تمامًا،
                         لكنها رقم مستقلّ كليًا: قد يراجع الطالب مقاطع بعيدة عن آخر ما
                         حفظه، أو العكس. --}}
                    <span class="progress-stat">
                        <span class="stat-value">{{ $reviewPercent }}%</span>
                        <span class="stat-label">
                            نسبة المراجعة
                            ({{ $reviewQuartersDone }} من {{ \App\Models\Quarter::COUNT }} ربعًا
                            @if ($reviewHizbDone > 0)
                                — {{ $reviewHizbDone }} حزبًا{{ $reviewQuarterRemainder ? ' و'.$reviewQuarterRemainder.' ربع' : '' }}
                            @endif
                            )
                        </span>
                    </span>
                </div>

                <div class="hint">
                    نسبة الحفظ محسوبة بحجم القرآن الفعلي (أرباع الأحزاب الـ240) لا بعدد
                    السور، فكل ربع له وزنه الحقيقي بدل أن تتساوى السور الطويلة
                    والقصيرة. تسجيل سورة بعيدة في ترتيب الحفظ (من الناس صعودًا إلى
                    البقرة) يُكمل تلقائيًا كل ما قبلها في هذا الترتيب.
                    الفاتحة مستثناة من العدّ ولا تُضيف للنسبة وإن سُمِّعت أو روجعت.
                    نسبة المراجعة بنفس الأرباع أيضًا، ويمكن تسجيل مراجعة تمتدّ من
                    آية في سورة إلى آية في سورة لاحقة (حزبًا أو نصف حزب) بسطر واحد
                    بدل سطر لكل سورة.
                </div>
            </div>
        </div>
    </div>

    {{-- المتون (S15) — تتبّع متوازٍ: أيّ عدد من الخمسة معًا لا متن واحد نشط. --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">المتون</h2>
        <div id="poemsList">
            @forelse ($poemsData as $entry)
                <div class="poem-entry" data-poem-id="{{ $entry->poem->id }}"
                     style="margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px solid rgba(127,127,127,.25);">
                    <div class="position"><strong>{{ $entry->poem->name }}</strong> — {{ $entry->percent }}%</div>
                    @if ($entry->latest_memorization)
                        <div class="position">آخر حفظ: بيت {{ $entry->latest_memorization->to_bayt }}</div>
                    @else
                        <div class="position text-muted">لم يبدأ الحفظ بعد</div>
                    @endif
                    @if ($entry->latest_review)
                        <div class="position">آخر مراجعة: من بيت {{ $entry->latest_review->from_bayt ?? 1 }} إلى بيت {{ $entry->latest_review->to_bayt }}</div>
                    @endif
                    {{-- منحنى حفظ هذا المتن (S16) — بنفس مبدأ منحنيي الحفظ
                         والمراجعة أعلاه، لكن لكل متن على حدة داخل بطاقته. --}}
                    @if ($entry->chart_points->isNotEmpty())
                        <canvas id="poemChart-{{ $entry->poem->id }}" height="120" style="margin-top: var(--space-2);"></canvas>
                    @endif
                </div>
            @empty
                {{-- (S24 — بطلب صريح من يحيى): "أرضية متن" اليدوية أُلغيت
                     نهائيًا، فلم يعد لهذه الرسالة أن تعرض بديلًا عنها. --}}
                <p class="text-muted" id="poemsEmpty">لا متون مُتتبَّعة بعد لهذا الطالب — أضف أول سجلّ حفظ/مراجعة متن أدناه.</p>
            @endforelse
        </div>
    </div>

    {{--
        قسم "التحليلات والإحصائيات" (S23.5 — إعادة التصميم بنمط هرماس): جمع
        بطاقات المنحنيَين والتقويم — شبكة (`.analytics-grid`) عمودين على
        الشاشات الواسعة. كل بطاقة داخلها بمضمونها ومنطقها الأصليَّين حرفيًا
        بلا أي تغيير سوى الغلاف.

        (تعديل لاحق، نفس S23.5، بطلب يحيى بعد معاينته الفعلية): بطاقة "نشاط
        المراجعة الشهري" (أعمدة شهرية) أُلغيت نهائيًا من هذه الشبكة — أبقى
        يحيى فقط على المنحنيَين التراكميَّين (تقدّم الحفظ + تقدّم المراجعة)،
        وطلب أن يحصل منحنى المراجعة أيضًا على مبدّل تجميع يومي/أسبوعي/شهري
        خاصّ به مرئيًا في بطاقته، بجانب مبدّل منحنى الحفظ. المنحنيان أصلًا
        يشتركان نفس حالة التجميع في الخلفية (كلاهما داخل مصفوفة controllers
        في student-timeline.js)، فأصبح لكل بطاقة الآن مجموعة أزرارها الخاصّة
        وكلتاهما تُحدَّثان معًا بفضل هذا التشارك (راجع تعليق الملف نفسه).
    --}}
    <h2 style="margin-top: var(--space-5); margin-bottom: var(--space-3);">التحليلات والإحصائيات</h2>
    <div class="analytics-grid">
        <div class="card card-pad chart-card">
            <div class="chart-card-head">
                <h2>منحنى تقدّم الحفظ</h2>
                {{-- تبديل التجميع (S16): يوقّت منحنيي الحفظ والمراجعة معًا
                     (والمتون أدناه) — النقاط تبقى نسبة تراكمية، فالتجميع يأخذ
                     آخر نقطة في كل حاوية زمنية لا مجموعها أو متوسطها. --}}
                <div id="chartGranularityToggle" class="granularity-toggle">
                    <button type="button" class="btn btn-sm btn-ghost is-active" data-granularity="daily">يومي</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-granularity="weekly">أسبوعي</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-granularity="monthly">شهري</button>
                </div>
            </div>
            @if ($chartPoints->isEmpty())
                <p class="text-muted">لا بيانات كافية لعرض منحنى بعد — أضف أول سجلّ حفظ.</p>
            @else
                <canvas id="progressChart" height="220"></canvas>
            @endif
        </div>

        {{-- منحنى تقدّم المراجعة (S23.5) — نظير منحنى الحفظ أعلاه تمامًا،
             بما فيه مبدّل التجميع يومي/أسبوعي/شهري الخاصّ به (بطلب يحيى) —
             يعمل بالاشتراك الفعلي مع مبدّل منحنى الحفظ (كلاهما ضمن نفس
             currentGranularity في student-timeline.js)، فالنقر على أي زرّ في
             أي من البطاقتين يحدّث المنحنيَين معًا ويُبقي كل الأزرار متوافقة
             بصريًا. --}}
        <div class="card card-pad chart-card">
            <div class="chart-card-head">
                <h2>منحنى تقدّم المراجعة</h2>
                <div class="granularity-toggle" data-granularity-group>
                    <button type="button" class="btn btn-sm btn-ghost is-active" data-granularity="daily">يومي</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-granularity="weekly">أسبوعي</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-granularity="monthly">شهري</button>
                </div>
            </div>
            @if ($reviewChartPoints->isEmpty())
                <p class="text-muted">لا بيانات كافية لعرض منحنى بعد — أضف أول سجلّ مراجعة.</p>
            @else
                <canvas id="reviewProgressChart" height="220"></canvas>
            @endif
        </div>

        <div class="card card-pad calendar-card">
            <div class="calendar-nav">
                <a href="?month={{ $monthStart->copy()->subMonth()->format('Y-m') }}" class="btn btn-sm btn-ghost">← الشهر السابق</a>
                <span class="month-label">{{ $monthStart->translatedFormat('F Y') }}</span>
                <a href="?month={{ $monthStart->copy()->addMonth()->format('Y-m') }}" class="btn btn-sm btn-ghost">الشهر التالي →</a>
            </div>
            <div class="calendar-grid">
                @foreach (['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'] as $weekday)
                    <div class="calendar-weekday">{{ $weekday }}</div>
                @endforeach
                @php $leadingBlanks = $monthStart->copy()->startOfMonth()->dayOfWeek; @endphp
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div class="calendar-day is-empty"></div>
                @endfor
                @for ($day = 1; $day <= $monthStart->daysInMonth; $day++)
                    @php
                        $dateStr = $monthStart->copy()->day($day)->toDateString();
                        $entry = $attendanceByDate->get($dateStr);
                    @endphp
                    <div class="calendar-day" @if ($entry) data-status="{{ $entry->status }}" title="{{ $entry->status }}" @endif>
                        <span class="day-num">{{ $day }}</span>
                    </div>
                @endfor
            </div>
            <div class="calendar-legend">
                @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                    {{-- data-status لا class خام (S22): بعض القيم الجديدة تحتوي مسافة
                         ("غائب بعذر")، فوضعها كاسم صنف CSS مباشرة يُقسِّمها المتصفّح
                         خطأً إلى صنفين منفصلين. --}}
                    <span><span class="dot" data-status="{{ $value }}"></span>{{ $label }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- (تصحيح صريح من يحيى): "إضافة سجلّ جديد" الموحّدة (نوع واحد يبدّل بين
         حفظ/مراجعة) استُبدلت ببطاقتين منفصلتين — كل نوع له نموذجه الخاص،
         فلا حاجة لقائمة "النوع" أصلًا؛ "إلى سورة" بقيت حصرًا في نموذج
         المراجعة لأنها ميزة تخصّها هي فقط (راجع S16 وتصحيح المصحف/الاتجاه
         الحرّ للمراجعة في StoreRecitationLogRequest). --}}
    {{-- (S23.5 — استئناف تصميم صفحة الطالب بنمط هرماس، بطلب يحيى "يلا أبدأ
         التصميم"): نموذجا "حفظ" و"مراجعة" كانا بطاقتين مستقلّتين تمامًا فوق
         بعضهما (كل واحدة تملأ عرض الصفحة كاملًا) — صارا الآن جنبًا لجنب في
         شبكة واحدة (`.paired-forms-grid`)، بنفس نمط `.analytics-grid`
         المستعمل أصلًا في نفس الصفحة لشبكة "التحليلات والإحصائيات" (grid
         مرن `auto-fit`، لا صنف جديد من الصفر). على الجوّال (عرض أقل من حدّ
         الشبكة) تتراصّان عموديًا تلقائيًا كما كانتا. لا تغيير في أيّ منطق
         أو معرّف داخل النموذجين أنفسهما — تصحيح تخطيط بصري بحت. --}}
    <div class="paired-forms-grid">
    <div class="card card-pad">
        <h2 class="mt-0">إضافة سجلّ حفظ (درس) جديد</h2>
        <form id="addLessonLogForm">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label" for="lesson_surah_id">من سورة</label>
                    {{-- القائمة بترتيب الحفظ (الناس أولًا) لا بترتيب المصحف:
                         السورة التالية للطالب تقع دائمًا قرب أعلى القائمة بدل
                         أن تكون بعد تمرير مئة سورة. الفاتحة في آخرها مع تنويه
                         أنها لا تُحتسب، فلا تُختار بالخطأ ظنًّا أنها تُقدّم النسبة. --}}
                    <select class="input" id="lesson_surah_id" name="surah_id" required>
                        <option value="">— اختر —</option>
                        @foreach ($surahs as $surah)
                            <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}" data-number="{{ $surah->number }}">
                                {{ $surah->name }}@if ($surah->excluded_from_progress) (لا تُحتسب في النسبة)@endif
                            </option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="surah_id"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="lesson_from_ayah">من آية (اختياري)</label>
                    <input class="input" type="number" min="1" id="lesson_from_ayah" name="from_ayah">
                    <span class="field-error" data-for="from_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="lesson_to_ayah">إلى آية</label>
                    <input class="input" type="number" min="1" id="lesson_to_ayah" name="to_ayah" required>
                    <span class="field-error" data-for="to_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="lesson_status">حالة الحفظ (اختياري)</label>
                    <select class="input" id="lesson_status" name="status">
                        <option value="">—</option>
                        @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="status"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="lesson_logged_at">التاريخ</label>
                    <input class="input" type="date" id="lesson_logged_at" name="logged_at"
                           value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    <span class="field-error" data-for="logged_at"></span>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="lesson_notes">ملاحظات (اختياري)</label>
                <textarea class="input" id="lesson_notes" name="notes" rows="2" maxlength="1000"></textarea>
                <span class="field-error" data-for="notes"></span>
            </div>
            <button type="submit" class="btn btn-primary" id="addLessonLogBtn">إضافة سجلّ الحفظ</button>
        </form>
    </div>

    <div class="card card-pad">
        <h2 class="mt-0">إضافة سجلّ مراجعة جديد</h2>
        <form id="addReviewLogForm">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label" for="review_surah_id">من سورة</label>
                    <select class="input" id="review_surah_id" name="surah_id" required>
                        <option value="">— اختر —</option>
                        @foreach ($surahs as $surah)
                            <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}" data-number="{{ $surah->number }}">
                                {{ $surah->name }}@if ($surah->excluded_from_progress) (لا تُحتسب في النسبة)@endif
                            </option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="surah_id"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="review_to_surah_id">إلى سورة (اختياري — لمراجعة تمتدّ لعدّة سور)</label>
                    {{-- S16، والاتجاه هنا حرّ تمامًا (تصحيح صريح من يحيى): قد
                         يمتدّ سجلّ واحد من آية في "من سورة" أعلاه إلى آية في
                         هذه السورة سواء كانت تالية أو سابقة لها في المصحف —
                         بلا أي فرض ترتيب، فالمراجعة قد تسير بأي اتجاه. --}}
                    <select class="input" id="review_to_surah_id" name="to_surah_id">
                        <option value="">— نفس السورة —</option>
                        @foreach ($surahs as $surah)
                            <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}" data-number="{{ $surah->number }}">
                                {{ $surah->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="to_surah_id"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="review_from_ayah">من آية (اختياري)</label>
                    <input class="input" type="number" min="1" id="review_from_ayah" name="from_ayah">
                    <span class="field-error" data-for="from_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="review_to_ayah">إلى آية</label>
                    <input class="input" type="number" min="1" id="review_to_ayah" name="to_ayah" required>
                    <span class="field-error" data-for="to_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="review_status">حالة الحفظ (اختياري)</label>
                    <select class="input" id="review_status" name="status">
                        <option value="">—</option>
                        @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="status"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="review_logged_at">التاريخ</label>
                    <input class="input" type="date" id="review_logged_at" name="logged_at"
                           value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    <span class="field-error" data-for="logged_at"></span>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="review_notes">ملاحظات (اختياري)</label>
                <textarea class="input" id="review_notes" name="notes" rows="2" maxlength="1000"></textarea>
                <span class="field-error" data-for="notes"></span>
            </div>
            <button type="submit" class="btn btn-primary" id="addReviewLogBtn">إضافة سجلّ المراجعة</button>
        </form>
    </div>
    </div>

    {{-- إضافة سجلّ متن (S15) — نظير النموذج أعلاه لكن بوحدة "بيت" ولأيّ من
         المتون الخمسة، مستقلّة كليًا عن سجلّات القرآن. --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">إضافة سجلّ متن (حفظ/مراجعة)</h2>
        <form id="addPoemLogForm">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label" for="poem_log_poem_id">المتن</label>
                    <select class="input" id="poem_log_poem_id" name="poem_id" required>
                        <option value="">— اختر —</option>
                        @foreach ($allPoems as $poem)
                            <option value="{{ $poem->id }}" data-bayt-count="{{ $poem->bayt_count }}">{{ $poem->name }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="poem_id"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_log_type">النوع</label>
                    <select class="input" id="poem_log_type" name="type" required>
                        @foreach (\App\Models\RecitationLog::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="type"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_log_from_bayt">من بيت (اختياري)</label>
                    <input class="input" type="number" min="1" id="poem_log_from_bayt" name="from_bayt">
                    <span class="field-error" data-for="from_bayt"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_log_to_bayt">إلى بيت</label>
                    <input class="input" type="number" min="1" id="poem_log_to_bayt" name="to_bayt" required>
                    <span class="field-error" data-for="to_bayt"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_log_status">حالة الحفظ (اختياري)</label>
                    <select class="input" id="poem_log_status" name="status">
                        <option value="">—</option>
                        @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="status"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_log_logged_at">التاريخ</label>
                    <input class="input" type="date" id="poem_log_logged_at" name="logged_at"
                           value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    <span class="field-error" data-for="logged_at"></span>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="poem_log_notes">ملاحظات (اختياري)</label>
                <textarea class="input" id="poem_log_notes" name="notes" rows="2" maxlength="1000"></textarea>
                <span class="field-error" data-for="notes"></span>
            </div>
            <button type="submit" class="btn btn-primary" id="addPoemLogBtn">إضافة سجلّ المتن</button>
        </form>
    </div>

    {{-- (S23.5 — استئناف تصميم صفحة الطالب بنمط هرماس): "الخط الزمني" (قائمة
         عمودية واحدة، كل سجلّ صفّ كامل العرض متّصل بخطّ رأسي جانبي) صار
         "السجلّات الأخيرة" — بطاقات مستقلّة في شبكة أفقية (`.records-grid`)
         تتدفّق يمينًا/يسارًا ثم تلتفّ سطرًا جديدًا، طبقًا لمخطّط يحيى. كل
         محتوى السجلّ (نوعه، تاريخه، مداه، حالته، ملاحظاته، زرّ حذفه) بقي كما
         هو حرفيًا — فقط أُعيد ترتيبه داخل بطاقة مستقلّة بدل صفّ فيه خطّ
         جانبي. `id="timelineList"`/الصنف `.delete-log` لم يتغيّرا (مستمع
         الحذف في السكربت أدناه مربوط بهما فقط، لا بأي صنف عرض). --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">السجلّات الأخيرة</h2>
        <div class="records-grid" id="timelineList">
            @forelse ($logs as $log)
                <div class="record-card" data-type="{{ $log->type }}">
                    <div class="record-card-head">
                        <span class="type-chip type-{{ $log->type }}">{{ $log->typeLabel() }}</span>
                        <span class="timeline-date">{{ $log->logged_at->format('Y-m-d') }}</span>
                    </div>
                    <p class="record-card-title">
                        {{ $log->surah->name ?? '—' }}
                        @if ($log->spansMultipleSurahs())
                            {{-- مراجعة عابرة لعدّة سور (S16). --}}
                            @if ($log->from_ayah)
                                — من آية {{ $log->from_ayah }}
                            @endif
                            إلى سورة {{ $log->toSurah->name ?? '—' }} آية {{ $log->to_ayah }}
                        @elseif ($log->from_ayah)
                            — آية {{ $log->from_ayah }} إلى {{ $log->to_ayah }}
                        @else
                            — حتى آية {{ $log->to_ayah }}
                        @endif
                    </p>
                    @if ($log->notes)
                        <p class="timeline-notes">{{ $log->notes }}</p>
                    @endif
                    <div class="record-card-footer">
                        @if ($log->status)
                            <span class="status-chip">{{ \App\Models\RecitationLog::STATUSES[$log->status] ?? $log->status }}</span>
                        @else
                            <span></span>
                        @endif
                        <button type="button" class="btn btn-sm btn-ghost delete-log" data-id="{{ $log->id }}">حذف</button>
                    </div>
                </div>
            @empty
                <p class="text-muted" id="timelineEmpty">لا سجلّات بعد.</p>
            @endforelse
        </div>
        {{ $logs->links('vendor.pagination.custom') }}
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/student-timeline.js'])
    <script>
        window.KeshfChartData = @json($chartPoints);
        {{-- منحنى تقدّم المراجعة (S23.5، جديد) — نفس شكل KeshfChartData
             بالضبط (date/percent)، يُستهلَك في student-timeline.js بنفس
             buildChart() المستعملة لمنحنى الحفظ. --}}
        window.KeshfReviewProgressData = @json($reviewChartPoints);
        {{-- خريطة معرّف متن ⇐ نقاطه (S16) لا مصفوفة: JS يقرن كل مدخل بقماشه
             الخاص (poemChart-{id}) بلا افتراض ترتيب متطابق مع الصفحة. --}}
        window.KeshfPoemChartData = @json($poemsData->pluck('chart_points', 'poem.id'));
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const { apiFetch, apiFetchQueueable, toast, confirmDialog, withButtonLoading, applyFieldErrors } = window.KeshfApp;
        const studentId = {{ $student->student_id }};
        // (تعديل الاسم أصبح ممكنًا من هذه الصفحة نفسها الآن) — كانت ثابتة،
        // صارت متغيّرة لتبقى محدَّثة بعد كل تعديل ناجح للاسم، لأن معالج حالة
        // العضوية أدناه يرسلها دومًا مع كل حفظ (UpdateStudentRequest يتطلّبها).
        let studentName = @json($student->student_name);

        /* ===== حالة العضوية (S23، منقولة من صفّ اللوحة الرئيسية) ===== */
        const studentStatusSelect = document.getElementById('studentStatus');
        let savedStatus = studentStatusSelect.value;
        studentStatusSelect.addEventListener('change', async () => {
            const value = studentStatusSelect.value;
            try {
                await apiFetch(`/dashboard/${studentId}`, {
                    method: 'PATCH',
                    // student_name مطلوب دائمًا في UpdateStudentRequest حتى
                    // عند تعديل الحالة وحدها — يُرسَل بقيمته الحالية بلا تغيير.
                    body: { student_name: studentName, status: value },
                });
                savedStatus = value;
                toast('تم حفظ الحالة.', 'success');
            } catch (error) {
                studentStatusSelect.value = savedStatus;
                if (error.status === 422 && error.errors) {
                    toast(Object.values(error.errors)[0][0], 'error');
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        /* ===== تعديل اسم الطالب/الحلقة + حذف الطالب (طلب صريح من يحيى:
           الزرّان انتقلا إلى هنا من صفّ اللوحة الرئيسية، أعلى يسار سجلّ
           الطالب) ===== */
        const editBtn = document.getElementById('editStudentBtn');
        const deleteBtn = document.getElementById('deleteStudentBtn');
        const editForm = document.getElementById('editStudentForm');
        const editNameInput = document.getElementById('editStudentName');
        const editCircleSelect = document.getElementById('editStudentCircle');
        const editCancelBtn = document.getElementById('editStudentCancelBtn');
        const studentCircleText = document.getElementById('studentCircleText');
        const studentNameHeading = document.getElementById('studentNameHeading');
        const currentCircleId = @json($student->circle_id);

        function openEditForm() {
            editNameInput.value = studentName;
            editCircleSelect.value = currentCircleId ?? '';
            editForm.hidden = false;
            editBtn.hidden = true;
            editNameInput.focus();
        }

        function closeEditForm() {
            editForm.hidden = true;
            editBtn.hidden = false;
        }

        editBtn.addEventListener('click', openEditForm);
        editCancelBtn.addEventListener('click', closeEditForm);

        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                student_name: editNameInput.value,
                circle_id: editCircleSelect.value || null,
            };

            const btn = document.getElementById('editStudentSaveBtn');
            try {
                const { data } = await withButtonLoading(btn, () => apiFetch(`/dashboard/${studentId}`, {
                    method: 'PATCH',
                    body: payload,
                }));
                studentName = data.student_name;
                studentNameHeading.textContent = data.student_name;
                studentCircleText.textContent = data.circle?.name ?? 'بلا حلقة';
                document.title = data.student_name + ' - سجلّ الطالب - رِواق';
                closeEditForm();
                toast('تم حفظ البيانات بنجاح.', 'success');
            } catch (error) {
                if (error.status === 422 && error.errors) {
                    applyFieldErrors(editForm, error.errors);
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        deleteBtn.addEventListener('click', async () => {
            const confirmed = await confirmDialog(
                `حذف الطالب "${studentName}"؟ يمكنك التراجع خلال هذه الجلسة فقط.`
            );
            if (!confirmed) return;

            try {
                await apiFetch(`/dashboard/${studentId}`, { method: 'DELETE' });
                toast('تم حذف الطالب.', 'success');
                window.location.href = '{{ route('dashboard') }}';
            } catch (error) {
                if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        /* ===== إضافة سجلّ حفظ (درس) جديد (تصحيح صريح من يحيى: نموذج
           مستقلّ لا يشترك مع المراجعة بقائمة "النوع" ولا بحقل "إلى سورة") ===== */
        const lessonForm = document.getElementById('addLessonLogForm');
        const lessonSurahSelect = document.getElementById('lesson_surah_id');
        const lessonFromAyahInput = document.getElementById('lesson_from_ayah');
        const lessonToAyahInput = document.getElementById('lesson_to_ayah');

        function updateLessonAyahMax() {
            const max = lessonSurahSelect.selectedOptions[0]?.dataset.ayahCount || '';
            lessonFromAyahInput.max = max;
            lessonToAyahInput.max = max;
        }

        lessonSurahSelect.addEventListener('change', updateLessonAyahMax);

        lessonForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(lessonForm).entries());
            payload.type = 'حفظ';
            ['from_ayah', 'status', 'notes'].forEach((k) => { if (!payload[k]) delete payload[k]; });

            const btn = document.getElementById('addLessonLogBtn');
            try {
                // Queueable (S12): بلا اتصال يُحفَظ السجلّ محليًا ويُرسَل تلقائيًا
                // عند عودة الشبكة بدل فشل الإضافة بالكامل — لا تحديث للصفحة في
                // هذه الحالة لأن السجلّ لم يظهر في القاعدة بعد فعليًا.
                const res = await withButtonLoading(btn, () => apiFetchQueueable(`/dashboard/${studentId}/logs`, { method: 'POST', body: payload }, 'سجلّ حفظ'));
                if (res.queued) {
                    lessonForm.reset();
                } else {
                    toast('تمت إضافة سجلّ الحفظ بنجاح.', 'success');
                    window.location.reload();
                }
            } catch (error) {
                if (error.status === 422 && error.errors) {
                    applyFieldErrors(lessonForm, error.errors);
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        /* ===== إضافة سجلّ مراجعة جديد ===== */
        const reviewForm = document.getElementById('addReviewLogForm');
        const reviewSurahSelect = document.getElementById('review_surah_id');
        const reviewToSurahSelect = document.getElementById('review_to_surah_id');
        const reviewFromAyahInput = document.getElementById('review_from_ayah');
        const reviewToAyahInput = document.getElementById('review_to_ayah');

        function updateReviewAyahMax() {
            const fromMax = reviewSurahSelect.selectedOptions[0]?.dataset.ayahCount || '';
            reviewFromAyahInput.max = fromMax;
            // آية النهاية تُقاس بسورة النهاية لو اختِيرت صراحة (مراجعة عابرة
            // لعدّة سور)، وإلا فبنفس سورة البداية كما كان دائمًا.
            const toMax = reviewToSurahSelect.value
                ? (reviewToSurahSelect.selectedOptions[0]?.dataset.ayahCount || '')
                : fromMax;
            reviewToAyahInput.max = toMax;
        }

        reviewSurahSelect.addEventListener('change', updateReviewAyahMax);
        reviewToSurahSelect.addEventListener('change', updateReviewAyahMax);

        reviewForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(reviewForm).entries());
            payload.type = 'مراجعة';
            ['from_ayah', 'to_surah_id', 'status', 'notes'].forEach((k) => { if (!payload[k]) delete payload[k]; });

            const btn = document.getElementById('addReviewLogBtn');
            try {
                const res = await withButtonLoading(btn, () => apiFetchQueueable(`/dashboard/${studentId}/logs`, { method: 'POST', body: payload }, 'سجلّ مراجعة'));
                if (res.queued) {
                    reviewForm.reset();
                } else {
                    toast('تمت إضافة سجلّ المراجعة بنجاح.', 'success');
                    window.location.reload();
                }
            } catch (error) {
                if (error.status === 422 && error.errors) {
                    applyFieldErrors(reviewForm, error.errors);
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        document.getElementById('timelineList').addEventListener('click', async (e) => {
            const btn = e.target.closest('.delete-log');
            if (!btn) return;
            const confirmed = await confirmDialog('حذف هذا السجلّ؟ لا يمكن التراجع.');
            if (!confirmed) return;
            try {
                await apiFetch(`/dashboard/${studentId}/logs/${btn.dataset.id}`, { method: 'DELETE' });
                toast('تم حذف السجلّ.', 'success');
                window.location.reload();
            } catch (error) {
                if (error.status !== 419) toast(error.message, 'error');
            }
        });

        /* ===== إضافة سجلّ متن (S15) ===== */
        const poemLogForm = document.getElementById('addPoemLogForm');
        const poemSurahLikeSelect = document.getElementById('poem_log_poem_id');
        const poemToBaytInput = document.getElementById('poem_log_to_bayt');
        const poemFromBaytInput = document.getElementById('poem_log_from_bayt');

        poemSurahLikeSelect.addEventListener('change', () => {
            const max = poemSurahLikeSelect.selectedOptions[0]?.dataset.baytCount || '';
            poemToBaytInput.max = max;
            poemFromBaytInput.max = max;
        });

        poemLogForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(poemLogForm).entries());
            ['from_bayt', 'status', 'notes'].forEach((k) => { if (!payload[k]) delete payload[k]; });
            const poemId = payload.poem_id;
            delete payload.poem_id;

            const btn = document.getElementById('addPoemLogBtn');
            try {
                await withButtonLoading(btn, () => apiFetch(`/dashboard/${studentId}/poem-logs`, {
                    method: 'POST',
                    body: { ...payload, poem_id: poemId },
                }));
                toast('تمت إضافة سجلّ المتن بنجاح.', 'success');
                window.location.reload();
            } catch (error) {
                if (error.status === 422 && error.errors) {
                    applyFieldErrors(poemLogForm, error.errors);
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });
    });
    </script>
@endpush
