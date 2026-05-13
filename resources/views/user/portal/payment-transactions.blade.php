@extends('layouts.user.app')

@section('title', $pageTitle)

@push('head')
    <style>
        .portal-tx {
            width: 100%;
            max-width: 100%;
            padding: 0 0 1.5rem;
        }
        .portal-tx__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .portal-tx__title-wrap {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }
        .portal-tx__title-ico {
            font-size: 1.35rem;
            color: var(--primary, #2563eb);
        }
        .portal-tx__title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .portal-tx__hint {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.5;
            max-width: 36rem;
        }
        .portal-tx__search {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 0.45rem;
            width: 100%;
            max-width: 26rem;
        }
        .portal-tx__search input[type='search'] {
            flex: 1 1 10rem;
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            padding: 0.55rem 0.65rem;
            font: inherit;
            background: var(--bg-card);
            color: inherit;
        }
        .portal-tx__search input:focus {
            outline: 2px solid rgba(37, 99, 235, 0.35);
            outline-offset: 1px;
        }
        .portal-tx__search button {
            border: none;
            border-radius: 0.65rem;
            padding: 0.55rem 0.85rem;
            font-weight: 800;
            cursor: pointer;
            background: var(--primary, #2563eb);
            color: #fff;
        }
        .portal-tx__search a {
            align-self: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            text-decoration: underline;
            padding: 0.25rem 0.35rem;
        }
        .portal-tx__meta {
            font-size: 0.78rem;
            color: var(--muted);
            margin: 0 0 0.65rem;
        }
        .portal-tx__table-wrap {
            display: none;
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            overflow: hidden;
            background: var(--bg-card);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        html[data-theme='dark'] .portal-tx__table-wrap {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }
        .portal-tx__table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .portal-tx__table th,
        .portal-tx__table td {
            padding: 0.55rem 0.5rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        .portal-tx__table th {
            background: rgba(148, 163, 184, 0.12);
            font-weight: 900;
            white-space: nowrap;
        }
        .portal-tx__table tr:last-child td {
            border-bottom: none;
        }
        .portal-tx__table tbody tr:hover td {
            background: rgba(37, 99, 235, 0.04);
        }
        .portal-tx__ltr {
            direction: ltr;
            unicode-bidi: embed;
            text-align: left;
            display: inline-block;
            max-width: 100%;
            word-break: break-all;
        }
        .portal-tx__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            padding: 0.18rem 0.45rem;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .portal-tx__badge--ok {
            background: rgba(16, 185, 129, 0.18);
            color: #047857;
        }
        html[data-theme='dark'] .portal-tx__badge--ok {
            color: #6ee7b7;
        }
        .portal-tx__badge--danger {
            background: rgba(239, 68, 68, 0.16);
            color: #b91c1c;
        }
        html[data-theme='dark'] .portal-tx__badge--danger {
            color: #fca5a5;
        }
        .portal-tx__badge--pending {
            background: rgba(245, 158, 11, 0.2);
            color: #b45309;
        }
        .portal-tx__badge--muted {
            background: rgba(148, 163, 184, 0.22);
            color: var(--muted);
        }
        .portal-tx__fail {
            margin: 0.25rem 0 0;
            font-size: 0.68rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .portal-tx__cards {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .portal-tx__card {
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.65rem 0.72rem 0.72rem;
            background: var(--bg-card);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }
        html[data-theme='dark'] .portal-tx__card {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
        }
        .portal-tx__card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.45rem;
        }
        .portal-tx__card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 900;
            line-height: 1.35;
        }
        .portal-tx__card-dl {
            margin: 0;
            display: grid;
            gap: 0.35rem 0.5rem;
        }
        .portal-tx__card-row {
            display: grid;
            grid-template-columns: minmax(0, 7.2rem) 1fr;
            gap: 0.35rem;
            font-size: 0.76rem;
            align-items: baseline;
        }
        .portal-tx__card-dt {
            margin: 0;
            color: var(--muted);
            font-weight: 800;
        }
        .portal-tx__card-dd {
            margin: 0;
            font-weight: 700;
            word-break: break-word;
        }
        .portal-tx__card-foot {
            margin-top: 0.55rem;
            padding-top: 0.45rem;
            border-top: 1px dashed var(--border);
            font-size: 0.68rem;
            color: var(--muted);
        }
        .portal-tx__pager {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
        }
        .portal-tx__pager-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 0.45rem 0.65rem;
            padding: 0.45rem 0.65rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            background: var(--bg-card);
        }
        .portal-tx__pager a,
        .portal-tx__pager span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 5.5rem;
            padding: 0.4rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
        }
        .portal-tx__pager a {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary, #2563eb);
        }
        .portal-tx__pager span.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: rgba(148, 163, 184, 0.15);
            color: var(--muted);
        }
        .portal-tx__pager-meta {
            font-size: 0.74rem;
            color: var(--muted);
            font-weight: 700;
        }
        .portal-tx__empty {
            text-align: center;
            padding: 2rem 1rem;
            border: 1px dashed var(--border);
            border-radius: 0.85rem;
            color: var(--muted);
        }
        .portal-tx__empty-ico {
            font-size: 2rem;
            margin-bottom: 0.35rem;
            opacity: 0.55;
        }
        @media (min-width: 960px) {
            .portal-tx__table-wrap {
                display: block;
            }
            .portal-tx__cards {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <section class="portal-tx" aria-labelledby="portal-tx-title">
        <header class="portal-tx__head">
            <div>
                <div class="portal-tx__title-wrap">
                    <i class="fa-solid fa-receipt portal-tx__title-ico" aria-hidden="true"></i>
                    <h1 id="portal-tx-title" class="portal-tx__title">{{ $pageTitle }}</h1>
                </div>
                <p class="portal-tx__hint">
                    تاریخچهٔ تمام تراکنش‌های مالی شما (پرداخت قسط از درگاه، و در آینده عملیات کیف پول و …) در اینجا نمایش داده می‌شود. با نوع تراکنش، عنوان، شرح، شماره پیگیری یا مرجع بانکی جستجو کنید.
                </p>
            </div>
            <form class="portal-tx__search" method="get" action="{{ route('user.payment-transactions.index') }}" role="search">
                <label class="visually-hidden" for="portal-tx-q">جستجو در تراکنش‌ها</label>
                <input
                    id="portal-tx-q"
                    name="q"
                    type="search"
                    maxlength="120"
                    autocomplete="off"
                    placeholder="جستجو…"
                    value="{{ old('q', $searchQ ?? '') }}"
                >
                <button type="submit">جستجو</button>
                @if(($searchQ ?? '') !== '')
                    <a href="{{ route('user.payment-transactions.index') }}">پاک کردن</a>
                @endif
            </form>
        </header>

        <p class="portal-tx__meta">
            @if($rows->total() > 0)
                <span class="portal-tx__meta-count">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $rows->total()) }} مورد</span>
                @if($rows->lastPage() > 1)
                    — صفحه {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $rows->currentPage()) }}
                    از {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $rows->lastPage()) }}
                @endif
            @else
                موردی یافت نشد.
            @endif
        </p>

        @if($rows->isEmpty())
            <div class="portal-tx__empty">
                <div class="portal-tx__empty-ico" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></div>
                <p>هنوز تراکنشی ثبت نشده است یا نتیجهٔ جستجو خالی است.</p>
            </div>
        @else
            <div class="portal-tx__table-wrap">
                <table class="portal-tx__table">
                    <thead>
                        <tr>
                            <th scope="col">وضعیت</th>
                            <th scope="col">نوع تراکنش</th>
                            <th scope="col">عنوان</th>
                            <th scope="col">شرح</th>
                            <th scope="col">مبلغ</th>
                            <th scope="col">درگاه</th>
                            <th scope="col">شماره پیگیری</th>
                            <th scope="col">شماره تراکنش بانک</th>
                            <th scope="col">تاریخ و زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <span class="portal-tx__badge portal-tx__badge--{{ $row['status_tone'] ?? 'muted' }}">{{ $row['status_label_fa'] ?? '—' }}</span>
                                    @if(!empty($row['failure_reason_fa']))
                                        <p class="portal-tx__fail">{{ $row['failure_reason_fa'] }}</p>
                                    @endif
                                </td>
                                <td>{{ $row['kind_label_fa'] ?? '—' }}</td>
                                <td>{{ $row['title'] ?? '—' }}</td>
                                <td>{{ $row['detail'] ?? '—' }}</td>
                                <td>{{ $row['amount_fa'] ?? '—' }}</td>
                                <td>{{ $row['gateway_label_fa'] ?? '—' }}</td>
                                <td><span class="portal-tx__ltr">{{ $row['track_id_fa'] ?? '—' }}</span></td>
                                <td><span class="portal-tx__ltr">{{ $row['bank_ref_fa'] ?? '—' }}</span></td>
                                <td>{{ $row['datetime_fa'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="portal-tx__cards" role="list">
                @foreach ($rows as $row)
                    <article class="portal-tx__card" role="listitem">
                        <div class="portal-tx__card-head">
                            <h2 class="portal-tx__card-title">{{ $row['title'] ?? '—' }}</h2>
                            <span class="portal-tx__badge portal-tx__badge--{{ $row['status_tone'] ?? 'muted' }}">{{ $row['status_label_fa'] ?? '—' }}</span>
                        </div>
                        <dl class="portal-tx__card-dl">
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">نوع تراکنش</dt>
                                <dd class="portal-tx__card-dd">{{ $row['kind_label_fa'] ?? '—' }}</dd>
                            </div>
                            @if(!empty($row['detail']))
                                <div class="portal-tx__card-row">
                                    <dt class="portal-tx__card-dt">شرح</dt>
                                    <dd class="portal-tx__card-dd">{{ $row['detail'] }}</dd>
                                </div>
                            @endif
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">مبلغ</dt>
                                <dd class="portal-tx__card-dd">{{ $row['amount_fa'] ?? '—' }}</dd>
                            </div>
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">درگاه</dt>
                                <dd class="portal-tx__card-dd">{{ $row['gateway_label_fa'] ?? '—' }}</dd>
                            </div>
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">شماره پیگیری</dt>
                                <dd class="portal-tx__card-dd"><span class="portal-tx__ltr">{{ $row['track_id_fa'] ?? '—' }}</span></dd>
                            </div>
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">تراکنش بانک</dt>
                                <dd class="portal-tx__card-dd"><span class="portal-tx__ltr">{{ $row['bank_ref_fa'] ?? '—' }}</span></dd>
                            </div>
                            <div class="portal-tx__card-row">
                                <dt class="portal-tx__card-dt">تاریخ و زمان</dt>
                                <dd class="portal-tx__card-dd">{{ $row['datetime_fa'] ?? '—' }}</dd>
                            </div>
                        </dl>
                        @if(!empty($row['failure_reason_fa']))
                            <p class="portal-tx__fail">{{ $row['failure_reason_fa'] }}</p>
                        @endif
                        <div class="portal-tx__card-foot">
                            شناسه سامانه:
                            <span class="portal-tx__ltr">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($row['id'] ?? '')) }}</span>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($rows->lastPage() > 1)
                <nav class="portal-tx__pager" aria-label="صفحه‌بندی نتایج">
                    <div class="portal-tx__pager-inner">
                        @if($rows->onFirstPage())
                            <span class="is-disabled">قبلی</span>
                        @else
                            <a href="{{ $rows->previousPageUrl() }}" rel="prev">قبلی</a>
                        @endif
                        <span class="portal-tx__pager-meta">
                            صفحه {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $rows->currentPage()) }}
                            از {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $rows->lastPage()) }}
                        </span>
                        @if($rows->hasMorePages())
                            <a href="{{ $rows->nextPageUrl() }}" rel="next">بعدی</a>
                        @else
                            <span class="is-disabled">بعدی</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </section>
@endsection
