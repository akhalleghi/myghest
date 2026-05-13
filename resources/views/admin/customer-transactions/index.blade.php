@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .ctx-page { width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; }
        .ctx-page-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
            margin-bottom: 0.4rem;
        }
        .ctx-h1 { margin: 0 0 0.35rem; font-size: 1.15rem; font-weight: 900; color: var(--text); }
        .ctx-page-toolbar .ctx-h1 { margin: 0; flex: 1; min-width: 12rem; }
        .ctx-lead { margin: 0 0 1rem; font-size: 0.82rem; color: var(--muted); line-height: 1.55; max-width: 52rem; }
        .ctx-filters {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
            gap: 0.55rem 0.65rem;
            align-items: end;
            margin-bottom: 0.9rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            background: linear-gradient(165deg, rgba(239, 246, 255, 0.35), var(--bg-card));
        }
        html[data-theme="dark"] .ctx-filters {
            background: linear-gradient(165deg, rgba(30, 58, 138, 0.12), rgba(15, 23, 42, 0.55));
        }
        .ctx-filters label { font-size: 0.68rem; font-weight: 800; color: var(--muted); display: block; margin-bottom: 0.18rem; }
        .ctx-filters input, .ctx-filters select {
            width: 100%;
            box-sizing: border-box;
            padding: 0.38rem 0.5rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            font-family: inherit;
            font-size: 0.78rem;
        }
        .ctx-jdate-input { cursor: pointer; }
        .datepicker-plot-area { z-index: 10050 !important; }
        .ctx-filters-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
            grid-column: 1 / -1;
            margin-top: 0.15rem;
        }
        .ctx-btn {
            font-family: inherit;
            font-size: 0.74rem;
            font-weight: 800;
            padding: 0.38rem 0.65rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            cursor: pointer;
            color: var(--text);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .ctx-btn--pri { background: var(--primary); color: #fff; border-color: var(--primary-dark); }
        .ctx-btn--ghost { background: transparent; }
        .ctx-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); }
        .ctx-tbl { width: 100%; border-collapse: collapse; font-size: 0.72rem; min-width: 72rem; }
        .ctx-tbl th, .ctx-tbl td { padding: 0.42rem 0.45rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .ctx-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .ctx-tbl tr:last-child td { border-bottom: 0; }
        .ctx-tbl td { color: var(--muted); font-weight: 600; }
        .ctx-tbl tbody tr:hover td { background: rgba(37, 99, 235, 0.04); }
        .ctx-badge { display: inline-flex; padding: 0.12rem 0.38rem; border-radius: 999px; font-size: 0.65rem; font-weight: 900; white-space: nowrap; }
        .ctx-badge--ok { background: rgba(16, 185, 129, 0.2); color: #047857; }
        .ctx-badge--danger { background: rgba(239, 68, 68, 0.16); color: #b91c1c; }
        .ctx-badge--pending { background: rgba(245, 158, 11, 0.22); color: #b45309; }
        .ctx-badge--muted { background: rgba(148, 163, 184, 0.22); color: var(--muted); }
        .ctx-clip { max-width: 14rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ctx-clip-2 { max-width: 18rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .ctx-ltr { direction: ltr; unicode-bidi: embed; text-align: left; display: inline-block; max-width: 100%; word-break: break-all; font-size: 0.68rem; }
        .ctx-pagination { margin-top: 0.75rem; }
        #ctx-dialog {
            display: none;
            padding: 0;
            border: none;
            border-radius: 1rem;
            max-width: min(96vw, 48rem);
            width: min(96vw, 48rem);
            max-height: min(92vh, 46rem);
            background: var(--bg-card);
            color: var(--text);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }
        #ctx-dialog[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            margin: auto;
        }
        #ctx-dialog::backdrop { background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); }
        .ctx-dlg-inner { position: relative; display: flex; flex-direction: column; min-height: 0; flex: 1; max-height: inherit; }
        .ctx-dlg-close {
            position: absolute;
            top: 0.35rem;
            inset-inline-end: 0.35rem;
            width: 2rem;
            height: 2rem;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1.35rem;
            cursor: pointer;
            border-radius: 0.4rem;
            z-index: 2;
        }
        .ctx-dlg-head { flex-shrink: 0; padding: 0.85rem 2.4rem 0.35rem 0.85rem; }
        .ctx-dlg-title { margin: 0; font-size: 1rem; font-weight: 900; line-height: 1.35; }
        .ctx-dlg-sub {
            margin: 0.35rem 0 0;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            line-height: 1.4;
        }
        .ctx-dlg-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 0.35rem 0.85rem 0.85rem; -webkit-overflow-scrolling: touch; }
        .ctx-dlg-footer {
            flex-shrink: 0;
            padding: 0.65rem 0.85rem 0.85rem;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
        }
        @media (max-width: 420px) {
            .ctx-dlg-footer .ctx-btn { flex: 1 1 calc(50% - 0.25rem); justify-content: center; }
        }
        .ctx-meta-pre {
            margin: 0;
            padding: 0.55rem 0.6rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            background: rgba(15, 23, 42, 0.04);
            font-size: 0.68rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 14rem;
            overflow: auto;
        }
        html[data-theme="dark"] .ctx-meta-pre { background: rgba(0, 0, 0, 0.2); }

        /* مودال جزئیات: شبکهٔ ۳ ستونه، ارتفاع یکسان کارت‌ها (meta بیرون گرید است) */
        .ctx-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
            align-items: stretch;
        }
        @media (max-width: 720px) {
            .ctx-detail-grid { grid-template-columns: 1fr; }
        }
        .ctx-detail-card {
            margin: 0;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 0.55rem 0.62rem;
            background: linear-gradient(165deg, rgba(248, 250, 252, 0.92), var(--bg-card));
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
            box-sizing: border-box;
            min-width: 0;
        }
        .ctx-detail-grid > .ctx-detail-card .ctx-detail-card__head {
            flex-shrink: 0;
        }
        .ctx-detail-grid > .ctx-detail-card {
            height: 8.85rem;
            min-height: 8.85rem;
            max-height: 8.85rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        html[data-theme="dark"] .ctx-detail-card {
            background: linear-gradient(165deg, rgba(30, 41, 59, 0.45), rgba(15, 23, 42, 0.55));
            box-shadow: none;
        }
        .ctx-detail-card--wide { grid-column: 1 / -1; }
        .ctx-detail-card--alert {
            border-color: rgba(239, 68, 68, 0.38);
            background: linear-gradient(165deg, rgba(254, 242, 242, 0.95), var(--bg-card));
        }
        html[data-theme="dark"] .ctx-detail-card--alert {
            border-color: rgba(248, 113, 113, 0.35);
            background: linear-gradient(165deg, rgba(127, 29, 29, 0.22), rgba(15, 23, 42, 0.65));
        }
        .ctx-detail-card__head {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.48rem;
            min-width: 0;
        }
        .ctx-detail-card__ico {
            flex-shrink: 0;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 0.5rem;
            display: grid;
            place-items: center;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 0.82rem;
        }
        html[data-theme="dark"] .ctx-detail-card__ico {
            background: rgba(37, 99, 235, 0.22);
            color: var(--primary-dark);
        }
        .ctx-detail-card--alert .ctx-detail-card__ico {
            background: rgba(239, 68, 68, 0.14);
            color: #b91c1c;
        }
        html[data-theme="dark"] .ctx-detail-card--alert .ctx-detail-card__ico {
            background: rgba(248, 113, 113, 0.15);
            color: #fca5a5;
        }
        .ctx-detail-card__label {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--muted);
            line-height: 1.35;
            flex: 1;
            min-width: 0;
        }
        .ctx-detail-card__value {
            border-top: 1px dashed rgba(148, 163, 184, 0.42);
            padding-top: 0.38rem;
            min-height: 0;
            flex: 1;
            overflow: auto;
            display: flex;
            align-items: flex-start;
        }
        html[data-theme="dark"] .ctx-detail-card__value {
            border-top-color: rgba(148, 163, 184, 0.22);
        }
        .ctx-detail-card__value > .ctx-badge { margin-top: 0.1rem; }
        .ctx-detail-card__text {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.45;
            word-break: break-word;
            width: 100%;
            min-width: 0;
        }
        .ctx-detail-card__text--muted { color: var(--muted); font-weight: 650; }
        .ctx-detail-card__text--ltr {
            direction: ltr;
            unicode-bidi: embed;
            text-align: left;
            font-variant-numeric: tabular-nums;
            font-size: 0.76rem;
        }
        .ctx-detail-card__text--pre { white-space: pre-wrap; }
        .ctx-detail-card--sm .ctx-detail-card__label { font-size: 0.62rem; }
        .ctx-detail-card--sm .ctx-detail-card__ico {
            width: 1.55rem;
            height: 1.55rem;
            font-size: 0.72rem;
        }
        .ctx-detail-card--sm .ctx-detail-card__head { margin-bottom: 0.35rem; }
        .ctx-detail-card--sm .ctx-detail-card__text { font-size: 0.7rem; line-height: 1.4; }
        .ctx-detail-card--sm .ctx-detail-card__text--ltr { font-size: 0.66rem; }
        .ctx-detail-card--sm .ctx-badge { font-size: 0.62rem; padding: 0.08rem 0.32rem; }
        .ctx-detail-card--sm .ctx-detail-times__k { font-size: 0.55rem; }
        .ctx-detail-card--sm .ctx-detail-times__v { font-size: 0.66rem; line-height: 1.32; }
        .ctx-detail-card--xs .ctx-detail-card__label { font-size: 0.58rem; }
        .ctx-detail-card--xs .ctx-detail-card__ico {
            width: 1.4rem;
            height: 1.4rem;
            font-size: 0.66rem;
        }
        .ctx-detail-card--xs .ctx-detail-card__head { margin-bottom: 0.28rem; gap: 0.35rem; }
        .ctx-detail-card--xs .ctx-detail-card__text { font-size: 0.64rem; line-height: 1.35; }
        .ctx-detail-card--xs .ctx-detail-card__text--ltr { font-size: 0.6rem; }
        .ctx-detail-card--xs .ctx-badge { font-size: 0.56rem; padding: 0.06rem 0.26rem; }
        .ctx-detail-card--xs .ctx-detail-times__k { font-size: 0.58rem; }
        .ctx-detail-card--xs .ctx-detail-times__v { font-size: 0.64rem; }
        .ctx-detail-card--times .ctx-detail-times-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem 0.65rem;
            width: 100%;
            min-width: 0;
        }
        .ctx-detail-times__k {
            display: block;
            font-size: 0.6rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.18rem;
        }
        .ctx-detail-times__v {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.4;
            word-break: break-word;
        }
        .ctx-detail-meta-wrap {
            margin-top: 0.55rem;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .ctx-detail-meta-wrap .ctx-detail-card__head {
            flex-shrink: 0;
        }
        .ctx-detail-meta-wrap .ctx-detail-card__value {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }
        .ctx-detail-meta-wrap .ctx-meta-pre { margin: 0; max-height: 14rem; }
    </style>
@endpush

@section('content')
    <div class="ctx-page">
        <div class="ctx-page-toolbar">
            <h1 class="ctx-h1">{{ $pageTitle }}</h1>
            <a href="{{ route('admin.customer-transactions.export', request()->query()) }}" class="ctx-btn ctx-btn--pri" title="خروجی همهٔ ردیف‌های مطابق فیلتر فعلی (نه فقط همین صفحه)">
                <i class="fa-regular fa-file-excel" aria-hidden="true"></i>
                خروجی اکسل
            </a>
        </div>
        <p class="ctx-lead">
            نمایش تمام تراکنش‌های ثبت‌شده در دفتر مشتریان (پرداخت درگاه قسط، و انواع دیگر در آینده). با فیلتر بازهٔ تاریخ شمسی، نوع، وضعیت، درگاه و جستجوی متنی می‌توانید گزارش را محدود کنید.
        </p>

        <form class="ctx-filters" method="get" action="{{ route('admin.customer-transactions.index') }}">
            <div>
                <label for="ctx-date-from">از تاریخ (شمسی)</label>
                <input type="text" id="ctx-date-from" class="ctx-jdate-input" name="date_from" value="{{ $filterInputs['date_from'] ?? '' }}" placeholder="۱۴۰۴/۰۱/۰۱" maxlength="20" autocomplete="off" inputmode="none">
            </div>
            <div>
                <label for="ctx-date-to">تا تاریخ (شمسی)</label>
                <input type="text" id="ctx-date-to" class="ctx-jdate-input" name="date_to" value="{{ $filterInputs['date_to'] ?? '' }}" placeholder="۱۴۰۴/۱۲/۲۹" maxlength="20" autocomplete="off" inputmode="none">
            </div>
            <div>
                <label for="ctx-kind">نوع تراکنش</label>
                <select id="ctx-kind" name="kind">
                    <option value="">همه</option>
                    @foreach ($kindLabels as $k => $label)
                        <option value="{{ $k }}" @selected(($filterInputs['kind'] ?? '') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ctx-status">وضعیت</label>
                <select id="ctx-status" name="status">
                    <option value="">همه</option>
                    @foreach ($statusLabels as $k => $label)
                        <option value="{{ $k }}" @selected(($filterInputs['status'] ?? '') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ctx-gateway">درگاه</label>
                <select id="ctx-gateway" name="gateway">
                    <option value="">همه</option>
                    <option value="zibal" @selected(($filterInputs['gateway'] ?? '') === 'zibal')>زیبال</option>
                </select>
            </div>
            <div>
                <label for="ctx-customer-id">شناسه مشتری</label>
                <input type="text" id="ctx-customer-id" name="customer_id" inputmode="numeric" pattern="[0-9]*" value="{{ $filterInputs['customer_id'] ?? '' }}" placeholder="مثلاً ۱۲" maxlength="12" autocomplete="off">
            </div>
            <div style="grid-column: span 2; min-width: 12rem;">
                <label for="ctx-q">جستجو</label>
                <input type="search" id="ctx-q" name="q" value="{{ $filterInputs['q'] ?? '' }}" maxlength="120" placeholder="نام، موبایل، کد، پیگیری، عنوان، شرح…">
            </div>
            <div class="ctx-filters-actions">
                <button type="submit" class="ctx-btn ctx-btn--pri"><i class="fa-solid fa-filter" aria-hidden="true"></i> اعمال فیلتر</button>
                <a href="{{ route('admin.customer-transactions.index') }}" class="ctx-btn ctx-btn--ghost">پاک‌سازی</a>
            </div>
        </form>

        <div class="ctx-wrap">
            <table class="ctx-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تاریخ ثبت</th>
                        <th>مشتری</th>
                        <th>نوع</th>
                        <th>وضعیت</th>
                        <th>عنوان</th>
                        <th>شرح</th>
                        <th>مبلغ</th>
                        <th>درگاه</th>
                        <th>پیگیری</th>
                        <th>مرجع بانک</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        @php
                            $st = (string) $tx->status;
                            $tone = match ($st) {
                                'completed' => 'ok',
                                'failed' => 'danger',
                                'redirected' => 'pending',
                                default => 'muted',
                            };
                            $c = $tx->customer;
                            $custBrief = $c ? trim($c->fullName()) : '—';
                            $custSub = $c ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($c->mobile ?? '')) : '';
                            $kindFa = $kindLabels[$tx->kind] ?? $tx->kind;
                            $stFa = $statusLabels[$tx->status] ?? $tx->status;
                            $gwFa = $tx->gateway_key === 'zibal' ? 'زیبال' : ($tx->gateway_key ?: '—');
                            $created = \Carbon\Carbon::parse($tx->created_at);
                            $createdFa = \Hekmatinasser\Jalali\Jalali::enToFaNumbers(\Hekmatinasser\Jalali\Jalali::instance($created)->format('Y/m/d H:i'));
                        @endphp
                        <tr>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $tx->id) }}</td>
                            <td>{{ $createdFa }}</td>
                            <td>
                                {{ $custBrief }}<br>
                                <small style="opacity:0.88">{{ $custSub }}</small>
                            </td>
                            <td>{{ $kindFa }}</td>
                            <td><span class="ctx-badge ctx-badge--{{ $tone }}">{{ $stFa }}</span></td>
                            <td><span class="ctx-clip" title="{{ e($tx->title) }}">{{ $tx->title }}</span></td>
                            <td><span class="ctx-clip-2" title="{{ e($tx->detail ?? '') }}">{{ $tx->detail ?: '—' }}</span></td>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int) $tx->amount_toman, 0, '.', ',')) }} تومان</td>
                            <td>{{ $gwFa }}</td>
                            <td><span class="ctx-ltr">{{ $tx->track_id !== null ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $tx->track_id) : '—' }}</span></td>
                            <td><span class="ctx-ltr">{{ $tx->bank_reference ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers($tx->bank_reference) : '—' }}</span></td>
                            <td>
                                <button type="button" class="ctx-btn ctx-btn--ghost ctx-open-detail" data-id="{{ $tx->id }}">
                                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                    جزئیات
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" style="text-align:center;padding:1.4rem;color:var(--muted);font-weight:800">رکوردی با این فیلترها یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ctx-pagination">
            {{ $transactions->links() }}
        </div>
    </div>

    <dialog id="ctx-dialog" aria-labelledby="ctx-dialog-title">
        <div class="ctx-dlg-inner">
            <button type="button" class="ctx-dlg-close" data-ctx-close aria-label="بستن">&times;</button>
            <div class="ctx-dlg-head">
                <h2 id="ctx-dialog-title" class="ctx-dlg-title">جزئیات تراکنش</h2>
                <p id="ctx-dialog-sub" class="ctx-dlg-sub" aria-live="polite"></p>
            </div>
            <div class="ctx-dlg-scroll">
                <div id="ctx-detail-fields" class="ctx-detail-grid" role="region" aria-label="جزئیات رکورد"></div>
                <div id="ctx-meta-block" class="ctx-detail-card ctx-detail-card--wide ctx-detail-meta-wrap" style="display:none" hidden>
                    <div class="ctx-detail-card__head">
                        <span class="ctx-detail-card__ico" aria-hidden="true"><i class="fa-solid fa-code"></i></span>
                        <span class="ctx-detail-card__label">دادهٔ ساختاری (meta)</span>
                    </div>
                    <div class="ctx-detail-card__value">
                        <pre id="ctx-meta-pre" class="ctx-meta-pre" aria-label="JSON"></pre>
                    </div>
                </div>
            </div>
            <div class="ctx-dlg-footer">
                <a id="ctx-customer-link" href="#" class="ctx-btn ctx-btn--pri" style="display:none" target="_blank" rel="noopener">مشتری در لیست</a>
                <button type="button" class="ctx-btn ctx-btn--ghost" data-ctx-close>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        window.__CTX_TX_SNAPSHOTS__ = @json($rowSnapshots ?? []);
    </script>
    <script>
        (function () {
            var snapshots = window.__CTX_TX_SNAPSHOTS__ || {};
            var dialog = document.getElementById('ctx-dialog');
            var fieldsEl = document.getElementById('ctx-detail-fields');
            var metaBlock = document.getElementById('ctx-meta-block');
            var metaPre = document.getElementById('ctx-meta-pre');
            var custLink = document.getElementById('ctx-customer-link');
            var subEl = document.getElementById('ctx-dialog-sub');

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function card(iconClass, labelText, valueInnerHtml, wide, extraCardClass) {
                var w = wide ? ' ctx-detail-card--wide' : '';
                var ec = extraCardClass ? ' ' + extraCardClass : '';
                return (
                    '<article class="ctx-detail-card' + w + ec + '">' +
                        '<div class="ctx-detail-card__head">' +
                            '<span class="ctx-detail-card__ico" aria-hidden="true"><i class="fa-solid ' + iconClass + '"></i></span>' +
                            '<span class="ctx-detail-card__label">' + esc(labelText) + '</span>' +
                        '</div>' +
                        '<div class="ctx-detail-card__value">' + valueInnerHtml + '</div>' +
                    '</article>'
                );
            }

            function itemText(icon, label, value, wide, textExtraClass) {
                var v = value == null || String(value).trim() === '' ? '—' : String(value);
                var cls = 'ctx-detail-card__text' + (textExtraClass ? ' ' + textExtraClass : '');
                return card(icon, label, '<span class="' + cls + '">' + esc(v) + '</span>', wide, '');
            }

            function badgeTone(st) {
                if (st === 'completed') return 'ok';
                if (st === 'failed') return 'danger';
                if (st === 'redirected') return 'pending';
                return 'muted';
            }

            function cardTimes(createdFa, updatedFa) {
                var c1 = createdFa == null || String(createdFa).trim() === '' ? '—' : String(createdFa);
                var c2 = updatedFa == null || String(updatedFa).trim() === '' ? '—' : String(updatedFa);
                var inner =
                    '<div class="ctx-detail-times-inner">' +
                    '<div><span class="ctx-detail-times__k">زمان ایجاد</span><span class="ctx-detail-times__v">' + esc(c1) + '</span></div>' +
                    '<div><span class="ctx-detail-times__k">آخرین به‌روزرسانی</span><span class="ctx-detail-times__v">' + esc(c2) + '</span></div>' +
                    '</div>';
                return card('fa-clock', 'زمان در سیستم', inner, true, 'ctx-detail-card--times');
            }

            function fitDetailCards() {
                if (!fieldsEl) return;
                fieldsEl.querySelectorAll('.ctx-detail-card').forEach(function (card) {
                    card.classList.remove('ctx-detail-card--sm', 'ctx-detail-card--xs');
                    var val = card.querySelector('.ctx-detail-card__value');
                    if (!val) return;
                    if (val.scrollHeight <= val.clientHeight) return;
                    card.classList.add('ctx-detail-card--sm');
                    void val.offsetHeight;
                    if (val.scrollHeight > val.clientHeight) {
                        card.classList.add('ctx-detail-card--xs');
                    }
                });
            }

            var fitDetailScheduled = false;
            function scheduleFitDetailCards() {
                if (fitDetailScheduled) return;
                fitDetailScheduled = true;
                requestAnimationFrame(function () {
                    fitDetailScheduled = false;
                    fitDetailCards();
                });
            }

            function openDetail(id) {
                var s = snapshots[String(id)] || snapshots[id];
                if (!s || !fieldsEl || !dialog) return;
                var tone = badgeTone(s.status);
                if (subEl) {
                    subEl.textContent = s.created_at_fa ? ('زمان ثبت: ' + String(s.created_at_fa)) : '';
                }

                var html = '';
                html += itemText('fa-user', 'مشتری', s.customer_line, true, '');
                html += card('fa-heading', 'عنوان', '<span class="ctx-detail-card__text">' + esc(s.title) + '</span>', true, '');
                if (s.detail) {
                    html += card(
                        'fa-align-right',
                        'شرح',
                        '<span class="ctx-detail-card__text ctx-detail-card__text--pre">' + esc(s.detail) + '</span>',
                        true,
                        ''
                    );
                }
                html += itemText('fa-hashtag', 'شناسه تراکنش', String(s.id), false, 'ctx-detail-card__text--ltr');
                html += card(
                    'fa-signal',
                    'وضعیت',
                    '<span class="ctx-badge ctx-badge--' + tone + '">' + esc(s.status_label_fa) + '</span>',
                    false,
                    ''
                );
                html += itemText('fa-tags', 'نوع تراکنش', s.kind_label_fa, false, '');
                html += itemText('fa-coins', 'مبلغ (تومان)', s.amount_fa, false, '');
                html += itemText('fa-money-bill-wave', 'مبلغ (ریال)', s.amount_rial_fa, false, '');
                html += itemText('fa-building-columns', 'درگاه', s.gateway_label_fa, false, '');
                html += itemText('fa-barcode', 'شماره پیگیری', s.track_id_fa, false, 'ctx-detail-card__text--ltr');
                html += itemText('fa-receipt', 'مرجع بانک', s.bank_reference_fa, false, 'ctx-detail-card__text--ltr');
                html += itemText('fa-link', 'منبع سیستمی', s.source_short, false, 'ctx-detail-card__text--ltr');
                if (s.failure_reason) {
                    html += card(
                        'fa-triangle-exclamation',
                        'پیام خطا / توضیح',
                        '<span class="ctx-detail-card__text ctx-detail-card__text--pre">' + esc(s.failure_reason) + '</span>',
                        true,
                        'ctx-detail-card--alert'
                    );
                }
                html += cardTimes(s.created_at_fa, s.updated_at_fa);
                fieldsEl.innerHTML = html;

                if (s.meta_json && metaBlock && metaPre) {
                    metaBlock.style.display = '';
                    metaBlock.removeAttribute('hidden');
                    metaPre.textContent = s.meta_json;
                } else if (metaBlock) {
                    metaBlock.style.display = 'none';
                    metaBlock.setAttribute('hidden', 'hidden');
                    metaPre.textContent = '';
                }

                if (custLink && s.customer_profile_url) {
                    custLink.href = s.customer_profile_url;
                    custLink.style.display = 'inline-flex';
                } else if (custLink) {
                    custLink.style.display = 'none';
                }

                if (typeof dialog.showModal === 'function') dialog.showModal();
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        fitDetailCards();
                    });
                });
            }

            function closeDlg() {
                if (dialog && dialog.open) dialog.close();
                if (subEl) subEl.textContent = '';
            }

            document.querySelectorAll('.ctx-open-detail').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openDetail(btn.getAttribute('data-id'));
                });
            });
            document.querySelectorAll('[data-ctx-close]').forEach(function (b) {
                b.addEventListener('click', closeDlg);
            });
            if (dialog) {
                dialog.addEventListener('click', function (e) {
                    if (e.target === dialog) closeDlg();
                });
            }
            window.addEventListener('resize', function () {
                if (dialog && dialog.open) scheduleFitDetailCards();
            });
        })();
    </script>
    <script>
        (function () {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                return;
            }
            window.jQuery(function () {
                var common = {
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                };
                window.jQuery('#ctx-date-from, #ctx-date-to').each(function () {
                    var $el = window.jQuery(this);
                    var hasVal = String($el.val() || '').trim() !== '';
                    try {
                        if ($el.data('datepicker')) {
                            $el.pDatepicker('destroy');
                        }
                    } catch (e) { /* noop */ }
                    $el.pDatepicker(window.jQuery.extend({}, common, {
                        initialValue: hasVal
                    }));
                });
            });
        })();
    </script>
@endpush
