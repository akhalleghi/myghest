<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" data-theme="light" data-admin-font="{{ $appFontSize }}" data-admin-ui-font="{{ $appUiFont }}">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.theme-boot')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @if(!empty($faviconUrl))
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" href="data:,">
        <link rel="shortcut icon" href="data:,">
    @endif
    <title>@yield('title', 'پنل') — {{ $appDisplayName }}</title>
    @include('layouts.partials.admin-ui-font-assets')
    @include('layouts.partials.admin-ui-font-style')
    @include('layouts.partials.fontawesome-local')
    <style>
        :root {
            --bg-page: #eef4fc;
            --bg-card: #ffffff;
            --border: rgba(37, 99, 235, 0.14);
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-soft: #eff6ff;
        }
        html[data-theme="dark"] {
            --bg-page: #0c1220;
            --bg-card: #151d2e;
            --border: rgba(59, 130, 246, 0.22);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --primary: #3b82f6;
            --primary-dark: #60a5fa;
            --primary-soft: rgba(37, 99, 235, 0.18);
        }
        * { box-sizing: border-box; }
        html[data-admin-font="small"] { font-size: 15px; }
        html[data-admin-font="normal"] { font-size: 16px; }
        html[data-admin-font="large"] { font-size: 18px; }
        html[data-admin-font="xlarge"] { font-size: 20px; }
        body.admin-embed-iframe {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background: var(--bg-page);
            line-height: 1.55;
            padding: 0.65rem 0.75rem 1rem;
        }
    </style>
    @include('layouts.partials.table-zebra-styles')
    @stack('head')
</head>
<body class="admin-embed-iframe">
    @include('layouts.partials.sweetalert2-css')
    @yield('content')
    @include('layouts.partials.sweetalert2-init')
    @stack('scripts')
</body>
</html>
