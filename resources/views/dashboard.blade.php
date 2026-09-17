@extends('layouts.app')

@section('title', 'لوحة متابعة الطلاب - كشف المتابعة')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">لوحة متابعة الطلاب</h1>
        <div class="cluster">
            <button type="button" class="btn btn-secondary" id="importStudentsBtn">استيراد من ملف</button>
            <input type="file" id="importFileInput" accept=".csv,text/csv" hidden>
            <button type="button" class="btn btn-primary" id="addStudentBtn">+ إضافة طالب</button>
        </div>
    </div>

    {{-- الحلقات (S6) --}}
    <div class="circles-panel" id="circlesPanel">
        <strong>الحلقات:</strong>
        <div class="cluster" id="circleChips">
            @forelse ($circles as $circle)
                <span class="circle-chip" data-id="{{ $circle->id }}">
                    {{ $circle->name }}
                    <button type="button" class="delete-circle" title="حذف الحلقة" aria-label="حذف الحلقة {{ $circle->name }}">×</button>
                </span>
            @empty
                <span class="text-muted">لا حلقات بعد.</span>
            @endforelse
        </div>
        <form class="add-circle" id="addCircleForm">
            <label class="sr-only" for="newCircleName">اسم حلقة جديدة</label>
            <input class="input" id="newCircleName" placeholder="اسم حلقة جديدة" maxlength="255">
            <button type="submit" class="btn btn-sm btn-secondary">+ إضافة حلقة</button>
        </form>
    </div>

    {{-- بحث وفلترة (S11) --}}
    <form method="GET" action="{{ route('dashboard') }}" class="search-filter-form">
        <label class="sr-only" for="dashboardSearch">ابحث باسم الطالب</label>
        <input class="input" type="search" id="dashboardSearch" name="q" value="{{ request('q') }}" placeholder="ابحث باسم الطالب">

        <label class="sr-only" for="dashboardCircleFilter">فلترة حسب الحلقة</label>
        <select class="input" id="dashboardCircleFilter" name="circle_id" onchange="this.form.submit()">
            <option value="">كل الحلقات</option>
            <option value="none" @selected(request('circle_id') === 'none')>بلا حلقة</option>
            @foreach ($circles as $circle)
                <option value="{{ $circle->id }}" @selected((string) request('circle_id') === (string) $circle->id)>{{ $circle->name }}</option>
            @endforeach
        </select>

        <label class="sr-only" for="dashboardStatusFilter">فلترة حسب الحالة</label>
        <select class="input" id="dashboardStatusFilter" name="status" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach (\App\Models\Student::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">بحث</button>
        @if (request('q') || request('circle_id') || request('status'))
            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">إعادة ضبط</a>
        @endif
    </form>

    {{-- رأس أعمدة قابل للفرز (S11) — يظهر على الشاشات الواسعة فقط، فبطاقة
         الهاتف تعرض تسمية كل حقل بجانبه أصلًا عبر column-label. --}}
    @php
        $nextDir = fn ($field) => ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
        $isActive = fn ($field) => $sort === $field || (! $sort && $field === 'name');
    @endphp
    <div class="students-table-head">
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => $nextDir('name')]) }}"
           class="col-sort {{ $isActive('name') ? 'active '.$dir : '' }}">اسم الطالب</a>
        <span>الحلقة</span>
        <span>آخر موضع</span>
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'dir' => $nextDir('status')]) }}"
           class="col-sort {{ $isActive('status') ? 'active '.$dir : '' }}">الحالة</a>
        <span></span>
    </div>

    <div class="students-table" id="studentsTable">
        @forelse ($students as $student)
            <div class="student-row" data-id="{{ $student->student_id }}">
                <div>
                    <span class="column-label">اسم الطالب</span>
                    <input class="input name" value="{{ $student->student_name }}" placeholder="اسم الطالب" aria-label="اسم الطالب">
                    @if ($attendanceAlerts[$student->student_id] ?? false)
                        <span class="attendance-alert" title="غياب في آخر جلستَين مسجَّلتين">انقطاع</span>
                    @endif
                </div>
                <div>
                    <span class="column-label">الحلقة</span>
                    {{-- column-label مخفي بصريًا على الشاشة الواسعة (display:none)، فلا
                         يُقرأ اسمًا للعنصر عبر قارئ الشاشة هناك — aria-label يضمن اسمًا
                         واضحًا دائمًا بصرف النظر عن حجم الشاشة. --}}
                    <select class="input circle" aria-label="حلقة {{ $student->student_name }}">
                        <option value="">بلا حلقة</option>
                        @foreach ($circles as $circle)
                            <option value="{{ $circle->id }}" @selected($student->circle_id === $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <span class="column-label">آخر موضع</span>
                    <div class="position-display">
                        @if ($student->latestMemorizationLog)
                            {{ $student->latestMemorizationLog->surah->name ?? '—' }} · آية {{ $student->latestMemorizationLog->to_ayah }}
                        @else
                            <span class="text-muted">لم يبدأ بعد</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="column-label">الحالة</span>
                    <select class="input status student-status" aria-label="حالة {{ $student->student_name }}">
                        @foreach (\App\Models\Student::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($student->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row-actions">
                    <a href="{{ route('students.show', $student->student_id) }}" class="btn btn-sm btn-ghost">السجلّ</a>
                    <button class="btn btn-sm btn-danger delete">حذف</button>
                </div>
            </div>
        @empty
            <div class="empty-state" id="emptyState">
                <p class="empty-emoji">🌱</p>
                <p>
                    @if (request('q') || request('circle_id') || request('status'))
                        لا نتائج مطابقة لهذا البحث/الفلتر.
                    @else
                        لا يوجد طلاب بعد. ابدأ بإضافة أول طالب في حلقتك.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{ $students->links('vendor.pagination.custom') }}
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetch, toast, confirmDialog, withButtonLoading } = window.KeshfApp;

    const table = document.getElementById('studentsTable');
    const addBtn = document.getElementById('addStudentBtn');

    const STATUS_OPTIONS = @json(\App\Models\Student::STATUSES);
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

    function statusSelectMarkup(selected) {
        return Object.entries(STATUS_OPTIONS).map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }

    function circleSelectMarkup() {
        const options = [...document.querySelectorAll('#circleChips .circle-chip')].map((chip) =>
            `<option value="${chip.dataset.id}">${chip.textContent.trim().replace(/×$/, '').trim()}</option>`
        ).join('');
        return `<option value="">بلا حلقة</option>${options}`;
    }

    function surahSelectMarkup() {
        const options = SURAHS.map((s) => `<option value="${s.id}" data-ayah-count="${s.ayah_count}">${s.name}</option>`).join('');
        return `<option value="">— اختر سورة (اختياري) —</option>${options}`;
    }

    function buildRow() {
        const row = document.createElement('div');
        row.className = 'student-row is-new editing';
        row.innerHTML = `
            <div><span class="column-label">اسم الطالب</span><input class="input name" placeholder="اسم الطالب" aria-label="اسم الطالب"></div>
            <div><span class="column-label">الحلقة</span><select class="input circle" aria-label="حلقة الطالب الجديد">${circleSelectMarkup()}</select></div>
            <div>
                <span class="column-label">نقطة البداية (اختياري)</span>
                <div class="cluster" style="gap: .5rem; flex-wrap: nowrap;">
                    <select class="input surah" style="flex: 1;" aria-label="سورة البداية (اختياري)">${surahSelectMarkup()}</select>
                    <input class="input ayah" type="number" min="1" placeholder="الآية" style="width: 90px;" aria-label="رقم آية البداية">
                </div>
            </div>
            <div><span class="column-label">الحالة</span><select class="input status student-status" aria-label="حالة الطالب الجديد" disabled>${statusSelectMarkup('active')}</select></div>
            <div class="row-actions">
                <button class="btn btn-sm btn-primary save">حفظ</button>
                <button class="btn btn-sm btn-ghost cancel-new">إلغاء</button>
            </div>
        `;
        return row;
    }

    addBtn.addEventListener('click', () => {
        if (table.querySelector('.student-row.is-new')) {
            toast('أكمل بيانات الطالب الحالي قبل إضافة آخر.', 'warning');
            return;
        }
        document.getElementById('emptyState')?.remove();
        const row = buildRow();
        table.prepend(row);
        bindNewRow(row);
        row.querySelector('.name').focus();
    });

    function rowPayload(row) {
        const payload = {
            student_name: row.querySelector('.name').value.trim(),
            circle_id: row.querySelector('.circle').value || null,
            status: row.querySelector('.status')?.value,
        };
        const surahSelect = row.querySelector('.surah');
        const ayahInput = row.querySelector('.ayah');
        if (surahSelect && surahSelect.value) {
            payload.surah_id = surahSelect.value;
            payload.the_ayah = ayahInput.value ? Number(ayahInput.value) : null;
        }
        return payload;
    }

    function highlightErrors(row, errors) {
        const map = { student_name: '.name', circle_id: '.circle', surah_id: '.surah', the_ayah: '.ayah', status: '.status' };
        row.querySelectorAll('.input').forEach((el) => el.classList.remove('has-error'));
        Object.keys(errors).forEach((field) => {
            row.querySelector(map[field])?.classList.add('has-error');
        });
    }

    async function saveNewRow(row) {
        const payload = rowPayload(row);

        if (!payload.student_name) {
            toast('اسم الطالب مطلوب.', 'error');
            row.querySelector('.name').classList.add('has-error');
            return;
        }

        const saveBtn = row.querySelector('.save');

        try {
            await withButtonLoading(saveBtn, () => apiFetch('/dashboard/create', { method: 'POST', body: payload }));
            toast('تمت إضافة الطالب بنجاح.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status === 422 && error.errors) {
                highlightErrors(row, error.errors);
                toast(Object.values(error.errors)[0][0], 'error');
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    }

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

    /**
     * حفظ تلقائي + تراجع (S11) — لا زر "تعديل"/"حفظ" بعد الآن لصفوف
     * الطلاب الحاليين: كل حقل يُحفظ فور تغييره (نص الاسم بعد توقّف الكتابة
     * قليلًا، والقوائم المنسدلة فورًا)، وتوست النجاح يحمل زر "تراجع" يعيد
     * القيمة السابقة ويحفظها مجددًا — بلا شاشة تأكيد لكل حرف يكتبه المعلّم.
     */
    function bindExistingRow(row) {
        const id = row.dataset.id;
        const nameInput = row.querySelector('.name');
        const circleSelect = row.querySelector('.circle');
        const statusSelect = row.querySelector('.status');

        let saved = {
            student_name: nameInput.value,
            circle_id: circleSelect.value,
            status: statusSelect.value,
        };

        function currentValues() {
            return {
                student_name: nameInput.value.trim(),
                circle_id: circleSelect.value,
                status: statusSelect.value,
            };
        }

        function applyValues(values) {
            nameInput.value = values.student_name;
            circleSelect.value = values.circle_id;
            statusSelect.value = values.status;
        }

        async function persist(values, { withUndo } = { withUndo: true }) {
            if (!values.student_name) {
                toast('اسم الطالب مطلوب.', 'error');
                applyValues(saved);
                return;
            }

            const previous = saved;
            row.classList.add('saving');
            try {
                const res = await apiFetch(`/dashboard/${id}`, {
                    method: 'PATCH',
                    body: {
                        student_name: values.student_name,
                        circle_id: values.circle_id || null,
                        status: values.status,
                    },
                });
                row.classList.remove('has-error');
                saved = values;
                toast(res.message ?? 'تم الحفظ تلقائيًا.', 'success', withUndo ? {
                    action: {
                        label: 'تراجع',
                        onClick: () => { applyValues(previous); persist(previous, { withUndo: false }); },
                    },
                } : {});
            } catch (error) {
                applyValues(saved);
                if (error.status === 422 && error.errors) {
                    row.querySelectorAll('.input').forEach((el) => el.classList.remove('has-error'));
                    Object.keys(error.errors).forEach((field) => {
                        const map = { student_name: '.name', circle_id: '.circle', status: '.status' };
                        row.querySelector(map[field])?.classList.add('has-error');
                    });
                    toast(Object.values(error.errors)[0][0], 'error');
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            } finally {
                row.classList.remove('saving');
            }
        }

        let nameTimer;
        const scheduleNameSave = () => {
            clearTimeout(nameTimer);
            nameTimer = setTimeout(() => {
                const values = currentValues();
                if (values.student_name !== saved.student_name) persist(values);
            }, 700);
        };
        nameInput.addEventListener('input', scheduleNameSave);
        nameInput.addEventListener('blur', () => {
            clearTimeout(nameTimer);
            const values = currentValues();
            if (values.student_name !== saved.student_name) persist(values);
        });

        circleSelect.addEventListener('change', () => {
            const values = currentValues();
            if (values.circle_id !== saved.circle_id) persist(values);
        });
        statusSelect.addEventListener('change', () => {
            const values = currentValues();
            if (values.status !== saved.status) persist(values);
        });

        row.querySelector('.delete')?.addEventListener('click', () => deleteRow(row));
    }

    function bindNewRow(row) {
        row.querySelector('.save')?.addEventListener('click', () => saveNewRow(row));
        row.querySelector('.cancel-new')?.addEventListener('click', () => row.remove());
        const surahSelect = row.querySelector('.surah');
        const ayahInput = row.querySelector('.ayah');
        surahSelect?.addEventListener('change', () => {
            const opt = surahSelect.selectedOptions[0];
            ayahInput.max = opt?.dataset.ayahCount || '';
        });
    }

    table.querySelectorAll('.student-row').forEach(bindExistingRow);

    /* ===== استيراد الطلاب من ملف (S11) ===== */
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
            importBtn.textContent = 'استيراد من ملف';
            importInput.value = '';
        }
    });

    /* ===== الحلقات ===== */
    const addCircleForm = document.getElementById('addCircleForm');
    addCircleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('newCircleName');
        const name = input.value.trim();
        if (!name) return;

        try {
            await apiFetch('/circles', { method: 'POST', body: { name } });
            toast('تمت إضافة الحلقة.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status !== 419) toast(error.message, 'error');
        }
    });

    document.getElementById('circleChips').addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-circle');
        if (!btn) return;
        const chip = btn.closest('.circle-chip');
        const confirmed = await confirmDialog('حذف هذه الحلقة؟ طلابها يبقون بلا حذف.');
        if (!confirmed) return;

        try {
            await apiFetch(`/circles/${chip.dataset.id}`, { method: 'DELETE' });
            toast('تم حذف الحلقة.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status !== 419) toast(error.message, 'error');
        }
    });
});
</script>
@endpush
