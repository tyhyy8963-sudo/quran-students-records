@extends('layouts.app')

@section('title', 'لوحة التقارير - رِواق')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">لوحة التقارير</h1>
    </div>

    {{-- إعادة هيكلة التبويب إلى مركز تقارير (S37 — بند 5 من خطّة التقارير
         المعتمَدة، آخر بنودها بعد بناء التقارير الثلاثة الجديدة): كانت هذه
         الصفحة إحصاءات عامة + زرّ واحد لتقرير الفترة فقط. الآن أعلاها روابط
         واضحة لكل التقارير المستقلّة (فترة، متون، حضور وغياب)، والإحصاءات/
         "طلاب بحاجة إلى متابعة" أدناه تبقى "نظرة عامة" سريعة في نفس الصفحة —
         لا حاجة لصفحة فرعية خاصة بها، فهي مختصرة أصلًا ولا تصدير لها بذاتها. --}}
    <div class="report-links-grid" style="margin-top: var(--space-4);">
        <a href="{{ route('reports.period') }}" class="card card-pad report-link-card">
            <h2 class="mt-0">تقرير الفترة</h2>
            <p class="text-muted">حضور وسجلّ زمني لكل طالب بين تاريخين — تصدير Excel/CSV وطباعة.</p>
        </a>
        <a href="{{ route('reports.poems') }}" class="card card-pad report-link-card">
            <h2 class="mt-0">تقرير المتون</h2>
            <p class="text-muted">صفّ لكل (طالب، متن) بنسبة حفظه — فلترة بالمتن/الحلقة/نسبة التقدّم.</p>
        </a>
        <a href="{{ route('reports.attendance') }}" class="card card-pad report-link-card">
            <h2 class="mt-0">تقرير الحضور والغياب</h2>
            <p class="text-muted">نسب حضور تفصيلية لكل طالب بين تاريخين، مع تنبيه "بحاجة إلى متابعة".</p>
        </a>
    </div>

    <h2 style="margin-top: var(--space-6);">نظرة عامة</h2>

    {{-- فلترة بالحلقة والحالة (S19) — كانت هذه اللوحة إحصاءات ثابتة بلا أي
         فلاتر إطلاقًا؛ نفس أسلوب الاختيار المتعدّد في لوحة الطلاب (S18). --}}
    @php
        $selectedCircleIds = array_map('strval', (array) request('circle_id', []));
        $selectedStatuses = (array) request('status', []);
        $hasAnyFilter = $selectedCircleIds || $selectedStatuses;
    @endphp
    <form method="GET" action="{{ route('reports.index') }}" class="search-filter-form">
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

        <button type="submit" class="btn btn-secondary btn-sm">تطبيق</button>
        @if ($hasAnyFilter)
            <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">إعادة ضبط</a>
        @endif
    </form>

    <div class="stat-cards" style="margin-top: var(--space-4);">
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
            @if ($avgProgress !== null && $previousAvgProgress !== null)
                @php $progressTrendDiff = round($avgProgress - $previousAvgProgress, 1); @endphp
                <div class="stat-trend {{ $progressTrendDiff > 0 ? 'stat-trend--up' : ($progressTrendDiff < 0 ? 'stat-trend--down' : 'stat-trend--flat') }}">
                    {{ $progressTrendDiff > 0 ? '▲' : ($progressTrendDiff < 0 ? '▼' : '—') }}
                    {{ $progressTrendDiff > 0 ? '+' : '' }}{{ $progressTrendDiff }}٪ عن قبل 30 يومًا
                </div>
            @endif
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">نسبة الحضور (آخر 30 يومًا)</div>
            <div class="stat-value">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</div>
            @if ($attendanceRate === null)
                <div class="hint">لا سجلّات حضور خلال هذه الفترة بعد.</div>
            @elseif ($previousAttendanceRate !== null)
                @php $attendanceTrendDiff = round($attendanceRate - $previousAttendanceRate, 1); @endphp
                <div class="stat-trend {{ $attendanceTrendDiff > 0 ? 'stat-trend--up' : ($attendanceTrendDiff < 0 ? 'stat-trend--down' : 'stat-trend--flat') }}">
                    {{ $attendanceTrendDiff > 0 ? '▲' : ($attendanceTrendDiff < 0 ? '▼' : '—') }}
                    {{ $attendanceTrendDiff > 0 ? '+' : '' }}{{ $attendanceTrendDiff }}٪ عن الـ30 يومًا السابقة
                </div>
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
