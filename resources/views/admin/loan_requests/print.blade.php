<!DOCTYPE html>
<html lang="fa" dir="rtl" data-admin-ui-font="{{ $appUiFont }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ — درخواست‌های وام</title>
    <link rel="stylesheet" href="{{ asset('css/iransans-fanum.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-ui-font-faces.css') }}">
    <style>
        @page { size: A4 landscape; margin: 11mm; }

        * { box-sizing: border-box; }

        html[data-admin-ui-font="iransans"] body {
            font-family: IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
        }
        html[data-admin-ui-font="iranyekan"] body {
            font-family: IRANYekan, IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
        }
        html[data-admin-ui-font="anjoman"] body {
            font-family: Anjoman, IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
        }
        html[data-admin-ui-font="estedad"] body {
            font-family: Estedad, IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
        }

        body {
            margin: 0;
            padding: 0.6rem 0.45rem 1.6rem;
            font-size: 9.5pt;
            line-height: 1.5;
            color: #0f172a;
            background: #f1f5f9;
            -webkit-font-smoothing: antialiased;
        }

        .sheet {
            max-width: 297mm;
            margin: 0 auto;
            padding: 1rem 1.1rem 1.25rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
        }

        .doc-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 0.85rem; padding-bottom: 0.55rem; margin-bottom: 0.7rem;
            border-bottom: 2px solid #1e40af;
        }
        .doc-head__title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            color: #1e3a8a;
            letter-spacing: 0.005em;
        }
        .doc-head__brand {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
        }
        .doc-head__meta {
            text-align: end;
            font-size: 0.74rem;
            color: #475569;
            line-height: 1.7;
        }
        .doc-head__meta strong { color: #0f172a; font-weight: 800; }

        .filters-card {
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
            margin: 0 0 0.7rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
            gap: 0.45rem 1.1rem;
            font-size: 0.75rem;
            color: #334155;
            background: #f8fafc;
        }
        .filters-card__row {
            display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: baseline;
        }
        .filters-card__row strong { color: #0f172a; font-weight: 800; }
        .filters-card__statuses {
            display: inline-flex; flex-wrap: wrap; gap: 0.25rem;
        }
        .filters-card__chip {
            display: inline-flex;
            padding: 0.05rem 0.4rem;
            border-radius: 999px;
            background: #e0e7ff;
            color: #1e3a8a;
            font-size: 0.7rem;
            font-weight: 800;
        }

        .table-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
        }

        table.print-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.6pt;
            background: #fff;
        }
        table.print-tbl thead {
            background: #e0e7ff;
            color: #1e3a8a;
        }
        table.print-tbl thead th {
            border: 1px solid #c7d2fe;
            padding: 0.45rem 0.4rem;
            font-weight: 800;
            font-size: 8.7pt;
            text-align: center;
            white-space: nowrap;
        }
        table.print-tbl tbody td {
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.4rem;
            text-align: center;
            vertical-align: middle;
            color: #0f172a;
            word-break: break-word;
        }
        table.print-tbl tbody tr:nth-child(even) td { background: #f8fafc; }

        .col-no { width: 5%; font-weight: 800; }
        .col-name { width: 11%; }
        .col-ccode { width: 6%; }
        .col-nid { width: 8%; }
        .col-loan { width: 12%; }
        .col-amount { width: 9%; white-space: nowrap; font-weight: 800; }
        .col-date { width: 9%; white-space: nowrap; }
        .col-status { width: 9%; font-weight: 800; }
        .col-note { width: 14%; text-align: justify !important; }
        .col-mobile { width: 8%; white-space: nowrap; }
        .col-city { width: 6%; }

        .empty-row td {
            padding: 1.1rem 0.5rem;
            color: #64748b;
            font-weight: 700;
            text-align: center;
        }

        .doc-foot {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 0.55rem; font-size: 0.7rem; color: #64748b;
            border-top: 1px dashed #cbd5e1; padding-top: 0.45rem;
        }

        .top-actions {
            position: fixed; top: 0.65rem; inset-inline-start: 0.85rem; z-index: 50;
            display: inline-flex; gap: 0.45rem;
        }
        .top-actions button, .top-actions a {
            font-family: inherit; font-size: 0.78rem; font-weight: 800;
            padding: 0.45rem 0.85rem; border-radius: 0.55rem; cursor: pointer;
            border: 1px solid #c7d2fe; color: #1e3a8a; background: #fff;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }
        .top-actions button:hover, .top-actions a:hover { background: #eef2ff; }
        .top-actions button.primary {
            background: linear-gradient(180deg, #2563eb, #1d4ed8); color: #fff; border-color: #1d4ed8;
        }
        .top-actions button.primary:hover { filter: brightness(1.05); }

        @media print {
            html, body { background: #fff !important; }
            body { padding: 0; }
            .sheet { max-width: none; margin: 0; padding: 0; border-radius: 0; box-shadow: none; }
            .top-actions { display: none !important; }
            table.print-tbl thead { display: table-header-group; }
            table.print-tbl { page-break-inside: auto; }
            table.print-tbl tr { page-break-inside: avoid; page-break-after: auto; }
            .doc-head { border-bottom-color: #1e3a8a; }
            .filters-card { background: #fff !important; }
            table.print-tbl tbody tr:nth-child(even) td {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            table.print-tbl thead {
                background: #e0e7ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 320);
        });
    </script>
</head>
<body>
<div class="top-actions" aria-hidden="false">
    <button type="button" class="primary" onclick="window.print()">چاپ</button>
    <button type="button" onclick="window.close()">بستن</button>
</div>

<div class="sheet">
    <header class="doc-head">
        <div>
            <h1 class="doc-head__title">گزارش درخواست‌های وام</h1>
            <div class="doc-head__brand">{{ $appDisplayName }}</div>
        </div>
        <div class="doc-head__meta">
            <div><strong>تاریخ تهیه:</strong> {{ $generatedAtFa }}</div>
            <div><strong>تعداد رکورد:</strong> {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($rows)) }}</div>
        </div>
    </header>

    <section class="filters-card" aria-label="فیلترهای اعمال‌شده">
        <div class="filters-card__row">
            <strong>بازهٔ تاریخ:</strong>
            <span>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($fromJDate) }}</span>
            <span>تا</span>
            <span>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($toJDate) }}</span>
        </div>
        <div class="filters-card__row">
            <strong>جست‌وجو:</strong>
            <span>{{ $search !== '' ? $search : '—' }}</span>
        </div>
        <div class="filters-card__row">
            <strong>وضعیت‌های انتخاب‌شده:</strong>
            @if (count($selectedStatusLabels) === 0)
                <span>همه</span>
            @else
                <span class="filters-card__statuses">
                    @foreach ($selectedStatusLabels as $lbl)
                        <span class="filters-card__chip">{{ $lbl }}</span>
                    @endforeach
                </span>
            @endif
        </div>
    </section>

    <div class="table-wrap">
        <table class="print-tbl">
            <thead>
                <tr>
                    <th class="col-no">شماره</th>
                    <th class="col-name">نام مشتری</th>
                    <th class="col-ccode">کد مشتری</th>
                    <th class="col-nid">کد ملی</th>
                    <th class="col-loan">نام وام درخواستی</th>
                    <th class="col-amount">مبلغ (تومان)</th>
                    <th class="col-date">تاریخ ثبت</th>
                    <th class="col-status">وضعیت جاری</th>
                    <th class="col-note">نظر کارشناس</th>
                    <th class="col-mobile">شمارهٔ تماس</th>
                    <th class="col-city">شهر</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="col-no">{{ $row['request_no_fa'] }}</td>
                        <td class="col-name">{{ $row['customer_name'] }}</td>
                        <td class="col-ccode">{{ $row['customer_code_fa'] }}</td>
                        <td class="col-nid">{{ $row['national_id_fa'] }}</td>
                        <td class="col-loan">{{ $row['loan_title'] }}</td>
                        <td class="col-amount">{{ $row['amount_fa'] }}</td>
                        <td class="col-date">{{ $row['datetime_fa'] }}</td>
                        <td class="col-status">{{ $row['status_label'] }}</td>
                        <td class="col-note">{{ $row['expert_note'] !== '' ? $row['expert_note'] : '—' }}</td>
                        <td class="col-mobile">{{ $row['mobile_fa'] }}</td>
                        <td class="col-city">{{ $row['city'] }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="11">رکوردی برای نمایش وجود ندارد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="doc-foot">
        <span>{{ $appDisplayName }}</span>
        <span>تهیه‌شده در {{ $generatedAtFa }}</span>
    </div>
</div>
</body>
</html>
