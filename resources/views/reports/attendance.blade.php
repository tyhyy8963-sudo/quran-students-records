@extends('layouts.app')

@section('title', 'تقرير الحضور والغياب - رِواق')

@section('content')
    <div class="page-title-row no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-ghost">→ العودة للوحة التقارير</a>
    </div>

    {{-- تقرير الحضور والغياب المستقلّ (S37 — بند 3 من خطّة التقارير
         المعتمَدة): امتداد لمؤشّر "طلاب بحاجة إلى متابعة" الصغير في لوحة
         التقارير الرئيسية إلى تقرير كامل بنطاق تاريخ + فلترة حلقة/حالة، بنفس
         تخطيط تقرير الفترة (reports/period.blade.php) حرفيًا. --}}
    @php
        $selectedCircleIds = array_map('strval', (array) request('circle_id', []));
        $selectedStatuses = (array) request('status', []);
    @endphp
    <div class="card card-pad no-print">
        <form method="GET" action="{{ route('reports.attendance') }}" class="report-range-form">
            <div class="field">
                <label class="field-label" for="attendanceFromDate">من</label>
                <input class="input" type="date" id="attendanceFromDate" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div class="field">
                <label class="field-label" for="attendanceToDate">إلى</label>
                <input class="input" type="date" id="attendanceToDate" name="to" value="{{ $to->toDateString() }}">
            </div>

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

            <button type="submit" class="btn btn-secondary">تحديث</button>
            <a href="{{ route('reports.attendance.export', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'circle_id' => $selectedCircleIds, 'status' => $selectedStatuses]) }}"
               class="btn btn-primary">تنزيل CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
        </form>
    </div>

    <div class="card card-pad print-report" style="margin-top: var(--space-5);">
        <div class="print-header">
            <h1 class="mt-0">تقرير الحضور والغياب</h1>
            <p class="text-muted">من {{ $from->toDateString() }} إلى {{ $to->toDateString() }}</p>
        </div>

        <div class="table-scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>الحلقة</th>
                        <th>حاضر</th>
                        <th>غائب بعذر</th>
                        <th>غائب بدون عذر</th>
                        <th>مستأذن</th>
                        <th>نسبة الحضور</th>
                        <th>بحاجة إلى متابعة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->student_name }}</td>
                            <td>{{ $row['student']->circle->name ?? '—' }}</td>
                            <td>{{ $row['present'] }}</td>
                            <td>{{ $row['excused_absent'] }}</td>
                            <td>{{ $row['unexcused_absent'] }}</td>
                            <td>{{ $row['excused'] }}</td>
                            <td>{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—' }}</td>
                            <td>
                                @if ($row['needs_attention'])
                                    <span class="status-pill" title="آخر سطرَي حضور مسجَّلين له كلاهما «غائب»">نعم</span>
                                @else
                                    <span class="text-muted">لا</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted">لا يوجد طلاب لعرضهم.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
