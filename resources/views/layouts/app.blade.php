<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#606c38">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'كشف المتابعة')</title>
    {{-- تطبيق الوضع الليلي المحفوظ قبل الرسم — سكربت متزامن عادي (لا وحدة
         Vite المؤجَّلة) حتى لا تظهر الصفحة بالوضع النهاري لحظة ثم تنقلب
         للّيلي بعد تحميل app.js (وميض واضح لمن يستخدم الوضع الليلي دائمًا). --}}
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('keshf-theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    <header class="app-header">
        <div class="app-header-inner">
            {{-- وجهة الشعار تتبع الدور: لوحة الطلاب لا وجود لها في حساب المدير
                 (طلابه صفر دائمًا بحكم عزل TeacherScope). --}}
            <a href="{{ auth()->user()?->isAdmin() ? route('admin.teachers.index') : route('dashboard') }}" class="brand">
                <span class="brand-mark">ك</span>
                <span>كشف المتابعة</span>
            </a>

            @auth
                <nav class="app-nav">
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.teachers.index') }}" class="{{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}">حسابات المعلّمين</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">الطلاب</a>
                        <a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}">الحضور</a>
                        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">التقارير</a>
                    @endif
                </nav>
            @endauth

            @auth
                <a href="{{ route('profile.show') }}" class="teacher-summary">
                    <span>
                        {{-- لا بادئة لاسم المدير: اسمه المعروض يحمل صفته عادةً
                             ("مدير المنظومة")، فإضافة "مدير النظام" قبله تعطي
                             تكرارًا ركيكًا في أعلى كل صفحة. --}}
                        <span class="name">{{ auth()->user()->isAdmin() ? '' : 'الأستاذ ' }}{{ auth()->user()->name }}</span><br>
                        <span class="meta">
                            @if (auth()->user()->isAdmin())
                                إدارة الحسابات
                            @else
                                {{ auth()->user()->mosque }} — {{ auth()->user()->classroom }}
                            @endif
                        </span>
                    </span>
                    @isset($studentsCount)
                        <span class="count-pill" title="عدد الطلاب">{{ $studentsCount }}</span>
                    @endisset
                </a>
            @endauth

            <button type="button" class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي" title="الوضع الليلي">🌙</button>
        </div>
    </header>

    <main class="container" style="padding-block: var(--space-6);">
        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </main>

    @include('components.toast-host')
    @include('components.confirm-bar')

    @stack('scripts')
</body>
</html>
