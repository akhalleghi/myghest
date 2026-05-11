<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" data-theme="light" data-admin-font="{{ $appFontSize }}" data-admin-ui-font="{{ $appUiFont }}">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.theme-boot')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if(!empty($faviconUrl))
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" href="data:,">
        <link rel="shortcut icon" href="data:,">
    @endif
    <title>@yield('title', 'ورود مدیر') — {{ $appDisplayName }}</title>
    @include('layouts.partials.admin-ui-font-assets')
    @include('layouts.partials.admin-ui-font-style')
    @include('layouts.partials.fontawesome-local')
    <style>
        :root {
            --page-top: #e8f4ff;
            --page-bottom: #f8fbff;
            --surface: #ffffff;
            --border-soft: rgba(37, 99, 235, 0.12);
            --border-strong: rgba(37, 99, 235, 0.22);
            --text-main: #0f172a;
            --muted: rgba(51, 65, 85, 0.88);
            --accent: #2563eb;
            --accent-strong: #1d4ed8;
            --input-bg: #f8fafc;
            --ring: rgba(37, 99, 235, 0.32);
            --danger-fill: rgba(254, 242, 242, 0.95);
            --danger-border: rgba(248, 113, 113, 0.55);
            --danger-text: #7f1d1d;
            --brand-heading: #0b1220;
            --placeholder: rgba(100, 116, 139, 0.5);
            --captcha-bg: #f1f7ff;
            --remember: rgba(51, 65, 85, 0.95);
            --help-color: rgba(100, 116, 139, 0.88);
        }

        html[data-theme="dark"] {
            --page-top: #0f172a;
            --page-bottom: #020617;
            --surface: #151d2e;
            --border-soft: rgba(59, 130, 246, 0.22);
            --border-strong: rgba(96, 165, 250, 0.28);
            --text-main: #e2e8f0;
            --muted: rgba(148, 163, 184, 0.95);
            --accent: #3b82f6;
            --accent-strong: #2563eb;
            --input-bg: #0f172a;
            --ring: rgba(59, 130, 246, 0.35);
            --danger-fill: rgba(127, 29, 29, 0.25);
            --danger-border: rgba(248, 113, 113, 0.4);
            --danger-text: #fecaca;
            --brand-heading: #f1f5f9;
            --placeholder: rgba(148, 163, 184, 0.45);
            --captcha-bg: #1e293b;
            --remember: rgba(226, 232, 240, 0.92);
            --help-color: rgba(148, 163, 184, 0.9);
        }

        * { box-sizing: border-box; }

        html[data-admin-font="small"] { font-size: 15px; }
        html[data-admin-font="normal"] { font-size: 16px; }
        html[data-admin-font="large"] { font-size: 18px; }
        html[data-admin-font="xlarge"] { font-size: 20px; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text-main);
            background:
                radial-gradient(900px 520px at 12% -8%, rgba(59, 130, 246, 0.2), transparent 55%),
                radial-gradient(640px 400px at 108% 6%, rgba(14, 165, 233, 0.12), transparent),
                linear-gradient(180deg, var(--page-top), var(--page-bottom));
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.75rem 1rem;
            line-height: 1.55;
        }

        html[data-theme="dark"] body {
            background:
                radial-gradient(900px 520px at 12% -8%, rgba(37, 99, 235, 0.12), transparent 55%),
                radial-gradient(640px 400px at 108% 6%, rgba(14, 165, 233, 0.06), transparent),
                linear-gradient(180deg, var(--page-top), var(--page-bottom));
        }

        .auth-theme-fab {
            position: fixed;
            top: 1rem;
            inset-inline-start: 1rem;
            z-index: 50;
            width: 2.55rem;
            height: 2.55rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-strong);
            background: var(--surface);
            color: var(--accent);
            cursor: pointer;
            display: grid;
            place-items: center;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.1);
            font-size: 1.05rem;
            transition: filter 0.12s ease, border-color 0.12s ease;
        }

        html[data-theme="dark"] .auth-theme-fab {
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }

        .auth-theme-fab:hover {
            filter: brightness(1.05);
        }

        .auth-theme-slot {
            display: inline-grid;
            place-items: center;
            width: 1.15rem;
            height: 1.15rem;
        }

        .auth-theme-slot [data-theme-icon] {
            grid-area: 1 / 1;
        }

        .shell {
            width: 100%;
            max-width: 418px;
        }

        .card {
            position: relative;
            border-radius: 1.2rem;
            padding: 1.85rem 1.72rem;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            box-shadow:
                0 22px 50px rgba(15, 23, 42, 0.08),
                0 10px 24px rgba(37, 99, 235, 0.08);
            overflow: hidden;
        }

        html[data-theme="dark"] .card {
            box-shadow:
                0 22px 50px rgba(0, 0, 0, 0.35),
                0 10px 24px rgba(37, 99, 235, 0.08);
        }

        .card > * {
            position: relative;
            z-index: 1;
        }

        .accent-bar {
            position: absolute;
            inset-inline-start: 0;
            inset-block: 12px;
            width: 4px;
            border-radius: 99px;
            background: linear-gradient(180deg, #60a5fa, var(--accent));
            opacity: 0.85;
        }

        .brand {
            margin-bottom: 1.36rem;
        }

        .brand .lead {
            margin-top: 0.42rem;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .brand-ico {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            background: linear-gradient(145deg, var(--accent), var(--accent-strong));
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.08rem;
            line-height: 1;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
        }

        .brand-row > div:last-child {
            min-width: 0;
            flex: 1 1 auto;
        }

        .brand > h1,
        .brand .brand-row h1 {
            margin: 0;
            font-size: 1.22rem;
            font-weight: 750;
            letter-spacing: -0.02em;
            line-height: 1.35;
            color: var(--brand-heading);
        }

        .lead {
            margin: 0.32rem 0 0;
            font-size: 0.86rem;
            color: var(--muted);
            max-width: 30em;
        }

        .alert {
            margin-top: 1.05rem;
            padding: 0.72rem 0.92rem;
            border-radius: 0.82rem;
            border: 1px solid var(--danger-border);
            background: var(--danger-fill);
            font-size: 0.848rem;
        }

        .alert ul {
            margin: 0;
            padding-inline-start: 1.05rem;
            color: var(--danger-text);
        }

        label {
            display: flex;
            align-items: center;
            gap: 0.42rem;
            margin-top: 1.02rem;
            margin-bottom: 0.42rem;
            font-size: 0.78rem;
            font-weight: 650;
            color: var(--muted);
        }

        label .lbl-ico {
            color: var(--accent);
            font-size: 0.82rem;
            opacity: 0.95;
            width: 1rem;
            text-align: center;
            flex-shrink: 0;
        }

        label:first-of-type { margin-top: 1.06rem; }

        input[type="text"],
        input[type="password"],
        input[type="tel"],
        input[type="email"],
        input[type="search"],
        input[type="url"] {
            width: 100%;
            padding: 0.72rem 0.92rem;
            border-radius: 0.82rem;
            border: 1px solid var(--border-soft);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 0.96rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input::placeholder {
            color: var(--placeholder);
        }

        input:focus {
            outline: none;
            border-color: rgba(37, 99, 235, 0.45);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .captcha-line {
            display: flex;
            flex-direction: row;
            gap: 0.72rem;
            align-items: stretch;
            flex-wrap: wrap;
            margin-top: 0.48rem;
            justify-content: flex-start;
        }

        .captcha-line .captcha-input {
            margin-top: 0;
            width: auto;
            min-width: min(100%, 8.75rem);
            max-width: 11.5rem;
            flex: 1 1 8.75rem;
            min-height: 48px;
            box-sizing: border-box;
            text-align: center;
            font-size: 1.02rem;
            letter-spacing: 0.08em;
        }

        .captcha-line img {
            flex-shrink: 0;
            height: 48px;
            border-radius: 0.82rem;
            border: 1px solid rgba(148, 163, 184, 0.5);
            cursor: pointer;
            background: var(--captcha-bg);
            user-select: none;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
            image-rendering: pixelated;
        }

        html[data-theme="dark"] .captcha-line img {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.25);
        }

        .captcha-line img:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        .help {
            margin: 0.48rem 0 0;
            font-size: 0.74rem;
            color: var(--help-color);
            text-align: start;
            display: flex;
            gap: 0.35rem;
            align-items: flex-start;
        }

        .help i {
            margin-top: 0.12rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 1.02rem;
            font-size: 0.848rem;
            color: var(--remember);
            user-select: none;
            cursor: pointer;
            font-weight: 550;
        }

        .remember input {
            accent-color: var(--accent);
            cursor: pointer;
            width: 1.05rem;
            height: 1.05rem;
        }

        button[type="submit"] {
            width: 100%;
            margin-top: 1.32rem;
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 0.82rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.96rem;
            letter-spacing: 0.025em;
            color: #fff;
            font-family: inherit;
            background: linear-gradient(180deg, var(--accent) 0%, var(--accent-strong) 110%);
            box-shadow:
                0 14px 32px rgba(37, 99, 235, 0.32),
                0 2px 0 rgba(255, 255, 255, 0.15) inset;
            transition: transform 0.08s ease, filter 0.12s ease, box-shadow 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        button[type="submit"]:hover {
            filter: brightness(1.025);
            box-shadow:
                0 17px 36px rgba(37, 99, 235, 0.36),
                0 2px 0 rgba(255, 255, 255, 0.18) inset;
        }

        button[type="submit"]:active {
            transform: translateY(1px);
            filter: brightness(0.98);
        }
    </style>
    @stack('head')
</head>
<body>
    <button type="button" class="auth-theme-fab" id="myghest-theme-toggle" data-myghest-theme-toggle title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره">
        <span class="auth-theme-slot" aria-hidden="true">
            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
        </span>
    </button>
    <div class="shell">
        <main class="card">
            <span class="accent-bar" aria-hidden="true"></span>
            @yield('content')
        </main>
        @stack('portals')
    </div>
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
        })();
    </script>
    @include('layouts.partials.theme-toggle-script')
    @include('layouts.partials.sweetalert2-auth-assets')
    @stack('scripts')
    @yield('scripts')
</body>
</html>
