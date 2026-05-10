<!DOCTYPE html>
<html lang="fa" dir="rtl" data-admin-ui-font="{{ $appUiFont }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titleMain }}</title>
    <link rel="stylesheet" href="{{ asset('css/iransans-fanum.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-ui-font-faces.css') }}">
    <style>
        @page { size: A4; margin: 11mm; }

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
            padding: 0.6rem 0.45rem 1.75rem;
            font-size: 10pt;
            line-height: 1.55;
            color: #0f172a;
            background: #f1f5f9;
            -webkit-font-smoothing: antialiased;
        }

        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            padding: 1rem 1.1rem 1.25rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
        }

        .doc-title {
            text-align: center;
            font-size: 1.42rem;
            margin: 0 0 0.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .doc-sub {
            text-align: center;
            margin: 0 0 1.15rem;
            font-size: 1.02rem;
            font-weight: 600;
            color: #334155;
        }

        .banner-revoked {
            background: linear-gradient(90deg, #fef2f2, #fee2e2);
            border: 1px solid rgba(185, 28, 28, 0.35);
            padding: 0.5rem 0.65rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            text-align: center;
            color: #7f1d1d;
            border-radius: 8px;
        }

        .tbl-wrap {
            border-radius: 9px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            margin-bottom: 1rem;
            background: #fff;
        }

        table.book {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 9.1pt;
        }
        table.book thead th {
            background: linear-gradient(180deg, #1e3a5f 0%, #1e40af 100%);
            color: #f8fafc;
            font-weight: 700;
            padding: 0.5rem 0.45rem;
            text-align: center;
            vertical-align: middle;
            border-bottom: 2px solid #172554;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        table.book thead th:not(:first-child) {
            border-inline-start: 1px solid rgba(248, 250, 252, 0.15);
        }
        table.book tbody td {
            padding: 0.48rem 0.45rem;
            vertical-align: top;
            text-align: right;
            border-bottom: 1px solid #e2e8f0;
            border-inline-start: 1px solid #e2e8f0;
            background: #fff;
        }
        table.book tbody td:first-child { border-inline-start: 0; }
        table.book tbody tr:last-child td { border-bottom: 0; }
        table.book tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        table.book.summary { font-size: 9.35pt; }
        table.book.summary tbody td {
            text-align: center;
            font-weight: 600;
            color: #1e293b;
        }

        table.book.detail { font-size: 7.45pt; table-layout: fixed; }
        table.book.detail thead th {
            padding: 0.38rem 0.22rem;
            line-height: 1.35;
            font-size: 7.1pt;
        }
        table.book.detail tbody td {
            padding: 0.32rem 0.22rem;
            word-wrap: break-word;
        }

        .pre-cell { white-space: pre-line; text-align: right; line-height: 1.5; }

        .portal-block {
            margin: 0.9rem 0 0.55rem;
            padding: 0.65rem 0.75rem;
            font-size: 9.1pt;
            line-height: 1.85;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #334155;
        }
        .portal-block strong { color: #0f172a; font-weight: 700; word-break: break-all; }

        .sig-row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            gap: 1.5rem;
            margin-top: 2.4rem;
            page-break-inside: avoid;
        }
        .sig-block {
            flex: 1 1 40%;
            border-top: 1px dashed #64748b;
            padding-top: 2.6rem;
            text-align: center;
            font-size: 9.1pt;
            min-height: 4.75rem;
            color: #334155;
        }
        .sig-block p { margin: 0.18rem 0; }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .tbl-wrap {
                border-radius: 0;
                box-shadow: none;
            }
            table.book thead { display: table-header-group; }
            table.book { page-break-inside: auto; }
            table.book tr { page-break-inside: avoid; page-break-after: auto; }
        }
    </style>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 280);
        });
    </script>
</head>
<body>
<div class="sheet">
@if (!empty($loanRevoked))
    <div class="banner-revoked">توجه: این قرارداد در سامانه به‌صورت «فسخ‌شده» ثبت شده است؛ جدول زیر بر اساس دادهٔ ذخیره‌شده نمایش داده می‌شود.</div>
@endif

<h1 class="doc-title">{{ $titleMain }}</h1>
<p class="doc-sub">{{ $subtitleSales }}</p>

<div class="tbl-wrap">
<table class="book summary">
    <thead>
    <tr>
        <th>شماره و عنوان پرونده</th>
        <th>نام و نام خانوادگی</th>
        <th>تاریخ قرارداد</th>
        <th>تعداد اقساط / مبلغ هر قسط</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td class="pre-cell">{{ $loanFileSummary }}</td>
        <td class="pre-cell">{{ $borrowerFullName }}</td>
        <td class="pre-cell">{{ $contractDateFa }}</td>
        <td class="pre-cell">تعداد اقساط: {{ $installmentsCountDisplay }}
مبلغ هر قسط: {{ $installmentAmountDisplay }}</td>
    </tr>
    </tbody>
</table>
</div>

<div class="tbl-wrap">
<table class="book detail">
    <thead>
    <tr>
        <th>شماره قسط</th>
        <th>تاریخ سررسید</th>
        <th>تاریخ پرداخت</th>
        <th>مبالغ پرداختی</th>
        <th>زود کرد</th>
        <th>دیرکرد</th>
        <th>جریمه</th>
        <th>شماره کارت</th>
        <th>نقدی</th>
        <th>واریزی</th>
        <th>کارتخوان</th>
        <th>توضیحات</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($bookletRows as $r)
        <tr>
            <td class="pre-cell">{{ $r['sequence_fa'] }}</td>
            <td class="pre-cell">{{ $r['due_fa'] }}</td>
            <td class="pre-cell">{{ $r['pay_dates_cell'] }}</td>
            <td class="pre-cell">{{ $r['amounts_paid_cell'] }}</td>
            <td class="pre-cell">{{ $r['early_cell'] }}</td>
            <td class="pre-cell">{{ $r['late_cell'] }}</td>
            <td class="pre-cell">{{ $r['penalty_cell'] }}</td>
            <td class="pre-cell">{{ $r['col_card_online'] }}</td>
            <td class="pre-cell">{{ $r['cash_cell'] }}</td>
            <td class="pre-cell">{{ $r['transfer_cell'] }}</td>
            <td class="pre-cell">{{ $r['terminal_cell'] }}</td>
            <td class="pre-cell">{{ $r['notes_cell'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" class="pre-cell" style="text-align:center;">ردیف قسطی برای نمایش وجود ندارد.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</div>

<div class="portal-block">
    <div>آدرس سایت جهت اطلاع از وضعیت اقساط و پرداخت آنلاین: <strong>{{ $portalUrl }}</strong></div>
    <div>نام کاربری: <strong>{{ $borrowerUsernameDisplay }}</strong></div>
</div>

<div class="sig-row">
    <div class="sig-block">
        <p><strong>امضا و اثر انگشت فروشنده</strong></p>
    </div>
    <div class="sig-block">
        <p><strong>امضا و اثر انگشت خریدار</strong></p>
        <p>{{ $borrowerTitleLine }}</p>
    </div>
</div>
</div>
</body>
</html>
