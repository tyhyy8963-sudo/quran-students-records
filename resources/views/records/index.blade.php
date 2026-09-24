@extends('layouts.app')

@section('title', 'السجلات - رِواق')

@section('content')
    {{--
        تبويب "السجلات" (S26) — أُعيدت كتابته بالكامل (2026-09-23) بعد أن وضّح
        يحيى أن المحاولة الأولى (قائمة زمنية موحَّدة لكل الأنواع) كانت فهمًا
        خاطئًا. المقصود الفعلي **دليل بسيط بكل الطلاب**: اسم + رابط "سجلّ"
        يفتح صفحة الطالب الكاملة (نفس students.show، المرجع الذي أرسل صورته)،
        مع إمكانية إضافة طالب جديد من نفس الصفحة — راجع تعليق RecordsController
        للتفصيل الكامل. الفلترة (بحث + حلقة) لم تتغيّر، كانت مؤكَّدة أصلًا.
    --}}
    <div class="page-title-row">
        <h1 class="mt-0">السجلات</h1>
        <button type="button" class="btn btn-primary" id="addStudentBtn">+ إضافة طالب</button>
    </div>

    <form method="GET" action="{{ route('records.index') }}" class="search-filter-form">
        <label class="sr-only" for="recordsSearch">ابحث باسم الطالب</label>
        <input class="input" type="search" id="recordsSearch" name="q" value="{{ $search }}" placeholder="ابحث باسم الطالب...">

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

        <button type="submit" class="btn btn-secondary btn-sm">تطبيق</button>
        @if ($search !== '' || $circleIds)
            <a href="{{ route('records.index') }}" class="btn btn-ghost btn-sm">إعادة ضبط</a>
        @endif
    </form>

    <div class="card card-pad" style="margin-top: var(--space-4);">
        <div class="student-directory" id="studentsDirectory">
            @forelse ($students as $student)
                <div class="student-directory-row" data-id="{{ $student->student_id }}">
                    <div>
                        <span class="column-label">اسم الطالب</span>
                        <span class="account-name">{{ $student->student_name }}</span>
                    </div>
                    <div>
                        <span class="column-label">الحلقة</span>
                        <span class="chip chip-simple">{{ $student->circle->name ?? 'بلا حلقة' }}</span>
                    </div>
                    <div class="student-directory-actions">
                        <a href="{{ route('students.show', $student->student_id) }}" class="btn btn-sm btn-secondary">السجلّ</a>
                    </div>
                </div>
            @empty
                <div class="empty-state" id="emptyState">
                    <p class="empty-emoji">🗒️</p>
                    <p>
                        @if ($search !== '' || $circleIds)
                            لا طلاب مطابقين لهذه الفلاتر.
                        @else
                            لا طلاب بعد. ابدأ بإضافة أول طالب.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
        {{ $students->links('vendor.pagination.custom') }}
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetch, toast, withButtonLoading } = window.KeshfApp;

    const directory = document.getElementById('studentsDirectory');
    const addBtn = document.getElementById('addStudentBtn');
    const CIRCLES = @json($circles->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]));

    // نفس دالّة التحصين ضد الحقن عبر innerHTML الموجودة أصلًا في dashboard.blade.php
    // (أسماء الحلقات نصّ حرّ بلا قيد على الأحرف).
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function circleSelectMarkup() {
        const options = CIRCLES.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        return `<option value="">بلا حلقة</option>${options}`;
    }

    function buildRow() {
        const row = document.createElement('div');
        row.className = 'student-directory-row is-new';
        row.innerHTML = `
            <div><span class="column-label">اسم الطالب</span><input class="input name" placeholder="اسم الطالب" aria-label="اسم الطالب"></div>
            <div><span class="column-label">الحلقة</span><select class="input circle" aria-label="حلقة الطالب الجديد">${circleSelectMarkup()}</select></div>
            <div class="student-directory-actions">
                <button class="btn btn-sm btn-primary save">حفظ</button>
                <button class="btn btn-sm btn-ghost cancel-new">إلغاء</button>
            </div>
        `;
        return row;
    }

    function bindNewRow(row) {
        row.querySelector('.save')?.addEventListener('click', () => saveNewRow(row));
        row.querySelector('.cancel-new')?.addEventListener('click', () => row.remove());
    }

    addBtn.addEventListener('click', () => {
        if (directory.querySelector('.student-directory-row.is-new')) {
            toast('أكمل بيانات الطالب الحالي قبل إضافة آخر.', 'warning');
            return;
        }
        document.getElementById('emptyState')?.remove();
        const row = buildRow();
        directory.prepend(row);
        bindNewRow(row);
        row.querySelector('.name').focus();
    });

    async function saveNewRow(row) {
        const payload = {
            student_name: row.querySelector('.name').value.trim(),
            circle_id: row.querySelector('.circle').value || null,
        };

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
                row.querySelectorAll('.input').forEach((el) => el.classList.remove('has-error'));
                const map = { student_name: '.name', circle_id: '.circle' };
                Object.keys(error.errors).forEach((field) => {
                    row.querySelector(map[field])?.classList.add('has-error');
                });
                toast(Object.values(error.errors)[0][0], 'error');
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    }
});
</script>
@endpush
