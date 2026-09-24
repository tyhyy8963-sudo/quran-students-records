@extends('layouts.app')

@section('title', 'سجلّ الحضور والغياب - رِواق')

@section('content')
    {{--
        عرض فقط (تصحيح S23) — كانت هذه الشاشة موضع تجربة موسَّعة لتسجيل الدرس
        والمراجعة أيضًا بناءً على فهم خاطئ لمكان "الصفحة الرئيسية"، صُحِّح
        صراحةً من يحيى: "تبويب الحضور والغياب هو فقط لعرض سجلات الحضور
        والغياب". لا أزرار تسجيل ولا نماذج هنا — التسجيل الفعلي صار من اللوحة
        الرئيسية (/dashboard) عبر نافذة تسجيل الحضور هناك، وهذه الشاشة تعرض
        نتيجة يوم واحد فقط.
    --}}
    @php
        $baseParams = collect(request()->query())->except(['circle_id'])->all();
    @endphp

    <div class="page-title-row">
        <h1 class="mt-0">سجلّ الحضور والغياب</h1>
    </div>

    <div class="attendance-toolbar">
        <div class="field">
            <label class="field-label" for="attendanceDate">اليوم</label>
            <input class="input" type="date" id="attendanceDate" value="{{ $date }}" max="{{ now()->toDateString() }}">
        </div>
    </div>

    <div class="circle-tabs">
        <a class="circle-tab {{ ! $circleId ? 'active' : '' }}"
           href="{{ url('/attendance') }}?{{ http_build_query($baseParams) }}">الكل</a>
        @foreach ($circles as $circle)
            <a class="circle-tab {{ (string) $circleId === (string) $circle->id ? 'active' : '' }}"
               href="{{ url('/attendance') }}?{{ http_build_query($baseParams + ['circle_id' => $circle->id]) }}">{{ $circle->name }}</a>
        @endforeach
    </div>

    <div class="students-table" id="attendanceRecordsList">
        @forelse ($students as $student)
            @php $attendance = $student->attendances->first(); @endphp
            <div class="student-row attendance-record-row">
                <div>
                    <span class="column-label">اسم الطالب</span>
                    <span class="position-display">{{ $student->student_name }}</span>
                </div>
                <div>
                    <span class="column-label">الحلقة</span>
                    <span class="position-display">{{ $student->circle->name ?? 'بلا حلقة' }}</span>
                </div>
                <div>
                    <span class="column-label">حالة الحضور</span>
                    {{-- (S30 — استكمال نمط هرماس لبقيّة الصفحات، بطلب يحيى "أبدأ
                         فيها كلها"): كانت هذه الشارة `.status-pill` بألوان قديمة
                         مختلفة عن كبسولة `.chip-attendance` (الألوان المقيسة من
                         مخطّط هرماس) المستعملة لنفس البيانات بالضبط في لوحتَي
                         "قرآن"/"المتون" — نفس حالة الحضور المشتركة، شكلان
                         مختلفان. صارت الآن نفس الكبسولة حرفيًا (بلا زرّ/نافذة،
                         الصفحة تبقى للعرض فقط كما تقرّر سابقًا). --}}
                    @if ($attendance)
                        <span class="chip chip-attendance" data-status="{{ $attendance->status }}">{{ $attendance->status }}</span>
                    @else
                        <span class="text-muted">لم يُسجَّل بعد</span>
                    @endif
                </div>
                <div>
                    <span class="column-label">ملاحظة</span>
                    <span class="text-muted">{{ $attendance->notes ?? '—' }}</span>
                </div>
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
    const dateInput = document.getElementById('attendanceDate');

    dateInput.addEventListener('change', () => {
        const params = new URLSearchParams(window.location.search);
        params.set('date', dateInput.value);
        window.location.search = params.toString();
    });
});
</script>
@endpush
