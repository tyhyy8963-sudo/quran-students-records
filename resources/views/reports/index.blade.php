@extends('layouts.app')

@section('title', 'لوحة التقارير - كشف المتابعة')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">لوحة التقارير</h1>
        <a href="{{ route('reports.period') }}" class="btn btn-primary">تقرير فترة + تصدير</a>
    </div>

    <div class="stat-cards">
        <div class="card card-pad stat-card">
            <div class="stat-label">إجمالي الطلاب</div>
            <div class="stat-value">{{ collect($statusCounts)->sum('count') }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">عدد الحلقات</div>
            <div class="stat-value">{{ $circlesCount }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">متوسّط نسبة التقدّم (الطلاب النشطون)</div>
            <div class="stat-value">{{ $avgProgress !== null ? $avgProgress.'%' : '—' }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">نسبة الحضور (آخر 30 يومًا)</div>
            <div class="stat-value">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</div>
            @if ($attendanceRate === null)
                <div class="hint">لا سجلّات حضور خلال هذه الفترة بعد.</div>
            @endif
        </div>
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">الطلاب حسب الحالة</h2>
        <div class="status-breakdown">
            @foreach ($statusCounts as $value => $row)
                <span class="badge badge-{{ $value }}">{{ $row['label'] }}: {{ $row['count'] }}</span>
            @endforeach
        </div>
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">طلاب بحاجة إلى متابعة</h2>
        <p class="hint">آخر سطرَي حضور مسجَّلين لهم كلاهما «غائب» — نفس مؤشّر الانقطاع الظاهر في لوحة الطلاب.</p>
        @if ($studentsNeedingAttention->isEmpty())
            <p class="text-muted">لا يوجد طلاب بحاجة إلى متابعة حاليًا.</p>
        @else
            <ul class="attention-list">
                @foreach ($studentsNeedingAttention as $student)
                    <li>
                        <a href="{{ route('students.show', $student->student_id) }}">{{ $student->student_name }}</a>
                        <span class="text-muted">{{ $student->circle->name ?? 'بلا حلقة' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
