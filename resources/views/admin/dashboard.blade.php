@extends('layouts.admin.app')

@section('title', 'داشبورد')

@push('head')
    <style>
        .dash-h1 {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text);
        }
        .dash-sub {
            margin: 0 0 1rem;
            font-size: 0.86rem;
            color: var(--muted);
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.95rem;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        html[data-theme="dark"] .card {
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.25);
        }

        .card-h {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.72rem 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--text);
        }

        .card-h-ico {
            width: 2rem;
            height: 2rem;
            border-radius: 0.55rem;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            font-size: 0.92rem;
        }

        .card-body { padding: 0.9rem 1rem 1rem; }

        .stat-sys {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 1.25rem;
            font-size: 0.84rem;
            color: var(--muted);
        }

        .stat-sys__col {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-width: 0;
        }

        @media (max-width: 820px) {
            .stat-sys {
                grid-template-columns: 1fr;
            }
        }

        .stat-sys__row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.65rem;
            padding: 0.42rem 0;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
        }

        html[data-theme="dark"] .stat-sys__row {
            border-bottom-color: rgba(148, 163, 184, 0.22);
        }

        .stat-sys__col .stat-sys__row:last-child {
            border-bottom: 0;
        }

        .stat-sys__val {
            font-weight: 800;
            font-size: 0.94rem;
            color: var(--text);
            text-align: end;
            white-space: nowrap;
        }

        .stat-sys__val-ltr {
            direction: ltr;
            display: inline-block;
            unicode-bidi: isolate;
        }

        .stat-sys__currency {
            display: inline-block;
            unicode-bidi: isolate;
            margin-inline-start: 0.2rem;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            grid-auto-rows: 1fr;
            gap: 0.65rem;
            margin-bottom: 1rem;
        }

        .quick-grid > .dash-widget {
            min-width: 0;
            display: flex;
        }

        .quick-grid > .dash-widget > .qk,
        .quick-grid > .dash-widget > .qk-link > .qk {
            height: 100%;
            width: 100%;
        }

        .quick-grid > .dash-widget > .qk-link {
            display: flex;
            width: 100%;
        }

        .charts-row > .dash-widget {
            min-width: 0;
        }

        .qk {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.75rem 0.8rem;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
            min-height: 9.4rem;
            height: 9.4rem;
            display: flex;
            flex-direction: column;
        }
        .qk-link {
            display: block;
            color: inherit;
            text-decoration: none;
            height: 100%;
        }

        html[data-theme="dark"] .qk {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .qk--clickable {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .qk--clickable:hover {
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
        }

        html[data-theme="dark"] .qk--clickable:hover {
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.25);
        }

        .qk-ico-wrap {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.45rem;
            display: grid;
            place-items: center;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .qk-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qk h3 {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }

        .qk-body {
            margin-top: 0.45rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.32rem;
            font-size: 0.74rem;
            color: var(--muted);
        }

        .qk-line,
        .qk-kv {
            line-height: 1.45;
        }

        .qk-kv {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.5rem;
        }

        .qk-kv span:first-child {
            flex-shrink: 1;
        }

        .qk-kv span:last-child {
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
        }

        .qk-val-ltr {
            direction: ltr;
            display: inline-block;
            unicode-bidi: isolate;
        }

        .qk-footer {
            margin-top: 0.55rem;
            padding-top: 0.45rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.35);
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--primary);
        }

        html[data-theme="dark"] .qk-footer {
            border-top-color: rgba(148, 163, 184, 0.22);
        }

        .tbl-wrap { overflow-x: auto; }

        table.dash-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }

        .dash-tbl th,
        .dash-tbl td {
            padding: 0.55rem 0.65rem;
            border-bottom: 1px solid var(--border);
            text-align: start;
            white-space: nowrap;
        }

        .dash-tbl th {
            background: var(--primary-soft);
            font-weight: 800;
            color: var(--text);
        }

        html[data-theme="dark"] .dash-tbl th {
            background: rgba(30, 41, 59, 0.95);
        }

        .dash-tbl td { color: var(--muted); }

        .tbl-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            font-size: 0.72rem;
            color: var(--muted);
            border-top: 1px solid var(--border);
            background: var(--primary-soft);
        }

        html[data-theme="dark"] .tbl-foot {
            background: rgba(30, 41, 59, 0.6);
        }

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        @media (max-width: 900px) {
            .charts-row { grid-template-columns: 1fr; }
        }

        .chart-card {
            border-radius: 0.9rem;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .chart-card .ch-h {
            padding: 0.65rem 0.85rem;
            font-weight: 800;
            font-size: 0.82rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .chart-card .ch-b {
            padding: 0.55rem 0.65rem 0.7rem;
            background: var(--bg-card);
        }

        .dash-line-chart {
            width: 100%;
            min-height: 12.5rem;
        }

        .dash-line-chart__wrap {
            width: 100%;
        }

        .dash-line-chart__plot {
            position: relative;
            width: 100%;
            height: 12.5rem;
            cursor: crosshair;
            touch-action: pan-y;
        }

        .dash-line-chart__svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .dash-line-chart__grid-line {
            stroke: rgba(148, 163, 184, 0.28);
            stroke-width: 1;
            vector-effect: non-scaling-stroke;
        }

        html[data-theme="dark"] .dash-line-chart__grid-line {
            stroke: rgba(148, 163, 184, 0.18);
        }

        .dash-line-chart__axis-y,
        .dash-line-chart__axis-x {
            fill: #64748b;
            font-size: 10px;
            font-family: IRANSans, Tahoma, sans-serif;
        }

        html[data-theme="dark"] .dash-line-chart__axis-y,
        html[data-theme="dark"] .dash-line-chart__axis-x {
            fill: #94a3b8;
        }

        .dash-line-chart__line {
            vector-effect: non-scaling-stroke;
            filter: drop-shadow(0 2px 6px rgba(15, 23, 42, 0.12));
        }

        html[data-theme="dark"] .dash-line-chart__line {
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.35));
        }

        .dash-line-chart__dot {
            transition: r 0.12s ease, stroke-width 0.12s ease;
        }

        .dash-line-chart__crosshair {
            vector-effect: non-scaling-stroke;
            pointer-events: none;
        }

        .dash-line-chart__tooltip {
            position: absolute;
            z-index: 5;
            min-width: 8.5rem;
            max-width: 14rem;
            padding: 0.55rem 0.65rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(var(--tip-rgb, 37, 99, 235), 0.35);
            background: var(--bg-card);
            color: var(--text);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
            pointer-events: none;
            line-height: 1.45;
        }

        html[data-theme="dark"] .dash-line-chart__tooltip {
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.42);
        }

        .dash-line-chart__tooltip::before {
            content: '';
            position: absolute;
            inset-inline-start: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            border-radius: 0.65rem 0 0 0.65rem;
            background: var(--tip-accent, var(--primary));
        }

        .dash-line-chart__tooltip-period {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 0.2rem;
        }

        .dash-line-chart__tooltip-value {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--tip-accent, var(--primary-dark));
            direction: ltr;
            text-align: end;
            unicode-bidi: isolate;
        }

        .dash-line-chart__tooltip-meta {
            margin-top: 0.28rem;
            font-size: 0.65rem;
            color: var(--muted);
            font-weight: 600;
        }

        .dash-line-chart__empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .dash-welcome-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem 1rem;
            margin-bottom: 1rem;
        }

        .dash-welcome-row .dash-h1 {
            margin: 0;
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-toolbar {
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        .dash-widget-btn {
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.45rem 0.82rem;
            border-radius: 0.68rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            transition: border-color 0.12s ease, background 0.12s ease;
        }

        .dash-widget-btn:hover {
            border-color: rgba(37, 99, 235, 0.35);
            background: var(--primary-soft);
        }

        html[data-theme="dark"] .dash-widget-btn:hover {
            border-color: rgba(59, 130, 246, 0.4);
        }

        .dash-widget-btn__label {
            display: inline;
        }

        @media (max-width: 640px) {
            .dash-welcome-row {
                flex-wrap: nowrap;
                align-items: center;
            }

            .dash-welcome-row .dash-h1 {
                font-size: clamp(0.92rem, 3.6vw, 1.1rem);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .dash-widget-btn {
                padding: 0.48rem;
                min-width: 2.6rem;
                min-height: 2.6rem;
                justify-content: center;
                gap: 0;
            }

            .dash-widget-btn__label {
                display: none;
            }

            .dash-widget-btn .fa-solid,
            .dash-widget-btn .fa-regular {
                font-size: 1.05rem;
            }
        }

        .dash-widget--hidden {
            display: none !important;
        }

        body.dash-widget-modal-open {
            overflow: hidden;
        }

        .dash-widget-overlay {
            position: fixed;
            inset: 0;
            z-index: 1200;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        html[data-theme="dark"] .dash-widget-overlay {
            background: rgba(0, 0, 0, 0.55);
        }

        .dash-widget-overlay[hidden],
        .dash-widget-overlay.hidden {
            display: none !important;
        }

        .dash-widget-dialog {
            background: var(--bg-card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 26px 64px rgba(15, 23, 42, 0.16);
            max-width: 28rem;
            width: 100%;
            max-height: min(86vh, 520px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        html[data-theme="dark"] .dash-widget-dialog {
            box-shadow: 0 26px 64px rgba(0, 0, 0, 0.45);
        }

        .dash-widget-dialog__head {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 800;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .dash-widget-dialog__close {
            border: none;
            background: var(--primary-soft);
            color: var(--primary-dark);
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.55rem;
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 1rem;
        }

        .dash-widget-dialog__body {
            overflow-y: auto;
            padding: 0.75rem 1rem 1rem;
            -webkit-overflow-scrolling: touch;
        }

        .dash-widget-group {
            margin-top: 0.65rem;
        }

        .dash-widget-group:first-child {
            margin-top: 0;
        }

        .dash-widget-group__title {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--muted);
            letter-spacing: 0.03em;
            margin-bottom: 0.42rem;
        }

        .dash-widget-choice {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.38rem 0;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            user-select: none;
        }

        .dash-widget-choice input {
            accent-color: var(--primary);
            width: 1rem;
            height: 1rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        .dash-widget-dialog__foot {
            padding: 0.65rem 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .dash-widget-foot-btn {
            font-family: inherit;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.42rem 0.75rem;
            border-radius: 0.6rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            cursor: pointer;
        }

        .dash-widget-foot-btn--primary {
            border: none;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff;
        }
    </style>
@endpush

@section('content')
    @auth('admin')
        <div class="dash-welcome-row">
            <h1 class="dash-h1">
                <i class="fa-regular fa-face-smile" style="margin-inline-end:0.35rem;opacity:0.9" aria-hidden="true"></i>
                سلام، {{ auth('admin')->user()->name }}
            </h1>
            @if (! empty($allowedDashboardWidgetIds))
            <div class="dash-toolbar">
                <button
                    type="button"
                    id="dash-open-widgets"
                    class="dash-widget-btn"
                    aria-haspopup="dialog"
                    aria-controls="dash-widget-dialog"
                    aria-label="انتخاب کارت‌های صفحه"
                    title="انتخاب کارت‌های صفحه"
                >
                    <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                    <span class="dash-widget-btn__label">انتخاب کارت‌های صفحه</span>
                </button>
            </div>
            @endif
        </div>
        <!-- <p class="dash-sub">
            نام کاربری: <strong>{{ auth('admin')->user()->username }}</strong>
            — این صفحه با دادهٔ نمونه نمایش داده می‌شود تا ماژول‌های واقعی در گام بعدی وصل شوند.
        </p> -->
    @else
        <p class="dash-sub">نشست نامعتبر است.</p>
    @endauth

    @php($dashCan = static fn (string $id): bool => ! empty($allowedDashboardWidgetIds[$id] ?? false))

    @if (empty($allowedDashboardWidgetIds))
        <div class="card" style="margin-top:1rem;padding:1.25rem 1.5rem;color:var(--muted,#64748b);">
            <p style="margin:0;">برای این حساب هیچ کارت داشبوردی تعریف نشده است. از بخش <strong>کاربران ادمین → دسترسی‌ها</strong> کارت‌های موردنظر را فعال کنید.</p>
        </div>
    @endif

    @if ($dashCan('system-stats'))
    {{-- آمار سیستم (کارت بزرگ) --}}
    <div class="dash-widget" data-dash-widget="system-stats" data-dash-title="آمار سیستم" data-dash-group="general">
        <div class="card">
            <div class="card-h">
                <span class="card-h-ico" style="background:linear-gradient(145deg,#6366f1,#4f46e5);color:#fff;">
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                </span>
                آمار سیستم
            </div>
            <div class="card-body">
                @php($systemStatChunks = array_chunk($systemStatRows ?? [], (int) ceil(max(1, count($systemStatRows ?? [])) / 2)))
                <div class="stat-sys">
                    @foreach ($systemStatChunks as $chunk)
                        <div class="stat-sys__col">
                            @foreach ($chunk as $sr)
                                <div class="stat-sys__row">
                                    <span>{{ $sr['label'] }}:</span>
                                    <span class="stat-sys__val">
                                        @if (\Illuminate\Support\Str::endsWith((string) ($sr['value'] ?? ''), ' تومان'))
                                            <span class="stat-sys__val-ltr">{{ \Illuminate\Support\Str::beforeLast((string) $sr['value'], ' تومان') }}</span><span class="stat-sys__currency"> تومان</span>
                                        @else
                                            <span class="stat-sys__val-ltr">{{ $sr['value'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- کارت‌های خلاصه --}}
    <div class="quick-grid">
        @foreach ($summaryCards ?? [] as $qk)
            @if ($dashCan($qk['widget_id'] ?? ''))
            <div
                class="dash-widget"
                data-dash-widget="{{ $qk['widget_id'] }}"
                data-dash-title="{{ $qk['title'] }}"
                data-dash-group="summary"
            >
            @if(!empty($qk['href']))
            <a href="{{ $qk['href'] }}" class="qk-link">
            @endif
            <div class="qk @if(! empty($qk['clickable'])) qk--clickable @endif" @if(! empty($qk['clickable']) && empty($qk['href'])) tabindex="0" role="button" @endif>
                <div class="qk-head">
                    <span class="qk-ico-wrap" style="background: {{ $qk['c'] }}22;border:1px solid {{ $qk['c'] }}44;color: {{ $qk['c'] }}">
                        <i class="fa-solid {{ $qk['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <h3>{{ $qk['title'] }}</h3>
                </div>
                <div class="qk-body">
                    @foreach ($qk['lines'] as $ln)
                        @isset($ln['k'])
                            <div class="qk-kv">
                                <span>{{ $ln['k'] }}:</span>
                                <span><span class="qk-val-ltr">{{ $ln['v'] }}</span></span>
                            </div>
                        @else
                            <div class="qk-line">
                                @if(! empty($ln['ltr']))
                                    <span class="qk-val-ltr">{{ $ln['text'] }}</span>
                                @else
                                    {{ $ln['text'] }}
                                @endif
                            </div>
                        @endisset
                    @endforeach
                </div>
                @if (! empty($qk['footer']))
                    <div class="qk-footer">{{ $qk['footer'] }}</div>
                @endif
            </div>
            @if(!empty($qk['href']))
            </a>
            @endif
            </div>
            @endif
        @endforeach
    </div>

    @foreach ($tables ?? [] as $tb)
        @if ($dashCan($tb['widget_id'] ?? ''))
        <div
            class="dash-widget"
            data-dash-widget="{{ $tb['widget_id'] }}"
            data-dash-title="{{ $tb['title'] }}"
            data-dash-group="tables"
        >
        <div class="card">
            <div class="card-h">
                <span class="card-h-ico" style="background: {{ $tb['color'] }}22;color:{{ $tb['color'] }};border:1px solid {{ $tb['color'] }}44;">
                    <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                </span>
                {{ $tb['title'] }}
            </div>
            <div class="tbl-wrap">
                <table class="dash-tbl">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>واریز</th>
                            <th>برداشت</th>
                            <th>مانده</th>
                            <th>شرح</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tb['rows'] as $r)
                            <tr>
                                @foreach ($r as $cell)
                                    <td><span class="stat-sys__val-ltr">{{ $cell }}</span></td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--muted);">رکوردی یافت نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="tbl-foot">
                <span><i class="fa-solid fa-list" style="margin-inline-end:0.25rem;opacity:0.75" aria-hidden="true"></i> نمایش آخرین {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers('10') }} ردیف</span>
                <span><i class="fa-regular fa-file-lines" style="margin-inline-end:0.25rem;opacity:0.75" aria-hidden="true"></i> تعداد: {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($tb['row_count'] ?? count($tb['rows'] ?? []))) }}</span>
            </div>
        </div>
        </div>
        @endif
    @endforeach

    @if ($dashCan('chart-installments-12m') || $dashCan('chart-new-loans-12m'))
    <div class="charts-row">
        @if ($dashCan('chart-installments-12m'))
        <div class="dash-widget" data-dash-widget="chart-installments-12m" data-dash-title="آمار اقساط (۱۲ ماه اخیر)" data-dash-group="charts">
        <div class="chart-card">
            <div class="ch-h" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                آمار اقساط (۱۲ ماه اخیر)
            </div>
            <div class="ch-b">
                <div id="dash-chart-installments" class="dash-line-chart" aria-hidden="false"></div>
            </div>
        </div>
        </div>
        @endif
        @if ($dashCan('chart-new-loans-12m'))
        <div class="dash-widget" data-dash-widget="chart-new-loans-12m" data-dash-title="آمار وام‌های جدید (۱۲ ماه اخیر)" data-dash-group="charts">
        <div class="chart-card">
            <div class="ch-h" style="background:linear-gradient(90deg,#22c55e,#15803d);">
                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                آمار وام‌های جدید (۱۲ ماه اخیر)
            </div>
            <div class="ch-b">
                <div id="dash-chart-new-loans" class="dash-line-chart" aria-hidden="false"></div>
            </div>
        </div>
        </div>
        @endif
    </div>
    @endif

    @auth('admin')
        <div id="dash-widget-overlay" class="dash-widget-overlay" hidden aria-hidden="true">
            <div
                id="dash-widget-dialog"
                class="dash-widget-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="dash-widget-dialog-title"
            >
                <div class="dash-widget-dialog__head">
                    <span id="dash-widget-dialog-title">کارت‌های قابل نمایش</span>
                    <button type="button" class="dash-widget-dialog__close" id="dash-widget-close" aria-label="بستن">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="dash-widget-dialog__body" id="dash-widget-checkboxes"></div>
                <div class="dash-widget-dialog__foot">
                    <button type="button" class="dash-widget-foot-btn" id="dash-widget-reset">نمایش همه</button>
                    <button type="button" class="dash-widget-foot-btn dash-widget-foot-btn--primary" id="dash-widget-done">بستن</button>
                </div>
            </div>
        </div>
    @endauth

    <script type="application/json" id="dash-charts-config">
        {!! json_encode([
            'installments' => $installmentChart ?? ['series' => []],
            'new_loans' => $newLoansChart ?? ['series' => []],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endsection

@push('scripts')
    @vite(['resources/js/admin-dashboard-charts.js'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    var STORAGE_KEY = 'myghest_dashboard_widgets_v1';
    var openBtn = document.getElementById('dash-open-widgets');
    var overlay = document.getElementById('dash-widget-overlay');
    if (!openBtn || !overlay) return;

    var checkboxHost = document.getElementById('dash-widget-checkboxes');
    var closeBtn = document.getElementById('dash-widget-close');
    var doneBtn = document.getElementById('dash-widget-done');
    var resetBtn = document.getElementById('dash-widget-reset');

    var GROUP_LABEL = {
        general: 'آمار کلی',
        summary: 'کارت‌های خلاصه',
        tables: 'جداول',
        charts: 'نمودارها',
        other: 'سایر',
    };

    var GROUP_ORDER = ['general', 'summary', 'tables', 'charts', 'other'];

    function collectWidgets() {
        return Array.from(document.querySelectorAll('.dash-widget[data-dash-widget]')).map(function (el) {
            return {
                id: el.getAttribute('data-dash-widget'),
                title: el.getAttribute('data-dash-title') || el.getAttribute('data-dash-widget'),
                group: el.getAttribute('data-dash-group') || 'other',
            };
        });
    }

    function defaultPrefs() {
        var o = {};
        collectWidgets().forEach(function (w) {
            o[w.id] = true;
        });

        return o;
    }

    function loadPrefs() {
        var base = defaultPrefs();
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return base;
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                Object.keys(base).forEach(function (id) {
                    if (typeof parsed[id] === 'boolean') base[id] = parsed[id];
                });
            }
        } catch (e) { /* */ }

        return base;
    }

    function savePrefs(prefs) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) { /* */ }
    }

    function applyPrefs(prefs) {
        document.querySelectorAll('.dash-widget[data-dash-widget]').forEach(function (el) {
            var id = el.getAttribute('data-dash-widget');
            var visible = prefs[id] !== false;
            el.classList.toggle('dash-widget--hidden', !visible);
        });
    }

    function buildPanel() {
        if (! checkboxHost) return;
        checkboxHost.innerHTML = '';
        var widgets = collectWidgets();
        var byGroup = {};

        widgets.forEach(function (w) {
            var g = w.group || 'other';
            if (! byGroup[g]) byGroup[g] = [];

            byGroup[g].push(w);
        });

        var prefs = loadPrefs();
        var groupKeys = [];
        GROUP_ORDER.forEach(function (k) {
            if (byGroup[k] && byGroup[k].length) groupKeys.push(k);
        });

        Object.keys(byGroup).forEach(function (k) {
            if (groupKeys.indexOf(k) === -1 && byGroup[k].length) groupKeys.push(k);
        });

        groupKeys.forEach(function (gKey) {
            var list = byGroup[gKey];
            if (! list || ! list.length) return;

            var section = document.createElement('section');
            section.className = 'dash-widget-group';
            var gh = document.createElement('div');
            gh.className = 'dash-widget-group__title';
            gh.textContent = GROUP_LABEL[gKey] || GROUP_LABEL.other;

            section.appendChild(gh);

            list.forEach(function (w) {
                var label = document.createElement('label');
                label.className = 'dash-widget-choice';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = prefs[w.id] !== false;
                cb.dataset.widgetId = w.id;

                var span = document.createElement('span');
                span.textContent = w.title;
                label.appendChild(cb);
                label.appendChild(span);
                section.appendChild(label);

                cb.addEventListener('change', function () {
                    var p = loadPrefs();

                    p[w.id] = cb.checked;

                    savePrefs(p);
                    applyPrefs(p);
                });
            });

            checkboxHost.appendChild(section);
        });
    }

    function openOverlay() {
        buildPanel();
        overlay.removeAttribute('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dash-widget-modal-open');
    }

    function closeOverlay() {
        overlay.setAttribute('hidden', '');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('dash-widget-modal-open');
    }

    applyPrefs(loadPrefs());

    openBtn.addEventListener('click', openOverlay);
    if (closeBtn) closeBtn.addEventListener('click', closeOverlay);
    if (doneBtn) doneBtn.addEventListener('click', closeOverlay);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) { /* */ }
            applyPrefs(loadPrefs());
            buildPanel();
        });
    }

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeOverlay();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ! overlay.hidden) closeOverlay();
    });
});
</script>
@endpush
