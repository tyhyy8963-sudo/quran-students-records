@extends('layouts.app')

@section('title', 'سجلّ '.$teacher->name.' - رِواق')

@section('content')
    <div class="page-title-row">
        <a href="{{ route('admin.overview') }}" class="btn btn-sm btn-ghost no-print">→ العودة للنظرة العامة</a>
        {{-- تصدير/طباعة جدول طلاب هذا المعلّم (S37 — بند 4 من خطّة التقارير
             المعتمَدة) — راجع AdminOverviewController::exportTeacherCsv(). --}}
        <div class="cluster no-print">
            <a href="{{ route('admin.teachers.report.export', $teacher) }}" class="btn btn-secondary">تنزيل CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
        </div>
    </div>

    {{-- بطاقة تعريف المعلّم — قراءة فقط، لا رابط تعديل هنا (ذلك من شاشة
         حسابات المعلّمين نفسها لا من هذه الشاشة القرائية). --}}
    <div class="card card-pad">
        <div class="student-header">
            <div>
                <h1>{{ $teacher->name }}</h1>
                <p class="text-muted">
                    <code class="username-chip">{{ $teacher->username }}</code>
                    @if ($teacher->mosque || $teacher->classroom)
                        · {{ $teacher->mosque }}@if ($teacher->mosque && $teacher->classroom) — @endif{{ $teacher->classroom }}
                    @endif
                    · <span class="badge {{ $teacher->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $teacher->is_active ? 'نشط' : 'معطَّل' }}</span>
                </p>
                <p class="text-muted">
                    آخر دخول: {{ $teacher->last_login_at ? $teacher->last_login_at->diffForHumans() : 'لم يدخل بعد' }}
                </p>
            </div>
            <a href="{{ route('admin.teachers.index') }}" class="btn btn-sm btn-secondary">إدارة الحساب</a>
        </div>
    </div>

    <div class="stat-cards" style="margin-top: var(--space-4);">
        <div class="card card-pad stat-card">
            <div class="stat-label">إجمالي الطلاب</div>
            <div class="stat-value">{{ collect($statusCounts)->sum('count') }}</div>
        </div>
        <div class="card card-pad stat-card">
            <div class="stat-label">عدد الحلقات</div>
            <div class="stat-value">{{ $circles->count() }}</div>
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

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">طلاب بحاجة إلى متابعة</h2>
        <p class="hint">آخر سطرَي حضور مسجَّلين لهم كلاهما «غائب».</p>
        @if ($studentsNeedingAttention->isEmpty())
            <p class="text-muted">لا يوجد طلاب بحاجة إلى متابعة حاليًا.</p>
        @else
            <ul class="attention-list">
                @foreach ($studentsNeedingAttention as $student)
                    <li>
                        <strong>{{ $student->student_name }}</strong>
                        <span class="text-muted">{{ $student->circle->name ?? 'بلا حلقة' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- الحلقات — عرض فقط، بلا نموذج إضافة/حذف (تلك أدوات المعلّم نفسه). --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">الحلقات</h2>
        @if ($circles->isEmpty())
            <p class="text-muted">لا حلقات بعد.</p>
        @else
            <div class="cluster">
                @foreach ($circles as $circle)
                    <span class="circle-chip">{{ $circle->name }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- جدول الطلاب — نفس أعمدة لوحة المعلّم الرئيسية (S18) لكن بلا أي عنصر
         تفاعلي (لا تعديل اسم، لا تغيير حلقة/حالة، لا رابط لصفحة سجلّ فردية —
         تلك خلف middleware('teacher') ولن يصلها حساب مدير أصلًا). --}}
    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">الطلاب</h2>
        @if ($students->isEmpty())
            <div class="empty-state">
                <div class="empty-emoji">👥</div>
                <p>لا طلاب بعد لهذا المعلّم.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>الحلقة</th>
                            <th>آخر موضع حفظ</th>
                            <th>آخر موضع مراجعة</th>
                            <th>الحالة</th>
                            <th>حضور اليوم</th>
                            <th>المتون</th>
                            <th>نسبة التقدّم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td>{{ $student->student_name }}</td>
                                <td>{{ $student->circle->name ?? '—' }}</td>
                                <td>
                                    @if ($student->latestMemorizationLog)
                                        {{ $student->latestMemorizationLog->surah->name ?? '—' }} · آية {{ $student->latestMemorizationLog->to_ayah }}
                                    @else
                                        <span class="text-muted">لم يبدأ بعد</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($student->latestReviewLog)
                                        {{ $student->latestReviewLog->surah->name ?? '—' }} · آية {{ $student->latestReviewLog->to_ayah }}
                                    @else
                                        <span class="text-muted">لا مراجعة مسجَّلة</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $student->status }}">{{ $student->statusLabel() }}</span></td>
                                <td>
                                    @php $todayStatus = $attendanceTodayByStudent[$student->student_id] ?? null; @endphp
                                    {{-- (S30 — نفس تصحيح صفحة "التحضير"): كبسولة
                                         .chip-attendance بألوان هرماس المقيسة
                                         بدل .status-pill القديمة، لنفس بيانات
                                         الحضور المعروضة بشكلها الحديث في بقيّة
                                         الصفحات. --}}
                                    @if ($todayStatus)
                                        <span class="chip chip-attendance" data-status="{{ $todayStatus }}">{{ $todayStatus }}</span>
                                    @else
                                        <span class="text-muted">لم يُسجَّل</span>
                                    @endif
                                </td>
                                <td>{{ $trackedPoemCounts[$student->student_id] ?? 0 }}</td>
                                <td>{{ $student->progressPercentage() }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
