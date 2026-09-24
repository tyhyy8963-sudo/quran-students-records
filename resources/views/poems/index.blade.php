@extends('layouts.app')

@section('title', 'المتون - رِواق')

@section('content')
    {{--
        لوحة طلاب خاصة بالمتون (S24، الجزء الثاني — طلب صريح من يحيى: "لوحة
        طلاب خاصة بالمتون"). أُضيفت قدرات فلترة جديدة بطلب لاحق صريح
        (2026-09-23): "أبغى تخصيص أكثر للبحث حق المتون بحيث لو أبغى أعرضهم
        كلهم أو بس حقون الجزرية أو بس إلي خلص 50%".

        ⚠️ تصحيح واجهة (2026-09-23، نفس اليوم): المحاولة الأولى استبدلت
        تبويبات المتن/الحلقة القديمة بالكامل بلوحتَي <details> منسدلتين، فأبلغ
        يحيى أن "الفلترة القديمة كانت أحلى بكثير" وأن طلبه كان "إضافات
        للفلترة لا حذفها بالكامل وإعادة إنشاءها". أُعيدت تبويبات المتن
        والحلقة (نفس آلية .circle-tabs/.circle-tab في dashboard.blade.php
        حرفيًا — نقرة واحدة تختار متنًا/حلقة واحدة، و"الكل" افتراضي) كما كانت
        بالضبط، ولوحتا <details> (اختيار متعدّد للمتن، اختيار متعدّد + "بلا
        حلقة" للحلقة) ونطاق النسبة أُبقيت **بجانبها** كعناصر إضافية للاستعمال
        المتقدّم فقط — تمامًا كما تتعايش تبويبات الحلقة البسيطة مع قائمة
        "الحلقة ▾" المتقدّمة في dashboard.blade.php نفسها أصلًا (نفس النمط
        القائم، لا اختراع جديد). كلا الواجهتين (التبويبات والقوائم المنسدلة)
        تقرآن/تكتبان نفس الحالة (`selectedPoemIds`/`circleIds`) فلا تعارض
        بينهما — راجع تعليق PoemBoardController للتفصيل الكامل، لم يتغيّر أي
        منطق فيه، هذا تصحيح واجهة بحت.
    --}}
    <div class="page-title-row">
        <h1 class="mt-0">المتون</h1>
        {{-- استيراد CSV/Excel (طلب صريح من يحيى 2026-09-23: "ضيف زر استيراد
             من ملف excel أو csv في صفحة المتون كمان") — نفس زرّ ونمط الاستيراد
             الموجودين أصلًا في لوحة "قرآن" (dashboard.blade.php، S11/S17)
             حرفيًا: نفس المسار الخلفي (/dashboard/import) ونفس نموذج الأعمدة
             (اسم الطالب، الحلقة) — الاستيراد يضيف طلابًا للمعلّم عمومًا، لا
             شيئًا خاصًّا بالمتون تحديدًا، فإعادة استعمال المسار نفسه صحيح. --}}
        <div class="cluster">
            <button type="button" class="btn btn-secondary" id="importStudentsBtn">استيراد من ملف (CSV أو Excel)</button>
            <input type="file" id="importFileInput"
                   accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                   hidden>
        </div>
    </div>

    @if ($poems->isEmpty())
        <div class="empty-state">
            <p class="empty-emoji">📖</p>
            <p>لا متون مضافة بعد في النظام. تُضاف من لوحة المدير.</p>
        </div>
    @else
        @php
            $hasAnyFilter = $selectedPoemIds || $circleIds || $progressMin !== null || $progressMax !== null;
        @endphp
        <form method="GET" action="{{ route('poems.index') }}" class="search-filter-form">
            <details class="filter-dropdown">
                <summary class="btn btn-sm btn-secondary">
                    المتن @if ($selectedPoemIds) ({{ count($selectedPoemIds) }}) @endif
                </summary>
                <div class="filter-dropdown-panel">
                    @foreach ($poems as $poem)
                        <label>
                            <input type="checkbox" name="poem_id[]" value="{{ $poem->id }}" @checked(in_array($poem->id, $selectedPoemIds, true))>
                            {{ $poem->name }}
                        </label>
                    @endforeach
                </div>
            </details>

            <details class="filter-dropdown">
                <summary class="btn btn-sm btn-secondary">
                    الحلقة @if ($circleIds) ({{ count($circleIds) }}) @endif
                </summary>
                <div class="filter-dropdown-panel">
                    <label>
                        <input type="checkbox" name="circle_id[]" value="none" @checked(in_array('none', $circleIds, true))>
                        بلا حلقة
                    </label>
                    @foreach ($circles as $circle)
                        <label>
                            <input type="checkbox" name="circle_id[]" value="{{ $circle->id }}" @checked(in_array((string) $circle->id, $circleIds, true))>
                            {{ $circle->name }}
                        </label>
                    @endforeach
                </div>
            </details>

            <details class="filter-dropdown">
                <summary class="btn btn-sm btn-secondary">
                    نسبة التقدّم @if ($progressMin !== null || $progressMax !== null) ({{ $progressMin ?? 0 }}٪ - {{ $progressMax ?? 100 }}٪) @endif
                </summary>
                <div class="filter-dropdown-panel">
                    <label>
                        من ٪
                        <input class="input" type="number" min="0" max="100" name="progress_min" value="{{ $progressMin }}">
                    </label>
                    <label>
                        إلى ٪
                        <input class="input" type="number" min="0" max="100" name="progress_max" value="{{ $progressMax }}">
                    </label>
                </div>
            </details>

            <button type="submit" class="btn btn-secondary btn-sm">تطبيق</button>
            @if ($hasAnyFilter)
                <a href="{{ route('poems.index') }}" class="btn btn-ghost btn-sm">إعادة ضبط</a>
            @endif
        </form>

        {{-- تبويبات المتن (أُعيدت 2026-09-23) — نقرة واحدة سريعة لعرض متن
             بعينه أو "الكل" (بلا فلتر)، بنفس آلية تبويبات الحلقة في
             dashboard.blade.php حرفيًا. تتعايش مع لوحة "المتن ▾" المنسدلة
             أعلاه (لاختيار أكثر من متن معًا) — كلتاهما تضبطان نفس المتغيّر
             $selectedPoemIds، فالتبويب النشط يعكس الاختيار الحالي أيًّا كانت
             الواجهة التي غيّرته. --}}
        @php
            $poemTabParams = collect(request()->query())->except(['poem_id', 'page'])->all();
        @endphp
        <div class="circle-tabs">
            <a class="circle-tab {{ ! $selectedPoemIds ? 'active' : '' }}"
               href="{{ route('poems.index') }}?{{ http_build_query($poemTabParams) }}">الكل</a>
            @foreach ($poems as $poem)
                <a class="circle-tab {{ in_array($poem->id, $selectedPoemIds, true) ? 'active' : '' }}"
                   href="{{ route('poems.index') }}?{{ http_build_query($poemTabParams + ['poem_id' => $poem->id]) }}">{{ $poem->name }}</a>
            @endforeach
        </div>

        {{-- تبويبات الحلقة (أُعيدت 2026-09-23) — نفس الفكرة، متداخلة تحت
             تبويبات المتن كما كانت في التصميم الأصلي. "بلا حلقة" تبقى
             متاحة فقط عبر قائمة "الحلقة ▾" المتقدّمة (نفس اتفاقية
             dashboard.blade.php: لا تبويب مخصّص لها هناك أيضًا). --}}
        @php
            $circleTabParams = collect(request()->query())->except(['circle_id', 'page'])->all();
        @endphp
        <div class="circle-tabs">
            <a class="circle-tab {{ ! $circleIds ? 'active' : '' }}"
               href="{{ route('poems.index') }}?{{ http_build_query($circleTabParams) }}">الكل</a>
            @foreach ($circles as $circle)
                <a class="circle-tab {{ in_array((string) $circle->id, $circleIds, true) ? 'active' : '' }}"
                   href="{{ route('poems.index') }}?{{ http_build_query($circleTabParams + ['circle_id' => $circle->id]) }}">{{ $circle->name }}</a>
            @endforeach
        </div>

        <div class="students-table-head poem-columns" aria-hidden="true">
            <span>اسم الطالب</span>
            <span>الحلقة</span>
            <span>المتون والتقدّم</span>
            <span>الحالة</span>
            <span>تسجيل</span>
            <span>السجلّ</span>
        </div>

        <div class="students-table" id="poemStudentsTable">
            @forelse ($students as $entry)
                @php $todayAttendanceStatus = $attendanceTodayByStudent[$entry->student->student_id] ?? null; @endphp
                <div class="student-row poem-columns" data-id="{{ $entry->student->student_id }}">
                    <div class="chip chip-name">
                        <span class="chip-name-text">{{ $entry->student->student_name }}</span>
                    </div>

                    <span class="chip chip-simple">{{ $entry->student->circle->name ?? '—' }}</span>

                    <div class="cluster">
                        @foreach ($entry->poem_entries as $e)
                            <span class="chip chip-simple">{{ $e->poem->name }}: {{ $e->percent }}%</span>
                        @endforeach
                    </div>

                    {{-- زرّ حضور اليوم (طلب صريح من يحيى 2026-09-23: "المفترض أن
                         يكون هناك زر للتحضير في صفحة المتون كما هو موجود في صفحة
                         القرآن") — نفس زرّ/نافذة open-attendance-modal الموجودين
                         حرفيًا في dashboard.blade.php، يقرآن/يكتبان نفس سجلّ
                         Attendance المشترك بين كل الصفحات. فإن سمع الطالب قرآنًا
                         اليوم (درسًا أو مراجعة) من لوحة "قرآن" ستظهر هنا "حاضر"
                         تلقائيًا دون أي تسجيل متن — تمامًا كما طلب يحيى. --}}
                    <button type="button" class="chip chip-attendance open-attendance-modal"
                            title="حضور اليوم"
                            data-status="{{ $todayAttendanceStatus ?? '' }}"
                            data-student-id="{{ $entry->student->student_id }}"
                            data-student-name="{{ $entry->student->student_name }}">
                        {{ $todayAttendanceStatus ? \App\Models\Attendance::STATUSES[$todayAttendanceStatus] : 'لم يُسجَّل بعد' }}
                    </button>

                    <button type="button" class="chip chip-action chip-action-primary open-poem-log-modal"
                            data-student-id="{{ $entry->student->student_id }}"
                            data-student-name="{{ $entry->student->student_name }}">تسجيل سريع</button>

                    {{-- زرّ "السجلّ" (بطلب يحيى 2026-09-23) — نفس الزرّ والوجهة
                         الموجودان أصلًا في لوحة "قرآن" (dashboard.blade.php)
                         حرفيًا: يوصل لصفحة الطالب الكاملة، لا صفحة خاصة
                         بالمتون فقط. --}}
                    <a href="{{ route('students.show', $entry->student->student_id) }}" class="chip chip-action">السجلّ</a>
                </div>
            @empty
                <div class="empty-state">
                    <p class="empty-emoji">🌱</p>
                    <p>
                        @if ($hasAnyFilter)
                            لا طلاب مطابقون لهذه الفلاتر بعد.
                        @else
                            لا طلاب يتتبّعون أي متن بعد.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
    @endif

    {{--
        نافذة تسجيل سريع لسجلّ متن (S24، الجزء الثاني — بطلب صريح من يحيى:
        "أضف زر تسجيل سريع") — نفس أسلوب logModalBackdrop في dashboard.blade.php
        (S23): نافذة واحدة مشتركة للصفحة، تُفتح من زرّ في كل صفّ عبر
        data-student-id. بعد إلغاء تبويب "المتن الواحد" (2026-09-23)، لم يعد
        هناك متن معروف مسبقًا؛ أُضيف حقل اختيار متن هنا (بنفس نمط
        addPoemLogForm في صفحة الطالب: data-bayt-count على كل خيار لضبط حدّ
        حقلي "من/إلى بيت" ديناميكيًا عبر JS).
    --}}
    @if ($poems->isNotEmpty())
        <div class="modal-backdrop" id="poemLogModalBackdrop" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="poemLogModalTitle">
                <div class="modal-head">
                    <h2 id="poemLogModalTitle">تسجيل متن</h2>
                    <button type="button" class="modal-close" id="poemLogModalClose" aria-label="إغلاق"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
                </div>
                <form id="poemLogModalForm">
                    <p class="text-muted" id="poemLogModalSubtitle"></p>
                    <div class="field">
                        <label class="field-label" for="poemLogModalPoemId">المتن</label>
                        <select class="input" id="poemLogModalPoemId" required>
                            <option value="">— اختر —</option>
                            @foreach ($poems as $poem)
                                <option value="{{ $poem->id }}" data-bayt-count="{{ $poem->bayt_count }}">{{ $poem->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="poemLogModalType">النوع</label>
                        <select class="input" id="poemLogModalType" required>
                            @foreach (\App\Models\RecitationLog::TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cluster">
                        <div class="field">
                            <label class="field-label" for="poemLogModalFromBayt">من بيت (اختياري)</label>
                            <input class="input" type="number" min="1" id="poemLogModalFromBayt">
                        </div>
                        <div class="field">
                            <label class="field-label" for="poemLogModalToBayt">إلى بيت</label>
                            <input class="input" type="number" min="1" id="poemLogModalToBayt" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="poemLogModalStatus">حالة الحفظ (اختياري)</label>
                        <select class="input" id="poemLogModalStatus">
                            <option value="">— بدون تحديد —</option>
                            @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="field-error" id="poemLogModalError"></span>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-primary" id="poemLogModalSave">حفظ</button>
                        <button type="button" class="btn btn-ghost" id="poemLogModalCancel">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- نافذة تسجيل الحضور (طلب #2 من يحيى 2026-09-23) — نسخة طبق الأصل من
         attendanceModalBackdrop في dashboard.blade.php: نقر أي حالة يحفظها
         فورًا في نفس سجلّ Attendance المشترك بلا زرّ "حفظ" منفصل. --}}
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
    const { apiFetch, apiFetchQueueable, toast, withButtonLoading } = window.KeshfApp;
    const ATTENDANCE_LABELS = @json(\App\Models\Attendance::STATUSES);

    /* ===== استيراد الطلاب من ملف — نفس منطق dashboard.blade.php حرفيًا
       (طلب صريح من يحيى 2026-09-23) — زرّ الاستيراد يظهر حتى إن لم توجد
       متون بعد (خارج شرط $poems->isEmpty() أدناه)، فهذه الكتلة قبل أي
       return مبكّر يعتمد على وجود متون. ===== */
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

    const table = document.getElementById('poemStudentsTable');
    if (! table) return;

    const poemLogModalBackdrop = document.getElementById('poemLogModalBackdrop');
    if (! poemLogModalBackdrop) return;

    const TODAY = @json(now()->toDateString());

    const poemLogModalSubtitle = document.getElementById('poemLogModalSubtitle');
    const poemLogModalForm = document.getElementById('poemLogModalForm');
    const poemLogModalPoemId = document.getElementById('poemLogModalPoemId');
    const poemLogModalType = document.getElementById('poemLogModalType');
    const poemLogModalFromBayt = document.getElementById('poemLogModalFromBayt');
    const poemLogModalToBayt = document.getElementById('poemLogModalToBayt');
    const poemLogModalStatus = document.getElementById('poemLogModalStatus');
    const poemLogModalError = document.getElementById('poemLogModalError');
    const poemLogModalClose = document.getElementById('poemLogModalClose');
    const poemLogModalCancel = document.getElementById('poemLogModalCancel');

    let poemLogModalStudentId = null;

    // بعد إلغاء تبويب "المتن الواحد": المتن يُختار الآن داخل النافذة نفسها،
    // فيُضبط حدّ حقلي "من/إلى بيت" ديناميكيًا (نفس نمط poemSurahLikeSelect
    // في students/show.blade.php).
    poemLogModalPoemId.addEventListener('change', () => {
        const max = poemLogModalPoemId.selectedOptions[0]?.dataset.baytCount || '';
        poemLogModalToBayt.max = max;
        poemLogModalFromBayt.max = max;
    });

    function openPoemLogModal(btn) {
        poemLogModalStudentId = btn.dataset.studentId;
        poemLogModalSubtitle.textContent = btn.dataset.studentName ?? '';
        poemLogModalError.textContent = '';
        poemLogModalForm.reset();
        poemLogModalToBayt.max = '';
        poemLogModalFromBayt.max = '';
        poemLogModalBackdrop.hidden = false;
        poemLogModalPoemId.focus();
    }

    function closePoemLogModal() {
        poemLogModalBackdrop.hidden = true;
        poemLogModalStudentId = null;
    }

    table.addEventListener('click', (e) => {
        const openBtn = e.target.closest('.open-poem-log-modal');
        if (openBtn) openPoemLogModal(openBtn);
    });

    /**
     * نافذة حضور اليوم (طلب #2 من يحيى 2026-09-23) — نفس منطق dashboard.blade.php
     * حرفيًا: شارة تعرض حالة اليوم، والنقر عليها يفتح نافذة مشتركة، ونقر حالة
     * داخل النافذة يحفظها فورًا عبر نفس مسار POST /attendance بلا زرّ "حفظ"
     * منفصل. سجلّ Attendance مشترك بين كل الصفحات فلا فرق بين حفظه من هنا أو
     * من لوحة "قرآن" — آخر تعديل من أي صفحة هو ما يظهر في البقيّة.
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
        const attendanceBtn = e.target.closest('.open-attendance-modal');
        if (attendanceBtn) openAttendanceModal(attendanceBtn);
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

    poemLogModalClose.addEventListener('click', closePoemLogModal);
    poemLogModalCancel.addEventListener('click', closePoemLogModal);
    poemLogModalBackdrop.addEventListener('click', (e) => {
        if (e.target === poemLogModalBackdrop) closePoemLogModal();
    });

    poemLogModalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (! poemLogModalStudentId || ! poemLogModalPoemId.value) return;

        const payload = {
            poem_id: poemLogModalPoemId.value,
            type: poemLogModalType.value,
            from_bayt: poemLogModalFromBayt.value || null,
            to_bayt: poemLogModalToBayt.value || null,
            status: poemLogModalStatus.value || null,
            logged_at: TODAY,
        };
        Object.keys(payload).forEach((k) => { if (payload[k] === null) delete payload[k]; });

        const saveBtn = document.getElementById('poemLogModalSave');
        poemLogModalError.textContent = '';

        try {
            await withButtonLoading(saveBtn, () => apiFetch(`/dashboard/${poemLogModalStudentId}/poem-logs`, {
                method: 'POST',
                body: payload,
            }));
            toast('تمت إضافة سجلّ المتن بنجاح.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status === 422 && error.errors) {
                poemLogModalError.textContent = Object.values(error.errors)[0][0];
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    });
});
</script>
@endpush
