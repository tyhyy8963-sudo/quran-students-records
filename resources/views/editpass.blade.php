@extends('layouts.app')

@section('title', 'تغيير كلمة المرور - كشف المتابعة')

@section('content')
    <div class="card card-pad auth-card" style="margin-inline: auto;">
        <div class="cluster" style="justify-content: space-between;">
            <h1 class="mt-0">تغيير كلمة المرور</h1>
            <a href="{{ route('admin.teachers.index') }}" class="btn btn-ghost btn-icon" aria-label="رجوع إلى لوحة الحسابات">✕</a>
        </div>

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" id="passwordForm">
            @csrf

            {{-- كلمة المرور الحالية تُرسل مع نفس الطلب الذي يحفظ الجديدة (C-01) --}}
            <div class="field">
                <label class="field-label" for="current_password">كلمة المرور الحالية</label>
                <input class="input" type="password" id="current_password" name="current_password"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <div class="field">
                <label class="field-label" for="new_password">كلمة المرور الجديدة</label>
                <input class="input" type="password" id="new_password" name="new_password"
                       placeholder="6 رموز على الأقل" required minlength="6" autocomplete="new-password">
            </div>

            <div class="field">
                <label class="field-label" for="new_password_confirmation">تأكيد كلمة المرور الجديدة</label>
                <input class="input" type="password" id="new_password_confirmation" name="new_password_confirmation"
                       placeholder="أعد كتابة كلمة المرور الجديدة" required minlength="6" autocomplete="new-password">
            </div>

            {{-- لا رابط "نسيت كلمة المرور": لا استعادة ذاتية في المنظومة المغلقة.
                 مدير فقد كلمته يستعيدها من الخادم بأمر keshf:reset-password. --}}
            <p class="field-hint" style="margin-bottom: var(--space-4);">
                هذه الصفحة لحساب المدير وحده. كلمة مرور المعلّم تُعاد من شاشة «حسابات المعلّمين».
            </p>

            <button type="submit" class="btn btn-primary btn-block">حفظ</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // تحقّق مبدئي في الواجهة فقط — التحقق الفعلي يجري على الخادم دائمًا.
    const form = document.getElementById('passwordForm');
    const { toast } = window.KeshfApp;

    form.addEventListener('submit', (event) => {
        const current = form.current_password.value.trim();
        const next = form.new_password.value.trim();
        const confirm = form.new_password_confirmation.value.trim();

        const fail = (msg) => { event.preventDefault(); toast(msg, 'error'); };

        if (!current || !next || !confirm) return fail('يرجى تعبئة جميع الحقول.');
        if (next.length < 6) return fail('كلمة المرور يجب أن تكون 6 رموز أو أكثر.');
        if (next !== confirm) return fail('كلمة المرور وتأكيدها غير متطابقين.');
        if (next === current) return fail('كلمة المرور الجديدة يجب أن تختلف عن الحالية.');
    });
});
</script>
@endpush
