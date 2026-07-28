@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    @php
        $adminReportsDisplay = $adminReportsDisplay ?? \App\Support\AdminReportsDisplaySettings::resolved();
        $adminReportsDisplayStyle = \App\Support\AdminReportsDisplaySettings::inlineStyle($adminReportsDisplay);
    @endphp
    <style>
        .rpt-page {
            max-width: 100%;
            {{ $adminReportsDisplayStyle }}
        }
        .rpt-title { margin: 0 0 0.35rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .rpt-sub { margin: 0 0 1rem; font-size: 0.84rem; color: var(--muted); line-height: 1.6; }

        .rpt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.85rem;
        }

        .rpt-card {
            border: 1px solid var(--border);
            border-radius: 1rem;
            background: var(--bg-card);
            padding: 1rem 1rem 0.9rem;
            text-align: start;
            cursor: pointer;
            font-family: inherit;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            min-height: 9.5rem;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        html[data-theme="dark"] .rpt-card {
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.22);
        }

        .rpt-card:hover:not(:disabled) {
            transform: translateY(-2px);
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1);
        }

        .rpt-card:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .rpt-card__head {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .rpt-card__ico {
            width: 2.65rem;
            height: 2.65rem;
            border-radius: 0.8rem;
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            color: #fff;
        }

        .rpt-card__title { margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--text); line-height: 1.45; }
        .rpt-card__desc { margin: 0; font-size: 0.76rem; color: var(--muted); line-height: 1.65; flex: 1; }
        .rpt-card__foot {
            margin-top: auto;
            padding-top: 0.45rem;
            border-top: 1px dashed var(--border);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--primary-dark);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .rpt-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1250;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(2px);
            display: grid;
            place-items: center;
            padding: 0.75rem;
        }

        .rpt-modal-overlay[hidden] { display: none !important; }

        .rpt-modal {
            width: min(1280px, 100%);
            max-height: min(92vh, 920px);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        html[data-theme="dark"] .rpt-modal {
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.45);
        }

        .rpt-modal__head {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            flex-shrink: 0;
        }

        .rpt-modal__title { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text); }
        .rpt-modal__close {
            width: 2.1rem;
            height: 2.1rem;
            border: none;
            border-radius: 0.55rem;
            background: var(--primary-soft);
            color: var(--primary-dark);
            cursor: pointer;
            font-size: 1.1rem;
        }

        .rpt-modal__body {
            overflow: auto;
            padding: 0.65rem 0.55rem 0.75rem;
            -webkit-overflow-scrolling: touch;
        }

        .rpt-date-toolbar {
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.7rem 0.75rem;
            margin-bottom: 0.75rem;
            background: var(--bg-card);
        }

        .rpt-date-scope {
            margin: 0 0 0.55rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            text-align: center;
            line-height: 1.5;
        }

        .rpt-range-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            align-items: end;
            justify-content: center;
        }

        .rpt-range-field label {
            display: block;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 0.2rem;
        }

        .rpt-range-field input {
            width: 100%;
            min-width: 9.5rem;
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.48rem 0.62rem;
            background: var(--bg-card);
            color: var(--text);
            font-family: inherit;
        }

        .rpt-range-submit {
            border: none;
            border-radius: 0.62rem;
            padding: 0.52rem 0.95rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        .rpt-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            align-items: end;
            margin-bottom: 0.7rem;
        }

        .rpt-filters label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 0.2rem;
        }

        .rpt-filters input,
        .rpt-filters select {
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.45rem 0.6rem;
            background: var(--bg-card);
            color: var(--text);
            font-family: inherit;
            font-size: 0.8rem;
        }

        .rpt-filters .rpt-search-wrap { flex: 1 1 14rem; min-width: min(100%, 12rem); }

        .rpt-export-btn {
            border: 1px solid rgba(22, 163, 74, 0.38);
            border-radius: 0.62rem;
            padding: 0.48rem 0.72rem;
            background: rgba(34, 197, 94, 0.14);
            color: #166534;
            cursor: pointer;
            font-size: 0.76rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
            font-family: inherit;
            align-self: end;
        }

        .rpt-export-btn:hover { background: rgba(34, 197, 94, 0.22); }

        html[data-theme="dark"] .rpt-export-btn {
            color: #86efac;
            border-color: rgba(74, 222, 128, 0.4);
            background: rgba(22, 101, 52, 0.34);
        }
        .rpt-meta { font-size: 0.74rem; color: var(--muted); margin-bottom: 0.55rem; font-weight: 600; }

        .rpt-table-card { margin-top: 0.15rem; }

        .rpt-table-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: min(52vh, 520px);
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            background: var(--bg-card);
        }

        .rpt-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: var(--rpt-font-base, 0.67rem);
        }

        .rpt-table col.rpt-col-loan { width: 13%; }
        .rpt-table col.rpt-col-customer { width: 12%; }
        .rpt-table col.rpt-col-amount { width: 8%; }
        .rpt-table col.rpt-col-count { width: 4.5%; }
        .rpt-table col.rpt-col-inst { width: 7%; }
        .rpt-table col.rpt-col-date { width: 6%; }
        .rpt-table col.rpt-col-settled { width: 4%; }
        .rpt-table col.rpt-col-paid { width: 7%; }
        .rpt-table col.rpt-col-remain { width: 7%; }
        .rpt-table col.rpt-col-delay { width: 8%; }
        .rpt-table col.rpt-col-discount { width: 6.5%; }
        .rpt-table col.rpt-col-sms { width: 9%; }

        .rpt-table th,
        .rpt-table td {
            box-sizing: border-box;
            border: 1px solid rgba(148, 163, 184, 0.28);
            padding: var(--rpt-cell-py, 0.32rem) var(--rpt-cell-px, 0.28rem);
            text-align: var(--rpt-td-align, right);
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rpt-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: rgba(241, 245, 249, 0.92);
            font-weight: 800;
            font-size: var(--rpt-font-th, 0.64rem);
            color: var(--text);
            text-align: var(--rpt-th-align, center);
            line-height: 1.35;
            white-space: normal;
        }

        .rpt-page[data-rpt-header-mode="match"] .rpt-table th {
            text-align: var(--rpt-num-align, center);
        }

        .rpt-page[data-rpt-header-mode="match"] .rpt-table th.rpt-th-loan,
        .rpt-page[data-rpt-header-mode="match"] .rpt-table th.rpt-th-customer,
        .rpt-page[data-rpt-header-mode="match"] .rpt-table th.rpt-th-text {
            text-align: var(--rpt-td-align, right);
        }

        .rpt-table th.rpt-th-loan,
        .rpt-table th.rpt-th-customer,
        .rpt-table th.rpt-th-text {
            text-align: var(--rpt-td-align, right);
        }

        html[data-theme="dark"] .rpt-table th {
            background: rgba(30, 41, 59, 0.94);
        }

        .rpt-table td { color: var(--muted); line-height: 1.4; }

        .rpt-table .rpt-td--stack {
            white-space: normal;
            vertical-align: top;
            line-height: 1.45;
            overflow: hidden;
        }

        .rpt-table .rpt-td--num,
        .rpt-table .rpt-td--amount { text-align: var(--rpt-num-align, center); }

        .rpt-table .rpt-td--text { text-align: var(--rpt-td-align, right); white-space: normal; }

        .rpt-table .rpt-td--sms {
            white-space: normal;
            text-align: center;
            overflow: visible;
        }

        .rpt-amt-principal,
        .rpt-amt-total,
        .rpt-num { font-size: var(--rpt-font-num, 0.64rem); }

        .rpt-cell-stack {
            display: flex;
            flex-direction: column;
            gap: 0.06rem;
            align-items: var(--rpt-stack-items, center);
            min-width: 0;
            max-width: 100%;
            width: 100%;
        }

        .rpt-cell-stack--amount { align-items: var(--rpt-stack-items, center); gap: 0.1rem; }

        .rpt-cell-sub {
            font-size: calc(var(--rpt-font-stack, 0.66rem) * 0.95);
            color: var(--muted);
            line-height: 1.4;
            width: 100%;
            text-align: inherit;
            white-space: normal;
        }

        .rpt-cell-stack span,
        .rpt-cell-stack .rpt-link {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: var(--rpt-font-stack, 0.66rem);
        }

        .rpt-td--stack .rpt-cell-stack span {
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rpt-link {
            color: #0891b2;
            font-weight: 800;
            text-decoration: none;
            font-size: var(--rpt-font-link, 0.66rem);
        }

        html[data-theme="dark"] .rpt-link { color: #38bdf8; }

        .rpt-link:hover { text-decoration: underline; }

        .rpt-val-ltr {
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
        }

        .rpt-amt-principal { color: var(--text); font-weight: 700; }
        .rpt-amt-total { color: #15803d; font-weight: 800; }
        html[data-theme="dark"] .rpt-amt-total { color: #4ade80; }

        .rpt-num { font-weight: 700; color: var(--text); font-size: var(--rpt-font-num, 0.62rem); }
        .rpt-num--neg { color: #b91c1c; }

        .rpt-amt-principal,
        .rpt-amt-total { font-size: var(--rpt-font-num, 0.62rem); }

        .rpt-empty {
            padding: 1.5rem 1rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.84rem;
        }

        .rpt-sms-actions {
            display: inline-flex;
            gap: 0.18rem;
            flex-wrap: nowrap;
            justify-content: center;
        }

        .rpt-sms-btn {
            width: 1.38rem;
            height: 1.38rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            font-size: 0.64rem;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            display: inline-grid;
            place-items: center;
            line-height: 1;
        }

        .rpt-sms-btn--pre { border-color: rgba(14, 165, 233, 0.35); background: rgba(14, 165, 233, 0.12); color: #0369a1; }
        .rpt-sms-btn--due { border-color: rgba(249, 115, 22, 0.35); background: rgba(251, 146, 60, 0.14); color: #c2410c; }
        .rpt-sms-btn--over { border-color: rgba(239, 68, 68, 0.35); background: rgba(248, 113, 113, 0.14); color: #b91c1c; }
        .rpt-sms-btn--thanks { border-color: rgba(34, 197, 94, 0.35); background: rgba(74, 222, 128, 0.14); color: #15803d; }

        .rpt-table--installment-due col.rpt-col-id-customer { width: 12%; }
        .rpt-table--installment-due col.rpt-col-id-loan { width: 13%; }
        .rpt-table--installment-due col.rpt-col-id-inst { width: 7.5%; }
        .rpt-table--installment-due col.rpt-col-id-paid { width: 7.5%; }
        .rpt-table--installment-due col.rpt-col-id-due { width: 7%; }
        .rpt-table--installment-due col.rpt-col-id-deposit { width: 7%; }
        .rpt-table--installment-due col.rpt-col-id-method { width: 8%; }
        .rpt-table--installment-due col.rpt-col-id-early { width: 8%; }
        .rpt-table--installment-due col.rpt-col-id-notes { width: 9%; }
        .rpt-table--installment-due col.rpt-col-id-sms { width: 9%; }
        .rpt-table--installment-due col.rpt-col-id-ops { width: 5.5%; }

        .rpt-table--installment-due .rpt-td--text {
            white-space: normal;
            font-size: 0.64rem;
            line-height: 1.45;
        }

        .rpt-table--installment-due .rpt-td--ops {
            text-align: center;
            overflow: visible;
        }

        .rpt-ops {
            display: inline-flex;
            gap: 0.2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .rpt-op-btn {
            width: 1.55rem;
            height: 1.55rem;
            border-radius: 0.45rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--primary-dark);
            display: inline-grid;
            place-items: center;
            text-decoration: none;
            font-size: 0.72rem;
        }

        .rpt-op-btn:hover {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
        }

        .rpt-table--deposits-by-date col.rpt-col-dep-customer { width: 13%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-loan { width: 14%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-inst { width: 8%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-paid { width: 8%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-due { width: 7.5%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-deposit { width: 7.5%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-method { width: 9%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-early { width: 9%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-notes { width: 11%; }
        .rpt-table--deposits-by-date col.rpt-col-dep-ops { width: 5.5%; }

        .rpt-dep-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
            gap: 0.4rem;
            margin: 0 0 0.6rem;
        }
        .rpt-dep-summary[hidden] { display: none !important; }
        .rpt-dep-summary__card {
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            background: var(--bg-card);
            padding: 0.48rem 0.55rem 0.45rem;
            min-width: 0;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.035);
        }
        .rpt-dep-summary__card--total {
            border-color: rgba(37, 99, 235, 0.35);
            background: linear-gradient(160deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.02));
        }
        html[data-theme="dark"] .rpt-dep-summary__card--total {
            background: linear-gradient(160deg, rgba(37, 99, 235, 0.22), rgba(15, 23, 42, 0.2));
        }
        .rpt-dep-summary__label {
            margin: 0;
            font-size: 0.64rem;
            font-weight: 700;
            color: var(--muted);
            line-height: 1.35;
        }
        .rpt-dep-summary__value {
            margin: 0.18rem 0 0;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text);
            font-variant-numeric: tabular-nums;
            direction: ltr;
            unicode-bidi: plaintext;
            line-height: 1.25;
            word-break: break-word;
        }
        .rpt-dep-summary__card--total .rpt-dep-summary__value { color: var(--primary-dark, #1d4ed8); }
        .rpt-dep-summary__count {
            margin: 0.12rem 0 0;
            font-size: 0.59rem;
            font-weight: 650;
            color: var(--muted);
        }
        .rpt-dep-summary__note {
            grid-column: 1 / -1;
            margin: 0;
            font-size: 0.68rem;
            color: var(--muted);
            font-weight: 600;
            line-height: 1.55;
        }

        .rpt-table--deposits-by-date .rpt-td--text {
            white-space: normal;
            font-size: 0.64rem;
            line-height: 1.45;
        }

        .rpt-table--deposits-by-date .rpt-td--ops {
            text-align: center;
            overflow: visible;
        }

        .rpt-table--settled-members col.rpt-col-sm-first { width: 11%; }
        .rpt-table--settled-members col.rpt-col-sm-last { width: 12%; }
        .rpt-table--settled-members col.rpt-col-sm-mobile { width: 11%; }
        .rpt-table--settled-members col.rpt-col-sm-count { width: 9%; }
        .rpt-table--settled-members col.rpt-col-sm-total { width: 14%; }
        .rpt-table--settled-members col.rpt-col-sm-date { width: 12%; }

        .rpt-table--wallet-tx col.rpt-col-wtx-time { width: 11%; }
        .rpt-table--wallet-tx col.rpt-col-wtx-gateway { width: 9%; }
        .rpt-table--wallet-tx col.rpt-col-wtx-amount { width: 11%; }
        .rpt-table--wallet-tx col.rpt-col-wtx-details { width: 28%; }
        .rpt-table--wallet-tx col.rpt-col-wtx-final { width: 12%; }
        .rpt-table--wallet-tx col.rpt-col-wtx-notes { width: 18%; }

        .rpt-table--wallet-tx .rpt-td--amount-deposit .rpt-num { color: #047857; }
        .rpt-table--wallet-tx .rpt-td--amount-withdraw .rpt-num { color: #b91c1c; }

        .rpt-table--guarantees col.rpt-col-gr-loan { width: 16%; }
        .rpt-table--guarantees col.rpt-col-gr-customer { width: 16%; }
        .rpt-table--guarantees col.rpt-col-gr-amount { width: 11%; }
        .rpt-table--guarantees col.rpt-col-gr-inst { width: 11%; }
        .rpt-table--guarantees col.rpt-col-gr-type { width: 12%; }
        .rpt-table--guarantees col.rpt-col-gr-detail { width: 34%; }

        .rpt-table--guarantees .rpt-td--stack {
            white-space: normal;
            vertical-align: top;
        }

        .rpt-guarantee-highlight {
            display: block;
            color: #b91c1c;
            font-weight: 800;
            font-size: 0.68rem;
            margin-bottom: 0.12rem;
        }

        html[data-theme="dark"] .rpt-guarantee-highlight { color: #f87171; }

        .rpt-guarantee-summary {
            margin: 0 0 0.55rem;
            font-size: 0.68rem;
            line-height: 1.5;
            color: var(--muted);
            font-weight: 600;
            word-break: break-word;
        }

        .rpt-customer-picker {
            position: relative;
        }

        .rpt-customer-picker input[type="search"] {
            width: 100%;
            padding-inline-end: 2rem;
        }

        .rpt-customer-clear {
            position: absolute;
            inset-inline-end: 0.35rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.45rem;
            height: 1.45rem;
            border: none;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            cursor: pointer;
            font-size: 0.95rem;
            line-height: 1;
        }

        .rpt-customer-suggest {
            position: absolute;
            inset-inline: 0;
            top: calc(100% + 0.2rem);
            z-index: 5;
            max-height: 12rem;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            background: var(--bg-card);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .rpt-customer-suggest button {
            display: block;
            width: 100%;
            border: none;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: transparent;
            color: var(--text);
            text-align: right;
            padding: 0.45rem 0.55rem;
            font-family: inherit;
            font-size: 0.74rem;
            cursor: pointer;
        }

        .rpt-customer-suggest button:last-child {
            border-bottom: none;
        }

        .rpt-customer-suggest button:hover,
        .rpt-customer-suggest button:focus {
            background: var(--primary-soft);
        }

        .rpt-table--interest-fees col.rpt-col-lif-customer { width: 11%; }
        .rpt-table--interest-fees col.rpt-col-lif-loan { width: 12%; }
        .rpt-table--interest-fees col.rpt-col-lif-principal { width: 7.5%; }
        .rpt-table--interest-fees col.rpt-col-lif-profit { width: 7.5%; }
        .rpt-table--interest-fees col.rpt-col-lif-fee { width: 7.5%; }
        .rpt-table--interest-fees col.rpt-col-lif-repayable { width: 8%; }
        .rpt-table--interest-fees col.rpt-col-lif-rate { width: 9%; }
        .rpt-table--interest-fees col.rpt-col-lif-paid { width: 7%; }
        .rpt-table--interest-fees col.rpt-col-lif-remain { width: 7%; }
        .rpt-table--interest-fees col.rpt-col-lif-discount { width: 6%; }
        .rpt-table--interest-fees col.rpt-col-lif-settled { width: 4.5%; }
        .rpt-table--interest-fees col.rpt-col-lif-start { width: 6%; }

        .rpt-table--interest-fees .rpt-td--stack {
            white-space: normal;
            vertical-align: top;
        }

        .rpt-amt-profit { color: #7c3aed; font-weight: 800; }
        html[data-theme="dark"] .rpt-amt-profit { color: #c4b5fd; }

        .rpt-amt-fee { color: #b45309; font-weight: 800; }
        html[data-theme="dark"] .rpt-amt-fee { color: #fbbf24; }

        .rpt-quick-overlay {
            position: fixed;
            inset: 0;
            z-index: 1400;
            background: rgba(15, 23, 42, 0.5);
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .rpt-quick-overlay[hidden] { display: none !important; }

        .rpt-quick-modal {
            width: min(560px, 100%);
            max-height: min(88vh, 640px);
            overflow: auto;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
        }

        .rpt-quick-head {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .rpt-quick-head h2 { margin: 0; font-size: 0.92rem; font-weight: 800; }
        .rpt-quick-body { padding: 0.85rem 1rem 1rem; display: grid; gap: 0.65rem; }
        .rpt-quick-body label { font-size: 0.74rem; font-weight: 700; color: var(--muted); display: block; margin-bottom: 0.22rem; }
        .rpt-quick-body select,
        .rpt-quick-body textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.5rem 0.62rem;
            background: var(--bg-card);
            color: var(--text);
            font-family: inherit;
            font-size: 0.82rem;
        }

        .rpt-quick-body textarea { min-height: 7.5rem; resize: vertical; }
        .rpt-quick-foot { display: flex; gap: 0.45rem; justify-content: flex-end; flex-wrap: wrap; }
        .rpt-quick-btn {
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.45rem 0.85rem;
            background: var(--bg-card);
            cursor: pointer;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .rpt-quick-btn--pri {
            border: none;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff;
        }

        @media (max-width: 860px) {
            .rpt-modal-overlay {
                padding: 0;
                place-items: stretch;
            }

            .rpt-modal {
                width: 100%;
                max-height: 100vh;
                max-height: 100dvh;
                border-radius: 0;
                border-right: none;
                border-left: none;
            }

            .rpt-modal__head {
                padding: 0.7rem 0.65rem;
            }

            .rpt-modal__title {
                font-size: 0.86rem;
                line-height: 1.45;
            }

            .rpt-modal__body {
                padding: 0.55rem 0.5rem 0.65rem;
            }

            .rpt-range-form {
                flex-direction: column;
                align-items: stretch;
            }

            .rpt-range-field input {
                min-width: 0;
            }

            .rpt-range-submit {
                width: 100%;
            }

            .rpt-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .rpt-filters .rpt-search-wrap {
                flex: 1 1 auto;
                min-width: 0;
            }

            .rpt-export-btn {
                width: 100%;
                justify-content: center;
            }

            .rpt-table-wrap {
                overflow: hidden;
                max-height: none;
                border: none;
                background: transparent;
                width: 100%;
            }

            .rpt-table {
                display: block;
                width: 100%;
                table-layout: auto;
                min-width: 0;
            }

            .rpt-table colgroup,
            .rpt-table thead {
                display: none;
            }

            .rpt-table tbody {
                display: block;
                width: 100%;
            }

            .rpt-table tbody tr.rpt-data-row {
                display: block;
                width: 100%;
                margin-bottom: 0.7rem;
                padding: 0.1rem 0;
                border: 1px solid var(--border);
                border-radius: 0.75rem;
                background: var(--bg-card);
                box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            html[data-theme="dark"] .rpt-table tbody tr.rpt-data-row {
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            }

            .rpt-table tbody tr.rpt-data-row td {
                display: grid;
                grid-template-columns: minmax(4.6rem, 30%) minmax(0, 1fr);
                gap: 0.2rem 0.5rem;
                align-items: start;
                width: 100%;
                max-width: 100%;
                border: none;
                border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
                padding: 0.48rem 0.5rem;
                white-space: normal;
                overflow: visible;
                text-overflow: unset;
                text-align: start;
                word-break: break-word;
            }

            .rpt-table tbody tr.rpt-data-row td.rpt-td--empty {
                display: none;
            }

            .rpt-table tbody tr.rpt-data-row td::before {
                content: attr(data-label);
                font-weight: 800;
                font-size: 0.68rem;
                color: var(--text);
                line-height: 1.45;
            }

            .rpt-table tbody tr.rpt-data-row td:last-child {
                border-bottom: none;
            }

            .rpt-table tbody tr.rpt-data-row td.rpt-td--num,
            .rpt-table tbody tr.rpt-data-row td.rpt-td--amount {
                text-align: var(--rpt-num-align, center);
            }

            .rpt-table tbody tr.rpt-data-row td.rpt-td--sms,
            .rpt-table tbody tr.rpt-data-row td.rpt-td--ops {
                grid-template-columns: minmax(4.8rem, 34%) 1fr;
            }

            .rpt-table tbody tr.rpt-data-row td.rpt-td--sms .rpt-sms-actions,
            .rpt-table tbody tr.rpt-data-row td.rpt-td--ops .rpt-ops {
                justify-content: flex-start;
            }

            .rpt-cell-stack {
                align-items: var(--rpt-stack-items, flex-start);
                min-width: 0;
            }

            .rpt-cell-stack span,
            .rpt-cell-stack .rpt-link {
                white-space: normal;
                display: block;
                -webkit-line-clamp: unset;
                -webkit-box-orient: unset;
                overflow: visible;
                text-overflow: unset;
                font-size: 0.72rem;
            }

            .rpt-td--stack .rpt-cell-stack span {
                display: block;
            }

            .rpt-cell-stack--amount {
                align-items: var(--rpt-stack-items, flex-start);
            }

            .rpt-sms-btn {
                width: 1.55rem;
                height: 1.55rem;
                font-size: 0.7rem;
            }

            .rpt-table tbody tr:not(.rpt-data-row) td.rpt-empty {
                display: block;
                border: 1px dashed var(--border);
                border-radius: 0.75rem;
                padding: 1.25rem 0.75rem;
                text-align: center;
            }

            .rpt-table tbody tr:not(.rpt-data-row) td.rpt-empty::before {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <div class="rpt-page" data-rpt-header-mode="{{ $adminReportsDisplay['header_mode'] ?? 'match' }}">
        <h1 class="rpt-title">گزارش‌ها</h1>
        <p class="rpt-sub">گزارش‌های تحلیلی و عملیاتی سامانه. هر گزارش در پنجرهٔ جدا با فیلتر تاریخ و جستجو نمایش داده می‌شود.</p>

        <div class="rpt-grid">
            @foreach ($reportCards as $card)
                <button
                    type="button"
                    class="rpt-card"
                    data-rpt-open="{{ $card['id'] }}"
                    @disabled(empty($card['enabled']))
                >
                    <div class="rpt-card__head">
                        <span class="rpt-card__ico" style="background: linear-gradient(145deg, {{ $card['accent'] }}, color-mix(in srgb, {{ $card['accent'] }} 70%, #0f172a));">
                            <i class="fa-solid {{ $card['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <h2 class="rpt-card__title">{{ $card['title'] }}</h2>
                    </div>
                    <p class="rpt-card__desc">{{ $card['description'] }}</p>
                    <span class="rpt-card__foot">
                        مشاهده گزارش
                        <i class="fa-solid fa-arrow-left-long" aria-hidden="true"></i>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="rpt-modal-overlay" id="rpt-modal-member-loans" hidden aria-hidden="true">
        <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-member-loans-title">
            <div class="rpt-modal__head">
                <h2 class="rpt-modal__title" id="rpt-modal-member-loans-title">وام‌های اعضا بر اساس تاریخ</h2>
                <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
            </div>
            <div class="rpt-modal__body">
                <div class="rpt-date-toolbar">
                    <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ شروع وام</strong></p>
                    <form class="rpt-range-form" id="rpt-member-loans-date-form">
                        <div class="rpt-range-field">
                            <label for="rpt-ml-from">از تاریخ</label>
                            <input type="text" id="rpt-ml-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                        </div>
                        <div class="rpt-range-field">
                            <label for="rpt-ml-to">تا تاریخ</label>
                            <input type="text" id="rpt-ml-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                        </div>
                        <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                    </form>
                </div>

                <div class="rpt-filters">
                    <div class="rpt-search-wrap">
                        <label for="rpt-ml-search">جستجو</label>
                        <input type="search" id="rpt-ml-search" placeholder="مشتری، کد ملی، موبایل، کد پرونده…" autocomplete="off">
                    </div>
                    <div>
                        <label for="rpt-ml-settled">تسویه پرونده</label>
                        <select id="rpt-ml-settled">
                            <option value="">همه</option>
                            <option value="no">خیر</option>
                            <option value="yes">بلی</option>
                        </select>
                    </div>
                    <a
                        id="rpt-ml-export-excel"
                        class="rpt-export-btn"
                        href="{{ route('admin.reports.member-loans-by-date.export-excel') }}"
                        title="خروجی اکسل مطابق فیلترهای فعلی"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                </div>

                <p class="rpt-meta" id="rpt-ml-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

                <div class="rpt-table-card">
                    <div class="rpt-table-wrap">
                        <table class="rpt-table">
                            <colgroup>
                                <col class="rpt-col-loan">
                                <col class="rpt-col-customer">
                                <col class="rpt-col-amount">
                                <col class="rpt-col-count">
                                <col class="rpt-col-inst">
                                <col class="rpt-col-date">
                                <col class="rpt-col-settled">
                                <col class="rpt-col-paid">
                                <col class="rpt-col-remain">
                                <col class="rpt-col-delay">
                                <col class="rpt-col-discount">
                                <col class="rpt-col-sms">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="rpt-th-loan">عنوان وام</th>
                                    <th scope="col" class="rpt-th-customer">مشتری</th>
                                    <th scope="col" class="rpt-th-amount">مبلغ وام</th>
                                    <th scope="col" class="rpt-th-count">اقساط</th>
                                    <th scope="col" class="rpt-th-inst">مبلغ قسط</th>
                                    <th scope="col" class="rpt-th-date">شروع</th>
                                    <th scope="col" class="rpt-th-settled">تسویه</th>
                                    <th scope="col" class="rpt-th-paid">پرداختی</th>
                                    <th scope="col" class="rpt-th-remain">مانده</th>
                                    <th scope="col" class="rpt-th-delay">تأخیر</th>
                                    <th scope="col" class="rpt-th-discount">تخفیف</th>
                                    <th scope="col" class="rpt-th-sms">پیامک</th>
                                </tr>
                            </thead>
                            <tbody id="rpt-ml-tbody">
                                <tr>
                                    <td colspan="12" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rpt-modal-overlay" id="rpt-modal-installment-due" hidden aria-hidden="true">
        <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-installment-due-title">
            <div class="rpt-modal__head">
                <h2 class="rpt-modal__title" id="rpt-modal-installment-due-title">سررسید اقساط بر اساس تاریخ</h2>
                <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
            </div>
            <div class="rpt-modal__body">
                <div class="rpt-date-toolbar">
                    <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ سررسید قسط</strong></p>
                    <form class="rpt-range-form" id="rpt-installment-due-date-form">
                        <div class="rpt-range-field">
                            <label for="rpt-id-from">از تاریخ</label>
                            <input type="text" id="rpt-id-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                        </div>
                        <div class="rpt-range-field">
                            <label for="rpt-id-to">تا تاریخ</label>
                            <input type="text" id="rpt-id-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                        </div>
                        <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                    </form>
                </div>

                <div class="rpt-filters">
                    <div class="rpt-search-wrap">
                        <label for="rpt-id-search">جستجو</label>
                        <input type="search" id="rpt-id-search" placeholder="مشتری، کد ملی، موبایل، کد پرونده…" autocomplete="off">
                    </div>
                    <div>
                        <label for="rpt-id-payment-status">وضعیت قسط</label>
                        <select id="rpt-id-payment-status">
                            <option value="">همه</option>
                            <option value="paid">پرداخت‌شده</option>
                            <option value="partial">پرداخت جزئی</option>
                            <option value="unpaid">پرداخت‌نشده</option>
                        </select>
                    </div>
                    <div>
                        <label for="rpt-id-overdue">معوق</label>
                        <select id="rpt-id-overdue">
                            <option value="">همه</option>
                            <option value="yes">فقط معوق</option>
                            <option value="no">غیرمعوق</option>
                        </select>
                    </div>
                    <a
                        id="rpt-id-export-excel"
                        class="rpt-export-btn"
                        href="{{ route('admin.reports.installment-due-by-date.export-excel') }}"
                        title="خروجی اکسل مطابق فیلترهای فعلی"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                </div>

                <p class="rpt-meta" id="rpt-id-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

                <div class="rpt-dep-summary" id="rpt-id-summary" hidden aria-live="polite"></div>

                <div class="rpt-table-card">
                    <div class="rpt-table-wrap">
                        <table class="rpt-table rpt-table--installment-due">
                            <colgroup>
                                <col class="rpt-col-id-customer">
                                <col class="rpt-col-id-loan">
                                <col class="rpt-col-id-inst">
                                <col class="rpt-col-id-paid">
                                <col class="rpt-col-id-due">
                                <col class="rpt-col-id-deposit">
                                <col class="rpt-col-id-method">
                                <col class="rpt-col-id-early">
                                <col class="rpt-col-id-notes">
                                <col class="rpt-col-id-sms">
                                <col class="rpt-col-id-ops">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">مشتری</th>
                                    <th scope="col">اطلاعات وام</th>
                                    <th scope="col">مبلغ قسط</th>
                                    <th scope="col">مبلغ واریزی</th>
                                    <th scope="col">تاریخ سررسید</th>
                                    <th scope="col">تاریخ واریز</th>
                                    <th scope="col">نحوه پرداخت</th>
                                    <th scope="col">دیرکرد/زودکرد</th>
                                    <th scope="col">توضیحات</th>
                                    <th scope="col">پیامک‌ها</th>
                                    <th scope="col">عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="rpt-id-tbody">
                                <tr>
                                    <td colspan="11" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rpt-modal-overlay" id="rpt-modal-deposits-by-date" hidden aria-hidden="true">
        <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-deposits-by-date-title">
            <div class="rpt-modal__head">
                <h2 class="rpt-modal__title" id="rpt-modal-deposits-by-date-title">واریزها بر اساس تاریخ</h2>
                <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
            </div>
            <div class="rpt-modal__body">
                <div class="rpt-date-toolbar">
                    <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ واریز</strong></p>
                    <form class="rpt-range-form" id="rpt-deposits-by-date-form">
                        <div class="rpt-range-field">
                            <label for="rpt-dep-from">از تاریخ</label>
                            <input type="text" id="rpt-dep-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                        </div>
                        <div class="rpt-range-field">
                            <label for="rpt-dep-to">تا تاریخ</label>
                            <input type="text" id="rpt-dep-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                        </div>
                        <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                    </form>
                </div>

                <div class="rpt-filters">
                    <div class="rpt-search-wrap">
                        <label for="rpt-dep-search">جستجو</label>
                        <input type="search" id="rpt-dep-search" placeholder="مشتری، کد ملی، موبایل، کد پرونده…" autocomplete="off">
                    </div>
                    <div>
                        <label for="rpt-dep-payment-method">نحوه پرداخت</label>
                        <select id="rpt-dep-payment-method">
                            <option value="">همه</option>
                            @foreach ($depositPaymentMethodOptions as $methodKey => $methodLabel)
                                <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <a
                        id="rpt-dep-export-excel"
                        class="rpt-export-btn"
                        href="{{ route('admin.reports.deposits-by-date.export-excel') }}"
                        title="خروجی اکسل مطابق فیلترهای فعلی"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                </div>

                <p class="rpt-meta" id="rpt-dep-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

                <div class="rpt-dep-summary" id="rpt-dep-summary" hidden aria-live="polite"></div>

                <div class="rpt-table-card">
                    <div class="rpt-table-wrap">
                        <table class="rpt-table rpt-table--deposits-by-date">
                            <colgroup>
                                <col class="rpt-col-dep-customer">
                                <col class="rpt-col-dep-loan">
                                <col class="rpt-col-dep-inst">
                                <col class="rpt-col-dep-paid">
                                <col class="rpt-col-dep-due">
                                <col class="rpt-col-dep-deposit">
                                <col class="rpt-col-dep-method">
                                <col class="rpt-col-dep-early">
                                <col class="rpt-col-dep-notes">
                                <col class="rpt-col-dep-ops">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">مشتری</th>
                                    <th scope="col">وام</th>
                                    <th scope="col">مبلغ قسط</th>
                                    <th scope="col">مبلغ واریزی</th>
                                    <th scope="col">تاریخ سررسید</th>
                                    <th scope="col">تاریخ واریز</th>
                                    <th scope="col">نحوه پرداخت</th>
                                    <th scope="col">دیرکرد/زودکرد</th>
                                    <th scope="col">توضیحات</th>
                                    <th scope="col">عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="rpt-dep-tbody">
                                <tr>
                                    <td colspan="10" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rpt-modal-overlay" id="rpt-modal-settled-members" hidden aria-hidden="true">
        <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-settled-members-title">
            <div class="rpt-modal__head">
                <h2 class="rpt-modal__title" id="rpt-modal-settled-members-title">اعضایی که وام‌های خود را تسویه نموده‌اند</h2>
                <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
            </div>
            <div class="rpt-modal__body">
                <div class="rpt-date-toolbar">
                    <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ تسویهٔ رسمی</strong> یا <strong>آخرین واریز</strong> (برای وام‌هایی که با پرداخت اقساط تسویه شده‌اند)</p>
                    <form class="rpt-range-form" id="rpt-settled-members-date-form">
                        <div class="rpt-range-field">
                            <label for="rpt-sm-from">از تاریخ</label>
                            <input type="text" id="rpt-sm-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                        </div>
                        <div class="rpt-range-field">
                            <label for="rpt-sm-to">تا تاریخ</label>
                            <input type="text" id="rpt-sm-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                        </div>
                        <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                    </form>
                </div>

                <div class="rpt-filters">
                    <div class="rpt-search-wrap">
                        <label for="rpt-sm-search">جستجو</label>
                        <input type="search" id="rpt-sm-search" placeholder="نام، نام خانوادگی، موبایل، کد ملی…" autocomplete="off">
                    </div>
                    <div>
                        <label for="rpt-sm-min-loans">حداقل تعداد وام تسویه‌شده</label>
                        <select id="rpt-sm-min-loans">
                            <option value="1">۱ وام به بالا</option>
                            <option value="2">۲ وام به بالا</option>
                            <option value="3">۳ وام به بالا</option>
                        </select>
                    </div>
                    <a
                        id="rpt-sm-export-excel"
                        class="rpt-export-btn"
                        href="{{ route('admin.reports.settled-members.export-excel') }}"
                        title="خروجی اکسل مطابق فیلترهای فعلی"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                </div>

                <p class="rpt-meta" id="rpt-sm-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

                <div class="rpt-table-card">
                    <div class="rpt-table-wrap">
                        <table class="rpt-table rpt-table--settled-members">
                            <colgroup>
                                <col class="rpt-col-sm-first">
                                <col class="rpt-col-sm-last">
                                <col class="rpt-col-sm-mobile">
                                <col class="rpt-col-sm-count">
                                <col class="rpt-col-sm-total">
                                <col class="rpt-col-sm-date">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">نام</th>
                                    <th scope="col">نام خانوادگی</th>
                                    <th scope="col">موبایل</th>
                                    <th scope="col">تعداد وام</th>
                                    <th scope="col">مجموع وام‌ها</th>
                                    <th scope="col">تاریخ آخرین تسویه</th>
                                </tr>
                            </thead>
                            <tbody id="rpt-sm-tbody">
                                <tr>
                                    <td colspan="6" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rpt-modal-overlay" id="rpt-modal-wallet-transactions-by-date" hidden aria-hidden="true">
        <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-wallet-tx-title">
            <div class="rpt-modal__head">
                <h2 class="rpt-modal__title" id="rpt-modal-wallet-tx-title">لیست واریز/برداشت‌های کیف پول</h2>
                <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
            </div>
            <div class="rpt-modal__body">
                <div class="rpt-date-toolbar">
                    <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>زمان تراکنش در دفتر مالی</strong> (همان نمای پنل کاربر) و <strong>ثبت کیف پول</strong></p>
                    <form class="rpt-range-form" id="rpt-wallet-tx-date-form">
                        <div class="rpt-range-field">
                            <label for="rpt-wtx-from">از تاریخ</label>
                            <input type="text" id="rpt-wtx-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                        </div>
                        <div class="rpt-range-field">
                            <label for="rpt-wtx-to">تا تاریخ</label>
                            <input type="text" id="rpt-wtx-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                        </div>
                        <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                    </form>
                </div>

                <div class="rpt-filters">
                    <div class="rpt-search-wrap">
                        <label for="rpt-wtx-search">جستجو</label>
                        <input type="search" id="rpt-wtx-search" placeholder="مشتری، موبایل، کد ملی، شناسه پیگیری…" autocomplete="off">
                    </div>
                    <div>
                        <label for="rpt-wtx-direction">نوع تراکنش</label>
                        <select id="rpt-wtx-direction">
                            <option value="">همه</option>
                            @foreach ($walletTransactionDirectionOptions as $dirKey => $dirLabel)
                                <option value="{{ $dirKey }}">{{ $dirLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="rpt-wtx-source">منبع / درگاه</label>
                        <select id="rpt-wtx-source">
                            <option value="">همه</option>
                            @foreach ($walletTransactionSourceOptions as $srcKey => $srcLabel)
                                <option value="{{ $srcKey }}">{{ $srcLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <a
                        id="rpt-wtx-export-excel"
                        class="rpt-export-btn"
                        href="{{ route('admin.reports.wallet-transactions-by-date.export-excel') }}"
                        title="خروجی اکسل مطابق فیلترهای فعلی"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                </div>

                <p class="rpt-meta" id="rpt-wtx-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

                <div class="rpt-table-card">
                    <div class="rpt-table-wrap">
                        <table class="rpt-table rpt-table--wallet-tx">
                            <colgroup>
                                <col class="rpt-col-wtx-time">
                                <col class="rpt-col-wtx-gateway">
                                <col class="rpt-col-wtx-amount">
                                <col class="rpt-col-wtx-details">
                                <col class="rpt-col-wtx-final">
                                <col class="rpt-col-wtx-notes">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">زمان</th>
                                    <th scope="col">درگاه</th>
                                    <th scope="col">مبلغ</th>
                                    <th scope="col">جزئیات تراکنش</th>
                                    <th scope="col">ثبت نهایی پرداخت</th>
                                    <th scope="col">توضیحات</th>
                                </tr>
                            </thead>
                            <tbody id="rpt-wtx-tbody">
                                <tr>
                                    <td colspan="6" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.reports.partials.modal-loan-guarantees')
    @include('admin.reports.partials.modal-loan-interest-fees')
    @include('admin.reports.partials.modal-admin-activity')

    <div class="rpt-quick-overlay" id="rpt-quick-sms-overlay" hidden aria-hidden="true">
        <div class="rpt-quick-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-quick-sms-title">
            <div class="rpt-quick-head">
                <div>
                    <h2 id="rpt-quick-sms-title">ارسال سریع پیامک</h2>
                    <p id="rpt-quick-sms-sub" style="margin:0.2rem 0 0;font-size:0.74rem;color:var(--muted);"></p>
                </div>
                <button type="button" class="rpt-modal__close" id="rpt-quick-sms-close" aria-label="بستن">&times;</button>
            </div>
            <form id="rpt-quick-sms-form" class="rpt-quick-body">
                <div>
                    <label for="rpt-quick-sms-template">قالب پیامک</label>
                    <select id="rpt-quick-sms-template" name="sms_template_id">
                        <option value="">— بدون قالب —</option>
                        @foreach ($quickSmsTemplates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rpt-quick-sms-text">متن پیامک</label>
                    <textarea id="rpt-quick-sms-text" name="sms_text" maxlength="1000" placeholder="متن پیامک را بنویسید…"></textarea>
                </div>
                <div class="rpt-quick-foot">
                    <button type="button" class="rpt-quick-btn" id="rpt-quick-sms-cancel">انصراف</button>
                    <button type="submit" class="rpt-quick-btn rpt-quick-btn--pri">ارسال</button>
                </div>
            </form>
        </div>
    </div>

    <script type="application/json" id="rpt-page-config">
        {!! json_encode([
            'memberLoansDataUrl' => route('admin.reports.member-loans-by-date.data'),
            'memberLoansExportUrl' => route('admin.reports.member-loans-by-date.export-excel'),
            'installmentDueDataUrl' => route('admin.reports.installment-due-by-date.data'),
            'installmentDueExportUrl' => route('admin.reports.installment-due-by-date.export-excel'),
            'depositsByDateDataUrl' => route('admin.reports.deposits-by-date.data'),
            'depositsByDateExportUrl' => route('admin.reports.deposits-by-date.export-excel'),
            'settledMembersDataUrl' => route('admin.reports.settled-members.data'),
            'settledMembersExportUrl' => route('admin.reports.settled-members.export-excel'),
            'walletTransactionsDataUrl' => route('admin.reports.wallet-transactions-by-date.data'),
            'walletTransactionsExportUrl' => route('admin.reports.wallet-transactions-by-date.export-excel'),
            'loanGuaranteesDataUrl' => route('admin.reports.loan-guarantees.data'),
            'loanGuaranteesExportUrl' => route('admin.reports.loan-guarantees.export-excel'),
            'loanInterestFeesDataUrl' => route('admin.reports.loan-interest-fees.data'),
            'loanInterestFeesExportUrl' => route('admin.reports.loan-interest-fees.export-excel'),
            'loanInterestFeesCustomersUrl' => route('admin.reports.loan-interest-fees.customers-search'),
            'adminActivityDataUrl' => route('admin.reports.admin-activity.data'),
            'adminActivityExportUrl' => route('admin.reports.admin-activity.export-excel'),
            'adminActivityAdminsUrl' => route('admin.reports.admin-activity.admins-search'),
            'customersBaseUrl' => url('admin/customers'),
            'csrf' => csrf_token(),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    @vite(['resources/js/admin-reports.js'])
@endpush
