@extends('layouts.app')

@section('title', $student->student_name.' - سجلّ الطالب - كشف المتابعة')

@php
    $radius = 42;
    $circumference = 2 * pi() * $radius;
    $percent = $student->progressPercentage();
    $offset = $circumference * (1 - min($percent, 100) / 100);
@endphp

@section('content')
    <div class="page-title-row">
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-ghost">→ العودة للوحة</a>
    </div>

    <div class="card card-pad">
        <div class="student-header">
            <div>
                <h1>{{ $student->student_name }}</h1>
                <p class="text-muted">
                    {{ $student->circle->name ?? 'بلا حلقة' }}
                    · <span class="badge badge-{{ $student->status }}">{{ $student->statusLabel() }}</span>
                </p>
            </div>
        </div>

        <div class="progress-summary" style="margin-top: var(--space-5);">
            <div class="progress-ring-wrap">
                <svg viewBox="0 0 100 100" width="96" height="96">
                    <circle class="progress-ring-track" cx="50" cy="50" r="{{ $radius }}"></circle>
                    <circle class="progress-ring-value" cx="50" cy="50" r="{{ $radius }}"
                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"></circle>
                </svg>
                <div class="progress-ring-label">{{ $percent }}%</div>
            </div>
            <div class="progress-facts">
                @if ($student->latestMemorizationLog)
                    <div class="position">آخر موضع: {{ $student->latestMemorizationLog->surah->name ?? '—' }} · آية {{ $student->latestMemorizationLog->to_ayah }}</div>
                @else
                    <div class="position text-muted">لم يبدأ الحفظ بعد</div>
                @endif

                <div class="progress-stats">
                    <span class="progress-stat">
                        <span class="stat-value">{{ $completedSurahs->count() }}</span>
                        <span class="stat-label">من {{ \App\Models\Surah::COUNTABLE_COUNT }} سورة مكتملة</span>
                    </span>
                    @if ($furthestSurah)
                        <span class="progress-stat">
                            <span class="stat-value">{{ $furthestSurah->name }}</span>
                            <span class="stat-label">أبعد سورة في ترتيب الحفظ</span>
                        </span>
                    @endif
                </div>

                <div class="hint">
                    النسبة محسوبة بترتيب الحفظ من الناس صعودًا إلى البقرة: كل سورة خطوة من
                    {{ \App\Models\Surah::COUNTABLE_COUNT }} خطوة، وتتحرّك بمقدار ما حُفظ منها فعلًا.
                    الفاتحة مستثناة من العدّ ولا تُضيف للنسبة وإن سُمِّعت أو روجعت.
                </div>
            </div>
        </div>
    </div>

    <div class="card card-pad chart-card" style="margin-top: var(--space-5);">
        <h2 class="mt-0">منحنى التقدّم</h2>
        @if ($chartPoints->isEmpty())
            <p class="text-muted">لا بيانات كافية لعرض منحنى بعد — أضف أول سجلّ حفظ.</p>
        @else
            <canvas id="progressChart" height="220"></canvas>
        @endif
    </div>

    <div class="card card-pad calendar-card" style="margin-top: var(--space-5);">
        <div class="calendar-nav">
            <a href="?month={{ $monthStart->copy()->subMonth()->format('Y-m') }}" class="btn btn-sm btn-ghost">← الشهر السابق</a>
            <span class="month-label">{{ $monthStart->translatedFormat('F Y') }}</span>
            <a href="?month={{ $monthStart->copy()->addMonth()->format('Y-m') }}" class="btn btn-sm btn-ghost">الشهر التالي →</a>
        </div>
        <div class="calendar-grid">
            @foreach (['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'] as $weekday)
                <div class="calendar-weekday">{{ $weekday }}</div>
            @endforeach
            @php $leadingBlanks = $monthStart->copy()->startOfMonth()->dayOfWeek; @endphp
            @for ($i = 0; $i < $leadingBlanks; $i++)
                <div class="calendar-day is-empty"></div>
            @endfor
            @for ($day = 1; $day <= $monthStart->daysInMonth; $day++)
                @php
                    $dateStr = $monthStart->copy()->day($day)->toDateString();
                    $entry = $attendanceByDate->get($dateStr);
                @endphp
                <div class="calendar-day" @if ($entry) data-status="{{ $entry->status }}" title="{{ $entry->status }}" @endif>
                    <span class="day-num">{{ $day }}</span>
                </div>
            @endfor
        </div>
        <div class="calendar-legend">
            @foreach (\App\Models\Attendance::STATUSES as $value => $label)
                <span><span class="dot {{ $value }}"></span>{{ $label }}</span>
            @endforeach
        </div>
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">إضافة سجلّ جديد</h2>
        <form id="addLogForm">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label" for="log_surah_id">السورة</label>
                    {{-- القائمة بترتيب الحفظ (الناس أولًا) لا بترتيب المصحف:
                         السورة التالية للطالب تقع دائمًا قرب أعلى القائمة بدل
                         أن تكون بعد تمرير مئة سورة. الفاتحة في آخرها مع تنويه
                         أنها لا تُحتسب، فلا تُختار بالخطأ ظنًّا أنها تُقدّم النسبة. --}}
                    <select class="input" id="log_surah_id" name="surah_id" required>
                        <option value="">— اختر —</option>
                        @foreach ($surahs as $surah)
                            <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}">
                                {{ $surah->name }}@if ($surah->excluded_from_progress) (لا تُحتسب في النسبة)@endif
                            </option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="surah_id"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="log_type">النوع</label>
                    <select class="input" id="log_type" name="type" required>
                        @foreach (\App\Models\RecitationLog::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="type"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="log_from_ayah">من آية (اختياري)</label>
                    <input class="input" type="number" min="1" id="log_from_ayah" name="from_ayah">
                    <span class="field-error" data-for="from_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="log_to_ayah">إلى آية</label>
                    <input class="input" type="number" min="1" id="log_to_ayah" name="to_ayah" required>
                    <span class="field-error" data-for="to_ayah"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="log_grade">التقييم (اختياري)</label>
                    <select class="input" id="log_grade" name="grade">
                        <option value="">—</option>
                        @foreach (\App\Models\RecitationLog::GRADES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-for="grade"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="log_logged_at">التاريخ</label>
                    <input class="input" type="date" id="log_logged_at" name="logged_at"
                           value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    <span class="field-error" data-for="logged_at"></span>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="log_notes">ملاحظات (اختياري)</label>
                <textarea class="input" id="log_notes" name="notes" rows="2" maxlength="1000"></textarea>
                <span class="field-error" data-for="notes"></span>
            </div>
            <button type="submit" class="btn btn-primary" id="addLogBtn">إضافة السجلّ</button>
        </form>
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">الخط الزمني</h2>
        <div class="timeline" id="timelineList">
            @forelse ($logs as $log)
                <div class="timeline-item" data-type="{{ $log->type }}">
                    <div class="timeline-row">
                        <span class="timeline-title">
                            {{ $log->surah->name ?? '—' }}
                            @if ($log->from_ayah)
                                — آية {{ $log->from_ayah }} إلى {{ $log->to_ayah }}
                            @else
                                — حتى آية {{ $log->to_ayah }}
                            @endif
                        </span>
                        <span class="timeline-date">{{ $log->logged_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="timeline-meta">
                        <span class="type-chip type-{{ $log->type }}">{{ $log->typeLabel() }}</span>
                        @if ($log->grade)
                            <span class="grade-chip">{{ \App\Models\RecitationLog::GRADES[$log->grade] ?? $log->grade }}</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-ghost delete-log" data-id="{{ $log->id }}">حذف</button>
                    </div>
                    @if ($log->notes)
                        <p class="timeline-notes">{{ $log->notes }}</p>
                    @endif
                </div>
            @empty
                <p class="text-muted" id="timelineEmpty">لا سجلّات بعد.</p>
            @endforelse
        </div>
        {{ $logs->links('vendor.pagination.custom') }}
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/student-timeline.js'])
    <script>
        window.KeshfChartData = @json($chartPoints);
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const { apiFetch, apiFetchQueueable, toast, confirmDialog, withButtonLoading, applyFieldErrors } = window.KeshfApp;
        const studentId = {{ $student->student_id }};

        const form = document.getElementById('addLogForm');
        const surahSelect = document.getElementById('log_surah_id');
        const toAyahInput = document.getElementById('log_to_ayah');
        const fromAyahInput = document.getElementById('log_from_ayah');

        surahSelect.addEventListener('change', () => {
            const max = surahSelect.selectedOptions[0]?.dataset.ayahCount || '';
            toAyahInput.max = max;
            fromAyahInput.max = max;
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(form).entries());
            ['from_ayah', 'grade', 'notes'].forEach((k) => { if (!payload[k]) delete payload[k]; });

            const btn = document.getElementById('addLogBtn');
            try {
                // Queueable (S12): بلا اتصال يُحفَظ السجلّ محليًا ويُرسَل تلقائيًا
                // عند عودة الشبكة بدل فشل الإضافة بالكامل — لا تحديث للصفحة في
                // هذه الحالة لأن السجلّ لم يظهر في القاعدة بعد فعليًا.
                const res = await withButtonLoading(btn, () => apiFetchQueueable(`/dashboard/${studentId}/logs`, { method: 'POST', body: payload }, 'سجلّ تسميع/حفظ'));
                if (res.queued) {
                    form.reset();
                } else {
                    toast('تمت إضافة السجلّ بنجاح.', 'success');
                    window.location.reload();
                }
            } catch (error) {
                if (error.status === 422 && error.errors) {
                    applyFieldErrors(form, error.errors);
                } else if (error.status !== 419) {
                    toast(error.message, 'error');
                }
            }
        });

        document.getElementById('timelineList').addEventListener('click', async (e) => {
            const btn = e.target.closest('.delete-log');
            if (!btn) return;
            const confirmed = await confirmDialog('حذف هذا السجلّ؟ لا يمكن التراجع.');
            if (!confirmed) return;
            try {
                await apiFetch(`/dashboard/${studentId}/logs/${btn.dataset.id}`, { method: 'DELETE' });
                toast('تم حذف السجلّ.', 'success');
                window.location.reload();
            } catch (error) {
                if (error.status !== 419) toast(error.message, 'error');
            }
        });
    });
    </script>
@endpush
