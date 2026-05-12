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
    <title>@yield('title', 'پنل کاربری') — {{ $appDisplayName }}</title>
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
        body.up-app {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background: var(--bg-page);
            line-height: 1.55;
        }
        body.up-app.up-drawer-open { overflow: hidden; }
        .only-mobile { display: none !important; }
        .up-layout {
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
            min-height: 100vh;
        }
        .up-sidebar {
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
        .up-column { grid-column: 2; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
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
        .sidebar-logo img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; display: block; }
        .sidebar-title { font-weight: 800; font-size: 1.02rem; color: var(--sidebar-title); letter-spacing: -0.02em; }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 0.65rem 0.5rem 1rem; -webkit-overflow-scrolling: touch; }
        .nav-section-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--muted);
            padding: 0.5rem 0.75rem 0.35rem;
            letter-spacing: 0.04em;
        }
        .up-nav-link {
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
        .up-nav-link:hover { background: var(--primary-soft); color: var(--primary-dark); }
        .up-nav-link.is-active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.12), rgba(37, 99, 235, 0.04));
            color: var(--primary-dark);
            border-inline-end: 3px solid var(--primary);
        }
        html[data-theme="dark"] .up-nav-link.is-active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.06));
        }
        .nav-ico { width: 1.35rem; flex-shrink: 0; text-align: center; opacity: 0.92; font-size: 0.95rem; line-height: 1; }
        .drawer-extra { padding: 0.5rem 0.65rem 0.75rem; border-top: 1px dashed var(--border); border-bottom: 1px dashed var(--border); }
        .drawer-extra-label { font-size: 0.65rem; font-weight: 800; color: var(--muted); letter-spacing: 0.04em; margin-bottom: 0.45rem; }
        .drawer-date-row { font-size: 0.8rem; font-weight: 700; color: var(--topbar-date); margin-bottom: 0.72rem; line-height: 1.35; display: flex; align-items: flex-start; gap: 0.35rem; }
        .drawer-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
        .drawer-actions .logout-form { flex: 1 1 auto; }
        .drawer-actions .logout-form button { width: 100%; justify-content: center; }
        .sidebar-foot {
            padding: 0.65rem 0.75rem 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        .up-wallet-card {
            width: 100%;
            text-align: center;
            padding: 0.72rem 0.65rem 0.82rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(37, 99, 235, 0.28);
            background: linear-gradient(165deg, var(--primary-soft), color-mix(in oklab, var(--bg-card) 92%, var(--primary-soft)));
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.1);
        }
        html[data-theme="dark"] .up-wallet-card {
            border-color: rgba(59, 130, 246, 0.35);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }
        .up-wallet-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .up-wallet-amount {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--primary-dark);
            letter-spacing: 0.02em;
            font-variant-numeric: tabular-nums;
        }
        .up-wallet-currency { font-size: 0.72rem; font-weight: 700; color: var(--muted); margin-inline-start: 0.2rem; }
        .logout-form { margin: 0; }
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
        .logout-form button:hover { filter: brightness(1.04); }
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
        .topbar-date { font-size: 0.86rem; font-weight: 700; color: var(--topbar-date); flex: 1; text-align: center; min-width: 0; }
        .topbar-cluster { display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0; }
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
        .icon-btn:hover:not(:disabled) { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); color: var(--primary-dark); }
        .icon-btn.icon-btn--static { cursor: default; pointer-events: none; }

        .up-notif-wrap {
            position: relative;
            display: inline-flex;
            vertical-align: middle;
        }
        .up-notif-badge {
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
        .up-notif-overlay {
            position: fixed;
            inset: 0;
            z-index: 199;
            background: rgba(15, 23, 42, 0.12);
            border: 0;
            padding: 0;
            margin: 0;
        }
        html[data-theme="dark"] .up-notif-overlay {
            background: rgba(0, 0, 0, 0.35);
        }
        .up-notif-flyout {
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
        html[data-theme="dark"] .up-notif-flyout {
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
        }
        .up-notif-flyout__head {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--text);
        }
        .up-notif-flyout__body {
            padding: 0.65rem 0.75rem 0.75rem;
            max-height: min(70vh, 22rem);
            overflow-y: auto;
        }
        .up-notif-empty {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            padding: 0.5rem 0.25rem;
        }
        .up-notif-card {
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
        .up-notif-card:hover {
            border-color: var(--primary);
            filter: brightness(0.98);
        }
        html[data-theme="dark"] .up-notif-card {
            background: rgba(30, 58, 138, 0.22);
        }
        .up-notif-card__ico {
            font-size: 1.15rem;
            color: var(--primary-dark);
            opacity: 0.92;
        }
        .up-notif-card__text {
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.55;
            color: var(--text);
        }
        .up-notif-card__cta {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.74rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-top: 0.15rem;
        }
        .theme-ico-slot { display: inline-grid; place-items: center; width: 1.1rem; height: 1.1rem; }
        .theme-ico-slot [data-theme-icon] { grid-area: 1 / 1; }
        .up-user-chip {
            max-width: 11rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--topbar-date);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0.2rem 0.45rem;
            border-radius: 0.5rem;
            border: 1px dashed var(--border);
        }
        .content-wrap { flex: 1; padding: 1.1rem 1.15rem 1.5rem; overflow-x: hidden; }
        .up-drawer-backdrop { display: none !important; }
        @media (max-width: 960px) {
            .up-drawer-backdrop {
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
            html[data-theme="dark"] .up-drawer-backdrop { background: rgba(0, 0, 0, 0.55); }
            .up-drawer-backdrop.is-visible { opacity: 1; visibility: visible; pointer-events: auto; }
            .only-mobile { display: flex !important; }
            .only-desktop { display: none !important; }
            .up-layout { display: block; min-height: 100vh; }
            .up-column { min-height: 0; }
            html[dir="rtl"] .up-sidebar {
                position: fixed;
                top: var(--mobile-topbar-h);
                bottom: 0;
                right: 0;
                left: auto;
                width: min(296px, 90vw);
                min-height: 0;
                z-index: 100;
                border-inline-start: none;
                box-shadow: inset 1px 0 0 var(--border), -12px 0 40px rgba(15, 23, 42, 0.12);
                transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.26s;
                visibility: hidden;
                transform: translateX(106%);
                overflow: hidden;
            }
            html[dir="rtl"][data-theme="dark"] .up-sidebar {
                box-shadow: inset 1px 0 0 var(--border), -14px 0 48px rgba(0, 0, 0, 0.35);
            }
            html[dir="rtl"] .up-sidebar.is-open { visibility: visible; transform: translateX(0); }
            html[dir="ltr"] .up-sidebar {
                position: fixed;
                top: var(--mobile-topbar-h);
                bottom: 0;
                left: 0;
                right: auto;
                width: min(296px, 90vw);
                z-index: 100;
                visibility: hidden;
                transform: translateX(-106%);
                transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.26s;
                overflow: hidden;
            }
            html[dir="ltr"] .up-sidebar.is-open { visibility: visible; transform: translateX(0); }
            .up-sidebar .sidebar-brand { display: none; }
            .mobile-topbar { display: flex; align-items: center; gap: 0.45rem; padding: 0.5rem 0.7rem; background: var(--topbar-bg); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 40; min-height: var(--mobile-topbar-h); }
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
            .mobile-topbar-btn:hover:not(:disabled),
            .mobile-nav-toggle:hover:not(:disabled) {
                background: var(--primary-soft);
                border-color: rgba(37, 99, 235, 0.35);
            }
            .mobile-topbar-btn:disabled { opacity: 0.55; cursor: not-allowed; }
            .mobile-nav-toggle__ico { grid-area: 1 / 1; }
            .mobile-nav-toggle__ico--close { display: none; }
            .up-drawer-open .mobile-nav-toggle__ico--bars { display: none; }
            .up-drawer-open .mobile-nav-toggle__ico--close { display: block; }
            .mobile-app-title { margin: 0; font-size: clamp(0.82rem, 3.6vw, 1.02rem); font-weight: 800; color: var(--topbar-date); flex: 1 1 0; text-align: start; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .mobile-topbar__actions {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                flex-shrink: 0;
                margin-inline-start: auto;
            }
            .mobile-topbar__actions .up-notif-wrap { display: inline-flex; }
            .mobile-topbar__actions .up-notif-badge {
                top: 0.12rem;
                inset-inline-end: 0.12rem;
            }
            .mobile-topbar-btn .theme-ico-slot { width: 1.15rem; height: 1.15rem; }
            .mobile-topbar__logout-form { margin: 0; display: inline-flex; }
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
            .drawer-extra.only-mobile { display: flex !important; flex-direction: column; }
        }
        @media (max-width: 380px) {
            .mobile-topbar { gap: 0.3rem; padding: 0.45rem 0.55rem; }
            .mobile-nav-toggle,
            .mobile-topbar-btn { width: 2.3rem; height: 2.3rem; font-size: 1.02rem; }
            .mobile-topbar__actions { gap: 0.26rem; }
            .mobile-topbar-btn .theme-ico-slot { width: 1.05rem; height: 1.05rem; }
        }
        @media (min-width: 961px) {
            .drawer-extra.only-mobile { display: none !important; }
            .mobile-topbar { display: none; }
        }
        .portal-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem 1.2rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
            max-width: 52rem;
        }
        html[data-theme="dark"] .portal-card { box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25); }
        .portal-card h2 { margin: 0 0 0.5rem; font-size: 1.08rem; color: var(--text); }
        .portal-card p { margin: 0; color: var(--muted); font-size: 0.9rem; }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .portal-banking {
            width: 100%;
            max-width: 100%;
            margin: 0 0 1.15rem;
        }

        .portal-banking__shell {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1rem 1.05rem 1.15rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
        }

        html[data-theme="dark"] .portal-banking__shell {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
        }

        .portal-banking__title {
            margin: 0 0 0.85rem;
            padding-bottom: 0.65rem;
            border-bottom: 1px dashed var(--border);
            font-size: clamp(1rem, 3.8vw, 1.12rem);
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .portal-banking__grid {
            display: grid;
            gap: 1rem;
            align-items: stretch;
            grid-template-columns: 1fr;
        }

        .portal-banking__media {
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 0;
        }

        .portal-banking__img {
            width: min(100%, 15rem);
            height: auto;
            max-height: 10.5rem;
            object-fit: contain;
            object-position: center;
            display: block;
            border-radius: 0.75rem;
            background: var(--primary-soft);
            padding: 0.5rem;
        }

        .portal-banking__body {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .portal-banking__html {
            font-size: 0.9rem;
            line-height: 1.65;
            color: var(--text);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }

        .portal-banking__html :where(p, ul, ol, h2, h3, h4) {
            margin: 0 0 0.55rem;
        }

        .portal-banking__html :where(ul, ol) {
            padding-inline-start: 1.15rem;
        }

        .portal-banking__html :where(table) {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin: 0.35rem 0 0.65rem;
        }

        .portal-banking__html :where(th, td) {
            border: 1px solid var(--border);
            padding: 0.35rem 0.45rem;
            vertical-align: top;
            word-break: break-word;
        }

        .portal-banking__html :where(a) {
            color: var(--primary-dark);
            text-decoration: underline;
            text-underline-offset: 0.12em;
            word-break: break-word;
        }

        .portal-banking__html :where(a):hover {
            text-decoration-thickness: 2px;
        }

        @media (min-width: 720px) {
            .portal-banking__grid {
                grid-template-columns: minmax(11rem, 15rem) minmax(0, 1fr);
                gap: 1.15rem 1.35rem;
                align-items: stretch;
            }

            .portal-banking__media {
                align-self: stretch;
                justify-content: center;
                align-items: center;
                padding: 0.7rem 0.6rem;
                border-radius: 0.85rem;
                background: var(--primary-soft);
                border: 1px solid var(--border);
            }

            .portal-banking__img {
                width: auto;
                max-width: 100%;
                height: auto;
                max-height: min(70vh, 28rem);
                object-fit: contain;
                object-position: center;
                background: transparent;
                padding: 0.2rem;
            }

            .portal-banking__body {
                justify-content: center;
                align-self: stretch;
            }

            .portal-banking__html {
                font-size: 0.92rem;
            }
        }

        .portal-loans {
            width: 100%;
            max-width: 100%;
            margin: 0 0 1.15rem;
        }

        .portal-loans__shell {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1rem 1.05rem 1.15rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
        }

        html[data-theme="dark"] .portal-loans__shell {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
        }

        .portal-loans__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--border);
        }

        .portal-loans__head-main {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .portal-loans__head-ico {
            font-size: 1.15rem;
            color: var(--primary-dark);
            opacity: 0.9;
        }

        .portal-loans__title {
            margin: 0;
            font-size: clamp(1rem, 3.8vw, 1.12rem);
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .portal-loans__badge {
            flex-shrink: 0;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--primary-dark);
            background: var(--primary-soft);
            border: 1px solid rgba(37, 99, 235, 0.28);
            padding: 0.28rem 0.55rem;
            border-radius: 999px;
            white-space: nowrap;
        }

        .portal-loans__empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.45rem;
            padding: 1rem 0.5rem;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
            text-align: center;
        }

        .portal-loans__empty-ico {
            font-size: 1.75rem;
            opacity: 0.45;
        }

        .portal-loans__empty p {
            margin: 0;
        }

        .portal-loans__list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .portal-loan {
            border: 1px solid var(--border);
            border-radius: 0.95rem;
            padding: 0.55rem 0.6rem 0.65rem;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.05) 0%, transparent 46%);
            overflow: hidden;
        }

        html[data-theme="dark"] .portal-loan {
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.12) 0%, transparent 50%);
        }

        .portal-loan__bar {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            justify-content: space-between;
            gap: 0.4rem;
            margin-bottom: 0.4rem;
            flex-wrap: nowrap;
        }

        .portal-loan__bar--solo {
            justify-content: center;
        }

        .portal-loan__ribbon-slot {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .portal-loan__ribbon {
            width: auto;
            max-width: 10.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.1rem;
            padding: 0.32rem 0.42rem 0.38rem;
            border-radius: 0.55rem;
            font-size: 0.66rem;
            font-weight: 900;
            line-height: 1.2;
            color: #fff;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
        }

        .portal-loan__ribbon-ico {
            font-size: 1rem;
            line-height: 1;
            display: block;
            margin-bottom: 0.06rem;
        }

        .portal-loan__ribbon-text {
            display: block;
        }

        .portal-loan__ribbon-sub {
            display: block;
            font-size: 0.6rem;
            font-weight: 700;
            opacity: 0.92;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .portal-loan--state-settled .portal-loan__ribbon {
            background: linear-gradient(145deg, #059669, #047857);
        }

        .portal-loan--state-revoked .portal-loan__ribbon {
            background: linear-gradient(145deg, #b45309, #92400e);
        }

        .portal-loan--state-creditor .portal-loan__ribbon {
            background: linear-gradient(145deg, #7c3aed, #5b21b6);
        }

        .portal-loan__code-card {
            flex: 0 0 auto;
            min-width: 6.75rem;
            max-width: 11rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 0.04rem;
            padding: 0.28rem 0.45rem 0.32rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.72);
        }

        html[data-theme="dark"] .portal-loan__code-card {
            background: rgba(30, 41, 59, 0.78);
        }

        .portal-loan__bar--solo .portal-loan__code-card {
            max-width: 100%;
            flex: 1 1 auto;
        }

        .portal-loan__code-card-k {
            font-size: 0.58rem;
            font-weight: 800;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }

        .portal-loan__code-card-v {
            font-size: 0.74rem;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -0.02em;
            direction: ltr;
            unicode-bidi: embed;
        }

        .portal-loan__top {
            margin-bottom: 0.35rem;
        }

        .portal-loan__title {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }

        .portal-loan__inline-ico {
            font-size: 0.85em;
            opacity: 0.85;
        }

        .portal-loan__progress {
            margin: 0 0 0.4rem;
        }

        .portal-loan__progress-track {
            height: 0.38rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.35);
            overflow: hidden;
        }

        .portal-loan__progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            transition: width 0.35s ease;
        }

        .portal-loan__progress-label {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.2rem;
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--muted);
        }

        .portal-loan__stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.48rem 0.55rem;
            margin: 0 0 0.48rem;
            align-items: stretch;
        }

        .portal-loan__stat {
            display: block;
            min-width: 0;
        }

        .portal-loan__stat--remain {
            grid-column: 1 / -1;
        }

        .portal-loan__stat-in {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: space-between;
            gap: 0.32rem;
            min-height: 4.15rem;
            height: 100%;
            padding: 0.42rem 0.5rem 0.46rem;
            border-radius: 0.52rem;
            border: 1px solid rgba(148, 163, 184, 0.48);
            background: rgba(255, 255, 255, 0.72);
            font-size: 0.74rem;
            line-height: 1.38;
        }

        html[data-theme="dark"] .portal-loan__stat-in {
            background: rgba(30, 41, 59, 0.72);
        }

        .portal-loan__stat-head {
            display: flex;
            align-items: center;
            gap: 0.32rem;
            flex-wrap: wrap;
            min-width: 0;
        }

        .portal-loan__stat-ico {
            font-size: 0.82rem;
            color: var(--primary-dark);
            opacity: 0.9;
            flex-shrink: 0;
        }

        .portal-loan__stat-k {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--muted);
            line-height: 1.25;
        }

        .portal-loan__stat-v {
            font-size: 0.76rem;
            font-weight: 800;
            color: var(--text);
            min-width: 0;
            white-space: normal;
            word-break: break-word;
        }

        .portal-loan__stat-paren {
            font-weight: 700;
            color: var(--muted);
            font-size: 0.88em;
        }

        .portal-loan__stat--paid .portal-loan__stat-in {
            border-color: rgba(37, 99, 235, 0.38);
            background: var(--primary-soft);
        }

        .portal-loan__stat--remain.portal-loan__stat--ok .portal-loan__stat-in {
            border-color: rgba(5, 150, 105, 0.48);
            background: rgba(16, 185, 129, 0.14);
        }

        .portal-loan__stat--remain.portal-loan__stat--warn .portal-loan__stat-in {
            border-color: rgba(217, 119, 6, 0.45);
            background: rgba(251, 191, 36, 0.14);
        }

        .portal-loan__stat--remain.portal-loan__stat--creditor .portal-loan__stat-in {
            border-color: rgba(124, 58, 237, 0.48);
            background: rgba(124, 58, 237, 0.12);
        }

        @media (min-width: 900px) {
            .portal-loan__stats {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 0.5rem 0.55rem;
            }

            .portal-loan__stat--remain {
                grid-column: auto;
            }

            .portal-loan__stat-in {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 0.4rem 0.55rem;
                min-height: 2.85rem;
                padding: 0.36rem 0.48rem 0.4rem;
            }

            .portal-loan__stat-head {
                flex-wrap: nowrap;
                flex-shrink: 0;
            }

            .portal-loan__stat-v {
                text-align: end;
                flex: 1;
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 360px) {
            .portal-loan__ribbon {
                max-width: 9.25rem;
                padding: 0.28rem 0.36rem 0.34rem;
            }
        }

        .portal-loan__settle-row {
            margin: 0.25rem 0 0.35rem;
        }

        .portal-loan__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.42rem 0.75rem;
            border-radius: 0.6rem;
            border: 1px solid var(--border);
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
            white-space: nowrap;
        }

        .portal-loan__btn--settle {
            background: #fff;
            color: var(--primary-dark);
            border-color: rgba(37, 99, 235, 0.45);
        }

        html[data-theme="dark"] .portal-loan__btn--settle {
            background: #1e293b;
            color: #93c5fd;
        }

        .portal-loan__btn--settle:hover {
            background: var(--primary-soft);
        }

        .portal-loan__btn--primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .portal-loan__btn--primary:hover {
            filter: brightness(1.05);
        }

        .portal-loan__btn--ghost {
            background: var(--bg-card);
            color: var(--text);
        }

        .portal-loan__btn--ghost:hover {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
        }

        .portal-loan__btn--disabled,
        .portal-loan__btn--disabled:hover {
            opacity: 0.55;
            cursor: not-allowed;
            pointer-events: auto;
        }

        .portal-loan__btn--table {
            font-size: 0.68rem;
            padding: 0.32rem 0.45rem;
            min-height: 2rem;
            white-space: nowrap;
        }

        .portal-loan__btn--block {
            width: 100%;
        }

        .portal-loan__details {
            margin-top: 0.2rem;
            border-radius: 0.65rem;
            border: 1px dashed rgba(148, 163, 184, 0.65);
            overflow: hidden;
        }

        .portal-loan__summary {
            list-style: none;
            cursor: pointer;
            padding: 0.55rem 0.65rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--primary-dark);
            background: rgba(37, 99, 235, 0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .portal-loan__summary-inner {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .portal-loan__summary::-webkit-details-marker {
            display: none;
        }

        .portal-loan__summary::after {
            content: "\f078";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 0.72rem;
            opacity: 0.75;
            transition: transform 0.2s ease;
        }

        .portal-loan__details[open] .portal-loan__summary::after {
            transform: rotate(-180deg);
        }

        .portal-loan__inst-list {
            padding: 0.55rem 0.45rem 0.65rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            max-height: min(70vh, 26rem);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .portal-inst {
            border: 1px solid var(--border);
            border-radius: 0.7rem;
            padding: 0.55rem 0.6rem 0.6rem;
            background: var(--bg-card);
            border-inline-start: 3px solid rgba(148, 163, 184, 0.65);
        }

        .portal-inst--tone-ok {
            border-inline-start-color: #059669;
            background: rgba(16, 185, 129, 0.06);
        }

        .portal-inst--tone-danger {
            border-inline-start-color: #dc2626;
            background: rgba(248, 113, 113, 0.08);
        }

        .portal-inst--tone-partial {
            border-inline-start-color: #d97706;
            background: rgba(251, 191, 36, 0.08);
        }

        .portal-inst__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem 0.5rem;
            margin-bottom: 0.45rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px dashed var(--border);
        }

        .portal-inst__n {
            font-weight: 800;
            font-size: 0.86rem;
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .portal-inst__status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--primary-dark);
            background: var(--primary-soft);
            padding: 0.18rem 0.48rem;
            border-radius: 999px;
        }

        .portal-inst--tone-ok .portal-inst__status {
            color: #047857;
            background: rgba(16, 185, 129, 0.18);
        }

        .portal-inst--tone-danger .portal-inst__status {
            color: #b91c1c;
            background: rgba(248, 113, 113, 0.2);
        }

        .portal-inst__tiles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem 0.5rem;
        }

        @media (max-width: 420px) {
            .portal-inst__tiles {
                grid-template-columns: 1fr;
            }
        }

        .portal-inst__tiles > div {
            display: flex;
            flex-direction: column;
            gap: 0.06rem;
            min-width: 0;
        }

        .portal-inst__k {
            font-size: 0.64rem;
            font-weight: 800;
            color: var(--muted);
        }

        .portal-inst__v {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text);
            word-break: break-word;
        }

        .portal-inst__note {
            margin: 0.45rem 0 0;
            font-size: 0.72rem;
            line-height: 1.55;
            color: var(--muted);
        }

        .portal-inst__actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem;
            margin-top: 0.55rem;
        }

        @media (max-width: 380px) {
            .portal-inst__actions {
                grid-template-columns: 1fr;
            }
        }

        .portal-inst__actions .portal-loan__btn {
            width: 100%;
            min-height: 2.35rem;
        }

        .portal-inst__locked {
            margin-top: 0.55rem;
            padding: 0.45rem 0.5rem;
            border-radius: 0.55rem;
            background: rgba(148, 163, 184, 0.16);
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.5;
            color: var(--muted);
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
        }

        .portal-inst__locked i {
            margin-top: 0.12rem;
            color: var(--text);
            opacity: 0.65;
        }

        .portal-summary {
            width: 100%;
            max-width: 100%;
            margin: 0 0 1.15rem;
        }

        .portal-summary__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        @media (min-width: 720px) {
            .portal-summary__grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1100px) {
            .portal-summary__grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .portal-sum-card {
            --sum-accent: var(--primary-dark);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.9rem;
            padding: 0.78rem 0.82rem 0.85rem;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
            min-height: 7.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        html[data-theme="dark"] .portal-sum-card {
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22);
        }

        .portal-sum-card:hover {
            border-color: color-mix(in srgb, var(--sum-accent) 38%, var(--border));
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
        }

        html[data-theme="dark"] .portal-sum-card:hover {
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
        }

        .portal-sum-card--loans { --sum-accent: #2563eb; }
        .portal-sum-card--paid { --sum-accent: #059669; }
        .portal-sum-card--remain { --sum-accent: #d97706; }
        .portal-sum-card--wallet { --sum-accent: #7c3aed; }
        .portal-sum-card--tickets { --sum-accent: #dc2626; }

        .portal-sum-card__head {
            display: flex;
            align-items: center;
            gap: 0.48rem;
            min-width: 0;
        }

        .portal-sum-card__ico-wrap {
            width: 2.05rem;
            height: 2.05rem;
            border-radius: 0.55rem;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            font-size: 0.95rem;
            color: var(--sum-accent);
            background: color-mix(in srgb, var(--sum-accent) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--sum-accent) 32%, transparent);
        }

        .portal-sum-card__title {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }

        .portal-sum-card__value {
            margin: 0.15rem 0 0;
            font-size: clamp(0.95rem, 2.8vw, 1.05rem);
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
            word-break: break-word;
        }

        .portal-sum-card__value--money {
            font-variant-numeric: tabular-nums;
        }

        .portal-sum-card__hint {
            margin: auto 0 0;
            padding-top: 0.35rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.38);
            font-size: 0.66rem;
            font-weight: 700;
            color: var(--muted);
            line-height: 1.45;
        }

        html[data-theme="dark"] .portal-sum-card__hint {
            border-top-color: rgba(148, 163, 184, 0.22);
        }

        .portal-loans-page {
            width: 100%;
            max-width: 100%;
        }

        .portal-loans-page__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--border);
        }

        .portal-loans-page__head-main {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .portal-loans-page__head-ico {
            font-size: 1.25rem;
            color: var(--primary-dark);
            opacity: 0.9;
        }

        .portal-loans-page__title {
            margin: 0;
            font-size: clamp(1rem, 3.8vw, 1.15rem);
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .portal-loans-page__badge {
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.28rem 0.62rem;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        html[data-theme="dark"] .portal-loans-page__badge {
            border-color: rgba(59, 130, 246, 0.35);
        }

        .portal-loans-page__empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--muted);
            background: var(--bg-card);
            border: 1px dashed var(--border);
            border-radius: 1rem;
        }

        .portal-loans-page__empty-ico {
            font-size: 2.25rem;
            margin-bottom: 0.5rem;
            opacity: 0.45;
        }

        .portal-loans-page__empty p {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .portal-loans-page__grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            align-items: stretch;
        }

        @media (min-width: 960px) {
            .portal-loans-page__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .portal-loan-board {
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 0.65rem 0.72rem 0.75rem;
            background: var(--bg-card);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            min-width: 0;
        }

        html[data-theme="dark"] .portal-loan-board {
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22);
        }

        .portal-loan-board__bar {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            justify-content: space-between;
            gap: 0.45rem;
            flex-wrap: nowrap;
        }

        .portal-loan-board__ribbon-slot {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .portal-loan-board__ribbon {
            max-width: 10.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.08rem;
            padding: 0.32rem 0.42rem 0.36rem;
            border-radius: 0.55rem;
            font-size: 0.66rem;
            font-weight: 900;
            line-height: 1.2;
            color: #fff;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
        }

        .portal-loan-board__ribbon-ico {
            font-size: 1rem;
            line-height: 1;
        }

        .portal-loan-board__ribbon-text {
            display: block;
        }

        .portal-loan-board--state-settled .portal-loan-board__ribbon {
            background: linear-gradient(145deg, #059669, #047857);
        }

        .portal-loan-board--state-revoked .portal-loan-board__ribbon {
            background: linear-gradient(145deg, #b45309, #92400e);
        }

        .portal-loan-board--state-creditor .portal-loan-board__ribbon {
            background: linear-gradient(145deg, #7c3aed, #5b21b6);
        }

        .portal-loan-board__code-card {
            flex: 0 0 auto;
            min-width: 6.5rem;
            max-width: 11rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.06rem;
            padding: 0.38rem 0.45rem;
            border-radius: 0.6rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.75);
        }

        html[data-theme="dark"] .portal-loan-board__code-card {
            background: rgba(30, 41, 59, 0.75);
        }

        .portal-loan-board__code-k {
            font-size: 0.62rem;
            font-weight: 800;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }

        .portal-loan-board__code-v {
            font-size: 0.95rem;
            font-weight: 900;
            color: var(--text);
            letter-spacing: 0.04em;
        }

        .portal-loan-board__cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
            align-items: start;
        }

        @media (max-width: 640px) {
            .portal-loan-board__cols {
                grid-template-columns: 1fr;
            }
        }

        .portal-loan-board__col-title {
            margin: 0 0 0.35rem;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }

        .portal-loan-board__col-title--sub {
            font-size: 0.88rem;
            color: var(--primary-dark);
        }

        .portal-loan-board__sep {
            border: 0;
            border-top: 1px dashed var(--border);
            margin: 0 0 0.55rem;
        }

        .portal-loan-board__sep--fine {
            border-top-width: 1px;
            opacity: 0.85;
        }

        .portal-loan-board__kv {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.38rem;
        }

        .portal-loan-board__kv-row {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
            gap: 0.35rem 0.5rem;
            align-items: baseline;
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .portal-loan-board__kv-row dt {
            margin: 0;
            font-weight: 800;
            color: var(--muted);
        }

        .portal-loan-board__kv-row dd {
            margin: 0;
            font-weight: 700;
            color: var(--text);
            text-align: end;
            min-width: 0;
            word-break: break-word;
        }

        .portal-loan-board__kv-row--emph dd {
            font-weight: 800;
            color: var(--text);
        }

        .portal-loan-board__kv-strong {
            display: block;
            font-weight: 800;
        }

        .portal-loan-board__kv-note {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--muted);
            margin-top: 0.12rem;
        }

        .portal-loan-board__val-ltr {
            direction: ltr;
            display: inline-block;
            unicode-bidi: isolate;
        }

        .portal-loan-board__footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.15rem;
            padding-top: 0.55rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.38);
        }

        html[data-theme="dark"] .portal-loan-board__footer {
            border-top-color: rgba(148, 163, 184, 0.22);
        }

        @media (max-width: 420px) {
            .portal-loan-board__footer {
                grid-template-columns: 1fr;
            }
        }

        .portal-loan-board__footer .portal-loan__btn {
            width: 100%;
            justify-content: center;
            min-height: 2.45rem;
        }

        .portal-dialog__inner--wide {
            max-height: min(88vh, 38rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .portal-loans-inst__sub {
            margin: 0 0 0.55rem;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--muted);
            line-height: 1.45;
        }

        .portal-loans-inst__scroll {
            flex: 1;
            min-height: 0;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            background: rgba(248, 250, 252, 0.5);
        }

        html[data-theme="dark"] .portal-loans-inst__scroll {
            background: rgba(15, 23, 42, 0.35);
        }

        .portal-loans-inst__tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
        }

        .portal-loans-inst__tbl th,
        .portal-loans-inst__tbl td {
            padding: 0.5rem 0.45rem;
            border-bottom: 1px solid var(--border);
            text-align: start;
            vertical-align: top;
        }

        .portal-loans-inst__tbl th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--primary-soft);
            font-weight: 800;
            color: var(--text);
            white-space: nowrap;
        }

        html[data-theme="dark"] .portal-loans-inst__tbl th {
            background: rgba(30, 41, 59, 0.95);
        }

        .portal-loans-inst__tbl td {
            color: var(--muted);
            font-weight: 600;
        }

        .portal-loans-inst__cell-late {
            max-width: 14rem;
            white-space: normal;
            word-break: break-word;
        }

        .portal-loans-inst__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }

        .portal-loans-inst__locked {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--muted);
        }

        .portal-loans-inst__desktop-table {
            display: block;
        }

        .portal-loans-inst-cards {
            display: none;
        }

        .portal-loans-inst-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 0.65rem 0.72rem 0.72rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }

        html[data-theme="dark"] .portal-loans-inst-card {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
        }

        .portal-loans-inst-card__head {
            margin-bottom: 0.45rem;
            padding-bottom: 0.4rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.45);
        }

        html[data-theme="dark"] .portal-loans-inst-card__head {
            border-bottom-color: rgba(148, 163, 184, 0.25);
        }

        .portal-loans-inst-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--text);
        }

        .portal-loans-inst-card__kv {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.42rem;
        }

        .portal-loans-inst-card__kv-row {
            display: grid;
            grid-template-columns: minmax(0, 5.8rem) minmax(0, 1fr);
            gap: 0.35rem 0.55rem;
            align-items: start;
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .portal-loans-inst-card__kv-row dt {
            margin: 0;
            font-weight: 800;
            color: var(--muted);
        }

        .portal-loans-inst-card__kv-row dd {
            margin: 0;
            font-weight: 700;
            color: var(--text);
            text-align: end;
            word-break: break-word;
        }

        .portal-loans-inst-card__foot {
            margin-top: 0.55rem;
            padding-top: 0.5rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }

        html[data-theme="dark"] .portal-loans-inst-card__foot {
            border-top-color: rgba(148, 163, 184, 0.25);
        }

        .portal-loans-inst-card__foot .portal-loans-inst__actions {
            flex-direction: column;
            width: 100%;
            gap: 0.45rem;
        }

        .portal-loans-inst-card__foot .portal-loan__btn--table {
            width: 100%;
            justify-content: center;
            min-height: 2.4rem;
            font-size: 0.76rem;
            white-space: normal;
        }

        @media (max-width: 720px) {
            .portal-loans-inst__desktop-table {
                display: none !important;
            }

            .portal-loans-inst-cards {
                display: flex;
                flex-direction: column;
                gap: 0.65rem;
                padding: 0.55rem 0.5rem 0.65rem;
            }

            .portal-loans-inst__scroll {
                border-radius: 0.55rem;
            }
        }

        .portal-dialog {
            max-width: min(100vw - 1.5rem, 22rem);
            width: 100%;
            border: none;
            border-radius: 1rem;
            padding: 0;
            background: var(--bg-card);
            color: var(--text);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
        }

        .portal-dialog.portal-dialog--wide {
            max-width: min(100vw - 0.75rem, 52rem);
            width: 100%;
        }

        .portal-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }

        html[data-theme="dark"] .portal-dialog::backdrop {
            background: rgba(0, 0, 0, 0.55);
        }

        .portal-dialog__inner {
            position: relative;
            padding: 1.1rem 1rem 1rem;
        }

        .portal-dialog__close {
            position: absolute;
            top: 0.45rem;
            inset-inline-end: 0.45rem;
            width: 2rem;
            height: 2rem;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            border-radius: 0.4rem;
        }

        .portal-dialog__close:hover {
            background: var(--primary-soft);
            color: var(--text);
        }

        .portal-dialog__title {
            margin: 0 0 0.65rem;
            padding-inline-end: 1.75rem;
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .portal-dialog__title i {
            color: var(--primary-dark);
        }

        .portal-dialog__lead {
            margin: 0 0 0.25rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
        }

        .portal-dialog__lead--muted {
            margin-top: 0.65rem;
        }

        .portal-dialog__amount {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--primary-dark);
            letter-spacing: -0.02em;
        }

        .portal-dialog__sub {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text);
        }

        .portal-dialog__hint {
            margin: 0.65rem 0 0;
            font-size: 0.72rem;
            line-height: 1.55;
            color: var(--muted);
        }

        .portal-dialog__actions {
            margin-top: 0.85rem;
        }
    </style>
    @stack('head')
</head>
<body class="up-app">
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

    <div class="up-drawer-backdrop" id="up-drawer-backdrop" aria-hidden="true"></div>

    <div class="up-layout">
        <aside id="up-drawer" class="up-sidebar" aria-label="منوی پنل کاربری">
            <div class="sidebar-brand only-desktop">
                <div class="sidebar-logo" aria-hidden="true">
                    @if(!empty($appIconUrl))
                        <img src="{{ $appIconUrl }}" alt="">
                    @else
                        <i class="{{ $appIconFaClass }}"></i>
                    @endif
                </div>
                <div class="sidebar-title">{{ $appDisplayName }}</div>
            </div>

            <nav class="sidebar-nav" aria-label="اصلی">
                <div class="nav-section-label">منوی کاربری</div>
                @php($upNav = [
                    ['label' => 'داشبورد', 'href' => route('user.dashboard'), 'icon' => 'fa-gauge-high', 'route' => 'user.dashboard'],
                    ['label' => 'لیست وام‌ها', 'href' => route('user.loans.index'), 'icon' => 'fa-file-invoice-dollar', 'route' => 'user.loans.index'],
                    ['label' => 'اعلام واریزی‌ها', 'href' => route('user.deposits.index'), 'icon' => 'fa-money-bill-transfer', 'route' => 'user.deposits.index'],
                    ['label' => 'درخواست وام', 'href' => route('user.loan-request'), 'icon' => 'fa-hand-holding-dollar', 'route' => 'user.loan-request'],
                ])
                @foreach ($upNav as $item)
                    <a
                        href="{{ $item['href'] }}"
                        class="up-nav-link js-up-drawer-nav-link @if(request()->routeIs($item['route'])) is-active @endif"
                    >
                        <i class="fa-solid {{ $item['icon'] }} nav-ico" aria-hidden="true"></i>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="sidebar-foot">
                <div class="up-wallet-card" role="region" aria-label="موجودی کیف پول">
                    <div class="up-wallet-label"><i class="fa-solid fa-wallet" aria-hidden="true"></i> موجودی کیف پول</div>
                    <div class="up-wallet-amount">{{ $customerWalletBalanceFormatted }}<span class="up-wallet-currency">تومان</span></div>
                </div>
            </div>

            <div class="drawer-extra only-mobile">
                <div class="drawer-extra-label">وضعیت</div>
                <div class="drawer-date-row">
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>{{ $todayFormatted }}</span>
                </div>
            </div>
        </aside>

        <div class="up-column">
            <header class="mobile-topbar only-mobile" role="banner">
                <button
                    type="button"
                    id="up-mobile-nav-toggle"
                    class="mobile-nav-toggle"
                    aria-controls="up-drawer"
                    aria-expanded="false"
                    aria-label="باز و بسته کردن منو"
                >
                    <i class="fa-solid fa-bars mobile-nav-toggle__ico mobile-nav-toggle__ico--bars" aria-hidden="true"></i>
                    <i class="fa-solid fa-xmark mobile-nav-toggle__ico mobile-nav-toggle__ico--close" aria-hidden="true"></i>
                </button>
                <h1 class="mobile-app-title">{{ $appDisplayName }}</h1>
                <div class="mobile-topbar__actions">
                    @if(auth()->guard('customer')->check())
                        <span class="up-notif-wrap">
                            <button
                                type="button"
                                class="mobile-topbar-btn"
                                title="اعلان‌ها"
                                aria-label="اعلان‌ها"
                                aria-expanded="false"
                                aria-haspopup="dialog"
                                aria-controls="up-notif-flyout"
                                data-up-notif-toggle
                            >
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                @if(($userPortalDepositReviewNotifBadge ?? '') !== '')
                                    <span class="up-notif-badge" aria-hidden="true">{{ $userPortalDepositReviewNotifBadge }}</span>
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
                    <form class="mobile-topbar__logout-form" method="post" action="{{ route('user.logout') }}" data-up-logout-form>
                        @csrf
                        <button
                            type="submit"
                            class="mobile-topbar-btn mobile-topbar-btn--logout"
                            title="خروج"
                            aria-label="خروج از حساب کاربری"
                        >
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </header>

            <header class="topbar only-desktop">
                <div class="topbar-cluster">
                    <span class="icon-btn icon-btn--static" title="حساب شما" aria-hidden="true" style="cursor:default">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                    </span>
                    <span class="up-user-chip" title="{{ $portalCustomerDisplayName }}">{{ $portalCustomerDisplayName }}</span>
                </div>
                <div class="topbar-date">
                    <i class="fa-regular fa-calendar-days" style="margin-inline-end:0.35rem;opacity:0.85" aria-hidden="true"></i>
                    امروز: {{ $todayFormatted }}
                </div>
                <div class="topbar-cluster">
                    @if(auth()->guard('customer')->check())
                        <span class="up-notif-wrap">
                            <button
                                type="button"
                                class="icon-btn"
                                title="اعلان‌ها"
                                aria-expanded="false"
                                aria-haspopup="dialog"
                                aria-controls="up-notif-flyout"
                                data-up-notif-toggle
                            >
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                @if(($userPortalDepositReviewNotifBadge ?? '') !== '')
                                    <span class="up-notif-badge" aria-hidden="true">{{ $userPortalDepositReviewNotifBadge }}</span>
                                @endif
                            </button>
                        </span>
                    @endif
                    <button type="button" class="icon-btn" title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره" data-myghest-theme-toggle>
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    <form class="logout-form" method="post" action="{{ route('user.logout') }}" data-up-logout-form>
                        @csrf
                        <button type="submit">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            خروج
                        </button>
                    </form>
                </div>
            </header>

            <div class="content-wrap">
                @yield('content')
            </div>
        </div>
    </div>

    @if(auth()->guard('customer')->check())
        @php($upDepNotifCount = (int) ($userPortalDepositReviewNotifCount ?? 0))
        <div id="up-notif-overlay" class="up-notif-overlay" hidden aria-hidden="true"></div>
        <div
            id="up-notif-flyout"
            class="up-notif-flyout"
            hidden
            role="dialog"
            aria-modal="true"
            aria-labelledby="up-notif-flyout-title"
        >
            <div id="up-notif-flyout-title" class="up-notif-flyout__head">اعلان‌ها</div>
            <div class="up-notif-flyout__body">
                @if($upDepNotifCount === 0)
                    <p class="up-notif-empty">اعلان فعالی وجود ندارد.</p>
                @else
                    <a href="{{ route('user.deposits.index') }}" class="up-notif-card">
                        <span class="up-notif-card__ico" aria-hidden="true">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </span>
                        <span class="up-notif-card__text">{{ $userPortalDepositReviewNotifMessage }}</span>
                        <span class="up-notif-card__cta">
                            رفتن به اعلام واریزی‌ها
                            <i class="fa-solid fa-chevron-left" style="font-size:0.72rem;opacity:0.85" aria-hidden="true"></i>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @endif

    <script>
        (function () {
            var hasUploadedFavicon = @json(!empty($faviconUrl));
            var faviconFaClass = @json($faviconFaClass ?? 'fa-solid fa-globe');
            if (hasUploadedFavicon || !faviconFaClass) return;
            function applyFontAwesomeFavicon() {
                var iconProbe = document.createElement('i');
                iconProbe.className = faviconFaClass;
                iconProbe.style.position = 'absolute';
                iconProbe.style.left = '-9999px';
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
                } else { ctx.fillRect(0, 0, 64, 64); }
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
        })();
    </script>
    @include('layouts.partials.theme-toggle-script')
    <script>
        (function () {
            var mq = window.matchMedia('(max-width: 960px)');
            var notifFlyout = document.getElementById('up-notif-flyout');
            var notifOverlay = document.getElementById('up-notif-overlay');

            function closeUpNotif() {
                if (!notifFlyout || notifFlyout.hidden) return;
                notifFlyout.hidden = true;
                notifFlyout.setAttribute('aria-hidden', 'true');
                if (notifOverlay) {
                    notifOverlay.hidden = true;
                    notifOverlay.setAttribute('aria-hidden', 'true');
                }
                document.querySelectorAll('[data-up-notif-toggle]').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
            }

            function positionUpNotif(anchorBtn) {
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

            function openUpNotif(anchorBtn) {
                if (!notifFlyout) return;
                positionUpNotif(anchorBtn);
                notifFlyout.hidden = false;
                notifFlyout.setAttribute('aria-hidden', 'false');
                if (notifOverlay) {
                    notifOverlay.hidden = false;
                    notifOverlay.setAttribute('aria-hidden', 'false');
                }
                document.querySelectorAll('[data-up-notif-toggle]').forEach(function (b) {
                    b.setAttribute('aria-expanded', b === anchorBtn ? 'true' : 'false');
                });
            }

            function toggleUpNotif(anchorBtn) {
                if (!notifFlyout) return;
                if (!notifFlyout.hidden) {
                    var cur = document.querySelector('[data-up-notif-toggle][aria-expanded="true"]');
                    if (cur === anchorBtn) {
                        closeUpNotif();
                        return;
                    }
                }
                openUpNotif(anchorBtn);
            }

            if (notifFlyout) {
                document.querySelectorAll('[data-up-notif-toggle]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        toggleUpNotif(btn);
                    });
                });
                if (notifOverlay) {
                    notifOverlay.addEventListener('click', closeUpNotif);
                }
                document.addEventListener('click', function (e) {
                    if (notifFlyout.hidden) return;
                    if (notifFlyout.contains(e.target)) return;
                    if (e.target.closest && e.target.closest('[data-up-notif-toggle]')) return;
                    closeUpNotif();
                });
                window.addEventListener('resize', function () {
                    if (notifFlyout.hidden) return;
                    var openBtn = document.querySelector('[data-up-notif-toggle][aria-expanded="true"]');
                    if (openBtn) positionUpNotif(openBtn);
                });
            }

            function closeDrawer() {
                var root = document.body;
                var aside = document.getElementById('up-drawer');
                var bd = document.getElementById('up-drawer-backdrop');
                var btn = document.getElementById('up-mobile-nav-toggle');
                root.classList.remove('up-drawer-open');
                if (aside) aside.classList.remove('is-open');
                if (bd) {
                    bd.classList.remove('is-visible');
                    bd.setAttribute('aria-hidden', 'true');
                }
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
            function openDrawer() {
                if (!mq.matches) return;
                var root = document.body;
                var aside = document.getElementById('up-drawer');
                var bd = document.getElementById('up-drawer-backdrop');
                var btn = document.getElementById('up-mobile-nav-toggle');
                root.classList.add('up-drawer-open');
                if (aside) aside.classList.add('is-open');
                if (bd) {
                    bd.setAttribute('aria-hidden', 'false');
                    bd.classList.add('is-visible');
                }
                if (btn) btn.setAttribute('aria-expanded', 'true');
            }
            function toggleDrawer() {
                var aside = document.getElementById('up-drawer');
                if (aside && aside.classList.contains('is-open')) closeDrawer();
                else openDrawer();
            }
            document.getElementById('up-mobile-nav-toggle')?.addEventListener('click', toggleDrawer);
            document.getElementById('up-drawer-backdrop')?.addEventListener('click', closeDrawer);
            document.querySelectorAll('.js-up-drawer-nav-link').forEach(function (a) {
                a.addEventListener('click', function () { if (mq.matches) closeDrawer(); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (notifFlyout && !notifFlyout.hidden) {
                    closeUpNotif();
                    return;
                }
                if (mq.matches) closeDrawer();
            });

            var logoutConfirmText = 'شما در حال خروج از سامانه هستید. مطمئنید؟';
            document.querySelectorAll('form[data-up-logout-form]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (form.dataset.upLogoutConfirmed === '1') return;
                    e.preventDefault();
                    function doSubmit() {
                        form.dataset.upLogoutConfirmed = '1';
                        if (mq.matches) closeDrawer();
                        if (notifFlyout && !notifFlyout.hidden) closeUpNotif();
                        form.submit();
                    }
                    function fallbackConfirm() {
                        if (window.confirm(logoutConfirmText)) doSubmit();
                    }
                    if (!window.AdminSwal || typeof AdminSwal.confirm !== 'function') {
                        fallbackConfirm();
                        return;
                    }
                    AdminSwal.confirm({
                        icon: 'warning',
                        title: 'خروج از سامانه',
                        text: logoutConfirmText,
                        confirmButtonText: 'بله، خارج شو',
                        cancelButtonText: 'انصراف',
                        confirmButtonColor: '#dc2626',
                        focusCancel: true,
                    }).then(function (res) {
                        if (res && res.isConfirmed) doSubmit();
                    }).catch(fallbackConfirm);
                });
            });
            function onMqChange(ev) { if (!ev.matches) closeDrawer(); }
            if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onMqChange);
            else if (typeof mq.addListener === 'function') mq.addListener(onMqChange);
        })();
    </script>
    @include('layouts.partials.sweetalert2-css')
    @include('layouts.partials.sweetalert2-init')
    @stack('scripts')
</body>
</html>
