@extends('layouts.app')

@section('title', 'تقرير المتون - رِواق')

@section('content')
    <div class="page-title-row no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-ghost">→ العودة للوحة التقارير</a>
    </div>

    {{-- تقرير المتون المستقلّ (S37 — بند 1 من خطّة التقارير المعتمَدة): نفس
         فلاتر لوحة "المتون" التفاعلية (poems.index/PoemBoardController) —
         متن/حلقة متعدّدَا الاختيار + نطاق نسبة — بنفس تخطيط عرض/تصدير تقرير
         الفترة (reports/period.blade.php). --}}
    @php
        $selectedPoemIds = array_map('intval', (array) request('poem_id', []));
        $selectedCircleIds = array_map('strval', (array) request('circle_id', []));
        $progressMin = request()->filled('progress_min') ? (float) request('progress_min') : null;
        $progressMax = request()->filled('progress_max') ? (float) request('progress_max') : null;
        $queryParams = array_filter([
            'poem_id' => $selectedPoemIds,
            'circle_id' => $selectedCircleIds,
            'progress_min' => $progressMin,
            'progress_max' => $progressMax,
        ], fn ($v) => $v !== null && $v !== []);
    @endphp
    <div class="card card-pad no-print">
        <form method="GET" action="{{ route('reports.poems') }}" class="report-range-form">
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
                    نسبة الحفظ @if ($progressMin !== null || $progressMax !== null) ({{ $progressMin ?? 0 }}٪ - {{ $progressMax ?? 100 }}٪) @endif
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

            <button type="submit" class="btn btn-secondary">تحديث</button>
            <a href="{{ route('reports.poems.export', $queryParams) }}" class="btn btn-primary">تنزيل CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
        </form>
        <p class="hint">
            لا يظهر أي متن في القائمة قبل اختيار متن واحد على الأقل من فلتر "المتن"، أو
            اختيار "الكل" ضمنيًا بترك الفلتر فارغًا — نفس منطق لوحة "المتون" التفاعلية.
        </p>
    </div>

    <div class="card card-pad print-report" style="margin-top: var(--space-5);">
        <div class="print-header">
            <h1 class="mt-0">تقرير المتون</h1>
        </div>

        <div class="table-scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>الحلقة</th>
                        <th>المتن</th>
                        <th>الأبيات المحفوظة</th>
                        <th>إجمالي الأبيات</th>
                        <th>نسبة الحفظ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->student_name }}</td>
                            <td>{{ $row['student']->circle->name ?? '—' }}</td>
                            <td>{{ $row['poem']->name }}</td>
                            <td>{{ $row['coverage'] }}</td>
                            <td>{{ $row['poem']->bayt_count }}</td>
                            <td>{{ $row['percent'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted">لا صفوف مطابقة لهذه الفلاتر.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
