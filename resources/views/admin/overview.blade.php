@extends('layouts.app')

@section('title', 'نظرة عامة على المنظومة - رِواق')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">نظرة عامة على المنظومة</h1>
        {{-- تصدير/طباعة جدول مقارنة المعلّمين (S37 — بند 4 من خطّة التقارير
             المعتمَدة) — راجع AdminOverviewController::exportCsv(). --}}
        <div class="cluster no-print">
            <a href="{{ route('admin.overview.export') }}" class="btn btn-secondary">تنزيل CSV (المعلّمون)</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
            <a href="{{ route('admin.teachers.index') }}" class="btn btn-secondary">إدارة حسابات المعلّمين</a>
        </div>
    </div>

    {{-- إحصاءات إجمالية على مستوى كل المعلّمين معًا — لا معلّم بعينه (S20). --}}
    <div class="stat-cards">
        <div class="card card-pad stat-card">
            <div class="stat-label">المعلّمون</div>
            <div class="stat-value">{{ $teachers->count() }}</div>
            <div class="hint">{{ $teachers->where('is_active', true)->count() }} نشط</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">إجمالي الطلاب</div>
            <div class="stat-value">{{ collect($statusCounts)->sum('count') }}</div>
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
                <div class="hint">لا سجلّات حضور بعد.</div>
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

    {{-- مقارنة المعلّمين ببعضهم — "لم يسجّل مؤخّرًا" تنبيه متابعة لا عقاب،
         يعني فقط أن آخر سجلّ حفظ/مراجعة/تسميع لطلابه أقدم من أسبوعين. --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">المعلّمون</h2>
        @if ($teacherStats->isEmpty())
            <div class="empty-state">
                <div class="empty-emoji">👥</div>
                <p>لا حسابات معلّمين بعد.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>المعلّم</th>
                            <th>الطلاب</th>
                            <th>متوسّط التقدّم</th>
                            <th>نسبة الحضور (30 يومًا)</th>
                            <th>آخر نشاط</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teacherStats as $row)
                            <tr>
                                <td>
                                    {{ $row['teacher']->name }}
                                    @if (! $row['teacher']->is_active)
                                        <span class="badge badge-inactive">معطَّل</span>
                                    @endif
                                </td>
                                <td>{{ $row['students_count'] }}</td>
                                <td>{{ $row['avg_progress'] !== null ? $row['avg_progress'].'%' : '—' }}</td>
                                <td>{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—' }}</td>
                                <td>
                                    @if ($row['last_activity'])
                                        {{ $row['last_activity']->diffForHumans() }}
                                    @else
                                        <span class="text-muted">لا نشاط بعد</span>
                                    @endif
                                    @if ($row['inactive_recently'] && $row['students_count'] > 0)
                                        <span class="status-pill" title="بلا سجلّ حفظ/مراجعة/تسميع منذ أكثر من أسبوعين">لم يسجّل مؤخّرًا</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.teachers.report', $row['teacher']) }}" class="btn btn-sm btn-secondary">عرض السجلّ</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- مقارنة الحلقات بصرف النظر عن معلّمها — حلقة معلّم أ تقارَن بحلقة
         معلّم ب مباشرة، لا داخل نطاق كل معلّم على حدة فقط. --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">أداء الحلقات</h2>
        @if ($circleStats->isEmpty())
            <p class="text-muted">لا حلقات بعد.</p>
        @else
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>الحلقة</th>
                            <th>المعلّم</th>
                            <th>عدد الطلاب</th>
                            <th>متوسّط التقدّم (النشطون)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($circleStats as $row)
                            <tr>
                                <td>{{ $row['circle']->name }}</td>
                                <td>{{ $row['circle']->teacher->name ?? '—' }}</td>
                                <td>{{ $row['students_count'] }}</td>
                                <td>{{ $row['avg_progress'] !== null ? $row['avg_progress'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- أعلى عشرة طلاب تقدّمًا على مستوى المنظومة كلّها. --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">الأعلى تقدّمًا على مستوى المنظومة</h2>
        @if ($topStudents->isEmpty())
            <p class="text-muted">لا طلاب نشطون بعد.</p>
        @else
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>الحلقة</th>
                            <th>نسبة التقدّم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topStudents as $student)
                            <tr>
                                <td>{{ $student->student_name }}</td>
                                <td>{{ $student->circle->name ?? 'بلا حلقة' }}</td>
                                <td>{{ $student->progressPercentage() }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
