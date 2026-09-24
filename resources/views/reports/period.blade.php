@extends('layouts.app')

@section('title', 'تقرير فترة - رِواق')

@section('content')
    <div class="page-title-row no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-ghost">→ العودة للوحة التقارير</a>
    </div>

    {{-- فلترة بالحلقة والحالة (S19) — كان تقرير الفترة مقصورًا على نطاق
         تاريخ فقط. نفس أسلوب الاختيار المتعدّد في لوحة الطلاب (S18)، وتُنقَل
         الاختيارات لرابط تنزيل CSV أيضًا حتى لا يختلف المُصدَّر عمّا يظهر
         على الشاشة (نفس مبدأ periodRows() الموحَّد في المتحكّم). --}}
    @php
        $selectedCircleIds = array_map('strval', (array) request('circle_id', []));
        $selectedStatuses = (array) request('status', []);
    @endphp
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
            <a href="{{ route('reports.period.exportXlsx', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'circle_id' => $selectedCircleIds, 'status' => $selectedStatuses]) }}"
               class="btn btn-primary">تنزيل Excel (.xlsx)</a>
            <a href="{{ route('reports.period.export', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'circle_id' => $selectedCircleIds, 'status' => $selectedStatuses]) }}"
               class="btn btn-secondary">تنزيل CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ كـ PDF</button>
        </form>
        <p class="hint">
            "تنزيل Excel" ملف .xlsx حقيقي (S17) بعمودين إضافيين لا يحملهما CSV
            (آخر موضع مراجعة ونسبة المراجعة) — الخيار المفضَّل لفتحه في إكسل مباشرة.
            "تنزيل CSV" يبقى تخطيطه القديم كما هو لمن يعتمد عليه في أداة أخرى.
            "طباعة / حفظ كـ PDF" يستخدم خاصية الطباعة المدمجة في المتصفّح نفسها
            لتحويل هذا التقرير إلى ملف PDF بلا أي خادم توليد وسيط.
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
                        {{-- (S25 — بطلب صريح من يحيى): "متأخر" و"تسميع" حُذفا (كانا
                             صفرًا دائمًا بعد إلغاء كلا النوعين في S22)، و"غائب"
                             انقسم إلى عمودين، وأُضيف عمودا حالة الحفظ. --}}
                        <th>غائب بعذر</th>
                        <th>غائب بدون عذر</th>
                        <th>مستأذن</th>
                        <th>نسبة الحضور</th>
                        <th>حفظ جديد</th>
                        <th>مراجعة</th>
                        <th>حافظ</th>
                        <th>غير حافظ</th>
                        {{-- عمودا نسبة التقدّم وعدد المتون (S19) — لقطة الآن لا
                             "خلال الفترة"، راجع تعليق periodRows() في المتحكّم. --}}
                        <th>نسبة الحفظ %</th>
                        <th>عدد المتون المتتبَّعة</th>
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
                            <td>{{ $row['memorization_count'] }}</td>
                            <td>{{ $row['review_count'] }}</td>
                            <td>{{ $row['memorized_count'] }}</td>
                            <td>{{ $row['not_memorized_count'] }}</td>
                            <td>{{ $row['progress_percent'] }}%</td>
                            <td>{{ $row['tracked_poem_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-muted">لا يوجد طلاب لعرضهم.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
