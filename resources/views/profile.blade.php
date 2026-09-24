@extends('layouts.app')

@section('title', 'الملف الشخصي - رِواق')

@section('content')
    <div class="card card-pad auth-card" style="margin-inline: auto;">
        <div class="cluster" style="justify-content: space-between;">
            <h1 class="mt-0">الملف الشخصي</h1>
            <a href="{{ $user->isAdmin() ? route('admin.teachers.index') : route('dashboard') }}" class="btn btn-ghost btn-icon" aria-label="رجوع"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></a>
        </div>

        <div class="profile-row">
            <span class="label">{{ $user->isAdmin() ? 'المدير' : 'الأستاذ' }}</span>
            <span class="value">{{ $user->name }}</span>
        </div>
        <div class="profile-row">
            <span class="label">اسم المستخدم</span>
            <span class="value" dir="ltr">{{ $user->username }}</span>
        </div>
        <div class="profile-row">
            <span class="label">الصلاحية</span>
            <span class="value">{{ $user->roleLabel() }}</span>
        </div>
        <div class="profile-row">
            <span class="label">الجامع</span>
            <span class="value">{{ $user->mosque ?: '—' }}</span>
        </div>
        <div class="profile-row">
            <span class="label">الحلقة</span>
            <span class="value">{{ $user->classroom ?: '—' }}</span>
        </div>

        {{-- كلمة المرور: المدير يغيّر كلمته بنفسه، والمعلّم يراجع المدير.
             لا زرّ معطَّل ولا رسالة خطأ بعد الضغط — الشاشة تقول ما هو ممكن فقط. --}}
        <div class="profile-row">
            <span class="label">كلمة المرور</span>
            <span class="value">
                @if ($user->isAdmin())
                    <a href="{{ route('edit.password') }}" style="color: var(--color-primary-600); text-decoration: underline;">تغيير كلمة المرور</a>
                @else
                    <span class="text-muted">لتغييرها راجع مدير النظام</span>
                @endif
            </span>
        </div>

        <div class="cluster" style="margin-top: var(--space-6);">
            <a href="{{ route('profile.edit') }}" class="btn btn-primary">تعديل البيانات</a>

            <form method="POST" action="{{ route('logout') }}" id="logoutForm" style="display:contents;">
                @csrf
                <button class="btn btn-danger" type="button" id="logoutBtn">تسجيل الخروج</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { confirmDialog } = window.KeshfApp;
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutForm = document.getElementById('logoutForm');

    logoutBtn.addEventListener('click', async () => {
        if (await confirmDialog('هل أنت متأكد من تسجيل الخروج؟')) {
            logoutForm.submit();
        }
    });
});
</script>
@endpush
