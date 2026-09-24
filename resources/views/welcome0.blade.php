@extends('layouts.guest')

@section('title', 'رِواق')

@section('content')
    <div class="hero">
        <img src="{{ asset('images/logo-full-light.png') }}" alt="رِواق" class="auth-logo is-light" style="max-width: 260px;">
        <img src="{{ asset('images/logo-full-dark.png') }}" alt="رِواق" class="auth-logo is-dark" style="max-width: 260px;">
        <h1>مرحبًا بك في رِواق</h1>
        <p>حيث كل خطوة في حفظ طلابك للقرآن تُسجَّل وتُتابَع بسهولة، من مكان واحد.</p>

        <div class="actions">
            <a href="{{ route('login') }}" class="btn btn-primary">تسجيل الدخول</a>
        </div>

        <p class="text-muted" style="margin-top: var(--space-5); font-size: 0.95rem;">
            نظام داخلي — تُنشأ الحسابات من إدارة المنظومة وتُسلَّم للمعلّم مباشرة.
        </p>
    </div>
@endsection
