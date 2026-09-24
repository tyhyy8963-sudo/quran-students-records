@extends('layouts.app')

@section('title', 'تعديل الملف الشخصي - رِواق')

@section('content')
    <div class="card card-pad auth-card" style="margin-inline: auto;">
        <h1>تعديل الملف الشخصي</h1>

        <form action="{{ route('profile.update') }}" method="POST" id="profileEditForm">
            @csrf
            @method('PATCH')

            <div class="field">
                <label class="field-label" for="name">{{ $user->isAdmin() ? 'المدير' : 'الأستاذ' }}</label>
                <input class="input @error('name') has-error @enderror" type="text" id="name" name="name" value="{{ old('name', $user->name) }}">
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="field-label" for="mosque">الجامع</label>
                <input class="input" type="text" id="mosque" name="mosque" value="{{ old('mosque', $user->mosque) }}">
            </div>

            <div class="field">
                <label class="field-label" for="classroom">الحلقة</label>
                <input class="input" type="text" id="classroom" name="classroom" value="{{ old('classroom', $user->classroom) }}">
            </div>

            {{-- اسم المستخدم غير قابل للتعديل هنا: هو معرّف الدخول الذي يحفظه
                 صاحبه، وتغييره من حساب المعلّم نفسه يعني فقدان المدير للمعرّف
                 الذي سلّمه إياه. --}}
            <div class="field">
                <label class="field-label" for="username_display">اسم المستخدم</label>
                <input class="input" type="text" id="username_display" value="{{ $user->username }}" dir="ltr"
                       style="text-align: left;" readonly aria-describedby="usernameLocked">
                <span class="field-hint" id="usernameLocked">
                    {{ $user->isAdmin() ? 'لا يُعدَّل من الواجهة.' : 'لا يُعدَّل — راجع مدير النظام عند الحاجة.' }}
                </span>
            </div>

            @if ($user->isAdmin())
                <p>
                    <a href="{{ route('edit.password') }}">هل ترغب بتغيير كلمة المرور؟</a>
                </p>
            @endif

            <div class="cluster" style="justify-content: flex-end;">
                <a href="{{ route('profile.show') }}" class="btn btn-secondary">خروج</a>
                <button class="btn btn-primary" type="submit" id="saveBtn">حفظ</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { confirmDialog } = window.KeshfApp;
    const form = document.getElementById('profileEditForm');

    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        if (await confirmDialog('هل أنت متأكد من الحفظ؟')) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    });
});
</script>
@endpush
