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
    <title>@yield('title', 'داشبورد') — {{ $appDisplayName }}</title>
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

        html[data-admin-font="small"] { font-size: 15px; }
        html[data-admin-font="normal"] { font-size: 16px; }
        html[data-admin-font="large"] { font-size: 18px; }
        html[data-admin-font="xlarge"] { font-size: 20px; }

        body.admin-app {
            margin: 0;
            min-height: 100vh;
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
        .sidebar-logo img {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            object-fit: cover;
            display: block;
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

        .admin-notif-wrap {
            position: relative;
            display: inline-flex;
            vertical-align: middle;
        }

        .admin-notif-badge {
            position: absolute;
            top: 0.1rem;
            inset-inline-end: 0.1rem;
            min-width: 1.05rem;
            height: 1.05rem;
            padding: 0 0.22rem;
            border-radius: 999px;
            font-size: 0.58rem;
            font-weight: 800;
            line-height: 1.05rem;
            text-align: center;
            background: #dc2626;
            color: #fff;
            box-shadow: 0 1px 4px rgba(220, 38, 38, 0.45);
            pointer-events: none;
        }

        .admin-notif-overlay {
            position: fixed;
            inset: 0;
            z-index: 199;
            background: rgba(15, 23, 42, 0.12);
            border: 0;
            padding: 0;
            margin: 0;
        }

        html[data-theme="dark"] .admin-notif-overlay {
            background: rgba(0, 0, 0, 0.35);
        }

        .admin-notif-flyout {
            position: fixed;
            z-index: 200;
            width: min(20rem, calc(100vw - 1rem));
            max-width: calc(100vw - 1rem);
            border-radius: 0.85rem;
            border: 1px solid var(--border);
            background: var(--topbar-bg);
            color: var(--text);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
            padding: 0;
            margin: 0;
            overflow: hidden;
        }

        html[data-theme="dark"] .admin-notif-flyout {
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
        }

        .admin-notif-flyout__head {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--text);
        }

        .admin-notif-flyout__body {
            padding: 0.65rem 0.75rem 0.75rem;
            max-height: min(70vh, 22rem);
            overflow-y: auto;
        }

        .admin-notif-empty {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            padding: 0.5rem 0.25rem;
        }

        .admin-notif-card {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            padding: 0.65rem 0.72rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(37, 99, 235, 0.28);
            background: var(--primary-soft);
            text-decoration: none;
            color: inherit;
            transition: border-color 0.12s ease, filter 0.12s ease;
        }

        .admin-notif-card:hover {
            border-color: var(--primary);
            filter: brightness(0.98);
        }

        html[data-theme="dark"] .admin-notif-card {
            background: rgba(30, 58, 138, 0.22);
        }

        .admin-notif-card__ico {
            font-size: 1.15rem;
            color: var(--primary-dark);
            opacity: 0.92;
        }

        .admin-notif-card__text {
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.5;
            color: var(--text);
        }

        .admin-notif-card__cta {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.74rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-top: 0.15rem;
        }

        .admin-notif-flyout__toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.45rem 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.74rem;
            color: var(--muted);
            font-weight: 700;
        }
        .admin-notif-flyout__toolbar form { margin: 0; }
        .admin-notif-mark-all {
            border: 1px solid rgba(37, 99, 235, 0.4);
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary-dark);
            font-family: inherit; font-size: 0.72rem; font-weight: 800;
            padding: 0.3rem 0.55rem; border-radius: 0.5rem; cursor: pointer;
        }
        .admin-notif-mark-all:hover:not(:disabled) { filter: brightness(1.05); }
        .admin-notif-mark-all:disabled { opacity: 0.45; cursor: not-allowed; }
        .admin-notif-section-h {
            margin: 0.1rem 0 0.45rem; font-size: 0.72rem; font-weight: 800; color: var(--muted);
            letter-spacing: 0.01em;
        }
        .admin-notif-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .admin-notif-item {
            display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.55rem;
            padding: 0.6rem 0.7rem; border-radius: 0.65rem; border: 1px solid var(--border);
            background: var(--bg-card); text-decoration: none; color: inherit;
            transition: border-color 0.12s ease, background 0.12s ease;
        }
        .admin-notif-item:hover { border-color: rgba(37, 99, 235, 0.5); background: rgba(37, 99, 235, 0.04); }
        .admin-notif-item--unread { border-right: 3px solid var(--primary); background: rgba(37, 99, 235, 0.06); }
        html[data-theme="dark"] .admin-notif-item--unread { background: rgba(30, 58, 138, 0.22); }
        .admin-notif-item__ico {
            width: 2.15rem; height: 2.15rem; border-radius: 0.55rem;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(37, 99, 235, 0.12); color: var(--primary-dark); font-size: 0.95rem;
            flex-shrink: 0;
        }
        .admin-notif-item__main { display: flex; flex-direction: column; gap: 0.25rem; min-width: 0; }
        .admin-notif-item__title { font-size: 0.82rem; font-weight: 800; color: var(--text); line-height: 1.4; }
        .admin-notif-item__body { font-size: 0.76rem; font-weight: 600; color: var(--muted); line-height: 1.55; word-break: break-word; }
        .admin-notif-item__meta { font-size: 0.7rem; font-weight: 700; color: var(--muted); opacity: 0.85; }

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
            gap: 0.45rem;
            padding: 0.5rem 0.7rem;
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 40;
            min-height: var(--mobile-topbar-h);
        }

        .mobile-nav-toggle,
        .mobile-topbar-btn {
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
            padding: 0;
            line-height: 1;
            font-family: inherit;
            position: relative;
            transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease, filter 0.12s ease;
        }

        .mobile-nav-toggle:hover:not(:disabled),
        .mobile-topbar-btn:hover:not(:disabled) {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
        }

        .mobile-topbar-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
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
            font-size: clamp(0.82rem, 3.6vw, 1.02rem);
            font-weight: 800;
            color: var(--topbar-date);
            letter-spacing: -0.02em;
            min-width: 0;
            line-height: 1.35;
            flex: 1 1 0;
            text-align: start;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mobile-topbar__actions {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-shrink: 0;
            margin-inline-start: auto;
        }

        .mobile-topbar__actions .admin-notif-wrap {
            display: inline-flex;
        }

        .mobile-topbar__actions .admin-notif-badge {
            top: 0.12rem;
            inset-inline-end: 0.12rem;
        }

        .mobile-topbar-btn .theme-ico-slot {
            width: 1.15rem;
            height: 1.15rem;
        }

        .mobile-topbar__logout-form {
            margin: 0;
            display: inline-flex;
        }

        .mobile-topbar-btn--logout {
            background: var(--icon-btn-bg);
            color: #b91c1c;
            border-color: rgba(220, 38, 38, 0.32);
            box-shadow: none;
        }

        .mobile-topbar-btn--logout:hover:not(:disabled) {
            background: rgba(248, 113, 113, 0.12);
            border-color: rgba(220, 38, 38, 0.55);
            color: #b91c1c;
            filter: none;
        }

        html[data-theme="dark"] .mobile-topbar-btn--logout {
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.35);
        }

        html[data-theme="dark"] .mobile-topbar-btn--logout:hover:not(:disabled) {
            background: rgba(248, 113, 113, 0.18);
            color: #fca5a5;
        }

        @media (max-width: 380px) {
            .mobile-topbar {
                gap: 0.3rem;
                padding: 0.45rem 0.55rem;
            }
            .mobile-nav-toggle,
            .mobile-topbar-btn {
                width: 2.3rem;
                height: 2.3rem;
                font-size: 1.02rem;
            }
            .mobile-topbar__actions {
                gap: 0.26rem;
            }
            .mobile-topbar-btn .theme-ico-slot {
                width: 1.05rem;
                height: 1.05rem;
            }
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
        .app-settings-field select,
        .app-settings-field textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 0.6rem;
            background: var(--bg-card);
            color: var(--text);
            padding: 0.48rem 0.58rem;
            font-family: inherit;
            font-size: 0.78rem;
        }

        .app-settings-field textarea {
            min-height: 11rem;
            resize: vertical;
            line-height: 1.55;
        }

        .app-settings-field input[readonly] {
            cursor: default;
            background: color-mix(in oklab, var(--bg-card) 82%, var(--primary-soft));
            color: var(--text);
            word-break: break-all;
            overflow-wrap: anywhere;
        }
        .app-icon-preview {
            margin-top: 0.45rem;
            width: 44px;
            height: 44px;
            border-radius: 0.72rem;
            border: 1px solid var(--border);
            background: var(--primary-soft);
            display: grid;
            place-items: center;
            color: var(--primary-dark);
            overflow: hidden;
            font-size: 1.1rem;
        }
        .app-icon-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .app-field-help {
            margin-top: 0.28rem;
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .app-checkbox-inline {
            font-size: 0.72rem;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 0.36rem;
            margin-top: 0.45rem;
            cursor: pointer;
            user-select: none;
        }
        .app-branding-grid {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            gap: 0.8rem;
            align-items: start;
        }
        .app-branding-previews {
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            background: color-mix(in oklab, var(--bg-card) 90%, var(--primary-soft));
            padding: 0.58rem;
            display: grid;
            gap: 0.55rem;
        }
        .app-branding-preview-item {
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            background: var(--bg-card);
            padding: 0.45rem;
            display: flex;
            align-items: center;
            gap: 0.48rem;
        }
        .app-branding-preview-meta {
            min-width: 0;
        }
        .app-branding-preview-label {
            display: block;
            font-size: 0.68rem;
            color: var(--muted);
            margin-bottom: 0.1rem;
        }
        .app-branding-preview-value {
            display: block;
            font-size: 0.74rem;
            color: var(--text);
            font-weight: 700;
        }
        .app-branding-controls {
            display: grid;
            gap: 0.68rem;
        }
        .app-branding-control-card {
            border: 1px solid var(--border);
            border-radius: 0.72rem;
            padding: 0.58rem;
            background: var(--bg-card);
        }
        .app-branding-control-title {
            font-size: 0.74rem;
            font-weight: 800;
            color: var(--text);
            margin: 0 0 0.35rem;
        }

        .app-settings-error {
            margin-top: 0.22rem;
            font-size: 0.72rem;
            color: #b91c1c;
            font-weight: 700;
        }

        .app-settings-note {
            margin: 0;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .app-financial-form {
            width: 100%;
            min-width: 0;
        }

        #app-settings-modal .app-banking-editor-wrap {
            min-width: 0;
            max-width: 100%;
        }

        #app-settings-modal .app-banking-editor-wrap .ck.ck-editor {
            max-width: 100%;
        }

        #app-settings-modal .app-banking-editor-wrap .ck.ck-editor__main {
            max-width: 100%;
        }

        #app-settings-modal .app-banking-editor-wrap .ck-editor__editable {
            min-height: 11rem;
            max-height: min(50vh, 22rem);
            overflow-y: auto;
        }

        @media (min-width: 640px) {
            #app-settings-modal .app-banking-editor-wrap .ck-editor__editable {
                min-height: 13rem;
                max-height: min(52vh, 26rem);
            }
        }

        .app-financial-select {
            min-height: 2.75rem;
            padding-block: 0.48rem;
            width: 100%;
            max-width: 100%;
        }

        .app-settings-card--banking-visibility {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.07) 0%, rgba(148, 163, 184, 0.08) 100%);
            border: 1px solid rgba(37, 99, 235, 0.22);
            box-shadow: 0 4px 18px rgba(37, 99, 235, 0.08);
        }

        html[data-theme="dark"] .app-settings-card--banking-visibility {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(15, 23, 42, 0.6) 100%);
            border-color: rgba(59, 130, 246, 0.35);
            box-shadow: 0 4px 22px rgba(0, 0, 0, 0.25);
        }

        .app-banking-visibility-title {
            margin: 0 0 0.35rem;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text);
        }

        .app-banking-visibility-lead {
            margin: 0 0 0.85rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.55;
        }

        .app-banking-visibility-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            flex-wrap: wrap;
            padding: 0.55rem 0.4rem 0.65rem;
            margin-bottom: 0.35rem;
            border-radius: 0.65rem;
            background: var(--bg-card);
            border: 1px dashed rgba(37, 99, 235, 0.28);
        }

        html[data-theme="dark"] .app-banking-visibility-control {
            border-color: rgba(96, 165, 250, 0.35);
        }

        .app-switch-legend {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--muted);
            min-width: 2.25rem;
            text-align: center;
            transition: color 0.15s ease, transform 0.15s ease;
        }

        .app-banking-visibility-control:has(.app-switch input:checked) .app-switch-legend--on {
            color: #1d4ed8;
            transform: scale(1.04);
        }

        .app-banking-visibility-control:has(.app-switch input:checked) .app-switch-legend--off {
            color: var(--muted);
            font-weight: 700;
        }

        .app-banking-visibility-control:not(:has(.app-switch input:checked)) .app-switch-legend--off {
            color: #0f172a;
            transform: scale(1.04);
        }

        html[data-theme="dark"] .app-banking-visibility-control:not(:has(.app-switch input:checked)) .app-switch-legend--off {
            color: #e2e8f0;
        }

        html[data-theme="dark"] .app-banking-visibility-control:has(.app-switch input:checked) .app-switch-legend--on {
            color: #93c5fd;
        }

        .app-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            cursor: pointer;
        }

        .app-switch input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
            pointer-events: none;
        }

        .app-switch-ui {
            width: 2.85rem;
            height: 1.55rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.45);
            border: 1px solid var(--border);
            transition: background 0.18s ease, border-color 0.18s ease;
            position: relative;
        }

        .app-switch-ui::after {
            content: "";
            position: absolute;
            top: 50%;
            inset-inline-start: 0.2rem;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
            transform: translateY(-50%);
            transition: inset-inline-start 0.2s ease, background 0.18s ease;
        }

        .app-switch--prominent .app-switch-ui {
            width: 3.65rem;
            height: 1.95rem;
            border: 2px solid #94a3b8;
            background: #e2e8f0;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .app-switch--prominent .app-switch-ui::after {
            width: 1.42rem;
            height: 1.42rem;
            inset-inline-start: 0.22rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.22);
        }

        .app-switch--prominent input:checked + .app-switch-ui {
            background: #2563eb;
            border-color: #1d4ed8;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .app-switch--prominent input:checked + .app-switch-ui::after {
            inset-inline-start: calc(100% - 1.42rem - 0.22rem);
            background: #f8fafc;
        }

        html[data-theme="dark"] .app-switch--prominent .app-switch-ui {
            background: #334155;
            border-color: #64748b;
        }

        html[data-theme="dark"] .app-switch--prominent input:checked + .app-switch-ui {
            background: #1d4ed8;
            border-color: #3b82f6;
        }

        .app-switch input:checked + .app-switch-ui {
            background: rgba(37, 99, 235, 0.35);
            border-color: rgba(37, 99, 235, 0.55);
        }

        .app-switch input:checked + .app-switch-ui::after {
            inset-inline-start: calc(100% - 1.1rem - 0.2rem);
        }

        .app-switch input:focus-visible + .app-switch-ui {
            outline: 3px solid rgba(37, 99, 235, 0.45);
            outline-offset: 3px;
        }

        .app-banking-visibility-note {
            margin: 0.45rem 0 0;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.65;
        }

        .app-settings-field--stack {
            margin-bottom: 0.65rem;
        }

        .app-settings-field--stack:last-of-type {
            margin-bottom: 0;
        }

        .app-financial-form .app-settings-actions {
            margin-top: 0.65rem;
            padding-top: 0.65rem;
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
            .app-branding-grid {
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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin-financial-ckeditor.js'])
    @include('layouts.partials.sweetalert2-css')
    @stack('head')
</head>
<body class="admin-app">
    @php
        $todayFormatted = rescue(
            function () {
                $j = jalali(now());

                return \Hekmatinasser\Jalali\Jalali::enToFaNumbers(
                    $j->format('l') . '، ' . $j->format('j F Y')
                );
            },
            now()->format('Y-m-d'),
            false,
        );
    @endphp

    <div class="admin-drawer-backdrop" id="admin-drawer-backdrop" aria-hidden="true"></div>

    <div class="admin-layout">
        <aside id="admin-drawer" class="admin-sidebar" aria-label="منوی کناری پنل">
            <div class="sidebar-brand only-desktop">
                <div class="sidebar-logo" aria-hidden="true">
                    @if(!empty($appIconUrl))
                        <img src="{{ $appIconUrl }}" alt="app icon">
                    @else
                        <i class="{{ $appIconFaClass }}"></i>
                    @endif
                </div>
                <div class="sidebar-title">{{ $appDisplayName }}</div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-label">منو</div>
                @php($nav = [
                    ['label' => 'داشبورد', 'href' => route('admin.dashboard'), 'icon' => 'fa-gauge-high', 'route' => 'admin.dashboard'],
                    ['label' => 'تعریف انواع وام', 'href' => route('admin.loan-types.index'), 'icon' => 'fa-money-bill-transfer', 'route' => 'admin.loan-types.index'],
                    ['label' => 'لیست مشتریان', 'href' => route('admin.customers.index'), 'icon' => 'fa-users', 'route' => 'admin.customers.index'],
                    ['label' => 'اعلام واریزها', 'href' => route('admin.deposit-declarations.index'), 'icon' => 'fa-building-columns', 'route' => 'admin.deposit-declarations.index'],
                    ['label' => 'مدیریت پیامک', 'href' => route('admin.sms.index'), 'icon' => 'fa-envelope', 'route' => 'admin.sms.index'],
                    ['label' => 'درخواست وام‌ها', 'href' => route('admin.loan-requests.index'), 'icon' => 'fa-file-invoice', 'route' => 'admin.loan-requests.index'],
                    ['label' => 'گزارش ورود', 'href' => route('admin.customer-login-logs.index'), 'icon' => 'fa-right-to-bracket', 'route' => 'admin.customer-login-logs.index'],
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

            {{-- بخش وضعیت در کشوی موبایل (دکمه‌های ابزار به نوار بالا منتقل شده‌اند) --}}
            <div class="drawer-extra only-mobile">
                <div class="drawer-extra-label">وضعیت</div>
                <div class="drawer-date-row">
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>امروز: {{ $todayFormatted }}</span>
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
                <h1 class="mobile-app-title">{{ $appDisplayName }}</h1>
                <div class="mobile-topbar__actions">
                    @if(auth()->guard('admin')->check())
                        <span class="admin-notif-wrap">
                            <button
                                type="button"
                                class="mobile-topbar-btn"
                                title="اعلان‌ها"
                                aria-label="اعلان‌ها"
                                aria-expanded="false"
                                aria-haspopup="dialog"
                                aria-controls="admin-notif-flyout"
                                data-admin-notif-toggle
                            >
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                @if(($adminNotificationsBadgeUnified ?? '') !== '')
                                    <span class="admin-notif-badge" aria-hidden="true">{{ $adminNotificationsBadgeUnified }}</span>
                                @elseif(($adminPendingDepositDeclarationsBadge ?? '') !== '')
                                    <span class="admin-notif-badge" aria-hidden="true">{{ $adminPendingDepositDeclarationsBadge }}</span>
                                @endif
                            </button>
                        </span>
                    @endif
                    <button
                        type="button"
                        class="mobile-topbar-btn"
                        title="حالت روشن / تیره"
                        aria-label="تغییر حالت روشن و تیره"
                        data-myghest-theme-toggle
                    >
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    @auth('admin')
                        <form class="mobile-topbar__logout-form" method="post" action="{{ route('admin.logout') }}" data-admin-logout-form>
                            @csrf
                            <button
                                type="submit"
                                class="mobile-topbar-btn mobile-topbar-btn--logout"
                                title="خروج"
                                aria-label="خروج از حساب ادمین"
                            >
                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <header class="topbar only-desktop">
                <div class="topbar-cluster">
                    <button type="button" class="icon-btn" title="جستجو" disabled aria-disabled="true">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                    @if(auth()->guard('admin')->check())
                        <span class="admin-notif-wrap">
                            <button
                                type="button"
                                class="icon-btn"
                                title="اعلان‌ها"
                                aria-expanded="false"
                                aria-haspopup="dialog"
                                aria-controls="admin-notif-flyout"
                                data-admin-notif-toggle
                            >
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                @if(($adminNotificationsBadgeUnified ?? '') !== '')
                                    <span class="admin-notif-badge" aria-hidden="true">{{ $adminNotificationsBadgeUnified }}</span>
                                @elseif(($adminPendingDepositDeclarationsBadge ?? '') !== '')
                                    <span class="admin-notif-badge" aria-hidden="true">{{ $adminPendingDepositDeclarationsBadge }}</span>
                                @endif
                            </button>
                        </span>
                    @else
                        <button type="button" class="icon-btn" title="اعلان‌ها" disabled aria-disabled="true">
                            <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        </button>
                    @endif
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
                        <form class="logout-form" method="post" action="{{ route('admin.logout') }}" data-admin-logout-form>
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

    @if(auth()->guard('admin')->check())
        @php($adminPendingDep = (int) ($adminPendingDepositDeclarationsCount ?? 0))
        @php($adminLoanNotifs = $adminLoanNotifications ?? collect())
        @php($adminLoanUnread = (int) ($adminLoanNotificationsUnreadCount ?? 0))
        <div id="admin-notif-overlay" class="admin-notif-overlay" hidden aria-hidden="true"></div>
        <div
            id="admin-notif-flyout"
            class="admin-notif-flyout"
            hidden
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-notif-flyout-title"
        >
            <div id="admin-notif-flyout-title" class="admin-notif-flyout__head">اعلان‌ها</div>
            @if($adminLoanUnread > 0)
                <div class="admin-notif-flyout__toolbar">
                    <span>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $adminLoanUnread) }} اعلان خوانده‌نشده</span>
                    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                        @csrf
                        <button type="submit" class="admin-notif-mark-all">خواندن همه</button>
                    </form>
                </div>
            @endif
            <div class="admin-notif-flyout__body">
                @if($adminPendingDep === 0 && $adminLoanNotifs->isEmpty())
                    <p class="admin-notif-empty">اعلان فعالی وجود ندارد.</p>
                @else
                    @if($adminPendingDep > 0)
                        <a href="{{ route('admin.deposit-declarations.index', ['status' => 'pending']) }}" class="admin-notif-card">
                            <span class="admin-notif-card__ico" aria-hidden="true">
                                <i class="fa-solid fa-building-columns"></i>
                            </span>
                            <span class="admin-notif-card__text">
                                @if($adminPendingDep === 1)
                                    یک درخواست اعلام واریزی ثبت شد و منتظر بررسی توسط شماست.
                                @else
                                    {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $adminPendingDep) }}
                                    درخواست اعلام واریزی در انتظار بررسی شماست.
                                @endif
                            </span>
                            <span class="admin-notif-card__cta">
                                رفتن به صفحهٔ اعلام واریزها
                                <i class="fa-solid fa-chevron-left" style="font-size:0.72rem;opacity:0.85" aria-hidden="true"></i>
                            </span>
                        </a>
                    @endif
                    @if(! $adminLoanNotifs->isEmpty())
                        @if($adminPendingDep > 0)
                            <p class="admin-notif-section-h">اعلان‌های اخیر</p>
                        @endif
                        <div class="admin-notif-list">
                            @foreach($adminLoanNotifs as $note)
                                <a href="{{ route('admin.notifications.follow', ['notification' => $note['id']]) }}"
                                    class="admin-notif-item {{ $note['is_unread'] ? 'admin-notif-item--unread' : '' }}">
                                    <span class="admin-notif-item__ico" aria-hidden="true">
                                        <i class="{{ $note['icon'] ?: 'fa-regular fa-bell' }}"></i>
                                    </span>
                                    <span class="admin-notif-item__main">
                                        <span class="admin-notif-item__title">{{ $note['title'] }}</span>
                                        <span class="admin-notif-item__body">{{ $note['body'] }}</span>
                                        <span class="admin-notif-item__meta">{{ $note['created_at_fa'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

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
                    <button type="button" class="app-settings-menu-btn" data-settings-tab="financial">
                        <i class="fa-solid fa-coins" aria-hidden="true"></i>
                        تنظیمات مالی
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
                            <p class="app-settings-card-desc">نام نمایشی در عنوان پنل و بخش‌های عمومی سامانه نمایش داده می‌شود.</p>
                            <form method="post" action="{{ route('admin.app-settings.base.update') }}">
                                @csrf
                                <div class="app-settings-field">
                                    <label for="app-display-name">نام نمایشی سامانه</label>
                                    <input id="app-display-name" type="text" name="display_name" value="{{ old('display_name', $appDisplayName) }}">
                                    @error('display_name')
                                        <div class="app-settings-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="app-settings-actions">
                                    <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                                </div>
                            </form>
                        </div>
                    </section>
                    <section class="app-settings-panel" data-settings-panel="ui" hidden>
                        <h4 class="app-settings-panel-title">ظاهر و تجربه کاربری</h4>
                        <p class="app-settings-panel-subtitle">نمایش و خوانایی پنل را مطابق ترجیح تیم تنظیم کنید.</p>
                        <form method="post" action="{{ route('admin.app-settings.ui.update') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="remove_app_icon" value="0">
                            <input type="hidden" name="remove_favicon" value="0">
                            <div class="app-settings-card">
                                <h4>تنظیمات نمای رابط</h4>
                                <p class="app-settings-card-desc">فونت و اندازهٔ متن روی تمام صفحات پنل ادمین اعمال می‌شود. برای ایران‌یکان و ایران‌سانس از نسخهٔ FaNum استفاده می‌شود؛ استعداد به‌صورت محلی از پوشهٔ ‎fonts/Estedad‎ بارگذاری می‌شود.</p>
                                <div class="app-settings-row">
                                    <div class="app-settings-field">
                                        <label for="app-ui-font">فونت رابط</label>
                                        <select id="app-ui-font" name="ui_font">
                                            <option value="iransans" @selected(old('ui_font', $appUiFont) === 'iransans')>ایران‌سنس (FaNum)</option>
                                            <option value="iranyekan" @selected(old('ui_font', $appUiFont) === 'iranyekan')>ایران‌یکان (FaNum)</option>
                                            <option value="anjoman" @selected(old('ui_font', $appUiFont) === 'anjoman')>انجمن</option>
                                            <option value="estedad" @selected(old('ui_font', $appUiFont) === 'estedad')>استعداد</option>
                                        </select>
                                        @error('ui_font')
                                            <div class="app-settings-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="app-settings-field">
                                        <label for="app-font-size">اندازه فونت</label>
                                        <select id="app-font-size" name="font_size">
                                            <option value="small" @selected(old('font_size', $appFontSize) === 'small')>کوچک</option>
                                            <option value="normal" @selected(old('font_size', $appFontSize) === 'normal')>معمولی</option>
                                            <option value="large" @selected(old('font_size', $appFontSize) === 'large')>بزرگ</option>
                                            <option value="xlarge" @selected(old('font_size', $appFontSize) === 'xlarge')>خیلی بزرگ</option>
                                        </select>
                                        @error('font_size')
                                            <div class="app-settings-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="app-settings-row">
                                    <div class="app-settings-field">
                                        <label>چیدمان پیش‌فرض داشبورد</label>
                                        <select disabled aria-disabled="true" title="به‌زودی">
                                            <option>فشرده</option>
                                            <option selected>استاندارد</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="app-settings-card">
                                <h4>هویت بصری برنامه</h4>
                                <p class="app-settings-card-desc">انتخاب‌ها را ساده‌تر کردیم: پیش‌نمایش زنده در سمت راست و تنظیمات در سمت چپ. در صورت آپلود فایل، تصویر نسبت به Font Awesome اولویت دارد.</p>
                                <div class="app-branding-grid">
                                    <div class="app-branding-previews">
                                        <div class="app-branding-preview-item">
                                            <div id="app-image-preview" class="app-icon-preview" aria-hidden="true">
                                                @if(!empty($appIconUrl))
                                                    <img src="{{ $appIconUrl }}" alt="app icon">
                                                @else
                                                    <i class="{{ old('app_icon_fa', $appIconFaClass) }}"></i>
                                                @endif
                                            </div>
                                            <div class="app-branding-preview-meta">
                                                <span class="app-branding-preview-label">لوگوی پنل</span>
                                                <span class="app-branding-preview-value">نمایش در نوار کناری</span>
                                            </div>
                                        </div>
                                        <div class="app-branding-preview-item">
                                            <div id="favicon-preview" class="app-icon-preview" aria-hidden="true" style="width:28px;height:28px;border-radius:.42rem">
                                                @if(!empty($faviconUrl))
                                                    <img src="{{ $faviconUrl }}" alt="favicon">
                                                @else
                                                    <i class="{{ old('favicon_fa', $faviconFaClass) }}" style="font-size:.85rem"></i>
                                                @endif
                                            </div>
                                            <div class="app-branding-preview-meta">
                                                <span class="app-branding-preview-label">فاوآیکون</span>
                                                <span class="app-branding-preview-value">نمایش در تب مرورگر</span>
                                            </div>
                                        </div>
                                        <div class="app-branding-preview-item">
                                            <div id="app-fa-preview" class="app-icon-preview" aria-hidden="true">
                                                <i class="{{ old('app_icon_fa', $appIconFaClass) }}"></i>
                                            </div>
                                            <div class="app-branding-preview-meta">
                                                <span class="app-branding-preview-label">آیکون پشتیبان</span>
                                                <span class="app-branding-preview-value">Font Awesome</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="app-branding-controls">
                                        <div class="app-branding-control-card">
                                            <p class="app-branding-control-title">آیکون Font Awesome</p>
                                            <div class="app-settings-field">
                                                <label for="app-icon-fa">کلاس آیکون</label>
                                                <input
                                                    id="app-icon-fa"
                                                    type="text"
                                                    name="app_icon_fa"
                                                    list="app-icon-fa-list"
                                                    value="{{ old('app_icon_fa', $appIconFaClass) }}"
                                                    placeholder="مثلاً fa-solid fa-building-columns"
                                                >
                                                <datalist id="app-icon-fa-list">
                                                    <option value="fa-solid fa-layer-group"></option>
                                                    <option value="fa-solid fa-building-columns"></option>
                                                    <option value="fa-solid fa-coins"></option>
                                                    <option value="fa-solid fa-hand-holding-dollar"></option>
                                                    <option value="fa-solid fa-shield-halved"></option>
                                                    <option value="fa-solid fa-chart-line"></option>
                                                    <option value="fa-solid fa-wallet"></option>
                                                    <option value="fa-solid fa-landmark"></option>
                                                </datalist>
                                                <p class="app-field-help">اگر فایل آیکون حذف شود یا وجود نداشته باشد، این کلاس استفاده می‌شود.</p>
                                                @error('app_icon_fa')
                                                    <div class="app-settings-error">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="app-branding-control-card">
                                            <p class="app-branding-control-title">فاوآیکون Font Awesome</p>
                                            <div class="app-settings-field">
                                                <label for="favicon-fa">کلاس آیکون فاوآیکون</label>
                                                <input
                                                    id="favicon-fa"
                                                    type="text"
                                                    name="favicon_fa"
                                                    list="app-icon-fa-list"
                                                    value="{{ old('favicon_fa', $faviconFaClass) }}"
                                                    placeholder="مثلاً fa-solid fa-globe"
                                                >
                                                <p class="app-field-help">اگر فایل فاوآیکون نداشته باشید، این آیکون برای تب مرورگر استفاده می‌شود.</p>
                                                @error('favicon_fa')
                                                    <div class="app-settings-error">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="app-branding-control-card">
                                            <p class="app-branding-control-title">آپلود فایل‌ها</p>
                                            <div class="app-settings-row">
                                                <div class="app-settings-field">
                                                    <label for="app-icon-upload">آیکون برنامه</label>
                                                    <input id="app-icon-upload" type="file" name="app_icon" accept=".png,.webp,.jpg,.jpeg,.svg">
                                                    <p class="app-field-help">فرمت پیشنهادی: PNG یا SVG مربعی (حداکثر 2MB)</p>
                                                    @error('app_icon')
                                                        <div class="app-settings-error">{{ $message }}</div>
                                                    @enderror
                                                    @if(!empty($appIconUrl))
                                                        <label class="app-checkbox-inline">
                                                            <input type="checkbox" name="remove_app_icon" value="1">
                                                            حذف آیکون فعلی
                                                        </label>
                                                    @endif
                                                </div>
                                                <div class="app-settings-field">
                                                    <label for="app-favicon-upload">فاوآیکون</label>
                                                    <input id="app-favicon-upload" type="file" name="favicon" accept=".ico,.png,.webp,.jpg,.jpeg,.svg">
                                                    <p class="app-field-help">فرمت پیشنهادی: ICO یا PNG (حداکثر 1MB)</p>
                                                    @error('favicon')
                                                        <div class="app-settings-error">{{ $message }}</div>
                                                    @enderror
                                                    @if(!empty($faviconUrl))
                                                        <label class="app-checkbox-inline">
                                                            <input type="checkbox" name="remove_favicon" value="1">
                                                            حذف فاوآیکون فعلی
                                                        </label>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="app-settings-actions">
                                <button type="button" id="app-ui-font-reset" class="app-settings-btn">بازنشانی</button>
                                <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
                            </div>
                        </form>
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
                    <section class="app-settings-panel" data-settings-panel="financial" hidden>
                        <h4 class="app-settings-panel-title">تنظیمات مالی</h4>
                        <p class="app-settings-panel-subtitle">توضیحات بانکی برای کاربران و درگاه پرداخت.</p>

                        <form method="post" action="{{ route('admin.app-settings.financial.update') }}" class="app-financial-form">
                            @csrf
                            <input type="hidden" name="banking_info_show_in_user_panel" value="0">
                            <div class="app-settings-card app-settings-card--banking-visibility">
                                <h4 class="app-banking-visibility-title">نمایش در پنل کاربر</h4>
                                <p class="app-banking-visibility-lead" id="banking-show-user-label">اطلاعات در پنل کاربر نمایش داده شود؟</p>
                                <div class="app-banking-visibility-control" role="group" aria-labelledby="banking-show-user-label">
                                    <span class="app-switch-legend app-switch-legend--off" aria-hidden="true">خیر</span>
                                    <label class="app-switch app-switch--prominent">
                                        <input
                                            type="checkbox"
                                            name="banking_info_show_in_user_panel"
                                            value="1"
                                            role="switch"
                                            aria-checked="{{ old('banking_info_show_in_user_panel', ($bankingInfoShowInUserPanel ?? false) ? '1' : '0') === '1' ? 'true' : 'false' }}"
                                            @checked(old('banking_info_show_in_user_panel', ($bankingInfoShowInUserPanel ?? false) ? '1' : '0') === '1')
                                        >
                                        <span class="app-switch-ui" aria-hidden="true"></span>
                                    </label>
                                    <span class="app-switch-legend app-switch-legend--on" aria-hidden="true">بله</span>
                                </div>
                                <p class="app-banking-visibility-note">با روشن کردن این گزینه، متن «اطلاعات بانکی» در داشبورد پنل کاربر (موبایل و دسکتاپ) نشان داده می‌شود.</p>
                                @error('banking_info_show_in_user_panel')
                                    <div class="app-settings-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="app-settings-card">
                                <h4>توضیحات اطلاعات بانکی</h4>
                                <p class="app-settings-card-desc">در صورت تمایل می‌توانید اطلاعات بانکی خود را جهت واریز وجه، شامل شماره کارت، شبا و غیره را در بخش زیر وارد کنید. محتوا پیش از ذخیره پاک‌سازی می‌شود و فقط در صورت روشن بودن گزینهٔ بالا در پنل کاربر دیده می‌شود.</p>
                                <div class="app-settings-field app-settings-field--stack app-banking-editor-wrap">
                                    <textarea
                                        id="banking-info-html"
                                        name="banking_info_html"
                                        rows="10"
                                        class="app-banking-textarea-fallback"
                                        spellcheck="true"
                                        dir="rtl"
                                        aria-label="توضیحات اطلاعات بانکی برای داشبورد کاربر"
                                    >{{ old('banking_info_html', $bankingInfoHtml ?? '') }}</textarea>
                                    @error('banking_info_html')
                                        <div class="app-settings-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="app-settings-card">
                                <div class="app-settings-field app-settings-field--stack">
                                    <label for="payment-gateway">درگاه پرداخت</label>
                                    <select
                                        id="payment-gateway"
                                        name="payment_gateway"
                                        class="app-financial-select"
                                        required
                                        autocomplete="off"
                                    >
                                        <option value="zibal" @selected(old('payment_gateway', $paymentGateway ?? 'zibal') === 'zibal')>زیبال</option>
                                        <option value="zarinpal" disabled>زرین‌پال</option>
                                        <option value="asanpardakht" disabled>آسان‌پرداخت</option>
                                    </select>
                                    @error('payment_gateway')
                                        <div class="app-settings-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="app-settings-field app-settings-field--stack">
                                    <label for="zibal-callback-url-readonly">آدرس بازگشت</label>
                                    <input
                                        id="zibal-callback-url-readonly"
                                        type="text"
                                        readonly
                                        value="{{ $zibalCallbackUrl }}"
                                        dir="ltr"
                                        style="text-align: left"
                                    >
                                </div>
                                <div class="app-settings-field app-settings-field--stack">
                                    <label for="zibal-merchant">شناسه مرچنت</label>
                                    <input
                                        id="zibal-merchant"
                                        type="text"
                                        name="zibal_merchant"
                                        value="{{ old('zibal_merchant', $zibalMerchant ?? '') }}"
                                        placeholder="zibal"
                                        autocomplete="off"
                                        spellcheck="false"
                                        dir="ltr"
                                        style="text-align: left"
                                        inputmode="text"
                                    >
                                    @error('zibal_merchant')
                                        <div class="app-settings-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="app-settings-actions">
                                    <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره</button>
                                </div>
                            </div>
                        </form>
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
                var notifFlyout = document.getElementById('admin-notif-flyout');
                var notifOverlay = document.getElementById('admin-notif-overlay');
                var hasUploadedFavicon = @json(!empty($faviconUrl));
                var faviconFaClass = @json($faviconFaClass ?? 'fa-solid fa-globe');

                function applyFontAwesomeFavicon() {
                    if (hasUploadedFavicon || !faviconFaClass) return;
                    var iconProbe = document.createElement('i');
                    iconProbe.className = faviconFaClass;
                    iconProbe.style.position = 'absolute';
                    iconProbe.style.left = '-9999px';
                    iconProbe.style.top = '-9999px';
                    document.body.appendChild(iconProbe);

                    var before = window.getComputedStyle(iconProbe, '::before');
                    var glyph = (before.content || '').replace(/^["']|["']$/g, '');
                    var fontFamily = before.fontFamily || 'Font Awesome 6 Free';
                    var fontWeight = before.fontWeight || '900';
                    document.body.removeChild(iconProbe);
                    if (!glyph || glyph === 'none') return;

                    var canvas = document.createElement('canvas');
                    canvas.width = 64;
                    canvas.height = 64;
                    var ctx = canvas.getContext('2d');
                    if (!ctx) return;
                    ctx.fillStyle = '#2563eb';
                    if (typeof ctx.roundRect === 'function') {
                        ctx.beginPath();
                        ctx.roundRect(0, 0, 64, 64, 14);
                        ctx.fill();
                    } else {
                        ctx.fillRect(0, 0, 64, 64);
                    }
                    ctx.fillStyle = '#ffffff';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.font = fontWeight + ' 34px ' + fontFamily;
                    ctx.fillText(glyph, 32, 34);
                    var dataUrl = canvas.toDataURL('image/png');

                    document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]').forEach(function (el) {
                        el.setAttribute('href', dataUrl);
                    });
                }

                if (document.fonts && typeof document.fonts.ready === 'object') {
                    document.fonts.ready.then(applyFontAwesomeFavicon).catch(function () {});
                } else {
                    setTimeout(applyFontAwesomeFavicon, 180);
                }

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

                function closeAdminNotif() {
                    if (!notifFlyout || notifFlyout.hidden) return;
                    notifFlyout.hidden = true;
                    notifFlyout.setAttribute('aria-hidden', 'true');
                    if (notifOverlay) {
                        notifOverlay.hidden = true;
                        notifOverlay.setAttribute('aria-hidden', 'true');
                    }
                    document.querySelectorAll('[data-admin-notif-toggle]').forEach(function (b) {
                        b.setAttribute('aria-expanded', 'false');
                    });
                }

                function positionAdminNotif(anchorBtn) {
                    if (!notifFlyout || !anchorBtn) return;
                    var rect = anchorBtn.getBoundingClientRect();
                    var gap = 8;
                    var pw = Math.min(320, window.innerWidth - 16);
                    notifFlyout.style.width = pw + 'px';
                    var left = rect.right - pw;
                    if (left < gap) left = gap;
                    if (left + pw > window.innerWidth - gap) left = window.innerWidth - pw - gap;
                    notifFlyout.style.left = left + 'px';
                    notifFlyout.style.top = (rect.bottom + gap) + 'px';
                }

                function openAdminNotif(anchorBtn) {
                    if (!notifFlyout) return;
                    positionAdminNotif(anchorBtn);
                    notifFlyout.hidden = false;
                    notifFlyout.setAttribute('aria-hidden', 'false');
                    if (notifOverlay) {
                        notifOverlay.hidden = false;
                        notifOverlay.setAttribute('aria-hidden', 'false');
                    }
                    document.querySelectorAll('[data-admin-notif-toggle]').forEach(function (b) {
                        b.setAttribute('aria-expanded', b === anchorBtn ? 'true' : 'false');
                    });
                }

                function toggleAdminNotif(anchorBtn) {
                    if (!notifFlyout) return;
                    if (!notifFlyout.hidden) {
                        var cur = document.querySelector('[data-admin-notif-toggle][aria-expanded="true"]');
                        if (cur === anchorBtn) {
                            closeAdminNotif();
                            return;
                        }
                    }
                    openAdminNotif(anchorBtn);
                }

                if (notifFlyout) {
                    document.querySelectorAll('[data-admin-notif-toggle]').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            toggleAdminNotif(btn);
                        });
                    });
                    if (notifOverlay) {
                        notifOverlay.addEventListener('click', closeAdminNotif);
                    }
                    document.addEventListener('click', function (e) {
                        if (notifFlyout.hidden) return;
                        if (notifFlyout.contains(e.target)) return;
                        if (e.target.closest && e.target.closest('[data-admin-notif-toggle]')) return;
                        closeAdminNotif();
                    });
                    window.addEventListener('resize', function () {
                        if (notifFlyout.hidden) return;
                        var openBtn = document.querySelector('[data-admin-notif-toggle][aria-expanded="true"]');
                        if (openBtn) positionAdminNotif(openBtn);
                    });
                }

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (notifFlyout && !notifFlyout.hidden) {
                        closeAdminNotif();
                        return;
                    }
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
                    if (tabId === 'financial' && typeof window.initFinancialBankingEditor === 'function') {
                        window.initFinancialBankingEditor();
                    }
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

                var fontResetBtn = document.getElementById('app-ui-font-reset');
                var fontSelect = document.getElementById('app-font-size');
                var uiFontSelect = document.getElementById('app-ui-font');
                var appIconFaInput = document.getElementById('app-icon-fa');
                var appFaPreview = document.getElementById('app-fa-preview');
                var appImagePreview = document.getElementById('app-image-preview');
                var appIconUpload = document.getElementById('app-icon-upload');
                var faviconUpload = document.getElementById('app-favicon-upload');
                var faviconPreview = document.getElementById('favicon-preview');
                var faviconFaInput = document.getElementById('favicon-fa');

                function renderFaPreview() {
                    if (!appFaPreview || !appIconFaInput) return;
                    var cls = String(appIconFaInput.value || '').trim();
                    if (!cls) cls = 'fa-solid fa-layer-group';
                    appFaPreview.innerHTML = '<i class="' + cls.replace(/"/g, '') + '"></i>';
                    var hasSelectedImage = !!(appIconUpload && appIconUpload.files && appIconUpload.files.length);
                    if (appImagePreview && !hasSelectedImage) {
                        var hasImg = appImagePreview.querySelector('img');
                        if (!hasImg) {
                            appImagePreview.innerHTML = '<i class="' + cls.replace(/"/g, '') + '"></i>';
                        }
                    }
                }

                function bindImagePreview(inputEl, previewEl) {
                    if (!inputEl || !previewEl) return;
                    inputEl.addEventListener('change', function () {
                        var file = inputEl.files && inputEl.files[0];
                        if (!file) return;
                        var reader = new FileReader();
                        reader.onload = function (ev) {
                            var result = ev && ev.target ? ev.target.result : '';
                            previewEl.innerHTML = '<img src="' + String(result || '') + '" alt="preview">';
                        };
                        reader.readAsDataURL(file);
                    });
                }

                function renderFaviconFaPreview() {
                    if (!faviconPreview || !faviconFaInput) return;
                    var hasSelectedFavicon = !!(faviconUpload && faviconUpload.files && faviconUpload.files.length);
                    if (hasSelectedFavicon) return;
                    var cls = String(faviconFaInput.value || '').trim();
                    if (!cls) cls = 'fa-solid fa-globe';
                    var hasImg = faviconPreview.querySelector('img');
                    if (!hasImg) {
                        faviconPreview.innerHTML = '<i class="' + cls.replace(/"/g, '') + '" style="font-size:.85rem"></i>';
                    }
                }

                if (fontResetBtn && fontSelect) {
                    fontResetBtn.addEventListener('click', function () {
                        fontSelect.value = 'normal';
                        if (uiFontSelect) {
                            uiFontSelect.value = 'iransans';
                        }
                        if (appIconFaInput) {
                            appIconFaInput.value = 'fa-solid fa-layer-group';
                            renderFaPreview();
                        }
                        if (faviconFaInput) {
                            faviconFaInput.value = 'fa-solid fa-globe';
                            renderFaviconFaPreview();
                        }
                    });
                }

                if (appIconFaInput) {
                    appIconFaInput.addEventListener('input', renderFaPreview);
                    appIconFaInput.addEventListener('change', renderFaPreview);
                }
                renderFaPreview();
                bindImagePreview(appIconUpload, appImagePreview);
                bindImagePreview(faviconUpload, faviconPreview);
                if (faviconFaInput) {
                    faviconFaInput.addEventListener('input', renderFaviconFaPreview);
                    faviconFaInput.addEventListener('change', renderFaviconFaPreview);
                }
                renderFaviconFaPreview();

                @if($errors->has('font_size') || $errors->has('ui_font') || $errors->has('app_icon') || $errors->has('favicon') || $errors->has('app_icon_fa') || $errors->has('favicon_fa'))
                activateSettingsTab('ui');
                openSettings();
                @elseif($errors->has('display_name'))
                activateSettingsTab('base');
                openSettings();
                @elseif($errors->has('zibal_merchant') || $errors->has('payment_gateway') || $errors->has('banking_info_html') || $errors->has('banking_info_show_in_user_panel'))
                activateSettingsTab('financial');
                openSettings();
                @elseif(session('open_app_settings_tab') === 'financial')
                activateSettingsTab('financial');
                openSettings();
                @else
                activateSettingsTab('base');
                @endif

                var adminLogoutConfirmText = 'شما در حال خروج از سامانه هستید. مطمئنید؟';
                document.querySelectorAll('form[data-admin-logout-form]').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        if (form.dataset.adminLogoutConfirmed === '1') return;
                        e.preventDefault();
                        function doSubmit() {
                            form.dataset.adminLogoutConfirmed = '1';
                            if (mq.matches) closeDrawer();
                            if (notifFlyout && !notifFlyout.hidden) closeAdminNotif();
                            if (appSettingsOverlay && !appSettingsOverlay.hidden) closeSettings();
                            form.submit();
                        }
                        function fallbackConfirm() {
                            if (window.confirm(adminLogoutConfirmText)) doSubmit();
                        }
                        if (!window.AdminSwal || typeof AdminSwal.confirm !== 'function') {
                            fallbackConfirm();
                            return;
                        }
                        AdminSwal.confirm({
                            icon: 'warning',
                            title: 'خروج از سامانه',
                            text: adminLogoutConfirmText,
                            confirmButtonText: 'بله، خارج شو',
                            cancelButtonText: 'انصراف',
                            confirmButtonColor: '#dc2626',
                            focusCancel: true,
                        }).then(function (res) {
                            if (res && res.isConfirmed) doSubmit();
                        }).catch(fallbackConfirm);
                    });
                });
            });
        })();
    </script>
    @include('layouts.partials.sweetalert2-init')
    @stack('scripts')
</body>
</html>
