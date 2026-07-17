@extends('layouts.admin.app')

@section('title', 'لیست مشتریان')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    <style>
        .cust-page { max-width: 100%; }
        .cust-head { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .cust-title-wrap h1 { margin: 0 0 0.35rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .cust-title-wrap p { margin: 0; font-size: 0.8rem; color: var(--muted); line-height: 1.5; }
        .cust-add-btn {
            border: none; border-radius: 0.7rem; padding: 0.55rem 1rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.82rem; font-weight: 800; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.45rem;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.28);
        }
        .cust-add-btn:hover { filter: brightness(1.03); }
        .cust-reload-btn {
            border: 1px solid var(--border); border-radius: 0.7rem; padding: 0.55rem 0.7rem;
            background: var(--bg-card); color: var(--text); cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .cust-reload-btn:hover { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); }
        .cust-export-excel-btn {
            border: 1px solid rgba(16, 185, 129, 0.42);
            border-radius: 0.7rem;
            padding: 0.55rem 0.85rem;
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.18), rgba(5, 150, 105, 0.12));
            color: #047857;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);
        }
        .cust-export-excel-btn:hover { filter: brightness(1.04); border-color: rgba(5, 150, 105, 0.55); }
        html[data-theme="dark"] .cust-export-excel-btn {
            color: #6ee7b7;
            background: linear-gradient(180deg, rgba(5, 150, 105, 0.28), rgba(4, 120, 87, 0.18));
            border-color: rgba(52, 211, 153, 0.35);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
        }
        .cust-import-btn {
            border: 1px solid rgba(99, 102, 241, 0.45);
            border-radius: 0.7rem;
            padding: 0.55rem 0.85rem;
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.14), rgba(129, 140, 248, 0.08));
            color: var(--primary-dark);
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);
        }
        .cust-import-btn:hover { filter: brightness(1.04); border-color: rgba(79, 70, 229, 0.55); }
        html[data-theme="dark"] .cust-import-btn {
            color: #a5b4fc;
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.28), rgba(67, 56, 202, 0.15));
            border-color: rgba(165, 180, 252, 0.35);
        }
        #cust-import-overlay { z-index: 1450; }
        .cust-import-modal .cust-import-help {
            margin: 0.45rem 0 0.85rem;
            padding: 0 1.1rem 0 0;
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.75;
        }
        .cust-import-modal .cust-import-help li { margin-bottom: 0.35rem; }
        .cust-import-dl {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0.35rem 0 0.65rem;
            padding: 0.48rem 0.82rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(59, 130, 246, 0.45);
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 0.8rem;
            font-weight: 800;
            text-decoration: none;
        }
        .cust-import-dl:hover { filter: brightness(1.02); }
        .cust-import-file {
            margin-top: 0.35rem;
        }
        .cust-import-actions { display: inline-flex; flex-wrap: wrap; gap: 0.45rem; margin-top: 0.75rem; align-items: center; }
        .cust-search { flex: 1 1 16rem; max-width: 22rem; }
        .cust-search-row {
            display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center;
            flex: 1 1 auto; width: 100%;
        }
        .cust-search-row .cust-search { flex: 1 1 14rem; max-width: none; }
        .cust-list-scope {
            flex: 0 1 13rem; min-width: 11rem;
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.72rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.84rem;
        }
        .cust-search input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.72rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.84rem;
        }
        .cust-list-filter-banner {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.55rem;
            margin-bottom: 0.85rem; padding: 0.55rem 0.75rem; border-radius: 0.7rem;
            border: 1px solid rgba(37, 99, 235, 0.28); background: var(--primary-soft);
            font-size: 0.8rem; color: var(--text);
        }
        .cust-list-filter-banner strong { font-weight: 800; }
        .cust-list-filter-clear {
            display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem;
            border-radius: 0.55rem; border: 1px solid var(--border); background: var(--bg-card);
            color: var(--text); font-size: 0.76rem; font-weight: 800; text-decoration: none;
        }
        .cust-list-filter-clear:hover { border-color: rgba(37, 99, 235, 0.35); color: var(--primary-dark); }
        .cust-card {
            border: 1px solid var(--border); border-radius: 0.9rem; background: var(--bg-card);
            overflow: visible; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .cust-table-wrap {
            overflow: auto;
            max-height: min(70vh, calc(100dvh - 17rem));
            -webkit-overflow-scrolling: touch;
        }
        .cust-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8rem; }
        .cust-table th, .cust-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .cust-table th {
            background: var(--primary-soft); font-weight: 800; white-space: nowrap;
            position: sticky; top: 0; z-index: 2;
            box-shadow: 0 1px 0 var(--border);
        }
        .cust-th-sort a {
            color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;
        }
        .cust-th-sort a:hover { color: var(--primary-dark); }
        .cust-th-sort.is-active a { color: var(--primary-dark); }
        .cust-th-sort-icon { font-size: 0.62rem; opacity: 0.55; }
        .cust-th-sort.is-active .cust-th-sort-icon { opacity: 1; }
        .cust-main-text { font-size: 0.82rem; font-weight: 800; color: var(--text); line-height: 1.4; }
        .cust-name-link { color: inherit; text-decoration: none; border-bottom: 1px dashed rgba(37, 99, 235, 0.35); }
        .cust-name-link:hover { color: var(--primary-dark); }
        .cust-sub-text { font-size: 0.68rem; color: var(--muted); line-height: 1.55; margin-top: 0.1rem; }
        .cust-loan-count { font-size: 0.9rem; font-weight: 900; color: var(--text); line-height: 1.2; }
        .cust-loan-ids { font-size: 0.65rem; color: var(--muted); margin-top: 0.18rem; max-width: 11rem; white-space: normal; word-break: break-word; }
        .cust-amount { font-size: 0.8rem; font-weight: 800; color: var(--text); }
        .cust-sms-actions { display: inline-flex; align-items: center; gap: 0.35rem; }
        .cust-sms-circle-btn {
            width: 2rem; height: 2rem; border-radius: 50%; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text); font-size: 0.83rem; font-weight: 900;
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; justify-content: center;
        }
        .cust-sms-circle-btn--link { border-color: rgba(37, 99, 235, 0.35); background: var(--primary-soft); color: var(--primary-dark); }
        .cust-sms-circle-btn--welcome { border-color: rgba(249, 115, 22, 0.35); background: rgba(251, 146, 60, 0.16); color: #c2410c; }
        .cust-sms-circle-btn--inst-pre {
            border-color: rgba(147, 51, 234, 0.38);
            background: rgba(168, 85, 247, 0.14);
            color: #6b21a8;
            font-weight: 900;
            font-size: 0.72rem;
        }
        .cust-sms-circle-btn--inst-due {
            border-color: rgba(37, 99, 235, 0.38);
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-weight: 900;
            font-size: 0.72rem;
        }
        .cust-sms-circle-btn--inst-over {
            border-color: rgba(220, 38, 38, 0.38);
            background: rgba(254, 202, 202, 0.35);
            color: #991b1b;
            font-weight: 900;
            font-size: 0.72rem;
        }
        .cust-sms-circle-btn--inst-thanks {
            border-color: rgba(22, 163, 74, 0.38);
            background: rgba(187, 247, 208, 0.45);
            color: #166534;
            font-weight: 900;
            font-size: 0.72rem;
        }
        .cust-sms-circle-btn:hover { filter: brightness(0.97); }
        .cust-sms-circle-btn:disabled { opacity: 0.45; cursor: not-allowed; filter: none; }
        .loan-inst-modal-sub {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            color: var(--muted);
            font-weight: 600;
        }
        .loan-inst-modal-body {
            padding-top: 0.35rem;
        }
        .loan-is-modal-body {
            padding-top: 0.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .loan-is-hero {
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 0.75rem 0.85rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(15, 23, 42, 0.02));
        }
        .loan-is-hero__label {
            display: block;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .loan-is-hero__amount {
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .loan-is-hero__amount--muted {
            font-size: 1rem;
            color: var(--muted);
        }
        .loan-is-summary {
            margin: 0;
            font-size: 0.78rem;
            line-height: 1.55;
            color: var(--text);
        }
        .loan-is-rows {
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            overflow: hidden;
            font-size: 0.76rem;
        }
        .loan-is-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
            padding: 0.48rem 0.55rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.28);
        }
        .loan-is-row:last-child { border-bottom: 0; }
        .loan-is-row--emph {
            background: rgba(59, 130, 246, 0.07);
            font-weight: 800;
        }
        .loan-is-row__left {
            flex: 1;
            min-width: 0;
        }
        .loan-is-row__label {
            font-weight: 700;
            color: var(--text);
            display: block;
        }
        .loan-is-row__hint {
            display: block;
            margin-top: 0.12rem;
            font-size: 0.68rem;
            color: var(--muted);
            font-weight: 600;
            line-height: 1.4;
        }
        .loan-is-row__amt {
            flex-shrink: 0;
            font-weight: 800;
            white-space: nowrap;
        }
        .loan-is-notes {
            margin: 0;
            padding: 0 0.15rem 0 0;
            list-style: disc;
            font-size: 0.72rem;
            line-height: 1.55;
            color: var(--muted);
        }
        .loan-is-notes li { margin: 0.2rem 0 0.2rem 1rem; }
        .loan-is-meta {
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.5;
        }
        .loan-inst-summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.45rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 1024px) {
            .loan-inst-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 720px) {
            .loan-inst-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .loan-inst-summary__card {
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            padding: 0.4rem 0.5rem;
            background: var(--bg-card);
            min-height: 3.3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.12rem;
        }
        .loan-inst-summary__label {
            font-size: 0.62rem;
            font-weight: 800;
            color: var(--muted);
            letter-spacing: 0.02em;
        }
        .loan-inst-summary__value {
            font-size: 0.78rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1.3;
            word-break: break-word;
        }
        .loan-inst-summary__muted {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--muted);
        }
        .loan-inst-table-scroll {
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            overflow: auto;
            max-height: min(58vh, 520px);
            background: var(--bg-card);
        }
        .loan-inst-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.74rem;
            min-width: 920px;
        }
        .loan-inst-table th,
        .loan-inst-table td {
            border: 1px solid rgba(148, 163, 184, 0.35);
            padding: 0.42rem 0.48rem;
            vertical-align: middle;
            text-align: right;
            white-space: nowrap;
        }
        .loan-inst-table th {
            background: rgba(241, 245, 249, 0.88);
            font-weight: 800;
            position: sticky;
            top: 0;
            z-index: 2;
            font-size: 0.72rem;
        }
        html[data-theme="dark"] .loan-inst-table th {
            background: rgba(30, 41, 59, 0.94);
        }
        .loan-inst-table td.loan-inst-td--sms {
            white-space: normal;
        }
        .loan-inst-paid-method {
            margin-top: 0.22rem;
            font-size: 0.62rem;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.45;
            white-space: normal;
        }
        .loan-inst-paid-method + .loan-inst-paid-method {
            margin-top: 0.12rem;
        }
        .loan-inst-paid-method__src {
            display: block;
            margin-top: 0.06rem;
            font-size: 0.54rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .loan-inst-mismatch {
            font-size: 0.66rem;
            font-weight: 800;
            line-height: 1.45;
            white-space: normal;
        }
        .loan-inst-mismatch--over {
            color: #b91c1c;
        }
        .loan-inst-mismatch--under {
            color: #b45309;
        }
        html[data-theme="dark"] .loan-inst-mismatch--over {
            color: #fca5a5;
        }
        html[data-theme="dark"] .loan-inst-mismatch--under {
            color: #fcd34d;
        }
        .loan-inst-sms-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.38rem;
            align-items: center;
            justify-content: flex-start;
        }
        .cust-sms-circle-wrap {
            position: relative;
            display: inline-flex;
            flex-shrink: 0;
        }
        .cust-sms-badge {
            position: absolute;
            min-width: 0.95rem;
            height: 0.95rem;
            padding: 0 0.12rem;
            border-radius: 999px;
            font-size: 0.5rem;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: 1px solid #fff;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.1);
            pointer-events: none;
            z-index: 1;
        }
        .cust-sms-badge--count {
            top: -0.22rem;
            inset-inline-end: -0.18rem;
            background: #0f172a;
            color: #fff;
        }
        html[data-theme="dark"] .cust-sms-badge--count {
            border-color: rgba(15, 23, 42, 0.9);
            box-shadow: 0 0 0 1px rgba(148, 163, 184, 0.25);
        }
        .cust-sms-badge--mode {
            bottom: -0.22rem;
            inset-inline-start: -0.18rem;
            width: 0.95rem;
            padding: 0;
        }
        .cust-sms-badge--auto {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .cust-sms-badge--manual {
            background: #fef3c7;
            color: #b45309;
        }
        html[data-theme="dark"] .cust-sms-badge--auto {
            background: rgba(30, 58, 138, 0.55);
            color: #bfdbfe;
        }
        html[data-theme="dark"] .cust-sms-badge--manual {
            background: rgba(146, 64, 14, 0.45);
            color: #fde68a;
        }
        .loan-inst-sms-actions .cust-sms-circle-btn {
            width: 1.65rem;
            height: 1.65rem;
            font-size: 0.68rem;
        }
        .loan-inst-th-ops { width: 6.5rem; }
        .loan-inst-ops {
            display: inline-flex;
            gap: 0.28rem;
            align-items: center;
            justify-content: center;
        }
        .loan-inst-op-btn {
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            color: var(--muted);
            cursor: pointer;
            padding: 0;
            font-size: 0.85rem;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }
        .loan-inst-op-btn--danger {
            color: #9f1239;
            border-color: #f43f5e;
            background: #fecdd3;
        }
        .loan-inst-op-btn--edit {
            color: #5b21b6;
            border-color: #8b5cf6;
            background: #ddd6fe;
        }
        .loan-inst-op-btn--pay {
            color: #14532d;
            border-color: #22c55e;
            background: #bbf7d0;
        }
        .loan-inst-op-btn:hover {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: rgba(37, 99, 235, 0.35);
        }
        .loan-inst-op-btn--danger:hover {
            background: #fda4af;
            color: #881337;
            border-color: #e11d48;
        }
        .loan-inst-op-btn--edit:hover {
            background: #c4b5fd;
            color: #4c1d95;
            border-color: #7c3aed;
        }
        .loan-inst-op-btn--pay:hover {
            background: #86efac;
            color: #052e16;
            border-color: #16a34a;
        }
        html[data-theme="dark"] .loan-inst-op-btn--danger {
            color: #ffe4e6;
            border-color: #fb7185;
            background: #9f1239;
        }
        html[data-theme="dark"] .loan-inst-op-btn--danger:hover {
            background: #be123c;
            color: #fff1f2;
            border-color: #fda4af;
        }
        html[data-theme="dark"] .loan-inst-op-btn--edit {
            color: #ede9fe;
            border-color: #a78bfa;
            background: #5b21b6;
        }
        html[data-theme="dark"] .loan-inst-op-btn--edit:hover {
            background: #6d28d9;
            color: #f5f3ff;
            border-color: #c4b5fd;
        }
        html[data-theme="dark"] .loan-inst-op-btn--pay {
            color: #dcfce7;
            border-color: #4ade80;
            background: #166534;
        }
        html[data-theme="dark"] .loan-inst-op-btn--pay:hover {
            background: #15803d;
            color: #f0fdf4;
            border-color: #86efac;
        }
        .loan-inst-op-btn:disabled {
            opacity: 0.42;
            cursor: not-allowed;
            pointer-events: none;
        }
        .loan-inst-empty {
            text-align: center;
            padding: 1.1rem !important;
            color: var(--muted);
        }
        .cust-ops { position: relative; display: inline-block; vertical-align: middle; }
        .cust-ops-trigger {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.68rem;
            font-size: 0.86rem; font-weight: 700; color: var(--text); background: var(--bg-card);
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem;
            line-height: 1.2;
        }
        .cust-ops-trigger:hover,
        .cust-ops-trigger:focus-visible { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); outline: none; }
        .cust-ops-menu {
            position: fixed; min-width: 10rem; z-index: 1500;
            border: 1px solid var(--border); border-radius: 0.6rem; background: var(--bg-card);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); padding: 0.28rem;
        }
        .cust-ops-menu[hidden] { display: none !important; }
        .cust-ops-item {
            width: 100%; text-align: start; border: 0; border-radius: 0.45rem; padding: 0.42rem 0.5rem;
            font-family: inherit; font-size: 0.74rem; font-weight: 700; background: transparent; color: var(--text);
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .cust-ops-item:hover { background: var(--primary-soft); }
        .cust-ops-item--danger { color: #b91c1c; }
        .cust-ops-item--danger:hover { background: rgba(248, 113, 113, 0.14); }
        .cust-empty { text-align: center; padding: 1.5rem; color: var(--muted); }
        .cust-pagination { padding: 0.65rem 0.85rem; }

        .cust-overlay {
            position: fixed; inset: 0; z-index: 1400; background: rgba(15, 23, 42, 0.55);
            display: grid; place-items: center; padding: 1rem;
        }
        .cust-overlay[hidden] { display: none !important; }
        /* بالاتر از بقیهٔ overlayها (مدیریت وام، اقساط، تضامین، …) — همةٔ .cust-overlay فعلاً 1400‌اند و ترتیب DOM روی هم را تعیین می‌کند */
        #loan-instant-settlement-overlay {
            z-index: 5500;
        }
        #loan-discount-overlay {
            z-index: 5480;
        }
        #loan-installment-edit-overlay {
            z-index: 5620;
        }
        #loan-installment-pay-overlay {
            z-index: 5630;
        }
        .loan-inst-pay-banner.loan-inst-edit-banner {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        @media (max-width: 700px) {
            .loan-inst-pay-banner.loan-inst-edit-banner {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .loan-inst-pay-strip {
            font-size: 0.74rem;
            line-height: 1.65;
            padding: 0.55rem 0.65rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            margin-bottom: 0.85rem;
            background: rgba(248, 250, 252, 0.88);
        }
        html[data-theme="dark"] .loan-inst-pay-strip {
            background: rgba(30, 41, 59, 0.72);
        }
        .loan-inst-pay-table-scroll {
            max-height: 14rem;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            margin-top: 0.65rem;
        }
        .loan-inst-pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.68rem;
        }
        .loan-inst-pay-table th,
        .loan-inst-pay-table td {
            padding: 0.4rem 0.45rem;
            border-bottom: 1px solid var(--border);
            text-align: start;
            vertical-align: top;
        }
        .loan-inst-pay-table th {
            position: sticky;
            top: 0;
            background: var(--bg-card);
            font-weight: 800;
            color: var(--muted);
            z-index: 1;
        }
        .loan-inst-pay-table tbody tr:last-child td { border-bottom: 0; }
        .loan-inst-pay-form-wrap {
            margin-top: 0.85rem;
            padding: 0.75rem 0.68rem;
            border-radius: 0.65rem;
            border: 1px dashed rgba(37, 99, 235, 0.35);
            background: rgba(239, 246, 255, 0.45);
        }
        html[data-theme="dark"] .loan-inst-pay-form-wrap {
            background: rgba(30, 58, 138, 0.18);
        }
        .loan-inst-pay-form-wrap[hidden] { display: none !important; }
        .loan-inst-edit-banner {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
            margin-bottom: 1rem;
            padding: 0.65rem 0.75rem;
            border-radius: 0.65rem;
            border: 1px solid var(--border);
            background: rgba(248, 250, 252, 0.92);
            font-size: 0.74rem;
            line-height: 1.55;
        }
        html[data-theme="dark"] .loan-inst-edit-banner {
            background: rgba(30, 41, 59, 0.72);
        }
        .loan-inst-edit-banner__col strong {
            display: block;
            font-size: 0.68rem;
            color: var(--muted);
            font-weight: 800;
            margin-bottom: 0.35rem;
        }
        .loan-discount-late-box {
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            padding: 0.65rem 0.75rem;
            background: rgba(248, 250, 252, 0.9);
            margin-bottom: 0.85rem;
        }
        html[data-theme="dark"] .loan-discount-late-box {
            background: rgba(30, 41, 59, 0.65);
        }
        .loan-discount-late-box__label {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--muted);
            display: block;
            margin-bottom: 0.35rem;
        }
        .loan-discount-late-box__value {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text);
        }
        .loan-discount-hint {
            font-size: 0.7rem;
            color: var(--muted);
            margin: 0.35rem 0 0;
            line-height: 1.5;
        }
        #quick-sms-overlay,
        #loan-sms-overlay {
            z-index: 6000;
        }
        .cust-modal {
            width: min(900px, 100%); max-height: min(92vh, 900px); overflow: auto;
            border: 1px solid var(--border); border-radius: 1rem; background: var(--bg-card);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
        }
        .cust-modal-head {
            padding: 0.85rem 1rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
            position: sticky; top: 0; background: var(--bg-card); z-index: 2;
        }
        .cust-modal-head h2 { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text); }
        .cust-modal-head p { margin: 0.25rem 0 0; font-size: 0.75rem; color: var(--muted); line-height: 1.45; }
        .cust-modal-close {
            flex-shrink: 0; width: 2.1rem; height: 2.1rem; border: 0; border-radius: 0.55rem;
            background: var(--primary-soft); color: var(--primary-dark); cursor: pointer;
        }
        .cust-modal-body { padding: 1rem 1rem 1.15rem; }
        .cust-form-grid {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem 0.9rem;
        }
        @media (max-width: 720px) { .cust-form-grid { grid-template-columns: 1fr; } }
        .cust-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.22rem; }
        .cust-field label .req { color: #b91c1c; font-weight: 800; }
        .cust-field input, .cust-field textarea, .cust-field select {
            width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem;
        }
        .cust-field input.is-invalid,
        .cust-field textarea.is-invalid,
        .cust-field select.is-invalid {
            border-color: #b91c1c;
            box-shadow: 0 0 0 2px rgba(185, 28, 28, 0.08);
            background: rgba(254, 242, 242, 0.55);
        }
        .cust-field input:disabled { opacity: 0.72; cursor: not-allowed; background: var(--primary-soft); }
        .cust-field textarea { min-height: 4rem; resize: vertical; }
        .cust-field--full { grid-column: 1 / -1; }
        .cust-field-error { margin-top: 0.22rem; font-size: 0.72rem; color: #b91c1c; font-weight: 700; }
        .cust-field-hint { display: block; margin-top: 0.18rem; font-size: 0.7rem; color: var(--muted); line-height: 1.4; }
        .loan-manage-modal { width: min(1180px, 100%); }
        .cust-modal.loan-manage-modal {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .cust-modal.loan-manage-modal .cust-modal-body.loan-manage-modal-body {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 1rem 1rem 1.15rem;
        }
        .loan-manage-sticky {
            flex-shrink: 0;
            z-index: 4;
            background: var(--bg-card);
        }
        .loan-manage-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding-top: 0.45rem;
        }
        .loan-files-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            flex-wrap: wrap;
            margin: 0.2rem 0 0.35rem;
            padding: 0.48rem 0.62rem;
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            background: rgba(248, 250, 252, 0.72);
        }
        html[data-theme="dark"] .loan-files-filter-bar {
            background: rgba(30, 41, 59, 0.45);
        }
        .loan-files-filter-bar[hidden] { display: none !important; }
        .loan-files-filter-check {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            user-select: none;
        }
        .loan-files-filter-check input {
            width: 0.95rem;
            height: 0.95rem;
            accent-color: var(--primary-dark);
            flex-shrink: 0;
        }
        .loan-manage-top {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.55rem;
            margin-bottom: 0.85rem;
        }
        .loan-manage-pill {
            border: 1px solid var(--border);
            border-radius: 0.72rem;
            background: var(--bg-card);
            padding: 0.58rem 0.68rem;
            text-align: right;
            min-height: 2.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.35rem;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            min-width: 0;
        }
        .loan-manage-pill-ico {
            width: 1.05rem;
            flex-shrink: 0;
            text-align: center;
            color: var(--primary-dark);
            opacity: 0.92;
        }
        button.loan-manage-pill {
            font-family: inherit;
            cursor: pointer;
        }
        button.loan-manage-pill:hover {
            border-color: rgba(37, 99, 235, 0.35);
            background: var(--primary-soft);
        }
        .loan-manage-pill-label { font-size: 0.72rem; font-weight: 700; color: var(--muted); flex-shrink: 0; }
        .loan-manage-pill-value { font-size: 0.8rem; font-weight: 800; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .loan-manage-pill-value--good { color: #047857; }
        .loan-manage-pill-value--normal { color: #b45309; }
        .loan-manage-pill-value--weak { color: #b91c1c; }
        .loan-manage-placeholder {
            border: 1px dashed var(--border);
            border-radius: 0.78rem;
            padding: 0.9rem;
            background: rgba(248, 250, 252, 0.5);
            color: var(--muted);
            font-size: 0.78rem;
            line-height: 1.8;
        }
        .loan-lrq-embed-wrap {
            position: relative;
            border: 1px solid var(--border);
            border-radius: 0.78rem;
            overflow: hidden;
            background: var(--bg-card);
            min-height: 18rem;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .loan-lrq-embed-loading {
            position: absolute;
            inset: 0;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(2px);
        }
        html[data-theme="dark"] .loan-lrq-embed-loading {
            background: rgba(15, 23, 42, 0.88);
        }
        .loan-lrq-embed-loading[hidden] {
            display: none !important;
        }
        .loan-lrq-embed-loading-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.65rem;
            padding: 1rem 1.25rem;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--muted);
        }
        .loan-lrq-embed-loading-inner i {
            font-size: 1.35rem;
            color: var(--primary);
        }
        .loan-tab-panel[data-loan-panel="requests"] {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }
        .loan-tab-panel[data-loan-panel="transactions"],
        .loan-tab-panel[data-loan-panel="tickets"] {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }
        .loan-lrq-embed-iframe {
            width: 100%;
            flex: 1 1 auto;
            min-height: 22rem;
            height: min(72vh, 38rem);
            border: 0;
            display: block;
            background: var(--bg-card);
        }
        .loan-files-head {
            margin-bottom: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .loan-files-summary { font-size: 0.72rem; color: var(--muted); font-weight: 700; }
        .loan-files-list { display: grid; gap: 0.85rem; }
        .loan-files-list--multi { gap: 1rem; }
        .loan-files-empty {
            text-align: center; color: var(--muted); font-size: 0.78rem; padding: 1.15rem;
            border: 1px dashed var(--border); border-radius: 0.72rem; background: rgba(248, 250, 252, 0.52);
        }
        .loan-file-card {
            border: 1px solid var(--border);
            border-radius: 0.82rem;
            background: var(--bg-card);
            padding: 0.9rem;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
            position: relative;
        }
        .loan-files-list--multi .loan-file-card--theme-0 {
            border-color: rgba(59, 130, 246, 0.32);
            border-inline-start: 4px solid #3b82f6;
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.92), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(59, 130, 246, 0.08);
        }
        .loan-files-list--multi .loan-file-card--theme-1 {
            border-color: rgba(99, 102, 241, 0.32);
            border-inline-start: 4px solid #6366f1;
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.92), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(99, 102, 241, 0.08);
        }
        .loan-files-list--multi .loan-file-card--theme-2 {
            border-color: rgba(14, 165, 233, 0.32);
            border-inline-start: 4px solid #0ea5e9;
            background: linear-gradient(135deg, rgba(224, 242, 254, 0.92), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(14, 165, 233, 0.08);
        }
        .loan-files-list--multi .loan-file-card--theme-3 {
            border-color: rgba(16, 185, 129, 0.32);
            border-inline-start: 4px solid #10b981;
            background: linear-gradient(135deg, rgba(236, 253, 245, 0.92), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(16, 185, 129, 0.08);
        }
        .loan-files-list--multi .loan-file-card--theme-4 {
            border-color: rgba(245, 158, 11, 0.34);
            border-inline-start: 4px solid #f59e0b;
            background: linear-gradient(135deg, rgba(255, 251, 235, 0.94), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(245, 158, 11, 0.08);
        }
        .loan-files-list--multi .loan-file-card--theme-5 {
            border-color: rgba(244, 114, 182, 0.32);
            border-inline-start: 4px solid #f472b6;
            background: linear-gradient(135deg, rgba(253, 242, 248, 0.92), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 22px rgba(244, 114, 182, 0.08);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-0 {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.22), rgba(30, 41, 59, 0.9));
            border-color: rgba(96, 165, 250, 0.35);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-1 {
            background: linear-gradient(135deg, rgba(49, 46, 129, 0.24), rgba(30, 41, 59, 0.9));
            border-color: rgba(129, 140, 248, 0.35);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-2 {
            background: linear-gradient(135deg, rgba(12, 74, 110, 0.24), rgba(30, 41, 59, 0.9));
            border-color: rgba(56, 189, 248, 0.35);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-3 {
            background: linear-gradient(135deg, rgba(6, 78, 59, 0.24), rgba(30, 41, 59, 0.9));
            border-color: rgba(52, 211, 153, 0.35);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-4 {
            background: linear-gradient(135deg, rgba(120, 53, 15, 0.24), rgba(30, 41, 59, 0.9));
            border-color: rgba(251, 191, 36, 0.35);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-5 {
            background: linear-gradient(135deg, rgba(131, 24, 67, 0.22), rgba(30, 41, 59, 0.9));
            border-color: rgba(244, 114, 182, 0.35);
        }
        .loan-files-list--multi .loan-file-card--theme-0 .loan-file-col {
            background: rgba(219, 234, 254, 0.42);
            border-color: rgba(59, 130, 246, 0.2);
        }
        .loan-files-list--multi .loan-file-card--theme-1 .loan-file-col {
            background: rgba(224, 231, 255, 0.42);
            border-color: rgba(99, 102, 241, 0.2);
        }
        .loan-files-list--multi .loan-file-card--theme-2 .loan-file-col {
            background: rgba(224, 242, 254, 0.42);
            border-color: rgba(14, 165, 233, 0.2);
        }
        .loan-files-list--multi .loan-file-card--theme-3 .loan-file-col {
            background: rgba(209, 250, 229, 0.38);
            border-color: rgba(16, 185, 129, 0.2);
        }
        .loan-files-list--multi .loan-file-card--theme-4 .loan-file-col {
            background: rgba(254, 243, 199, 0.38);
            border-color: rgba(245, 158, 11, 0.22);
        }
        .loan-files-list--multi .loan-file-card--theme-5 .loan-file-col {
            background: rgba(252, 231, 243, 0.4);
            border-color: rgba(244, 114, 182, 0.2);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-0 .loan-file-col {
            background: rgba(30, 58, 138, 0.18);
            border-color: rgba(96, 165, 250, 0.22);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-1 .loan-file-col {
            background: rgba(49, 46, 129, 0.2);
            border-color: rgba(129, 140, 248, 0.22);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-2 .loan-file-col {
            background: rgba(12, 74, 110, 0.2);
            border-color: rgba(56, 189, 248, 0.22);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-3 .loan-file-col {
            background: rgba(6, 78, 59, 0.2);
            border-color: rgba(52, 211, 153, 0.22);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-4 .loan-file-col {
            background: rgba(120, 53, 15, 0.2);
            border-color: rgba(251, 191, 36, 0.22);
        }
        html[data-theme="dark"] .loan-files-list--multi .loan-file-card--theme-5 .loan-file-col {
            background: rgba(131, 24, 67, 0.18);
            border-color: rgba(244, 114, 182, 0.22);
        }
        .loan-file-card--settled {
            border-color: rgba(239, 68, 68, 0.38);
            background: linear-gradient(180deg, rgba(254, 242, 242, 0.72), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.09);
        }
        html[data-theme="dark"] .loan-file-card--settled {
            background: linear-gradient(180deg, rgba(127, 29, 29, 0.16), rgba(30, 41, 59, 0.82));
            border-color: rgba(248, 113, 113, 0.45);
        }
        .loan-file-card--revoked {
            border-color: rgba(180, 83, 9, 0.42);
            background: linear-gradient(180deg, rgba(254, 252, 232, 0.88), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 24px rgba(180, 83, 9, 0.1);
        }
        html[data-theme="dark"] .loan-file-card--revoked {
            background: linear-gradient(180deg, rgba(113, 63, 18, 0.2), rgba(30, 41, 59, 0.82));
            border-color: rgba(251, 191, 36, 0.38);
        }
        .loan-file-corner-ribbon {
            position: absolute;
            top: 0;
            left: 0;
            width: 6.6rem;
            height: 6.6rem;
            overflow: hidden;
            pointer-events: none;
            z-index: 2;
        }
        .loan-file-corner-ribbon > span {
            position: absolute;
            top: 1rem;
            left: -1.85rem;
            width: 8.6rem;
            text-align: center;
            transform: rotate(-45deg);
            background: linear-gradient(180deg, #ef4444, #dc2626);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 0.01em;
            line-height: 1.45;
            padding: 0.18rem 0;
            box-shadow: 0 6px 14px rgba(220, 38, 38, 0.35);
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.15);
        }
        html[data-theme="dark"] .loan-file-corner-ribbon > span {
            background: linear-gradient(180deg, #f87171, #ef4444);
        }
        .loan-file-corner-ribbon--revoked > span {
            background: linear-gradient(180deg, #ea580c, #c2410c);
            box-shadow: 0 6px 14px rgba(194, 65, 12, 0.38);
        }
        html[data-theme="dark"] .loan-file-corner-ribbon--revoked > span {
            background: linear-gradient(180deg, #f59e0b, #d97706);
        }
        .loan-file-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .loan-file-col {
            min-width: 0;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.68rem;
            padding: 0.62rem 0.65rem;
            background: rgba(248, 250, 252, 0.38);
        }
        html[data-theme="dark"] .loan-file-col {
            background: rgba(30, 41, 59, 0.32);
            border-color: rgba(148, 163, 184, 0.22);
        }
        .loan-file-col-title {
            margin: 0;
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--text);
        }
        .loan-file-col-sep {
            margin: 0.3rem 0 0.42rem;
            border: 0;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }
        .loan-file-items { display: grid; gap: 0; }
        .loan-file-item {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
            align-items: baseline;
            gap: 0.5rem;
            padding: 0.28rem 0;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.25);
        }
        .loan-file-items .loan-file-item:last-child { border-bottom: 0; }
        .loan-file-k { font-size: 0.72rem; color: var(--muted); font-weight: 700; }
        .loan-file-v { font-size: 0.76rem; color: var(--text); font-weight: 700; text-align: end; }
        .loan-file-v--danger { color: #b91c1c; }
        .loan-file-v--ok { color: #047857; }
        .loan-file-v--warn { color: #b45309; }
        .loan-file-item--stack { grid-template-columns: 1fr; gap: 0.28rem; }
        .loan-file-item--stack .loan-file-v { text-align: start; }
        .loan-file-desc {
            margin: 0;
            font-size: 0.75rem;
            line-height: 1.9;
            color: var(--text);
            white-space: pre-wrap;
            word-break: break-word;
        }
        .loan-file-desc--empty { color: var(--muted); font-style: italic; }
        .loan-file-foot {
            margin-top: 0.8rem;
            padding-top: 0.62rem;
            border-top: 1px solid rgba(148, 163, 184, 0.22);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .loan-file-actions-right, .loan-file-actions-left {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .loan-file-btn {
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.28rem 0.58rem;
            background: var(--bg-card);
            color: var(--text);
            font-size: 0.7rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            text-decoration: none;
        }
        .loan-file-btn:hover { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); }
        .loan-file-btn--mini {
            width: 1.85rem; height: 1.85rem; border-radius: 50%; padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .loan-file-btn--danger { color: #b91c1c; border-color: rgba(239, 68, 68, 0.4); background: rgba(248, 113, 113, 0.1); }
        .loan-file-btn--disc { font-size: 0.68rem; padding: 0.22rem 0.52rem; }
        .loan-guarantee-list { display: grid; gap: 0.55rem; margin-bottom: 0.72rem; }
        .loan-guarantee-empty {
            border: 1px dashed var(--border);
            border-radius: 0.7rem;
            padding: 0.72rem;
            font-size: 0.75rem;
            color: var(--muted);
            text-align: center;
        }
        .loan-guarantee-card {
            border: 1px solid var(--border);
            border-radius: 0.72rem;
            padding: 0.58rem 0.62rem;
            background: rgba(248, 250, 252, 0.4);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            /* جلوگیری از جمع‌شدن ستون کناری زیرِ متن طولانی */
            min-width: 0;
        }
        html[data-theme="dark"] .loan-guarantee-card { background: rgba(30, 41, 59, 0.34); }
        .loan-guarantee-card__main {
            flex: 1 1 0%;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .loan-guarantee-card__aside {
            flex: 0 0 auto;
            flex-shrink: 0;
            width: 264px;
            max-width: 264px;
            min-width: 258px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .loan-guarantee-card__aside .loan-guarantee-thumb-link {
            width: 100%;
            max-width: 252px;
            display: block;
        }
        .loan-guarantee-card__aside .loan-guarantee-thumb {
            width: 100%;
            max-width: 252px;
            height: 112px;
            object-fit: cover;
        }
        .loan-guarantee-card__aside .loan-guarantee-actions {
            width: 100%;
            max-width: 252px;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 0.32rem;
        }
        .loan-guarantee-card__aside .loan-guarantee-actions .loan-file-btn {
            flex: 1 1 auto;
            min-width: 0;
            padding: 0.4rem 0.45rem;
            font-size: 0.72rem;
            font-weight: 800;
            white-space: nowrap;
        }
        @media (max-width: 520px) {
            .loan-guarantee-card {
                flex-direction: column;
                align-items: stretch;
            }
            .loan-guarantee-card__aside {
                flex-direction: row;
                flex-wrap: wrap;
                width: 100%;
                max-width: none;
                justify-content: center;
                align-items: flex-start;
            }
            .loan-guarantee-card__aside .loan-guarantee-actions {
                flex: 1 1 auto;
                min-width: 0;
                justify-content: center;
            }
        }
        .loan-guarantee-title { margin: 0 0 0.18rem; font-size: 0.8rem; font-weight: 900; color: var(--text); }
        .loan-guarantee-meta { font-size: 0.72rem; color: var(--muted); line-height: 1.7; word-break: break-word; overflow-wrap: anywhere; }
        .loan-guarantee-actions { display: inline-flex; gap: 0.35rem; flex-wrap: wrap; }
        .loan-guarantee-thumb-link { display: inline-flex; align-items: center; }
        .loan-guarantee-thumb {
            width: 130px;
            height: 92px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--line);
            display: block;
        }
        .loan-guarantee-type-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            overflow: hidden;
            margin-bottom: 0.72rem;
        }
        .loan-guarantee-type-btn {
            border: 0;
            border-inline-end: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--muted);
            padding: 0.42rem 0.68rem;
            font-size: 0.74rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
        }
        .loan-guarantee-type-btn:last-child { border-inline-end: 0; }
        .loan-guarantee-type-btn.is-active { background: var(--primary-soft); color: var(--primary-dark); }
        .loan-guarantee-section[hidden] { display: none !important; }
        .loan-guarantee-check-row {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            margin: 0;
            user-select: none;
        }
        .loan-guarantee-check-row input[type="checkbox"] { width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; }
        .loan-guarantee-org-shared-block { margin-bottom: 0.35rem; }
        .loan-guarantee-org-toolbar { margin-bottom: 0.15rem; }
        .loan-guarantee-org-toolbar__row {
            display: flex; flex-wrap: wrap; align-items: stretch; gap: 0.5rem;
            margin-top: 0.25rem;
        }
        .loan-guarantee-org-toolbar__row .loan-guarantee-org-select-wrap {
            flex: 1 1 14rem;
            min-width: 0;
        }
        .loan-guarantee-org-manage-btn {
            flex: 0 0 auto;
            white-space: nowrap;
            border: none; border-radius: 0.62rem; padding: 0.48rem 0.75rem;
            background: var(--primary-soft); color: var(--primary-dark);
            font-size: 0.76rem; font-weight: 800; cursor: pointer; font-family: inherit;
        }
        .loan-guarantee-org-manage-btn:hover { filter: brightness(0.98); }
        .loan-guarantee-guarantor-mobile-row {
            display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center;
            margin-top: 0.2rem;
        }
        .loan-guarantee-guarantor-mobile-row input { flex: 1 1 10rem; min-width: 0; }
        .loan-guarantee-guarantor-otp-btn {
            border: none; border-radius: 0.62rem; padding: 0.48rem 0.65rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.74rem; font-weight: 800; cursor: pointer; font-family: inherit;
        }
        .loan-guarantee-guarantor-otp-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .loan-guarantee-guarantor-otp-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-top: 0.25rem; }
        .loan-creation-otp-section { border: 1px dashed rgba(37, 99, 235, 0.28); border-radius: 0.65rem; padding: 0.65rem 0.75rem; background: rgba(37, 99, 235, 0.04); }
        .loan-creation-otp-verified { font-size: 0.74rem; font-weight: 800; color: #15803d; margin-top: 0.35rem; }
        .loan-creation-otp-btn {
            border: 1px solid rgba(37, 99, 235, 0.35); background: var(--primary-soft); color: var(--primary-dark);
            border-radius: 0.55rem; padding: 0.38rem 0.72rem; font-size: 0.76rem; font-weight: 800; cursor: pointer; font-family: inherit;
        }
        .loan-creation-otp-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .loan-guarantee-guarantor-verify-msg { font-size: 0.72rem; font-weight: 700; margin-top: 0.35rem; min-height: 1.1rem; }
        .loan-guarantee-guarantor-verify-msg.is-ok { color: #15803d; }
        .loan-guarantee-guarantor-verify-msg.is-err { color: #b91c1c; }
        .loan-org-manage-list { display: grid; gap: 0.45rem; margin-top: 0.6rem; max-height: min(52vh, 420px); overflow: auto; }
        .loan-org-manage-item {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.45rem;
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.55rem; background: var(--bg-card);
        }
        .loan-org-manage-item span { font-size: 0.8rem; font-weight: 700; word-break: break-word; }
        .loan-org-manage-item-actions { display: inline-flex; gap: 0.35rem; flex-shrink: 0; }
        .loan-org-inline-form { margin-top: 0.55rem; padding: 0.55rem; border: 1px dashed var(--border); border-radius: 0.65rem; background: rgba(248, 250, 252, 0.5); }
        html[data-theme="dark"] .loan-org-inline-form { background: rgba(30, 41, 59, 0.35); }
        .select2-container { z-index: 1600; }
        .gold-item-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.45rem;
            margin-bottom: 0.45rem;
        }
        .gold-item-option {
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.45rem 0.5rem;
            background: var(--bg-card);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text);
            transition: all 0.15s ease;
            user-select: none;
        }
        .gold-item-option input { display: none; }
        .gold-item-option.is-active {
            background: var(--primary-soft);
            border-color: rgba(37, 99, 235, 0.35);
            color: var(--primary-dark);
        }
        .loan-guarantee-attach {
            border: 1px dashed var(--border);
            border-radius: 0.72rem;
            padding: 0.62rem;
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 0.75rem;
            align-items: start;
        }
        .loan-guarantee-attach-left { display: grid; gap: 0.28rem; justify-items: center; }
        .loan-guarantee-preview {
            width: 150px; height: 100px;
            border: 1px dashed var(--border);
            border-radius: 0.6rem;
            background: rgba(248, 250, 252, 0.6);
            display: grid;
            place-items: center;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.72rem;
        }
        html[data-theme="dark"] .loan-guarantee-preview { background: rgba(30, 41, 59, 0.4); }
        .loan-guarantee-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .loan-guarantee-preview-download {
            font-size: 0.72rem;
            color: #22c55e;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.22rem;
            font-weight: 700;
        }
        .loan-guarantee-preview-download:hover { color: #16a34a; text-decoration: underline; }
        .loan-guarantee-attach-actions { display: inline-flex; gap: 0.45rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .loan-guarantee-attach-actions .loan-file-btn--mini {
            width: 2.35rem;
            height: 2.35rem;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
        }
        .loan-guarantee-attach-actions #loan-guarantee-file-remove {
            color: #fff;
            border-color: #ef2a78;
            background: linear-gradient(180deg, #ff3b8f, #ef2a78);
        }
        .loan-guarantee-attach-actions #loan-guarantee-file-upload {
            color: #fff;
            border-color: #1fae55;
            background: linear-gradient(180deg, #39c56e, #1fae55);
        }
        .loan-guarantee-attach-actions #loan-guarantee-file-download {
            color: #fff;
            border-color: #8b2bd3;
            background: linear-gradient(180deg, #b043ff, #8b2bd3);
        }
        @media (max-width: 720px) {
            .loan-guarantee-attach { grid-template-columns: 1fr; }
            .loan-guarantee-preview { width: 100%; height: 130px; }
            .loan-guarantee-attach-left { justify-items: stretch; }
        }
        @media (max-width: 980px) {
            .loan-file-grid { grid-template-columns: 1fr; }
            .loan-file-item { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        }
        .loan-interest-note { margin-top: 0.22rem; font-size: 0.7rem; color: var(--muted); }
        .loan-extra-box {
            border: 1px dashed var(--border);
            border-radius: 0.66rem;
            padding: 0.58rem;
            background: rgba(248, 250, 252, 0.52);
        }
        html[data-theme="dark"] .loan-extra-box { background: rgba(30, 41, 59, 0.45); }
        .loan-extra-box[hidden] { display: none !important; }
        .loan-manage-sep {
            border: 0;
            border-top: 1px solid rgba(148, 163, 184, 0.35);
            margin: 0.1rem 0 0.75rem;
        }
        .loan-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.72rem;
            justify-content: center;
        }
        .loan-tab-btn {
            border: 1px solid var(--border);
            border-radius: 0.62rem;
            padding: 0.4rem 0.68rem;
            background: var(--bg-card);
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
        }
        .loan-tab-btn.is-active {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: rgba(37, 99, 235, 0.35);
        }
        .loan-tab-panel[hidden] { display: none !important; }
        .loan-guarantees-report-wrap {
            border: 1px solid var(--border);
            border-radius: 0.66rem;
            overflow: auto;
            max-height: min(62vh, 520px);
            background: var(--bg-card);
        }
        .loan-guarantees-report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.74rem;
        }
        .loan-guarantees-report-table th,
        .loan-guarantees-report-table td {
            border: 1px solid rgba(148, 163, 184, 0.35);
            padding: 0.45rem 0.5rem;
            vertical-align: top;
            text-align: right;
        }
        .loan-guarantees-report-table th {
            background: rgba(241, 245, 249, 0.85);
            font-weight: 800;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        html[data-theme="dark"] .loan-guarantees-report-table th {
            background: rgba(30, 41, 59, 0.92);
        }
        .loan-guarantees-report-title {
            font-size: 0.88rem;
            font-weight: 800;
            margin: 0 0 0.55rem;
            color: var(--text);
        }
        .loan-guarantees-report-controls {
            margin-bottom: 0.45rem;
        }
        .loan-guarantees-search-input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            padding: 0.42rem 0.65rem;
            font-size: 0.8rem;
            font-family: inherit;
            background: var(--bg-card);
            color: var(--text);
        }
        .loan-guarantees-search-input::placeholder {
            color: var(--muted);
            opacity: 0.85;
        }
        .loan-guarantees-summary-line {
            margin: 0 0 0.5rem;
            font-size: 0.62rem;
            line-height: 1.45;
            color: var(--muted);
            font-weight: 600;
            word-break: break-word;
        }
        .loan-tab-panel-toolbar-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.55rem;
        }
        .loan-tab-panel-toolbar-row[dir="ltr"] {
            justify-content: flex-start;
        }
        .loan-tab-panel-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }
        .loan-tab-panel-actions .cust-mini-btn {
            min-width: 2.15rem;
            justify-content: center;
        }
        .loan-sms-msg--btn {
            border: 0;
            background: transparent;
            padding: 0;
            margin: 0;
            font: inherit;
            color: var(--muted);
            cursor: pointer;
            text-align: inherit;
            max-width: 18rem;
            line-height: 1.55;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .loan-sms-msg--btn:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        .loan-sms-date-toolbar {
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.65rem 0.72rem;
            margin-bottom: 0.65rem;
            background: var(--bg-card);
        }
        .loan-sms-day-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
            justify-content: center;
        }
        .loan-sms-day-btn {
            border: 1px solid var(--border);
            border-radius: 0.6rem;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.42rem 0.7rem;
            color: var(--text);
            background: var(--bg-card);
            cursor: pointer;
            font-family: inherit;
        }
        .loan-sms-day-btn:hover { background: var(--primary-soft); }
        .loan-sms-day-current {
            border: 1px dashed var(--border);
            border-radius: 0.6rem;
            padding: 0.42rem 0.7rem;
            min-width: 9.7rem;
            text-align: center;
            font-size: 0.83rem;
            font-weight: 700;
            color: var(--text);
            background: var(--primary-soft);
        }
        input.loan-sms-day-current {
            cursor: pointer;
            max-width: 11rem;
            font-family: inherit;
            outline: none;
        }
        input.loan-sms-day-current:focus {
            border-color: rgba(37, 99, 235, 0.45);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
        }
        .loan-sms-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.9rem;
            overflow: hidden;
        }
        .loan-sms-table-wrap { overflow-x: auto; max-height: min(58vh, 480px); overflow-y: auto; }
        .loan-sms-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .loan-sms-table th,
        .loan-sms-table td {
            padding: 0.52rem 0.62rem;
            border-bottom: 1px solid var(--border);
            text-align: start;
            vertical-align: top;
        }
        .loan-sms-table th {
            white-space: nowrap;
            background: var(--primary-soft);
            color: var(--text);
            font-weight: 800;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .loan-sms-msg {
            max-width: 18rem;
            line-height: 1.55;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .loan-sms-badge {
            display: inline-block;
            padding: 0.16rem 0.45rem;
            border-radius: 0.35rem;
            font-size: 0.71rem;
            font-weight: 700;
        }
        .loan-sms-badge--pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .loan-sms-badge--delivered { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .loan-sms-badge--undelivered { background: rgba(248, 113, 113, 0.2); color: #b91c1c; }
        .loan-sms-empty { text-align: center; padding: 1rem; color: var(--muted); font-size: 0.8rem; }
        .loan-sms-mobile-note { margin: 0 0 0.5rem; font-size: 0.76rem; color: #b45309; font-weight: 700; }
        @media (max-width: 860px) {
            .loan-manage-top { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .loan-manage-top { grid-template-columns: 1fr; }
        }
        html[data-admin-font="large"] .loan-manage-pill {
            min-height: 3.2rem;
            white-space: normal;
            align-items: flex-start;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        html[data-admin-font="large"] .loan-manage-pill-label,
        html[data-admin-font="large"] .loan-manage-pill-value {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.5;
        }

        .cust-section {
            margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border);
        }
        .cust-section-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;
            margin-bottom: 0.55rem;
        }
        .cust-section-head h3 { margin: 0; font-size: 0.84rem; font-weight: 800; color: var(--text); }
        .cust-section-head p { margin: 0.2rem 0 0; font-size: 0.72rem; color: var(--muted); width: 100%; }
        .cust-mini-btn {
            border: 1px solid rgba(37, 99, 235, 0.35); border-radius: 0.55rem; padding: 0.36rem 0.65rem;
            background: var(--primary-soft); color: var(--primary-dark); font-size: 0.75rem; font-weight: 800;
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .cust-mini-btn:hover { filter: brightness(0.98); }

        .cust-repeat-row {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.1fr) minmax(0, 1fr) auto;
            gap: 0.45rem; align-items: end; margin-bottom: 0.5rem;
            padding: 0.55rem 0.6rem; border: 1px solid var(--border); border-radius: 0.65rem; background: rgba(248, 250, 252, 0.55);
        }
        html[data-theme="dark"] .cust-repeat-row { background: rgba(30, 41, 59, 0.45); }
        @media (max-width: 900px) {
            .cust-repeat-row { grid-template-columns: 1fr 1fr; }
            .cust-repeat-row .cust-f-remove { grid-column: 1 / -1; justify-self: end; }
        }
        .cust-f-remove {
            width: 2.15rem; height: 2.15rem; border: 0; border-radius: 0.5rem; cursor: pointer;
            background: rgba(239, 68, 68, 0.12); color: #b91c1c; display: grid; place-items: center;
        }
        .cust-f-remove:hover { background: rgba(239, 68, 68, 0.2); }

        .cust-ref-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.45rem; align-items: end;
            margin-bottom: 0.5rem; padding: 0.55rem 0.6rem; border: 1px solid var(--border);
            border-radius: 0.65rem; background: rgba(248, 250, 252, 0.55);
        }
        html[data-theme="dark"] .cust-ref-row { background: rgba(30, 41, 59, 0.45); }
        @media (max-width: 720px) {
            .cust-ref-row { grid-template-columns: 1fr; }
            .cust-ref-row .cust-f-remove { justify-self: end; }
        }

        .cust-send-row {
            margin-top: 1.1rem; padding: 0.65rem 0.75rem; border-radius: 0.65rem;
            border: 1px solid var(--border); background: var(--primary-soft);
            display: flex; align-items: flex-start; gap: 0.55rem;
        }
        .cust-send-row input[type="checkbox"] { width: 1rem; height: 1rem; margin-top: 0.1rem; accent-color: var(--primary); }
        .cust-send-row label { font-size: 0.78rem; font-weight: 700; color: var(--text); cursor: pointer; line-height: 1.55; }

        .cust-actions { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
        .cust-submit {
            border: none; border-radius: 0.65rem; padding: 0.55rem 1.35rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.82rem; font-weight: 800; cursor: pointer; font-family: inherit;
        }
        .cust-cancel {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.52rem 1rem;
            background: var(--bg-card); color: var(--text); font-size: 0.8rem; font-weight: 700; cursor: pointer; font-family: inherit;
        }
        .cust-block-error { font-size: 0.75rem; color: #b91c1c; font-weight: 700; margin-top: 0.35rem; }

        .wallet-balance-card {
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 0.85rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(14, 116, 144, 0.09));
            padding: 0.95rem;
            margin-bottom: 0.95rem;
        }
        .wallet-balance-label { font-size: 0.76rem; color: var(--muted); margin-bottom: 0.3rem; font-weight: 700; }
        .wallet-balance-value { font-size: 1.25rem; font-weight: 900; color: var(--text); }
        .wallet-balance-value small { font-size: 0.85rem; color: var(--muted); margin-inline-start: 0.2rem; }
        .wallet-status-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.85rem; }
        .wallet-status-pill {
            display: inline-flex; align-items: center; gap: 0.38rem;
            border-radius: 999px; padding: 0.28rem 0.62rem; font-size: 0.74rem; font-weight: 800;
        }
        .wallet-status-pill--ok { background: rgba(34, 197, 94, 0.13); color: #166534; }
        .wallet-status-pill--locked { background: rgba(239, 68, 68, 0.13); color: #991b1b; }
        .wallet-actions { display: flex; gap: 0.45rem; flex-wrap: wrap; }
        .wallet-btn {
            border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.74rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.78rem; font-weight: 800; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .wallet-btn--primary { border-color: rgba(37, 99, 235, 0.35); background: var(--primary-soft); color: var(--primary-dark); }
        .wallet-btn--danger { border-color: rgba(239, 68, 68, 0.4); background: rgba(248, 113, 113, 0.12); color: #b91c1c; }
        .wallet-btn:disabled { opacity: 0.65; cursor: not-allowed; }
        .wallet-form-grid { display: grid; grid-template-columns: 1fr; gap: 0.65rem; }
        .wallet-radio-row { display: flex; gap: 0.6rem; flex-wrap: wrap; }
        .wallet-radio-row label {
            display: inline-flex; align-items: center; gap: 0.35rem;
            border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.35rem 0.55rem; font-size: 0.76rem; font-weight: 700;
        }
        .wallet-trans-table-wrap { max-height: 56vh; overflow: auto; border: 1px solid var(--border); border-radius: 0.7rem; }
        .wallet-trans-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        .wallet-trans-table th, .wallet-trans-table td { border-bottom: 1px solid var(--border); padding: 0.48rem 0.52rem; text-align: start; vertical-align: top; }
        .wallet-trans-table th { background: var(--primary-soft); font-weight: 800; position: sticky; top: 0; z-index: 1; }
        .wallet-trans-plus { color: #166534; font-weight: 800; }
        .wallet-trans-minus { color: #991b1b; font-weight: 800; }
        .wallet-empty { text-align: center; color: var(--muted); padding: 0.85rem; font-size: 0.78rem; }
        .wallet-sms-swal { max-width: 540px !important; padding: 1rem 1rem 0.85rem !important; border-radius: 0.9rem !important; }
        .wallet-sms-swal-title { font-size: 1rem !important; margin-bottom: 0.4rem !important; }
    </style>
@endpush

@section('content')
    @php
        $oldAccounts = old('accounts', []);
        $oldReferrers = old('referrers', []);
        $quickSmsTemplates = $smsTemplates ?? collect();
        $loanTypes = $loanTypes ?? collect();
        $loanManageMap = $loanManageMap ?? collect();
        $listFilterLabel = $listFilterLabel ?? null;
        $listFilterQuery = $listFilterQuery ?? [];
        $listScope = $listScope ?? 'all';
        $listSort = $listSort ?? null;
        $listSortDir = $listSortDir ?? 'asc';
        $listExportQuery = array_filter(array_merge(
            $listFilterQuery,
            ['q' => ($search ?? '') !== '' ? $search : null]
        ));
        $custSortUrl = static function (string $column) use ($listFilterQuery, $search, $listSort, $listSortDir): string {
            $nextDir = ($listSort === $column && $listSortDir === 'asc') ? 'desc' : 'asc';

            return route('admin.customers.index', array_filter(array_merge(
                $listFilterQuery,
                [
                    'q' => ($search ?? '') !== '' ? $search : null,
                    'sort' => $column,
                    'dir' => $nextDir,
                ]
            )));
        };
        $custSortIcon = static function (string $column) use ($listSort, $listSortDir): string {
            if ($listSort !== $column) {
                return 'fa-sort';
            }

            return $listSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        };
    @endphp

    <div class="cust-page">
        <div class="cust-head">
            <div class="cust-title-wrap">
                <h1>لیست مشتریان</h1>
                <p>مشاهده و ثبت مشتری جدید با اطلاعات هویتی، حساب بانکی و معرف‌ها.</p>
            </div>
            <div style="display:inline-flex; gap:0.45rem; align-items:center;">
                <button type="button" class="cust-reload-btn" onclick="window.location.reload()" title="بارگذاری مجدد" aria-label="بارگذاری مجدد">
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                </button>
                <a
                    href="{{ route('admin.customers.export-excel', $listExportQuery) }}"
                    class="cust-export-excel-btn"
                    title="دانلود خروجی اکسل مطابق همین فیلتر و جستجو"
                >
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
                <button type="button" class="cust-import-btn" id="cust-import-open-btn" aria-haspopup="dialog" title="ورود دسته‌جمعی مشتریان با فایل اکسل">
                    <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
                    ورود فایل اکسل مشتریان
                </button>
                <button type="button" class="cust-add-btn" id="cust-open-modal" aria-haspopup="dialog">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    افزودن مشتری
                </button>
            </div>
        </div>

        @if (!empty($listFilterLabel))
            <div class="cust-list-filter-banner" role="status">
                <span><strong>فیلتر فعال:</strong> {{ $listFilterLabel }}</span>
                <a href="{{ route('admin.customers.index', array_filter(['q' => ($search ?? '') !== '' ? $search : null])) }}" class="cust-list-filter-clear">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    حذف فیلتر
                </a>
            </div>
        @endif

        <div class="cust-head" style="margin-top: -0.25rem;">
            <form method="get" action="{{ route('admin.customers.index') }}" class="cust-search-row">
                <div class="cust-search">
                    <input type="search" name="q" value="{{ $search }}" placeholder="جستجو: کد، نام، موبایل، کد ملی..." autocomplete="off">
                </div>
                <select name="list_scope" class="cust-list-scope" aria-label="فیلتر مشتریان" onchange="this.form.submit()">
                    <option value="all" @selected($listScope === 'all')>همه مشتریان</option>
                    <option value="active_loan" @selected($listScope === 'active_loan')>دارای وام فعال</option>
                    <option value="overdue_installment" @selected($listScope === 'overdue_installment')>دارای قسط معوق</option>
                </select>
                @if ($listSort)
                    <input type="hidden" name="sort" value="{{ $listSort }}">
                    <input type="hidden" name="dir" value="{{ $listSortDir }}">
                @endif
                @if (!empty($listFilterQuery['disbursement_due_overdue']))
                    <input type="hidden" name="disbursement_due_overdue" value="1">
                @endif
            </form>
        </div>

        <div class="cust-card">
            <div class="cust-table-wrap">
                <table class="cust-table">
                    <thead>
                        <tr>
                            <th class="cust-th-sort @if($listSort === 'customer_code') is-active @endif">
                                <a href="{{ $custSortUrl('customer_code') }}" title="مرتب‌سازی بر اساس کد مشتری">
                                    <span>کد مشتری</span>
                                    <i class="fa-solid {{ $custSortIcon('customer_code') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'name') is-active @endif">
                                <a href="{{ $custSortUrl('name') }}" title="مرتب‌سازی بر اساس نام مشتری">
                                    <span>نام مشتری</span>
                                    <i class="fa-solid {{ $custSortIcon('name') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'loan_count') is-active @endif">
                                <a href="{{ $custSortUrl('loan_count') }}" title="مرتب‌سازی بر اساس تعداد وام">
                                    <span>تعداد وام</span>
                                    <i class="fa-solid {{ $custSortIcon('loan_count') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'loan_total') is-active @endif">
                                <a href="{{ $custSortUrl('loan_total') }}" title="مرتب‌سازی بر اساس مجموع مبلغ وام‌ها">
                                    <span>مجموع وام‌های دریافتی با بهره</span>
                                    <i class="fa-solid {{ $custSortIcon('loan_total') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'loan_remaining') is-active @endif">
                                <a href="{{ $custSortUrl('loan_remaining') }}" title="مرتب‌سازی بر اساس مانده اقساط">
                                    <span>مانده اقساط</span>
                                    <i class="fa-solid {{ $custSortIcon('loan_remaining') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'wallet') is-active @endif">
                                <a href="{{ $custSortUrl('wallet') }}" title="مرتب‌سازی بر اساس موجودی کیف پول">
                                    <span>موجودی کیف پول</span>
                                    <i class="fa-solid {{ $custSortIcon('wallet') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th class="cust-th-sort @if($listSort === 'membership_at') is-active @endif">
                                <a href="{{ $custSortUrl('membership_at') }}" title="مرتب‌سازی بر اساس تاریخ عضویت">
                                    <span>تاریخ عضویت</span>
                                    <i class="fa-solid {{ $custSortIcon('membership_at') }} cust-th-sort-icon" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th>پیامک‌ها</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $c)
                            @php
                                $loanMeta = $loanManageMap->get((string) $c->id, []);
                                $loanFiles = $loanMeta['loan_files'] ?? [];
                                $loanCount = (int) ($loanMeta['loan_count'] ?? 0);
                                $loanCodes = array_map(static fn ($item): string => (string) ($item['loan_code'] ?? ''), $loanFiles);
                                $loanTotalWithProfit = (int) ($loanMeta['loan_total_with_profit'] ?? 0);
                                $loanRemainInstallments = (int) ($loanMeta['loan_remaining_installments'] ?? 0);
                                $overdueInstId = (int) ($loanMeta['primary_overdue_installment_id'] ?? 0);
                                $overdueLfId = (int) ($loanMeta['primary_overdue_loan_file_id'] ?? 0);
                                $overdueCount = (int) ($loanMeta['overdue_installment_count'] ?? 0);
                                $overdueSmsTitle = $overdueInstId > 0
                                    ? 'ارسال پیامک معوق'.($overdueCount > 1 ? ' ('.\Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $overdueCount).' قسط معوق)' : '')
                                    : 'قسط معوق پرداخت‌نشده‌ای وجود ندارد';
                            @endphp
                            <tr>
                                <td>{{ $c->customer_code }}</td>
                                <td>
                                    <div class="cust-main-text">
                                        <a href="#" class="cust-name-link" data-cust-manage-loans data-customer-id="{{ $c->id }}" data-customer-name="{{ e($c->fullName()) }}" data-customer-mobile="{{ $c->mobile }}">
                                            {{ $c->fullName() }}
                                        </a>
                                    </div>
                                    <div class="cust-sub-text">تماس: {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($c->mobile) }}</div>
                                    <div class="cust-sub-text">کد ملی: {{ $c->national_id !== null && $c->national_id !== '' ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $c->national_id) : '—' }}</div>
                                </td>
                                <td>
                                    <div class="cust-loan-count">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $loanCount) }}</div>
                                    <div class="cust-loan-ids">
                                        @if (count($loanCodes) > 0)
                                            {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(implode('، ', $loanCodes)) }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </td>
                                <td class="cust-amount">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format($loanTotalWithProfit, 0)) }} تومان</td>
                                <td class="cust-amount">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format($loanRemainInstallments, 0)) }} تومان</td>
                                <td class="cust-amount">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int) ($c->wallet?->balance_toman ?? 0), 0)) }} تومان</td>
                                <td>
                                    @if ($c->membership_at)
                                        {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(jalali($c->membership_at)->format('Y/m/d')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="cust-sms-actions">
                                        <button type="button" class="cust-sms-circle-btn cust-sms-circle-btn--link" data-cust-quick-sms data-sms-type="wallet_link" data-customer-id="{{ $c->id }}" data-customer-name="{{ e($c->fullName()) }}" data-customer-mobile="{{ $c->mobile }}" title="ارسال لینک شارژ کیف پول">ل</button>
                                        <button type="button" class="cust-sms-circle-btn cust-sms-circle-btn--welcome" data-cust-quick-sms data-sms-type="welcome" data-customer-id="{{ $c->id }}" data-customer-name="{{ e($c->fullName()) }}" data-customer-mobile="{{ $c->mobile }}" title="ارسال پیامک خوش‌آمدگویی">خ</button>
                                        <button
                                            type="button"
                                            class="cust-sms-circle-btn cust-sms-circle-btn--inst-over"
                                            data-cust-quick-sms
                                            data-sms-type="installment_overdue"
                                            data-customer-id="{{ $c->id }}"
                                            data-customer-name="{{ e($c->fullName()) }}"
                                            data-customer-mobile="{{ $c->mobile }}"
                                            @if($overdueInstId > 0)
                                                data-installment-id="{{ $overdueInstId }}"
                                                data-loan-file-id="{{ $overdueLfId }}"
                                            @else
                                                disabled
                                            @endif
                                            title="{{ $overdueSmsTitle }}"
                                        >م</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="cust-ops" data-cust-ops>
                                        <button
                                            type="button"
                                            class="cust-ops-trigger"
                                            data-cust-ops-toggle
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            title="عملیات"
                                        >
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        </button>
                                        <div class="cust-ops-menu" data-cust-ops-menu hidden>
                                            <button type="button" class="cust-ops-item" data-cust-manage-loans data-customer-id="{{ $c->id }}" data-customer-name="{{ e($c->fullName()) }}" data-customer-mobile="{{ $c->mobile }}">
                                                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                                مدیریت وام ها
                                            </button>
                                            <button type="button" class="cust-ops-item" data-cust-wallet data-customer-id="{{ $c->id }}" data-customer-name="{{ e($c->fullName()) }}" data-customer-mobile="{{ $c->mobile }}">
                                                <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                                کیف پول
                                            </button>
                                            <button type="button" class="cust-ops-item" data-cust-edit data-customer-id="{{ $c->id }}">
                                                <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                                                ویرایش
                                            </button>
                                            <form method="post" action="{{ route('admin.customers.destroy', $c) }}" data-cust-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cust-ops-item cust-ops-item--danger">
                                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                                    حذف
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="cust-empty">هنوز مشتری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.list-pagination', ['paginator' => $customers])
        </div>
    </div>

    <div class="cust-overlay" id="cust-import-overlay" hidden aria-hidden="true">
        <div class="cust-modal cust-import-modal" role="dialog" aria-modal="true" aria-labelledby="cust-import-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="cust-import-title">ورود فایل اکسل مشتریان</h2>
                    <p class="loan-inst-modal-sub" style="margin-top:0.25rem;">فقط اطلاعات پایهٔ هویتی و تماس؛ حساب بانکی، معرف و پروندهٔ وام بعداً در سامانه ثبت می‌شود.</p>
                </div>
                <button type="button" class="cust-modal-close" id="cust-import-close-btn" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <a class="cust-import-dl" href="{{ route('admin.customers.import.sample-excel') }}" download>
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    دانلود فایل نمونه
                </a>
                <ul class="cust-import-help">
                    <li>سطر اول فقط عنوان ستون‌هاست؛ از سطر دوم دادهٔ مشتریان را وارد کنید. حداکثر ۵۰۰ ردیف در هر بار.</li>
                    <li>فایل نمونه با جداکنندهٔ تب (همان خروجی استاندارد اکسل) ساخته شده است؛ اکسل هنگام ذخیرهٔ CSV گاهی «ستون اول خالی» را حذف می‌کند؛ سامانه این حالت را تشخیص می‌دهد. فایل‌های ‎.xlsx واقعی را پس از ویرایش به صورت CSV UTF-8 یا متن یونیکد (جداشده با تب) ذخیره کنید یا همان نمونهٔ دانلودشده را بدون تبدیل بارگذاری کنید.</li>
                    <li>فیلدهای اجباری: نام، نام خانوادگی، نام پدر، کد ملی (۱۰ رقم معتبر)، موبایل (۰۹…)، شهر، آدرس، کد پستی (ده رقم).</li>
                    <li><strong>کد مشتری</strong> اختیاری است (خالی = تولید خودکار). اگر <strong>رمز عبور</strong> خالی یا کوتاه‌تر از ۸ نویسه باشد، برای آن ردیف رمز تصادفی امن تعیین می‌شود؛ پس از ثبت می‌توانید از همین پنل ویرایش یا بازیابی رمز انجام دهید.</li>
                    <li>تاریخ‌ها به‌صورت شمسی مانند <span dir="ltr">1404/01/15</span>؛ تاریخ عضویت و تولد و ایمیل و تلفن ثابت اختیاری‌اند.</li>
                    <li>ردیف‌های تکراری (کد ملی یا موبایل تکراری داخل یک فایل یا در دیتابیس) رد و در گزارش خطا نشان داده می‌شوند.</li>
                </ul>
                <form id="cust-import-form">
                    @csrf
                    <div class="cust-field cust-import-file">
                        <label for="cust-import-file-input">انتخاب فایل اکسل یا CSV آماده‌شده مطابق نمونه <span class="req">*</span></label>
                        <input id="cust-import-file-input" name="excel_file" type="file" accept=".xls,.csv,.txt,text/xml,application/vnd.ms-excel,text/plain" required>
                    </div>
                    <div class="cust-import-actions">
                        <button type="submit" class="cust-submit" id="cust-import-submit-btn">بارگذاری و ثبت مشتریان</button>
                        <button type="button" class="cust-cancel" id="cust-import-dismiss-btn">بستن</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="cust-modal-overlay" hidden aria-hidden="true">
        <div class="cust-modal" role="dialog" aria-modal="true" aria-labelledby="cust-modal-title" id="cust-modal">
            <div class="cust-modal-head">
                <div>
                    <h2 id="cust-modal-title">افزودن مشتری جدید</h2>
                    <p id="cust-modal-desc">فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.</p>
                </div>
                <button type="button" class="cust-modal-close" id="cust-close-modal" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form method="post" action="{{ route('admin.customers.store') }}" id="cust-store-form" novalidate>
                    @csrf

                    <div class="cust-form-grid">
                        <div class="cust-field">
                            <label for="cust-code">کد مشتری</label>
                            <input id="cust-code" name="customer_code" type="text" value="{{ old('customer_code') }}" placeholder="خالی = تولید خودکار" autocomplete="off">
                            @error('customer_code')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-username-preview">نام کاربری <span class="req">*</span></label>
                            <input id="cust-username-preview" type="text" readonly disabled placeholder="با وارد کردن موبایل پر می‌شود" value="">
                        </div>

                        <div class="cust-field">
                            <label for="cust-fname">نام <span class="req">*</span></label>
                            <input id="cust-fname" name="first_name" type="text" value="{{ old('first_name') }}" required autocomplete="given-name">
                            @error('first_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-lname">نام خانوادگی <span class="req">*</span></label>
                            <input id="cust-lname" name="last_name" type="text" value="{{ old('last_name') }}" required autocomplete="family-name">
                            @error('last_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-father">نام پدر <span class="req" id="cust-father-req" style="display:none">*</span></label>
                            <input id="cust-father" name="father_name" type="text" value="{{ old('father_name') }}">
                            @error('father_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-national">کد ملی <span class="req" id="cust-national-req" style="display:none">*</span></label>
                            <input id="cust-national" name="national_id" type="text" inputmode="numeric" value="{{ old('national_id') }}" maxlength="10">
                            @error('national_id')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-mobile">موبایل <span class="req">*</span></label>
                            <input id="cust-mobile" name="mobile" type="text" inputmode="numeric" value="{{ old('mobile') }}" placeholder="09123456789" required autocomplete="tel">
                            @error('mobile')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-mobile2">موبایل دوم</label>
                            <input id="cust-mobile2" name="mobile2" type="text" inputmode="numeric" value="{{ old('mobile2') }}" placeholder="09123456789" autocomplete="tel">
                            @error('mobile2')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-phone">تلفن ثابت</label>
                            <input id="cust-phone" name="phone_landline" type="text" value="{{ old('phone_landline') }}">
                            @error('phone_landline')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-membership-jdate">تاریخ عضویت</label>
                            <input id="cust-membership-jdate" name="membership_jdate" type="text" value="{{ old('membership_jdate') }}" autocomplete="off" placeholder="۱۴۰۳/۰۱/۰۱">
                            @error('membership_jdate')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-birth-jdate">تاریخ تولد</label>
                            <input id="cust-birth-jdate" name="birth_jdate" type="text" value="{{ old('birth_jdate') }}" autocomplete="off">
                            @error('birth_jdate')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-email">ایمیل</label>
                            <input id="cust-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                            @error('email')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-password">کلمه عبور <span class="req" id="cust-password-req">*</span></label>
                            <input id="cust-password" name="password" type="password" autocomplete="new-password">
                            <span class="cust-field-hint" id="cust-password-hint" hidden>برای حفظ رمز فعلی هنگام ویرایش این فیلد را خالی بگذارید.</span>
                            @error('password')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-city">شهر <span class="req" id="cust-city-req" style="display:none">*</span></label>
                            <input id="cust-city" name="city" type="text" value="{{ old('city') }}">
                            @error('city')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field cust-field--full">
                            <label for="cust-address">آدرس <span class="req" id="cust-address-req" style="display:none">*</span></label>
                            <textarea id="cust-address" name="address">{{ old('address') }}</textarea>
                            @error('address')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-postal">کدپستی <span class="req" id="cust-postal-req" style="display:none">*</span></label>
                            <input id="cust-postal" name="postal_code" type="text" inputmode="numeric" value="{{ old('postal_code') }}" maxlength="10">
                            @error('postal_code')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="cust-section">
                        <div class="cust-section-head">
                            <div>
                                <h3>شماره حساب‌ها</h3>
                                <p>شماره کارت، حساب یا شبا در یک فیلد؛ در صورت نیاز چند ردیف اضافه کنید.</p>
                            </div>
                            <button type="button" class="cust-mini-btn" id="cust-add-bank">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                شماره حساب جدید
                            </button>
                        </div>
                        @error('accounts')<div class="cust-block-error">{{ $message }}</div>@enderror
                        <div id="cust-bank-rows">
                            @if (count($oldAccounts) > 0)
                                @foreach ($oldAccounts as $i => $row)
                                    <div class="cust-repeat-row" data-bank-row>
                                        <div class="cust-field">
                                            <label>شماره کارت / حساب / شبا</label>
                                            <input name="accounts[{{ $i }}][account_identifier]" value="{{ $row['account_identifier'] ?? '' }}" placeholder="مثلاً شبا یا شماره کارت">
                                        </div>
                                        <div class="cust-field">
                                            <label>بانک</label>
                                            <input name="accounts[{{ $i }}][bank_name]" value="{{ $row['bank_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>شعبه</label>
                                            <input name="accounts[{{ $i }}][branch_name]" value="{{ $row['branch_name'] ?? '' }}">
                                        </div>
                                        <button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="cust-section">
                        <div class="cust-section-head">
                            <div>
                                <h3>معرف‌ها</h3>
                                <p>در صورت وجود معرف، نام کامل و موبایل را وارد کنید.</p>
                            </div>
                            <button type="button" class="cust-mini-btn" id="cust-add-referrer">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                افزودن معرف
                            </button>
                        </div>
                        @error('referrers')<div class="cust-block-error">{{ $message }}</div>@enderror
                        <div id="cust-referrer-rows">
                            @if (count($oldReferrers) > 0)
                                @foreach ($oldReferrers as $i => $row)
                                    <div class="cust-ref-row" data-ref-row>
                                        <div class="cust-field">
                                            <label>نام</label>
                                            <input name="referrers[{{ $i }}][first_name]" value="{{ $row['first_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>نام خانوادگی</label>
                                            <input name="referrers[{{ $i }}][last_name]" value="{{ $row['last_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>شماره تماس</label>
                                            <input name="referrers[{{ $i }}][phone]" value="{{ $row['phone'] ?? '' }}" placeholder="09xxxxxxxxx">
                                        </div>
                                        <button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <input type="hidden" name="send_credentials" value="0" id="cust-send-hidden">

                    <div class="cust-send-row">
                        <input type="checkbox" id="cust-send-chk">
                        <label for="cust-send-chk">ارسال نام کاربری و رمز عبور برای کاربر (پیامک)</label>
                    </div>

                    <div class="cust-actions">
                        <button type="button" class="cust-cancel" id="cust-cancel-modal">انصراف</button>
                        <button type="submit" class="cust-submit">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="wallet-modal-overlay" hidden aria-hidden="true">
        <div class="cust-modal" role="dialog" aria-modal="true" aria-labelledby="wallet-modal-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="wallet-modal-title">کیف پول مشتری</h2>
                    <p id="wallet-modal-subtitle">مدیریت اعتبار، قفل/بازکردن و عملیات تراکنش‌ها</p>
                </div>
                <button type="button" class="cust-modal-close" id="wallet-close-modal" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div class="wallet-balance-card">
                    <div class="wallet-balance-label">اعتبار فعلی</div>
                    <div class="wallet-balance-value" id="wallet-balance-view">0 <small>تومان</small></div>
                </div>
                <div class="wallet-status-row">
                    <span class="wallet-status-pill wallet-status-pill--ok" id="wallet-lock-pill">
                        <i class="fa-solid fa-lock-open"></i>
                        فعال
                    </span>
                    <button type="button" class="wallet-btn wallet-btn--danger" id="wallet-lock-toggle-btn">
                        <i class="fa-solid fa-lock"></i>
                        قفل کیف پول
                    </button>
                </div>
                <div class="wallet-actions">
                    <button type="button" class="wallet-btn wallet-btn--primary" id="wallet-open-adjust-btn">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        واریز / برداشت
                    </button>
                    <button type="button" class="wallet-btn" id="wallet-open-transactions-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        مشاهده تراکنش‌ها
                    </button>
                    <button type="button" class="wallet-btn" id="wallet-export-excel-btn">
                        <i class="fa-solid fa-file-excel"></i>
                        خروجی اکسل تراکنش‌ها
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="wallet-adjust-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(560px,100%)" role="dialog" aria-modal="true" aria-labelledby="wallet-adjust-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="wallet-adjust-title">ثبت تراکنش کیف پول</h2>
                    <p>اعتبار جاری: <strong id="wallet-adjust-balance">0 تومان</strong></p>
                </div>
                <button type="button" class="cust-modal-close" id="wallet-adjust-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form id="wallet-adjust-form" class="wallet-form-grid" novalidate>
                    <div class="wallet-radio-row">
                        <label><input type="radio" name="direction" value="deposit" checked> واریز</label>
                        <label><input type="radio" name="direction" value="withdraw"> برداشت</label>
                    </div>
                    <div class="cust-field">
                        <label for="wallet-amount">مبلغ (تومان)</label>
                        <input id="wallet-amount" name="amount_toman" inputmode="numeric" placeholder="مثلاً 500000" required>
                    </div>
                    <div class="cust-field">
                        <label for="wallet-description">توضیحات</label>
                        <textarea id="wallet-description" name="description" maxlength="500" placeholder="دلیل تراکنش را ثبت کنید..."></textarea>
                    </div>
                    <div class="cust-actions" style="margin-top:0.2rem;">
                        <button type="button" class="cust-cancel" id="wallet-adjust-cancel">انصراف</button>
                        <button type="submit" class="cust-submit">ذخیره تراکنش</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="wallet-trans-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(980px,100%)" role="dialog" aria-modal="true" aria-labelledby="wallet-trans-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="wallet-trans-title">لاگ تراکنش‌های کیف پول</h2>
                    <p>فقط عملیات ثبت‌شده توسط مدیر در این نسخه نمایش داده می‌شود.</p>
                </div>
                <button type="button" class="cust-modal-close" id="wallet-trans-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div class="wallet-trans-table-wrap">
                    <table class="wallet-trans-table">
                        <thead>
                            <tr>
                                <th>زمان</th>
                                <th>نوع</th>
                                <th>مبلغ</th>
                                <th>موجودی بعد</th>
                                <th>توضیحات</th>
                                <th>اپراتور</th>
                            </tr>
                        </thead>
                        <tbody id="wallet-trans-tbody">
                            <tr><td colspan="6" class="wallet-empty">هنوز تراکنشی ثبت نشده است.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="quick-sms-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(560px,100%)" role="dialog" aria-modal="true" aria-labelledby="quick-sms-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="quick-sms-title">ارسال سریع پیامک</h2>
                    <p id="quick-sms-subtitle">ارسال پیامک به مشتری</p>
                </div>
                <button type="button" class="cust-modal-close" id="quick-sms-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form id="quick-sms-form" class="wallet-form-grid" novalidate>
                    <div class="cust-field">
                        <label for="quick-sms-template">انتخاب قالب پیامک</label>
                        <select id="quick-sms-template" name="sms_template_id">
                            <option value="">بدون قالب (متن آزاد)</option>
                            @foreach ($quickSmsTemplates as $tpl)
                                <option value="{{ $tpl['id'] ?? '' }}">{{ ($tpl['title'] ?? '') . ' (' . ($tpl['category'] ?? '') . ')' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cust-field">
                        <label for="quick-sms-text">متن پیامک</label>
                        <textarea id="quick-sms-text" name="sms_text" maxlength="1000" placeholder="متن پیامک را بنویسید..."></textarea>
                    </div>
                    <div class="cust-actions" style="margin-top:0.2rem;">
                        <button type="button" class="cust-cancel" id="quick-sms-cancel">انصراف</button>
                        <button type="submit" class="cust-submit">ارسال پیامک</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-manage-overlay" hidden aria-hidden="true">
        <div class="cust-modal loan-manage-modal" role="dialog" aria-modal="true" aria-labelledby="loan-manage-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-manage-title">مدیریت وام ها</h2>
                </div>
                <button type="button" class="cust-modal-close" id="loan-manage-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body loan-manage-modal-body">
                <div class="loan-manage-sticky">
                <div class="loan-manage-top">
                    <button type="button" class="loan-manage-pill" id="loan-manage-open-edit">
                        <i class="fa-regular fa-user loan-manage-pill-ico" aria-hidden="true"></i>
                        <span class="loan-manage-pill-label">نام کاربر:</span>
                        <span class="loan-manage-pill-value" id="loan-manage-customer-name">—</span>
                    </button>
                    <div class="loan-manage-pill">
                        <i class="fa-solid fa-mobile-screen-button loan-manage-pill-ico" aria-hidden="true"></i>
                        <span class="loan-manage-pill-label">موبایل:</span>
                        <span class="loan-manage-pill-value" id="loan-manage-customer-mobile">—</span>
                    </div>
                    <div class="loan-manage-pill">
                        <i class="fa-solid fa-chart-line loan-manage-pill-ico" aria-hidden="true"></i>
                        <span class="loan-manage-pill-label">وضعیت خوش حسابی:</span>
                        <span class="loan-manage-pill-value" id="loan-manage-credit-status">در حال ارزیابی</span>
                    </div>
                    <button type="button" class="loan-manage-pill" id="loan-manage-open-wallet">
                        <i class="fa-solid fa-wallet loan-manage-pill-ico" aria-hidden="true"></i>
                        <span class="loan-manage-pill-label">اعتبار کیف پول:</span>
                        <span class="loan-manage-pill-value" id="loan-manage-wallet-balance">در حال دریافت...</span>
                    </button>
                </div>
                <hr class="loan-manage-sep">
                <div class="loan-tabs" role="tablist" aria-label="تب‌های مدیریت وام">
                    <button type="button" class="loan-tab-btn is-active" data-loan-tab="files">پرونده وام ها</button>
                    <button type="button" class="loan-tab-btn" data-loan-tab="requests">درخواست وام ها</button>
                    <button type="button" class="loan-tab-btn" data-loan-tab="transactions">تراکنش ها</button>
                    <button type="button" class="loan-tab-btn" data-loan-tab="sms">پیامک ها</button>
                    <button type="button" class="loan-tab-btn" data-loan-tab="tickets">تیکت ها</button>
                    <button type="button" class="loan-tab-btn" data-loan-tab="guarantees">تضامین</button>
                </div>
                <div class="loan-files-filter-bar" id="loan-files-filter-bar" hidden>
                    <span class="loan-files-summary" id="loan-files-filter-hint" style="font-size:0.68rem;color:var(--muted);font-weight:700;">فیلتر پرونده‌ها</span>
                    <label class="loan-files-filter-check" for="loan-files-hide-settled">
                        <input type="checkbox" id="loan-files-hide-settled" checked>
                        <span>مخفی کردن پرونده‌های تسویه‌شده</span>
                    </label>
                </div>
                </div>
                <div class="loan-manage-scroll">
                <div class="loan-tab-panel" data-loan-panel="files">
                    <div class="loan-files-head">
                        <div class="loan-files-summary" id="loan-files-summary">برای این مشتری هنوز پرونده وام ثبت نشده است.</div>
                        <button type="button" class="cust-mini-btn" id="loan-open-create-modal">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            افزودن وام
                        </button>
                    </div>
                    <div id="loan-files-list" class="loan-files-list">
                        <div class="loan-files-empty">در حال بارگذاری...</div>
                    </div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="requests" hidden>
                    <div class="loan-lrq-embed-wrap">
                        <div id="loan-manage-lrq-loading" class="loan-lrq-embed-loading" hidden aria-live="polite" aria-busy="false">
                            <div class="loan-lrq-embed-loading-inner">
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>در حال بارگذاری اطلاعات…</span>
                            </div>
                        </div>
                        <iframe
                            id="loan-manage-lrq-iframe"
                            class="loan-lrq-embed-iframe"
                            title="درخواست وام‌های مشتری"
                            loading="lazy"
                            referrerpolicy="same-origin"
                        ></iframe>
                    </div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="transactions" hidden>
                    <div class="loan-lrq-embed-wrap">
                        <div id="loan-manage-ctx-loading" class="loan-lrq-embed-loading" hidden aria-live="polite" aria-busy="false">
                            <div class="loan-lrq-embed-loading-inner">
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>در حال بارگذاری تراکنش‌ها…</span>
                            </div>
                        </div>
                        <iframe
                            id="loan-manage-ctx-iframe"
                            class="loan-lrq-embed-iframe"
                            title="تراکنش‌های مشتری"
                            loading="lazy"
                            referrerpolicy="same-origin"
                        ></iframe>
                    </div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="sms" hidden>
                    <div class="loan-tab-panel-toolbar-row" dir="ltr">
                        <div class="loan-tab-panel-actions">
                            <button type="button" class="cust-mini-btn" id="loan-sms-reload" title="بارگذاری مجدد"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                            <a class="cust-mini-btn" id="loan-sms-excel" href="#" title="خروجی اکسل"><i class="fa-solid fa-file-excel" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <div class="loan-sms-date-toolbar">
                        <div class="loan-sms-day-nav">
                            <button type="button" class="loan-sms-day-btn" id="loan-sms-day-prev">روز قبل</button>
                            <input type="text" class="loan-sms-day-current" id="loan-sms-day-input" readonly autocomplete="off" aria-label="انتخاب تاریخ گزارش پیامک" placeholder="تاریخ">
                            <button type="button" class="loan-sms-day-btn" id="loan-sms-day-next">روز بعد</button>
                        </div>
                    </div>
                    <p class="loan-sms-mobile-note" id="loan-sms-mobile-missing" hidden>برای این مشتری شماره موبایل معتبر ثبت نشده؛ گزارش پیامک خالی است.</p>
                    <div class="loan-sms-card">
                        <div class="loan-sms-table-wrap">
                            <table class="loan-sms-table">
                                <thead>
                                    <tr>
                                        <th>پنل پیامک</th>
                                        <th>وضعیت</th>
                                        <th>زمان ارسال</th>
                                        <th>متن</th>
                                        <th>دریافت کننده</th>
                                        <th>نوع</th>
                                        <th>هزینه</th>
                                    </tr>
                                </thead>
                                <tbody id="loan-sms-logs-body">
                                    <tr><td colspan="7" class="loan-sms-empty">در حال بارگذاری...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="tickets" hidden>
                    <div class="loan-lrq-embed-wrap">
                        <div id="loan-manage-tickets-loading" class="loan-lrq-embed-loading" hidden aria-live="polite" aria-busy="false">
                            <div class="loan-lrq-embed-loading-inner">
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>در حال بارگذاری تیکت‌ها…</span>
                            </div>
                        </div>
                        <iframe
                            id="loan-manage-tickets-iframe"
                            class="loan-lrq-embed-iframe"
                            title="تیکت‌های مشتری"
                            loading="lazy"
                            referrerpolicy="same-origin"
                        ></iframe>
                    </div>
                </div>
                    <div class="loan-tab-panel" data-loan-panel="guarantees" hidden>
                    <div class="loan-tab-panel-toolbar-row" dir="ltr">
                        <div class="loan-tab-panel-actions">
                            <button type="button" class="cust-mini-btn" id="loan-guarantees-reload" title="بارگذاری مجدد"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                            <a class="cust-mini-btn" id="loan-guarantees-excel" href="#" title="خروجی اکسل"><i class="fa-solid fa-file-excel" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <h3 class="loan-guarantees-report-title">گزارش تضامین</h3>
                    <div class="loan-guarantees-report-controls">
                        <input type="search" id="loan-guarantees-search" class="loan-guarantees-search-input" placeholder="جستجو در شماره وام، نوع وام، نوع ضمانت، جزئیات…" autocomplete="off" dir="auto">
                    </div>
                    <p class="loan-guarantees-summary-line" id="loan-guarantees-summary" aria-live="polite"></p>
                    <div class="loan-guarantees-report-wrap">
                        <table class="loan-guarantees-report-table">
                            <thead>
                                <tr>
                                    <th>اطلاعات وام</th>
                                    <th>اطلاعات مشتری</th>
                                    <th>مبلغ وام</th>
                                    <th>مبلغ اقساط</th>
                                    <th>نوع ضمانت</th>
                                    <th>اطلاعات ضمانت</th>
                                </tr>
                            </thead>
                            <tbody id="loan-guarantees-report-body">
                                <tr><td colspan="6" style="text-align:center;padding:1rem;">در حال بارگذاری...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-create-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(980px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-create-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-create-title">ثبت وام</h2>
                    <p id="loan-create-subtitle">ثبت پرونده وام برای مشتری انتخاب‌شده</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-create-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form id="loan-create-form" class="cust-form-grid" novalidate>
                    <div class="cust-field">
                        <label for="loan-start-jdate">تاریخ شروع وام <span class="req">*</span></label>
                        <input id="loan-start-jdate" name="loan_start_jdate" required placeholder="مثلاً 1405/02/10">
                    </div>
                    <div class="cust-field">
                        <label for="loan-disbursement-due-jdate">سررسید واریز به حساب مشتری</label>
                        <input id="loan-disbursement-due-jdate" name="disbursement_due_jdate" placeholder="اختیاری">
                    </div>
                    <div class="cust-field">
                        <label for="loan-customer-name">مشتری مد نظر <span class="req">*</span></label>
                        <input id="loan-customer-name" disabled>
                    </div>
                    <div class="cust-field">
                        <label for="loan-type-id">وام <span class="req">*</span></label>
                        <select id="loan-type-id" name="loan_type_id" required>
                            <option value="">انتخاب نوع وام</option>
                            @foreach ($loanTypes as $lt)
                                <option value="{{ $lt->id }}" data-interest-rate="{{ (float) $lt->interest_rate }}" data-default-unit="{{ $lt->installment_gap_unit }}">{{ $lt->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cust-field">
                        <label for="loan-amount">مبلغ (تومان) <span class="req">*</span></label>
                        <input id="loan-amount" name="amount_toman" inputmode="numeric" required placeholder="مثلاً 150,000,000">
                    </div>
                    <div class="cust-field">
                        <label for="loan-installments-count">تعداد اقساط <span class="req">*</span></label>
                        <input id="loan-installments-count" name="installments_count" type="number" min="1" step="1" required value="6">
                    </div>
                    <div class="cust-field">
                        <label for="loan-installment-interval-count">فاصله اقساط <span class="req">*</span></label>
                        <input id="loan-installment-interval-count" name="installment_interval_count" type="number" min="1" step="1" required value="1">
                    </div>
                    <div class="cust-field">
                        <label for="loan-installment-interval-unit">محدوده زمانی اقساط <span class="req">*</span></label>
                        <select id="loan-installment-interval-unit" name="installment_interval_unit" required>
                            <option value="monthly">ماهانه</option>
                            <option value="weekly">هفتگی</option>
                        </select>
                    </div>
                    <div class="cust-field">
                        <label for="loan-installment-amount">مبلغ هر قسط (تومان) <span class="req">*</span></label>
                        <input id="loan-installment-amount" name="installment_amount_toman" inputmode="numeric" required>
                        <span class="loan-interest-note" id="loan-installment-help">به‌صورت خودکار محاسبه می‌شود (رند تا ۱۰٬۰۰۰ تومان)؛ قابل ویرایش است.</span>
                    </div>
                    <div class="cust-field">
                        <label for="loan-down-payment">مبلغ پیش پرداخت</label>
                        <input id="loan-down-payment" name="down_payment_toman" inputmode="numeric" placeholder="اختیاری">
                    </div>
                    <div class="cust-field">
                        <label for="loan-sub-file-number">شماره پرونده فرعی</label>
                        <input id="loan-sub-file-number" name="sub_file_number" maxlength="120">
                    </div>
                    <div class="cust-field cust-field--full">
                        <label for="loan-description">توضیحات</label>
                        <textarea id="loan-description" name="description" maxlength="3000"></textarea>
                    </div>
                    <div class="cust-field cust-field--full loan-extra-box" id="loan-settled-section" hidden>
                        <label><input type="checkbox" id="loan-is-settled" name="is_settled" value="1"> وام تسویه شده است</label>
                        <div id="loan-settled-wrap" style="margin-top:.45rem;" hidden>
                            <label for="loan-settled-jdate">تاریخ تسویه</label>
                            <input id="loan-settled-jdate" name="settled_jdate" placeholder="مثلاً 1405/10/20">
                        </div>
                    </div>
                    <div class="cust-field cust-field--full loan-extra-box">
                        <label><input type="checkbox" id="loan-has-custom-interest" name="has_custom_interest_rate" value="1"> تغییر درصد بهره برای پرونده</label>
                        <div id="loan-custom-interest-wrap" style="margin-top:.45rem;" hidden>
                            <div class="loan-interest-note">درصد بهره فعلی: <strong id="loan-current-interest-rate">—</strong></div>
                            <label for="loan-custom-interest-rate">درصد بهره جدید</label>
                            <input id="loan-custom-interest-rate" name="custom_interest_rate" type="number" min="0" max="100" step="0.01" placeholder="مثلاً 18.5">
                        </div>
                    </div>
                    <div class="cust-field cust-field--full">
                        <div class="loan-interest-note" id="loan-total-check">جمع اقساط: 0 تومان</div>
                    </div>
                    <div class="cust-field cust-field--full loan-extra-box loan-creation-otp-section" id="loan-creation-otp-section" hidden>
                        <label>احراز هویت مشتری با پیامک <span class="req">*</span></label>
                        <p class="loan-interest-note" style="margin-top:0.2rem">برای ثبت پرونده وام، ارسال و تایید کد به موبایل مشتری الزامی است.</p>
                        <div class="loan-interest-note" style="margin-top:0.25rem">موبایل مشتری: <strong id="loan-creation-otp-mobile-view">—</strong></div>
                        <div style="margin-top:0.45rem">
                            <button type="button" class="loan-creation-otp-btn" id="loan-creation-otp-send">ارسال کد تایید</button>
                        </div>
                        <div id="loan-creation-otp-panel" class="cust-field" style="margin-top:0.5rem;padding:0;border:0;" hidden>
                            <label for="loan-creation-otp-code">کد پیامک‌شده</label>
                            <div class="loan-guarantee-guarantor-otp-actions">
                                <input id="loan-creation-otp-code" type="text" inputmode="numeric" maxlength="8" placeholder="کد ۶ رقمی" style="max-width:11rem;">
                                <button type="button" class="loan-file-btn" id="loan-creation-otp-verify">تایید کد</button>
                            </div>
                        </div>
                        <div id="loan-creation-otp-verified" class="loan-creation-otp-verified" hidden>احراز مشتری انجام شد.</div>
                        <input type="hidden" id="loan-creation-verification-token" value="" autocomplete="off">
                        <input type="hidden" id="loan-creation-otp-session" value="" autocomplete="off">
                    </div>
                    <div class="cust-actions cust-field--full" style="margin-top:0.2rem;">
                        <button type="button" class="cust-cancel" id="loan-create-cancel">انصراف</button>
                        <button type="submit" class="cust-submit">ثبت وام</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-sms-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(560px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-sms-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-sms-title">ارسال پیامک ثبت پرونده وام</h2>
                    <p id="loan-sms-subtitle">ارسال برای مشتری</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-sms-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form id="loan-sms-form" class="wallet-form-grid" novalidate>
                    <div class="cust-field">
                        <label for="loan-sms-template">انتخاب قالب پیامک</label>
                        <select id="loan-sms-template" name="sms_template_id">
                            <option value="">بدون قالب (متن آزاد)</option>
                            @foreach ($quickSmsTemplates as $tpl)
                                <option value="{{ $tpl['id'] ?? '' }}">{{ ($tpl['title'] ?? '') . ' (' . ($tpl['category'] ?? '') . ')' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cust-field">
                        <label for="loan-sms-text">متن پیامک</label>
                        <textarea id="loan-sms-text" name="sms_text" maxlength="1000" placeholder="متن پیامک را بنویسید..."></textarea>
                    </div>
                    <div class="cust-actions" style="margin-top:0.2rem;">
                        <button type="button" class="cust-cancel" id="loan-sms-cancel">انصراف</button>
                        <button type="submit" class="cust-submit">ارسال پیامک ثبت پرونده جدید</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-installments-overlay" hidden aria-hidden="true">
        <div class="cust-modal loan-inst-modal" style="width:min(1120px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-inst-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-inst-title">اقساط و پرداخت</h2>
                    <p id="loan-inst-subtitle" class="loan-inst-modal-sub">در حال بارگذاری...</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-inst-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body loan-inst-modal-body">
                <div class="loan-inst-summary" id="loan-inst-summary" hidden>
                    <div class="loan-inst-summary__card loan-inst-summary__card--loan">
                        <span class="loan-inst-summary__label">نام وام و مبلغ اصلی</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-type">—</div>
                        <div class="loan-inst-summary__muted" id="loan-inst-sum-amount">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--start">
                        <span class="loan-inst-summary__label">تاریخ شروع وام</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-start">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--inst">
                        <span class="loan-inst-summary__label">مبلغ هر قسط</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-installment">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--paid-count">
                        <span class="loan-inst-summary__label">تعداد اقساط پرداخت شده</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-paid-count">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--remain-count">
                        <span class="loan-inst-summary__label">تعداد اقساط مانده</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-remain-count">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--remain-amount">
                        <span class="loan-inst-summary__label">مبلغ اقساط باقیمانده</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-remain-amount">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--paid-amount">
                        <span class="loan-inst-summary__label">مبلغ اقساط پرداخت شده</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-paid-amount">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--late">
                        <span class="loan-inst-summary__label">مبلغ دیرکرد</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-late">—</div>
                    </div>
                    <div class="loan-inst-summary__card loan-inst-summary__card--early">
                        <span class="loan-inst-summary__label">مبلغ زودکرد</span>
                        <div class="loan-inst-summary__value" id="loan-inst-sum-early">—</div>
                    </div>
                </div>
                <div class="loan-inst-table-scroll">
                    <table class="loan-inst-table">
                        <thead>
                            <tr>
                                <th>شماره قسط</th>
                                <th>مبلغ قسط</th>
                                <th>تاریخ سررسید</th>
                                <th>مبلغ پرداختی</th>
                                <th>مغایرت مبلغ</th>
                                <th>تاریخ واریز</th>
                                <th>دیرکرد / زودکرد</th>
                                <th>ثبت توسط</th>
                                <th>پیامک‌ها</th>
                                <th class="loan-inst-th-ops">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="loan-inst-tbody">
                            <tr><td colspan="10" class="loan-inst-empty">در حال بارگذاری...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-installment-edit-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(560px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-inst-edit-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-inst-edit-title">ویرایش قسط</h2>
                    <p id="loan-inst-edit-subtitle" class="loan-inst-modal-sub" style="margin:0.25rem 0 0;"></p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-inst-edit-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div class="loan-inst-edit-banner">
                    <div class="loan-inst-edit-banner__col">
                        <strong>مشتری و پرونده</strong>
                        <div id="loan-inst-edit-col-customer">—</div>
                    </div>
                    <div class="loan-inst-edit-banner__col">
                        <strong>اقساط</strong>
                        <div id="loan-inst-edit-col-schedule">—</div>
                    </div>
                    <div class="loan-inst-edit-banner__col">
                        <strong>باقیمانده برنامه</strong>
                        <div id="loan-inst-edit-col-remaining">—</div>
                    </div>
                </div>
                <div class="cust-form-grid" style="gap:0.85rem;">
                    <div class="cust-field">
                        <label for="loan-inst-edit-amount">مبلغ قسط (تومان)</label>
                        <input id="loan-inst-edit-amount" type="text" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="cust-field">
                        <label for="loan-inst-edit-due">تاریخ سررسید</label>
                        <input id="loan-inst-edit-due" type="text" autocomplete="off" placeholder="انتخاب از تقویم">
                    </div>
                </div>
                <input type="hidden" id="loan-inst-edit-installment-id" value="">
                <div class="cust-actions" style="margin-top:1rem;">
                    <button type="button" class="cust-cancel" id="loan-inst-edit-cancel">انصراف</button>
                    <button type="button" class="cust-submit" id="loan-inst-edit-save">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-installment-pay-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(760px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-inst-pay-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-inst-pay-title">ثبت واریزی قسط</h2>
                    <p id="loan-inst-pay-subtitle" class="loan-inst-modal-sub" style="margin:0.25rem 0 0;"></p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-inst-pay-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div class="loan-inst-edit-banner loan-inst-pay-banner">
                    <div class="loan-inst-edit-banner__col">
                        <strong>مشتری و پرونده</strong>
                        <div id="loan-inst-pay-col-customer">—</div>
                    </div>
                    <div class="loan-inst-edit-banner__col">
                        <strong>اقساط</strong>
                        <div id="loan-inst-pay-col-schedule">—</div>
                    </div>
                    <div class="loan-inst-edit-banner__col">
                        <strong>باقیمانده اقساط</strong>
                        <div id="loan-inst-pay-col-remaining">—</div>
                    </div>
                    <div class="loan-inst-edit-banner__col">
                        <strong>تاریخ شروع وام</strong>
                        <div id="loan-inst-pay-col-start">—</div>
                    </div>
                </div>
                <div class="loan-inst-pay-strip" id="loan-inst-pay-strip">—</div>
                <div class="cust-actions" style="margin:0 0 0.5rem; flex-wrap:wrap; gap:0.5rem;">
                    <button type="button" class="cust-submit" id="loan-inst-pay-add" style="font-size:0.78rem;">افزودن پرداخت جدید</button>
                </div>
                <div class="loan-inst-pay-form-wrap" id="loan-inst-pay-form-wrap" hidden>
                    <div class="cust-form-grid" style="gap:0.75rem;">
                        <div class="cust-field">
                            <label for="loan-inst-pay-method">نحوه پرداخت <span class="req">*</span></label>
                            <select id="loan-inst-pay-method"></select>
                        </div>
                        <div class="cust-field">
                            <label for="loan-inst-pay-amount">مبلغ پرداختی (تومان) <span class="req">*</span></label>
                            <input id="loan-inst-pay-amount" type="text" inputmode="numeric" autocomplete="off" placeholder="مثلاً ۱٬۰۰۰٬۰۰۰">
                        </div>
                        <div class="cust-field">
                            <label for="loan-inst-pay-ref-due">تاریخ سررسید</label>
                            <input id="loan-inst-pay-ref-due" type="text" autocomplete="off" placeholder="اختیاری — تقویم شمسی">
                        </div>
                        <div class="cust-field">
                            <label for="loan-inst-pay-dep">تاریخ واریز <span class="req">*</span></label>
                            <input id="loan-inst-pay-dep" type="text" autocomplete="off" placeholder="انتخاب از تقویم">
                        </div>
                        <div class="cust-field" style="grid-column:1/-1;">
                            <label for="loan-inst-pay-note">توضیحات</label>
                            <textarea id="loan-inst-pay-note" rows="2" placeholder="اختیاری"></textarea>
                        </div>
                    </div>
                    <div class="cust-actions" style="margin-top:0.75rem;">
                        <button type="button" class="cust-cancel" id="loan-inst-pay-form-cancel">انصراف از فرم</button>
                        <button type="button" class="cust-submit" id="loan-inst-pay-save">ثبت پرداخت</button>
                    </div>
                </div>
                <div class="loan-inst-pay-table-scroll">
                    <table class="loan-inst-pay-table">
                        <thead>
                            <tr>
                                <th>نحوه</th>
                                <th>مبلغ</th>
                                <th>سررسید (مرجع)</th>
                                <th>واریز</th>
                                <th>ثبت توسط</th>
                                <th>توضیح</th>
                                <th style="white-space:nowrap;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="loan-inst-pay-tbody">
                            <tr><td colspan="7" class="loan-inst-empty" style="padding:0.75rem;">در حال بارگذاری...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="cust-actions" style="margin-top:1rem;">
                    <button type="button" class="cust-cancel" id="loan-inst-pay-dismiss">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-instant-settlement-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(640px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-is-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-is-title">مبلغ تسویه آنی</h2>
                    <p id="loan-is-subtitle" class="loan-inst-modal-sub">در حال بارگذاری...</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-is-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body loan-is-modal-body">
                <div class="loan-is-hero" id="loan-is-hero">
                    <span class="loan-is-hero__label" id="loan-is-primary-label">—</span>
                    <div class="loan-is-hero__amount" id="loan-is-primary-amount">—</div>
                </div>
                <p class="loan-is-summary" id="loan-is-summary"></p>
                <div class="loan-is-rows" id="loan-is-rows"></div>
                <ul class="loan-is-notes" id="loan-is-notes" hidden></ul>
                <div class="loan-is-meta" id="loan-is-meta"></div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-discount-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(480px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-discount-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-discount-title">ثبت تخفیف</h2>
                    <p id="loan-discount-subtitle" class="loan-inst-modal-sub">در حال بارگذاری...</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-discount-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div class="loan-discount-late-box">
                    <span class="loan-discount-late-box__label">دیرکرد تا کنون (برآورد بر اساس ضریب دیرکرد نوع وام)</span>
                    <div class="loan-discount-late-box__value" id="loan-discount-late-amount">—</div>
                </div>
                <p class="loan-discount-hint" id="loan-discount-meta-hint"></p>
                <div class="cust-field" style="margin-top:0.75rem;">
                    <label for="loan-discount-amount-input">مبلغ کل تخفیف (تومان)</label>
                    <input id="loan-discount-amount-input" type="text" inputmode="numeric" autocomplete="off" placeholder="۰ = بدون تخفیف">
                </div>
                <div class="cust-actions" style="margin-top:1rem; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="cust-modal-close" id="loan-discount-cancel" style="width:auto; padding:0.45rem 0.85rem;">انصراف</button>
                    <button type="button" class="cust-submit" id="loan-discount-save">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-guarantee-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(920px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-guarantee-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-guarantee-title">تضامین</h2>
                    <p id="loan-guarantee-subtitle">مدیریت تضامین پرونده وام</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-guarantee-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <div id="loan-guarantee-list" class="loan-guarantee-list">
                    <div class="loan-guarantee-empty">در حال بارگذاری...</div>
                </div>
                <div class="cust-actions" style="justify-content:center; margin:0.35rem 0 0.75rem;">
                    <button type="button" class="cust-submit" id="loan-guarantee-open-form">
                        <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                        افزودن ضمانت برای این وام
                    </button>
                </div>

                <div id="loan-guarantee-form-wrap" class="loan-extra-box" hidden>
                    <form id="loan-guarantee-form" class="wallet-form-grid" novalidate>
                        <div class="cust-field">
                            <label>نوع ضمانت:</label>
                            <div class="loan-guarantee-type-tabs" id="loan-guarantee-type-tabs">
                                <button type="button" class="loan-guarantee-type-btn is-active" data-guarantee-type="org_self">سازمانی - خودم</button>
                                <button type="button" class="loan-guarantee-type-btn" data-guarantee-type="org_other">سازمانی - شخص دیگر</button>
                                <button type="button" class="loan-guarantee-type-btn" data-guarantee-type="cheque">چک</button>
                                <button type="button" class="loan-guarantee-type-btn" data-guarantee-type="gold">طلا</button>
                                <button type="button" class="loan-guarantee-type-btn" data-guarantee-type="other">سایر</button>
                            </div>
                        </div>
                        <input type="hidden" id="loan-guarantee-type" name="type" value="org_self">

                        <div id="loan-guarantee-org-shared" class="loan-guarantee-org-shared-block" hidden>
                            <div class="cust-form-grid">
                                <div class="cust-field loan-guarantee-org-toolbar" style="grid-column: 1 / -1;">
                                    <label for="loan-guarantee-organization-id">سازمان <span class="req">*</span></label>
                                    <div class="loan-guarantee-org-toolbar__row">
                                        <div class="loan-guarantee-org-select-wrap">
                                            <select id="loan-guarantee-organization-id" name="organization_id" class="loan-guarantee-org-select" data-placeholder="انتخاب سازمان...">
                                                <option value="">انتخاب کنید...</option>
                                            </select>
                                        </div>
                                        <button type="button" class="loan-guarantee-org-manage-btn" id="loan-guarantee-org-manage-open" title="مدیریت فهرست سازمان‌ها">مدیریت سازمان‌ها</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="loan-guarantee-section" data-guarantee-section="org_self">
                            <div class="cust-form-grid">
                                <div class="cust-field"><label for="loan-guarantee-self-employee-no">شماره پرسنلی</label><input name="employee_no" id="loan-guarantee-self-employee-no" maxlength="120" autocomplete="off"></div>
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label for="loan-guarantee-org-self-desc">در صورت لزوم توضیحات ضمانت را وارد کنید:</label>
                                    <textarea name="description" id="loan-guarantee-org-self-desc" maxlength="2000" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="loan-guarantee-section" data-guarantee-section="org_other" hidden>
                            <div class="cust-form-grid">
                                <div class="cust-field"><label>نام و نام خانوادگی ضامن <span class="req">*</span></label><input name="guarantor_name" id="loan-guarantee-guarantor-name" autocomplete="name"></div>
                                <div class="cust-field"><label>کد ملی ضامن</label><input name="guarantor_national_id" id="loan-guarantee-guarantor-national-id" inputmode="numeric" maxlength="24" autocomplete="off" placeholder="اختیاری — ۱۰ رقم"></div>
                                <div class="cust-field"><label>شماره پرسنلی</label><input name="guarantor_employee_no" id="loan-guarantee-guarantor-employee-no" maxlength="120"></div>
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label for="loan-guarantee-guarantor-phone">شماره موبایل ضامن</label>
                                    <div class="loan-guarantee-guarantor-mobile-row">
                                        <input name="guarantor_phone" id="loan-guarantee-guarantor-phone" inputmode="numeric" placeholder="مثلاً 09121234567" autocomplete="tel">
                                        <button type="button" class="loan-guarantee-guarantor-otp-btn" id="loan-guarantee-guarantor-send-otp">احراز هویت موبایل</button>
                                    </div>
                                    <div id="loan-guarantee-guarantor-otp-panel" class="cust-field" style="margin-top:0.5rem;padding:0;border:0;" hidden>
                                        <label for="loan-guarantee-guarantor-otp-code">کد پیامک‌شده</label>
                                        <div class="loan-guarantee-guarantor-otp-actions">
                                            <input id="loan-guarantee-guarantor-otp-code" type="text" inputmode="numeric" maxlength="8" placeholder="کد ۶ رقمی" style="max-width:11rem;">
                                            <button type="button" class="loan-file-btn" id="loan-guarantee-guarantor-verify-otp">تایید کد</button>
                                        </div>
                                        <div id="loan-guarantee-guarantor-verify-msg" class="loan-guarantee-guarantor-verify-msg" aria-live="polite"></div>
                                    </div>
                                    <input type="hidden" name="guarantor_verification_token" id="loan-guarantee-guarantor-verification-token" value="" autocomplete="off">
                                    <input type="hidden" id="loan-guarantee-guarantor-otp-session" value="" autocomplete="off">
                                </div>
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label for="loan-guarantee-org-other-desc">در صورت لزوم توضیحات ضمانت را وارد کنید:</label>
                                    <textarea name="description" id="loan-guarantee-org-other-desc" maxlength="2000" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="loan-guarantee-section" data-guarantee-section="cheque" hidden>
                            <div class="cust-form-grid">
                                <div class="cust-field"><label>نام و نام خانوادگی صاحب چک <span class="req">*</span></label><input name="cheque_owner_name"></div>
                                <div class="cust-field"><label>کد ملی صاحب چک <span class="req">*</span></label><input name="cheque_owner_national_id" inputmode="numeric"></div>
                                <div class="cust-field"><label>شماره موبایل صاحب چک</label><input name="cheque_owner_mobile" inputmode="numeric" placeholder="اختیاری — مثلاً 09123456789"></div>
                                <div class="cust-field"><label>تاریخ چک <span class="req">*</span></label><input type="text" name="cheque_due_jdate" id="loan-guarantee-cheque-due" autocomplete="off" placeholder="انتخاب از تقویم"></div>
                                <div class="cust-field"><label>شماره چک <span class="req">*</span></label><input name="cheque_serial"></div>
                                <div class="cust-field"><label>شماره صیادی <span class="req">*</span></label><input name="cheque_sayadi"></div>
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label class="loan-guarantee-check-row" for="loan-guarantee-cheque-collected">
                                        <input type="checkbox" name="cheque_collected" value="1" id="loan-guarantee-cheque-collected">
                                        وصول شده؟
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="loan-guarantee-section" data-guarantee-section="gold" hidden>
                            <div class="cust-form-grid">
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label>نوع طلا <span class="req">*</span></label>
                                    <div class="gold-item-options" id="gold-item-options">
                                        <label class="gold-item-option is-active" data-gold-option>
                                            <input type="radio" name="gold_item_code" value="broken_gold" checked>
                                            طلای شکن
                                        </label>
                                        <label class="gold-item-option" data-gold-option>
                                            <input type="radio" name="gold_item_code" value="quarter_coin">
                                            ربع سکه
                                        </label>
                                        <label class="gold-item-option" data-gold-option>
                                            <input type="radio" name="gold_item_code" value="half_coin">
                                            نیم سکه
                                        </label>
                                        <label class="gold-item-option" data-gold-option>
                                            <input type="radio" name="gold_item_code" value="full_coin">
                                            تمام بهار
                                        </label>
                                        <label class="gold-item-option" data-gold-option>
                                            <input type="radio" name="gold_item_code" value="parsian_gram">
                                            گرمی پارسیان
                                        </label>
                                    </div>
                                </div>
                                <div class="cust-field" id="gold-weight-wrap">
                                    <label>وزن طلا (گرم) <span class="req">*</span></label>
                                    <input type="number" name="gold_weight_gram" step="0.01" min="0">
                                </div>
                                <div class="cust-field" id="gold-quantity-wrap" hidden>
                                    <label>تعداد <span class="req">*</span></label>
                                    <input type="number" name="gold_quantity" step="1" min="1">
                                </div>
                                <div class="cust-field">
                                    <label>نرخ طلا (تومان) <span class="req">*</span></label>
                                    <input name="gold_rate_toman" inputmode="numeric" data-gold-rate>
                                </div>
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label>در صورت لزوم توضیحات ضمانت را وارد کنید:</label>
                                    <textarea name="description" id="loan-guarantee-gold-desc" maxlength="2000"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="loan-guarantee-section" data-guarantee-section="other" hidden>
                            <div class="cust-field">
                                <label>در صورت لزوم توضیحات را وارد کنید: <span class="req">*</span></label>
                                <textarea name="description" id="loan-guarantee-other-desc" maxlength="2000"></textarea>
                            </div>
                        </div>

                        <div class="cust-field">
                            <label>فایل پیوست</label>
                            <div class="loan-guarantee-attach">
                                <div class="loan-guarantee-attach-left">
                                    <div class="loan-guarantee-preview" id="loan-guarantee-preview">بدون فایل</div>
                                    <a href="#" id="loan-guarantee-preview-download-link" class="loan-guarantee-preview-download" hidden>
                                        <i class="fa-solid fa-download"></i>
                                        دانلود
                                    </a>
                                </div>
                                <div>
                                    <input type="file" id="loan-guarantee-attachment" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf" hidden>
                                    <div class="loan-guarantee-meta" id="loan-guarantee-file-name">فایلی انتخاب نشده است.</div>
                                    <div class="loan-guarantee-attach-actions">
                                        <button type="button" class="loan-file-btn loan-file-btn--mini loan-file-btn--danger" id="loan-guarantee-file-remove" title="حذف فایل"><i class="fa-regular fa-trash-can"></i></button>
                                        <button type="button" class="loan-file-btn loan-file-btn--mini" id="loan-guarantee-file-download" title="دانلود فایل" disabled><i class="fa-solid fa-download"></i></button>
                                        <button type="button" class="loan-file-btn loan-file-btn--mini" id="loan-guarantee-file-upload" title="آپلود فایل"><i class="fa-solid fa-upload"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cust-field cust-field--full loan-extra-box loan-creation-otp-section" id="loan-guarantee-return-section" hidden>
                            <label class="loan-guarantee-check-row" for="loan-guarantee-mark-returned">
                                <input type="checkbox" value="1" id="loan-guarantee-mark-returned">
                                عودت شده؟
                            </label>
                            <p class="loan-interest-note" style="margin-top:.25rem">برای ثبت عودت چک یا اوراق ضمانتی، در صورت تمایل می‌توانید مستند تحویل به مشتری را بارگذاری کنید.</p>
                            <div id="loan-guarantee-return-details" hidden>
                                <div class="loan-interest-note" style="margin-top:.35rem">موبایل مشتری: <strong id="loan-guarantee-return-mobile-view">—</strong></div>
                                <div id="loan-guarantee-return-otp-wrap" style="margin-top:.45rem" hidden>
                                    <button type="button" class="loan-creation-otp-btn" id="loan-guarantee-return-send-otp">ارسال کد تایید عودت</button>
                                    <div id="loan-guarantee-return-otp-panel" class="cust-field" style="margin-top:0.5rem;padding:0;border:0;" hidden>
                                        <label for="loan-guarantee-return-otp-code">کد پیامک‌شده</label>
                                        <div class="loan-guarantee-guarantor-otp-actions">
                                            <input id="loan-guarantee-return-otp-code" type="text" inputmode="numeric" maxlength="8" placeholder="کد ۶ رقمی" style="max-width:11rem;">
                                            <button type="button" class="loan-file-btn" id="loan-guarantee-return-verify-otp">تایید کد</button>
                                        </div>
                                    </div>
                                    <div id="loan-guarantee-return-verified" class="loan-creation-otp-verified" hidden>احراز مشتری برای عودت انجام شد.</div>
                                    <input type="hidden" id="loan-guarantee-return-verification-token" value="" autocomplete="off">
                                    <input type="hidden" id="loan-guarantee-return-otp-session" value="" autocomplete="off">
                                </div>
                                <div class="cust-field" style="margin-top:.55rem;padding:0;border:0;">
                                    <label for="loan-guarantee-return-document">مستند عودت</label>
                                    <input type="file" id="loan-guarantee-return-document" name="return_document" accept=".png,.jpg,.jpeg,.webp,.pdf">
                                    <div id="loan-guarantee-return-document-existing" class="loan-interest-note" style="margin-top:.35rem" hidden></div>
                                </div>
                            </div>
                        </div>

                        <div class="cust-actions" style="margin-top:0.3rem;">
                            <button type="button" class="cust-cancel" id="loan-guarantee-cancel">انصراف</button>
                            <button type="submit" class="cust-submit" id="loan-guarantee-submit">ذخیره ضمانت</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="cust-overlay" id="loan-org-manage-overlay" hidden aria-hidden="true">
        <div class="cust-modal" style="width:min(560px,100%)" role="dialog" aria-modal="true" aria-labelledby="loan-org-manage-title">
            <div class="cust-modal-head">
                <div>
                    <h2 id="loan-org-manage-title">مدیریت سازمان‌ها</h2>
                    <p style="margin:0.25rem 0 0;font-size:0.75rem;color:var(--muted);">افزودن، ویرایش یا حذف نام سازمان‌ها</p>
                </div>
                <button type="button" class="cust-modal-close" id="loan-org-manage-close" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <button type="button" class="cust-submit" id="loan-org-add-toggle" style="width:100%; justify-content:center;">
                    <i class="fa-solid fa-circle-plus" aria-hidden="true"></i> افزودن سازمان
                </button>
                <div id="loan-org-inline-form" class="loan-org-inline-form" hidden>
                    <input type="hidden" id="loan-org-edit-id" value="">
                    <div class="cust-field">
                        <label for="loan-org-name-input">نام سازمان</label>
                        <input id="loan-org-name-input" type="text" maxlength="255" placeholder="نام سازمان">
                    </div>
                    <div class="cust-actions" style="margin-top:0.45rem;">
                        <button type="button" class="cust-cancel" id="loan-org-inline-cancel">انصراف</button>
                        <button type="button" class="cust-submit" id="loan-org-inline-save">ذخیره</button>
                    </div>
                </div>
                <div id="loan-org-manage-list" class="loan-org-manage-list"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
    <script>
        (function () {
            function toEnglishDigits(s) {
                var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                var en = ['0','1','2','3','4','5','6','7','8','9'];
                var out = s;
                for (var i = 0; i < 10; i++) out = out.split(fa[i]).join(en[i]);
                return out;
            }

            function usernameFromMobile(m) {
                var d = toEnglishDigits(String(m || '')).replace(/\D/g, '');
                if (d.length === 10 && d.charAt(0) === '9') d = '0' + d;
                return d;
            }

            var custFormMode = 'create';
            var custListBaseUrl = @json(rtrim(route('admin.customers.index'), '/'));
            var customersImportExcelUrl = @json(route('admin.customers.import-excel'));
            var custStoreUrl = @json(route('admin.customers.store'));
            var appDisplayName = @json($appDisplayName ?? config('app.name'));

            function custEditDataUrl(id) {
                return custListBaseUrl + '/' + id + '/edit-data';
            }

            function custLoanBoardSummaryUrl(id) {
                return custListBaseUrl + '/' + encodeURIComponent(String(id || '')) + '/loan-board-summary';
            }

            function custUpdateUrl(id) {
                return custListBaseUrl + '/' + id;
            }

            function walletShowUrl(id) {
                return custListBaseUrl + '/' + id + '/wallet';
            }

            function walletLockUrl(id) {
                return custListBaseUrl + '/' + id + '/wallet/lock';
            }

            function walletAdjustUrl(id) {
                return custListBaseUrl + '/' + id + '/wallet/adjust';
            }

            function walletTransactionsUrl(id) {
                return custListBaseUrl + '/' + id + '/wallet/transactions';
            }

            function walletTransactionsExportUrl(id) {
                return custListBaseUrl + '/' + id + '/wallet/transactions/export-excel';
            }

            function quickSmsUrl(id) {
                return custListBaseUrl + '/' + id + '/quick-sms';
            }

            function customerSmsModalPreviewUrl(customerId, smsType, installmentId) {
                var u = custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/sms-modal-preview?sms_type=' + encodeURIComponent(String(smsType || ''));
                if (installmentId != null && String(installmentId).trim() !== '') {
                    u += '&installment_id=' + encodeURIComponent(String(installmentId));
                }
                return u;
            }

            function customerLoanStoreUrl(id) {
                return custListBaseUrl + '/' + id + '/loan-files';
            }
            function customerLoanCreationOtpSendUrl(id) {
                return custListBaseUrl + '/' + encodeURIComponent(String(id || '')) + '/loan-creation-otp/send';
            }
            function customerLoanCreationOtpVerifyUrl(id) {
                return custListBaseUrl + '/' + encodeURIComponent(String(id || '')) + '/loan-creation-otp/verify';
            }
            function customerLoanUpdateUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId;
            }
            function customerLoanDeleteUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId;
            }
            function customerLoanRevokeContractUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/revoke-contract';
            }
            function customerLoanSendSmsUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/send-sms';
            }
            function customerLoanGuaranteesUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees';
            }
            function customerLoanInstallmentsUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/installments';
            }
            function customerLoanBookletPrintUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/installment-booklet';
            }
            function customerLoanInstallmentUpdateUrl(customerId, loanFileId, installmentId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/installments/' + encodeURIComponent(String(installmentId || ''));
            }
            function customerLoanInstallmentPaymentsUrl(customerId, loanFileId, installmentId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/installments/' + encodeURIComponent(String(installmentId || '')) + '/payments';
            }
            function customerLoanInstallmentPaymentItemUrl(customerId, loanFileId, installmentId, paymentId) {
                return customerLoanInstallmentPaymentsUrl(customerId, loanFileId, installmentId) + '/' + encodeURIComponent(String(paymentId || ''));
            }
            function customerLoanInstantSettlementUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/instant-settlement-preview';
            }
            function customerLoanDiscountPreviewUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/discount-preview';
            }
            function customerLoanDiscountStoreUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/discount';
            }
            function customerLoanGuaranteeDeleteUrl(customerId, loanFileId, guaranteeId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees/' + guaranteeId;
            }
            function customerLoanGuaranteeUpdateUrl(customerId, loanFileId, guaranteeId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees/' + guaranteeId;
            }
            function customerGuaranteeReturnOtpUrl(customerId, loanFileId, action) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/loan-files/' + encodeURIComponent(String(loanFileId || '')) + '/guarantee-return-otp/' + encodeURIComponent(String(action || ''));
            }
            function customerGuaranteesReportUrl(customerId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/guarantees-report';
            }
            function customerSmsLogsUrl(customerId, dateYmd) {
                var u = custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/sms-logs';
                if (dateYmd) {
                    u += '?date=' + encodeURIComponent(String(dateYmd));
                }
                return u;
            }
            function customerSmsLogsExportUrl(customerId, dateYmd) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/sms-logs/export-excel?date=' + encodeURIComponent(String(dateYmd || ''));
            }
            function customerGuaranteesReportExportUrl(customerId) {
                return custListBaseUrl + '/' + encodeURIComponent(String(customerId || '')) + '/guarantees-report/export-excel';
            }

            var organizationsListUrl = @json(route('admin.organizations.index'));
            var guarantorOtpSendUrl = @json(route('admin.guarantor-otp.send'));
            var guarantorOtpVerifyUrl = @json(route('admin.guarantor-otp.verify'));
            var loanCreationOtpEnabled = @json($loanCreationOtpEnabled ?? false);
            var loanInstallmentRounding = @json($loanInstallmentRounding ?? ['step_toman' => 10000, 'remainder_target' => 'last', 'remainder_target_label' => 'قسط آخر']);
            var loanCreationOtpVerified = false;
            var guaranteeReturnOtpEnabled = @json($guaranteeReturnOtpEnabled ?? false);
            var guaranteeReturnOtpVerified = false;
            var guaranteeReturnHasExistingDocument = false;
            var adminOrgBaseUrl = @json(url('/admin/organizations'));

            function organizationRestUrl(id) {
                return adminOrgBaseUrl.replace(/\/?$/, '') + '/' + encodeURIComponent(String(id || ''));
            }

            function destroyLoanOrgSelect2() {
                if (!window.jQuery) return;
                var $s = window.jQuery('#loan-guarantee-organization-id');
                if ($s.length && $s.data && $s.data('select2')) {
                    try {
                        $s.select2('destroy');
                    } catch (e2) { /* noop */ }
                }
            }

            function populateOrgSelectOptions(rows, selectedId) {
                var sel = document.getElementById('loan-guarantee-organization-id');
                if (!sel) return;
                var want = selectedId != null && selectedId !== '' ? String(selectedId) : '';
                sel.innerHTML = '<option value="">انتخاب کنید...</option>';
                (rows || []).forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = String(o.id);
                    opt.textContent = o.name;
                    sel.appendChild(opt);
                });
                if (want) sel.value = want;
            }

            function loadOrganizationsIntoSelect(selectedId, done) {
                fetch(organizationsListUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var rows = Array.isArray(data.organizations) ? data.organizations : [];
                    populateOrgSelectOptions(rows, selectedId);
                    if (typeof done === 'function') done();
                }).catch(function () {
                    populateOrgSelectOptions([], selectedId);
                    if (typeof done === 'function') done();
                });
            }

            function initLoanOrgSelect2() {
                if (!window.jQuery || !window.jQuery.fn.select2) return;
                var $s = window.jQuery('#loan-guarantee-organization-id');
                if (!$s.length) return;
                destroyLoanOrgSelect2();
                $s.select2({
                    dir: 'rtl',
                    width: '100%',
                    placeholder: 'انتخاب سازمان...',
                    language: {
                        noResults: function () { return 'موردی یافت نشد'; },
                        searching: function () { return 'در حال جستجو...'; }
                    }
                });
            }

            function resetGuarantorOtpUi() {
                var tokenEl = document.getElementById('loan-guarantee-guarantor-verification-token');
                var sessEl = document.getElementById('loan-guarantee-guarantor-otp-session');
                var codeEl = document.getElementById('loan-guarantee-guarantor-otp-code');
                var panel = document.getElementById('loan-guarantee-guarantor-otp-panel');
                var msg = document.getElementById('loan-guarantee-guarantor-verify-msg');
                if (tokenEl) tokenEl.value = '';
                if (sessEl) sessEl.value = '';
                if (codeEl) codeEl.value = '';
                if (panel) panel.hidden = true;
                if (msg) {
                    msg.textContent = '';
                    msg.className = 'loan-guarantee-guarantor-verify-msg';
                }
            }

            function resetLoanCreationOtpUi() {
                loanCreationOtpVerified = false;
                var tokenEl = document.getElementById('loan-creation-verification-token');
                var sessEl = document.getElementById('loan-creation-otp-session');
                var codeEl = document.getElementById('loan-creation-otp-code');
                var panel = document.getElementById('loan-creation-otp-panel');
                var verifiedEl = document.getElementById('loan-creation-otp-verified');
                var sendBtn = document.getElementById('loan-creation-otp-send');
                if (tokenEl) tokenEl.value = '';
                if (sessEl) sessEl.value = '';
                if (codeEl) codeEl.value = '';
                if (panel) panel.hidden = true;
                if (verifiedEl) verifiedEl.hidden = true;
                if (sendBtn) sendBtn.disabled = false;
            }

            function syncLoanCreationOtpSectionVisibility() {
                var section = document.getElementById('loan-creation-otp-section');
                var mobileView = document.getElementById('loan-creation-otp-mobile-view');
                if (!section) return;
                var show = !!loanCreationOtpEnabled && loanFormMode === 'create';
                section.hidden = !show;
                if (mobileView) mobileView.textContent = loanManageCurrentCustomerMobile || '—';
            }

            function guaranteeTypesSupportingReturn(type) {
                return type === 'cheque' || type === 'gold' || type === 'other';
            }

            function resetGuaranteeReturnUi() {
                guaranteeReturnOtpVerified = false;
                guaranteeReturnHasExistingDocument = false;
                var markReturned = document.getElementById('loan-guarantee-mark-returned');
                var details = document.getElementById('loan-guarantee-return-details');
                var tokenEl = document.getElementById('loan-guarantee-return-verification-token');
                var sessEl = document.getElementById('loan-guarantee-return-otp-session');
                var codeEl = document.getElementById('loan-guarantee-return-otp-code');
                var panel = document.getElementById('loan-guarantee-return-otp-panel');
                var verifiedEl = document.getElementById('loan-guarantee-return-verified');
                var docInput = document.getElementById('loan-guarantee-return-document');
                var docExisting = document.getElementById('loan-guarantee-return-document-existing');
                var sendBtn = document.getElementById('loan-guarantee-return-send-otp');
                if (markReturned) markReturned.checked = false;
                if (details) details.hidden = true;
                if (tokenEl) tokenEl.value = '';
                if (sessEl) sessEl.value = '';
                if (codeEl) codeEl.value = '';
                if (panel) panel.hidden = true;
                if (verifiedEl) verifiedEl.hidden = true;
                if (docInput) docInput.value = '';
                if (docExisting) {
                    docExisting.hidden = true;
                    docExisting.textContent = '';
                }
                if (sendBtn) sendBtn.disabled = false;
            }

            function syncGuaranteeReturnDetailsVisibility() {
                var markReturned = document.getElementById('loan-guarantee-mark-returned');
                var details = document.getElementById('loan-guarantee-return-details');
                var otpWrap = document.getElementById('loan-guarantee-return-otp-wrap');
                var mobileView = document.getElementById('loan-guarantee-return-mobile-view');
                if (!details || !markReturned) return;
                details.hidden = !markReturned.checked;
                if (mobileView) mobileView.textContent = loanManageCurrentCustomerMobile || '—';
                if (otpWrap) otpWrap.hidden = !guaranteeReturnOtpEnabled;
            }

            function syncGuaranteeReturnSectionVisibility(type) {
                var section = document.getElementById('loan-guarantee-return-section');
                if (!section) return;
                var show = guaranteeTypesSupportingReturn(String(type || ''));
                section.hidden = !show;
                if (!show) {
                    resetGuaranteeReturnUi();
                    return;
                }
                syncGuaranteeReturnDetailsVisibility();
            }

            function guaranteeTypeLabelFa(type) {
                var map = {
                    cheque: 'چک',
                    gold: 'طلا',
                    other: 'سایر'
                };
                return map[String(type || '')] || 'ضمانت';
            }

            function populateGuaranteeReturnFromData(guaranteeData) {
                resetGuaranteeReturnUi();
                if (!guaranteeData) return;
                var gtype = String(guaranteeData.type || '');
                if (!guaranteeTypesSupportingReturn(gtype)) return;
                var meta = guaranteeData.meta && typeof guaranteeData.meta === 'object' ? guaranteeData.meta : {};
                var isReturned = gtype === 'cheque' ? !!meta.cheque_returned : !!meta.returned;
                var markReturned = document.getElementById('loan-guarantee-mark-returned');
                if (markReturned) markReturned.checked = isReturned;
                if (isReturned) {
                    guaranteeReturnOtpVerified = !guaranteeReturnOtpEnabled;
                    var verifiedEl = document.getElementById('loan-guarantee-return-verified');
                    if (verifiedEl && guaranteeReturnOtpEnabled) verifiedEl.hidden = false;
                }
                var docExisting = document.getElementById('loan-guarantee-return-document-existing');
                var downloadUrl = String(guaranteeData.return_document_download_url || '');
                if (docExisting && downloadUrl) {
                    guaranteeReturnHasExistingDocument = true;
                    docExisting.hidden = false;
                    docExisting.innerHTML = 'مستند عودت ثبت‌شده: <a href="' + escapeHtmlAttr(downloadUrl) + '" target="_blank" rel="noopener">' + escapeHtmlText(guaranteeData.return_document_name || 'دانلود') + '</a>'
                        + (guaranteeData.returned_at ? (' | تاریخ: ' + escapeHtmlText(guaranteeData.returned_at)) : '');
                }
                syncGuaranteeReturnDetailsVisibility();
            }

            function normalizeGuarantorMobileValue(raw) {
                var mobile = toEnglishDigits(String(raw || '')).replace(/\D/g, '');
                if (mobile.length === 10 && mobile.charAt(0) === '9') mobile = '0' + mobile;
                return mobile;
            }

            function updateGuarantorOtpButtonChrome() {
                var btn = document.getElementById('loan-guarantee-guarantor-send-otp');
                if (!btn) return;
                if (loanGuaranteeGuarantorOtpLocked) {
                    btn.textContent = 'احراز شده';
                    btn.disabled = true;
                } else {
                    btn.textContent = 'احراز هویت موبایل';
                    btn.disabled = false;
                }
                if (loanGuaranteeGuarantorOtpLocked) {
                    var panel = document.getElementById('loan-guarantee-guarantor-otp-panel');
                    if (panel) panel.hidden = true;
                }
            }

            function syncGuarantorOtpLockFromFormMeta() {
                var active = loanGuaranteeTypeInput ? String(loanGuaranteeTypeInput.value || '') : '';
                if (active !== 'org_other') {
                    return;
                }
                var gLoaded = loanGuaranteeLoadedMeta && typeof loanGuaranteeLoadedMeta === 'object' ? loanGuaranteeLoadedMeta : null;
                var phoneEl = document.getElementById('loan-guarantee-guarantor-phone');
                if (!gLoaded || !phoneEl) {
                    loanGuaranteeGuarantorOtpLocked = false;
                    loanGuaranteeGuarantorOtpPhoneSnapshot = '';
                    updateGuarantorOtpButtonChrome();
                    return;
                }
                var metaSnap = normalizeGuarantorMobileValue(String(gLoaded.guarantor_phone || ''));
                var currentSnap = normalizeGuarantorMobileValue(String(phoneEl.value || ''));
                var baseVerified = !!gLoaded.guarantor_mobile_verified && /^09\d{9}$/.test(metaSnap);
                loanGuaranteeGuarantorOtpLocked = baseVerified && currentSnap === metaSnap;
                loanGuaranteeGuarantorOtpPhoneSnapshot = loanGuaranteeGuarantorOtpLocked ? currentSnap : '';
                updateGuarantorOtpButtonChrome();
            }

            var overlay = document.getElementById('cust-modal-overlay');
            var openBtn = document.getElementById('cust-open-modal');
            var closeBtn = document.getElementById('cust-close-modal');
            var cancelBtn = document.getElementById('cust-cancel-modal');
            var mobile = document.getElementById('cust-mobile');
            var userPrev = document.getElementById('cust-username-preview');
            var form = document.getElementById('cust-store-form');
            var sendHidden = document.getElementById('cust-send-hidden');
            var sendChk = document.getElementById('cust-send-chk');
            var bankContainer = document.getElementById('cust-bank-rows');
            var refContainer = document.getElementById('cust-referrer-rows');
            var bankBtn = document.getElementById('cust-add-bank');
            var refBtn = document.getElementById('cust-add-referrer');
            var bankIndex = {{ max(count(old('accounts', [])), 0) }};
            var refIndex = {{ max(count(old('referrers', [])), 0) }};
            var skipPrompt = false;
            var pwdInput = document.getElementById('cust-password');
            var pwdReq = document.getElementById('cust-password-req');
            var pwdHint = document.getElementById('cust-password-hint');
            var custFatherInput = document.getElementById('cust-father');
            var custNationalInput = document.getElementById('cust-national');
            var custCityInput = document.getElementById('cust-city');
            var custAddressInput = document.getElementById('cust-address');
            var custPostalInput = document.getElementById('cust-postal');
            var custFatherReq = document.getElementById('cust-father-req');
            var custNationalReq = document.getElementById('cust-national-req');
            var custCityReq = document.getElementById('cust-city-req');
            var custAddressReq = document.getElementById('cust-address-req');
            var custPostalReq = document.getElementById('cust-postal-req');
            var modalTitle = document.getElementById('cust-modal-title');
            var modalDesc = document.getElementById('cust-modal-desc');
            var custImportOverlay = document.getElementById('cust-import-overlay');
            var custImportOpenBtn = document.getElementById('cust-import-open-btn');
            var custImportCloseBtn = document.getElementById('cust-import-close-btn');
            var custImportDismissBtn = document.getElementById('cust-import-dismiss-btn');
            var custImportForm = document.getElementById('cust-import-form');
            var custImportFileInput = document.getElementById('cust-import-file-input');
            var custImportSubmitBtn = document.getElementById('cust-import-submit-btn');
            var custImportSubmitting = false;

            function openCustImportModal() {
                if (!custImportOverlay) return;
                custImportOverlay.hidden = false;
                custImportOverlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('app-settings-open');
            }
            function closeCustImportModal() {
                if (!custImportOverlay) return;
                custImportOverlay.hidden = true;
                custImportOverlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('app-settings-open');
                if (custImportForm) custImportForm.reset();
                custImportSubmitting = false;
                if (custImportSubmitBtn) {
                    custImportSubmitBtn.disabled = false;
                    custImportSubmitBtn.textContent = 'بارگذاری و ثبت مشتریان';
                }
            }

            var walletModalOverlay = document.getElementById('wallet-modal-overlay');
            var walletCloseModal = document.getElementById('wallet-close-modal');
            var walletBalanceView = document.getElementById('wallet-balance-view');
            var walletSubtitle = document.getElementById('wallet-modal-subtitle');
            var walletLockPill = document.getElementById('wallet-lock-pill');
            var walletLockToggleBtn = document.getElementById('wallet-lock-toggle-btn');
            var walletOpenAdjustBtn = document.getElementById('wallet-open-adjust-btn');
            var walletOpenTransactionsBtn = document.getElementById('wallet-open-transactions-btn');
            var walletExportExcelBtn = document.getElementById('wallet-export-excel-btn');
            var walletAdjustOverlay = document.getElementById('wallet-adjust-overlay');
            var walletAdjustClose = document.getElementById('wallet-adjust-close');
            var walletAdjustCancel = document.getElementById('wallet-adjust-cancel');
            var walletAdjustForm = document.getElementById('wallet-adjust-form');
            var walletAmountInput = document.getElementById('wallet-amount');
            var walletAdjustBalance = document.getElementById('wallet-adjust-balance');
            var walletTransOverlay = document.getElementById('wallet-trans-overlay');
            var walletTransClose = document.getElementById('wallet-trans-close');
            var walletTransTbody = document.getElementById('wallet-trans-tbody');
            var quickSmsOverlay = document.getElementById('quick-sms-overlay');
            var quickSmsClose = document.getElementById('quick-sms-close');
            var quickSmsCancel = document.getElementById('quick-sms-cancel');
            var quickSmsForm = document.getElementById('quick-sms-form');
            var quickSmsTitle = document.getElementById('quick-sms-title');
            var quickSmsSubtitle = document.getElementById('quick-sms-subtitle');
            var quickSmsTemplate = document.getElementById('quick-sms-template');
            var quickSmsText = document.getElementById('quick-sms-text');
            var loanManageOverlay = document.getElementById('loan-manage-overlay');
            var loanManageLrqIframe = document.getElementById('loan-manage-lrq-iframe');
            var loanManageLrqLoading = document.getElementById('loan-manage-lrq-loading');
            var loanManageLrqEmbedTmpl = @json($loanManageLrqEmbedUrlTemplate ?? '');

            function setLoanManageLrqLoading(show) {
                if (!loanManageLrqLoading) return;
                loanManageLrqLoading.hidden = !show;
                loanManageLrqLoading.setAttribute('aria-hidden', show ? 'false' : 'true');
                loanManageLrqLoading.setAttribute('aria-busy', show ? 'true' : 'false');
            }
            var loanManageCtxIframe = document.getElementById('loan-manage-ctx-iframe');
            var loanManageCtxLoading = document.getElementById('loan-manage-ctx-loading');
            var loanManageCtxEmbedTmpl = @json($loanManageCtxEmbedUrlTemplate ?? '');
            var loanManageTicketsIframe = document.getElementById('loan-manage-tickets-iframe');
            var loanManageTicketsLoading = document.getElementById('loan-manage-tickets-loading');
            var loanManageTicketsEmbedTmpl = @json($loanManageTicketsEmbedUrlTemplate ?? '');

            function setLoanManageTicketsLoading(show) {
                if (!loanManageTicketsLoading) return;
                loanManageTicketsLoading.hidden = !show;
                loanManageTicketsLoading.setAttribute('aria-hidden', show ? 'false' : 'true');
                loanManageTicketsLoading.setAttribute('aria-busy', show ? 'true' : 'false');
            }

            function setLoanManageCtxLoading(show) {
                if (!loanManageCtxLoading) return;
                loanManageCtxLoading.hidden = !show;
                loanManageCtxLoading.setAttribute('aria-hidden', show ? 'false' : 'true');
                loanManageCtxLoading.setAttribute('aria-busy', show ? 'true' : 'false');
            }
            var loanManageClose = document.getElementById('loan-manage-close');
            var loanFilesSummary = document.getElementById('loan-files-summary');
            var loanFilesList = document.getElementById('loan-files-list');
            var loanFilesFilterBar = document.getElementById('loan-files-filter-bar');
            var loanFilesHideSettledCheckbox = document.getElementById('loan-files-hide-settled');
            var loanFilesFilterHint = document.getElementById('loan-files-filter-hint');
            var loanOpenCreateModalBtn = document.getElementById('loan-open-create-modal');
            var loanManageOpenEditBtn = document.getElementById('loan-manage-open-edit');
            var loanManageOpenWalletBtn = document.getElementById('loan-manage-open-wallet');
            var loanTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-loan-tab]'));
            var loanTabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-loan-panel]'));
            var loanManageCustomerNameView = document.getElementById('loan-manage-customer-name');
            var loanManageCustomerMobileView = document.getElementById('loan-manage-customer-mobile');
            var loanManageCreditStatusView = document.getElementById('loan-manage-credit-status');
            var loanManageWalletBalanceView = document.getElementById('loan-manage-wallet-balance');
            var loanGuaranteesReportBody = document.getElementById('loan-guarantees-report-body');
            var loanGuaranteesReportCache = [];
            var loanGuaranteesSummaryCache = null;
            var loanGuaranteesSearchInput = document.getElementById('loan-guarantees-search');
            var loanGuaranteesSummaryEl = document.getElementById('loan-guarantees-summary');
            var loanSmsLogsBody = document.getElementById('loan-sms-logs-body');
            var loanSmsDayInput = document.getElementById('loan-sms-day-input');
            var loanSmsDayPrev = document.getElementById('loan-sms-day-prev');
            var loanSmsDayNext = document.getElementById('loan-sms-day-next');
            var loanSmsMobileMissingNote = document.getElementById('loan-sms-mobile-missing');
            var loanSmsDefaultDate = @json(now()->format('Y-m-d'));
            var loanSmsSelectedDate = loanSmsDefaultDate;
            var adminTodayJdate = @json(\Hekmatinasser\Jalali\Jalali::now()->format('Y/m/d'));
            var walletCurrentCustomerId = null;
            var walletCurrentCustomerName = '';
            var walletCurrentCustomerMobile = '';
            var quickSmsCurrentCustomerId = null;
            var quickSmsCurrentType = '';
            var quickSmsCurrentCustomerName = '';
            var quickSmsCurrentInstallmentId = null;
            var quickSmsCurrentLoanFileId = null;
            var quickSmsTemplatesData = @json($quickSmsTemplates->values());
            var walletSmsTemplates = [];
            var walletAdjustSubmitting = false;
            var walletLockSubmitting = false;
            var customerFormSubmitting = false;
            var quickSmsSubmitting = false;
            var loanManageCurrentCustomerId = null;
            var loanManageCurrentCustomerName = '';
            var loanManageCurrentCustomerMobile = '';
            var loanManageHideSettledFiles = true;
            var loanManageMap = @json($loanManageMap);
            var loanTypesData = @json($loanTypes->values());
            var loanCreateOverlay = document.getElementById('loan-create-overlay');
            var loanCreateClose = document.getElementById('loan-create-close');
            var loanCreateCancel = document.getElementById('loan-create-cancel');
            var loanCreateSubtitle = document.getElementById('loan-create-subtitle');
            var loanCreateForm = document.getElementById('loan-create-form');
            var loanCustomerNameInput = document.getElementById('loan-customer-name');
            var loanTypeIdSelect = document.getElementById('loan-type-id');
            var loanAmountInput = document.getElementById('loan-amount');
            var loanInstallmentsCountInput = document.getElementById('loan-installments-count');
            var loanInstallmentAmountInput = document.getElementById('loan-installment-amount');
            var loanInstallmentIntervalCountInput = document.getElementById('loan-installment-interval-count');
            var loanInstallmentIntervalUnitSelect = document.getElementById('loan-installment-interval-unit');
            var loanDownPaymentInput = document.getElementById('loan-down-payment');
            var loanIsSettledCheckbox = document.getElementById('loan-is-settled');
            var loanSettledSection = document.getElementById('loan-settled-section');
            var loanSettledWrap = document.getElementById('loan-settled-wrap');
            var loanHasCustomInterestCheckbox = document.getElementById('loan-has-custom-interest');
            var loanCustomInterestWrap = document.getElementById('loan-custom-interest-wrap');
            var loanCurrentInterestRate = document.getElementById('loan-current-interest-rate');
            var loanCustomInterestRateInput = document.getElementById('loan-custom-interest-rate');
            var loanTotalCheck = document.getElementById('loan-total-check');
            var loanStartJdateInput = document.getElementById('loan-start-jdate');
            var loanDisbursementDueJdateInput = document.getElementById('loan-disbursement-due-jdate');
            var loanSettledJdateInput = document.getElementById('loan-settled-jdate');
            var loanCreateSubmitting = false;
            var loanInstallmentAutoDirty = false;
            var loanDownPaymentAutoDirty = false;
            var loanFormMode = 'create';
            var loanEditingFileId = null;
            var loanSmsOverlay = document.getElementById('loan-sms-overlay');
            var loanSmsClose = document.getElementById('loan-sms-close');
            var loanSmsCancel = document.getElementById('loan-sms-cancel');
            var loanSmsSubtitle = document.getElementById('loan-sms-subtitle');
            var loanSmsForm = document.getElementById('loan-sms-form');
            var loanSmsTemplate = document.getElementById('loan-sms-template');
            var loanSmsText = document.getElementById('loan-sms-text');
            var loanSmsCurrentLoanId = null;
            var loanSmsCurrentLoanData = null;
            var loanSmsSubmitting = false;
            var loanGuaranteeOverlay = document.getElementById('loan-guarantee-overlay');
            var loanInstallmentsOverlay = document.getElementById('loan-installments-overlay');
            var loanInstantSettlementOverlay = document.getElementById('loan-instant-settlement-overlay');
            var loanDiscountOverlay = document.getElementById('loan-discount-overlay');
            var loanDiscountClose = document.getElementById('loan-discount-close');
            var loanDiscountCancel = document.getElementById('loan-discount-cancel');
            var loanDiscountSave = document.getElementById('loan-discount-save');
            var loanDiscountSubtitle = document.getElementById('loan-discount-subtitle');
            var loanDiscountLateAmount = document.getElementById('loan-discount-late-amount');
            var loanDiscountMetaHint = document.getElementById('loan-discount-meta-hint');
            var loanDiscountAmountInput = document.getElementById('loan-discount-amount-input');
            var loanDiscountCurrentLoanId = null;
            var loanDiscountPreviewData = null;
            var loanDiscountSaving = false;
            var loanIsClose = document.getElementById('loan-is-close');
            var loanIsTitle = document.getElementById('loan-is-title');
            var loanIsSubtitle = document.getElementById('loan-is-subtitle');
            var loanIsPrimaryLabel = document.getElementById('loan-is-primary-label');
            var loanIsPrimaryAmount = document.getElementById('loan-is-primary-amount');
            var loanIsSummary = document.getElementById('loan-is-summary');
            var loanIsRows = document.getElementById('loan-is-rows');
            var loanIsNotes = document.getElementById('loan-is-notes');
            var loanIsMeta = document.getElementById('loan-is-meta');
            var loanInstClose = document.getElementById('loan-inst-close');
            var loanInstTbody = document.getElementById('loan-inst-tbody');
            var loanInstSubtitle = document.getElementById('loan-inst-subtitle');
            var loanInstSummary = document.getElementById('loan-inst-summary');
            var loanInstSumType = document.getElementById('loan-inst-sum-type');
            var loanInstSumAmount = document.getElementById('loan-inst-sum-amount');
            var loanInstSumStart = document.getElementById('loan-inst-sum-start');
            var loanInstSumInstallment = document.getElementById('loan-inst-sum-installment');
            var loanInstSumPaidCount = document.getElementById('loan-inst-sum-paid-count');
            var loanInstSumRemainCount = document.getElementById('loan-inst-sum-remain-count');
            var loanInstSumRemainAmount = document.getElementById('loan-inst-sum-remain-amount');
            var loanInstSumPaidAmount = document.getElementById('loan-inst-sum-paid-amount');
            var loanInstSumLate = document.getElementById('loan-inst-sum-late');
            var loanInstSumEarly = document.getElementById('loan-inst-sum-early');
            var loanInstallmentEditOverlay = document.getElementById('loan-installment-edit-overlay');
            var loanInstEditClose = document.getElementById('loan-inst-edit-close');
            var loanInstEditCancel = document.getElementById('loan-inst-edit-cancel');
            var loanInstEditSave = document.getElementById('loan-inst-edit-save');
            var loanInstEditTitle = document.getElementById('loan-inst-edit-title');
            var loanInstEditSubtitle = document.getElementById('loan-inst-edit-subtitle');
            var loanInstEditColCustomer = document.getElementById('loan-inst-edit-col-customer');
            var loanInstEditColSchedule = document.getElementById('loan-inst-edit-col-schedule');
            var loanInstEditColRemaining = document.getElementById('loan-inst-edit-col-remaining');
            var loanInstEditAmountInput = document.getElementById('loan-inst-edit-amount');
            var loanInstEditDueInput = document.getElementById('loan-inst-edit-due');
            var loanInstEditInstallmentIdInput = document.getElementById('loan-inst-edit-installment-id');
            var loanInstCachedPayload = null;
            var loanInstActiveLoanFileId = null;
            var loanInstEditSaving = false;
            var loanInstallmentPayOverlay = document.getElementById('loan-installment-pay-overlay');
            var loanInstPayClose = document.getElementById('loan-inst-pay-close');
            var loanInstPayDismiss = document.getElementById('loan-inst-pay-dismiss');
            var loanInstPayTitle = document.getElementById('loan-inst-pay-title');
            var loanInstPaySubtitle = document.getElementById('loan-inst-pay-subtitle');
            var loanInstPayColCustomer = document.getElementById('loan-inst-pay-col-customer');
            var loanInstPayColSchedule = document.getElementById('loan-inst-pay-col-schedule');
            var loanInstPayColRemaining = document.getElementById('loan-inst-pay-col-remaining');
            var loanInstPayColStart = document.getElementById('loan-inst-pay-col-start');
            var loanInstPayStrip = document.getElementById('loan-inst-pay-strip');
            var loanInstPayAddBtn = document.getElementById('loan-inst-pay-add');
            var loanInstPayFormWrap = document.getElementById('loan-inst-pay-form-wrap');
            var loanInstPayMethodSelect = document.getElementById('loan-inst-pay-method');
            var loanInstPayAmountInput = document.getElementById('loan-inst-pay-amount');
            var loanInstPayRefDueInput = document.getElementById('loan-inst-pay-ref-due');
            var loanInstPayDepInput = document.getElementById('loan-inst-pay-dep');
            var loanInstPayNoteInput = document.getElementById('loan-inst-pay-note');
            var loanInstPayFormCancel = document.getElementById('loan-inst-pay-form-cancel');
            var loanInstPaySaveBtn = document.getElementById('loan-inst-pay-save');
            var loanInstPayTbody = document.getElementById('loan-inst-pay-tbody');
            var loanInstPaySubmitting = false;
            var loanInstClearAllPaymentsBusy = false;
            var loanInstPayFormVisible = false;
            var loanInstPayCurrentInstallmentId = null;
            var loanInstPayLastServerPayload = null;
            var loanInstPayEditingPaymentId = null;
            var loanInstPayEditingOriginalAmount = 0;
            var loanGuaranteeClose = document.getElementById('loan-guarantee-close');
            var loanGuaranteeSubtitle = document.getElementById('loan-guarantee-subtitle');
            var loanGuaranteeList = document.getElementById('loan-guarantee-list');
            var loanGuaranteeOpenFormBtn = document.getElementById('loan-guarantee-open-form');
            var loanGuaranteeFormWrap = document.getElementById('loan-guarantee-form-wrap');
            var loanGuaranteeForm = document.getElementById('loan-guarantee-form');
            var loanGuaranteeTypeTabs = Array.prototype.slice.call(document.querySelectorAll('[data-guarantee-type]'));
            var loanGuaranteeTypeInput = document.getElementById('loan-guarantee-type');
            var loanGuaranteeTypeSections = Array.prototype.slice.call(document.querySelectorAll('[data-guarantee-section]'));
            var loanGuaranteeCancel = document.getElementById('loan-guarantee-cancel');
            var loanGuaranteeAttachmentInput = document.getElementById('loan-guarantee-attachment');
            var loanGuaranteePreview = document.getElementById('loan-guarantee-preview');
            var loanGuaranteePreviewDownloadLink = document.getElementById('loan-guarantee-preview-download-link');
            var loanGuaranteeFileName = document.getElementById('loan-guarantee-file-name');
            var loanGuaranteeFileUploadBtn = document.getElementById('loan-guarantee-file-upload');
            var loanGuaranteeFileRemoveBtn = document.getElementById('loan-guarantee-file-remove');
            var loanGuaranteeFileDownloadBtn = document.getElementById('loan-guarantee-file-download');
            var loanGuaranteeCurrentLoanId = null;
            var loanGuaranteeSubmitting = false;
            var loanGuaranteeCurrentDownloadUrl = '';
            var loanGuaranteeFormMode = 'create';
            var loanGuaranteeEditingId = null;
            var loanGuaranteeRemoveExistingAttachment = false;
            var loanGuaranteeLoadedMeta = null;
            var loanGuaranteeGuarantorOtpLocked = false;
            var loanGuaranteeGuarantorOtpPhoneSnapshot = '';
            var goldItemOptions = Array.prototype.slice.call(document.querySelectorAll('[data-gold-option]'));
            var goldWeightWrap = document.getElementById('gold-weight-wrap');
            var goldQuantityWrap = document.getElementById('gold-quantity-wrap');
            var walletState = {
                balance_toman: 0,
                is_locked: false,
                locked_at: null
            };

            function escapeHtmlAttr(v) {
                if (v === undefined || v === null) return '';
                return String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function escapeHtmlText(v) {
                if (v === undefined || v === null) return '';
                return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            function formatToman(value) {
                var n = Number(value || 0);
                if (!Number.isFinite(n)) n = 0;
                return new Intl.NumberFormat('fa-IR').format(n);
            }

            function formatThousandsInputValue(rawValue) {
                var digits = toEnglishDigits(String(rawValue || '')).replace(/[^\d]/g, '');
                if (digits === '') {
                    return '';
                }
                return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function parseThousandsInput(rawValue) {
                var digits = toEnglishDigits(String(rawValue || '')).replace(/[^\d]/g, '');
                if (!digits) return 0;
                var n = parseInt(digits, 10);
                return Number.isFinite(n) ? n : 0;
            }

            function renderWalletTemplateText(templateBody, vars) {
                var text = String(templateBody || '');
                return text.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function (_, key) {
                    var k = String(key || '').toLowerCase();
                    return vars[k] !== undefined ? String(vars[k]) : '';
                });
            }

            function selectedLoanTypeData() {
                if (!loanTypeIdSelect) return null;
                var selectedId = parseInt(String(loanTypeIdSelect.value || '0'), 10);
                if (!selectedId) return null;
                return loanTypesData.find(function (x) {
                    return parseInt(String(x.id), 10) === selectedId;
                }) || null;
            }

            function loanRepaymentDurationMonths(installmentsCount, intervalCount, intervalUnit) {
                var c = Number(installmentsCount || 0);
                var gap = Number(intervalCount || 0);
                if (!Number.isFinite(c) || c <= 0 || !Number.isFinite(gap) || gap <= 0) return 0;
                var mul = String(intervalUnit || 'monthly') === 'weekly' ? (12 / 52) : 1;
                return c * gap * mul;
            }

            function loanProfitToman(amountToman, interestRatePercent, profitMethod, installmentsCount, intervalCount, intervalUnit) {
                var amount = Number(amountToman || 0);
                var rate = Number(interestRatePercent || 0);
                var months = loanRepaymentDurationMonths(installmentsCount, intervalCount, intervalUnit);
                if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(rate) || rate <= 0 || months <= 0) return 0;
                var rateFactor = rate / 100;
                var profit = String(profitMethod || 'monthly') === 'bank'
                    ? (amount * rateFactor * (months / 12))
                    : (amount * rateFactor * months);
                return Math.max(0, Math.round(profit));
            }

            function allocateLoanInstallmentAmounts(amountToman, profitToman, downPaymentToman, installmentsCount, roundingConfig) {
                var cfg = roundingConfig || loanInstallmentRounding || {};
                var step = Number(cfg.step_toman || 10000);
                if (!Number.isFinite(step) || step < 1) step = 10000;
                var target = String(cfg.remainder_target || 'last');
                var amount = Number(amountToman || 0);
                var profit = Number(profitToman || 0);
                var downPayment = Math.max(0, Number(downPaymentToman || 0));
                var count = parseInt(String(installmentsCount || '0'), 10);
                var payable = Math.max(0, (amount + profit) - downPayment);

                function buildSchedule(payableAmount, downPay) {
                    if (!Number.isFinite(count) || count < 1 || payableAmount < 1) {
                        return { base: 0, amounts: [], remainder: 0, adjustedDownPayment: downPay, payable: payableAmount, sum: 0 };
                    }
                    var raw = Math.floor(payableAmount / count);
                    var base = Math.floor(raw / step) * step;
                    if (base < 1) base = raw;
                    var amounts = [];
                    var i;
                    for (i = 0; i < count; i++) amounts.push(base);
                    var remainder = payableAmount - (base * count);
                    if (remainder > 0) {
                        if (target === 'first') {
                            amounts[0] = base + remainder;
                        } else if (target === 'down_payment') {
                            // remainder applied via down payment below
                        } else if (target === 'distribute') {
                            var share = Math.floor(remainder / count);
                            var extra = remainder % count;
                            for (i = 0; i < count; i++) {
                                amounts[i] = base + share + (i < extra ? 1 : 0);
                            }
                            base = base + share;
                        } else {
                            amounts[count - 1] = base + remainder;
                        }
                    }
                    var sum = amounts.reduce(function (acc, val) { return acc + val; }, 0);
                    return { base: base, amounts: amounts, remainder: Math.max(0, remainder), adjustedDownPayment: downPay, payable: payableAmount, sum: sum };
                }

                var schedule = buildSchedule(payable, downPayment);
                if (target === 'down_payment' && schedule.remainder > 0) {
                    downPayment += schedule.remainder;
                    payable = Math.max(0, (amount + profit) - downPayment);
                    schedule = buildSchedule(payable, downPayment);
                }

                return schedule;
            }

            function loanInstallmentRoundingNote(allocation) {
                if (!allocation || !allocation.remainder || allocation.remainder <= 0) return '';
                var target = String((loanInstallmentRounding && loanInstallmentRounding.remainder_target) || 'last');
                if (target === 'down_payment') {
                    return ' | مبلغ خرد ' + formatToman(allocation.remainder) + ' به پیش‌پرداخت اضافه می‌شود';
                }
                if (target === 'distribute') {
                    return ' | مبلغ خرد ' + formatToman(allocation.remainder) + ' بین اقساط تقسیم می‌شود';
                }
                return ' | مبلغ خرد ' + formatToman(allocation.remainder) + ' به ' + String((loanInstallmentRounding && loanInstallmentRounding.remainder_target_label) || 'قسط آخر') + ' اضافه می‌شود';
            }

            function activeInterestRatePercent() {
                var lt = selectedLoanTypeData();
                var base = Number(lt && lt.interest_rate ? lt.interest_rate : 0);
                if (loanHasCustomInterestCheckbox && loanHasCustomInterestCheckbox.checked) {
                    var custom = Number(toEnglishDigits(String((loanCustomInterestRateInput && loanCustomInterestRateInput.value) || '')).replace(',', '.'));
                    if (Number.isFinite(custom) && custom >= 0) {
                        return custom;
                    }
                }
                return base;
            }

            function isRepaymentAllowedByLoanType(loanType, amount, installmentsCount, intervalCount, intervalUnit) {
                if (!loanType) return true;
                var periods = loanType.repayment_periods && typeof loanType.repayment_periods === 'object'
                    ? loanType.repayment_periods
                    : null;
                if (!periods || !periods.type || periods.type === 'unlimited') return true;
                var months = Math.ceil(loanRepaymentDurationMonths(installmentsCount, intervalCount, intervalUnit));
                if (periods.type === 'max_until') {
                    var maxMonths = parseInt(String(periods.max_months || '0'), 10);
                    return !maxMonths || maxMonths < 1 || months <= maxMonths;
                }
                if (periods.type === 'allowed_months') {
                    var rows = Array.isArray(periods.allowed_rows) ? periods.allowed_rows : [];
                    return rows.some(function (row) {
                        var m = parseInt(String((row && row.months) || '0'), 10);
                        var cap = Math.round(Number((row && row.cap) || 0));
                        return m === months && (!cap || cap < 1 || amount <= cap);
                    });
                }
                return true;
            }

            function setLoanFieldError(fieldEl, message) {
                if (!fieldEl) return;
                var wrapper = fieldEl.closest('.cust-field');
                if (!wrapper) return;
                fieldEl.classList.add('is-invalid');
                var err = wrapper.querySelector('.cust-field-error[data-loan-field-error]');
                if (!err) {
                    err = document.createElement('div');
                    err.className = 'cust-field-error';
                    err.setAttribute('data-loan-field-error', '1');
                    wrapper.appendChild(err);
                }
                err.textContent = message;
            }

            function clearLoanFieldError(fieldEl) {
                if (!fieldEl) return;
                var wrapper = fieldEl.closest('.cust-field');
                if (!wrapper) return;
                fieldEl.classList.remove('is-invalid');
                var err = wrapper.querySelector('.cust-field-error[data-loan-field-error]');
                if (err) err.remove();
            }

            function clearAllLoanFieldErrors() {
                if (!loanCreateForm) return;
                loanCreateForm.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
                loanCreateForm.querySelectorAll('.cust-field-error[data-loan-field-error]').forEach(function (el) {
                    el.remove();
                });
            }

            function removeMethodField() {
                var el = document.getElementById('cust-http-method');
                if (el) {
                    el.remove();
                }
            }

            function setCustomerSubmitLoading(isLoading) {
                if (!form) return;
                var submitBtn = form.querySelector('button[type="submit"]');
                if (!submitBtn) return;
                submitBtn.disabled = !!isLoading;
                submitBtn.textContent = isLoading ? 'در حال ذخیره...' : 'ذخیره';
            }

            function addMethodPut() {
                if (document.getElementById('cust-http-method')) {
                    return;
                }
                var el = document.createElement('input');
                el.type = 'hidden';
                el.name = '_method';
                el.id = 'cust-http-method';
                el.value = 'PUT';
                form.appendChild(el);
            }

            function destroyCustPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    return;
                }
                try {
                    window.jQuery('#cust-membership-jdate, #cust-birth-jdate').each(function () {
                        var $el = window.jQuery(this);
                        if ($el.data('datepicker')) {
                            $el.pDatepicker('destroy');
                        }
                    });
                } catch (err) { /* noop */ }
            }

            function setCustProfileOptionalFieldsRequired(isRequired) {
                [custFatherInput, custNationalInput, custCityInput, custAddressInput, custPostalInput].forEach(function (el) {
                    if (el) {
                        el.required = isRequired;
                    }
                });
                [custFatherReq, custNationalReq, custCityReq, custAddressReq, custPostalReq].forEach(function (el) {
                    if (el) {
                        el.style.display = isRequired ? '' : 'none';
                    }
                });
            }

            function openCreateModal() {
                custFormMode = 'create';
                removeMethodField();
                form.action = custStoreUrl;
                form.setAttribute('method', 'post');
                if (modalTitle) {
                    modalTitle.textContent = 'افزودن مشتری جدید';
                }
                if (modalDesc) {
                    modalDesc.textContent = 'فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.';
                }
                if (pwdReq) {
                    pwdReq.style.display = '';
                }
                if (pwdHint) {
                    pwdHint.hidden = true;
                }
                if (pwdInput) {
                    pwdInput.value = '';
                    pwdInput.required = true;
                    pwdInput.placeholder = '';
                }
                setCustProfileOptionalFieldsRequired(false);
                bankContainer.innerHTML = '';
                refContainer.innerHTML = '';
                bankIndex = 0;
                refIndex = 0;
                form.reset();
                if (sendHidden) {
                    sendHidden.value = '0';
                }
                if (sendChk) {
                    sendChk.checked = false;
                }
                customerFormSubmitting = false;
                setCustomerSubmitLoading(false);
                destroyCustPickers();
                var membershipJdateEl = document.getElementById('cust-membership-jdate');
                if (membershipJdateEl && adminTodayJdate) {
                    membershipJdateEl.value = adminTodayJdate;
                }
                syncUsername();
                openModal();
            }

            function openEditModal(customerId) {
                custFormMode = 'edit';
                addMethodPut();
                form.action = custUpdateUrl(customerId);
                form.setAttribute('method', 'post');
                if (modalTitle) {
                    modalTitle.textContent = 'ویرایش مشتری';
                }
                if (modalDesc) {
                    modalDesc.textContent = 'اطلاعات مشتری را ویرایش کنید. رمز عبور را فقط در صورت تغییر پر کنید.';
                }
                if (pwdReq) {
                    pwdReq.style.display = 'none';
                }
                if (pwdHint) {
                    pwdHint.hidden = false;
                }
                if (pwdInput) {
                    pwdInput.value = '';
                    pwdInput.required = false;
                    pwdInput.placeholder = '';
                }
                setCustProfileOptionalFieldsRequired(false);
                customerFormSubmitting = false;
                setCustomerSubmitLoading(false);

                fetch(custEditDataUrl(customerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad');
                    }
                    return r.json();
                }).then(function (data) {
                    var c = data.customer;
                    document.getElementById('cust-code').value = c.customer_code || '';
                    document.getElementById('cust-fname').value = c.first_name || '';
                    document.getElementById('cust-lname').value = c.last_name || '';
                    document.getElementById('cust-father').value = c.father_name || '';
                    document.getElementById('cust-national').value = c.national_id || '';
                    document.getElementById('cust-mobile').value = c.mobile || '';
                    document.getElementById('cust-mobile2').value = c.mobile2 || '';
                    document.getElementById('cust-phone').value = c.phone_landline || '';
                    document.getElementById('cust-membership-jdate').value = c.membership_jdate || '';
                    document.getElementById('cust-birth-jdate').value = c.birth_jdate || '';
                    document.getElementById('cust-email').value = c.email || '';
                    document.getElementById('cust-city').value = c.city || '';
                    document.getElementById('cust-address').value = c.address || '';
                    document.getElementById('cust-postal').value = c.postal_code || '';

                    bankContainer.innerHTML = '';
                    bankIndex = 0;
                    var banks = data.bank_accounts || [];
                    banks.forEach(function (row, idx) {
                        var i = idx;
                        bankIndex = idx + 1;
                        var div = document.createElement('div');
                        div.className = 'cust-repeat-row';
                        div.setAttribute('data-bank-row', '');
                        div.innerHTML =
                            '<div class="cust-field"><label>شماره کارت / حساب / شبا</label>' +
                            '<input name="accounts[' + i + '][account_identifier]" value="' + escapeHtmlAttr(row.account_identifier) + '" placeholder="مثلاً شبا یا شماره کارت"></div>' +
                            '<div class="cust-field"><label>بانک</label><input name="accounts[' + i + '][bank_name]" value="' + escapeHtmlAttr(row.bank_name) + '"></div>' +
                            '<div class="cust-field"><label>شعبه</label><input name="accounts[' + i + '][branch_name]" value="' + escapeHtmlAttr(row.branch_name) + '"></div>' +
                            '<button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>';
                        bankContainer.appendChild(div);
                        div.querySelector('[data-remove-bank]').addEventListener('click', function () {
                            div.remove();
                        });
                    });

                    refContainer.innerHTML = '';
                    refIndex = 0;
                    var refs = data.referrers || [];
                    refs.forEach(function (row, idx) {
                        var i = idx;
                        refIndex = idx + 1;
                        var div = document.createElement('div');
                        div.className = 'cust-ref-row';
                        div.setAttribute('data-ref-row', '');
                        div.innerHTML =
                            '<div class="cust-field"><label>نام</label><input name="referrers[' + i + '][first_name]" value="' + escapeHtmlAttr(row.first_name) + '"></div>' +
                            '<div class="cust-field"><label>نام خانوادگی</label><input name="referrers[' + i + '][last_name]" value="' + escapeHtmlAttr(row.last_name) + '"></div>' +
                            '<div class="cust-field"><label>شماره تماس</label><input name="referrers[' + i + '][phone]" value="' + escapeHtmlAttr(row.phone) + '" placeholder="09xxxxxxxxx"></div>' +
                            '<button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>';
                        refContainer.appendChild(div);
                        div.querySelector('[data-remove-ref]').addEventListener('click', function () {
                            div.remove();
                        });
                    });

                    syncUsername();
                    destroyCustPickers();
                    openModal();
                }).catch(function () {
                    if (window.AdminSwal && window.AdminSwal.error) {
                        AdminSwal.error('بارگذاری اطلاعات مشتری ناموفق بود.');
                    }
                });
            }

            function setWalletVisualState() {
                if (walletBalanceView) {
                    walletBalanceView.innerHTML = formatToman(walletState.balance_toman) + ' <small>تومان</small>';
                }
                if (walletAdjustBalance) {
                    walletAdjustBalance.textContent = formatToman(walletState.balance_toman) + ' تومان';
                }
                if (!walletLockPill || !walletLockToggleBtn) {
                    return;
                }
                if (walletState.is_locked) {
                    walletLockPill.className = 'wallet-status-pill wallet-status-pill--locked';
                    walletLockPill.innerHTML = '<i class="fa-solid fa-lock"></i> قفل شده';
                    walletLockToggleBtn.classList.remove('wallet-btn--danger');
                    walletLockToggleBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> بازکردن کیف پول';
                } else {
                    walletLockPill.className = 'wallet-status-pill wallet-status-pill--ok';
                    walletLockPill.innerHTML = '<i class="fa-solid fa-lock-open"></i> فعال';
                    walletLockToggleBtn.classList.add('wallet-btn--danger');
                    walletLockToggleBtn.innerHTML = '<i class="fa-solid fa-lock"></i> قفل کیف پول';
                }
            }

            function openWalletModal(customerId, customerName, customerMobile) {
                walletCurrentCustomerId = customerId;
                walletCurrentCustomerName = customerName || '';
                walletCurrentCustomerMobile = customerMobile || '';
                if (walletSubtitle) {
                    walletSubtitle.textContent = 'مدیریت کیف پول «' + walletCurrentCustomerName + '»';
                }

                fetch(walletShowUrl(customerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad');
                    }
                    return r.json();
                }).then(function (data) {
                    walletState = data.wallet || walletState;
                    walletSmsTemplates = Array.isArray(data.sms_templates) ? data.sms_templates : [];
                    setWalletVisualState();
                    walletModalOverlay.hidden = false;
                    walletModalOverlay.setAttribute('aria-hidden', 'false');
                }).catch(function () {
                    if (window.AdminSwal && window.AdminSwal.error) {
                        AdminSwal.error('اطلاعات کیف پول بارگذاری نشد.');
                    }
                });
            }

            function closeWalletModal() {
                if (!walletModalOverlay) return;
                walletModalOverlay.hidden = true;
                walletModalOverlay.setAttribute('aria-hidden', 'true');
            }

            function openWalletAdjustModal() {
                if (!walletAdjustOverlay) return;
                setWalletVisualState();
                walletAdjustForm.reset();
                walletAdjustOverlay.hidden = false;
                walletAdjustOverlay.setAttribute('aria-hidden', 'false');
            }

            function closeWalletAdjustModal() {
                if (!walletAdjustOverlay) return;
                walletAdjustOverlay.hidden = true;
                walletAdjustOverlay.setAttribute('aria-hidden', 'true');
            }

            function openWalletTransactionsModal() {
                if (!walletCurrentCustomerId) return;
                walletTransTbody.innerHTML = '<tr><td colspan="6" class="wallet-empty">در حال بارگذاری...</td></tr>';
                walletTransOverlay.hidden = false;
                walletTransOverlay.setAttribute('aria-hidden', 'false');

                fetch(walletTransactionsUrl(walletCurrentCustomerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad');
                    }
                    return r.json();
                }).then(function (data) {
                    var rows = data.transactions || [];
                    if (rows.length === 0) {
                        walletTransTbody.innerHTML = '<tr><td colspan="6" class="wallet-empty">هنوز تراکنشی ثبت نشده است.</td></tr>';
                        return;
                    }
                    walletTransTbody.innerHTML = rows.map(function (row) {
                        var isDeposit = row.direction === 'deposit';
                        var dirText = isDeposit ? 'واریز' : 'برداشت';
                        var dirClass = isDeposit ? 'wallet-trans-plus' : 'wallet-trans-minus';
                        return '<tr>' +
                            '<td>' + escapeHtmlText(row.created_at || '—') + '</td>' +
                            '<td class="' + dirClass + '">' + dirText + '</td>' +
                            '<td class="' + dirClass + '">' + formatToman(row.amount_toman) + '</td>' +
                            '<td>' + formatToman(row.balance_after_toman) + '</td>' +
                            '<td>' + escapeHtmlText(row.description || '—') + '</td>' +
                            '<td>' + escapeHtmlText(row.actor_name || '—') + '</td>' +
                            '</tr>';
                    }).join('');
                }).catch(function () {
                    walletTransTbody.innerHTML = '<tr><td colspan="6" class="wallet-empty">خطا در دریافت تراکنش‌ها.</td></tr>';
                });
            }

            function closeWalletTransactionsModal() {
                if (!walletTransOverlay) return;
                walletTransOverlay.hidden = true;
                walletTransOverlay.setAttribute('aria-hidden', 'true');
            }

            function openQuickSmsModal(customerId, customerName, customerMobile, smsType, meta) {
                meta = meta || {};
                quickSmsCurrentCustomerId = customerId;
                quickSmsCurrentType = smsType;
                quickSmsCurrentCustomerName = customerName || '';
                var iid = meta.installmentId;
                var lid = meta.loanFileId;
                quickSmsCurrentInstallmentId = iid != null && String(iid).trim() !== '' ? parseInt(String(iid), 10) : null;
                quickSmsCurrentLoanFileId = lid != null && String(lid).trim() !== '' ? parseInt(String(lid), 10) : null;
                if (quickSmsCurrentInstallmentId !== null && !Number.isFinite(quickSmsCurrentInstallmentId)) {
                    quickSmsCurrentInstallmentId = null;
                }
                if (quickSmsCurrentLoanFileId !== null && !Number.isFinite(quickSmsCurrentLoanFileId)) {
                    quickSmsCurrentLoanFileId = null;
                }
                var typeTitle = {
                    wallet_link: 'ارسال پیامک لینک شارژ',
                    welcome: 'ارسال پیامک خوش‌آمدگویی',
                    installment_pre_due: 'ارسال پیامک پیش از سررسید',
                    installment_due: 'ارسال پیامک سررسید',
                    installment_overdue: 'ارسال پیامک معوق',
                    installment_thanks: 'ارسال پیامک تشکر پس از پرداخت'
                };
                if (quickSmsTitle) {
                    quickSmsTitle.textContent = typeTitle[smsType] || typeTitle.welcome;
                }
                if (quickSmsSubtitle) {
                    quickSmsSubtitle.textContent = 'گیرنده: ' + (customerName || '') + ' — ' + (customerMobile || '');
                }
                if (quickSmsForm) {
                    quickSmsForm.reset();
                }
                if (quickSmsText) {
                    quickSmsText.value = '';
                    quickSmsText.placeholder = 'در حال بارگذاری متن از قالب پیامک…';
                }
                if (quickSmsOverlay) {
                    quickSmsOverlay.hidden = false;
                    quickSmsOverlay.setAttribute('aria-hidden', 'false');
                }
                loadQuickSmsModalPreview();
            }

            function loadQuickSmsModalPreview() {
                if (!quickSmsText || !quickSmsCurrentCustomerId) return;
                var smsType = quickSmsCurrentType || 'welcome';
                var instTypes = ['installment_pre_due', 'installment_due', 'installment_overdue', 'installment_thanks'];
                var needsInst = instTypes.indexOf(smsType) !== -1;
                if (needsInst && (!quickSmsCurrentInstallmentId || quickSmsCurrentInstallmentId < 1)) {
                    quickSmsText.placeholder = 'متن پیامک را بنویسید...';
                    return;
                }
                var url = customerSmsModalPreviewUrl(quickSmsCurrentCustomerId, smsType, needsInst ? quickSmsCurrentInstallmentId : null);
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    quickSmsText.placeholder = 'متن پیامک را بنویسید...';
                    if (data.body != null && data.body !== undefined) {
                        quickSmsText.value = String(data.body);
                    }
                    if (quickSmsTemplate && data.template_id != null && String(data.template_id) !== '') {
                        quickSmsTemplate.value = String(data.template_id);
                    }
                }).catch(function () {
                    quickSmsText.placeholder = 'متن پیامک را بنویسید...';
                    var smsTypeCatch = quickSmsCurrentType || 'welcome';
                    if (smsTypeCatch === 'wallet_link') {
                        quickSmsText.value = 'سلام ' + (quickSmsCurrentCustomerName || '') + '، لینک شارژ کیف پول شما: —';
                    } else if (smsTypeCatch === 'welcome') {
                        quickSmsText.value = 'سلام ' + (quickSmsCurrentCustomerName || '') + '، به سامانه خوش آمدید.';
                    }
                });
            }

            function closeQuickSmsModal() {
                if (!quickSmsOverlay) return;
                quickSmsOverlay.hidden = true;
                quickSmsOverlay.setAttribute('aria-hidden', 'true');
                quickSmsCurrentInstallmentId = null;
                quickSmsCurrentLoanFileId = null;
            }

            function loanInstMakeSmsCell(row) {
                var cid = loanManageCurrentCustomerId;
                var cname = loanManageCurrentCustomerName || '';
                var cmob = loanManageCurrentCustomerMobile || '';
                var iid = row.id;
                var lf = row.loan_file_id;
                if (!cid || !iid) {
                    return '—';
                }
                var ea = escapeHtmlAttr;
                var stats = row.sms_stats && typeof row.sms_stats === 'object' ? row.sms_stats : {};

                function makeSmsBtn(type, cls, letter, title) {
                    var st = stats[type] || {};
                    var count = Number(st.count || 0);
                    var lastMode = st.last_mode || null;
                    var html = '<span class="cust-sms-circle-wrap">';
                    html += '<button type="button" class="cust-sms-circle-btn ' + cls + '" data-cust-quick-sms data-sms-type="' + type + '" data-customer-id="' + String(cid) + '" data-customer-name="' + ea(cname) + '" data-customer-mobile="' + ea(cmob) + '" data-installment-id="' + String(iid) + '" data-loan-file-id="' + String(lf) + '" title="' + ea(title) + '">' + letter + '</button>';
                    if (count > 0) {
                        html += '<span class="cust-sms-badge cust-sms-badge--count" title="تعداد ارسال: ' + ea(formatToman(count)) + '">' + formatToman(count) + '</span>';
                    }
                    if (lastMode === 'auto' || lastMode === 'manual') {
                        var modeLabel = lastMode === 'auto' ? 'خ' : 'د';
                        var modeTitle = lastMode === 'auto' ? 'آخرین ارسال: خودکار' : 'آخرین ارسال: دستی';
                        html += '<span class="cust-sms-badge cust-sms-badge--mode cust-sms-badge--' + lastMode + '" title="' + ea(modeTitle) + '">' + modeLabel + '</span>';
                    }
                    html += '</span>';

                    return html;
                }

                return '<div class="cust-sms-actions loan-inst-sms-actions">' +
                    makeSmsBtn('installment_pre_due', 'cust-sms-circle-btn--inst-pre', 'پ', 'ارسال پیامک پیش از موعد') +
                    makeSmsBtn('installment_due', 'cust-sms-circle-btn--inst-due', 'س', 'ارسال پیامک سررسید') +
                    makeSmsBtn('installment_overdue', 'cust-sms-circle-btn--inst-over', 'م', 'ارسال پیامک معوق جدید') +
                    makeSmsBtn('installment_thanks', 'cust-sms-circle-btn--inst-thanks', 'ت', 'ارسال پیامک تشکر جدید') +
                    '</div>';
            }

            function reloadActiveLoanInstallmentsQuiet() {
                if (!loanManageCurrentCustomerId || !loanInstActiveLoanFileId) {
                    return;
                }
                if (!loanInstallmentsOverlay || loanInstallmentsOverlay.hidden) {
                    return;
                }
                fetch(customerLoanInstallmentsUrl(loanManageCurrentCustomerId, loanInstActiveLoanFileId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad');
                    }

                    return r.json();
                }).then(function (data) {
                    renderLoanInstallmentsPayload(data);
                }).catch(function () { /* noop */ });
            }

            function destroyLoanInstallmentEditDatePicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var $el = window.jQuery('#loan-inst-edit-due');
                if (!$el.length) return;
                try {
                    if ($el.data('datepicker')) {
                        $el.pDatepicker('destroy');
                    }
                } catch (err) { /* noop */ }
            }

            function initLoanInstallmentEditDatePicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var el = document.getElementById('loan-inst-edit-due');
                if (!el || el.disabled) return;
                destroyLoanInstallmentEditDatePicker();
                window.jQuery('#loan-inst-edit-due').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
            }

            function fillLoanInstallmentEditBanner(loan) {
                var l = loan || {};
                var cname = String(loanManageCurrentCustomerName || '').trim();
                var cmob = String(loanManageCurrentCustomerMobile || '').trim();
                if (loanInstEditColCustomer) {
                    loanInstEditColCustomer.innerHTML =
                        '<div>' + escapeHtmlText(cname || '—') + '</div>' +
                        '<div style="margin-top:0.3rem;"><span style="color:var(--muted);font-weight:700;">کد پرونده:</span> ' + escapeHtmlText(String(l.loan_code || '—')) + '</div>' +
                        '<div style="margin-top:0.3rem;"><span style="color:var(--muted);font-weight:700;">نوع وام:</span> ' + escapeHtmlText(String(l.loan_type_title || '—')) + '</div>' +
                        '<div style="margin-top:0.25rem;"><span style="color:var(--muted);font-weight:700;">مبلغ وام:</span> ' + escapeHtmlText(formatToman(l.amount_toman || 0) + ' تومان') + '</div>' +
                        (cmob ? '<div style="margin-top:0.35rem;font-size:0.7rem;color:var(--muted);"> موبایل: ' + escapeHtmlText(cmob) + '</div>' : '');
                }
                var icnt = Number(l.installments_count || 0);
                var iamt = Number(l.installment_amount_toman || 0);
                if (loanInstEditColSchedule) {
                    loanInstEditColSchedule.innerHTML =
                        '<div><span style="color:var(--muted);font-weight:700;">تعداد اقساط:</span> ' + escapeHtmlText(formatToman(icnt)) + '</div>' +
                        '<div style="margin-top:0.35rem;"><span style="color:var(--muted);font-weight:700;">مبلغ هر قسط (پرونده):</span> ' + escapeHtmlText(formatToman(iamt) + ' تومان') + '</div>';
                }
                var rem = Number(l.schedule_remaining_toman != null ? l.schedule_remaining_toman : 0);
                if (loanInstEditColRemaining) {
                    loanInstEditColRemaining.innerHTML =
                        '<div style="font-weight:800;font-size:0.88rem;">' + escapeHtmlText(formatToman(rem) + ' تومان') + '</div>';
                }
            }

            function closeLoanInstallmentEditModal() {
                if (!loanInstallmentEditOverlay) return;
                destroyLoanInstallmentEditDatePicker();
                loanInstallmentEditOverlay.hidden = true;
                loanInstallmentEditOverlay.setAttribute('aria-hidden', 'true');
                if (loanInstEditAmountInput) loanInstEditAmountInput.value = '';
                if (loanInstEditDueInput) loanInstEditDueInput.value = '';
                if (loanInstEditInstallmentIdInput) loanInstEditInstallmentIdInput.value = '';
                loanInstEditSaving = false;
                if (loanInstEditSave) loanInstEditSave.disabled = false;
            }

            function openLoanInstallmentEditModal(row) {
                if (!loanInstallmentEditOverlay || !loanInstCachedPayload || !row) return;
                var loan = loanInstCachedPayload.loan || {};
                fillLoanInstallmentEditBanner(loan);
                if (loanInstEditTitle) loanInstEditTitle.textContent = 'ویرایش قسط';
                if (loanInstEditSubtitle) {
                    loanInstEditSubtitle.textContent =
                        'قسط شماره ' + formatToman(row.sequence || 0) +
                        ' از ' + formatToman(loan.installments_count || 0);
                }
                if (loanInstEditInstallmentIdInput) loanInstEditInstallmentIdInput.value = String(row.id || '');
                if (loanInstEditAmountInput) {
                    loanInstEditAmountInput.value = formatThousandsInputValue(String(row.amount_toman || ''));
                }
                destroyLoanInstallmentEditDatePicker();
                if (loanInstEditDueInput) {
                    loanInstEditDueInput.value = String(row.due_jdate || '').trim();
                }
                loanInstallmentEditOverlay.hidden = false;
                loanInstallmentEditOverlay.setAttribute('aria-hidden', 'false');
                setTimeout(function () { initLoanInstallmentEditDatePicker(); }, 0);
            }

            function destroyLoanInstPayDatePickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                ['#loan-inst-pay-ref-due', '#loan-inst-pay-dep'].forEach(function (sel) {
                    try {
                        var $px = window.jQuery(sel);
                        if ($px.length && $px.data('datepicker')) {
                            $px.pDatepicker('destroy');
                        }
                    } catch (errPayP) { /* noop */ }
                });
            }

            function initLoanInstPayBothPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                destroyLoanInstPayDatePickers();
                var payPickerCfg = {
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                };
                if (document.getElementById('loan-inst-pay-ref-due')) {
                    window.jQuery('#loan-inst-pay-ref-due').pDatepicker(payPickerCfg);
                }
                if (document.getElementById('loan-inst-pay-dep')) {
                    window.jQuery('#loan-inst-pay-dep').pDatepicker(payPickerCfg);
                }
            }

            function fillLoanInstPayBannerFromLoan(loan) {
                var ln = loan || {};
                var cnameP = String(loanManageCurrentCustomerName || '').trim();
                var cmobP = String(loanManageCurrentCustomerMobile || '').trim();
                if (loanInstPayColCustomer) {
                    loanInstPayColCustomer.innerHTML =
                        '<div>' + escapeHtmlText(cnameP || '—') + '</div>' +
                        '<div style="margin-top:0.3rem;"><span style="color:var(--muted);font-weight:700;">کد پرونده:</span> ' + escapeHtmlText(String(ln.loan_code || '—')) + '</div>' +
                        '<div style="margin-top:0.3rem;"><span style="color:var(--muted);font-weight:700;">نوع وام:</span> ' + escapeHtmlText(String(ln.loan_type_title || '—')) + '</div>' +
                        '<div style="margin-top:0.25rem;"><span style="color:var(--muted);font-weight:700;">مبلغ وام:</span> ' + escapeHtmlText(formatToman(ln.amount_toman || 0) + ' تومان') + '</div>' +
                        (cmobP ? '<div style="margin-top:0.35rem;font-size:0.7rem;color:var(--muted);"> موبایل: ' + escapeHtmlText(cmobP) + '</div>' : '');
                }
                var icntp = Number(ln.installments_count || 0);
                var iamtp = Number(ln.installment_amount_toman || 0);
                if (loanInstPayColSchedule) {
                    loanInstPayColSchedule.innerHTML =
                        '<div><span style="color:var(--muted);font-weight:700;">تعداد اقساط:</span> ' + escapeHtmlText(formatToman(icntp)) + '</div>' +
                        '<div style="margin-top:0.35rem;"><span style="color:var(--muted);font-weight:700;">مبلغ هر قسط (پرونده):</span> ' + escapeHtmlText(formatToman(iamtp) + ' تومان') + '</div>';
                }
                var remp = Number(ln.schedule_remaining_toman != null ? ln.schedule_remaining_toman : 0);
                if (loanInstPayColRemaining) {
                    loanInstPayColRemaining.innerHTML =
                        '<div style="font-weight:800;font-size:0.88rem;">' + escapeHtmlText(formatToman(remp) + ' تومان') + '</div>';
                }
                if (loanInstPayColStart) {
                    loanInstPayColStart.innerHTML =
                        '<div style="font-weight:800;">' +
                        escapeHtmlText(String(ln.loan_start_jdate_fa || ln.loan_start_jdate || '—')) +
                        '</div>';
                }
            }

            function fillLoanInstPayMethodSelectFromList(optList) {
                if (!loanInstPayMethodSelect) return;
                var list = Array.isArray(optList) ? optList : [];
                loanInstPayMethodSelect.innerHTML =
                    '<option value="">— انتخاب نحوه پرداخت —</option>' +
                    list.map(function (op) {
                        return '<option value="' +
                            escapeHtmlAttr(String(op.value || '')) + '">' +
                            escapeHtmlText(String(op.label || '')) +
                            '</option>';
                    }).join('');
            }

            function fillLoanInstPayMethodSelectAdd() {
                var optPx = loanInstPayLastServerPayload && Array.isArray(loanInstPayLastServerPayload.payment_method_options)
                    ? loanInstPayLastServerPayload.payment_method_options
                    : [];
                fillLoanInstPayMethodSelectFromList(optPx);
            }

            function loanInstPayResetEditMode() {
                loanInstPayEditingPaymentId = null;
                loanInstPayEditingOriginalAmount = 0;
                if (loanInstPaySaveBtn) loanInstPaySaveBtn.textContent = 'ثبت پرداخت';
            }

            function loanInstPayRefreshAfterMutation(cidPay, lfPay, insPay, successMsg) {
                return fetch(customerLoanInstallmentPaymentsUrl(cidPay, lfPay, insPay), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (rfresh) {
                    if (!rfresh.ok) throw new Error('خطا در بروزرسانی جزئیات پرداخت.');
                    return rfresh.json();
                }).then(function (payPayload) {
                    applyLoanInstPayServerPayload(payPayload);
                    return fetch(customerLoanInstallmentsUrl(cidPay, lfPay), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    }).then(function (rInstl) {
                        if (!rInstl.ok) throw new Error('خطا در بروزرسانی لیست اقساط.');
                        return rInstl.json().then(function (dInstl) {
                            loanInstCachedPayload = dInstl;
                            renderLoanInstallmentsPayload(dInstl);
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(successMsg);
                        });
                    });
                });
            }

            function renderLoanInstPayTable(payments) {
                var plist = Array.isArray(payments) ? payments : [];
                if (!loanInstPayTbody) return;
                var lx = loanInstPayLastServerPayload && loanInstPayLastServerPayload.loan
                    ? loanInstPayLastServerPayload.loan
                    : {};
                var lockedActs = !!(lx.is_settled === true || lx.is_settled === 1);
                if (!plist.length) {
                    loanInstPayTbody.innerHTML =
                        '<tr><td colspan="7" class="loan-inst-empty" style="padding:0.65rem;text-align:center;">پرداخت جزئی ثبت نشده است.</td></tr>';
                    return;
                }
                loanInstPayTbody.innerHTML = plist.map(function (pp) {
                    var pid = Number(pp.id || 0);
                    var refSx = String(pp.reference_due_jdate_fa || pp.reference_due_jdate || '').trim() || '—';
                    var depSx = String(pp.deposited_jdate_fa || pp.deposited_jdate || '').trim() || '—';
                    var noteS = String(pp.note || '').trim();
                    var noteSx = noteS ? escapeHtmlText(noteS.length > 52 ? noteS.slice(0, 50) + '…' : noteS) : '—';
                    var ops = lockedActs || pid <= 0
                        ? '—'
                        : ('<button type="button" class="cust-submit" style="font-size:0.62rem;padding:0.12rem 0.38rem;margin-inline-end:0.25rem;" data-loan-inst-pay-edit-id="' +
                            pid + '">ویرایش</button>' +
                            '<button type="button" class="cust-cancel" style="font-size:0.62rem;padding:0.12rem 0.38rem;" data-loan-inst-pay-delete-id="' +
                            pid + '">حذف</button>');
                    return '<tr>' +
                        '<td>' + escapeHtmlText(String(pp.payment_method_label || '—')) + '</td>' +
                        '<td>' + escapeHtmlText(formatToman(pp.amount_toman || 0) + ' تومان') + '</td>' +
                        '<td>' + escapeHtmlText(refSx) + '</td>' +
                        '<td>' + escapeHtmlText(depSx) + '</td>' +
                        '<td>' + escapeHtmlText(String(pp.recorded_by || '—')) + '</td>' +
                        '<td style="white-space:normal;max-width:8rem;font-size:0.65rem;">' + noteSx + '</td>' +
                        '<td style="white-space:nowrap;">' + ops + '</td>' +
                        '</tr>';
                }).join('');
            }

            function openLoanInstPayEditForm(pp) {
                if (!pp || !loanInstPayFormWrap || !loanInstPayLastServerPayload) return;
                var pid = Number(pp.id || 0);
                if (pid <= 0) return;
                if (loanInstPaySubmitting) return;
                var lax = loanInstPayLastServerPayload.loan || {};
                if (lax.is_settled === true || lax.is_settled === 1) {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('پرونده تسویه‌شده است؛ ویرایش پرداخت مجاز نیست.');
                    return;
                }
                loanInstPayEditingPaymentId = pid;
                loanInstPayEditingOriginalAmount = Number(pp.amount_toman || 0);
                if (loanInstPaySaveBtn) loanInstPaySaveBtn.textContent = 'ذخیرهٔ تغییرات';
                loanInstPayFormVisible = true;
                loanInstPayFormWrap.hidden = false;
                destroyLoanInstPayDatePickers();
                var editOpts = Array.isArray(loanInstPayLastServerPayload.payment_method_edit_options)
                    ? loanInstPayLastServerPayload.payment_method_edit_options
                    : (loanInstPayLastServerPayload.payment_method_options || []);
                fillLoanInstPayMethodSelectFromList(editOpts);
                if (loanInstPayMethodSelect) {
                    loanInstPayMethodSelect.value = String(pp.payment_method || '').trim();
                }
                if (loanInstPayAmountInput) {
                    loanInstPayAmountInput.value = formatThousandsInputValue(String(pp.amount_toman || ''));
                }
                if (loanInstPayRefDueInput) {
                    loanInstPayRefDueInput.value = String(pp.reference_due_jdate || '').trim();
                }
                if (loanInstPayDepInput) {
                    loanInstPayDepInput.value = String(pp.deposited_jdate || '').trim();
                }
                if (loanInstPayNoteInput) {
                    loanInstPayNoteInput.value = String(pp.note || '');
                }
                setTimeout(function () {
                    initLoanInstPayBothPickers();
                }, 0);
            }

            function setLoanInstPayStripFromInstallment(insP) {
                if (!loanInstPayStrip || !insP) return;
                var amta = Number(insP.amount_toman || 0);
                var defp = Number(insP.paid_amount_toman || 0);
                var remInst = Number(insP.remaining_toman != null ? insP.remaining_toman : Math.max(0, amta - defp));
                var maxPayStep = Number(
                    insP.max_payment_toman != null
                        ? insP.max_payment_toman
                        : remInst
                );
                loanInstPayStrip.innerHTML =
                    '<span style="font-weight:800;color:var(--text);">قسط ' +
                    escapeHtmlText(formatToman(insP.sequence || 0)) +
                    '</span> · مبلغ قسط <strong>' + escapeHtmlText(formatToman(amta) + ' تومان') + '</strong> · پرداخت‌شده <strong>' +
                    escapeHtmlText(formatToman(defp) + ' تومان') + '</strong> · ماندهٔ سهم نامی این قسط <strong>' +
                    escapeHtmlText(formatToman(remInst) + ' تومان') + '</strong> · سقف ثبت این واریزی (بر اساس ماندهٔ کل وام) <strong>' +
                    escapeHtmlText(formatToman(maxPayStep) + ' تومان') + '</strong>';
            }

            function applyLoanInstPayServerPayload(payload) {
                loanInstPayLastServerPayload = payload || null;
                var loanPx = payload && payload.loan ? payload.loan : {};
                var insPx = payload && payload.installment ? payload.installment : {};
                var payPx = payload && Array.isArray(payload.payments) ? payload.payments : [];
                fillLoanInstPayBannerFromLoan(loanPx);
                if (loanInstPaySubtitle) {
                    loanInstPaySubtitle.textContent =
                        'قسط شماره ' + formatToman(insPx.sequence || 0) +
                        ' از ' + formatToman(loanPx.installments_count || 0);
                }
                setLoanInstPayStripFromInstallment(insPx);
                loanInstPayResetEditMode();
                fillLoanInstPayMethodSelectAdd();
                renderLoanInstPayTable(payPx);
                var canMore = insPx.can_add_payment === true || insPx.can_add_payment === 1;
                if (loanInstPayAddBtn) loanInstPayAddBtn.disabled = !canMore;
                toggleLoanInstPayForm(false);
            }

            function resetLoanInstPayFormFields(resetForOpen) {
                if (loanInstPayMethodSelect) loanInstPayMethodSelect.value = '';
                if (loanInstPayAmountInput) loanInstPayAmountInput.value = '';
                if (loanInstPayNoteInput) loanInstPayNoteInput.value = '';
                if (!resetForOpen) {
                    if (loanInstPayRefDueInput) loanInstPayRefDueInput.value = '';
                    if (loanInstPayDepInput) loanInstPayDepInput.value = '';
                } else if (loanInstPayDepInput) {
                    loanInstPayDepInput.value = '';
                }
            }

            function toggleLoanInstPayForm(show) {
                loanInstPayFormVisible = !!show;
                if (loanInstPayFormWrap) loanInstPayFormWrap.hidden = !loanInstPayFormVisible;
                destroyLoanInstPayDatePickers();
                if (!loanInstPayFormVisible) {
                    resetLoanInstPayFormFields(false);
                    loanInstPayResetEditMode();
                    return;
                }
                loanInstPayResetEditMode();
                var insOpen = loanInstPayLastServerPayload && loanInstPayLastServerPayload.installment
                    ? loanInstPayLastServerPayload.installment
                    : {};
                resetLoanInstPayFormFields(true);
                fillLoanInstPayMethodSelectAdd();
                if (loanInstPayRefDueInput) {
                    loanInstPayRefDueInput.value = String(insOpen.due_jdate || '').trim();
                }
                setTimeout(function () {
                    initLoanInstPayBothPickers();
                }, 0);
            }

            function closeLoanInstallmentPayModal() {
                if (!loanInstallmentPayOverlay) return;
                destroyLoanInstPayDatePickers();
                loanInstallmentPayOverlay.hidden = true;
                loanInstallmentPayOverlay.setAttribute('aria-hidden', 'true');
                loanInstPayFormVisible = false;
                if (loanInstPayFormWrap) loanInstPayFormWrap.hidden = true;
                resetLoanInstPayFormFields(false);
                loanInstPayCurrentInstallmentId = null;
                loanInstPayLastServerPayload = null;
                loanInstPaySubmitting = false;
                loanInstPayResetEditMode();
                if (loanInstPaySaveBtn) loanInstPaySaveBtn.disabled = false;
                if (loanInstPayAddBtn) loanInstPayAddBtn.disabled = false;
            }

            function openLoanInstallmentPayModalInstallment(instId) {
                if (!loanInstallmentPayOverlay || !loanManageCurrentCustomerId || !loanInstActiveLoanFileId || !instId) return;
                loanInstPayCurrentInstallmentId = instId;
                loanInstallmentPayOverlay.hidden = false;
                loanInstallmentPayOverlay.setAttribute('aria-hidden', 'false');
                if (loanInstPaySubtitle) loanInstPaySubtitle.textContent = 'در حال بارگذاری…';
                if (loanInstPayStrip) loanInstPayStrip.textContent = '…';
                if (loanInstPayTbody) {
                    loanInstPayTbody.innerHTML =
                        '<tr><td colspan="7" class="loan-inst-empty" style="padding:0.65rem;text-align:center;">در حال بارگذاری...</td></tr>';
                }
                loanInstPayResetEditMode();
                if (loanInstPayMethodSelect) loanInstPayMethodSelect.innerHTML = '';
                loanInstPayFormVisible = false;
                if (loanInstPayFormWrap) loanInstPayFormWrap.hidden = true;

                fetch(customerLoanInstallmentPaymentsUrl(loanManageCurrentCustomerId, loanInstActiveLoanFileId, instId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().then(function (jp) {
                        return { ok: r.ok, json: jp };
                    });
                }).then(function (rk) {
                    if (!rk.ok) {
                        var em = rk.json && rk.json.message ? String(rk.json.message) : 'خطا در دریافت اطلاعات پرداخت.';
                        throw new Error(em);
                    }
                    applyLoanInstPayServerPayload(rk.json);
                }).catch(function (ex) {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error(ex.message || 'خطا در دریافت اطلاعات.');
                    closeLoanInstallmentPayModal();
                });
            }

            function refreshLoanManageMapFromServer(customerId) {
                var cid = customerId != null ? String(customerId) : '';
                if (!cid) return Promise.resolve();
                return fetch(custLoanBoardSummaryUrl(cid), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (rBoard) {
                    return rBoard.json().then(function (jBoard) {
                        return { ok: rBoard.ok, json: jBoard };
                    });
                }).then(function (resBoard) {
                    if (!resBoard.ok) {
                        return;
                    }
                    var jb = resBoard.json || {};
                    if (!loanManageMap[cid]) {
                        loanManageMap[cid] = { loan_files: [], loan_count: 0, loan_total_with_profit: 0, loan_remaining_installments: 0 };
                    }
                    loanManageMap[cid].loan_files = Array.isArray(jb.loan_files) ? jb.loan_files : [];
                    loanManageMap[cid].loan_count = Number(jb.loan_count != null ? jb.loan_count : loanManageMap[cid].loan_files.length);
                    loanManageMap[cid].loan_total_with_profit = Number(jb.loan_total_with_profit != null ? jb.loan_total_with_profit : 0);
                    loanManageMap[cid].loan_remaining_installments = Number(jb.loan_remaining_installments != null ? jb.loan_remaining_installments : 0);
                    if (loanManageCurrentCustomerId && String(loanManageCurrentCustomerId) === cid) {
                        renderLoanFilesForCustomer(customerId);
                    }
                }).catch(function () { /* بستن مدال؛ خطای شبکه نادیده */ });
            }

            function closeLoanInstallmentsModal() {
                closeLoanInstallmentPayModal();
                closeLoanInstallmentEditModal();
                if (!loanInstallmentsOverlay) return;
                loanInstallmentsOverlay.hidden = true;
                loanInstallmentsOverlay.setAttribute('aria-hidden', 'true');
                if (loanManageCurrentCustomerId) {
                    refreshLoanManageMapFromServer(loanManageCurrentCustomerId);
                }
            }

            function closeLoanInstantSettlementModal() {
                if (!loanInstantSettlementOverlay) return;
                loanInstantSettlementOverlay.hidden = true;
                loanInstantSettlementOverlay.setAttribute('aria-hidden', 'true');
            }

            function closeLoanDiscountModal() {
                if (!loanDiscountOverlay) return;
                loanDiscountOverlay.hidden = true;
                loanDiscountOverlay.setAttribute('aria-hidden', 'true');
                loanDiscountCurrentLoanId = null;
                loanDiscountPreviewData = null;
                loanDiscountSaving = false;
                if (loanDiscountAmountInput) loanDiscountAmountInput.value = '';
                if (loanDiscountSave) loanDiscountSave.disabled = false;
            }

            function submitLoanDiscountTotal(totalDiscountToman) {
                if (!loanManageCurrentCustomerId || !loanDiscountCurrentLoanId || loanDiscountSaving) return;
                loanDiscountSaving = true;
                if (loanDiscountSave) loanDiscountSave.disabled = true;
                fetch(customerLoanDiscountStoreUrl(loanManageCurrentCustomerId, loanDiscountCurrentLoanId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ discount_toman: totalDiscountToman })
                }).then(function (r) {
                    return r.json().then(function (json) {
                        return { ok: r.ok, json: json };
                    });
                }).then(function (res) {
                    if (!res.ok) {
                        var msg = (res.json && res.json.message) ? res.json.message : 'ثبت تخفیف ناموفق بود.';
                        throw new Error(msg);
                    }
                    var key = String(loanManageCurrentCustomerId);
                    if (loanManageMap[key] && res.json && res.json.loan_file) {
                        var lf = res.json.loan_file;
                        var rows = Array.isArray(loanManageMap[key].loan_files) ? loanManageMap[key].loan_files : [];
                        loanManageMap[key].loan_files = rows.map(function (x) {
                            return Number(x.id || 0) === Number(lf.id || loanDiscountCurrentLoanId) ? lf : x;
                        });
                        loanManageMap[key].loan_total_with_profit = loanManageMap[key].loan_files.reduce(function (s, x) {
                            return s + Number(x.total_repayable_toman || 0);
                        }, 0);
                        loanManageMap[key].loan_remaining_installments = loanManageMap[key].loan_files.reduce(function (s, x) {
                            return s + Number(x.remaining_amount_toman || 0);
                        }, 0);
                        renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                    }
                    closeLoanDiscountModal();
                    if (window.AdminSwal && AdminSwal.success) {
                        AdminSwal.success((res.json && res.json.message) ? res.json.message : 'تخفیف ثبت شد.');
                    }
                }).catch(function (err) {
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error(err.message || 'ثبت تخفیف ناموفق بود.');
                    }
                }).finally(function () {
                    loanDiscountSaving = false;
                    if (loanDiscountSave) loanDiscountSave.disabled = false;
                });
            }

            function openLoanDiscountModal(loanFileId) {
                if (!loanDiscountOverlay || !loanManageCurrentCustomerId || !loanFileId) return;
                loanDiscountCurrentLoanId = loanFileId;
                loanDiscountPreviewData = null;
                if (loanDiscountSubtitle) loanDiscountSubtitle.textContent = 'در حال بارگذاری…';
                if (loanDiscountLateAmount) loanDiscountLateAmount.textContent = '—';
                if (loanDiscountMetaHint) loanDiscountMetaHint.textContent = '';
                if (loanDiscountAmountInput) loanDiscountAmountInput.value = '';
                if (loanDiscountSave) loanDiscountSave.disabled = true;
                loanDiscountOverlay.hidden = false;
                loanDiscountOverlay.setAttribute('aria-hidden', 'false');

                fetch(customerLoanDiscountPreviewUrl(loanManageCurrentCustomerId, loanFileId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json().then(function (json) {
                        return { ok: r.ok, json: json };
                    });
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error((res.json && res.json.message) ? res.json.message : 'خطا در دریافت اطلاعات');
                    }
                    var j = res.json || {};
                    loanDiscountPreviewData = j;
                    if (loanDiscountSubtitle) {
                        loanDiscountSubtitle.textContent = 'پرونده «' + String(j.loan_code || '') + '» — ' + String(j.borrower_name || '');
                    }
                    if (loanDiscountLateAmount) {
                        loanDiscountLateAmount.textContent = formatToman(j.late_fee_so_far_toman || 0) + ' تومان';
                    }
                    var maxDisc = Number(j.max_discount_toman != null ? j.max_discount_toman : j.schedule_remaining_toman || 0);
                    var reg = Number(j.discount_registered_toman || 0);
                    var hint =
                        'مانده قسطی: ' + formatToman(j.schedule_remaining_toman || 0) +
                        ' تومان · سقف مبلغ کل تخفیف: ' + formatToman(maxDisc) +
                        ' تومان. می‌توانید مبلغ فعلی را ویرایش و دوباره ذخیره کنید.';
                    if (loanDiscountMetaHint) loanDiscountMetaHint.textContent = hint;
                    if (loanDiscountAmountInput) {
                        loanDiscountAmountInput.value = formatThousandsInputValue(String(reg));
                    }
                    if (loanDiscountSave) loanDiscountSave.disabled = false;
                }).catch(function (err) {
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error(err.message || 'خطا');
                    }
                    closeLoanDiscountModal();
                });
            }

            function renderLoanInstantSettlementPayload(data) {
                var d = data || {};
                var meta0 = d.meta || {};
                if (loanIsTitle) loanIsTitle.textContent = String(d.headline || 'مبلغ تسویه آنی');
                if (loanIsSubtitle) {
                    loanIsSubtitle.textContent = meta0.loan_code
                        ? ('پرونده «' + String(meta0.loan_code) + '»')
                        : '';
                }
                if (loanIsPrimaryLabel) loanIsPrimaryLabel.textContent = String(d.primary_label || '—');
                if (loanIsPrimaryAmount) {
                    var pam = Number(d.primary_amount_toman || 0);
                    loanIsPrimaryAmount.textContent = formatToman(pam) + ' تومان';
                    loanIsPrimaryAmount.className = 'loan-is-hero__amount' + (pam <= 0 ? ' loan-is-hero__amount--muted' : '');
                }
                if (loanIsSummary) {
                    loanIsSummary.textContent = String(d.summary || '');
                    loanIsSummary.hidden = !String(d.summary || '').trim();
                }
                var rows = Array.isArray(d.rows) ? d.rows : [];
                if (loanIsRows) {
                    if (!rows.length) {
                        loanIsRows.innerHTML = '<div class="loan-is-row"><span class="loan-is-row__left"><span class="loan-is-row__label">—</span></span><span class="loan-is-row__amt">—</span></div>';
                    } else {
                        loanIsRows.innerHTML = rows.map(function (r) {
                            var lab = String(r.label || '');
                            var amt = Number(r.amount_toman || 0);
                            var hint = String(r.hint || '').trim();
                            var emph = r.emphasis === true || r.emphasis === 1;
                            return '<div class="loan-is-row' + (emph ? ' loan-is-row--emph' : '') + '">' +
                                '<span class="loan-is-row__left">' +
                                '<span class="loan-is-row__label">' + escapeHtmlText(lab) + '</span>' +
                                (hint ? '<span class="loan-is-row__hint">' + escapeHtmlText(hint) + '</span>' : '') +
                                '</span>' +
                                '<span class="loan-is-row__amt">' + escapeHtmlText(formatToman(amt) + ' تومان') + '</span>' +
                                '</div>';
                        }).join('');
                    }
                }
                var notes = Array.isArray(d.notes) ? d.notes : [];
                if (loanIsNotes) {
                    if (!notes.length) {
                        loanIsNotes.hidden = true;
                        loanIsNotes.innerHTML = '';
                    } else {
                        loanIsNotes.hidden = false;
                        loanIsNotes.innerHTML = notes.map(function (n) {
                            return '<li>' + escapeHtmlText(String(n || '')) + '</li>';
                        }).join('');
                    }
                }
                var meta = d.meta || {};
                if (loanIsMeta) {
                    var parts = [];
                    if (d.scenario) parts.push('سناریو سیستم: ' + String(d.scenario));
                    if (meta.loan_code) parts.push('کد پرونده: ' + String(meta.loan_code));
                    if (meta.profit_method_label) parts.push('روش بهره: ' + String(meta.profit_method_label));
                    if (meta.loan_start_jdate_fa) parts.push('شروع وام: ' + String(meta.loan_start_jdate_fa));
                    if (meta.last_due_jdate_fa) parts.push('آخرین سررسید: ' + String(meta.last_due_jdate_fa));
                    if (meta.settled_jdate_fa) parts.push('تاریخ تسویه ثبت‌شده: ' + String(meta.settled_jdate_fa));
                    if (meta.paid_installments != null) parts.push('اقساط پرداخت‌شده: ' + formatToman(meta.paid_installments));
                    if (meta.unpaid_installments != null) parts.push('اقساط معوق / ناتمام: ' + formatToman(meta.unpaid_installments));
                    loanIsMeta.textContent = parts.join(' · ');
                }
            }

            function openLoanInstantSettlementModal(loanFileId) {
                if (!loanInstantSettlementOverlay || !loanManageCurrentCustomerId || !loanFileId) return;
                loanInstantSettlementOverlay.hidden = false;
                loanInstantSettlementOverlay.setAttribute('aria-hidden', 'false');
                if (loanIsTitle) loanIsTitle.textContent = 'مبلغ تسویه آنی';
                if (loanIsSubtitle) loanIsSubtitle.textContent = 'در حال بارگذاری…';
                if (loanIsPrimaryLabel) loanIsPrimaryLabel.textContent = '—';
                if (loanIsPrimaryAmount) {
                    loanIsPrimaryAmount.textContent = '—';
                    loanIsPrimaryAmount.className = 'loan-is-hero__amount';
                }
                if (loanIsSummary) {
                    loanIsSummary.textContent = '';
                    loanIsSummary.hidden = true;
                }
                if (loanIsRows) loanIsRows.innerHTML = '<div class="loan-is-row"><span class="loan-is-row__left"><span class="loan-is-row__label">در حال بارگذاری...</span></span></div>';
                if (loanIsNotes) {
                    loanIsNotes.hidden = true;
                    loanIsNotes.innerHTML = '';
                }
                if (loanIsMeta) loanIsMeta.textContent = '';
                fetch(customerLoanInstantSettlementUrl(loanManageCurrentCustomerId, loanFileId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (json) {
                    renderLoanInstantSettlementPayload(json);
                }).catch(function () {
                    if (loanIsSubtitle) loanIsSubtitle.textContent = 'خطا در دریافت اطلاعات';
                    if (loanIsRows) {
                        loanIsRows.innerHTML = '<div class="loan-is-row"><span class="loan-is-row__left"><span class="loan-is-row__label" style="color:#b91c1c">خطا در دریافت پیش‌نمایش تسویه آنی.</span></span></div>';
                    }
                });
            }

            function renderLoanInstallmentsPayload(data) {
                loanInstCachedPayload = data || null;
                var loan = loanInstCachedPayload ? (loanInstCachedPayload.loan || {}) : {};
                var rows = Array.isArray(loanInstCachedPayload && loanInstCachedPayload.installments)
                    ? loanInstCachedPayload.installments
                    : [];
                var loanLocked = !!(loan.is_settled === true || loan.is_settled === 1 || loan.is_revoked === true || loan.is_revoked === 1);
                if (loanInstSubtitle) {
                    loanInstSubtitle.textContent = 'پرونده «' + String(loan.loan_code || '') + '» — «' + String(loan.loan_type_title || '') + '»';
                }
                if (loanInstSummary) loanInstSummary.hidden = false;
                if (loanInstSumType) loanInstSumType.textContent = String(loan.loan_type_title || '—');
                if (loanInstSumAmount) loanInstSumAmount.textContent = formatToman(loan.amount_toman || 0) + ' تومان';
                if (loanInstSumStart) loanInstSumStart.textContent = String(loan.loan_start_jdate_fa || loan.loan_start_jdate || '—');
                if (loanInstSumInstallment) loanInstSumInstallment.textContent = formatToman(loan.installment_amount_toman || 0) + ' تومان';
                var instTotalCount = Number(loan.installments_count || 0);
                if (loanInstSumPaidCount) {
                    loanInstSumPaidCount.textContent = formatToman(loan.paid_installments_count || 0) +
                        (instTotalCount > 0 ? ' از ' + formatToman(instTotalCount) : '');
                }
                if (loanInstSumRemainCount) {
                    loanInstSumRemainCount.textContent = formatToman(loan.remaining_installments_count || 0) +
                        (instTotalCount > 0 ? ' از ' + formatToman(instTotalCount) : '');
                }
                if (loanInstSumRemainAmount) loanInstSumRemainAmount.textContent = formatToman(loan.remaining_amount_toman || 0) + ' تومان';
                if (loanInstSumPaidAmount) loanInstSumPaidAmount.textContent = formatToman(loan.paid_installments_amount_toman || 0) + ' تومان';
                if (loanInstSumLate) loanInstSumLate.textContent = formatToman(loan.late_penalty_toman || 0) + ' تومان';
                if (loanInstSumEarly) loanInstSumEarly.textContent = formatToman(loan.early_benefit_toman || 0) + ' تومان';
                if (!loanInstTbody) return;
                if (!rows.length) {
                    loanInstTbody.innerHTML = '<tr><td colspan="10" class="loan-inst-empty">اقساطی ثبت نشده است.</td></tr>';
                    return;
                }
                loanInstTbody.innerHTML = rows.map(function (row) {
                    var paidAmt = Number(row.paid_amount_toman || 0);
                    var paidShow = paidAmt > 0 ? formatToman(paidAmt) + ' تومان' : '—';
                    var paidLines = Array.isArray(row.payment_method_lines) ? row.payment_method_lines : [];
                    var paidMethodsHtml = paidLines.map(function (ln) {
                        var m = String((ln && ln.method_label) || '').trim();
                        var s = String((ln && ln.source_label) || '').trim();
                        if (!m) return '';
                        var block = '<div class="loan-inst-paid-method">' + escapeHtmlText(m);
                        if (s) {
                            block += '<span class="loan-inst-paid-method__src">' + escapeHtmlText(s) + '</span>';
                        }
                        block += '</div>';
                        return block;
                    }).join('');
                    if (!paidMethodsHtml) {
                        var paidMethodFallback = String(row.payment_methods_label || '').trim();
                        if (paidMethodFallback) {
                            paidMethodsHtml = '<div class="loan-inst-paid-method">' + escapeHtmlText(paidMethodFallback) + '</div>';
                        }
                    }
                    var paidCell = paidAmt > 0
                        ? ('<div>' + escapeHtmlText(paidShow) + '</div>' + paidMethodsHtml)
                        : '—';
                    var mismatchKind = String(row.amount_mismatch_kind || 'none').trim();
                    var mismatchLabel = String(row.amount_mismatch_label || '').trim();
                    var mismatchCell = '—';
                    if (mismatchKind === 'over' || mismatchKind === 'under') {
                        mismatchCell = '<span class="loan-inst-mismatch loan-inst-mismatch--' +
                            escapeHtmlAttr(mismatchKind) + '">' +
                            escapeHtmlText(mismatchLabel || '—') + '</span>';
                    }
                    var paidDate = String(row.paid_jdate_fa || row.paid_jdate || '').trim();
                    if (!paidDate) paidDate = '—';
                    var editPayload = {
                        id: row.id,
                        sequence: row.sequence,
                        amount_toman: row.amount_toman,
                        due_jdate: row.due_jdate,
                        paid_amount_toman: row.paid_amount_toman
                    };
                    var editAttr = escapeHtmlAttr(JSON.stringify(editPayload));
                    var editDisabled = loanLocked ? ' disabled' : '';
                    var editData = loanLocked ? '' : ' data-loan-inst-edit="' + editAttr + '"';
                    var payAttr = escapeHtmlAttr(JSON.stringify({ id: row.id }));
                    var payDisabled = loanLocked ? ' disabled' : '';
                    var payData = loanLocked ? '' : ' data-loan-inst-pay="' + payAttr + '"';
                    var clearPayDisabled = loanLocked || paidAmt <= 0 ? ' disabled' : '';
                    var clearPayData = loanLocked || paidAmt <= 0 ? '' : ' data-loan-inst-clear-payments="' + payAttr + '"';
                    return '<tr>' +
                        '<td>' + formatToman(row.sequence || 0) + '</td>' +
                        '<td>' + escapeHtmlText(formatToman(row.amount_toman || 0) + ' تومان') + '</td>' +
                        '<td>' + escapeHtmlText(String(row.due_jdate_fa || row.due_jdate || '—')) + '</td>' +
                        '<td>' + paidCell + '</td>' +
                        '<td>' + mismatchCell + '</td>' +
                        '<td>' + escapeHtmlText(paidDate) + '</td>' +
                        '<td>' + escapeHtmlText(String(row.early_late_label || '—')) + '</td>' +
                        '<td>' + escapeHtmlText(String(row.recorded_by || '—')) + '</td>' +
                        '<td class="loan-inst-td--sms">' + loanInstMakeSmsCell(row) + '</td>' +
                        '<td><div class="loan-inst-ops">' +
                            '<button type="button" class="loan-inst-op-btn loan-inst-op-btn--danger"' + clearPayData + clearPayDisabled +
                            ' title="حذف واریزی‌های ثبت‌شده برای این قسط (خود قسط حذف نمی‌شود)" aria-label="حذف واریزی‌های این قسط">' +
                            '<i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
                            '<button type="button" class="loan-inst-op-btn loan-inst-op-btn--edit" title="ویرایش قسط" aria-label="ویرایش قسط"' + editData + editDisabled + '><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></button>' +
                            '<button type="button" class="loan-inst-op-btn loan-inst-op-btn--pay" title="ثبت واریزی قسط" aria-label="ثبت واریزی قسط"' + payData + payDisabled + '><i class="fa-solid fa-dollar-sign" aria-hidden="true"></i></button>' +
                        '</div></td>' +
                        '</tr>';
                }).join('');
            }

            function openLoanInstallmentsModal(loanFileId) {
                if (!loanInstallmentsOverlay || !loanManageCurrentCustomerId || !loanFileId) return;
                loanInstActiveLoanFileId = loanFileId;
                loanInstallmentsOverlay.hidden = false;
                loanInstallmentsOverlay.setAttribute('aria-hidden', 'false');
                if (loanInstSubtitle) loanInstSubtitle.textContent = 'در حال بارگذاری…';
                if (loanInstSummary) loanInstSummary.hidden = true;
                if (loanInstTbody) {
                    loanInstTbody.innerHTML = '<tr><td colspan="10" class="loan-inst-empty">در حال بارگذاری...</td></tr>';
                }
                fetch(customerLoanInstallmentsUrl(loanManageCurrentCustomerId, loanFileId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    renderLoanInstallmentsPayload(data);
                }).catch(function () {
                    if (loanInstSubtitle) loanInstSubtitle.textContent = 'خطا در دریافت اقساط';
                    if (loanInstTbody) {
                        loanInstTbody.innerHTML = '<tr><td colspan="10" class="loan-inst-empty" style="color:#b91c1c">خطا در دریافت اقساط.</td></tr>';
                    }
                });
            }

            function loadLoanManageWalletSummary(customerId) {
                if (!loanManageWalletBalanceView || !customerId) return;
                loanManageWalletBalanceView.textContent = 'در حال دریافت...';
                fetch(walletShowUrl(customerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    var balance = Number((data && data.wallet ? data.wallet.balance_toman : 0) || 0);
                    loanManageWalletBalanceView.textContent = formatToman(balance) + ' تومان';
                }).catch(function () {
                    loanManageWalletBalanceView.textContent = 'نامشخص';
                });
            }

            function loanIntervalUnitLabel(unit) {
                return String(unit || '') === 'weekly' ? 'هفتگی' : 'ماهانه';
            }

            function shiftGregorianDay(ymd, delta) {
                var p = String(ymd || '').split('-');
                if (p.length !== 3) return loanSmsDefaultDate;
                var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
                if (isNaN(d.getTime())) return loanSmsDefaultDate;
                d.setDate(d.getDate() + delta);
                var y = d.getFullYear();
                var m = String(d.getMonth() + 1).padStart(2, '0');
                var day = String(d.getDate()).padStart(2, '0');
                return y + '-' + m + '-' + day;
            }

            function loanSmsStatusBadgeClass(status) {
                var s = String(status || '');
                if (s === 'pending') return 'loan-sms-badge--pending';
                if (s === 'delivered') return 'loan-sms-badge--delivered';
                return 'loan-sms-badge--undelivered';
            }

            function gregorianYmdFromUnixPick(unix) {
                if (unix == null || unix === '') return null;
                if (Array.isArray(unix) && unix.length >= 3 && typeof window.persianDate === 'function') {
                    try {
                        var pdArr = new window.persianDate([unix[0], unix[1], unix[2]]);
                        var dtArr = pdArr.toDate();
                        if (dtArr && !isNaN(dtArr.getTime())) {
                            var ya = dtArr.getFullYear();
                            var ma = String(dtArr.getMonth() + 1).padStart(2, '0');
                            var da = String(dtArr.getDate()).padStart(2, '0');
                            return ya + '-' + ma + '-' + da;
                        }
                    } catch (e0) { /* noop */ }
                }
                var n = typeof unix === 'number' ? unix : parseFloat(unix);
                if (!Number.isFinite(n)) return null;
                var ms = n < 1e12 ? n * 1000 : n;
                var d = null;
                if (typeof window.persianDate === 'function') {
                    try {
                        d = new window.persianDate(ms).toDate();
                    } catch (e1) { d = null; }
                }
                if (!d || isNaN(d.getTime())) {
                    d = new Date(ms);
                }
                if (isNaN(d.getTime())) return null;
                var y = d.getFullYear();
                var m = String(d.getMonth() + 1).padStart(2, '0');
                var day = String(d.getDate()).padStart(2, '0');
                return y + '-' + m + '-' + day;
            }

            function destroyLoanSmsDayPicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var $el = window.jQuery('#loan-sms-day-input');
                if (!$el.length) return;
                try {
                    if ($el.data('datepicker')) {
                        $el.pDatepicker('destroy');
                    }
                } catch (err) { /* noop */ }
            }

            function initLoanSmsDayPicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var el = document.getElementById('loan-sms-day-input');
                if (!el) return;
                destroyLoanSmsDayPicker();
                window.jQuery('#loan-sms-day-input').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false },
                    onSelect: function (unix) {
                        var ymd = gregorianYmdFromUnixPick(unix);
                        if (!ymd || !loanManageCurrentCustomerId) return;
                        loadLoanManageCustomerSms(loanManageCurrentCustomerId, ymd);
                    }
                });
            }

            function loadLoanManageCustomerSms(customerId, dateYmd) {
                if (!loanSmsLogsBody || !customerId) return;
                var day = dateYmd || loanSmsSelectedDate || loanSmsDefaultDate;
                loanSmsLogsBody.innerHTML = '<tr><td colspan="7" class="loan-sms-empty">در حال بارگذاری...</td></tr>';
                if (loanSmsMobileMissingNote) loanSmsMobileMissingNote.hidden = true;
                fetch(customerSmsLogsUrl(customerId, day), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    loanSmsSelectedDate = String(data.date || day);
                    if (loanSmsDayInput) {
                        loanSmsDayInput.value = String(data.date_jalali_fa || data.date_jalali || '');
                    }
                    if (loanSmsMobileMissingNote) {
                        loanSmsMobileMissingNote.hidden = !!data.has_mobile;
                    }
                    var logs = Array.isArray(data.logs) ? data.logs : [];
                    if (!logs.length) {
                        var emptyMsg = data.has_mobile ? 'پیامکی برای این روز یافت نشد.' : '—';
                        loanSmsLogsBody.innerHTML = '<tr><td colspan="7" class="loan-sms-empty">' + emptyMsg + '</td></tr>';
                        return;
                    }
                    loanSmsLogsBody.innerHTML = logs.map(function (log) {
                        var stCls = loanSmsStatusBadgeClass(log.status);
                        var msg = escapeHtmlText(String(log.message_text || ''));
                        var typeFa = String(log.type_label_fa != null && log.type_label_fa !== '' ? log.type_label_fa : (log.type || ''));
                        return '<tr>' +
                            '<td>' + escapeHtmlText(String(log.sms_panel || '')) + '</td>' +
                            '<td><span class="loan-sms-badge ' + stCls + '">' + escapeHtmlText(String(log.status_label || '')) + '</span></td>' +
                            '<td>' + escapeHtmlText(String(log.sent_at_jalali_fa || '')) + '</td>' +
                            '<td><button type="button" class="loan-sms-msg loan-sms-msg--btn" data-loan-sms-full-text="' + escapeHtmlAttr(String(log.message_text || '')) + '" title="نمایش متن کامل">' + msg + '</button></td>' +
                            '<td>' + escapeHtmlText(String(log.recipient_fa || '')) + '</td>' +
                            '<td>' + escapeHtmlText(typeFa) + '</td>' +
                            '<td>' + escapeHtmlText(String(log.cost_fa || '')) + '</td>' +
                            '</tr>';
                    }).join('');
                }).catch(function () {
                    loanSmsLogsBody.innerHTML = '<tr><td colspan="7" class="loan-sms-empty" style="color:#b91c1c;">خطا در دریافت پیامک‌ها.</td></tr>';
                });
            }

            function guaranteeReportRowHaystack(row) {
                var lines = Array.isArray(row.guarantee_detail_lines) ? row.guarantee_detail_lines : [];
                var parts = [
                    row.loan_code,
                    row.loan_type_title,
                    row.customer_full_name,
                    row.customer_national_id,
                    row.customer_mobile,
                    String(row.amount_toman || 0),
                    String(row.installment_amount_toman || 0),
                    row.guarantee_type,
                    row.guarantee_type_label
                ].concat(lines);
                return toEnglishDigits(parts.join(' ')).toLowerCase();
            }

            function filterLoanGuaranteesReportRows(rows, queryRaw) {
                var q = toEnglishDigits(String(queryRaw || '').trim()).toLowerCase();
                if (!q) return rows.slice();
                return rows.filter(function (row) {
                    return guaranteeReportRowHaystack(row).indexOf(q) !== -1;
                });
            }

            function renderLoanGuaranteesReportTableRows(reportRows) {
                if (!reportRows.length) {
                    return '<tr><td colspan="6" style="text-align:center;padding:1rem;">موردی با این جستجو یافت نشد.</td></tr>';
                }
                return reportRows.map(function (row) {
                    var loanInfo = escapeHtmlText(String(row.loan_code || '')) +
                        '<br><span style="opacity:.85;font-size:.72rem;">' + escapeHtmlText(String(row.loan_type_title || '')) + '</span>';
                    var custInfo = escapeHtmlText(String(row.customer_full_name || '')) +
                        '<br><span style="opacity:.85;font-size:.72rem;">کد ملی: ' + escapeHtmlText(String(row.customer_national_id || '—')) + '</span>' +
                        '<br><span style="opacity:.85;font-size:.72rem;">موبایل: ' + escapeHtmlText(String(row.customer_mobile || '—')) + '</span>';
                    var amt = formatToman(row.amount_toman || 0) + ' تومان';
                    var inst = formatToman(row.installment_amount_toman || 0) + ' تومان';
                    var typeLbl = escapeHtmlText(String(row.guarantee_type_label || ''));
                    var lines = Array.isArray(row.guarantee_detail_lines) ? row.guarantee_detail_lines : [];
                    var detailHtml = lines.map(function (line) {
                        return escapeHtmlText(String(line));
                    }).join('<br>');
                    return '<tr>' +
                        '<td>' + loanInfo + '</td>' +
                        '<td>' + custInfo + '</td>' +
                        '<td>' + escapeHtmlText(amt) + '</td>' +
                        '<td>' + escapeHtmlText(inst) + '</td>' +
                        '<td>' + typeLbl + '</td>' +
                        '<td style="font-size:.72rem;line-height:1.45;">' + detailHtml + '</td>' +
                        '</tr>';
                }).join('');
            }

            function updateLoanGuaranteesSummaryLine(shownCount) {
                if (!loanGuaranteesSummaryEl) return;
                var sum = loanGuaranteesSummaryCache;
                if (!sum || typeof sum !== 'object') {
                    loanGuaranteesSummaryEl.textContent = '';
                    return;
                }
                var parts = [];
                parts.push('کل: ' + formatToman(sum.total || 0));
                parts.push('سازمانی (خودم): ' + formatToman(sum.org_self || 0));
                parts.push('سازمانی (شخص دیگر): ' + formatToman(sum.org_other || 0));
                var ch = Number(sum.cheque || 0);
                parts.push('چک: ' + formatToman(ch));
                if (ch > 0) {
                    parts.push('عودت‌شده: ' + formatToman(sum.cheque_returned || 0));
                    parts.push('وصول‌شده: ' + formatToman(sum.cheque_collected || 0));
                }
                parts.push('طلا: ' + formatToman(sum.gold || 0));
                parts.push('سایر: ' + formatToman(sum.other || 0));
                var totalCached = loanGuaranteesReportCache.length;
                var q = loanGuaranteesSearchInput ? String(loanGuaranteesSearchInput.value || '').trim() : '';
                if (q !== '' && typeof shownCount === 'number' && totalCached > 0 && shownCount !== totalCached) {
                    parts.push('نمایش: ' + formatToman(shownCount) + ' از ' + formatToman(totalCached));
                }
                loanGuaranteesSummaryEl.textContent = parts.join(' · ');
            }

            function applyLoanGuaranteesReportFilter() {
                if (!loanGuaranteesReportBody) return;
                var q = loanGuaranteesSearchInput ? loanGuaranteesSearchInput.value : '';
                var filtered = filterLoanGuaranteesReportRows(loanGuaranteesReportCache, q);
                loanGuaranteesReportBody.innerHTML = renderLoanGuaranteesReportTableRows(filtered);
                updateLoanGuaranteesSummaryLine(filtered.length);
            }

            function loadLoanGuaranteesReport(customerId) {
                if (!loanGuaranteesReportBody || !customerId) return;
                loanGuaranteesReportBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:1rem;">در حال بارگذاری...</td></tr>';
                if (loanGuaranteesSummaryEl) loanGuaranteesSummaryEl.textContent = '';
                loanGuaranteesReportCache = [];
                loanGuaranteesSummaryCache = null;
                fetch(customerGuaranteesReportUrl(customerId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    loanGuaranteesReportCache = Array.isArray(data.rows) ? data.rows : [];
                    loanGuaranteesSummaryCache = data.summary && typeof data.summary === 'object' ? data.summary : null;
                    if (loanGuaranteesSearchInput) loanGuaranteesSearchInput.value = '';
                    if (!loanGuaranteesReportCache.length) {
                        loanGuaranteesReportBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:1rem;">ضمانتی برای این مشتری ثبت نشده است.</td></tr>';
                        updateLoanGuaranteesSummaryLine(0);
                        return;
                    }
                    applyLoanGuaranteesReportFilter();
                }).catch(function () {
                    loanGuaranteesReportCache = [];
                    loanGuaranteesSummaryCache = null;
                    if (loanGuaranteesSummaryEl) loanGuaranteesSummaryEl.textContent = '';
                    loanGuaranteesReportBody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#b91c1c;padding:1rem;">خطا در دریافت گزارش.</td></tr>';
                });
            }

            function refreshLoanGuaranteesReportIfTabActive() {
                var gTab = document.querySelector('.loan-tab-btn.is-active');
                if (gTab && String(gTab.getAttribute('data-loan-tab') || '') === 'guarantees' && loanManageCurrentCustomerId) {
                    loadLoanGuaranteesReport(loanManageCurrentCustomerId);
                }
            }

            function isLoanFileSettledForUi(row) {
                if (!row) return false;
                var remainingAmount = Number(row.remaining_amount_toman || 0);
                return !!(row.is_settled || (!row.is_revoked && remainingAmount <= 0));
            }

            function updateLoanFilesFilterBarVisibility(tabId) {
                if (!loanFilesFilterBar) return;
                var show = String(tabId || '') === 'files';
                loanFilesFilterBar.hidden = !show;
                loanFilesFilterBar.setAttribute('aria-hidden', show ? 'false' : 'true');
            }

            function renderLoanFilesForCustomer(customerId) {
                if (!loanFilesList || !loanFilesSummary) return;
                var meta = loanManageMap ? loanManageMap[String(customerId || '')] : null;
                var rows = (meta && Array.isArray(meta.loan_files)) ? meta.loan_files : [];
                var hideSettled = loanManageHideSettledFiles !== false;
                if (loanFilesHideSettledCheckbox) {
                    loanFilesHideSettledCheckbox.checked = hideSettled;
                }
                var settledCount = rows.filter(function (row) {
                    return isLoanFileSettledForUi(row);
                }).length;
                var visibleRows = hideSettled
                    ? rows.filter(function (row) {
                        return !isLoanFileSettledForUi(row);
                    })
                    : rows.slice();
                var count = rows.length;
                var visibleCount = visibleRows.length;
                var total = rows.reduce(function (sum, row) {
                    return sum + Number(row.total_repayable_toman || 0);
                }, 0);
                var remain = rows.reduce(function (sum, row) {
                    return sum + Number(row.remaining_amount_toman || 0);
                }, 0);
                var summaryText = count
                    ? ('تعداد پرونده: ' + formatToman(count) + ' | مجموع بازپرداخت: ' + formatToman(total) + ' تومان | مانده: ' + formatToman(remain) + ' تومان')
                    : 'برای این مشتری هنوز پرونده وام ثبت نشده است.';
                if (count && hideSettled && settledCount > 0) {
                    summaryText += ' | نمایش: ' + formatToman(visibleCount) + ' از ' + formatToman(count);
                }
                loanFilesSummary.textContent = summaryText;
                if (loanFilesFilterHint) {
                    loanFilesFilterHint.textContent = count && hideSettled && settledCount > 0
                        ? (formatToman(settledCount) + ' پرونده تسویه‌شده مخفی است')
                        : 'فیلتر پرونده‌ها';
                }
                if (!count) {
                    loanFilesList.innerHTML = '<div class="loan-files-empty">هنوز پرونده‌ای ثبت نشده است.</div>';
                    loanFilesList.classList.remove('loan-files-list--multi');
                    var activeTabBtnEmpty = document.querySelector('.loan-tab-btn.is-active');
                    if (activeTabBtnEmpty && String(activeTabBtnEmpty.getAttribute('data-loan-tab') || '') === 'guarantees' && loanManageCurrentCustomerId) {
                        loadLoanGuaranteesReport(loanManageCurrentCustomerId);
                    }
                    return;
                }
                if (!visibleCount) {
                    loanFilesList.innerHTML = '<div class="loan-files-empty">' +
                        (hideSettled && settledCount > 0
                            ? 'همهٔ پرونده‌ها تسویه‌شده‌اند؛ برای مشاهده، فیلتر «مخفی کردن پرونده‌های تسویه‌شده» را غیرفعال کنید.'
                            : 'هنوز پرونده‌ای ثبت نشده است.') +
                        '</div>';
                    loanFilesList.classList.remove('loan-files-list--multi');
                    return;
                }

                var multiLoanFiles = visibleCount >= 2;
                if (multiLoanFiles) {
                    loanFilesList.classList.add('loan-files-list--multi');
                } else {
                    loanFilesList.classList.remove('loan-files-list--multi');
                }

                loanFilesList.innerHTML = visibleRows.map(function (row, rowIndex) {
                    var paidInstallments = Number(row.paid_installments_count || 0);
                    var paidAmount = Number(row.paid_installments_amount_toman || 0);
                    var remainingAmount = Number(row.remaining_amount_toman || 0);
                    var discountAmount = Number(row.discount_amount_toman || 0);
                    /** تسویهٔ رسمی یا ماندهٔ محاسبه‌شده صفر (مثلاً بعد از پرداخت‌های ثبت‌شده روی اقساط) */
                    var settledForUi = !!(row.is_settled || (!row.is_revoked && remainingAmount <= 0));
                    var settlementText = settledForUi ? 'بلی' : 'خیر';
                    var accountStatus = remainingAmount > 0 ? 'بدهکار' : (remainingAmount < 0 ? 'بستانکار' : 'تراز');
                    var accountClass = remainingAmount > 0 ? 'loan-file-v--danger' : (remainingAmount < 0 ? 'loan-file-v--ok' : 'loan-file-v--warn');
                    var description = String(row.description || '').trim();
                    var defaultLoanSmsText =
                        'سامانه ' + (appDisplayName || '') + '\n' +
                        'مشتری گرامی ' + (loanManageCurrentCustomerName || '') + '\n' +
                        'ثبت پرونده وام جدید انجام شد.\n' +
                        'پرونده وام: ' + (row.loan_code || '') + '\n' +
                        'مبلغ وام: ' + formatToman(row.amount_toman || 0) + ' تومان\n' +
                        'مبلغ هر قسط: ' + formatToman(row.installment_amount_toman || 0) + ' تومان';

                    var cardModClass = '';
                    var ribbonHtml = '';
                    if (row.is_revoked) {
                        cardModClass = ' loan-file-card--revoked';
                        ribbonHtml = '<span class="loan-file-corner-ribbon loan-file-corner-ribbon--revoked"><span>فسخ شده</span></span>';
                    } else if (settledForUi) {
                        cardModClass = ' loan-file-card--settled';
                        ribbonHtml = '<span class="loan-file-corner-ribbon"><span>تسویه شده</span></span>';
                    }
                    var themeClass = multiLoanFiles ? (' loan-file-card--theme-' + (rowIndex % 6)) : '';

                    return '<article class="loan-file-card' + cardModClass + themeClass + '">' +
                        ribbonHtml +
                        '<div class="loan-file-grid">' +
                            '<section class="loan-file-col">' +
                                '<h3 class="loan-file-col-title">' + escapeHtmlText(row.loan_type_title || 'نوع وام') + '</h3>' +
                                '<hr class="loan-file-col-sep">' +
                                '<div class="loan-file-items">' +
                                    '<div class="loan-file-item"><span class="loan-file-k">شماره وام:</span><span class="loan-file-v">' + escapeHtmlText(row.loan_code || '—') + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">شماره فرعی:</span><span class="loan-file-v">' + escapeHtmlText(row.sub_file_number || '—') + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">تاریخ شروع:</span><span class="loan-file-v">' + escapeHtmlText(row.loan_start_jdate || '—') + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مبلغ وام:</span><span class="loan-file-v">' + formatToman(row.amount_toman || 0) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مبلغ بازپرداخت با بهره:</span><span class="loan-file-v">' + formatToman(row.total_repayable_toman || 0) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">تعداد اقساط:</span><span class="loan-file-v">' + formatToman(row.installments_count || 0) + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مبلغ هر قسط:</span><span class="loan-file-v">' + formatToman(row.installment_amount_toman || 0) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مبلغ پیش پرداخت:</span><span class="loan-file-v">' + formatToman(row.down_payment_toman || 0) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">درصد بهره:</span><span class="loan-file-v">' + String(Number(row.effective_interest_rate || 0).toFixed(2)) + '%</span></div>' +
                                '</div>' +
                            '</section>' +
                            '<section class="loan-file-col">' +
                                '<h3 class="loan-file-col-title">بازپرداخت ها</h3>' +
                                '<hr class="loan-file-col-sep">' +
                                '<div class="loan-file-items">' +
                                    (function () {
                                        var icRow = Number(row.installments_count || 0);
                                        var slotNom = row.paid_installments_slot_count != null ? Number(row.paid_installments_slot_count) : null;
                                        var showSlotNote = remainingAmount <= 0 && icRow > 0 && slotNom !== null && slotNom < icRow;
                                        return '<div class="loan-file-item"><span class="loan-file-k">تعداد دورهٔ تحت پوشش تعهد:</span><span class="loan-file-v">' + formatToman(paidInstallments) +
                                            '<span style="font-size:0.65rem;color:var(--muted);"> از ' + formatToman(icRow) + '</span></span></div>' +
                                            (showSlotNote ? '<div class="loan-file-item" style="font-size:0.68rem;line-height:1.45;"><span class="loan-file-k">رسید هر قسط به مبلغ نامی:</span><span class="loan-file-v">' +
                                                formatToman(slotNom) + '<span style="color:var(--muted);"> از ' + formatToman(icRow) +
                                                ' — ماندهٔ نامی بعضی دوره‌ها صفر نشده؛ ولی تعهد کل تسویه است.</span></span></div>' : '');
                                    })() +
                                    '<div class="loan-file-item"><span class="loan-file-k">مجموع مبلغ اقساط پرداخت شده:</span><span class="loan-file-v">' + formatToman(paidAmount) + ' تومان</span></div>' +
                                    (function () {
                                        if (row.is_revoked) {
                                            return '<div class="loan-file-item"><span class="loan-file-k">دیرکرد / زودکرد:</span><span class="loan-file-v">—</span></div>';
                                        }
                                        var lateFee = Number(row.late_fee_so_far_toman || 0);
                                        var earlyBenefit = Number(row.early_benefit_toman || 0);
                                        var lateEarlyText = 'دیرکرد: ' + formatToman(lateFee) + ' تومان · زودکرد: ' + formatToman(earlyBenefit) + ' تومان';
                                        var lateEarlyClass = lateFee > 0 ? ' loan-file-v--danger' : (earlyBenefit > 0 ? ' loan-file-v--ok' : '');
                                        return '<div class="loan-file-item"><span class="loan-file-k">دیرکرد / زودکرد:</span><span class="loan-file-v' + lateEarlyClass + '">' + lateEarlyText + '</span></div>';
                                    })() +
                                    '<div class="loan-file-item loan-file-item--stack">' +
                                        '<span class="loan-file-k">تخفیف:</span>' +
                                        '<span class="loan-file-v">' + formatToman(discountAmount) + ' تومان</span>' +
                                        '<span>' +
                                        (row.is_revoked || settledForUi
                                            ? '<button type="button" class="loan-file-btn loan-file-btn--disc" disabled>ثبت تخفیف</button>'
                                            : '<button type="button" class="loan-file-btn loan-file-btn--disc" data-loan-discount-open data-loan-id="' + String(row.id || '') + '">ثبت تخفیف</button>') +
                                        '</span>' +
                                    '</div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مبلغ باقیمانده:</span><span class="loan-file-v ' + accountClass + '">' + formatToman(remainingAmount) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">تسویه شده:</span><span class="loan-file-v">' + settlementText + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">وضعیت حساب:</span><span class="loan-file-v ' + accountClass + '">' + accountStatus + '</span></div>' +
                                '</div>' +
                            '</section>' +
                            '<section class="loan-file-col">' +
                                '<h3 class="loan-file-col-title">توضیحات</h3>' +
                                '<hr class="loan-file-col-sep">' +
                                '<p class="loan-file-desc' + (description ? '' : ' loan-file-desc--empty') + '">' + escapeHtmlText(description || 'توضیحاتی درج نشده است') + '</p>' +
                            '</section>' +
                        '</div>' +
                        '<div class="loan-file-foot">' +
                            '<div class="loan-file-actions-left">' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="چاپ دفترچه اقساط" aria-label="چاپ دفترچه اقساط" data-loan-booklet-print data-loan-id="' + String(row.id || '') + '"><i class="fa-solid fa-print" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="پیامک" data-loan-sms-id="' + String(row.id || '') + '" data-loan-default-sms="' + escapeHtmlAttr(defaultLoanSmsText) + '"><i class="fa-regular fa-message" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini loan-file-btn--danger" title="حذف" data-loan-delete-id="' + String(row.id || '') + '" data-loan-delete-code="' + escapeHtmlAttr(row.loan_code || '') + '"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="ویرایش" data-loan-edit-id="' + String(row.id || '') + '"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></button>' +
                            '</div>' +
                            '<div class="loan-file-actions-right">' +
                                '<button type="button" class="loan-file-btn" data-loan-installments-open data-loan-id="' + String(row.id || '') + '">مشاهده اقساط و پرداخت</button>' +
                                '<button type="button" class="loan-file-btn" data-loan-guarantees-id="' + String(row.id || '') + '">تضامین</button>' +
                                '<button type="button" class="loan-file-btn" data-loan-instant-settlement-open data-loan-id="' + String(row.id || '') + '">مشاهده مبلغ تسویه آنی</button>' +
                                (row.is_revoked ? '' : '<button type="button" class="loan-file-btn loan-file-btn--danger" data-loan-revoke-id="' + String(row.id || '') + '" data-loan-revoke-code="' + escapeHtmlAttr(row.loan_code || '') + '">فسخ قرارداد</button>') +
                            '</div>' +
                        '</div>' +
                    '</article>';
                }).join('');
                var activeTabBtn = document.querySelector('.loan-tab-btn.is-active');
                if (activeTabBtn && String(activeTabBtn.getAttribute('data-loan-tab') || '') === 'guarantees' && loanManageCurrentCustomerId) {
                    loadLoanGuaranteesReport(loanManageCurrentCustomerId);
                }
            }

            function syncLoanCurrentInterestView() {
                if (!loanCurrentInterestRate) return;
                var lt = selectedLoanTypeData();
                if (!lt) {
                    loanCurrentInterestRate.textContent = '—';
                    return;
                }
                var rate = Number(lt.interest_rate || 0);
                loanCurrentInterestRate.textContent = rate.toFixed(2) + '%';
                if (loanInstallmentIntervalUnitSelect && lt.installment_gap_unit) {
                    loanInstallmentIntervalUnitSelect.value = String(lt.installment_gap_unit);
                }
            }

            function syncLoanInstallmentCalculation(force) {
                if (!loanInstallmentAmountInput || !loanAmountInput || !loanInstallmentsCountInput || !loanTotalCheck) return;
                var amount = parseThousandsInput(loanAmountInput.value);
                var count = parseInt(String(loanInstallmentsCountInput.value || '0'), 10);
                if (!Number.isFinite(count) || count < 1) count = 0;
                var intervalCount = parseInt(String((loanInstallmentIntervalCountInput && loanInstallmentIntervalCountInput.value) || '0'), 10);
                if (!Number.isFinite(intervalCount) || intervalCount < 1) intervalCount = 1;
                var intervalUnit = String((loanInstallmentIntervalUnitSelect && loanInstallmentIntervalUnitSelect.value) || 'monthly');
                var downPayment = parseThousandsInput((loanDownPaymentInput && loanDownPaymentInput.value) || '');
                var lt = selectedLoanTypeData();
                var interestRate = activeInterestRatePercent();
                var profitMethod = String((lt && lt.profit_calculation_method) || 'monthly');
                var profit = loanProfitToman(amount, interestRate, profitMethod, count, intervalCount, intervalUnit);
                var allocation = allocateLoanInstallmentAmounts(amount, profit, downPayment, count, loanInstallmentRounding);
                if ((force || !loanInstallmentAutoDirty) && allocation.base > 0) {
                    loanInstallmentAmountInput.value = formatThousandsInputValue(String(allocation.base));
                }
                if ((force || !loanDownPaymentAutoDirty) && loanDownPaymentInput && String((loanInstallmentRounding && loanInstallmentRounding.remainder_target) || '') === 'down_payment') {
                    loanDownPaymentInput.value = formatThousandsInputValue(String(allocation.adjustedDownPayment || 0));
                }
                var installment = parseThousandsInput(loanInstallmentAmountInput.value);
                var sum = allocation.sum > 0 ? allocation.sum : (installment * count);
                var payableAfterDownPayment = allocation.payable > 0
                    ? allocation.payable
                    : Math.max(0, (amount + profit) - downPayment);
                loanTotalCheck.textContent = 'اصل وام: ' + formatToman(amount) + ' | نرخ بهره: ' + String(interestRate) + '% | بهره تخمینی: ' + formatToman(profit) + ' | قابل بازپرداخت: ' + formatToman(payableAfterDownPayment) + ' | جمع اقساط: ' + formatToman(sum) + loanInstallmentRoundingNote(allocation);
                loanTotalCheck.style.color = sum > payableAfterDownPayment ? '#b91c1c' : 'var(--muted)';
            }

            function openLoanCreateModal() {
                if (!loanManageCurrentCustomerId || !loanCreateOverlay || !loanCreateForm) return;
                loanFormMode = 'create';
                loanEditingFileId = null;
                loanInstallmentAutoDirty = false;
                loanDownPaymentAutoDirty = false;
                loanCreateForm.reset();
                if (loanCustomerNameInput) {
                    loanCustomerNameInput.value = loanManageCurrentCustomerName || '—';
                }
                var titleEl = document.getElementById('loan-create-title');
                if (titleEl) titleEl.textContent = 'ثبت وام';
                if (loanCreateSubtitle) {
                    loanCreateSubtitle.textContent = 'ثبت پرونده وام برای ' + (loanManageCurrentCustomerName || '');
                }
                if (loanSettledWrap) loanSettledWrap.hidden = true;
                if (loanSettledSection) loanSettledSection.hidden = true;
                if (loanCustomInterestWrap) loanCustomInterestWrap.hidden = true;
                if (loanInstallmentsCountInput) loanInstallmentsCountInput.value = '6';
                if (loanInstallmentIntervalCountInput) loanInstallmentIntervalCountInput.value = '1';
                if (loanStartJdateInput && adminTodayJdate) loanStartJdateInput.value = adminTodayJdate;
                if (loanDisbursementDueJdateInput && adminTodayJdate) loanDisbursementDueJdateInput.value = adminTodayJdate;
                var submitBtn = loanCreateForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'ثبت وام';
                resetLoanCreationOtpUi();
                syncLoanCreationOtpSectionVisibility();
                syncLoanCurrentInterestView();
                syncLoanInstallmentCalculation(true);
                loanCreateOverlay.hidden = false;
                loanCreateOverlay.setAttribute('aria-hidden', 'false');
                setTimeout(function () {
                    initLoanPickers();
                }, 80);
            }

            function openLoanEditModal(loanFileId) {
                if (!loanManageCurrentCustomerId || !loanCreateOverlay || !loanCreateForm) return;
                var meta = loanManageMap ? loanManageMap[String(loanManageCurrentCustomerId || '')] : null;
                var rows = (meta && Array.isArray(meta.loan_files)) ? meta.loan_files : [];
                var row = rows.find(function (x) { return Number(x.id || 0) === Number(loanFileId || 0); });
                if (!row) return;
                if (row.is_revoked) {
                    if (window.AdminSwal && window.AdminSwal.error) {
                        AdminSwal.error('این قرارداد فسخ شده است و قابل ویرایش نیست.');
                    }
                    return;
                }
                loanFormMode = 'edit';
                loanEditingFileId = Number(row.id || 0);
                loanInstallmentAutoDirty = false;
                loanDownPaymentAutoDirty = false;
                clearAllLoanFieldErrors();
                loanCreateForm.reset();
                var titleEl = document.getElementById('loan-create-title');
                if (titleEl) titleEl.textContent = 'ویرایش وام';
                if (loanCreateSubtitle) {
                    loanCreateSubtitle.textContent = 'ویرایش پرونده ' + (row.loan_code || '');
                }
                if (loanCustomerNameInput) loanCustomerNameInput.value = loanManageCurrentCustomerName || '—';
                if (loanStartJdateInput) loanStartJdateInput.value = row.loan_start_jdate || '';
                if (loanDisbursementDueJdateInput) loanDisbursementDueJdateInput.value = row.disbursement_due_jdate || '';
                if (loanTypeIdSelect) loanTypeIdSelect.value = String(row.loan_type_id || '');
                if (loanAmountInput) loanAmountInput.value = formatThousandsInputValue(String(row.amount_toman || ''));
                if (loanInstallmentsCountInput) loanInstallmentsCountInput.value = String(row.installments_count || 1);
                if (loanInstallmentIntervalCountInput) loanInstallmentIntervalCountInput.value = String(row.installment_interval_count || 1);
                if (loanInstallmentIntervalUnitSelect) loanInstallmentIntervalUnitSelect.value = String(row.installment_interval_unit || 'monthly');
                if (loanInstallmentAmountInput) loanInstallmentAmountInput.value = formatThousandsInputValue(String(row.installment_amount_toman || ''));
                if (loanDownPaymentInput) loanDownPaymentInput.value = formatThousandsInputValue(String(row.down_payment_toman || ''));
                var subFileEl = document.getElementById('loan-sub-file-number');
                if (subFileEl) subFileEl.value = row.sub_file_number || '';
                var descEl = document.getElementById('loan-description');
                if (descEl) descEl.value = row.description || '';

                if (loanSettledSection) loanSettledSection.hidden = false;
                if (loanIsSettledCheckbox) loanIsSettledCheckbox.checked = !!row.is_settled;
                if (loanSettledWrap) loanSettledWrap.hidden = !row.is_settled;
                if (loanSettledJdateInput) loanSettledJdateInput.value = row.settled_jdate || '';

                if (loanHasCustomInterestCheckbox) loanHasCustomInterestCheckbox.checked = !!row.has_custom_interest_rate;
                if (loanCustomInterestWrap) loanCustomInterestWrap.hidden = !row.has_custom_interest_rate;
                if (loanCustomInterestRateInput) loanCustomInterestRateInput.value = row.custom_interest_rate !== null && row.custom_interest_rate !== undefined ? String(row.custom_interest_rate) : '';
                syncLoanCurrentInterestView();
                syncLoanInstallmentCalculation(true);
                var submitBtn = loanCreateForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'ذخیره تغییرات';
                resetLoanCreationOtpUi();
                syncLoanCreationOtpSectionVisibility();
                loanCreateOverlay.hidden = false;
                loanCreateOverlay.setAttribute('aria-hidden', 'false');
                setTimeout(function () {
                    initLoanPickers();
                }, 80);
            }

            function openLoanSmsModal(loanFileId, defaultText) {
                if (!loanManageCurrentCustomerId || !loanSmsOverlay || !loanSmsForm) return;
                var meta = loanManageMap ? loanManageMap[String(loanManageCurrentCustomerId || '')] : null;
                var rows = (meta && Array.isArray(meta.loan_files)) ? meta.loan_files : [];
                var row = rows.find(function (x) { return Number(x.id || 0) === Number(loanFileId || 0); });
                if (!row) return;
                if (row.is_revoked) {
                    if (window.AdminSwal && window.AdminSwal.error) {
                        AdminSwal.error('این قرارداد فسخ شده است؛ ارسال پیامک برای این پرونده مجاز نیست.');
                    }
                    return;
                }
                loanSmsCurrentLoanId = Number(loanFileId || 0);
                loanSmsCurrentLoanData = row;
                loanSmsForm.reset();
                if (loanSmsSubtitle) {
                    loanSmsSubtitle.textContent = 'گیرنده: ' + (loanManageCurrentCustomerName || '') + ' - ' + (loanManageCurrentCustomerMobile || '') + ' | پرونده: ' + (row.loan_code || '—');
                }
                if (loanSmsText) {
                    loanSmsText.value = String(defaultText || '').trim() !== '' ? String(defaultText || '') : (
                        'سامانه ' + (appDisplayName || '') + '\n' +
                        'مشتری گرامی ' + (loanManageCurrentCustomerName || '') + '\n' +
                        'ثبت پرونده وام جدید انجام شد.\n' +
                        'پرونده وام: ' + (row.loan_code || '') + '\n' +
                        'مبلغ وام: ' + formatToman(row.amount_toman || 0) + ' تومان\n' +
                        'مبلغ هر قسط: ' + formatToman(row.installment_amount_toman || 0) + ' تومان'
                    );
                }
                loanSmsOverlay.hidden = false;
                loanSmsOverlay.setAttribute('aria-hidden', 'false');
            }

            function closeLoanSmsModal() {
                if (!loanSmsOverlay) return;
                loanSmsOverlay.hidden = true;
                loanSmsOverlay.setAttribute('aria-hidden', 'true');
                loanSmsCurrentLoanId = null;
                loanSmsCurrentLoanData = null;
            }

            function setGoldOptionSelectionStyles() {
                goldItemOptions.forEach(function (label) {
                    var input = label.querySelector('input[name="gold_item_code"]');
                    var checked = !!(input && input.checked);
                    label.classList.toggle('is-active', checked);
                });
            }

            function syncGoldFieldsByOption() {
                var selectedInput = loanGuaranteeForm ? loanGuaranteeForm.querySelector('input[name="gold_item_code"]:checked') : null;
                var selectedCode = String((selectedInput && selectedInput.value) || 'broken_gold');
                var isBrokenGold = selectedCode === 'broken_gold';
                var weightInput = loanGuaranteeForm ? loanGuaranteeForm.elements['gold_weight_gram'] : null;
                var quantityInput = loanGuaranteeForm ? loanGuaranteeForm.elements['gold_quantity'] : null;

                if (goldWeightWrap) goldWeightWrap.hidden = !isBrokenGold;
                if (goldQuantityWrap) goldQuantityWrap.hidden = isBrokenGold;
                if (weightInput) {
                    weightInput.disabled = !isBrokenGold;
                    if (!isBrokenGold) weightInput.value = '';
                }
                if (quantityInput) {
                    quantityInput.disabled = isBrokenGold;
                    if (isBrokenGold) quantityInput.value = '';
                }
                setGoldOptionSelectionStyles();
            }

            function destroyLoanGuaranteeChequeDatePicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var $el = window.jQuery('#loan-guarantee-cheque-due');
                if (!$el.length) return;
                try {
                    if ($el.data('datepicker')) {
                        $el.pDatepicker('destroy');
                    }
                } catch (err) { /* noop */ }
            }

            function initLoanGuaranteeChequeDatePicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var el = document.getElementById('loan-guarantee-cheque-due');
                if (!el || el.disabled) return;
                destroyLoanGuaranteeChequeDatePicker();
                window.jQuery('#loan-guarantee-cheque-due').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
            }

            function setGuaranteeType(type, skipOrgHydrate) {
                destroyLoanGuaranteeChequeDatePicker();
                var activeType = String(type || 'org_self');
                var isOrgTab = activeType === 'org_self' || activeType === 'org_other';
                if (!isOrgTab) {
                    destroyLoanOrgSelect2();
                }
                if (loanGuaranteeTypeInput) {
                    loanGuaranteeTypeInput.value = activeType;
                }
                loanGuaranteeTypeTabs.forEach(function (btn) {
                    btn.classList.toggle('is-active', String(btn.getAttribute('data-guarantee-type') || '') === activeType);
                });
                loanGuaranteeTypeSections.forEach(function (sec) {
                    var isActive = String(sec.getAttribute('data-guarantee-section') || '') === activeType;
                    sec.hidden = !isActive;
                    sec.querySelectorAll('input, textarea, select').forEach(function (field) {
                        field.disabled = !isActive;
                    });
                });
                var orgShared = document.getElementById('loan-guarantee-org-shared');
                if (orgShared) {
                    orgShared.hidden = !isOrgTab;
                    orgShared.querySelectorAll('input, textarea, select, button').forEach(function (field) {
                        field.disabled = !isOrgTab;
                    });
                }
                if (activeType === 'gold') {
                    syncGoldFieldsByOption();
                }
                if (activeType === 'cheque') {
                    setTimeout(function () { initLoanGuaranteeChequeDatePicker(); }, 0);
                }
                if (isOrgTab) {
                    setTimeout(function () {
                        if (!skipOrgHydrate) {
                            loadOrganizationsIntoSelect(null, function () {
                                initLoanOrgSelect2();
                            });
                        } else {
                            initLoanOrgSelect2();
                        }
                    }, 0);
                }
                if (activeType !== 'org_other') {
                    loanGuaranteeGuarantorOtpLocked = false;
                    loanGuaranteeGuarantorOtpPhoneSnapshot = '';
                    resetGuarantorOtpUi();
                    updateGuarantorOtpButtonChrome();
                } else {
                    syncGuarantorOtpLockFromFormMeta();
                }
                syncGuaranteeReturnSectionVisibility(activeType);
            }

            function resetGuaranteeFilePreview() {
                loanGuaranteeCurrentDownloadUrl = '';
                if (loanGuaranteePreview) loanGuaranteePreview.innerHTML = 'بدون فایل';
                if (loanGuaranteeFileName) loanGuaranteeFileName.textContent = 'فایلی انتخاب نشده است.';
                if (loanGuaranteeFileDownloadBtn) loanGuaranteeFileDownloadBtn.disabled = true;
                if (loanGuaranteePreviewDownloadLink) {
                    loanGuaranteePreviewDownloadLink.hidden = true;
                    loanGuaranteePreviewDownloadLink.setAttribute('href', '#');
                }
                loanGuaranteeRemoveExistingAttachment = false;
            }

            function setGuaranteeFilePreviewFromFile(file) {
                if (!file) {
                    resetGuaranteeFilePreview();
                    return;
                }
                loanGuaranteeRemoveExistingAttachment = false;
                if (loanGuaranteeFileName) loanGuaranteeFileName.textContent = file.name || 'فایل انتخاب شد';
                if (loanGuaranteeFileDownloadBtn) loanGuaranteeFileDownloadBtn.disabled = true;
                loanGuaranteeCurrentDownloadUrl = '';
                if (loanGuaranteePreviewDownloadLink) {
                    loanGuaranteePreviewDownloadLink.hidden = true;
                    loanGuaranteePreviewDownloadLink.setAttribute('href', '#');
                }
                if (!loanGuaranteePreview) return;
                var isImage = /^image\//.test(String(file.type || ''));
                if (!isImage) {
                    loanGuaranteePreview.innerHTML = '<i class="fa-regular fa-file-pdf" style="font-size:1.35rem;opacity:.78"></i>';
                    return;
                }
                var reader = new FileReader();
                reader.onload = function () {
                    loanGuaranteePreview.innerHTML = '<img src="' + String(reader.result || '') + '" alt="preview">';
                };
                reader.readAsDataURL(file);
            }

            function setGuaranteeExistingAttachment(previewUrl, downloadUrl, name) {
                var currentPreviewUrl = String(previewUrl || '');
                loanGuaranteeCurrentDownloadUrl = String(downloadUrl || currentPreviewUrl || '');
                if (loanGuaranteeFileName) loanGuaranteeFileName.textContent = name || 'فایل پیوست';
                if (loanGuaranteeFileDownloadBtn) loanGuaranteeFileDownloadBtn.disabled = !loanGuaranteeCurrentDownloadUrl;
                if (loanGuaranteePreviewDownloadLink) {
                    loanGuaranteePreviewDownloadLink.hidden = !loanGuaranteeCurrentDownloadUrl;
                    loanGuaranteePreviewDownloadLink.setAttribute('href', loanGuaranteeCurrentDownloadUrl || '#');
                }
                if (!loanGuaranteePreview) return;
                if (!currentPreviewUrl) {
                    loanGuaranteePreview.innerHTML = 'بدون فایل';
                    return;
                }
                var fileName = String(name || '').trim();
                var isImage = /\.(png|jpe?g|webp)$/i.test(fileName);
                loanGuaranteePreview.innerHTML = isImage
                    ? ('<img src="' + escapeHtmlAttr(currentPreviewUrl) + '" alt="preview">')
                    : '<i class="fa-regular fa-file-pdf" style="font-size:1.35rem;opacity:.78"></i>';
            }

            function openGuaranteeFormForCreate() {
                if (!loanGuaranteeFormWrap || !loanGuaranteeForm) return;
                loanGuaranteeFormMode = 'create';
                loanGuaranteeEditingId = null;
                loanGuaranteeLoadedMeta = null;
                loanGuaranteeGuarantorOtpLocked = false;
                loanGuaranteeGuarantorOtpPhoneSnapshot = '';
                loanGuaranteeForm.reset();
                resetGuarantorOtpUi();
                resetGuaranteeReturnUi();
                setGuaranteeType('org_self');
                resetGuaranteeFilePreview();
                loanGuaranteeRemoveExistingAttachment = false;
                var submitBtn = document.getElementById('loan-guarantee-submit');
                if (submitBtn) submitBtn.textContent = 'ذخیره ضمانت';
                loanGuaranteeFormWrap.hidden = false;
            }

            function openGuaranteeFormForEdit(guaranteeData) {
                if (!loanGuaranteeFormWrap || !loanGuaranteeForm || !guaranteeData) return;
                loanGuaranteeFormMode = 'edit';
                loanGuaranteeEditingId = Number(guaranteeData.id || 0);
                loanGuaranteeForm.reset();
                resetGuarantorOtpUi();
                resetGuaranteeReturnUi();
                var gtype = String(guaranteeData.type || 'other');
                var meta = guaranteeData.meta && typeof guaranteeData.meta === 'object' ? guaranteeData.meta : {};
                loanGuaranteeLoadedMeta = Object.assign({}, meta);
                setGuaranteeType(gtype, gtype === 'org_other' || gtype === 'org_self');
                resetGuaranteeFilePreview();
                loanGuaranteeRemoveExistingAttachment = false;
                if (loanGuaranteeTypeInput) loanGuaranteeTypeInput.value = gtype;
                var sharedDescription = guaranteeData.description || '';
                var goldDescEl = document.getElementById('loan-guarantee-gold-desc');
                var otherDescEl = document.getElementById('loan-guarantee-other-desc');
                var orgOtherDescEl = document.getElementById('loan-guarantee-org-other-desc');
                var orgSelfDescEl = document.getElementById('loan-guarantee-org-self-desc');
                if (goldDescEl) goldDescEl.value = sharedDescription;
                if (otherDescEl) otherDescEl.value = sharedDescription;
                if (orgOtherDescEl) orgOtherDescEl.value = sharedDescription;
                if (orgSelfDescEl) orgSelfDescEl.value = sharedDescription;
                if (loanGuaranteeForm.elements['employee_no']) loanGuaranteeForm.elements['employee_no'].value = meta.employee_no || '';
                if (loanGuaranteeForm.elements['guarantor_name']) loanGuaranteeForm.elements['guarantor_name'].value = meta.guarantor_name || '';
                if (loanGuaranteeForm.elements['guarantor_national_id']) loanGuaranteeForm.elements['guarantor_national_id'].value = meta.guarantor_national_id || '';
                if (loanGuaranteeForm.elements['guarantor_phone']) loanGuaranteeForm.elements['guarantor_phone'].value = meta.guarantor_phone || '';
                if (loanGuaranteeForm.elements['guarantor_employee_no']) loanGuaranteeForm.elements['guarantor_employee_no'].value = meta.guarantor_employee_no || '';
                if (loanGuaranteeForm.elements['cheque_owner_name']) loanGuaranteeForm.elements['cheque_owner_name'].value = meta.cheque_owner_name || '';
                if (loanGuaranteeForm.elements['cheque_owner_national_id']) loanGuaranteeForm.elements['cheque_owner_national_id'].value = meta.cheque_owner_national_id || '';
                if (loanGuaranteeForm.elements['cheque_owner_mobile']) loanGuaranteeForm.elements['cheque_owner_mobile'].value = meta.cheque_owner_mobile || '';
                if (loanGuaranteeForm.elements['cheque_serial']) loanGuaranteeForm.elements['cheque_serial'].value = meta.cheque_serial || '';
                if (loanGuaranteeForm.elements['cheque_sayadi']) loanGuaranteeForm.elements['cheque_sayadi'].value = meta.cheque_sayadi || '';
                if (loanGuaranteeForm.elements['cheque_due_jdate']) loanGuaranteeForm.elements['cheque_due_jdate'].value = meta.cheque_due_jdate || '';
                var chequeCollectedCb = document.getElementById('loan-guarantee-cheque-collected');
                if (chequeCollectedCb) chequeCollectedCb.checked = !!meta.cheque_collected;
                populateGuaranteeReturnFromData(guaranteeData);
                var selectedGoldCode = String(meta.gold_item_code || '').trim();
                if (!selectedGoldCode && meta.gold_item_type) {
                    selectedGoldCode = String(meta.gold_item_type).indexOf('شکن') !== -1 ? 'broken_gold' : 'full_coin';
                }
                if (!selectedGoldCode) selectedGoldCode = 'broken_gold';
                var selectedGoldInput = loanGuaranteeForm.querySelector('input[name="gold_item_code"][value="' + selectedGoldCode + '"]');
                if (selectedGoldInput) {
                    selectedGoldInput.checked = true;
                }
                if (loanGuaranteeForm.elements['gold_weight_gram']) loanGuaranteeForm.elements['gold_weight_gram'].value = meta.gold_weight_gram || '';
                if (loanGuaranteeForm.elements['gold_quantity']) loanGuaranteeForm.elements['gold_quantity'].value = meta.gold_quantity || '';
                if (loanGuaranteeForm.elements['gold_rate_toman']) loanGuaranteeForm.elements['gold_rate_toman'].value = formatThousandsInputValue(String(meta.gold_rate_toman || ''));
                syncGoldFieldsByOption();

                setGuaranteeExistingAttachment(
                    guaranteeData.attachment_preview_url || guaranteeData.attachment_url || '',
                    guaranteeData.attachment_download_url || guaranteeData.attachment_url || '',
                    guaranteeData.attachment_name || ''
                );
                var submitBtn = document.getElementById('loan-guarantee-submit');
                if (submitBtn) submitBtn.textContent = 'ذخیره تغییرات ضمانت';
                loanGuaranteeFormWrap.hidden = false;
                if (gtype === 'org_other' || gtype === 'org_self') {
                    var selId = meta.organization_id != null && meta.organization_id !== ''
                        ? meta.organization_id
                        : null;
                    loadOrganizationsIntoSelect(selId, function () {
                        initLoanOrgSelect2();
                    });
                }
                if (gtype === 'cheque') {
                    setTimeout(function () { initLoanGuaranteeChequeDatePicker(); }, 60);
                }
                if (gtype === 'org_other') {
                    syncGuarantorOtpLockFromFormMeta();
                }
            }

            function openLoanGuaranteeModal(loanFileId) {
                if (!loanManageCurrentCustomerId || !loanGuaranteeOverlay) return;
                loanGuaranteeCurrentLoanId = Number(loanFileId || 0);
                loanGuaranteeFormMode = 'create';
                loanGuaranteeEditingId = null;
                loanGuaranteeFormWrap.hidden = true;
                if (loanGuaranteeSubtitle) {
                    loanGuaranteeSubtitle.textContent = 'پرونده: #' + String(loanFileId || '—') + ' | مشتری: ' + (loanManageCurrentCustomerName || '');
                }
                if (loanGuaranteeList) {
                    loanGuaranteeList.innerHTML = '<div class="loan-guarantee-empty">در حال بارگذاری...</div>';
                }
                loanGuaranteeOverlay.hidden = false;
                loanGuaranteeOverlay.setAttribute('aria-hidden', 'false');

                fetch(customerLoanGuaranteesUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    var rows = Array.isArray(data.guarantees) ? data.guarantees : [];
                    if (!loanGuaranteeList) return;
                    if (!rows.length) {
                        loanGuaranteeList.innerHTML = '<div class="loan-guarantee-empty">هنوز ضمانتی ثبت نشده است.</div>';
                        return;
                    }
                    loanGuaranteeList.innerHTML = rows.map(function (row) {
                        var desc = String(row.description || '').trim();
                        var metaText = [];
                        if (row.meta && typeof row.meta === 'object') {
                            if (String(row.type || '') === 'org_self') {
                                var orgSelfLbl = row.meta.organization_name || row.meta.org_name || '';
                                if (orgSelfLbl) metaText.push('سازمان: ' + orgSelfLbl);
                                if (row.meta.employee_no) metaText.push('شماره پرسنلی: ' + row.meta.employee_no);
                            }
                            if (String(row.type || '') === 'org_other') {
                                var orgLbl = row.meta.organization_name || row.meta.org_name || '';
                                if (orgLbl) metaText.push('سازمان: ' + orgLbl);
                                if (row.meta.guarantor_name) metaText.push('ضامن: ' + row.meta.guarantor_name);
                                if (row.meta.guarantor_employee_no) metaText.push('شماره پرسنلی: ' + row.meta.guarantor_employee_no);
                                if (row.meta.guarantor_phone) metaText.push('موبایل ضامن: ' + row.meta.guarantor_phone);
                                metaText.push('موبایل ضامن احراز شده: ' + (row.meta.guarantor_mobile_verified ? 'بله' : 'خیر'));
                            }
                            if (row.meta.org_name && String(row.type || '') !== 'org_other' && String(row.type || '') !== 'org_self') metaText.push('سازمان: ' + row.meta.org_name);
                            if (row.meta.guarantor_name && String(row.type || '') !== 'org_other') metaText.push('ضامن: ' + row.meta.guarantor_name);
                            if (row.meta.cheque_owner_name) metaText.push('صاحب چک: ' + row.meta.cheque_owner_name);
                            if (row.meta.cheque_owner_mobile) metaText.push('موبایل: ' + row.meta.cheque_owner_mobile);
                            if (row.meta.cheque_serial) metaText.push('شماره چک: ' + row.meta.cheque_serial);
                            if (row.meta.cheque_sayadi) metaText.push('صیادی: ' + row.meta.cheque_sayadi);
                            if (row.meta.cheque_due_jdate) metaText.push('تاریخ چک: ' + row.meta.cheque_due_jdate);
                            if (String(row.type || '') === 'cheque') {
                                metaText.push('وصول شده؟ ' + (row.meta.cheque_collected ? 'بله' : 'خیر'));
                                metaText.push('عودت شده؟ ' + (row.meta.cheque_returned ? 'بله' : 'خیر'));
                            }
                            if (String(row.type || '') === 'gold' || String(row.type || '') === 'other') {
                                metaText.push('عودت شده؟ ' + (row.meta.returned ? 'بله' : 'خیر'));
                            }
                            if (row.returned_at) metaText.push('تاریخ عودت: ' + row.returned_at);
                            if (row.meta.gold_item_label || row.meta.gold_item_type) metaText.push('نوع طلا: ' + (row.meta.gold_item_label || row.meta.gold_item_type));
                            if (row.meta.gold_weight_gram) metaText.push('وزن: ' + row.meta.gold_weight_gram + ' گرم');
                            if (row.meta.gold_quantity) metaText.push('تعداد: ' + row.meta.gold_quantity);
                            if (row.meta.gold_rate_toman) metaText.push('نرخ: ' + formatToman(row.meta.gold_rate_toman) + ' تومان');
                            var guaranteeAmtType = String(row.type || '');
                            if (row.meta.amount_toman && (guaranteeAmtType === 'gold' || guaranteeAmtType === 'other')) {
                                metaText.push('مبلغ: ' + formatToman(row.meta.amount_toman) + ' تومان');
                            }
                        }
                        var attachmentName = String(row.attachment_name || '');
                        var attachmentPreviewUrl = String(row.attachment_preview_url || row.attachment_url || '');
                        var attachmentDownloadUrl = String(row.attachment_download_url || row.attachment_url || '');
                        var hasAttachment = !!attachmentDownloadUrl;
                        var returnDocUrl = String(row.return_document_download_url || '');
                        var isImageAttachment = /\.(png|jpe?g|webp)$/i.test(attachmentName);
                        return '<div class="loan-guarantee-card">' +
                            '<div class="loan-guarantee-card__main">' +
                                '<h4 class="loan-guarantee-title">' + escapeHtmlText(row.type_label || row.type || 'ضمانت') + '</h4>' +
                                '<div class="loan-guarantee-meta">' +
                                    (desc ? ('توضیح: ' + escapeHtmlText(desc) + '<br>') : '') +
                                    (metaText.length ? escapeHtmlText(metaText.join(' | ')) + '<br>' : '') +
                                    (row.created_at ? ('ثبت: ' + escapeHtmlText(row.created_at)) : '') +
                                '</div>' +
                            '</div>' +
                            '<div class="loan-guarantee-card__aside">' +
                                (hasAttachment && isImageAttachment ? '<a class="loan-guarantee-thumb-link" href="' + escapeHtmlAttr(attachmentPreviewUrl) + '" target="_blank" rel="noopener" title="پیش‌نمایش تصویر"><img class="loan-guarantee-thumb" src="' + escapeHtmlAttr(attachmentPreviewUrl) + '" alt="preview"></a>' : '') +
                                '<div class="loan-guarantee-actions">' +
                                    (hasAttachment ? '<a class="loan-file-btn" href="' + escapeHtmlAttr(attachmentDownloadUrl) + '" target="_blank" rel="noopener">دانلود</a>' : '') +
                                    (returnDocUrl ? '<a class="loan-file-btn" href="' + escapeHtmlAttr(returnDocUrl) + '" target="_blank" rel="noopener">مستند عودت</a>' : '') +
                                    '<button type="button" class="loan-file-btn" data-guarantee-edit="' + escapeHtmlAttr(JSON.stringify(row)) + '">ویرایش</button>' +
                                    '<button type="button" class="loan-file-btn loan-file-btn--danger" data-guarantee-delete-id="' + String(row.id || '') + '">حذف</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                }).catch(function () {
                    if (loanGuaranteeList) {
                        loanGuaranteeList.innerHTML = '<div class="loan-guarantee-empty">خطا در دریافت تضامین.</div>';
                    }
                });
            }

            function closeLoanGuaranteeModal() {
                if (!loanGuaranteeOverlay) return;
                loanGuaranteeOverlay.hidden = true;
                loanGuaranteeOverlay.setAttribute('aria-hidden', 'true');
                loanGuaranteeCurrentLoanId = null;
                loanGuaranteeFormMode = 'create';
                loanGuaranteeEditingId = null;
            }

            function closeLoanCreateModal() {
                if (!loanCreateOverlay) return;
                loanCreateOverlay.hidden = true;
                loanCreateOverlay.setAttribute('aria-hidden', 'true');
                resetLoanCreationOtpUi();
            }

            function openLoanManageModal(customerId, customerName, customerMobile) {
                loanManageCurrentCustomerId = customerId || null;
                loanManageCurrentCustomerName = customerName || '';
                loanManageCurrentCustomerMobile = customerMobile || '';
                loanSmsSelectedDate = loanSmsDefaultDate;
                if (loanManageCustomerNameView) loanManageCustomerNameView.textContent = loanManageCurrentCustomerName || '—';
                if (loanManageCustomerMobileView) loanManageCustomerMobileView.textContent = loanManageCurrentCustomerMobile || '—';
                if (loanManageCreditStatusView) {
                    loanManageCreditStatusView.textContent = 'در حال ارزیابی';
                    loanManageCreditStatusView.classList.remove('loan-manage-pill-value--good', 'loan-manage-pill-value--normal', 'loan-manage-pill-value--weak');
                }
                loadLoanManageWalletSummary(loanManageCurrentCustomerId);
                renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                setLoanTab('files');
                if (loanManageOverlay) {
                    loanManageOverlay.hidden = false;
                    loanManageOverlay.setAttribute('aria-hidden', 'false');
                }
            }

            function setLoanTab(tabId) {
                updateLoanFilesFilterBarVisibility(tabId);
                loanTabButtons.forEach(function (btn) {
                    var active = btn.getAttribute('data-loan-tab') === tabId;
                    btn.classList.toggle('is-active', active);
                });
                loanTabPanels.forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-loan-panel') !== tabId;
                });
                if (tabId === 'guarantees' && loanManageCurrentCustomerId) {
                    loadLoanGuaranteesReport(loanManageCurrentCustomerId);
                }
                if (tabId === 'sms' && loanManageCurrentCustomerId) {
                    initLoanSmsDayPicker();
                    loadLoanManageCustomerSms(loanManageCurrentCustomerId, loanSmsSelectedDate);
                }
                if (tabId === 'requests' && loanManageCurrentCustomerId && loanManageLrqIframe && loanManageLrqEmbedTmpl && loanManageLrqEmbedTmpl.indexOf('__CUSTOMER_ID__') !== -1) {
                    var nextSrc = loanManageLrqEmbedTmpl.replace('__CUSTOMER_ID__', String(loanManageCurrentCustomerId));
                    setLoanManageLrqLoading(true);
                    if (loanManageLrqIframe.src === nextSrc) {
                        setLoanManageLrqLoading(false);
                    } else {
                        loanManageLrqIframe.onload = function () {
                            setLoanManageLrqLoading(false);
                            loanManageLrqIframe.onload = null;
                            loanManageLrqIframe.onerror = null;
                        };
                        loanManageLrqIframe.onerror = function () {
                            setLoanManageLrqLoading(false);
                            loanManageLrqIframe.onload = null;
                            loanManageLrqIframe.onerror = null;
                        };
                        loanManageLrqIframe.src = nextSrc;
                    }
                } else if (tabId === 'requests') {
                    setLoanManageLrqLoading(false);
                }
                if (tabId === 'transactions' && loanManageCurrentCustomerId && loanManageCtxIframe && loanManageCtxEmbedTmpl && loanManageCtxEmbedTmpl.indexOf('__CUSTOMER_ID__') !== -1) {
                    var ctxNextSrc = loanManageCtxEmbedTmpl.replace('__CUSTOMER_ID__', String(loanManageCurrentCustomerId));
                    setLoanManageCtxLoading(true);
                    if (loanManageCtxIframe.src === ctxNextSrc) {
                        setLoanManageCtxLoading(false);
                    } else {
                        loanManageCtxIframe.onload = function () {
                            setLoanManageCtxLoading(false);
                            loanManageCtxIframe.onload = null;
                            loanManageCtxIframe.onerror = null;
                        };
                        loanManageCtxIframe.onerror = function () {
                            setLoanManageCtxLoading(false);
                            loanManageCtxIframe.onload = null;
                            loanManageCtxIframe.onerror = null;
                        };
                        loanManageCtxIframe.src = ctxNextSrc;
                    }
                } else if (tabId === 'transactions') {
                    setLoanManageCtxLoading(false);
                }
                if (tabId === 'tickets' && loanManageCurrentCustomerId && loanManageTicketsIframe && loanManageTicketsEmbedTmpl && loanManageTicketsEmbedTmpl.indexOf('__CUSTOMER_ID__') !== -1) {
                    var ticketsNextSrc = loanManageTicketsEmbedTmpl.replace('__CUSTOMER_ID__', String(loanManageCurrentCustomerId));
                    setLoanManageTicketsLoading(true);
                    if (loanManageTicketsIframe.src === ticketsNextSrc) {
                        setLoanManageTicketsLoading(false);
                    } else {
                        loanManageTicketsIframe.onload = function () {
                            setLoanManageTicketsLoading(false);
                            loanManageTicketsIframe.onload = null;
                            loanManageTicketsIframe.onerror = null;
                        };
                        loanManageTicketsIframe.onerror = function () {
                            setLoanManageTicketsLoading(false);
                            loanManageTicketsIframe.onload = null;
                            loanManageTicketsIframe.onerror = null;
                        };
                        loanManageTicketsIframe.src = ticketsNextSrc;
                    }
                } else if (tabId === 'tickets') {
                    setLoanManageTicketsLoading(false);
                }
            }

            function closeLoanManageModal() {
                if (!loanManageOverlay) return;
                destroyLoanSmsDayPicker();
                setLoanManageLrqLoading(false);
                setLoanManageCtxLoading(false);
                setLoanManageTicketsLoading(false);
                if (loanManageLrqIframe) {
                    loanManageLrqIframe.onload = null;
                    loanManageLrqIframe.onerror = null;
                    loanManageLrqIframe.src = 'about:blank';
                }
                if (loanManageCtxIframe) {
                    loanManageCtxIframe.onload = null;
                    loanManageCtxIframe.onerror = null;
                    loanManageCtxIframe.src = 'about:blank';
                }
                if (loanManageTicketsIframe) {
                    loanManageTicketsIframe.onload = null;
                    loanManageTicketsIframe.onerror = null;
                    loanManageTicketsIframe.src = 'about:blank';
                }
                loanManageOverlay.hidden = true;
                loanManageOverlay.setAttribute('aria-hidden', 'true');
                closeLoanInstallmentsModal();
            }

            function syncUsername() {
                if (!userPrev) return;
                var u = usernameFromMobile(mobile ? mobile.value : '');
                userPrev.value = u || '';
            }

            if (mobile) {
                mobile.addEventListener('input', syncUsername);
                syncUsername();
            }

            function openModal() {
                if (!overlay) return;
                overlay.hidden = false;
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('app-settings-open');
                setTimeout(initPickers, 80);
            }

            function closeModal() {
                if (!overlay) return;
                overlay.hidden = true;
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('app-settings-open');
            }

            if (openBtn) openBtn.addEventListener('click', function () {
                openCreateModal();
            });

            if (custImportOpenBtn) custImportOpenBtn.addEventListener('click', openCustImportModal);
            if (custImportCloseBtn) custImportCloseBtn.addEventListener('click', closeCustImportModal);
            if (custImportDismissBtn) custImportDismissBtn.addEventListener('click', closeCustImportModal);
            if (custImportOverlay) {
                custImportOverlay.addEventListener('click', function (evIm) {
                    if (evIm.target === custImportOverlay) closeCustImportModal();
                });
            }
            if (custImportForm && custImportFileInput && custImportSubmitBtn) {
                custImportForm.addEventListener('submit', function (evIm) {
                    evIm.preventDefault();
                    if (custImportSubmitting) return;
                    if (!custImportFileInput.files || custImportFileInput.files.length === 0) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ابتدا فایل را انتخاب کنید.');
                        return;
                    }
                    custImportSubmitting = true;
                    custImportSubmitBtn.disabled = true;
                    custImportSubmitBtn.textContent = 'در حال پردازش...';
                    var fdIm = new FormData(custImportForm);
                    var importScheduleReload = false;
                    fetch(customersImportExcelUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: fdIm
                    }).then(function (rIm) {
                        return rIm.json().then(function (jIm) {
                            return { ok: rIm.ok, status: rIm.status, json: jIm };
                        });
                    }).then(function (resIm) {
                        var jIx = resIm.json || {};
                        var msgIx = String(jIx.message || '');
                        var createdIx = Number(jIx.created_count || 0);
                        var failsIx = Array.isArray(jIx.failures) ? jIx.failures : [];
                        if (!msgIx && resIm.status >= 400) msgIx = 'درخواست ناموفق بود.';
                        var detailIx = '';
                        if (failsIx.length > 0) {
                            detailIx = failsIx.slice(0, 14).map(function (fx) {
                                return 'سطر ' + String(fx.row || '') + ': ' + (Array.isArray(fx.errors) ? fx.errors.join(' — ') : '');
                            }).join('\n');
                            if (failsIx.length > 14) detailIx += '\n…';
                        }
                        var combinedIx = detailIx !== '' ? (msgIx + '\n\n' + detailIx) : msgIx;
                        if (createdIx > 0 && failsIx.length === 0) {
                            importScheduleReload = true;
                            closeCustImportModal();
                            var pOk = (window.AdminSwal && AdminSwal.success) ? AdminSwal.success(msgIx) : Promise.resolve();
                            pOk.then(function () { window.location.reload(); });
                            return;
                        }
                        if (createdIx > 0 && failsIx.length > 0) {
                            importScheduleReload = true;
                            closeCustImportModal();
                            var pPart = (window.AdminSwal && AdminSwal.warning)
                                ? AdminSwal.warning(combinedIx, 'ثبت جزئی')
                                : ((window.AdminSwal && AdminSwal.success) ? AdminSwal.success(combinedIx) : Promise.resolve());
                            pPart.then(function () { window.location.reload(); });
                            return;
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error(combinedIx !== '' ? combinedIx : 'هیچ مشتری جدیدی ثبت نشد.');
                    }).catch(function () {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.');
                    }).finally(function () {
                        if (!importScheduleReload) {
                            custImportSubmitting = false;
                            custImportSubmitBtn.disabled = false;
                            custImportSubmitBtn.textContent = 'بارگذاری و ثبت مشتریان';
                        }
                    });
                });
            }

            /* capture: از آنجا که روی منو e.stopPropagation() است، در حباب رویداد هرگز به document نمی‌رسد */
            document.addEventListener('click', function (e) {
                var manageLoansBtn = e.target.closest('[data-cust-manage-loans]');
                if (manageLoansBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var manageCustomerId = parseInt(manageLoansBtn.getAttribute('data-customer-id') || '0', 10);
                    var manageCustomerName = manageLoansBtn.getAttribute('data-customer-name') || '';
                    var manageCustomerMobile = manageLoansBtn.getAttribute('data-customer-mobile') || '';
                    closeAllCustMenus();
                    openLoanManageModal(manageCustomerId, manageCustomerName, manageCustomerMobile);
                    return;
                }
                var quickSmsBtn = e.target.closest('[data-cust-quick-sms]');
                if (quickSmsBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var quickCustomerId = quickSmsBtn.getAttribute('data-customer-id');
                    var quickCustomerName = quickSmsBtn.getAttribute('data-customer-name') || '';
                    var quickCustomerMobile = quickSmsBtn.getAttribute('data-customer-mobile') || '';
                    var quickSmsType = quickSmsBtn.getAttribute('data-sms-type') || 'welcome';
                    var quickInstId = quickSmsBtn.getAttribute('data-installment-id');
                    var quickLfId = quickSmsBtn.getAttribute('data-loan-file-id');
                    if (quickSmsType === 'installment_overdue' && (!quickInstId || parseInt(quickInstId, 10) < 1)) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error('این مشتری قسط معوق پرداخت‌نشده‌ای ندارد.');
                        }
                        return;
                    }
                    if (quickCustomerId) {
                        openQuickSmsModal(parseInt(quickCustomerId, 10), quickCustomerName, quickCustomerMobile, quickSmsType, {
                            installmentId: quickInstId,
                            loanFileId: quickLfId
                        });
                    }
                    return;
                }
                var walletBtn = e.target.closest('[data-cust-wallet]');
                if (walletBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var walletCustomerId = walletBtn.getAttribute('data-customer-id');
                    var walletCustomerName = walletBtn.getAttribute('data-customer-name') || '';
                    var walletCustomerMobile = walletBtn.getAttribute('data-customer-mobile') || '';
                    if (walletCustomerId) {
                        closeAllCustMenus();
                        openWalletModal(parseInt(walletCustomerId, 10), walletCustomerName, walletCustomerMobile);
                    }
                    return;
                }
                var editBtn = e.target.closest('[data-cust-edit]');
                if (!editBtn) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var cid = editBtn.getAttribute('data-customer-id');
                if (cid) {
                    closeAllCustMenus();
                    openEditModal(parseInt(cid, 10));
                }
            }, true);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) closeModal();
                });
            }
            if (walletCloseModal) walletCloseModal.addEventListener('click', closeWalletModal);
            if (walletModalOverlay) {
                walletModalOverlay.addEventListener('click', function (e) {
                    if (e.target === walletModalOverlay) closeWalletModal();
                });
            }
            if (walletAdjustClose) walletAdjustClose.addEventListener('click', closeWalletAdjustModal);
            if (walletAdjustCancel) walletAdjustCancel.addEventListener('click', closeWalletAdjustModal);
            if (walletAdjustOverlay) {
                walletAdjustOverlay.addEventListener('click', function (e) {
                    if (e.target === walletAdjustOverlay) closeWalletAdjustModal();
                });
            }
            if (walletTransClose) walletTransClose.addEventListener('click', closeWalletTransactionsModal);
            if (walletTransOverlay) {
                walletTransOverlay.addEventListener('click', function (e) {
                    if (e.target === walletTransOverlay) closeWalletTransactionsModal();
                });
            }
            if (quickSmsClose) quickSmsClose.addEventListener('click', closeQuickSmsModal);
            if (quickSmsCancel) quickSmsCancel.addEventListener('click', closeQuickSmsModal);
            if (quickSmsOverlay) {
                quickSmsOverlay.addEventListener('click', function (e) {
                    if (e.target === quickSmsOverlay) closeQuickSmsModal();
                });
            }
            if (loanManageClose) loanManageClose.addEventListener('click', closeLoanManageModal);
            if (loanManageOverlay) {
                loanManageOverlay.addEventListener('click', function (e) {
                    if (e.target === loanManageOverlay) closeLoanManageModal();
                });
            }
            if (loanManageOpenEditBtn) {
                loanManageOpenEditBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    closeLoanManageModal();
                    openEditModal(loanManageCurrentCustomerId);
                });
            }
            if (loanManageOpenWalletBtn) {
                loanManageOpenWalletBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    closeLoanManageModal();
                    openWalletModal(loanManageCurrentCustomerId, loanManageCurrentCustomerName, loanManageCurrentCustomerMobile);
                });
            }
            loanTabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setLoanTab(btn.getAttribute('data-loan-tab') || 'files');
                });
            });
            if (loanFilesHideSettledCheckbox) {
                loanFilesHideSettledCheckbox.addEventListener('change', function () {
                    loanManageHideSettledFiles = !!loanFilesHideSettledCheckbox.checked;
                    if (loanManageCurrentCustomerId) {
                        renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                    }
                });
            }
            if (loanSmsDayPrev) {
                loanSmsDayPrev.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    var nextDay = shiftGregorianDay(loanSmsSelectedDate, -1);
                    loadLoanManageCustomerSms(loanManageCurrentCustomerId, nextDay);
                });
            }
            if (loanSmsDayNext) {
                loanSmsDayNext.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    var nextDay = shiftGregorianDay(loanSmsSelectedDate, 1);
                    loadLoanManageCustomerSms(loanManageCurrentCustomerId, nextDay);
                });
            }
            var loanSmsReloadBtn = document.getElementById('loan-sms-reload');
            var loanSmsExcelBtn = document.getElementById('loan-sms-excel');
            var loanGuaranteesReloadBtn = document.getElementById('loan-guarantees-reload');
            var loanGuaranteesExcelBtn = document.getElementById('loan-guarantees-excel');
            if (loanSmsReloadBtn) {
                loanSmsReloadBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    loadLoanManageCustomerSms(loanManageCurrentCustomerId, loanSmsSelectedDate);
                });
            }
            if (loanSmsExcelBtn) {
                loanSmsExcelBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (!loanManageCurrentCustomerId) return;
                    window.location.href = customerSmsLogsExportUrl(loanManageCurrentCustomerId, loanSmsSelectedDate);
                });
            }
            if (loanGuaranteesReloadBtn) {
                loanGuaranteesReloadBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId) return;
                    loadLoanGuaranteesReport(loanManageCurrentCustomerId);
                });
            }
            if (loanGuaranteesExcelBtn) {
                loanGuaranteesExcelBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (!loanManageCurrentCustomerId) return;
                    window.location.href = customerGuaranteesReportExportUrl(loanManageCurrentCustomerId);
                });
            }
            if (loanGuaranteesSearchInput) {
                loanGuaranteesSearchInput.addEventListener('input', function () {
                    if (!loanGuaranteesReportCache.length) return;
                    applyLoanGuaranteesReportFilter();
                });
            }
            document.addEventListener('click', function (e) {
                var smsFullBtn = e.target.closest('[data-loan-sms-full-text]');
                if (!smsFullBtn || !loanSmsLogsBody || !loanSmsLogsBody.contains(smsFullBtn)) return;
                e.preventDefault();
                var fullTxt = smsFullBtn.getAttribute('data-loan-sms-full-text') || '';
                if (window.AdminSwal && AdminSwal.info) {
                    AdminSwal.info(fullTxt, 'متن کامل پیامک');
                } else {
                    window.alert(fullTxt);
                }
            });
            if (loanOpenCreateModalBtn) {
                loanOpenCreateModalBtn.addEventListener('click', function () {
                    openLoanCreateModal();
                });
            }
            if (loanCreateClose) loanCreateClose.addEventListener('click', closeLoanCreateModal);
            if (loanCreateCancel) loanCreateCancel.addEventListener('click', closeLoanCreateModal);
            if (loanCreateOverlay) {
                loanCreateOverlay.addEventListener('click', function (e) {
                    if (e.target === loanCreateOverlay) closeLoanCreateModal();
                });
            }
            document.addEventListener('click', function (e) {
                var editLoanBtn = e.target.closest('[data-loan-edit-id]');
                if (!editLoanBtn) return;
                e.preventDefault();
                var loanId = parseInt(String(editLoanBtn.getAttribute('data-loan-edit-id') || '0'), 10);
                if (loanId > 0) {
                    openLoanEditModal(loanId);
                }
            });
            document.addEventListener('click', function (e) {
                var deleteLoanBtn = e.target.closest('[data-loan-delete-id]');
                if (!deleteLoanBtn) return;
                e.preventDefault();
                if (!loanManageCurrentCustomerId) return;
                var loanId = parseInt(String(deleteLoanBtn.getAttribute('data-loan-delete-id') || '0'), 10);
                if (loanId <= 0) return;
                var loanCode = String(deleteLoanBtn.getAttribute('data-loan-delete-code') || '');

                var proceed = function () {
                    fetch(customerLoanDeleteUrl(loanManageCurrentCustomerId, loanId), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin'
                    }).then(function (r) {
                        return r.json().then(function (json) {
                            return { ok: r.ok, json: json };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'حذف پرونده ناموفق بود.');
                        }
                        var key = String(loanManageCurrentCustomerId);
                        if (!loanManageMap[key]) return;
                        var currentRows = Array.isArray(loanManageMap[key].loan_files) ? loanManageMap[key].loan_files : [];
                        loanManageMap[key].loan_files = currentRows.filter(function (x) {
                            return Number(x.id || 0) !== Number(loanId);
                        });
                        renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                        if (window.AdminSwal && window.AdminSwal.success) {
                            AdminSwal.success((res.json && res.json.message) ? res.json.message : 'پرونده حذف شد.');
                        }
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error(err.message || 'حذف پرونده ناموفق بود.');
                        }
                    });
                };

                if (window.AdminSwal && window.AdminSwal.confirm) {
                    AdminSwal.confirm({
                        title: 'حذف پرونده وام',
                        text: 'پرونده ' + (loanCode || ('#' + loanId)) + ' حذف شود؟ این عمل قابل بازگشت نیست.',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            proceed();
                        }
                    });
                    return;
                }
                if (window.confirm('پرونده وام حذف شود؟')) {
                    proceed();
                }
            });
            document.addEventListener('click', function (e) {
                var revokeLoanBtn = e.target.closest('[data-loan-revoke-id]');
                if (!revokeLoanBtn) return;
                e.preventDefault();
                if (!loanManageCurrentCustomerId) return;
                var loanId = parseInt(String(revokeLoanBtn.getAttribute('data-loan-revoke-id') || '0'), 10);
                if (loanId <= 0) return;

                var revokeConfirmText =
                    'آیا از فسخ این قرارداد مطمئن هستید؟ دقت شود پس از فسخ قرارداد، کلیه اقساط تعریف شده برای این پرونده حذف می گردد و مانده وام صفر می شود و غیر قابل بازگشت می باشد.';

                var proceedRevoke = function () {
                    fetch(customerLoanRevokeContractUrl(loanManageCurrentCustomerId, loanId), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: '{}'
                    }).then(function (r) {
                        return r.json().then(function (json) {
                            return { ok: r.ok, json: json };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'فسخ قرارداد ناموفق بود.');
                        }
                        var key = String(loanManageCurrentCustomerId);
                        if (!loanManageMap[key] || !(res.json && res.json.loan_file)) return;
                        var currentRows = Array.isArray(loanManageMap[key].loan_files) ? loanManageMap[key].loan_files : [];
                        loanManageMap[key].loan_files = currentRows.map(function (x) {
                            return Number(x.id || 0) === Number(loanId) ? res.json.loan_file : x;
                        });
                        loanManageMap[key].loan_count = loanManageMap[key].loan_files.length;
                        loanManageMap[key].loan_total_with_profit = loanManageMap[key].loan_files.reduce(function (s, x) {
                            return s + Number(x.total_repayable_toman || 0);
                        }, 0);
                        loanManageMap[key].loan_remaining_installments = loanManageMap[key].loan_files.reduce(function (s, x) {
                            return s + Number(x.remaining_amount_toman || 0);
                        }, 0);
                        renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                        refreshLoanGuaranteesReportIfTabActive();
                        if (window.AdminSwal && window.AdminSwal.success) {
                            AdminSwal.success((res.json && res.json.message) ? res.json.message : 'قرارداد فسخ شد.');
                        }
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error(err.message || 'فسخ قرارداد ناموفق بود.');
                        }
                    });
                };

                if (window.AdminSwal && window.AdminSwal.confirm) {
                    AdminSwal.confirm({
                        title: 'فسخ قرارداد',
                        text: revokeConfirmText,
                        confirmButtonText: 'بله',
                        cancelButtonText: 'خیر'
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            proceedRevoke();
                        }
                    });
                    return;
                }
                if (window.confirm(revokeConfirmText)) {
                    proceedRevoke();
                }
            });
            document.addEventListener('click', function (e) {
                var smsLoanBtn = e.target.closest('[data-loan-sms-id]');
                if (!smsLoanBtn) return;
                e.preventDefault();
                var loanId = parseInt(String(smsLoanBtn.getAttribute('data-loan-sms-id') || '0'), 10);
                if (loanId <= 0) return;
                openLoanSmsModal(loanId, smsLoanBtn.getAttribute('data-loan-default-sms') || '');
            });
            if (loanSmsClose) loanSmsClose.addEventListener('click', closeLoanSmsModal);
            if (loanSmsCancel) loanSmsCancel.addEventListener('click', closeLoanSmsModal);
            if (loanSmsOverlay) {
                loanSmsOverlay.addEventListener('click', function (e) {
                    if (e.target === loanSmsOverlay) closeLoanSmsModal();
                });
            }
            if (loanSmsTemplate && loanSmsText) {
                loanSmsTemplate.addEventListener('change', function () {
                    var tplId = parseInt(String(loanSmsTemplate.value || '0'), 10);
                    if (!tplId || !loanSmsCurrentLoanData) return;
                    var tpl = quickSmsTemplatesData.find(function (x) { return parseInt(String(x.id), 10) === tplId; });
                    if (!tpl) return;
                    loanSmsText.value = renderWalletTemplateText(tpl.body || '', {
                        store_name: appDisplayName || (document.title || 'سامانه'),
                        customer_name: loanManageCurrentCustomerName || '',
                        loan_code: String(loanSmsCurrentLoanData.loan_code || ''),
                        loan_amount: formatToman(loanSmsCurrentLoanData.amount_toman || 0) + ' تومان',
                        installment_amount: formatToman(loanSmsCurrentLoanData.installment_amount_toman || 0) + ' تومان'
                    });
                });
            }
            if (loanSmsForm) {
                loanSmsForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!loanManageCurrentCustomerId || !loanSmsCurrentLoanId || loanSmsSubmitting) return;
                    var submitBtn = loanSmsForm.querySelector('button[type="submit"]');
                    var payload = {
                        sms_text: String((loanSmsText && loanSmsText.value) || '').trim(),
                        sms_template_id: String((loanSmsTemplate && loanSmsTemplate.value) || '')
                    };
                    loanSmsSubmitting = true;
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'در حال ارسال...';
                    }
                    fetch(customerLoanSendSmsUrl(loanManageCurrentCustomerId, loanSmsCurrentLoanId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        return r.json().then(function (json) { return { ok: r.ok, json: json }; });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'ارسال پیامک ناموفق بود.');
                        }
                        closeLoanSmsModal();
                        if (window.AdminSwal && window.AdminSwal.success) {
                            AdminSwal.success((res.json && res.json.message) ? res.json.message : 'پیامک ارسال شد.');
                        }
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error(err.message || 'ارسال پیامک ناموفق بود.');
                        }
                    }).finally(function () {
                        loanSmsSubmitting = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'ارسال پیامک ثبت پرونده جدید';
                        }
                    });
                });
            }
            document.addEventListener('click', function (e) {
                var bookletBtn = e.target.closest('[data-loan-booklet-print]');
                if (bookletBtn && loanManageOverlay && loanManageOverlay.contains(bookletBtn)) {
                    e.preventDefault();
                    var lfBk = parseInt(String(bookletBtn.getAttribute('data-loan-id') || '0'), 10);
                    if (lfBk > 0 && loanManageCurrentCustomerId) {
                        window.open(customerLoanBookletPrintUrl(loanManageCurrentCustomerId, lfBk), '_blank', 'noopener');
                    }
                    return;
                }
                var instLoanBtn = e.target.closest('[data-loan-installments-open]');
                if (instLoanBtn) {
                    e.preventDefault();
                    var loanOpenId = parseInt(String(instLoanBtn.getAttribute('data-loan-id') || '0'), 10);
                    if (loanOpenId > 0 && loanManageCurrentCustomerId) {
                        openLoanInstallmentsModal(loanOpenId);
                    }
                    return;
                }
                var isBtn = e.target.closest('[data-loan-instant-settlement-open]');
                if (isBtn) {
                    e.preventDefault();
                    var isLoanId = parseInt(String(isBtn.getAttribute('data-loan-id') || '0'), 10);
                    if (isLoanId > 0 && loanManageCurrentCustomerId) {
                        openLoanInstantSettlementModal(isLoanId);
                    }
                    return;
                }
                var discBtn = e.target.closest('[data-loan-discount-open]');
                if (discBtn) {
                    e.preventDefault();
                    var discLoanId = parseInt(String(discBtn.getAttribute('data-loan-id') || '0'), 10);
                    if (discLoanId > 0 && loanManageCurrentCustomerId) {
                        openLoanDiscountModal(discLoanId);
                    }
                    return;
                }
                var guaranteeBtn = e.target.closest('[data-loan-guarantees-id]');
                if (!guaranteeBtn) return;
                e.preventDefault();
                var loanId = parseInt(String(guaranteeBtn.getAttribute('data-loan-guarantees-id') || '0'), 10);
                if (loanId > 0) {
                    openLoanGuaranteeModal(loanId);
                }
            });
            if (loanGuaranteeClose) loanGuaranteeClose.addEventListener('click', closeLoanGuaranteeModal);
            if (loanGuaranteeOverlay) {
                loanGuaranteeOverlay.addEventListener('click', function (e) {
                    if (e.target === loanGuaranteeOverlay) closeLoanGuaranteeModal();
                });
            }
            if (loanInstClose) loanInstClose.addEventListener('click', closeLoanInstallmentsModal);
            if (loanInstallmentsOverlay) {
                loanInstallmentsOverlay.addEventListener('click', function (e) {
                    var payOpenBtn = e.target.closest('[data-loan-inst-pay]');
                    if (payOpenBtn && loanInstallmentsOverlay.contains(payOpenBtn) && !payOpenBtn.disabled) {
                        e.preventDefault();
                        var rawPayOpen = payOpenBtn.getAttribute('data-loan-inst-pay') || '';
                        var payOpenObj = null;
                        try {
                            payOpenObj = JSON.parse(rawPayOpen);
                        } catch (ePayOpen) {
                            payOpenObj = null;
                        }
                        if (payOpenObj && payOpenObj.id) {
                            openLoanInstallmentPayModalInstallment(payOpenObj.id);
                        }
                        return;
                    }
                    var clearInstPayBtn = e.target.closest('[data-loan-inst-clear-payments]');
                    if (clearInstPayBtn && loanInstallmentsOverlay.contains(clearInstPayBtn) && !clearInstPayBtn.disabled) {
                        e.preventDefault();
                        if (loanInstClearAllPaymentsBusy || loanInstPaySubmitting) return;
                        var rawClr = clearInstPayBtn.getAttribute('data-loan-inst-clear-payments') || '';
                        var clrObj = null;
                        try {
                            clrObj = JSON.parse(rawClr);
                        } catch (eClr) {
                            clrObj = null;
                        }
                        var insClr = clrObj && clrObj.id ? Number(clrObj.id) : 0;
                        var cidClr = loanManageCurrentCustomerId;
                        var lfClr = loanInstActiveLoanFileId;
                        if (insClr <= 0 || !cidClr || !lfClr) return;
                        var runClearInstPayments = function () {
                            loanInstClearAllPaymentsBusy = true;
                            fetch(customerLoanInstallmentPaymentsUrl(cidClr, lfClr, insClr), {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': @json(csrf_token())
                                },
                                credentials: 'same-origin'
                            }).then(function (rClr) {
                                return rClr.json().then(function (jClr) {
                                    return { ok: rClr.ok, json: jClr };
                                });
                            }).then(function (resClr) {
                                if (!resClr.ok) {
                                    var mClr = resClr.json && resClr.json.message ? String(resClr.json.message) : 'حذف واریزی‌ها ناموفق بود.';
                                    throw new Error(mClr);
                                }
                                var okClr = resClr.json && resClr.json.message ? String(resClr.json.message) : 'واریزی‌ها حذف شد.';
                                return loanInstPayRefreshAfterMutation(cidClr, lfClr, insClr, okClr);
                            }).catch(function (eClr) {
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(eClr.message || 'حذف واریزی‌ها ناموفق بود.');
                            }).finally(function () {
                                loanInstClearAllPaymentsBusy = false;
                            });
                        };
                        if (window.AdminSwal && AdminSwal.confirm) {
                            AdminSwal.confirm({
                                title: 'حذف واریزی‌های این قسط',
                                text: 'تمام واریزی‌های ثبت‌شده برای این قسط حذف شود؟ خود قسط از برنامهٔ اقساط حذف نمی‌شود؛ ماندهٔ پرونده دوباره محاسبه می‌شود.',
                                confirmButtonText: 'بله',
                                cancelButtonText: 'خیر'
                            }).then(function (rClrConf) {
                                if (rClrConf && rClrConf.isConfirmed) runClearInstPayments();
                            });
                            return;
                        }
                        if (window.confirm('تمام واریزی‌های ثبت‌شده برای این قسط حذف شود؟ (خود قسط حذف نمی‌شود)')) runClearInstPayments();
                        return;
                    }
                    var editBtn = e.target.closest('[data-loan-inst-edit]');
                    if (editBtn && loanInstallmentsOverlay.contains(editBtn) && !editBtn.disabled) {
                        e.preventDefault();
                        var raw = editBtn.getAttribute('data-loan-inst-edit') || '';
                        var rowObj = null;
                        try {
                            rowObj = JSON.parse(raw);
                        } catch (eInst) {
                            rowObj = null;
                        }
                        if (rowObj && rowObj.id) {
                            openLoanInstallmentEditModal(rowObj);
                        }
                        return;
                    }
                    if (e.target === loanInstallmentsOverlay) closeLoanInstallmentsModal();
                });
            }
            if (loanInstEditClose) loanInstEditClose.addEventListener('click', closeLoanInstallmentEditModal);
            if (loanInstEditCancel) loanInstEditCancel.addEventListener('click', closeLoanInstallmentEditModal);
            if (loanInstallmentEditOverlay) {
                loanInstallmentEditOverlay.addEventListener('click', function (e) {
                    if (e.target === loanInstallmentEditOverlay) closeLoanInstallmentEditModal();
                });
            }
            if (loanInstEditAmountInput) {
                loanInstEditAmountInput.addEventListener('input', function () {
                    var el = loanInstEditAmountInput;
                    var pos = el.selectionStart;
                    var raw = el.value;
                    var next = formatThousandsInputValue(raw);
                    el.value = next;
                    try {
                        if (typeof pos === 'number') el.setSelectionRange(next.length, next.length);
                    } catch (eSel) { /* noop */ }
                });
            }
            if (loanInstEditSave) {
                loanInstEditSave.addEventListener('click', function () {
                    if (loanInstEditSaving) return;
                    var cid = loanManageCurrentCustomerId;
                    var lfId = loanInstActiveLoanFileId;
                    var iid = loanInstEditInstallmentIdInput && loanInstEditInstallmentIdInput.value;
                    if (!cid || !lfId || !iid) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error('اطلاعات کافی برای ذخیره وجود ندارد.');
                        }
                        return;
                    }
                    var amt = parseThousandsInput((loanInstEditAmountInput && loanInstEditAmountInput.value) || '');
                    var due = String((loanInstEditDueInput && loanInstEditDueInput.value) || '').trim();
                    if (amt < 1) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('مبلغ قسط معتبر نیست.');
                        return;
                    }
                    if (!due) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('تاریخ سررسید را انتخاب یا وارد کنید.');
                        return;
                    }
                    loanInstEditSaving = true;
                    loanInstEditSave.disabled = true;
                    fetch(customerLoanInstallmentUpdateUrl(cid, lfId, iid), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            amount_toman: amt,
                            due_jdate: due
                        })
                    }).then(function (r) {
                        return r.json().then(function (json) {
                            return { ok: r.ok, json: json };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            var msgBad = (res.json && res.json.message) ? res.json.message : 'ذخیره ناموفق بود.';
                            throw new Error(msgBad);
                        }
                        var okMsg = (res.json && res.json.message) ? res.json.message : 'قسط به‌روزرسانی شد.';
                        return fetch(customerLoanInstallmentsUrl(cid, lfId), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        }).then(function (rr) {
                            if (!rr.ok) throw new Error('bad');
                            return rr.json().then(function (j2) {
                                return { data: j2, msg: okMsg };
                            });
                        });
                    }).then(function (pack) {
                        loanInstCachedPayload = pack.data;
                        renderLoanInstallmentsPayload(pack.data);
                        closeLoanInstallmentEditModal();
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success(pack.msg);
                    }).catch(function (err) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err.message || 'ذخیره ناموفق بود.');
                    }).finally(function () {
                        loanInstEditSaving = false;
                        if (loanInstEditSave) loanInstEditSave.disabled = false;
                    });
                });
            }
            if (loanInstPayClose) loanInstPayClose.addEventListener('click', closeLoanInstallmentPayModal);
            if (loanInstPayDismiss) loanInstPayDismiss.addEventListener('click', closeLoanInstallmentPayModal);
            if (loanInstallmentPayOverlay) {
                loanInstallmentPayOverlay.addEventListener('click', function (e) {
                    if (e.target === loanInstallmentPayOverlay) closeLoanInstallmentPayModal();
                });
            }
            if (loanInstPayAddBtn) {
                loanInstPayAddBtn.addEventListener('click', function () {
                    if (loanInstPayAddBtn.disabled) return;
                    toggleLoanInstPayForm(!loanInstPayFormVisible);
                });
            }
            if (loanInstPayFormCancel) {
                loanInstPayFormCancel.addEventListener('click', function () {
                    toggleLoanInstPayForm(false);
                });
            }
            if (loanInstPayAmountInput) {
                loanInstPayAmountInput.addEventListener('input', function () {
                    var elAp = loanInstPayAmountInput;
                    var posAp = elAp.selectionStart;
                    var rawAp = elAp.value;
                    var nextAp = formatThousandsInputValue(rawAp);
                    elAp.value = nextAp;
                    try {
                        if (typeof posAp === 'number') elAp.setSelectionRange(nextAp.length, nextAp.length);
                    } catch (eSap) { /* noop */ }
                });
            }
            function buildLoanInstPaymentDefaultSmsText(amtPay, snapIns) {
                var loanPx = loanInstPayLastServerPayload && loanInstPayLastServerPayload.loan
                    ? loanInstPayLastServerPayload.loan
                    : {};
                var amtText = formatToman(amtPay) + ' تومان';
                var seq = snapIns.sequence != null ? String(snapIns.sequence) : '—';
                var loanCode = String(loanPx.loan_code || '—');
                var remainBefore = Number(
                    snapIns.loan_remaining_payable_toman != null
                        ? snapIns.loan_remaining_payable_toman
                        : (snapIns.max_payment_toman != null ? snapIns.max_payment_toman : 0)
                );
                var remainAfter = Math.max(0, remainBefore - Number(amtPay || 0));
                var remainText = formatToman(remainAfter) + ' تومان';
                var tplVars = {
                    store_name: appDisplayName || (document.title || 'سامانه'),
                    customer_name: loanManageCurrentCustomerName || '',
                    loan_code: loanCode,
                    installment_number: seq,
                    paid_amount: amtText,
                    remaining_loan: remainText
                };
                var adminPayTpl = quickSmsTemplatesData.find(function (t) {
                    return String(t.template_key || '') === 'default_admin_installment_payment_registered';
                });
                if (adminPayTpl && adminPayTpl.body) {
                    return renderWalletTemplateText(adminPayTpl.body, tplVars);
                }

                return (appDisplayName || '') + '\n' +
                    'مشتری گرامی ' + (loanManageCurrentCustomerName || '') + '؛ مبلغ ' + amtText +
                    ' بابت قسط شماره ' + formatToman(seq) + ' پرونده ' + loanCode + ' ثبت گردید.\n' +
                    'مانده قابل پرداخت: ' + remainText;
            }

            function submitLoanInstPaymentRequest(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId) {
                loanInstPaySubmitting = true;
                if (loanInstPaySaveBtn) loanInstPaySaveBtn.disabled = true;
                fetch(payUrl, {
                    method: payMethodHttp,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(postBody)
                }).then(function (rPay) {
                    return rPay.json().then(function (jPay) {
                        return { ok: rPay.ok, json: jPay };
                    });
                }).then(function (resPay) {
                    if (!resPay.ok) {
                        var msgPayErr = '';
                        try {
                            var jpe = resPay.json || {};
                            msgPayErr = jpe.message ? String(jpe.message) : '';
                            if (!msgPayErr && jpe.errors && typeof jpe.errors === 'object') {
                                var kPay = Object.keys(jpe.errors);
                                if (kPay.length && Array.isArray(jpe.errors[kPay[0]]) && jpe.errors[kPay[0]][0]) {
                                    msgPayErr = String(jpe.errors[kPay[0]][0]);
                                }
                            }
                        } catch (ePv) {}
                        throw new Error(msgPayErr || (editPaymentId > 0 ? 'به‌روزرسانی پرداخت ناموفق بود.' : 'ثبت پرداخت ناموفق بود.'));
                    }
                    var okPayMsg = resPay.json && resPay.json.message
                        ? resPay.json.message
                        : (editPaymentId > 0 ? 'پرداخت به‌روزرسانی شد.' : 'پرداخت ثبت شد.');
                    return loanInstPayRefreshAfterMutation(cidPay, lfPay, insPay, okPayMsg);
                }).catch(function (exPay) {
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error(exPay.message || 'عملیات پرداخت ناموفق بود.');
                    }
                }).finally(function () {
                    loanInstPaySubmitting = false;
                    if (loanInstPaySaveBtn) loanInstPaySaveBtn.disabled = false;
                });
            }

            function promptLoanInstPaymentSmsThenSubmit(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId, snapIns, amtPay) {
                if (editPaymentId > 0 || typeof Swal === 'undefined') {
                    submitLoanInstPaymentRequest(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId);
                    return;
                }

                var defaultPaySms = buildLoanInstPaymentDefaultSmsText(amtPay, snapIns);
                var adminPayTpl = quickSmsTemplatesData.find(function (t) {
                    return String(t.template_key || '') === 'default_admin_installment_payment_registered';
                });
                var templateOptionsHtml = '<option value="">بدون قالب (متن آزاد)</option>';
                quickSmsTemplatesData.forEach(function (tpl) {
                    var selected = adminPayTpl && Number(tpl.id) === Number(adminPayTpl.id) ? ' selected' : '';
                    templateOptionsHtml += '<option value="' + String(tpl.id) + '"' + selected + '>' +
                        escapeHtmlText((tpl.title || '') + ' (' + (tpl.category || '') + ')') + '</option>';
                });

                Swal.fire({
                    icon: 'question',
                    title: 'ارسال پیامک پس از ثبت واریز',
                    width: 540,
                    customClass: {
                        popup: 'wallet-sms-swal',
                        title: 'wallet-sms-swal-title',
                    },
                    html:
                        '<div style="text-align:right">' +
                        '<div style="font-size:.73rem;color:#64748b;margin-bottom:.3rem">موبایل مشتری: ' + escapeHtmlText(loanManageCurrentCustomerMobile || '—') + '</div>' +
                        '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">قالب پیامک</label>' +
                        '<select id="loan-inst-pay-sms-template" class="swal2-select" style="width:100%;margin:0 0 .35rem;min-height:2.1rem">' + templateOptionsHtml + '</select>' +
                        '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">متن پیامک (قابل ویرایش)</label>' +
                        '<textarea id="loan-inst-pay-sms-text" class="swal2-textarea" style="width:100%;margin:0;min-height:88px;padding:.45rem .55rem">' + escapeHtmlText(defaultPaySms) + '</textarea>' +
                        '</div>',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'ثبت و ارسال پیامک',
                    denyButtonText: 'فقط ثبت پرداخت',
                    cancelButtonText: 'لغو',
                    reverseButtons: true,
                    focusCancel: false,
                    didOpen: function () {
                        var p = document.querySelector('.swal2-popup');
                        if (p) p.setAttribute('dir', 'rtl');
                        var selectEl = document.getElementById('loan-inst-pay-sms-template');
                        var txtEl = document.getElementById('loan-inst-pay-sms-text');
                        var loanPx = loanInstPayLastServerPayload && loanInstPayLastServerPayload.loan
                            ? loanInstPayLastServerPayload.loan
                            : {};
                        var remainBefore = Number(
                            snapIns.loan_remaining_payable_toman != null
                                ? snapIns.loan_remaining_payable_toman
                                : (snapIns.max_payment_toman != null ? snapIns.max_payment_toman : 0)
                        );
                        var remainAfter = Math.max(0, remainBefore - Number(amtPay || 0));
                        if (selectEl && txtEl) {
                            selectEl.addEventListener('change', function () {
                                var selectedId = parseInt(String(selectEl.value || '0'), 10);
                                if (!selectedId) {
                                    txtEl.value = defaultPaySms;
                                    return;
                                }
                                var tpl = quickSmsTemplatesData.find(function (x) {
                                    return parseInt(String(x.id), 10) === selectedId;
                                });
                                if (!tpl) return;
                                txtEl.value = renderWalletTemplateText(tpl.body || '', {
                                    store_name: appDisplayName || (document.title || 'سامانه'),
                                    customer_name: loanManageCurrentCustomerName || '',
                                    loan_code: String(loanPx.loan_code || '—'),
                                    installment_number: snapIns.sequence != null ? String(snapIns.sequence) : '—',
                                    paid_amount: formatToman(amtPay) + ' تومان',
                                    remaining_loan: formatToman(remainAfter) + ' تومان'
                                });
                            });
                            if (adminPayTpl && String(selectEl.value || '') === String(adminPayTpl.id)) {
                                selectEl.dispatchEvent(new Event('change'));
                            }
                        }
                    },
                    preConfirm: function () {
                        var txtEl = document.getElementById('loan-inst-pay-sms-text');
                        var selectEl = document.getElementById('loan-inst-pay-sms-template');
                        return {
                            sms_text: txtEl ? String(txtEl.value || '').trim() : '',
                            sms_template_id: selectEl ? (selectEl.value || '') : ''
                        };
                    }
                }).then(function (result) {
                    if (result.isDismissed) {
                        return;
                    }
                    if (result.isConfirmed) {
                        postBody.send_sms = true;
                        postBody.sms_text = (result.value && result.value.sms_text) ? result.value.sms_text : '';
                        postBody.sms_template_id = (result.value && result.value.sms_template_id) ? result.value.sms_template_id : '';
                        submitLoanInstPaymentRequest(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId);
                        return;
                    }
                    if (result.isDenied) {
                        postBody.send_sms = false;
                        submitLoanInstPaymentRequest(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId);
                    }
                });
            }

            if (loanInstPaySaveBtn) {
                loanInstPaySaveBtn.addEventListener('click', function () {
                    if (loanInstPaySubmitting) return;
                    var cidPay = loanManageCurrentCustomerId;
                    var lfPay = loanInstActiveLoanFileId;
                    var insPay = loanInstPayCurrentInstallmentId;
                    if (!cidPay || !lfPay || !insPay) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('اطلاعات کافی برای ثبت وجود ندارد.');
                        return;
                    }
                    var methodPay = loanInstPayMethodSelect ? String(loanInstPayMethodSelect.value || '').trim() : '';
                    if (!methodPay) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('نحوهٔ پرداخت را انتخاب کنید.');
                        return;
                    }
                    var amtPay = parseThousandsInput((loanInstPayAmountInput && loanInstPayAmountInput.value) || '');
                    var depPay = String((loanInstPayDepInput && loanInstPayDepInput.value) || '').trim();
                    if (amtPay < 1) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('مبلغ پرداختی را وارد کنید.');
                        return;
                    }
                    if (!depPay) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('تاریخ واریز را انتخاب کنید.');
                        return;
                    }
                    var snapIns = loanInstPayLastServerPayload && loanInstPayLastServerPayload.installment
                        ? loanInstPayLastServerPayload.installment
                        : {};
                    var editPaymentId = loanInstPayEditingPaymentId ? Number(loanInstPayEditingPaymentId) : 0;
                    var headroomEdit = editPaymentId > 0 ? Number(loanInstPayEditingOriginalAmount || 0) : 0;
                    var maxRemain = Number(
                        snapIns.max_payment_toman != null
                            ? snapIns.max_payment_toman
                            : (snapIns.remaining_toman != null
                                ? snapIns.remaining_toman
                                : Math.max(0, Number(snapIns.amount_toman || 0) - Number(snapIns.paid_amount_toman || 0)))
                    ) + headroomEdit;
                    if (amtPay > maxRemain) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error(
                                maxRemain <= 0
                                    ? 'طبق ماندهٔ وام، مبلغ دیگری قابل ثبت نیست.'
                                    : ('حداکثر مبلغ قابل ثبت با احتساب ماندهٔ کل وام: ' + formatToman(maxRemain) + ' تومان')
                            );
                        }
                        return;
                    }
                    var postBody = {
                        payment_method: methodPay,
                        amount_toman: amtPay,
                        deposited_jdate: depPay
                    };
                    var refPayDate = String((loanInstPayRefDueInput && loanInstPayRefDueInput.value) || '').trim();
                    if (refPayDate) postBody.reference_due_jdate = refPayDate;
                    var notePay = String((loanInstPayNoteInput && loanInstPayNoteInput.value) || '').trim();
                    if (notePay) postBody.note = notePay;

                    var payUrl = editPaymentId > 0
                        ? customerLoanInstallmentPaymentItemUrl(cidPay, lfPay, insPay, editPaymentId)
                        : customerLoanInstallmentPaymentsUrl(cidPay, lfPay, insPay);
                    var payMethodHttp = editPaymentId > 0 ? 'PUT' : 'POST';
                    promptLoanInstPaymentSmsThenSubmit(postBody, payUrl, payMethodHttp, cidPay, lfPay, insPay, editPaymentId, snapIns, amtPay);
                });
            }
            if (loanInstPayTbody) {
                loanInstPayTbody.addEventListener('click', function (evPayRow) {
                    var editTrig = evPayRow.target.closest('[data-loan-inst-pay-edit-id]');
                    if (editTrig) {
                        evPayRow.preventDefault();
                        var pidE = Number(editTrig.getAttribute('data-loan-inst-pay-edit-id') || 0);
                        if (pidE <= 0 || !loanInstPayLastServerPayload) return;
                        var arrE = Array.isArray(loanInstPayLastServerPayload.payments) ? loanInstPayLastServerPayload.payments : [];
                        var ppE = null;
                        for (var ie = 0; ie < arrE.length; ie++) {
                            if (Number(arrE[ie].id || 0) === pidE) {
                                ppE = arrE[ie];
                                break;
                            }
                        }
                        if (ppE) openLoanInstPayEditForm(ppE);
                        return;
                    }
                    var delTrig = evPayRow.target.closest('[data-loan-inst-pay-delete-id]');
                    if (!delTrig) return;
                    evPayRow.preventDefault();
                    var pidDel = Number(delTrig.getAttribute('data-loan-inst-pay-delete-id') || 0);
                    if (pidDel <= 0 || loanInstPaySubmitting) return;
                    var cidDel = loanManageCurrentCustomerId;
                    var lfDel = loanInstActiveLoanFileId;
                    var insDel = loanInstPayCurrentInstallmentId;
                    if (!cidDel || !lfDel || !insDel) return;
                    if (loanInstPayLastServerPayload && loanInstPayLastServerPayload.loan) {
                        var lDel = loanInstPayLastServerPayload.loan;
                        if (lDel.is_settled === true || lDel.is_settled === 1) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('پرونده تسویه‌شده است؛ حذف پرداخت مجاز نیست.');
                            return;
                        }
                    }
                    var runDelete = function () {
                        loanInstPaySubmitting = true;
                        fetch(customerLoanInstallmentPaymentItemUrl(cidDel, lfDel, insDel, pidDel), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin'
                        }).then(function (rDel) {
                            return rDel.json().then(function (jDel) {
                                return { ok: rDel.ok, json: jDel };
                            });
                        }).then(function (resDel) {
                            if (!resDel.ok) {
                                var mDel = resDel.json && resDel.json.message ? String(resDel.json.message) : 'حذف پرداخت ناموفق بود.';
                                throw new Error(mDel);
                            }
                            var okDel = resDel.json && resDel.json.message ? String(resDel.json.message) : 'پرداخت حذف شد.';
                            return loanInstPayRefreshAfterMutation(cidDel, lfDel, insDel, okDel);
                        }).catch(function (eDel) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(eDel.message || 'حذف پرداخت ناموفق بود.');
                        }).finally(function () {
                            loanInstPaySubmitting = false;
                        });
                    };
                    if (window.AdminSwal && AdminSwal.confirm) {
                        AdminSwal.confirm({
                            title: 'حذف واریزی',
                            text: 'این ردیف پرداخت حذف شود؟ ماندهٔ قسط و پرونده دوباره محاسبه می‌شود.',
                            confirmButtonText: 'بله، حذف شود',
                            cancelButtonText: 'انصراف'
                        }).then(function (rConf) {
                            if (rConf && rConf.isConfirmed) runDelete();
                        });
                        return;
                    }
                    if (window.confirm('این ردیف پرداخت حذف شود؟')) runDelete();
                });
            }
            if (loanIsClose) loanIsClose.addEventListener('click', closeLoanInstantSettlementModal);
            if (loanInstantSettlementOverlay) {
                loanInstantSettlementOverlay.addEventListener('click', function (e) {
                    if (e.target === loanInstantSettlementOverlay) closeLoanInstantSettlementModal();
                });
            }
            if (loanDiscountClose) loanDiscountClose.addEventListener('click', closeLoanDiscountModal);
            if (loanDiscountCancel) loanDiscountCancel.addEventListener('click', closeLoanDiscountModal);
            if (loanDiscountOverlay) {
                loanDiscountOverlay.addEventListener('click', function (e) {
                    if (e.target === loanDiscountOverlay) closeLoanDiscountModal();
                });
            }
            if (loanDiscountAmountInput) {
                loanDiscountAmountInput.addEventListener('input', function () {
                    var el = loanDiscountAmountInput;
                    var pos = el.selectionStart;
                    var raw = el.value;
                    var next = formatThousandsInputValue(raw);
                    el.value = next;
                    try {
                        if (typeof pos === 'number') el.setSelectionRange(next.length, next.length);
                    } catch (eSel) { /* noop */ }
                });
            }
            if (loanDiscountSave) {
                loanDiscountSave.addEventListener('click', function () {
                    if (loanDiscountSaving || !loanDiscountCurrentLoanId || !loanManageCurrentCustomerId || !loanDiscountPreviewData) {
                        if (!loanDiscountPreviewData && window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error('اطلاعات پرونده هنوز بارگذاری نشده است.');
                        }
                        return;
                    }
                    var totalDisc = parseThousandsInput(loanDiscountAmountInput ? loanDiscountAmountInput.value : '');
                    if (!Number.isFinite(totalDisc) || totalDisc < 0) {
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('مبلغ کل تخفیف را به‌درستی وارد کنید.');
                        return;
                    }
                    var p = loanDiscountPreviewData;
                    var maxDisc = Number(p.max_discount_toman != null ? p.max_discount_toman : p.schedule_remaining_toman || 0);
                    if (totalDisc > maxDisc) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error('مبلغ کل تخفیف از سقف مجاز بیشتر است. حداکثر: ' + formatToman(maxDisc) + ' تومان');
                        }
                        return;
                    }
                    var sched = Number(p.schedule_remaining_toman || 0);
                    var newRem = Math.max(0, sched - totalDisc);
                    var confirmBody =
                        'آیا از ذخیرهٔ تخفیف به مبلغ کل ' + formatToman(totalDisc) + ' تومان برای وام شماره ' + String(p.loan_code || '') +
                        ' به نام ' + String(p.borrower_name || '') + ' مطمئنید؟ مانده برابر می شود با ' + formatToman(newRem) + ' تومان';

                    var doPost = function () {
                        submitLoanDiscountTotal(totalDisc);
                    };

                    if (window.AdminSwal && AdminSwal.confirm) {
                        AdminSwal.confirm({
                            title: 'تایید ثبت تخفیف',
                            text: confirmBody,
                            confirmButtonText: 'بله',
                            cancelButtonText: 'خیر'
                        }).then(function (result) {
                            if (result && result.isConfirmed) {
                                doPost();
                            }
                        });
                        return;
                    }
                    if (window.confirm(confirmBody)) {
                        doPost();
                    }
                });
            }
            if (loanGuaranteeOpenFormBtn && loanGuaranteeFormWrap) {
                loanGuaranteeOpenFormBtn.addEventListener('click', function () {
                    if (loanGuaranteeFormWrap.hidden) {
                        openGuaranteeFormForCreate();
                    } else {
                        loanGuaranteeFormWrap.hidden = true;
                    }
                });
            }
            loanGuaranteeTypeTabs.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var next = btn.getAttribute('data-guarantee-type') || 'org_self';
                    var prev = loanGuaranteeTypeInput ? String(loanGuaranteeTypeInput.value || '') : '';
                    var bothOrg = (prev === 'org_self' || prev === 'org_other') && (next === 'org_self' || next === 'org_other');
                    setGuaranteeType(next, bothOrg);
                });
            });
            (function bindOrgManageAndGuarantorOtp() {
                var loanOrgManageOverlay = document.getElementById('loan-org-manage-overlay');
                var loanOrgManageClose = document.getElementById('loan-org-manage-close');
                var loanOrgManageOpenBtn = document.getElementById('loan-guarantee-org-manage-open');
                var loanOrgAddToggle = document.getElementById('loan-org-add-toggle');
                var loanOrgInlineForm = document.getElementById('loan-org-inline-form');
                var loanOrgInlineCancel = document.getElementById('loan-org-inline-cancel');
                var loanOrgInlineSave = document.getElementById('loan-org-inline-save');
                var loanOrgNameInput = document.getElementById('loan-org-name-input');
                var loanOrgEditId = document.getElementById('loan-org-edit-id');
                var loanOrgManageList = document.getElementById('loan-org-manage-list');
                var orgListCache = [];

                function closeOrgManageModal() {
                    if (!loanOrgManageOverlay) return;
                    loanOrgManageOverlay.hidden = true;
                    loanOrgManageOverlay.setAttribute('aria-hidden', 'true');
                    if (loanOrgInlineForm) loanOrgInlineForm.hidden = true;
                }

                function openOrgManageModal() {
                    if (!loanOrgManageOverlay) return;
                    loanOrgManageOverlay.hidden = false;
                    loanOrgManageOverlay.setAttribute('aria-hidden', 'false');
                    refreshOrgManageList();
                }

                function refreshOrgManageList() {
                    if (!loanOrgManageList) return;
                    loanOrgManageList.innerHTML = '<div class="loan-guarantee-empty" style="text-align:center">در حال بارگذاری...</div>';
                    fetch(organizationsListUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        orgListCache = Array.isArray(data.organizations) ? data.organizations : [];
                        if (!orgListCache.length) {
                            loanOrgManageList.innerHTML = '<div class="loan-guarantee-empty" style="text-align:center">سازمانی ثبت نشده است.</div>';
                            return;
                        }
                        loanOrgManageList.innerHTML = orgListCache.map(function (o) {
                            return '<div class="loan-org-manage-item" data-org-id="' + String(o.id) + '">' +
                                '<span>' + escapeHtmlText(String(o.name || '')) + '</span>' +
                                '<div class="loan-org-manage-item-actions">' +
                                '<button type="button" class="loan-file-btn loan-file-btn--disc" data-org-edit="' + String(o.id) + '">ویرایش</button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--disc loan-file-btn--danger" data-org-delete="' + String(o.id) + '">حذف</button>' +
                                '</div></div>';
                        }).join('');
                    }).catch(function () {
                        loanOrgManageList.innerHTML = '<div class="loan-guarantee-empty" style="color:#b91c1c">خطا در دریافت فهرست.</div>';
                    });
                }

                function showInlineForm(editId, name) {
                    if (!loanOrgInlineForm || !loanOrgNameInput || !loanOrgEditId) return;
                    loanOrgInlineForm.hidden = false;
                    loanOrgEditId.value = editId ? String(editId) : '';
                    loanOrgNameInput.value = name ? String(name) : '';
                    loanOrgNameInput.focus();
                }

                if (loanOrgManageOpenBtn) {
                    loanOrgManageOpenBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        openOrgManageModal();
                    });
                }
                if (loanOrgManageClose) loanOrgManageClose.addEventListener('click', closeOrgManageModal);
                if (loanOrgManageOverlay) {
                    loanOrgManageOverlay.addEventListener('click', function (e) {
                        if (e.target === loanOrgManageOverlay) closeOrgManageModal();
                    });
                }
                if (loanOrgAddToggle) {
                    loanOrgAddToggle.addEventListener('click', function () {
                        showInlineForm(null, '');
                    });
                }
                if (loanOrgInlineCancel) {
                    loanOrgInlineCancel.addEventListener('click', function () {
                        if (loanOrgInlineForm) loanOrgInlineForm.hidden = true;
                    });
                }
                if (loanOrgInlineSave) {
                    loanOrgInlineSave.addEventListener('click', function () {
                        var nm = loanOrgNameInput ? String(loanOrgNameInput.value || '').trim() : '';
                        if (!nm) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('نام سازمان را وارد کنید.');
                            return;
                        }
                        var eid = loanOrgEditId && loanOrgEditId.value ? String(loanOrgEditId.value) : '';
                        var url = eid ? organizationRestUrl(eid) : organizationsListUrl;
                        var headers = {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        };
                        fetch(url, {
                            method: eid ? 'PUT' : 'POST',
                            headers: headers,
                            credentials: 'same-origin',
                            body: JSON.stringify({ name: nm })
                        }).then(function (r) {
                            return r.json().then(function (json) { return { ok: r.ok, json: json }; });
                        }).then(function (res) {
                            if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'ذخیره نشد.');
                            if (loanOrgInlineForm) loanOrgInlineForm.hidden = true;
                            refreshOrgManageList();
                            var activeType = loanGuaranteeTypeInput ? String(loanGuaranteeTypeInput.value || '') : '';
                            if (activeType === 'org_other') {
                                var cur = document.getElementById('loan-guarantee-organization-id');
                                var keep = cur ? cur.value : '';
                                loadOrganizationsIntoSelect(keep, function () { initLoanOrgSelect2(); });
                            }
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(res.json.message || 'ثبت شد.');
                        }).catch(function (err) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err.message || 'خطا');
                        });
                    });
                }
                document.addEventListener('click', function (e) {
                    var ed = e.target.closest('[data-org-edit]');
                    if (ed && loanOrgManageOverlay && !loanOrgManageOverlay.hidden) {
                        e.preventDefault();
                        var oid = ed.getAttribute('data-org-edit');
                        var row = orgListCache.find(function (x) { return String(x.id) === String(oid); });
                        if (row) showInlineForm(row.id, row.name);
                        return;
                    }
                    var del = e.target.closest('[data-org-delete]');
                    if (del && loanOrgManageOverlay && !loanOrgManageOverlay.hidden) {
                        e.preventDefault();
                        var did = del.getAttribute('data-org-delete');
                        var runDel = function () {
                            fetch(organizationRestUrl(did), {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': @json(csrf_token())
                                },
                                credentials: 'same-origin'
                            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                                .then(function (res) {
                                    if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'حذف نشد.');
                                    refreshOrgManageList();
                                    var cur = document.getElementById('loan-guarantee-organization-id');
                                    var keep = cur ? cur.value : '';
                                    if (String(keep) === String(did)) keep = '';
                                    loadOrganizationsIntoSelect(keep, function () { initLoanOrgSelect2(); });
                                    if (window.AdminSwal && AdminSwal.success) AdminSwal.success(res.json.message || 'حذف شد.');
                                }).catch(function (err) {
                                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err.message || 'خطا');
                                });
                        };
                        if (window.AdminSwal && AdminSwal.confirm) {
                            AdminSwal.confirm({
                                title: 'حذف سازمان',
                                text: 'این سازمان از فهرست حذف شود؟',
                                confirmButtonText: 'بله',
                                cancelButtonText: 'انصراف'
                            }).then(function (r) { if (r && r.isConfirmed) runDel(); });
                        } else if (window.confirm('حذف شود؟')) runDel();
                    }
                });

                var gPhone = document.getElementById('loan-guarantee-guarantor-phone');
                if (gPhone) {
                    gPhone.addEventListener('input', function () {
                        var cur = normalizeGuarantorMobileValue(String(gPhone.value || ''));
                        if (loanGuaranteeGuarantorOtpLocked && cur !== loanGuaranteeGuarantorOtpPhoneSnapshot) {
                            loanGuaranteeGuarantorOtpLocked = false;
                            loanGuaranteeGuarantorOtpPhoneSnapshot = '';
                        }
                        resetGuarantorOtpUi();
                        updateGuarantorOtpButtonChrome();
                    });
                }
                var sendOtpBtn = document.getElementById('loan-guarantee-guarantor-send-otp');
                if (sendOtpBtn) {
                    sendOtpBtn.addEventListener('click', function () {
                        var mobEl = document.getElementById('loan-guarantee-guarantor-phone');
                        var mobile = toEnglishDigits(String(mobEl && mobEl.value ? mobEl.value : '')).replace(/\D/g, '');
                        if (mobile.length === 10 && mobile.charAt(0) === '9') mobile = '0' + mobile;
                        if (!/^09\d{9}$/.test(mobile)) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ابتدا شماره موبایل معتبر (۱۱ رقم با ۰۹) وارد کنید.');
                            return;
                        }
                        resetGuarantorOtpUi();
                        sendOtpBtn.disabled = true;
                        var gNameEl = document.getElementById('loan-guarantee-guarantor-name');
                        var guarantorName = gNameEl ? String(gNameEl.value || '').trim() : '';
                        var borrowerName = typeof loanManageCurrentCustomerName !== 'undefined'
                            ? String(loanManageCurrentCustomerName || '').trim()
                            : '';
                        fetch(guarantorOtpSendUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                mobile: mobile,
                                guarantor_name: guarantorName,
                                borrower_name: borrowerName
                            })
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j || {} }; }).catch(function () {
                                return { ok: r.ok, status: r.status, json: {} };
                            });
                        })
                            .then(function (res) {
                                if (!res.ok) {
                                    var m = (res.json && res.json.message) ? String(res.json.message) : '';
                                    if (!m && res.status === 429) m = 'درخواست زیاد؛ یک دقیقه صبر کنید و دوباره «ارسال کد» را بزنید.';
                                    throw new Error(m || 'ارسال نشد.');
                                }
                                var sid = res.json.otp_session || '';
                                var sessEl = document.getElementById('loan-guarantee-guarantor-otp-session');
                                if (sessEl) sessEl.value = sid;
                                var panel = document.getElementById('loan-guarantee-guarantor-otp-panel');
                                if (panel) panel.hidden = false;
                                if (window.AdminSwal && AdminSwal.success) AdminSwal.success(res.json.message || 'کد ارسال شد.');
                            }).catch(function (err) {
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err.message || 'خطا');
                            }).finally(function () {
                                sendOtpBtn.disabled = false;
                            });
                    });
                }
                var verifyOtpBtn = document.getElementById('loan-guarantee-guarantor-verify-otp');
                if (verifyOtpBtn) {
                    verifyOtpBtn.addEventListener('click', function () {
                        var mobEl = document.getElementById('loan-guarantee-guarantor-phone');
                        var mobile = toEnglishDigits(String(mobEl && mobEl.value ? mobEl.value : '')).replace(/\D/g, '');
                        if (mobile.length === 10 && mobile.charAt(0) === '9') mobile = '0' + mobile;
                        var sessEl = document.getElementById('loan-guarantee-guarantor-otp-session');
                        var codeEl = document.getElementById('loan-guarantee-guarantor-otp-code');
                        var sid = sessEl ? String(sessEl.value || '') : '';
                        var code = codeEl ? toEnglishDigits(String(codeEl.value || '')).replace(/\D/g, '') : '';
                        if (!sid || !code) {
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('کد پیامک را وارد کنید.');
                            return;
                        }
                        verifyOtpBtn.disabled = true;
                        fetch(guarantorOtpVerifyUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ otp_session: sid, code: code, mobile: mobile })
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j || {} }; }).catch(function () {
                                return { ok: r.ok, status: r.status, json: {} };
                            });
                        })
                            .then(function (res) {
                                if (!res.ok) {
                                    var mv = (res.json && res.json.message) ? String(res.json.message) : '';
                                    if (!mv && res.status === 429) mv = 'درخواست زیاد؛ کمی بعد دوباره تلاش کنید.';
                                    throw new Error(mv || 'تایید نشد.');
                                }
                                var tok = document.getElementById('loan-guarantee-guarantor-verification-token');
                                if (tok) tok.value = String(res.json.verification_token || '');
                                loanGuaranteeGuarantorOtpLocked = true;
                                loanGuaranteeGuarantorOtpPhoneSnapshot = mobile;
                                updateGuarantorOtpButtonChrome();
                                var panelOk = document.getElementById('loan-guarantee-guarantor-otp-panel');
                                if (panelOk) panelOk.hidden = true;
                                var msg = document.getElementById('loan-guarantee-guarantor-verify-msg');
                                if (msg) {
                                    msg.textContent = res.json.message || 'احراز با موفقیت انجام شد.';
                                    msg.className = 'loan-guarantee-guarantor-verify-msg is-ok';
                                }
                            }).catch(function (err) {
                                var msg = document.getElementById('loan-guarantee-guarantor-verify-msg');
                                if (msg) {
                                    msg.textContent = err.message || 'خطا';
                                    msg.className = 'loan-guarantee-guarantor-verify-msg is-err';
                                }
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err.message || 'خطا');
                            }).finally(function () {
                                verifyOtpBtn.disabled = false;
                            });
                    });
                }
            })();
            setGuaranteeType((loanGuaranteeTypeInput && loanGuaranteeTypeInput.value) || 'org_self', true);
            if (loanGuaranteeCancel && loanGuaranteeFormWrap) {
                loanGuaranteeCancel.addEventListener('click', function () {
                    loanGuaranteeFormWrap.hidden = true;
                });
            }
            var loanGuaranteeMarkReturned = document.getElementById('loan-guarantee-mark-returned');
            if (loanGuaranteeMarkReturned) {
                loanGuaranteeMarkReturned.addEventListener('change', function () {
                    if (loanGuaranteeMarkReturned.checked) {
                        guaranteeReturnOtpVerified = false;
                        var tokenEl = document.getElementById('loan-guarantee-return-verification-token');
                        var sessEl = document.getElementById('loan-guarantee-return-otp-session');
                        var codeEl = document.getElementById('loan-guarantee-return-otp-code');
                        var panel = document.getElementById('loan-guarantee-return-otp-panel');
                        var verifiedEl = document.getElementById('loan-guarantee-return-verified');
                        if (tokenEl) tokenEl.value = '';
                        if (sessEl) sessEl.value = '';
                        if (codeEl) codeEl.value = '';
                        if (panel) panel.hidden = true;
                        if (verifiedEl) verifiedEl.hidden = true;
                    }
                    syncGuaranteeReturnDetailsVisibility();
                });
            }
            var loanGuaranteeReturnSendOtpBtn = document.getElementById('loan-guarantee-return-send-otp');
            if (loanGuaranteeReturnSendOtpBtn) {
                loanGuaranteeReturnSendOtpBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId || !loanGuaranteeCurrentLoanId || !guaranteeReturnOtpEnabled) return;
                    var mobile = toEnglishDigits(String(loanManageCurrentCustomerMobile || '')).replace(/\D/g, '');
                    if (mobile.length === 10 && mobile.charAt(0) === '9') mobile = '0' + mobile;
                    if (!/^09\d{9}$/.test(mobile)) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('موبایل معتبر برای این مشتری ثبت نشده است.');
                        return;
                    }
                    var tokenEl = document.getElementById('loan-guarantee-return-verification-token');
                    var sessEl = document.getElementById('loan-guarantee-return-otp-session');
                    var codeEl = document.getElementById('loan-guarantee-return-otp-code');
                    var panel = document.getElementById('loan-guarantee-return-otp-panel');
                    var verifiedEl = document.getElementById('loan-guarantee-return-verified');
                    if (tokenEl) tokenEl.value = '';
                    if (sessEl) sessEl.value = '';
                    if (codeEl) codeEl.value = '';
                    if (panel) panel.hidden = true;
                    if (verifiedEl) verifiedEl.hidden = true;
                    guaranteeReturnOtpVerified = false;
                    loanGuaranteeReturnSendOtpBtn.disabled = true;
                    var gtype = String((loanGuaranteeTypeInput && loanGuaranteeTypeInput.value) || '');
                    var payload = {
                        customer_name: loanManageCurrentCustomerName || '',
                        guarantee_type_label: guaranteeTypeLabelFa(gtype)
                    };
                    if (loanGuaranteeFormMode === 'edit' && loanGuaranteeEditingId) {
                        payload.guarantee_id = loanGuaranteeEditingId;
                    }
                    fetch(customerGuaranteeReturnOtpUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId, 'send'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        return r.json().then(function (j) { return { ok: r.ok, json: j || {} }; }).catch(function () {
                            return { ok: r.ok, json: {} };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'ارسال کد ناموفق بود.');
                        }
                        if (sessEl) sessEl.value = String(res.json.otp_session || '');
                        if (panel) panel.hidden = false;
                        if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'کد ارسال شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'ارسال کد ناموفق بود.');
                    }).finally(function () {
                        loanGuaranteeReturnSendOtpBtn.disabled = false;
                    });
                });
            }
            var loanGuaranteeReturnVerifyOtpBtn = document.getElementById('loan-guarantee-return-verify-otp');
            if (loanGuaranteeReturnVerifyOtpBtn) {
                loanGuaranteeReturnVerifyOtpBtn.addEventListener('click', function () {
                    if (!loanManageCurrentCustomerId || !loanGuaranteeCurrentLoanId || !guaranteeReturnOtpEnabled) return;
                    var sessEl = document.getElementById('loan-guarantee-return-otp-session');
                    var codeEl = document.getElementById('loan-guarantee-return-otp-code');
                    var sessionId = sessEl ? String(sessEl.value || '').trim() : '';
                    var code = codeEl ? toEnglishDigits(String(codeEl.value || '')).replace(/\D/g, '') : '';
                    if (!sessionId || !code) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('کد پیامک را وارد کنید.');
                        return;
                    }
                    loanGuaranteeReturnVerifyOtpBtn.disabled = true;
                    var verifyPayload = {
                        otp_session: sessionId,
                        code: code
                    };
                    if (loanGuaranteeFormMode === 'edit' && loanGuaranteeEditingId) {
                        verifyPayload.guarantee_id = loanGuaranteeEditingId;
                    }
                    fetch(customerGuaranteeReturnOtpUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId, 'verify'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(verifyPayload)
                    }).then(function (r) {
                        return r.json().then(function (j) { return { ok: r.ok, json: j || {} }; }).catch(function () {
                            return { ok: r.ok, json: {} };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'تایید کد ناموفق بود.');
                        }
                        var tok = document.getElementById('loan-guarantee-return-verification-token');
                        if (tok) tok.value = String(res.json.verification_token || '');
                        guaranteeReturnOtpVerified = true;
                        var panelOk = document.getElementById('loan-guarantee-return-otp-panel');
                        var verifiedEl = document.getElementById('loan-guarantee-return-verified');
                        if (panelOk) panelOk.hidden = true;
                        if (verifiedEl) verifiedEl.hidden = false;
                        if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'احراز انجام شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'تایید کد ناموفق بود.');
                    }).finally(function () {
                        loanGuaranteeReturnVerifyOtpBtn.disabled = false;
                    });
                });
            }
            if (loanGuaranteeFileUploadBtn && loanGuaranteeAttachmentInput) {
                loanGuaranteeFileUploadBtn.addEventListener('click', function () {
                    loanGuaranteeAttachmentInput.click();
                });
            }
            if (loanGuaranteeAttachmentInput) {
                loanGuaranteeAttachmentInput.addEventListener('change', function () {
                    var file = loanGuaranteeAttachmentInput.files && loanGuaranteeAttachmentInput.files[0]
                        ? loanGuaranteeAttachmentInput.files[0]
                        : null;
                    setGuaranteeFilePreviewFromFile(file);
                });
            }
            if (loanGuaranteeFileRemoveBtn && loanGuaranteeAttachmentInput) {
                loanGuaranteeFileRemoveBtn.addEventListener('click', function () {
                    loanGuaranteeAttachmentInput.value = '';
                    resetGuaranteeFilePreview();
                    if (loanGuaranteeFormMode === 'edit') {
                        loanGuaranteeRemoveExistingAttachment = true;
                    }
                });
            }
            if (loanGuaranteeFileDownloadBtn) {
                loanGuaranteeFileDownloadBtn.addEventListener('click', function () {
                    if (!loanGuaranteeCurrentDownloadUrl) return;
                    window.open(loanGuaranteeCurrentDownloadUrl, '_blank');
                });
            }
            document.addEventListener('click', function (e) {
                var editBtn = e.target.closest('[data-guarantee-edit]');
                if (editBtn) {
                    e.preventDefault();
                    try {
                        var payload = JSON.parse(String(editBtn.getAttribute('data-guarantee-edit') || '{}'));
                        openGuaranteeFormForEdit(payload);
                    } catch (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error('داده ضمانت برای ویرایش نامعتبر است.');
                        }
                    }
                    return;
                }
                var delBtn = e.target.closest('[data-guarantee-delete-id]');
                if (!delBtn || !loanGuaranteeCurrentLoanId || !loanManageCurrentCustomerId) return;
                e.preventDefault();
                var guaranteeId = parseInt(String(delBtn.getAttribute('data-guarantee-delete-id') || '0'), 10);
                if (guaranteeId <= 0) return;
                var proceedDelete = function () {
                    fetch(customerLoanGuaranteeDeleteUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId, guaranteeId), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin'
                    }).then(function (r) {
                        return r.json().then(function (json) { return { ok: r.ok, json: json }; });
                    }).then(function (res) {
                        if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'حذف ضمانت ناموفق بود.');
                        openLoanGuaranteeModal(loanGuaranteeCurrentLoanId);
                        refreshLoanGuaranteesReportIfTabActive();
                        if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'ضمانت حذف شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'حذف ضمانت ناموفق بود.');
                    });
                };
                if (window.AdminSwal && window.AdminSwal.confirm) {
                    AdminSwal.confirm({
                        title: 'حذف ضمانت',
                        text: 'این ضمانت حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف'
                    }).then(function (result) {
                        if (result && result.isConfirmed) proceedDelete();
                    });
                } else if (window.confirm('ضمانت حذف شود؟')) {
                    proceedDelete();
                }
            });
            if (loanGuaranteeForm) {
                loanGuaranteeForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!loanManageCurrentCustomerId || !loanGuaranteeCurrentLoanId || loanGuaranteeSubmitting) return;
                    var type = String((loanGuaranteeTypeInput && loanGuaranteeTypeInput.value) || 'org_self');
                    var fd = new FormData(loanGuaranteeForm);
                    fd.set('type', type);
                    if (loanGuaranteeFormMode === 'edit') {
                        fd.append('_method', 'PUT');
                    }
                    fd.set('remove_attachment', loanGuaranteeRemoveExistingAttachment ? '1' : '0');
                    fd.delete('amount_toman');
                    if (type === 'org_other') {
                        var natGuarantorEl = document.getElementById('loan-guarantee-guarantor-national-id');
                        var natRaw = natGuarantorEl ? String(natGuarantorEl.value || '') : '';
                        var natNorm = natRaw.trim() !== ''
                            ? toEnglishDigits(natRaw).replace(/\D/g, '')
                            : '';
                        fd.set('guarantor_national_id', natNorm);
                    }

                    // فقط برای نوع «طلا» نرخ/فیلدهای طلا را ارسال کن؛ در غیر این صورت
                    // مقدار ۰ باعث اعتبارسنجی min:1 روی سرور می‌شد (تب‌های دیگر فرم جدا هستند).
                    var goldRateInputValue = 0;
                    if (type === 'gold') {
                        goldRateInputValue = parseThousandsInput(String(fd.get('gold_rate_toman') || ''));
                        fd.set('gold_rate_toman', String(goldRateInputValue));
                    } else {
                        fd.delete('gold_rate_toman');
                    }

                    if (type === 'other') {
                        var desc = String((document.getElementById('loan-guarantee-other-desc').value) || '').trim();
                        if (!desc) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('برای نوع سایر، توضیحات الزامی است.');
                            return;
                        }
                    }
                    if (type === 'gold') {
                        var selectedGoldInput = loanGuaranteeForm.querySelector('input[name="gold_item_code"]:checked');
                        var selectedGoldCode = String((selectedGoldInput && selectedGoldInput.value) || '').trim();
                        if (!selectedGoldCode) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('نوع طلا را انتخاب کنید.');
                            return;
                        }
                        if (goldRateInputValue <= 0) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('نرخ طلا را به‌صورت معتبر وارد کنید.');
                            return;
                        }
                        var weightVal = Number(loanGuaranteeForm.elements['gold_weight_gram'] ? loanGuaranteeForm.elements['gold_weight_gram'].value : 0);
                        var qtyVal = Number(loanGuaranteeForm.elements['gold_quantity'] ? loanGuaranteeForm.elements['gold_quantity'].value : 0);
                        if (selectedGoldCode === 'broken_gold' && (!(weightVal > 0))) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('برای طلای شکن، وزن طلا را وارد کنید.');
                            return;
                        }
                        if (selectedGoldCode !== 'broken_gold' && (!(qtyVal > 0))) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('برای این نوع طلا، تعداد را وارد کنید.');
                            return;
                        }
                    }

                    var markReturnedEl = document.getElementById('loan-guarantee-mark-returned');
                    var markingReturned = !!(markReturnedEl && markReturnedEl.checked);
                    var wasReturned = false;
                    if (loanGuaranteeLoadedMeta && typeof loanGuaranteeLoadedMeta === 'object') {
                        wasReturned = type === 'cheque'
                            ? !!loanGuaranteeLoadedMeta.cheque_returned
                            : !!loanGuaranteeLoadedMeta.returned;
                    }
                    if (guaranteeTypesSupportingReturn(type)) {
                        if (type === 'cheque') {
                            fd.set('cheque_returned', markingReturned ? '1' : '0');
                        } else {
                            fd.set('guarantee_returned', markingReturned ? '1' : '0');
                        }
                        if (markingReturned && !wasReturned) {
                            if (guaranteeReturnOtpEnabled) {
                                var returnTokEl = document.getElementById('loan-guarantee-return-verification-token');
                                var returnTok = returnTokEl ? String(returnTokEl.value || '').trim() : '';
                                if (!returnTok) {
                                    if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('ابتدا احراز مشتری با پیامک را برای عودت انجام دهید.');
                                    return;
                                }
                                fd.set('guarantee_return_verification_token', returnTok);
                            }
                        }
                    }

                    loanGuaranteeSubmitting = true;
                    var submitBtn = document.getElementById('loan-guarantee-submit');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'در حال ذخیره...';
                    }
                    var guaranteeEndpoint = loanGuaranteeFormMode === 'edit' && loanGuaranteeEditingId
                        ? customerLoanGuaranteeUpdateUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId, loanGuaranteeEditingId)
                        : customerLoanGuaranteesUrl(loanManageCurrentCustomerId, loanGuaranteeCurrentLoanId);
                    fetch(guaranteeEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: fd
                    }).then(function (r) {
                        return r.json().then(function (json) { return { ok: r.ok, status: r.status, json: json || {} }; }).catch(function () {
                            return { ok: r.ok, status: r.status, json: {} };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            var m = (res.json && res.json.message) ? String(res.json.message) : '';
                            if (!m && res.status === 429) m = 'درخواست زیاد؛ چند ثانیه صبر کنید.';
                            throw new Error(m || 'ذخیره ضمانت ناموفق بود.');
                        }
                        loanGuaranteeLoadedMeta = null;
                        loanGuaranteeGuarantorOtpLocked = false;
                        loanGuaranteeGuarantorOtpPhoneSnapshot = '';
                        loanGuaranteeForm.reset();
                        loanGuaranteeFormMode = 'create';
                        loanGuaranteeEditingId = null;
                        setGuaranteeType('org_self');
                        resetGuaranteeFilePreview();
                        openLoanGuaranteeModal(loanGuaranteeCurrentLoanId);
                        refreshLoanGuaranteesReportIfTabActive();
                        if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'ضمانت ثبت شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'ذخیره ضمانت ناموفق بود.');
                    }).finally(function () {
                        loanGuaranteeSubmitting = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = loanGuaranteeFormMode === 'edit' ? 'ذخیره تغییرات ضمانت' : 'ذخیره ضمانت';
                        }
                    });
                });
            }
            if (loanIsSettledCheckbox && loanSettledWrap) {
                loanIsSettledCheckbox.addEventListener('change', function () {
                    loanSettledWrap.hidden = !loanIsSettledCheckbox.checked;
                });
            }
            if (loanHasCustomInterestCheckbox && loanCustomInterestWrap) {
                loanHasCustomInterestCheckbox.addEventListener('change', function () {
                    loanCustomInterestWrap.hidden = !loanHasCustomInterestCheckbox.checked;
                    syncLoanInstallmentCalculation(true);
                });
            }
            if (loanTypeIdSelect) {
                loanTypeIdSelect.addEventListener('change', function () {
                    syncLoanCurrentInterestView();
                    syncLoanInstallmentCalculation(true);
                });
            }
            [loanAmountInput, loanInstallmentAmountInput, loanDownPaymentInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () {
                    clearLoanFieldError(el);
                    el.value = formatThousandsInputValue(el.value);
                });
                el.addEventListener('blur', function () {
                    el.value = formatThousandsInputValue(el.value);
                });
            });
            document.querySelectorAll('[data-gold-rate]').forEach(function (el) {
                el.addEventListener('input', function () {
                    el.value = formatThousandsInputValue(el.value);
                });
                el.addEventListener('blur', function () {
                    el.value = formatThousandsInputValue(el.value);
                });
            });
            goldItemOptions.forEach(function (label) {
                var input = label.querySelector('input[name="gold_item_code"]');
                if (!input) return;
                input.addEventListener('change', syncGoldFieldsByOption);
            });
            [loanStartJdateInput, loanDisbursementDueJdateInput, loanSettledJdateInput, loanTypeIdSelect, loanInstallmentsCountInput, loanInstallmentIntervalCountInput, loanInstallmentIntervalUnitSelect, loanCustomInterestRateInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () { clearLoanFieldError(el); });
                el.addEventListener('change', function () { clearLoanFieldError(el); });
            });
            if (loanAmountInput) {
                loanAmountInput.addEventListener('input', function () {
                    syncLoanInstallmentCalculation(false);
                });
            }
            if (loanInstallmentsCountInput) {
                loanInstallmentsCountInput.addEventListener('input', function () {
                    syncLoanInstallmentCalculation(false);
                });
            }
            if (loanInstallmentAmountInput) {
                loanInstallmentAmountInput.addEventListener('input', function () {
                    loanInstallmentAutoDirty = true;
                    syncLoanInstallmentCalculation(false);
                });
            }
            if (loanDownPaymentInput) {
                loanDownPaymentInput.addEventListener('input', function () {
                    loanDownPaymentAutoDirty = true;
                    syncLoanInstallmentCalculation(false);
                });
            }
            if (loanCustomInterestRateInput) {
                loanCustomInterestRateInput.addEventListener('input', function () {
                    syncLoanInstallmentCalculation(true);
                });
            }
            if (loanCreateForm) {
                var loanCreationOtpSendBtn = document.getElementById('loan-creation-otp-send');
                if (loanCreationOtpSendBtn) {
                    loanCreationOtpSendBtn.addEventListener('click', function () {
                        if (!loanManageCurrentCustomerId || !loanCreationOtpEnabled || loanFormMode !== 'create') return;
                        var mobile = toEnglishDigits(String(loanManageCurrentCustomerMobile || '')).replace(/\D/g, '');
                        if (mobile.length === 10 && mobile.charAt(0) === '9') mobile = '0' + mobile;
                        if (!/^09\d{9}$/.test(mobile)) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('موبایل معتبر برای این مشتری ثبت نشده است.');
                            return;
                        }
                        resetLoanCreationOtpUi();
                        loanCreationOtpSendBtn.disabled = true;
                        fetch(customerLoanCreationOtpSendUrl(loanManageCurrentCustomerId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                customer_name: loanManageCurrentCustomerName || ''
                            })
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, json: j || {} }; }).catch(function () {
                                return { ok: r.ok, json: {} };
                            });
                        }).then(function (res) {
                            if (!res.ok) {
                                throw new Error((res.json && res.json.message) ? res.json.message : 'ارسال کد ناموفق بود.');
                            }
                            var sessEl = document.getElementById('loan-creation-otp-session');
                            var panel = document.getElementById('loan-creation-otp-panel');
                            if (sessEl) sessEl.value = String(res.json.otp_session || '');
                            if (panel) panel.hidden = false;
                            if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'کد ارسال شد.');
                        }).catch(function (err) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'ارسال کد ناموفق بود.');
                        }).finally(function () {
                            loanCreationOtpSendBtn.disabled = false;
                        });
                    });
                }
                var loanCreationOtpVerifyBtn = document.getElementById('loan-creation-otp-verify');
                if (loanCreationOtpVerifyBtn) {
                    loanCreationOtpVerifyBtn.addEventListener('click', function () {
                        if (!loanManageCurrentCustomerId || !loanCreationOtpEnabled || loanFormMode !== 'create') return;
                        var sessEl = document.getElementById('loan-creation-otp-session');
                        var codeEl = document.getElementById('loan-creation-otp-code');
                        var sessionId = sessEl ? String(sessEl.value || '').trim() : '';
                        var code = codeEl ? toEnglishDigits(String(codeEl.value || '')).replace(/\D/g, '') : '';
                        if (!sessionId || !code) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('کد پیامک را وارد کنید.');
                            return;
                        }
                        loanCreationOtpVerifyBtn.disabled = true;
                        fetch(customerLoanCreationOtpVerifyUrl(loanManageCurrentCustomerId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                otp_session: sessionId,
                                code: code
                            })
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, json: j || {} }; }).catch(function () {
                                return { ok: r.ok, json: {} };
                            });
                        }).then(function (res) {
                            if (!res.ok) {
                                throw new Error((res.json && res.json.message) ? res.json.message : 'تایید کد ناموفق بود.');
                            }
                            var tok = document.getElementById('loan-creation-verification-token');
                            if (tok) tok.value = String(res.json.verification_token || '');
                            loanCreationOtpVerified = true;
                            var panel = document.getElementById('loan-creation-otp-panel');
                            var verifiedEl = document.getElementById('loan-creation-otp-verified');
                            if (panel) panel.hidden = true;
                            if (verifiedEl) verifiedEl.hidden = false;
                            if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || 'احراز انجام شد.');
                        }).catch(function (err) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'تایید کد ناموفق بود.');
                        }).finally(function () {
                            loanCreationOtpVerifyBtn.disabled = false;
                        });
                    });
                }
                loanCreateForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!loanManageCurrentCustomerId || loanCreateSubmitting) return;
                    clearAllLoanFieldErrors();
                    var submitBtn = loanCreateForm.querySelector('button[type="submit"]');
                    var payload = {
                        loan_start_jdate: String((loanStartJdateInput && loanStartJdateInput.value) || '').trim(),
                        disbursement_due_jdate: String((loanDisbursementDueJdateInput && loanDisbursementDueJdateInput.value) || '').trim(),
                        loan_type_id: parseInt(String((loanTypeIdSelect && loanTypeIdSelect.value) || '0'), 10),
                        amount_toman: parseThousandsInput((loanAmountInput && loanAmountInput.value) || ''),
                        installments_count: parseInt(String((loanInstallmentsCountInput && loanInstallmentsCountInput.value) || '0'), 10),
                        installment_interval_count: parseInt(String((loanInstallmentIntervalCountInput && loanInstallmentIntervalCountInput.value) || '0'), 10),
                        installment_interval_unit: String((loanInstallmentIntervalUnitSelect && loanInstallmentIntervalUnitSelect.value) || 'monthly'),
                        installment_amount_toman: parseThousandsInput((loanInstallmentAmountInput && loanInstallmentAmountInput.value) || ''),
                        down_payment_toman: parseThousandsInput((loanDownPaymentInput && loanDownPaymentInput.value) || ''),
                        sub_file_number: String((document.getElementById('loan-sub-file-number').value) || '').trim(),
                        description: String((document.getElementById('loan-description').value) || '').trim(),
                        is_settled: loanIsSettledCheckbox && loanIsSettledCheckbox.checked,
                        settled_jdate: String((loanSettledJdateInput && loanSettledJdateInput.value) || '').trim(),
                        has_custom_interest_rate: loanHasCustomInterestCheckbox && loanHasCustomInterestCheckbox.checked,
                        custom_interest_rate: String((loanCustomInterestRateInput && loanCustomInterestRateInput.value) || '').trim()
                    };
                    if (!payload.loan_start_jdate || !payload.loan_type_id || payload.amount_toman <= 0 || payload.installments_count <= 0 || payload.installment_amount_toman <= 0 || payload.installment_interval_count <= 0) {
                        if (!payload.loan_start_jdate) setLoanFieldError(loanStartJdateInput, 'تکمیل این فیلد ضروری است.');
                        if (!payload.loan_type_id) setLoanFieldError(loanTypeIdSelect, 'انتخاب نوع وام ضروری است.');
                        if (payload.amount_toman <= 0) setLoanFieldError(loanAmountInput, 'وارد کردن مبلغ معتبر ضروری است.');
                        if (payload.installments_count <= 0) setLoanFieldError(loanInstallmentsCountInput, 'تعداد اقساط باید بیشتر از صفر باشد.');
                        if (payload.installment_interval_count <= 0) setLoanFieldError(loanInstallmentIntervalCountInput, 'فاصله اقساط باید بیشتر از صفر باشد.');
                        if (!payload.installment_interval_unit) setLoanFieldError(loanInstallmentIntervalUnitSelect, 'انتخاب محدوده زمانی ضروری است.');
                        if (payload.installment_amount_toman <= 0) setLoanFieldError(loanInstallmentAmountInput, 'مبلغ هر قسط باید معتبر باشد.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('لطفاً فیلدهای الزامی ثبت وام را کامل کنید.');
                        return;
                    }
                    var selectedLoanType = selectedLoanTypeData();
                    if (selectedLoanType) {
                        if (selectedLoanType.installment_gap_unit && payload.installment_interval_unit !== String(selectedLoanType.installment_gap_unit)) {
                            setLoanFieldError(loanInstallmentIntervalUnitSelect, 'بازه اقساط باید مطابق نوع وام باشد.');
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('بازه اقساط با نوع وام انتخابی سازگار نیست.');
                            return;
                        }
                        var maxAmount = Number(selectedLoanType.max_loan_amount || 0);
                        if (Number.isFinite(maxAmount) && maxAmount > 0 && payload.amount_toman > maxAmount) {
                            setLoanFieldError(loanAmountInput, 'مبلغ از سقف نوع وام بیشتر است.');
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('مبلغ وام بیشتر از سقف نوع وام است.');
                            return;
                        }
                        var maxGap = parseInt(String(selectedLoanType.max_installment_gap || '0'), 10);
                        if (Number.isFinite(maxGap) && maxGap > 0 && payload.installment_interval_count > maxGap) {
                            setLoanFieldError(loanInstallmentIntervalCountInput, 'فاصله اقساط از حداکثر مجاز نوع وام بیشتر است.');
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('فاصله اقساط از حد مجاز نوع وام بیشتر است.');
                            return;
                        }
                        if (!isRepaymentAllowedByLoanType(selectedLoanType, payload.amount_toman, payload.installments_count, payload.installment_interval_count, payload.installment_interval_unit)) {
                            setLoanFieldError(loanInstallmentsCountInput, 'دوره بازپرداخت با قوانین نوع وام سازگار نیست.');
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('دوره بازپرداخت انتخابی در نوع وام مجاز نیست.');
                            return;
                        }
                    }
                    if (payload.down_payment_toman > payload.amount_toman) {
                        setLoanFieldError(loanDownPaymentInput, 'پیش‌پرداخت نمی‌تواند از مبلغ وام بیشتر باشد.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('مبلغ پیش‌پرداخت معتبر نیست.');
                        return;
                    }
                    if (payload.is_settled && !payload.settled_jdate) {
                        setLoanFieldError(loanSettledJdateInput, 'با فعال بودن تسویه، تاریخ تسویه ضروری است.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('تاریخ تسویه را تکمیل کنید.');
                        return;
                    }
                    if (payload.has_custom_interest_rate && String(payload.custom_interest_rate || '').trim() === '') {
                        setLoanFieldError(loanCustomInterestRateInput, 'با فعال بودن تغییر بهره، این فیلد ضروری است.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('درصد بهره جدید را تکمیل کنید.');
                        return;
                    }
                    var selectedInterestRate = activeInterestRatePercent();
                    var calculatedProfit = selectedLoanType
                        ? loanProfitToman(payload.amount_toman, selectedInterestRate, String(selectedLoanType.profit_calculation_method || 'monthly'), payload.installments_count, payload.installment_interval_count, payload.installment_interval_unit)
                        : 0;
                    var loanAllocation = allocateLoanInstallmentAmounts(
                        payload.amount_toman,
                        calculatedProfit,
                        payload.down_payment_toman,
                        payload.installments_count,
                        loanInstallmentRounding
                    );
                    if (loanFormMode === 'create') {
                        payload.installment_amount_toman = loanAllocation.base;
                        payload.down_payment_toman = loanAllocation.adjustedDownPayment;
                    }
                    var payableCap = loanAllocation.payable > 0
                        ? loanAllocation.payable
                        : Math.max(0, (payload.amount_toman + calculatedProfit) - payload.down_payment_toman);
                    var installmentSum = loanAllocation.sum > 0
                        ? loanAllocation.sum
                        : (payload.installment_amount_toman * payload.installments_count);
                    if (installmentSum > payableCap) {
                        setLoanFieldError(loanInstallmentAmountInput, 'مبلغ هر قسط زیاد است؛ جمع اقساط از مبلغ وام بیشتر شده.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('جمع مبلغ اقساط نباید از مبلغ قابل بازپرداخت (اصل + بهره - پیش‌پرداخت) بیشتر باشد.');
                        return;
                    }
                    if (loanCreationOtpEnabled && loanFormMode === 'create') {
                        var otpTokEl = document.getElementById('loan-creation-verification-token');
                        var otpTok = otpTokEl ? String(otpTokEl.value || '').trim() : '';
                        if (!otpTok) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('ابتدا احراز هویت مشتری با پیامک را انجام دهید.');
                            var otpSection = document.getElementById('loan-creation-otp-section');
                            if (otpSection && otpSection.hidden) syncLoanCreationOtpSectionVisibility();
                            return;
                        }
                    }

                    function submitLoanCreate(finalPayload) {
                        if (loanCreationOtpEnabled && loanFormMode === 'create') {
                            var otpTokElSubmit = document.getElementById('loan-creation-verification-token');
                            var otpTokSubmit = otpTokElSubmit ? String(otpTokElSubmit.value || '').trim() : '';
                            if (otpTokSubmit) finalPayload.customer_verification_token = otpTokSubmit;
                        }
                        loanCreateSubmitting = true;
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'در حال ثبت...';
                        }
                        var endpoint = loanFormMode === 'edit' && loanEditingFileId
                            ? customerLoanUpdateUrl(loanManageCurrentCustomerId, loanEditingFileId)
                            : customerLoanStoreUrl(loanManageCurrentCustomerId);
                        var method = loanFormMode === 'edit' && loanEditingFileId ? 'PUT' : 'POST';
                        fetch(endpoint, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(finalPayload)
                        }).then(function (r) {
                            return r.json().then(function (json) {
                                return { ok: r.ok, json: json };
                            });
                        }).then(function (res) {
                            if (!res.ok) {
                                throw new Error((res.json && res.json.message) ? res.json.message : 'ثبت پرونده ناموفق بود.');
                            }
                            var key = String(loanManageCurrentCustomerId);
                            if (!loanManageMap[key]) {
                                loanManageMap[key] = { loan_files: [], loan_count: 0, loan_total_with_profit: 0, loan_remaining_installments: 0 };
                            }
                            if (loanFormMode === 'edit' && loanEditingFileId) {
                                var currentRows = loanManageMap[key].loan_files || [];
                                loanManageMap[key].loan_files = currentRows.map(function (x) {
                                    return Number(x.id || 0) === Number(loanEditingFileId) ? res.json.loan_file : x;
                                });
                            } else {
                                loanManageMap[key].loan_files = [res.json.loan_file].concat(loanManageMap[key].loan_files || []);
                            }
                            renderLoanFilesForCustomer(loanManageCurrentCustomerId);
                            closeLoanCreateModal();
                            resetLoanCreationOtpUi();
                            if (window.AdminSwal && window.AdminSwal.success) AdminSwal.success(res.json.message || (loanFormMode === 'edit' ? 'پرونده وام ویرایش شد.' : 'پرونده وام ثبت شد.'));
                        }).catch(function (err) {
                            if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error(err.message || 'ثبت پرونده وام ناموفق بود.');
                        }).finally(function () {
                            loanCreateSubmitting = false;
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = loanFormMode === 'edit' ? 'ذخیره تغییرات' : 'ثبت وام';
                            }
                        });
                    }

                    if (typeof Swal === 'undefined') {
                        submitLoanCreate(payload);
                        return;
                    }

                    var amountText = formatToman(payload.amount_toman) + ' تومان';
                    var installmentText = formatToman(payload.installment_amount_toman) + ' تومان';
                    var defaultLoanSms =
                        'سامانه ' + (appDisplayName || '') + '\n' +
                        'مشتری گرامی ' + (loanManageCurrentCustomerName || '') + '\n' +
                        'ثبت پرونده وام جدید انجام شد.\n' +
                        'مبلغ وام: ' + amountText + '\n' +
                        'مبلغ هر قسط: ' + installmentText;

                    var templateOptionsHtml = '<option value="">بدون قالب (متن آزاد)</option>';
                    quickSmsTemplatesData.forEach(function (tpl) {
                        templateOptionsHtml += '<option value="' + String(tpl.id) + '">' + escapeHtmlText((tpl.title || '') + ' (' + (tpl.category || '') + ')') + '</option>';
                    });

                    Swal.fire({
                        icon: 'question',
                        title: 'ارسال پیامک پس از ثبت وام',
                        width: 540,
                        customClass: {
                            popup: 'wallet-sms-swal',
                            title: 'wallet-sms-swal-title',
                        },
                        html:
                            '<div style="text-align:right">' +
                            '<div style="font-size:.73rem;color:#64748b;margin-bottom:.3rem">موبایل مشتری: ' + escapeHtmlText(loanManageCurrentCustomerMobile || '—') + '</div>' +
                            '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">قالب پیامک</label>' +
                            '<select id="loan-create-sms-template" class="swal2-select" style="width:100%;margin:0 0 .35rem;min-height:2.1rem">' + templateOptionsHtml + '</select>' +
                            '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">متن پیامک (قابل ویرایش)</label>' +
                            '<textarea id="loan-create-sms-text" class="swal2-textarea" style="width:100%;margin:0;min-height:88px;padding:.45rem .55rem">' + escapeHtmlText(defaultLoanSms) + '</textarea>' +
                            '</div>',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'ذخیره و ارسال پیامک',
                        denyButtonText: 'فقط ذخیره',
                        cancelButtonText: 'لغو',
                        reverseButtons: true,
                        focusCancel: false,
                        didOpen: function () {
                            var p = document.querySelector('.swal2-popup');
                            if (p) p.setAttribute('dir', 'rtl');
                            var selectEl = document.getElementById('loan-create-sms-template');
                            var txtEl = document.getElementById('loan-create-sms-text');
                            if (selectEl && txtEl) {
                                selectEl.addEventListener('change', function () {
                                    var selectedId = parseInt(String(selectEl.value || '0'), 10);
                                    if (!selectedId) return;
                                    var tpl = quickSmsTemplatesData.find(function (x) {
                                        return parseInt(String(x.id), 10) === selectedId;
                                    });
                                    if (!tpl) return;
                                    txtEl.value = renderWalletTemplateText(tpl.body || '', {
                                        store_name: document.title || 'سامانه',
                                        customer_name: loanManageCurrentCustomerName,
                                        loan_amount: amountText,
                                        installment_amount: installmentText
                                    });
                                });
                            }
                        },
                        preConfirm: function () {
                            var txtEl = document.getElementById('loan-create-sms-text');
                            var selectEl = document.getElementById('loan-create-sms-template');
                            return {
                                sms_text: txtEl ? String(txtEl.value || '').trim() : '',
                                sms_template_id: selectEl ? (selectEl.value || '') : ''
                            };
                        }
                    }).then(function (result) {
                        if (result.isDismissed) {
                            return;
                        }
                        if (result.isConfirmed) {
                            payload.send_sms = true;
                            payload.sms_text = (result.value && result.value.sms_text) ? result.value.sms_text : '';
                            payload.sms_template_id = (result.value && result.value.sms_template_id) ? result.value.sms_template_id : '';
                            submitLoanCreate(payload);
                            return;
                        }
                        if (result.isDenied) {
                            payload.send_sms = false;
                            submitLoanCreate(payload);
                        }
                    });
                });
            }

            if (walletOpenAdjustBtn) {
                walletOpenAdjustBtn.addEventListener('click', function () {
                    if (!walletCurrentCustomerId) return;
                    if (walletState.is_locked) {
                        if (window.AdminSwal && window.AdminSwal.warning) {
                            AdminSwal.warning('کیف پول قفل است. برای ثبت تراکنش ابتدا قفل را باز کنید.');
                        }
                        return;
                    }
                    openWalletAdjustModal();
                });
            }

            if (walletOpenTransactionsBtn) {
                walletOpenTransactionsBtn.addEventListener('click', function () {
                    if (!walletCurrentCustomerId) return;
                    openWalletTransactionsModal();
                });
            }

            if (walletExportExcelBtn) {
                walletExportExcelBtn.addEventListener('click', function () {
                    if (!walletCurrentCustomerId) return;
                    window.location.href = walletTransactionsExportUrl(walletCurrentCustomerId);
                });
            }

            if (walletLockToggleBtn) {
                walletLockToggleBtn.addEventListener('click', function () {
                    if (!walletCurrentCustomerId) return;
                    if (walletLockSubmitting) return;
                    walletLockSubmitting = true;
                    walletLockToggleBtn.disabled = true;
                    var nextLockState = !walletState.is_locked;
                    fetch(walletLockUrl(walletCurrentCustomerId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ is_locked: nextLockState ? 1 : 0 })
                    }).then(function (r) {
                        return r.json().then(function (json) {
                            return { ok: r.ok, json: json };
                        });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'bad');
                        }
                        walletState = res.json.wallet || walletState;
                        setWalletVisualState();
                        if (window.AdminSwal && window.AdminSwal.success) {
                            AdminSwal.success(res.json.message || 'وضعیت کیف پول به‌روزرسانی شد.');
                        }
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error(err.message || 'به‌روزرسانی وضعیت قفل ناموفق بود.');
                        }
                    }).finally(function () {
                        walletLockSubmitting = false;
                        walletLockToggleBtn.disabled = false;
                    });
                });
            }

            if (walletAdjustForm) {
                walletAdjustForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!walletCurrentCustomerId) return;
                    if (walletAdjustSubmitting) return;
                    walletAdjustSubmitting = true;
                    var walletSubmitBtn = walletAdjustForm.querySelector('button[type="submit"]');
                    if (walletSubmitBtn) {
                        walletSubmitBtn.disabled = true;
                        walletSubmitBtn.textContent = 'در حال پردازش...';
                    }

                    var formData = new FormData(walletAdjustForm);
                    var amountRaw = toEnglishDigits(String(formData.get('amount_toman') || '')).replace(/[^\d]/g, '');
                    var amount = amountRaw === '' ? 0 : parseInt(amountRaw, 10);
                    var direction = String(formData.get('direction') || 'deposit');
                    var description = String(formData.get('description') || '').trim();
                    var payload = {
                        direction: direction,
                        amount_toman: amount,
                        description: description
                    };

                    if (!amount || amount <= 0) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error('مبلغ تراکنش معتبر نیست.');
                        }
                        walletAdjustSubmitting = false;
                        if (walletSubmitBtn) {
                            walletSubmitBtn.disabled = false;
                            walletSubmitBtn.textContent = 'ذخیره تراکنش';
                        }
                        return;
                    }

                    var amountText = formatToman(amount) + ' تومان';
                    var predictedBalance = direction === 'deposit'
                        ? (walletState.balance_toman + amount)
                        : (walletState.balance_toman - amount);
                    var dirFa = direction === 'deposit' ? 'واریز' : 'برداشت';
                    var smsVars = {
                        store_name: document.title || 'سامانه',
                        customer_name: walletCurrentCustomerName,
                        paid_amount: amountText,
                        installment_amount: amountText,
                        remaining_loan: formatToman(Math.max(0, predictedBalance)) + ' تومان'
                    };
                    var defaultSmsText =
                        'سامانه\n' +
                        'مشتری: ' + walletCurrentCustomerName + '\n' +
                        'نوع تراکنش: ' + dirFa + '\n' +
                        'مبلغ: ' + amountText + '\n' +
                        'موجودی کیف پول: ' + formatToman(Math.max(0, predictedBalance)) + ' تومان';

                    if (typeof Swal === 'undefined') {
                        walletAdjustSubmitting = false;
                        if (walletSubmitBtn) {
                            walletSubmitBtn.disabled = false;
                            walletSubmitBtn.textContent = 'ذخیره تراکنش';
                        }
                        return;
                    }

                    var templateOptionsHtml = '<option value="">بدون قالب (متن آزاد)</option>';
                    walletSmsTemplates.forEach(function (tpl) {
                        templateOptionsHtml += '<option value="' + String(tpl.id) + '">' + escapeHtmlText((tpl.title || '') + ' (' + (tpl.category || '') + ')') + '</option>';
                    });

                    Swal.fire({
                        icon: 'question',
                        title: 'ارسال پیامک پس از ثبت تراکنش',
                        width: 540,
                        customClass: {
                            popup: 'wallet-sms-swal',
                            title: 'wallet-sms-swal-title',
                        },
                        html:
                            '<div style="text-align:right">' +
                            '<div style="font-size:.73rem;color:#64748b;margin-bottom:.3rem">موبایل مشتری: ' + escapeHtmlText(walletCurrentCustomerMobile || '—') + '</div>' +
                            '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">قالب پیامک</label>' +
                            '<select id="wallet-sms-template" class="swal2-select" style="width:100%;margin:0 0 .35rem;min-height:2.1rem">' + templateOptionsHtml + '</select>' +
                            '<label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.2rem">متن پیامک (قابل ویرایش)</label>' +
                            '<textarea id="wallet-sms-text" class="swal2-textarea" style="width:100%;margin:0;min-height:88px;padding:.45rem .55rem">' + escapeHtmlText(defaultSmsText) + '</textarea>' +
                            '</div>',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'ذخیره و ارسال پیامک',
                        denyButtonText: 'فقط ذخیره',
                        cancelButtonText: 'لغو',
                        reverseButtons: true,
                        focusCancel: false,
                        didOpen: function () {
                            var p = document.querySelector('.swal2-popup');
                            if (p) p.setAttribute('dir', 'rtl');

                            var selectEl = document.getElementById('wallet-sms-template');
                            var txtEl = document.getElementById('wallet-sms-text');
                            if (selectEl && txtEl) {
                                selectEl.addEventListener('change', function () {
                                    var selectedId = parseInt(String(selectEl.value || '0'), 10);
                                    if (!selectedId) return;
                                    var tpl = walletSmsTemplates.find(function (x) { return parseInt(String(x.id), 10) === selectedId; });
                                    if (!tpl) return;
                                    txtEl.value = renderWalletTemplateText(tpl.body || '', smsVars);
                                });
                            }
                        },
                        preConfirm: function () {
                            var txtEl = document.getElementById('wallet-sms-text');
                            var selectEl = document.getElementById('wallet-sms-template');
                            return {
                                sms_text: txtEl ? String(txtEl.value || '').trim() : '',
                                sms_template_id: selectEl ? (selectEl.value || '') : ''
                            };
                        },
                        preDeny: function () {
                            return {
                                sms_text: '',
                                sms_template_id: ''
                            };
                        }
                    }).then(function (result) {
                        if (result.isDismissed) {
                            walletAdjustSubmitting = false;
                            if (walletSubmitBtn) {
                                walletSubmitBtn.disabled = false;
                                walletSubmitBtn.textContent = 'ذخیره تراکنش';
                            }
                            return;
                        }

                        if (result.isConfirmed) {
                            payload.send_sms = true;
                            payload.sms_text = (result.value && result.value.sms_text) ? result.value.sms_text : '';
                            payload.sms_template_id = (result.value && result.value.sms_template_id) ? result.value.sms_template_id : '';
                        } else if (result.isDenied) {
                            payload.send_sms = false;
                        } else {
                            walletAdjustSubmitting = false;
                            if (walletSubmitBtn) {
                                walletSubmitBtn.disabled = false;
                                walletSubmitBtn.textContent = 'ذخیره تراکنش';
                            }
                            return;
                        }

                        var requestId = (window.crypto && window.crypto.randomUUID)
                            ? window.crypto.randomUUID()
                            : ('req-' + Date.now() + '-' + Math.random().toString(16).slice(2));
                        payload.request_id = requestId;

                        fetch(walletAdjustUrl(walletCurrentCustomerId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        }).then(function (r) {
                            return r.json().then(function (json) {
                                return { ok: r.ok, json: json };
                            });
                        }).then(function (res) {
                            if (!res.ok) {
                                throw new Error((res.json && res.json.message) ? res.json.message : 'bad');
                            }
                            walletState = res.json.wallet || walletState;
                            setWalletVisualState();
                            closeWalletAdjustModal();
                            openWalletTransactionsModal();
                            if (window.AdminSwal && window.AdminSwal.success) {
                                AdminSwal.success(res.json.message || 'تراکنش ثبت شد.');
                            }
                        }).catch(function (err) {
                            if (window.AdminSwal && window.AdminSwal.error) {
                                AdminSwal.error(err.message || 'ثبت تراکنش ناموفق بود.');
                            }
                        }).finally(function () {
                            walletAdjustSubmitting = false;
                            if (walletSubmitBtn) {
                                walletSubmitBtn.disabled = false;
                                walletSubmitBtn.textContent = 'ذخیره تراکنش';
                            }
                        });
                    });
                });
            }

            if (walletAmountInput) {
                walletAmountInput.addEventListener('input', function () {
                    walletAmountInput.value = formatThousandsInputValue(walletAmountInput.value);
                });
                walletAmountInput.addEventListener('blur', function () {
                    walletAmountInput.value = formatThousandsInputValue(walletAmountInput.value);
                });
            }

            if (quickSmsTemplate && quickSmsText) {
                quickSmsTemplate.addEventListener('change', function () {
                    var tplId = parseInt(String(quickSmsTemplate.value || '0'), 10);
                    if (!tplId) {
                        return;
                    }
                    var tpl = quickSmsTemplatesData.find(function (x) {
                        return parseInt(String(x.id), 10) === tplId;
                    });
                    if (!tpl) {
                        return;
                    }
                    quickSmsText.value = renderWalletTemplateText(tpl.body || '', {
                        store_name: document.title || 'سامانه',
                        customer_name: quickSmsCurrentCustomerName
                    });
                });
            }

            if (quickSmsForm) {
                quickSmsForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!quickSmsCurrentCustomerId || quickSmsSubmitting) return;
                    quickSmsSubmitting = true;
                    var submitBtn = quickSmsForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'در حال ارسال...';
                    }

                    var payload = {
                        sms_type: quickSmsCurrentType || 'welcome',
                        sms_template_id: quickSmsTemplate ? String(quickSmsTemplate.value || '') : '',
                        sms_text: quickSmsText ? String(quickSmsText.value || '').trim() : ''
                    };
                    if (quickSmsCurrentInstallmentId != null && quickSmsCurrentInstallmentId > 0) {
                        payload.installment_id = quickSmsCurrentInstallmentId;
                    }
                    fetch(quickSmsUrl(quickSmsCurrentCustomerId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        return r.json().then(function (json) { return { ok: r.ok, json: json }; });
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.json && res.json.message) ? res.json.message : 'ارسال پیامک ناموفق بود.');
                        }
                        closeQuickSmsModal();
                        if (window.AdminSwal && window.AdminSwal.success) {
                            AdminSwal.success(res.json.message || 'پیامک ارسال شد.');
                        }
                        if (quickSmsCurrentInstallmentId && ['installment_pre_due', 'installment_due', 'installment_overdue', 'installment_thanks'].indexOf(quickSmsCurrentType) !== -1) {
                            reloadActiveLoanInstallmentsQuiet();
                        }
                    }).catch(function (err) {
                        if (window.AdminSwal && window.AdminSwal.error) {
                            AdminSwal.error(err.message || 'ارسال پیامک ناموفق بود.');
                        }
                    }).finally(function () {
                        quickSmsSubmitting = false;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'ارسال پیامک';
                        }
                    });
                });
            }

            function initPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    return;
                }
                destroyCustPickers();
                window.jQuery('#cust-membership-jdate, #cust-birth-jdate').each(function () {
                    var $el = window.jQuery(this);
                    $el.pDatepicker({
                        format: 'YYYY/MM/DD',
                        autoClose: true,
                        initialValue: false,
                        calendarType: 'persian',
                        initialValueType: 'persian',
                        toolbox: { calendarSwitch: false }
                    });
                });
            }

            function initLoanPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    return;
                }
                window.jQuery('#loan-start-jdate, #loan-disbursement-due-jdate, #loan-settled-jdate').each(function () {
                    var $el = window.jQuery(this);
                    try {
                        if ($el.data('datepicker')) {
                            $el.pDatepicker('destroy');
                        }
                    } catch (err) { /* noop */ }
                    $el.pDatepicker({
                        format: 'YYYY/MM/DD',
                        autoClose: true,
                        initialValue: false,
                        calendarType: 'persian',
                        initialValueType: 'persian',
                        toolbox: { calendarSwitch: false }
                    });
                });
            }

            if (window.jQuery) {
                window.jQuery(function () { initPickers(); });
            }

            function bindRemove(scope, selector, attr) {
                scope.querySelectorAll(selector).forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var row = btn.closest(attr);
                        if (row) row.remove();
                    });
                });
            }
            bindRemove(document, '[data-remove-bank]', '[data-bank-row]');
            bindRemove(document, '[data-remove-ref]', '[data-ref-row]');

            function addBankRow() {
                var i = bankIndex++;
                var div = document.createElement('div');
                div.className = 'cust-repeat-row';
                div.setAttribute('data-bank-row', '');
                div.innerHTML =
                    '<div class="cust-field"><label>شماره کارت / حساب / شبا</label>' +
                    '<input name="accounts[' + i + '][account_identifier]" placeholder="مثلاً شبا یا شماره کارت"></div>' +
                    '<div class="cust-field"><label>بانک</label><input name="accounts[' + i + '][bank_name]"></div>' +
                    '<div class="cust-field"><label>شعبه</label><input name="accounts[' + i + '][branch_name]"></div>' +
                    '<button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>';
                bankContainer.appendChild(div);
                div.querySelector('[data-remove-bank]').addEventListener('click', function () { div.remove(); });
            }

            function addRefRow() {
                var i = refIndex++;
                var div = document.createElement('div');
                div.className = 'cust-ref-row';
                div.setAttribute('data-ref-row', '');
                div.innerHTML =
                    '<div class="cust-field"><label>نام</label><input name="referrers[' + i + '][first_name]"></div>' +
                    '<div class="cust-field"><label>نام خانوادگی</label><input name="referrers[' + i + '][last_name]"></div>' +
                    '<div class="cust-field"><label>شماره تماس</label><input name="referrers[' + i + '][phone]" placeholder="09xxxxxxxxx"></div>' +
                    '<button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>';
                refContainer.appendChild(div);
                div.querySelector('[data-remove-ref]').addEventListener('click', function () { div.remove(); });
            }

            if (bankBtn) bankBtn.addEventListener('click', addBankRow);
            if (refBtn) refBtn.addEventListener('click', addRefRow);

            if (sendChk && sendHidden) {
                sendChk.addEventListener('change', function () {
                    sendHidden.value = sendChk.checked ? '1' : '0';
                });
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (skipPrompt) {
                        skipPrompt = false;
                        return;
                    }
                    if (customerFormSubmitting) {
                        e.preventDefault();
                        return;
                    }
                    if (!sendHidden || sendHidden.value === '1') {
                        customerFormSubmitting = true;
                        setCustomerSubmitLoading(true);
                        return;
                    }
                    e.preventDefault();
                    if (typeof Swal === 'undefined') {
                        customerFormSubmitting = true;
                        setCustomerSubmitLoading(true);
                        skipPrompt = true;
                        form.submit();
                        return;
                    }
                    Swal.fire({
                        icon: 'question',
                        title: 'ارسال پیامک',
                        text: 'آیا می‌خواهید نام کاربری و رمز عبور برای مشتری پیامک شود؟',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'بله، ارسال و ذخیره',
                        denyButtonText: 'خیر، فقط ذخیره',
                        cancelButtonText: 'عدم ذخیره',
                        reverseButtons: true,
                        allowOutsideClick: false,
                        focusCancel: false,
                        didOpen: function () {
                            var p = document.querySelector('.swal2-popup');
                            if (p) {
                                p.setAttribute('dir', 'rtl');
                            }
                        }
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            sendHidden.value = '1';
                            customerFormSubmitting = true;
                            setCustomerSubmitLoading(true);
                            skipPrompt = true;
                            form.requestSubmit();
                            return;
                        }
                        if (result.isDenied) {
                            sendHidden.value = '0';
                            customerFormSubmitting = true;
                            setCustomerSubmitLoading(true);
                            skipPrompt = true;
                            form.requestSubmit();
                            return;
                        }
                        // cancel، ESC یا بستن: هیچ‌چیز ذخیره نشود
                        customerFormSubmitting = false;
                        setCustomerSubmitLoading(false);
                    });
                });
            }

            var custOpsBoxes = Array.from(document.querySelectorAll('[data-cust-ops]'));
            var custLeaveTimer = null;

            function placeCustMenu(toggle, menu) {
                var rect = toggle.getBoundingClientRect();
                var gap = 6;
                menu.style.left = '0px';
                menu.style.top = '0px';
                menu.hidden = false;
                var menuRect = menu.getBoundingClientRect();
                var left = rect.right - menuRect.width;
                if (left < 8) left = 8;
                if (left + menuRect.width > window.innerWidth - 8) {
                    left = window.innerWidth - menuRect.width - 8;
                }
                var top = rect.bottom + gap;
                if (top + menuRect.height > window.innerHeight - 8) {
                    top = rect.top - menuRect.height - gap;
                }
                if (top < 8) top = 8;
                menu.style.left = left + 'px';
                menu.style.top = top + 'px';
            }

            function closeAllCustMenus() {
                custOpsBoxes.forEach(function (box) {
                    var m = box.querySelector('[data-cust-ops-menu]');
                    var t = box.querySelector('[data-cust-ops-toggle]');
                    if (m) m.hidden = true;
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }

            custOpsBoxes.forEach(function (box) {
                var toggle = box.querySelector('[data-cust-ops-toggle]');
                var menu = box.querySelector('[data-cust-ops-menu]');
                if (!toggle || !menu) return;

                function openThis() {
                    closeAllCustMenus();
                    placeCustMenu(toggle, menu);
                    toggle.setAttribute('aria-expanded', 'true');
                }

                function closeThis() {
                    menu.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }

                box.addEventListener('mouseenter', function () {
                    if (custLeaveTimer) {
                        clearTimeout(custLeaveTimer);
                        custLeaveTimer = null;
                    }
                    openThis();
                });

                box.addEventListener('mouseleave', function () {
                    custLeaveTimer = setTimeout(closeThis, 220);
                });

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isHidden = menu.hidden;
                    closeAllCustMenus();
                    if (isHidden) {
                        placeCustMenu(toggle, menu);
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });

                menu.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            });

            document.addEventListener('click', function () {
                closeAllCustMenus();
            });

            window.addEventListener('resize', function () {
                custOpsBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-cust-ops-menu]');
                    var toggle = box.querySelector('[data-cust-ops-toggle]');
                    if (!menu || !toggle || menu.hidden) return;
                    placeCustMenu(toggle, menu);
                });
            });

            window.addEventListener('scroll', function () {
                custOpsBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-cust-ops-menu]');
                    var toggle = box.querySelector('[data-cust-ops-toggle]');
                    if (!menu || !toggle || menu.hidden) return;
                    placeCustMenu(toggle, menu);
                });
            }, true);

            document.querySelectorAll('[data-cust-delete-form]').forEach(function (formEl) {
                formEl.addEventListener('submit', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!window.AdminSwal || !window.AdminSwal.confirm) {
                        formEl.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف مشتری',
                        text: 'این مشتری و اطلاعات مرتبطش حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            formEl.submit();
                        }
                    });
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                closeAllCustMenus();
                if (overlay && !overlay.hidden) closeModal();
                if (walletAdjustOverlay && !walletAdjustOverlay.hidden) closeWalletAdjustModal();
                if (walletTransOverlay && !walletTransOverlay.hidden) closeWalletTransactionsModal();
                if (walletModalOverlay && !walletModalOverlay.hidden) closeWalletModal();
                if (quickSmsOverlay && !quickSmsOverlay.hidden) closeQuickSmsModal();
                if (loanManageOverlay && !loanManageOverlay.hidden) closeLoanManageModal();
            });

            (function openLoanManageFromQueryIfNeeded() {
                var params = new URLSearchParams(window.location.search);
                if (params.get('open_loan_manage') !== '1') return;
                var cid = parseInt(params.get('customer_id') || '0', 10);
                if (!cid) return;
                var ctxUrl = custListBaseUrl + '/' + encodeURIComponent(String(cid)) + '/loan-manage-modal-context';
                fetch(ctxUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    var id = parseInt(String(data.id || 0), 10);
                    if (!id) return;
                    openLoanManageModal(id, String(data.name || ''), String(data.mobile || ''));
                    var loanInstOpen = parseInt(params.get('open_loan_installments') || '0', 10);
                    if (loanInstOpen > 0) {
                        openLoanInstallmentsModal(loanInstOpen);
                    }
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                }).catch(function () {
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error('امکان باز کردن مدیریت وام برای این مشتری وجود ندارد.');
                    }
                });
            })();

            @if ($errors->any() && ! session('open_edit_customer_id'))
            custFormMode = 'create';
            removeMethodField();
            form.action = custStoreUrl;
            form.setAttribute('method', 'post');
            if (modalTitle) modalTitle.textContent = 'افزودن مشتری جدید';
            if (modalDesc) modalDesc.textContent = 'فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.';
            if (pwdReq) pwdReq.style.display = '';
            if (pwdHint) pwdHint.hidden = true;
            if (pwdInput) { pwdInput.required = true; }
            setCustProfileOptionalFieldsRequired(false);
            syncUsername();
            (function () {
                var membershipJdateEl = document.getElementById('cust-membership-jdate');
                if (membershipJdateEl && !String(membershipJdateEl.value || '').trim() && adminTodayJdate) {
                    membershipJdateEl.value = adminTodayJdate;
                }
            })();
            openModal();
            @elseif (session('open_edit_customer_id'))
            custFormMode = 'edit';
            addMethodPut();
            form.action = custUpdateUrl({{ (int) session('open_edit_customer_id') }});
            if (modalTitle) modalTitle.textContent = 'ویرایش مشتری';
            if (modalDesc) modalDesc.textContent = 'اطلاعات مشتری را ویرایش کنید. رمز عبور را فقط در صورت تغییر پر کنید.';
            if (pwdReq) pwdReq.style.display = 'none';
            if (pwdHint) pwdHint.hidden = false;
            if (pwdInput) { pwdInput.value = ''; pwdInput.required = false; }
            setCustProfileOptionalFieldsRequired(false);
            syncUsername();
            openModal();
            @endif

            window.adminCustomersOpenEditModal = openEditModal;
        })();

        (function openCustomerEditFromQueryIfNeeded() {
            var params = new URLSearchParams(window.location.search);
            if (params.get('open_customer_edit') !== '1') return;
            var cid = parseInt(params.get('customer_id') || '0', 10);
            if (!cid) return;
            var fn = window.adminCustomersOpenEditModal;
            if (typeof fn !== 'function') return;
            fn(cid);
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        })();
    </script>
@endpush
