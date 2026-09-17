@extends('layouts.guest')

@section('title', 'كشف المتابعة')

@section('content')
    <div class="hero">
        <p class="emoji">📖</p>
        <h1>مرحبًا بك في كشف المتابعة</h1>
        <p>حيث كل خطوة في حفظ طلابك للقرآن تُسجَّل وتُتابَع بسهولة، من مكان واحد.</p>

        <div class="actions">
            <a href="{{ route('login') }}" class="btn btn-primary">تسجيل الدخول</a>
        </div>

        <p class="text-muted" style="margin-top: var(--space-5); font-size: 0.95rem;">
            نظام داخلي — تُنشأ الحسابات من إدارة المنظومة وتُسلَّم للمعلّم مباشرة.
        </p>
    </div>
@endsection
