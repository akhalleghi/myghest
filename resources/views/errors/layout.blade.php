<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="light" data-admin-font="{{ $appFontSize ?? 'normal' }}" data-admin-ui-font="{{ $appUiFont ?? 'iransans' }}">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.theme-boot')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if(!empty($faviconUrl))
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" href="data:,">
    @endif
    <title>@yield('error_title', 'خطا') — {{ $appDisplayName ?? config('app.name') }}</title>
    @include('layouts.partials.admin-ui-font-assets')
    @include('layouts.partials.admin-ui-font-style')
    @include('layouts.partials.fontawesome-local')
    <style>
        :root {
            --err-bg-top: #e8f0ff;
            --err-bg-bottom: #f4f8ff;
            --err-card: #ffffff;
            --err-text: #0f172a;
            --err-muted: #64748b;
            --err-border: rgba(37, 99, 235, 0.14);
            --err-accent: #2563eb;
            --err-accent-dark: #1d4ed8;
            --err-accent-soft: rgba(37, 99, 235, 0.12);
            --err-warn: #f59e0b;
            --err-warn-soft: rgba(245, 158, 11, 0.14);
            --err-shadow: 0 28px 60px -18px rgba(15, 23, 42, 0.18);
            --err-glow: rgba(37, 99, 235, 0.35);
        }

        html[data-theme="dark"] {
            --err-bg-top: #0b1220;
            --err-bg-bottom: #0f172a;
            --err-card: #151d2e;
            --err-text: #e2e8f0;
            --err-muted: #94a3b8;
            --err-border: rgba(59, 130, 246, 0.22);
            --err-accent: #3b82f6;
            --err-accent-dark: #60a5fa;
            --err-accent-soft: rgba(59, 130, 246, 0.2);
            --err-warn: #fbbf24;
            --err-warn-soft: rgba(251, 191, 36, 0.16);
            --err-shadow: 0 28px 60px -18px rgba(0, 0, 0, 0.55);
            --err-glow: rgba(59, 130, 246, 0.4);
        }

        html[data-admin-font="small"] { font-size: 15px; }
        html[data-admin-font="normal"] { font-size: 16px; }
        html[data-admin-font="large"] { font-size: 18px; }
        html[data-admin-font="xlarge"] { font-size: 20px; }

        * { box-sizing: border-box; }

        body.http-error-body {
            margin: 0;
            min-height: 100vh;
            color: var(--err-text);
            background: linear-gradient(165deg, var(--err-bg-top) 0%, var(--err-bg-bottom) 48%, var(--err-bg-top) 100%);
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .http-error-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .http-error-bg__orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(72px);
            opacity: 0.55;
            animation: err-float 14s ease-in-out infinite;
        }

        .http-error-bg__orb--1 {
            width: min(42vw, 320px);
            height: min(42vw, 320px);
            top: -8%;
            inset-inline-start: -6%;
            background: var(--err-accent);
        }

        .http-error-bg__orb--2 {
            width: min(36vw, 280px);
            height: min(36vw, 280px);
            bottom: -10%;
            inset-inline-end: -4%;
            background: var(--err-warn);
            animation-delay: -4s;
        }

        .http-error-bg__orb--3 {
            width: min(28vw, 200px);
            height: min(28vw, 200px);
            top: 42%;
            inset-inline-start: 38%;
            background: #8b5cf6;
            opacity: 0.28;
            animation-delay: -7s;
        }

        @keyframes err-float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(12px, -18px) scale(1.06); }
        }

        .http-error-topbar {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem clamp(1rem, 4vw, 2.5rem);
        }

        .http-error-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: var(--err-text);
            font-weight: 800;
            font-size: 0.95rem;
        }

        .http-error-brand__mark {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--err-accent), var(--err-accent-dark));
            color: #fff;
            box-shadow: 0 8px 20px -6px var(--err-glow);
        }

        .http-error-brand__mark img {
            width: 1.35rem;
            height: 1.35rem;
            object-fit: contain;
        }

        .http-error-panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--err-accent-dark);
            background: var(--err-accent-soft);
            border: 1px solid var(--err-border);
        }

        html[data-theme="dark"] .http-error-panel-badge {
            color: #bfdbfe;
        }

        .http-error-main {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem clamp(1rem, 4vw, 2.5rem) 2.5rem;
        }

        .http-error-card {
            width: min(100%, 34rem);
            background: var(--err-card);
            border: 1px solid var(--err-border);
            border-radius: 1.35rem;
            box-shadow: var(--err-shadow);
            padding: clamp(1.75rem, 5vw, 2.5rem);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .http-error-card::before {
            content: '';
            position: absolute;
            inset-inline: 0;
            top: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--err-accent), var(--err-warn), var(--err-accent));
        }

        .http-error-visual {
            position: relative;
            margin: 0 auto 1.25rem;
            width: 7.5rem;
            height: 7.5rem;
        }

        .http-error-visual__ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px dashed var(--err-border);
            animation: err-spin 22s linear infinite;
        }

        @keyframes err-spin {
            to { transform: rotate(360deg); }
        }

        .http-error-visual__icon {
            position: absolute;
            inset: 0.85rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
            background: linear-gradient(145deg, var(--err-accent), var(--err-accent-dark));
            box-shadow: 0 14px 32px -10px var(--err-glow);
        }

        .http-error-code {
            position: absolute;
            left: 50%;
            bottom: -0.15rem;
            transform: translateX(-50%);
            font-size: 2.75rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
            background: linear-gradient(135deg, var(--err-accent) 20%, var(--err-warn) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 2px 8px rgba(37, 99, 235, 0.2));
        }

        .http-error-title {
            margin: 0 0 0.65rem;
            font-size: clamp(1.25rem, 3.5vw, 1.55rem);
            font-weight: 800;
        }

        .http-error-message {
            margin: 0 auto 1.5rem;
            max-width: 26rem;
            color: var(--err-muted);
            font-size: 0.95rem;
        }

        .http-error-hint {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            text-align: start;
            margin: 0 0 1.5rem;
            padding: 0.85rem 1rem;
            border-radius: 0.85rem;
            background: var(--err-warn-soft);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: var(--err-text);
            font-size: 0.84rem;
        }

        .http-error-hint i {
            color: var(--err-warn);
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .http-error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
        }

        .http-error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 2.65rem;
            padding: 0 1.15rem;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: inherit;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .http-error-btn:hover {
            transform: translateY(-1px);
        }

        .http-error-btn--primary {
            background: linear-gradient(180deg, var(--err-accent), var(--err-accent-dark));
            color: #fff;
            box-shadow: 0 10px 24px -10px var(--err-glow);
        }

        .http-error-btn--ghost {
            background: transparent;
            color: var(--err-text);
            border-color: var(--err-border);
        }

        .http-error-foot {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 0 1rem 1.25rem;
            font-size: 0.78rem;
            color: var(--err-muted);
        }

        @media (max-width: 480px) {
            .http-error-code { font-size: 2.25rem; }
            .http-error-actions { flex-direction: column; }
            .http-error-btn { width: 100%; }
        }
    </style>
    @stack('error_styles')
</head>
<body class="http-error-body @yield('error_body_class')">
    <div class="http-error-bg" aria-hidden="true">
        <span class="http-error-bg__orb http-error-bg__orb--1"></span>
        <span class="http-error-bg__orb http-error-bg__orb--2"></span>
        <span class="http-error-bg__orb http-error-bg__orb--3"></span>
    </div>

    <header class="http-error-topbar">
        <a href="@yield('error_home_url', url('/'))" class="http-error-brand">
            <span class="http-error-brand__mark">
                @if(!empty($appIconUrl))
                    <img src="{{ $appIconUrl }}" alt="">
                @else
                    <i class="{{ $appIconFaClass ?? 'fa-solid fa-layer-group' }}" aria-hidden="true"></i>
                @endif
            </span>
            <span>{{ $appDisplayName ?? config('app.name') }}</span>
        </a>
        @if (! empty($errorPanelBadge))
            <span class="http-error-panel-badge">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                {{ $errorPanelBadge }}
            </span>
        @endif
    </header>

    <main class="http-error-main">
        @yield('error_content')
    </main>

    <footer class="http-error-foot">
        @yield('error_footer', 'در صورت نیاز با مدیر سامانه تماس بگیرید.')
    </footer>
</body>
</html>
