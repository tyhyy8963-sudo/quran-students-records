@extends('layouts.app')

@section('title', 'الحضور - كشف المتابعة')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">التحضير السريع</h1>
    </div>

    <div class="attendance-toolbar">
        <div class="field">
            <label class="field-label" for="attendanceDate">اليوم</label>
            <input class="input" type="date" id="attendanceDate" value="{{ $date }}" max="{{ now()->toDateString() }}">
        </div>
        <div class="field">
            <label class="field-label" for="attendanceCircle">الحلقة</label>
            <select class="input" id="attendanceCircle">
                <option value="">كل الحلقات</option>
                @foreach ($circles as $circle)
                    <option value="{{ $circle->id }}" @selected((string) $circleId === (string) $circle->id)>{{ $circle->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="attendance-list" id="attendanceList">
        @forelse ($students as $student)
            @php $today = $student->attendances->first(); @endphp
            <div class="attendance-row" data-student-id="{{ $student->student_id }}">
                <div class="who">
                    <span>{{ $student->student_name }}</span>
                    @if ($student->circle)
                        <span class="circle-name">— {{ $student->circle->name }}</span>
                    @endif
                </div>
                <div class="attendance-status-group">
                    @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                        <button type="button"
                                class="status-btn {{ optional($today)->status === $value ? 'selected' : '' }}"
                                data-status="{{ $value }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <input type="text" class="input note-input" placeholder="ملاحظة (اختياري)"
                       aria-label="ملاحظة حضور {{ $student->student_name }}" maxlength="500" value="{{ optional($today)->notes }}">
            </div>
        @empty
            <div class="empty-state">
                <p class="empty-emoji">🌱</p>
                <p>لا يوجد طلاب لعرضهم — أضِف طلابًا من لوحة الطلاب أولًا.</p>
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetchQueueable, toast } = window.KeshfApp;

    const dateInput = document.getElementById('attendanceDate');
    const circleSelect = document.getElementById('attendanceCircle');
    const list = document.getElementById('attendanceList');

    function reloadWithParams() {
        const params = new URLSearchParams();
        params.set('date', dateInput.value);
        if (circleSelect.value) params.set('circle_id', circleSelect.value);
        window.location.search = params.toString();
    }

    dateInput.addEventListener('change', reloadWithParams);
    circleSelect.addEventListener('change', reloadWithParams);

    async function saveEntry(row, status) {
        const studentId = Number(row.dataset.studentId);
        const notes = row.querySelector('.note-input').value.trim();
        const studentName = row.querySelector('.who span')?.textContent ?? 'حضور';

        try {
            // Queueable: لا اتصال الآن يعني حفظًا محليًا مؤقتًا لا فشلًا (S12) —
            // يُعاد إرساله تلقائيًا فور عودة الشبكة (انظر flushOfflineQueue في app.js).
            const res = await apiFetchQueueable('/attendance', {
                method: 'POST',
                body: {
                    date: dateInput.value,
                    entries: [{ student_id: studentId, status, notes: notes || null }],
                },
            }, `حضور ${studentName}`);
            row.querySelectorAll('.status-btn').forEach((b) => b.classList.toggle('selected', b.dataset.status === status));
            if (!res.queued) toast('تم حفظ الحضور.', 'success');
        } catch (error) {
            if (error.status === 422 && error.errors) {
                toast(Object.values(error.errors)[0][0], 'error');
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    }

    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.status-btn');
        if (!btn) return;
        const row = btn.closest('.attendance-row');
        saveEntry(row, btn.dataset.status);
    });

    list.querySelectorAll('.note-input').forEach((input) => {
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const row = input.closest('.attendance-row');
                const selected = row.querySelector('.status-btn.selected');
                if (selected) saveEntry(row, selected.dataset.status);
            }, 700);
        });
    });
});
</script>
@endpush
