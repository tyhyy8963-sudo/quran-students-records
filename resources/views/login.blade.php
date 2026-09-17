@extends('layouts.guest')

@section('title', 'تسجيل الدخول - كشف المتابعة')

@section('content')
    <div class="card card-pad auth-card">
        <h1>تسجيل الدخول</h1>
        <p class="subtitle">ادخل باسم المستخدم وكلمة المرور اللذين سلّمهما لك مدير النظام.</p>

        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label class="field-label" for="username">اسم المستخدم</label>
                <input class="input @error('username') has-error @enderror" type="text" id="username" name="username"
                       value="{{ old('username') }}" placeholder="username" required autofocus
                       dir="ltr" style="text-align: left;" autocomplete="username">
                @error('username')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="field">
                <label class="field-label" for="password">كلمة المرور</label>
                <input class="input" type="password" id="password" name="password" placeholder="••••••••" required
                       autocomplete="current-password">
                @error('password')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block">تسجيل الدخول</button>
        </form>

        {{-- لا رابط "أنشئ حسابك" ولا "نسيت كلمة المرور": النظام مغلق، والحسابات
             تُنشأ وتُستعاد من لوحة المدير وحدها. عرض ما لا وجود له يترك المعلّم
             ينتظر رسالة لن تصل. --}}
        <p class="auth-note">
            نسيت كلمة المرور أو تعذّر الدخول؟ راجع مدير النظام ليعيد تعيينها لك.
        </p>
    </div>
@endsection
