@extends('layouts.app')

@section('title', 'تقرير فترة - كشف المتابعة')

@section('content')
    <div class="page-title-row no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-ghost">→ العودة للوحة التقارير</a>
    </div>

    <div class="card card-pad no-print">
        <form method="GET" action="{{ route('reports.period') }}" class="report-range-form">
            <div class="field">
                <label class="field-label" for="fromDate">من</label>
                <input class="input" type="date" id="fromDate" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div class="field">
                <label class="field-label" for="toDate">إلى</label>
                <input class="input" type="date" id="toDate" name="to" value="{{ $to->toDateString() }}">
            </div>
            <button type="submit" class="btn btn-secondary">تحديث</button>
            <a href="{{ route('reports.period.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               class="btn btn-secondary">تنزيل CSV (إكسل)</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
        </form>
        <p class="hint">
            لا يوجد خادم توليد PDF/إكسل حقيقي في هذا الإصدار — "تنزيل CSV" ملف حقيقي
            يُفتح مباشرة في إكسل، و"طباعة / حفظ كـ PDF" يستخدم خاصية الطباعة المدمجة
            في المتصفّح نفسها لتحويل هذا التقرير إلى ملف PDF بلا وسيط.
        </p>
    </div>

    <div class="card card-pad print-report" style="margin-top: var(--space-5);">
        <div class="print-header">
            <h1 class="mt-0">تقرير الفترة</h1>
            <p class="text-muted">من {{ $from->toDateString() }} إلى {{ $to->toDateString() }}</p>
        </div>

        <div class="table-scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>الحلقة</th>
                        <th>حاضر</th>
                        <th>غائب</th>
                        <th>متأخر</th>
                        <th>مستأذن</th>
                        <th>نسبة الحضور</th>
                        <th>حفظ جديد</th>
                        <th>مراجعة</th>
                        <th>تسميع</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->student_name }}</td>
                            <td>{{ $row['student']->circle->name ?? '—' }}</td>
                            <td>{{ $row['present'] }}</td>
                            <td>{{ $row['absent'] }}</td>
                            <td>{{ $row['late'] }}</td>
                            <td>{{ $row['excused'] }}</td>
                            <td>{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—' }}</td>
                            <td>{{ $row['memorization_count'] }}</td>
                            <td>{{ $row['review_count'] }}</td>
                            <td>{{ $row['recitation_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-muted">لا يوجد طلاب لعرضهم.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
