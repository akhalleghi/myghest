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
            .mobile-topbar { display: flex; align-items: center; gap: 0.65rem; padding: 0.55rem 0.85rem; background: var(--topbar-bg); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 40; min-height: var(--mobile-topbar-h); }
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
            }
            .mobile-nav-toggle__ico { grid-area: 1 / 1; }
            .mobile-nav-toggle__ico--close { display: none; }
            .up-drawer-open .mobile-nav-toggle__ico--bars { display: none; }
            .up-drawer-open .mobile-nav-toggle__ico--close { display: block; }
            .mobile-app-title { margin: 0; font-size: clamp(0.88rem, 3.9vw, 1.06rem); font-weight: 800; color: var(--topbar-date); flex: 1; text-align: start; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .drawer-extra.only-mobile { display: flex !important; flex-direction: column; }
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
    </style>
    @stack('head')
</head>
<body class="up-app">
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
                <div class="drawer-extra-label">نوار ابزار</div>
                <div class="drawer-actions">
                    <button type="button" class="icon-btn" title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره" data-myghest-theme-toggle>
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    <form class="logout-form" method="post" action="{{ route('user.logout') }}">
                        @csrf
                        <button type="submit">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            خروج
                        </button>
                    </form>
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
                    <button type="button" class="icon-btn" title="حالت روشن / تیره" aria-label="تغییر حالت روشن و تیره" data-myghest-theme-toggle>
                        <span class="theme-ico-slot" aria-hidden="true">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun" style="display:none"></i>
                        </span>
                    </button>
                    <form class="logout-form" method="post" action="{{ route('user.logout') }}">
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
                if (e.key === 'Escape' && mq.matches) closeDrawer();
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
