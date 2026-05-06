<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.theme-boot')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'داشبورد') — {{ config('app.name') }}</title>
    @include('layouts.partials.iransans-fanum')
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
            --sidebar-w: 258px;
            --mobile-topbar-h: 3.45rem;
            --sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f4f8ff 100%);
            --sidebar-title: #0b1220;
            --nav-color: #334155;
            --topbar-bg: #fff;
            --topbar-date: #1e293b;
            --icon-btn-bg: #f8fafc;
            --icon-btn-color: #475569;
            --icon-btn-border: rgba(148, 163, 184, 0.45);
            --sidebar-foot-bg: #fff;
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
            --sidebar-bg: linear-gradient(180deg, #111827 0%, #0f172a 100%);
            --sidebar-title: #f1f5f9;
            --nav-color: #cbd5e1;
            --topbar-bg: #111827;
            --topbar-date: #e2e8f0;
            --icon-btn-bg: #1e293b;
            --icon-btn-color: #cbd5e1;
            --icon-btn-border: rgba(148, 163, 184, 0.28);
            --sidebar-foot-bg: #1e293b;
        }

        * { box-sizing: border-box; }

        body.admin-app {
            margin: 0;
            min-height: 100vh;
            font-family: IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background: var(--bg-page);
            line-height: 1.55;
        }

        body.admin-app.admin-drawer-open {
            overflow: hidden;
        }

        body.admin-app.app-settings-open {
            overflow: hidden;
        }

        /* فقط دسکتاپ / فقط موبایل */
        .only-mobile { display: none !important; }

        .admin-layout {
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
            min-height: 100vh;
        }

        .admin-sidebar {
            background: var(--sidebar-bg);
            border-inline-start: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            align-self: start;
            min-height: 100vh;
            z-index: 20;
            grid-column: 1;
        }

        .admin-column {
            grid-column: 2;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 1.1rem 1rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            background: linear-gradient(145deg, #3b82f6, #1d4ed8);
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
            color: #fff;
            font-size: 1.05rem;
        }

        .sidebar-title {
            font-weight: 800;
            font-size: 1.02rem;
            color: var(--sidebar-title);
            letter-spacing: -0.02em;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 0.65rem 0.5rem 1rem;
            -webkit-overflow-scrolling: touch;
        }

        .nav-section-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--muted);
            padding: 0.5rem 0.75rem 0.35rem;
            letter-spacing: 0.04em;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.55rem 0.75rem;
            margin: 0.12rem 0;
            border-radius: 0.65rem;
            color: var(--nav-color);
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 600;
            transition: background 0.12s ease, color 0.12s ease;
        }

        .nav-link:hover {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .nav-link.is-active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.12), rgba(37, 99, 235, 0.04));
            color: var(--primary-dark);
            border-inline-end: 3px solid var(--primary);
        }

        html[data-theme="dark"] .nav-link.is-active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.06));
        }

        .nav-link--disabled {
            opacity: 0.55;
            cursor: not-allowed;
            pointer-events: none;
        }

        .nav-ico {
            width: 1.35rem;
            flex-shrink: 0;
            text-align: center;
            opacity: 0.92;
            font-size: 0.95rem;
            line-height: 1;
        }

        .drawer-extra {
            padding: 0.5rem 0.65rem 0.75rem;
            border-top: 1px dashed var(--border);
            border-bottom: 1px dashed var(--border);
        }

        .drawer-extra-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.45rem;
        }

        .drawer-quick-icons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.72rem;
        }

        .drawer-date-row {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--topbar-date);
            margin-bottom: 0.72rem;
            line-height: 1.35;
            display: flex;
            align-items: flex-start;
            gap: 0.35rem;
        }

        .drawer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }

        .drawer-actions .logout-form {
            flex: 1 1 auto;
        }

        .drawer-actions .logout-form button {
            width: 100%;
            justify-content: center;
        }

        .sidebar-foot {
            padding: 0.65rem 0.75rem 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .sidebar-foot button {
            width: 100%;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.52rem 0.65rem;
            border-radius: 0.6rem;
            cursor: pointer;
            border: 1px solid rgba(37, 99, 235, 0.28);
            background: var(--sidebar-foot-bg);
            color: var(--primary-dark);
            transition: background 0.12s ease, border-color 0.12s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        html[data-theme="dark"] .sidebar-foot button {
            border-color: rgba(59, 130, 246, 0.35);
        }

        .sidebar-foot button:hover:not(:disabled) {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.45);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 0.65rem 1.15rem;
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--border);
            min-height: 3.25rem;
        }

        .topbar-date {
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--topbar-date);
            flex: 1;
            text-align: center;
            min-width: 0;
        }

        .topbar-cluster {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-shrink: 0;
        }

        .icon-btn {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 0.65rem;
            border: 1px solid var(--icon-btn-border);
            background: var(--icon-btn-bg);
            display: grid;
            place-items: center;
            cursor: pointer;
            color: var(--icon-btn-color);
            transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
            font-size: 1rem;
        }

        .icon-btn:hover:not(:disabled) {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
            color: var(--primary-dark);
        }

        .icon-btn:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .theme-ico-slot {
            display: inline-grid;
            place-items: center;
            width: 1.1rem;
            height: 1.1rem;
        }

        .theme-ico-slot [data-theme-icon] {
            grid-area: 1 / 1;
        }

        .icon-btn--static {
            cursor: default;
        }

        .logout-form {
            margin: 0;
        }

        .logout-form button {
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.45rem 0.75rem;
            border-radius: 0.6rem;
            border: none;
            cursor: pointer;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .logout-form button:hover {
            filter: brightness(1.04);
        }

        .content-wrap {
            flex: 1;
            padding: 1.1rem 1.15rem 1.5rem;
            overflow-x: hidden;
        }

        /* پردهٔ تیره فقط زیر سکشن کشو در موبایل */
        .admin-drawer-backdrop {
            display: none !important;
        }

        @media (max-width: 960px) {
            .admin-drawer-backdrop {
                display: block !important;
                position: fixed;
                inset: var(--mobile-topbar-h) 0 0 0;
                z-index: 90;
                background: rgba(15, 23, 42, 0.45);
                backdrop-filter: blur(1px);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.2s ease, visibility 0.2s;
            }

            html[data-theme="dark"] .admin-drawer-backdrop {
                background: rgba(0, 0, 0, 0.55);
            }

            .admin-drawer-backdrop.is-visible {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
        }

        .mobile-topbar {
            display: none;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0.85rem;
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 40;
            min-height: var(--mobile-topbar-h);
        }

        .mobile-nav-toggle {
            flex-shrink: 0;
            width: 2.55rem;
            height: 2.55rem;
            border-radius: 0.7rem;
            border: 1px solid var(--icon-btn-border);
            background: var(--icon-btn-bg);
            display: grid;
            place-items: center;
            cursor: pointer;
            color: var(--primary-dark);
            font-size: 1.12rem;
            transition: background 0.12s ease, border-color 0.12s ease;
        }

        .mobile-nav-toggle:hover {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
        }

        .mobile-nav-toggle__ico {
            grid-area: 1 / 1;
        }

        .mobile-nav-toggle__ico--close {
            display: none;
        }

        .admin-drawer-open .mobile-nav-toggle__ico--bars {
            display: none;
        }

        .admin-drawer-open .mobile-nav-toggle__ico--close {
            display: block;
        }

        .mobile-app-title {
            margin: 0;
            font-size: clamp(0.88rem, 3.9vw, 1.06rem);
            font-weight: 800;
            color: var(--topbar-date);
            letter-spacing: -0.02em;
            min-width: 0;
            line-height: 1.35;
            flex: 1;
            text-align: start;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 960px) {
            .only-mobile {
                display: flex !important;
            }

            .only-desktop {
                display: none !important;
            }

            .admin-layout {
                display: block;
                min-height: 100vh;
            }

            .admin-column {
                min-height: 0;
            }

            /* پنل RTL: کشو از لبهٔ راست (همان ستون کناری دسکتاپ) */
            html[dir="rtl"] .admin-sidebar {
                position: fixed;
                top: var(--mobile-topbar-h);
                bottom: 0;
                right: 0;
                left: auto;
                width: min(296px, 90vw);
                min-height: 0;
                z-index: 100;
                border-inline-start: none;
                box-shadow:
                    inset 1px 0 0 var(--border),
                    -12px 0 40px rgba(15, 23, 42, 0.12);
                transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1),
                    visibility 0.26s;
                visibility: hidden;
                transform: translateX(106%);
                align-self: auto;
                overflow: hidden;
            }

            html[dir="rtl"][data-theme="dark"] .admin-sidebar {
                box-shadow:
                    inset 1px 0 0 var(--border),
                    -14px 0 48px rgba(0, 0, 0, 0.35);
            }

            html[dir="rtl"] .admin-sidebar.is-open {
                visibility: visible;
                transform: translateX(0);
            }

            html[dir="ltr"] .admin-sidebar {
                position: fixed;
                top: var(--mobile-topbar-h);
                bottom: 0;
                left: 0;
                right: auto;
                width: min(296px, 90vw);
                min-height: 0;
                z-index: 100;
                visibility: hidden;
                transform: translateX(-106%);
                transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1),
                    visibility 0.26s;
                overflow: hidden;
            }

            html[dir="ltr"] .admin-sidebar.is-open {
                visibility: visible;
                transform: translateX(0);
            }

            .admin-sidebar .sidebar-brand {
                display: none;
            }

            .mobile-topbar {
                display: flex;
            }

            /* بلوک «نوار ابزار» کشو فقط موبایل */
            .drawer-extra.only-mobile {
                display: flex !important;
                flex-direction: column;
            }
        }

        /* دسکتاپ: سکشن کشوی ابزار مخفی می‌ماند */
        @media (min-width: 961px) {
            .drawer-extra.only-mobile {
                display: none !important;
            }
        }

        .app-settings-overlay {
            position: fixed;
            inset: 0;
            z-index: 1400;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(2px);
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .app-settings-overlay[hidden] {
            display: none !important;
        }

        .app-settings-modal {
            width: min(980px, 100%);
            max-height: min(90vh, 760px);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1.05rem;
            box-shadow: 0 30px 72px rgba(15, 23, 42, 0.24);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .app-settings-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.06), transparent 85%);
        }

        .app-settings-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text);
        }

        .app-settings-subtitle {
            margin: 0.1rem 0 0;
            font-size: 0.74rem;
            color: var(--muted);
        }

        .app-settings-close {
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 0.55rem;
            background: var(--primary-soft);
            color: var(--primary-dark);
            cursor: pointer;
        }

        .app-settings-body {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: 0;
            flex: 1;
        }

        .app-settings-menu {
            border-inline-start: 1px solid var(--border);
            background: color-mix(in oklab, var(--bg-card) 86%, var(--primary-soft));
            padding: 0.7rem 0.55rem;
            overflow-y: auto;
        }

        .app-settings-menu-btn {
            width: 100%;
            border: 1px solid transparent;
            border-radius: 0.65rem;
            background: transparent;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-align: start;
            padding: 0.52rem 0.62rem;
            margin-bottom: 0.2rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
        }

        .app-settings-menu-btn.is-active {
            color: var(--primary-dark);
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.26);
        }

        .app-settings-content {
            padding: 0.9rem 1rem;
            overflow-y: auto;
        }

        .app-settings-panel-title {
            margin: 0 0 0.18rem;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text);
        }

        .app-settings-panel-subtitle {
            margin: 0 0 0.75rem;
            font-size: 0.74rem;
            color: var(--muted);
        }

        .app-settings-panel[hidden] {
            display: none !important;
        }

        .app-settings-card {
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            background: var(--bg-card);
            padding: 0.75rem 0.85rem;
            margin-bottom: 0.7rem;
        }

        .app-settings-card h4 {
            margin: 0 0 0.45rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--text);
        }

        .app-settings-card-desc {
            margin: -0.2rem 0 0.55rem;
            font-size: 0.73rem;
            color: var(--muted);
        }

        .app-settings-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
        }

        .app-settings-field label {
            display: block;
            font-size: 0.73rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 0.2rem;
        }

        .app-settings-field input,
        .app-settings-field select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 0.6rem;
            background: var(--bg-card);
            color: var(--text);
            padding: 0.48rem 0.58rem;
            font-family: inherit;
            font-size: 0.78rem;
        }

        .app-settings-note {
            margin: 0;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .app-settings-actions {
            margin-top: 0.8rem;
            padding-top: 0.65rem;
            border-top: 1px dashed var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.45rem;
        }

        .app-settings-btn {
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            border-radius: 0.58rem;
            padding: 0.44rem 0.72rem;
            font-size: 0.76rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }

        .app-settings-btn--primary {
            border: none;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff;
        }

        @media (max-width: 860px) {
            .app-settings-modal {
                width: min(100%, 760px);
            }

            .app-settings-body {
                grid-template-columns: 1fr;
            }

            .app-settings-menu {
                border-inline-start: 0;
                border-bottom: 1px solid var(--border);
                overflow-x: auto;
                overflow-y: hidden;
                white-space: nowrap;
                display: flex;
                gap: 0.3rem;
                padding: 0.55rem;
            }

            .app-settings-menu-btn {
                width: auto;
                margin-bottom: 0;
                flex: 0 0 auto;
            }

            .app-settings-row {
                grid-template-columns: 1fr;
            }

            .app-settings-actions {
                justify-content: stretch;
            }

            .app-settings-btn {
                flex: 1 1 auto;
            }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.sweetalert2-css')
    @stack('head')
</head>
<body class="admin-app">
    @php($todayFormatted = rescue(
        function () {
            $j = jalali(now());

            return \Hekmatinasser\Jalali\Jalali::enToFaNumbers(
                $j->format('l') . '، ' . $j->format('j F Y')
            );
        },
        now()->format('Y-m-d'),
        false,
    ))

    <div class="admin-drawer-backdrop" id="admin-drawer-backdrop" aria-hidden="true"></div>

    <div class="admin-layout">
        <aside id="admin-drawer" class="admin-sidebar" aria-label="منوی کناری پنل">
            <div class="sidebar-brand only-desktop">
                <div class="sidebar-logo" aria-hidden="true">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="sidebar-title">{{ config('app.name') }}</div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-label">منو</div>
                @php($nav = [
                    ['label' => 'داشبورد', 'href' => route('admin.dashboard'), 'icon' => 'fa-gauge-high', 'route' => 'admin.dashboard'],
                    ['label' => 'تعریف انواع وام', 'href' => route('admin.loan-types.index'), 'icon' => 'fa-money-bill-transfer', 'route' => 'admin.loan-types.index'],
                    ['label' => 'لیست مشتریان', 'icon' => 'fa-users', 'disabled' => true],
                    ['label' => 'اعلام واریزها', 'icon' => 'fa-building-columns', 'disabled' => true],
                    ['label' => 'مدیریت پیامک', 'href' => route('admin.sms.index'), 'icon' => 'fa-envelope', 'route' => 'admin.sms.index'],
                    ['label' => 'درخواست وام‌ها', 'icon' => 'fa-file-invoice', 'disabled' => true],
                    ['label' => 'نماینده‌ها', 'icon' => 'fa-user-tie', 'disabled' => true],
                    ['label' => 'بازاریاب‌ها', 'icon' => 'fa-bullhorn', 'disabled' => true],
                    ['label' => 'سفارش خریدها', 'icon' => 'fa-cart-shopping', 'disabled' => true],
                    ['label' => 'گزارش‌ها', 'icon' => 'fa-chart-column', 'disabled' => true],
                    ['label' => 'کاربران', 'icon' => 'fa-user-group', 'disabled' => true],
                ])
                @foreach ($nav as $item)
                    @if(! empty($item['disabled']))
                        <span class="nav-link nav-link--disabled js-drawer-close-on-nav">
                            <i class="fa-solid {{ $item['icon'] }} nav-ico" aria-hidden="true"></i>
                            {{ $item['label'] }}
                        </span>
                    @else
                        <a
                            href="{{ $item['href'] }}"
                            class="nav-link js-drawer-nav-link @if(isset($item['route']) && request()->routeIs($item['route'])) is-active @endif"
                        >
                            <i class="fa-solid {{ $item['icon'] }} nav-ico" aria-hidden="true"></i>
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </nav>

            {{-- ابزارهای نوار بالا فقط در موبایل داخل کشو --}}
            <div class="drawer-extra only-mobile">
                <div class="drawer-extra-label">نوار ابزار</div>
                <div class="drawer-quick-icons">
                    <button type="button" class="icon-btn" title="جستجو" disabled aria-disabled="true">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="icon-btn" title="اعلان‌ها" disabled aria-disabled="true">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    </button>
                    <span class="icon-btn icon-btn--static" title="پروفایل" aria-hidden="true">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="drawer-date-row">
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>امروز: {{ $todayFormatted }}</span>
                </div>
                <div class="drawer-actions">
                    <button type="button" class="icon-btn" title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره" data-myghest-theme-toggle>
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    @auth('admin')
                        <form class="logout-form" method="post" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit">
                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                                خروج
                            </button>
                        </form>
                    @endauth
                </div>
            </div>

            <div class="sidebar-foot">
                <button type="button" id="app-settings-open" aria-haspopup="dialog" aria-controls="app-settings-modal">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    تنظیمات برنامه
                </button>
                <button type="button" disabled title="به‌زودی">
                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                    پشتیبان‌گیری و بازیابی
                </button>
            </div>
        </aside>

        <div class="admin-column">
            <header class="mobile-topbar only-mobile" role="banner">
                <button
                    type="button"
                    id="mobile-nav-toggle"
                    class="mobile-nav-toggle"
                    aria-controls="admin-drawer"
                    aria-expanded="false"
                    aria-label="باز و بسته کردن منو"
                >
                    <i class="fa-solid fa-bars mobile-nav-toggle__ico mobile-nav-toggle__ico--bars" aria-hidden="true"></i>
                    <i class="fa-solid fa-xmark mobile-nav-toggle__ico mobile-nav-toggle__ico--close" aria-hidden="true"></i>
                </button>
                <h1 class="mobile-app-title">{{ config('app.name') }}</h1>
            </header>

            <header class="topbar only-desktop">
                <div class="topbar-cluster">
                    <button type="button" class="icon-btn" title="جستجو" disabled aria-disabled="true">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="icon-btn" title="اعلان‌ها" disabled aria-disabled="true">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    </button>
                    <span class="icon-btn icon-btn--static" title="پروفایل" aria-hidden="true">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="topbar-date">
                    <i class="fa-regular fa-calendar-days" style="margin-inline-end:0.35rem;opacity:0.85" aria-hidden="true"></i>
                    امروز:
                    {{ $todayFormatted }}
                </div>
                <div class="topbar-cluster">
                    <button type="button" class="icon-btn" title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره" data-myghest-theme-toggle>
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    @auth('admin')
                        <form class="logout-form" method="post" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit">
                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                                خروج
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <div class="content-wrap">
                @yield('content')
            </div>
        </div>
    </div>

    <div id="app-settings-overlay" class="app-settings-overlay" hidden aria-hidden="true">
        <div id="app-settings-modal" class="app-settings-modal" role="dialog" aria-modal="true" aria-labelledby="app-settings-title">
            <div class="app-settings-head">
                <div>
                    <h3 id="app-settings-title" class="app-settings-title">تنظیمات برنامه</h3>
                    <p class="app-settings-subtitle">شخصی‌سازی رفتار سامانه، ظاهر و اعلان‌ها</p>
                </div>
                <button type="button" id="app-settings-close" class="app-settings-close" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="app-settings-body">
                <aside class="app-settings-menu" aria-label="دسته‌بندی تنظیمات">
                    <button type="button" class="app-settings-menu-btn is-active" data-settings-tab="base">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        تنظیمات بنیان
                    </button>
                    <button type="button" class="app-settings-menu-btn" data-settings-tab="ui">
                        <i class="fa-solid fa-palette" aria-hidden="true"></i>
                        ظاهر و تجربه کاربری
                    </button>
                    <button type="button" class="app-settings-menu-btn" data-settings-tab="notifications">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        اعلان‌ها
                    </button>
                    <button type="button" class="app-settings-menu-btn" data-settings-tab="security">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        امنیت
                    </button>
                </aside>
                <div class="app-settings-content">
                    <section class="app-settings-panel" data-settings-panel="base">
                        <h4 class="app-settings-panel-title">تنظیمات بنیان</h4>
                        <p class="app-settings-panel-subtitle">پارامترهای اصلی سامانه که رفتار کلی را تعیین می‌کنند.</p>
                        <div class="app-settings-card">
                            <h4>اطلاعات پایه سامانه</h4>
                            <p class="app-settings-card-desc">این بخش برای تعریف اطلاعات پایه‌ای و ثابت محیط کاری استفاده می‌شود.</p>
                            <div class="app-settings-row">
                                <div class="app-settings-field">
                                    <label>نام نمایشی سامانه</label>
                                    <input type="text" value="{{ config('app.name') }}" readonly>
                                </div>
                                <div class="app-settings-field">
                                    <label>منطقه زمانی</label>
                                    <select>
                                        <option>Asia/Tehran</option>
                                        <option>UTC</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p class="app-settings-note">این آیتم‌ها نمایشی هستند و در مرحله بعدی به ذخیره‌سازی واقعی متصل می‌شوند.</p>
                        <div class="app-settings-actions">
                            <button type="button" class="app-settings-btn">بازنشانی</button>
                            <button type="button" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                        </div>
                    </section>
                    <section class="app-settings-panel" data-settings-panel="ui" hidden>
                        <h4 class="app-settings-panel-title">ظاهر و تجربه کاربری</h4>
                        <p class="app-settings-panel-subtitle">نمایش و خوانایی پنل را مطابق ترجیح تیم تنظیم کنید.</p>
                        <div class="app-settings-card">
                            <h4>تنظیمات نمای رابط</h4>
                            <p class="app-settings-card-desc">تنظیمات این بخش روی نحوه نمایش صفحات و اجزای پنل اثر می‌گذارد.</p>
                            <div class="app-settings-row">
                                <div class="app-settings-field">
                                    <label>چیدمان پیش‌فرض داشبورد</label>
                                    <select>
                                        <option>فشرده</option>
                                        <option>استاندارد</option>
                                    </select>
                                </div>
                                <div class="app-settings-field">
                                    <label>اندازه فونت</label>
                                    <select>
                                        <option>معمولی</option>
                                        <option>بزرگ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="app-settings-actions">
                            <button type="button" class="app-settings-btn">بازنشانی</button>
                            <button type="button" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                        </div>
                    </section>
                    <section class="app-settings-panel" data-settings-panel="notifications" hidden>
                        <h4 class="app-settings-panel-title">اعلان‌ها</h4>
                        <p class="app-settings-panel-subtitle">قوانین ارسال اطلاع‌رسانی برای کاربران و مدیران را مدیریت کنید.</p>
                        <div class="app-settings-card">
                            <h4>تنظیمات اعلان</h4>
                            <p class="app-settings-card-desc">تنظیم کنید چه زمانی اعلان پیامکی یا خلاصه گزارش ارسال شود.</p>
                            <div class="app-settings-row">
                                <div class="app-settings-field">
                                    <label>دریافت اعلان پیامکی</label>
                                    <select>
                                        <option>فعال</option>
                                        <option>غیرفعال</option>
                                    </select>
                                </div>
                                <div class="app-settings-field">
                                    <label>ارسال گزارش روزانه</label>
                                    <select>
                                        <option>فعال</option>
                                        <option>غیرفعال</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="app-settings-actions">
                            <button type="button" class="app-settings-btn">بازنشانی</button>
                            <button type="button" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                        </div>
                    </section>
                    <section class="app-settings-panel" data-settings-panel="security" hidden>
                        <h4 class="app-settings-panel-title">امنیت</h4>
                        <p class="app-settings-panel-subtitle">برای افزایش امنیت دسترسی‌ها، سیاست‌های ورود را تنظیم کنید.</p>
                        <div class="app-settings-card">
                            <h4>تنظیمات امنیتی</h4>
                            <p class="app-settings-card-desc">این بخش برای کنترل ریسک نشست‌های کاربری و دسترسی‌ها طراحی شده است.</p>
                            <div class="app-settings-row">
                                <div class="app-settings-field">
                                    <label>مدت انقضای نشست</label>
                                    <select>
                                        <option>30 دقیقه</option>
                                        <option>60 دقیقه</option>
                                    </select>
                                </div>
                                <div class="app-settings-field">
                                    <label>ورود دو مرحله‌ای</label>
                                    <select>
                                        <option>غیرفعال</option>
                                        <option>فعال</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="app-settings-actions">
                            <button type="button" class="app-settings-btn">بازنشانی</button>
                            <button type="button" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    @include('layouts.partials.theme-toggle-script')
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var mq = window.matchMedia('(max-width: 960px)');

                function closeDrawer() {
                    var root = document.body;
                    var aside = document.getElementById('admin-drawer');
                    var bd = document.getElementById('admin-drawer-backdrop');
                    var btn = document.getElementById('mobile-nav-toggle');

                    root.classList.remove('admin-drawer-open');

                    if (aside) aside.classList.remove('is-open');

                    if (bd) {
                        bd.classList.remove('is-visible');

                        bd.setAttribute('aria-hidden', 'true');
                    }

                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                    }
                }

                function openDrawer() {
                    var root = document.body;
                    var aside = document.getElementById('admin-drawer');
                    var bd = document.getElementById('admin-drawer-backdrop');
                    var btn = document.getElementById('mobile-nav-toggle');

                    if (! mq.matches) return;

                    root.classList.add('admin-drawer-open');

                    if (aside) aside.classList.add('is-open');

                    if (bd) {
                        bd.setAttribute('aria-hidden', 'false');

                        bd.classList.add('is-visible');
                    }

                    if (btn) {
                        btn.setAttribute('aria-expanded', 'true');
                    }
                }

                function toggleDrawer() {
                    var aside = document.getElementById('admin-drawer');

                    if (aside && aside.classList.contains('is-open')) {
                        closeDrawer();
                    } else {
                        openDrawer();
                    }
                }

                document.getElementById('mobile-nav-toggle')
                    ?.addEventListener('click', toggleDrawer);

                document.getElementById('admin-drawer-backdrop')
                    ?.addEventListener('click', closeDrawer);

                document.querySelectorAll('.js-drawer-nav-link').forEach(function (a) {
                    a.addEventListener('click', function () {
                        if (mq.matches) closeDrawer();
                    });
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (appSettingsOverlay && !appSettingsOverlay.hidden) {
                        closeSettings();
                        return;
                    }
                    if (mq.matches) closeDrawer();
                });

                function onMqChange(ev) {
                    if (! ev.matches) closeDrawer();
                }

                if (typeof mq.addEventListener === 'function') {
                    mq.addEventListener('change', onMqChange);
                } else if (typeof mq.addListener === 'function') {
                    mq.addListener(onMqChange);
                }

                var appSettingsOpen = document.getElementById('app-settings-open');
                var appSettingsOverlay = document.getElementById('app-settings-overlay');
                var appSettingsClose = document.getElementById('app-settings-close');
                var settingsTabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
                var settingsPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));

                function activateSettingsTab(tabId) {
                    settingsTabs.forEach(function (tabBtn) {
                        var active = tabBtn.getAttribute('data-settings-tab') === tabId;
                        tabBtn.classList.toggle('is-active', active);
                    });
                    settingsPanels.forEach(function (panelEl) {
                        panelEl.hidden = panelEl.getAttribute('data-settings-panel') !== tabId;
                    });
                }

                function openSettings() {
                    if (!appSettingsOverlay) return;
                    appSettingsOverlay.hidden = false;
                    appSettingsOverlay.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('app-settings-open');
                }

                function closeSettings() {
                    if (!appSettingsOverlay) return;
                    appSettingsOverlay.hidden = true;
                    appSettingsOverlay.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('app-settings-open');
                }

                if (appSettingsOpen) {
                    appSettingsOpen.addEventListener('click', function () {
                        activateSettingsTab('base');
                        openSettings();
                    });
                }

                if (appSettingsClose) {
                    appSettingsClose.addEventListener('click', closeSettings);
                }

                if (appSettingsOverlay) {
                    appSettingsOverlay.addEventListener('click', function (event) {
                        if (event.target === appSettingsOverlay) closeSettings();
                    });
                }

                settingsTabs.forEach(function (tabBtn) {
                    tabBtn.addEventListener('click', function () {
                        activateSettingsTab(tabBtn.getAttribute('data-settings-tab'));
                    });
                });

                activateSettingsTab('base');
            });
        })();
    </script>
    @include('layouts.partials.sweetalert2-init')
    @stack('scripts')
</body>
</html>
