<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#606c38">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'رِواق')</title>
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

    <div class="decorative-backdrop" aria-hidden="true"></div>

    <button type="button" class="theme-toggle guest-theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي" title="الوضع الليلي">🌙</button>

    <div class="center-screen">
        @yield('content')
    </div>

    @include('components.toast-host')
    @include('components.confirm-bar')

    @stack('scripts')
</body>
</html>
