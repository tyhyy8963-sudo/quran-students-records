@extends('layouts.app')

@section('title', $student->student_name.' - تقرير الطالب - رِواق')

@section('content')
    <div class="page-title-row no-print">
        <a href="{{ route('students.show', $student->student_id) }}" class="btn btn-sm btn-ghost">→ العودة لصفحة الطالب</a>
    </div>

    {{-- تصدير/طباعة سجلّ طالب واحد كاملًا (S37 — بند 2 من خطّة التقارير
         المعتمَدة): كل السجلّات دفعة واحدة بلا ترقيم صفحات، بنفس تخطيط
         عرض/طباعة تقرير الفترة (reports/period.blade.php). --}}
    <div class="card card-pad no-print">
        <a href="{{ route('students.report.export', $student->student_id) }}" class="btn btn-primary">تنزيل CSV</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
    </div>

    <div class="card card-pad print-report" style="margin-top: var(--space-5);">
        <div class="print-header">
            <h1 class="mt-0">تقرير الطالب: {{ $student->student_name }}</h1>
            <p class="text-muted">{{ $student->circle->name ?? 'بلا حلقة' }}</p>
        </div>

        <div class="stat-cards">
            <div class="card card-pad stat-card">
                <div class="stat-label">نسبة الحفظ</div>
                <div class="stat-value">{{ $percent }}%</div>
            </div>
            <div class="card card-pad stat-card">
                <div class="stat-label">نسبة المراجعة</div>
                <div class="stat-value">{{ $reviewPercent }}%</div>
            </div>
            <div class="card card-pad stat-card">
                <div class="stat-label">أيام الحضور</div>
                <div class="stat-value">{{ $attendanceDaysCount }}</div>
            </div>
            <div class="card card-pad stat-card">
                <div class="stat-label">إجمالي المراجعات</div>
                <div class="stat-value">{{ $totalReviewsCount }}</div>
            </div>
        </div>

        @if ($poemsData->isNotEmpty())
            <h2>المتون المتتبَّعة</h2>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr><th>المتن</th><th>نسبة الحفظ</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($poemsData as $entry)
                            <tr>
                                <td>{{ $entry->poem->name }}</td>
                                <td>{{ $entry->percent }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h2>سجلّ الحفظ والمراجعة</h2>
        <div class="table-scroll">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>المدى</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ optional($log->logged_at)->toDateString() }}</td>
                            <td>{{ $log->typeLabel() }}</td>
                            <td>
                                {{ $log->surah->name ?? '—' }}
                                @if ($log->from_ayah)
                                    من آية {{ $log->from_ayah }}
                                @endif
                                حتى
                                @if ($log->spansMultipleSurahs())
                                    {{ $log->toSurah->name ?? '—' }} ·
                                @endif
                                آية {{ $log->to_ayah }}
                            </td>
                            <td>{{ $log->statusLabel() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted">لا سجلّات بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2>سجلّ الحضور</h2>
        <div class="table-scroll">
            <table class="report-table">
                <thead>
                    <tr><th>التاريخ</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @forelse ($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->date->toDateString() }}</td>
                            <td>{{ $attendance->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-muted">لا سجلّات حضور بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
