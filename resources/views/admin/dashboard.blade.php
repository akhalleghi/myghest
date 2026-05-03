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

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 0.65rem;
            margin-bottom: 1rem;
        }

        .quick-grid > .dash-widget {
            min-width: 0;
        }

        .quick-grid > .dash-widget > .qk {
            height: 100%;
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
            min-height: 7.5rem;
            display: flex;
            flex-direction: column;
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
            margin-bottom: 0.45rem;
            display: grid;
            place-items: center;
            font-size: 0.88rem;
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
            padding: 0.65rem;
            background: var(--bg-card);
        }

        .chart-card svg { width: 100%; height: auto; display: block; }

        .chart-svg-surface {
            fill: var(--primary-soft);
        }

        html[data-theme="dark"] .chart-svg-surface {
            fill: #1e293b;
        }

        .chart-svg-label {
            fill: #64748b;
        }

        html[data-theme="dark"] .chart-svg-label {
            fill: #94a3b8;
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
        </div>
        <!-- <p class="dash-sub">
            نام کاربری: <strong>{{ auth('admin')->user()->username }}</strong>
            — این صفحه با دادهٔ نمونه نمایش داده می‌شود تا ماژول‌های واقعی در گام بعدی وصل شوند.
        </p> -->
    @else
        <p class="dash-sub">نشست نامعتبر است.</p>
    @endauth

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
                @php($systemStatRows = [
                ['label' => 'تعداد مشتری', 'value' => '1'],
                ['label' => 'تعداد پرونده وام', 'value' => '1'],
                ['label' => 'تعداد وام تسویه', 'value' => '0'],
                ['label' => 'مجموع خالص وام ها', 'value' => '6,000,000 تومان'],
                ['label' => 'مجموع وام ها با احتساب بهره', 'value' => '6,360,000 تومان'],
                ['label' => 'مجموع وام ها با احتساب بهره و دیرکرد', 'value' => '6,387,560 تومان'],
                ['label' => 'مجموع وصول شده', 'value' => '3,000,000 تومان'],
                ['label' => 'مجموع وصول نشده', 'value' => '3,387,560 تومان'],
                ['label' => 'مجموع وصول نشده سررسید نشده', 'value' => '4,240,000 تومان'],
                ])
                @php($systemStatChunks = array_chunk($systemStatRows, (int) ceil(count($systemStatRows) / 2)))
                <div class="stat-sys">
                    @foreach ($systemStatChunks as $chunk)
                        <div class="stat-sys__col">
                            @foreach ($chunk as $sr)
                                <div class="stat-sys__row">
                                    <span>{{ $sr['label'] }}:</span>
                                    <span class="stat-sys__val"><span class="stat-sys__val-ltr">{{ $sr['value'] }}</span></span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- کارت‌های خلاصه --}}
    @php($summaryCards = [
        [
            'widget_id' => 'summary-overdue',
            'title' => 'اقساط سررسید شده و معوق ها',
            'icon' => 'fa-calendar-xmark',
            'c' => '#8b5cf6',
            'clickable' => true,
            'lines' => [
                ['text' => '0 تومان', 'ltr' => true],
                ['text' => '0 مورد'],
            ],
            'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
        ],
        [
            'widget_id' => 'summary-deposit-notifications',
            'title' => 'اعلام واریزی های جدید',
            'icon' => 'fa-money-bill-transfer',
            'c' => '#ec4899',
            'clickable' => true,
            'lines' => [
                ['text' => '0 مورد'],
            ],
            'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
        ],
        [
            'widget_id' => 'summary-loan-requests',
            'title' => 'درخواست وام ها',
            'icon' => 'fa-file-signature',
            'c' => '#06b6d4',
            'clickable' => true,
            'lines' => [
                ['k' => 'در انتظار بررسی کارشناس', 'v' => '0'],
                ['k' => 'بررسی مجدد کارشناس', 'v' => '0'],
                ['k' => 'درخواست های جدید امروز', 'v' => '0'],
            ],
            'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
        ],
        [
            'widget_id' => 'summary-sms-email',
            'title' => 'وضعیت پیامک و ایمیل',
            'icon' => 'fa-paper-plane',
            'c' => '#f97316',
            'clickable' => false,
            'lines' => [
                ['k' => 'ارسال موفق پیامک امروز', 'v' => '۰ مورد'],
                ['k' => 'ارسال موفق ایمیل امروز', 'v' => '۰ مورد'],
            ],
            'footer' => null,
        ],
        [
            'widget_id' => 'summary-counterparty-matured',
            'title' => 'سررسید شده های طرف حساب',
            'icon' => 'fa-user-clock',
            'c' => '#2563eb',
            'clickable' => true,
            'lines' => [
                ['text' => '0 تومان', 'ltr' => true],
                ['text' => '0 مورد'],
            ],
            'footer' => 'جهت مشاهده بر روی باکس کلیک کنید',
        ],
    ])
    <div class="quick-grid">
        @foreach ($summaryCards as $qk)
            <div
                class="dash-widget"
                data-dash-widget="{{ $qk['widget_id'] }}"
                data-dash-title="{{ $qk['title'] }}"
                data-dash-group="summary"
            >
            <div class="qk @if(! empty($qk['clickable'])) qk--clickable @endif" @if(! empty($qk['clickable'])) tabindex="0" role="button" @endif>
                <span class="qk-ico-wrap" style="background: {{ $qk['c'] }}22;border:1px solid {{ $qk['c'] }}44;color: {{ $qk['c'] }}">
                    <i class="fa-solid {{ $qk['icon'] }}" aria-hidden="true"></i>
                </span>
                <h3>{{ $qk['title'] }}</h3>
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
            </div>
        @endforeach
    </div>

    @php($tables = [
        ['widget_id' => 'tbl-online-installments', 'title' => 'واریز قسط‌های آنلاین', 'color' => '#6366f1', 'rows' => [
            ['۱۴۰۴/۱۲/۰۲', '۲٬۵۰۰٬۰۰۰', '—', '۱۵٬۲۰۰٬۰۰۰', 'واریز قسط آنلاین'],
            ['۱۴۰۴/۱۲/۰۱', '۱٬۸۰۰٬۰۰۰', '—', '۱۲٬۷۰۰٬۰۰۰', 'درگاه بانکی'],
        ]],
        ['widget_id' => 'tbl-bank-transactions', 'title' => 'تراکنش‌های بانک', 'color' => '#06b6d4', 'rows' => [
            ['۱۴۰۴/۱۱/۲۸', '۵٬۰۰۰٬۰۰۰', '—', '۴۲٬۰۰۰٬۰۰۰', 'واریز توده‌ای'],
            ['۱۴۰۴/۱۱/۲۷', '—', '۱٬۲۰۰٬۰۰۰', '۳۷٬۰۰۰٬۰۰۰', 'برداشت کارمزد'],
        ]],
        ['widget_id' => 'tbl-fund-transactions', 'title' => 'تراکنش‌های صندوق', 'color' => '#10b981', 'rows' => [
            ['۱۴۰۴/۱۱/۲۵', '۳۰۰٬۰۰۰', '—', '۲٬۱۰۰٬۰۰۰', 'دریافت نقدی'],
            ['۱۴۰۴/۱۱/۲۴', '—', '۱۵۰٬۰۰۰', '۱٬۸۰۰٬۰۰۰', 'پرداخت هزینه'],
        ]],
        ['widget_id' => 'tbl-special-box', 'title' => 'جعبه‌شکن / تراکنش ویژه', 'color' => '#ec4899', 'rows' => [
            ['۱۴۰۴/۱۱/۲۰', '۸۰۰٬۰۰۰', '—', '۹۵۰٬۰۰۰', 'تسهیم دوره‌ای'],
            ['۱۴۰۴/۱۱/۱۹', '—', '۲۰۰٬۰۰۰', '۱۵۰٬۰۰۰', 'اصلاح سند'],
        ]],
    ])
    @foreach ($tables as $tb)
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
                        @foreach ($tb['rows'] as $r)
                            <tr>
                                @foreach ($r as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="tbl-foot">
                <span><i class="fa-solid fa-list" style="margin-inline-end:0.25rem;opacity:0.75" aria-hidden="true"></i> ردیف در هر صفحه: ۱۰</span>
                <span><i class="fa-regular fa-file-lines" style="margin-inline-end:0.25rem;opacity:0.75" aria-hidden="true"></i> صفحه ۱ از ۱</span>
            </div>
        </div>
        </div>
    @endforeach

    <div class="charts-row">
        <div class="dash-widget" data-dash-widget="chart-installments-12m" data-dash-title="آمار اقساط (۱۲ ماه اخیر)" data-dash-group="charts">
        <div class="chart-card">
            <div class="ch-h" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                آمار اقساط (۱۲ ماه اخیر)
            </div>
            <div class="ch-b">
                <svg viewBox="0 0 420 140" role="img" aria-label="نمودار اقساط">
                    <rect class="chart-svg-surface" width="420" height="140"/>
                    <polyline fill="none" stroke="#0ea5e9" stroke-width="2.5"
                        points="20,100 55,88 90,95 125,72 160,80 195,55 230,62 265,48 300,52 335,40 370,35 405,28"/>
                    <g class="chart-svg-label" font-size="10" font-family="IRANSans, sans-serif">
                        <text x="12" y="132">فروردین</text>
                        <text x="52" y="132">اردیبهشت</text>
                        <text x="92" y="132">خرداد</text>
                        <text x="132" y="132">تیر</text>
                        <text x="168" y="132">مرداد</text>
                        <text x="200" y="132">شهریور</text>
                        <text x="238" y="132">مهر</text>
                        <text x="276" y="132">آبان</text>
                        <text x="312" y="132">آذر</text>
                        <text x="348" y="132">دی</text>
                        <text x="380" y="132">بهمن</text>
                        <text x="408" y="132">اسفند</text>
                    </g>
                </svg>
            </div>
        </div>
        </div>
        <div class="dash-widget" data-dash-widget="chart-new-loans-12m" data-dash-title="آمار وام‌های جدید (۱۲ ماه اخیر)" data-dash-group="charts">
        <div class="chart-card">
            <div class="ch-h" style="background:linear-gradient(90deg,#22c55e,#15803d);">
                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                آمار وام‌های جدید (۱۲ ماه اخیر)
            </div>
            <div class="ch-b">
                <svg viewBox="0 0 420 140" role="img" aria-label="نمودار وام جدید">
                    <rect class="chart-svg-surface" width="420" height="140"/>
                    <polyline fill="none" stroke="#22c55e" stroke-width="2.5"
                        points="20,95 55,90 90,85 125,70 160,65 195,58 230,50 265,55 300,42 335,38 370,32 405,25"/>
                    <g class="chart-svg-label" font-size="10" font-family="IRANSans, sans-serif">
                        <text x="12" y="132">فروردین</text>
                        <text x="52" y="132">اردیبهشت</text>
                        <text x="92" y="132">خرداد</text>
                        <text x="132" y="132">تیر</text>
                        <text x="168" y="132">مرداد</text>
                        <text x="200" y="132">شهریور</text>
                        <text x="238" y="132">مهر</text>
                        <text x="276" y="132">آبان</text>
                        <text x="312" y="132">آذر</text>
                        <text x="348" y="132">دی</text>
                        <text x="380" y="132">بهمن</text>
                        <text x="408" y="132">اسفند</text>
                    </g>
                </svg>
            </div>
        </div>
        </div>
    </div>

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
@endsection

@push('scripts')
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
