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
        .cust-search { flex: 1 1 16rem; max-width: 22rem; }
        .cust-search input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.72rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.84rem;
        }
        .cust-card {
            border: 1px solid var(--border); border-radius: 0.9rem; background: var(--bg-card);
            overflow: visible; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .cust-table-wrap { overflow-x: auto; overflow-y: visible; }
        .cust-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .cust-table th, .cust-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .cust-table th { background: var(--primary-soft); font-weight: 800; white-space: nowrap; }
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
        .cust-sms-circle-btn:hover { filter: brightness(0.97); }
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
        .loan-file-card--settled {
            border-color: rgba(239, 68, 68, 0.38);
            background: linear-gradient(180deg, rgba(254, 242, 242, 0.72), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.09);
        }
        html[data-theme="dark"] .loan-file-card--settled {
            background: linear-gradient(180deg, rgba(127, 29, 29, 0.16), rgba(30, 41, 59, 0.82));
            border-color: rgba(248, 113, 113, 0.45);
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
                <button type="button" class="cust-add-btn" id="cust-open-modal" aria-haspopup="dialog">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    افزودن مشتری
                </button>
            </div>
        </div>

        <div class="cust-head" style="margin-top: -0.25rem;">
            <form method="get" action="{{ route('admin.customers.index') }}" class="cust-search">
                <input type="search" name="q" value="{{ $search }}" placeholder="جستجو: کد، نام، موبایل، کد ملی..." autocomplete="off">
            </form>
        </div>

        <div class="cust-card">
            <div class="cust-table-wrap">
                <table class="cust-table">
                    <thead>
                        <tr>
                            <th>کد مشتری</th>
                            <th>نام مشتری</th>
                            <th>تعداد وام</th>
                            <th>مجموع وام‌های دریافتی با بهره</th>
                            <th>مانده اقساط</th>
                            <th>تاریخ عضویت</th>
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
                                    <div class="cust-sub-text">کد ملی: {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($c->national_id) }}</div>
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
                                <td colspan="8" class="cust-empty">هنوز مشتری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="cust-pagination">{{ $customers->links() }}</div>
            @endif
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
                            <label for="cust-father">نام پدر <span class="req">*</span></label>
                            <input id="cust-father" name="father_name" type="text" value="{{ old('father_name') }}" required>
                            @error('father_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-national">کد ملی <span class="req">*</span></label>
                            <input id="cust-national" name="national_id" type="text" inputmode="numeric" value="{{ old('national_id') }}" maxlength="10" required>
                            @error('national_id')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-mobile">موبایل <span class="req">*</span></label>
                            <input id="cust-mobile" name="mobile" type="text" inputmode="numeric" value="{{ old('mobile') }}" placeholder="09123456789" required autocomplete="tel">
                            @error('mobile')<div class="cust-field-error">{{ $message }}</div>@enderror
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
                            <label for="cust-city">شهر <span class="req">*</span></label>
                            <input id="cust-city" name="city" type="text" value="{{ old('city') }}" required>
                            @error('city')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field cust-field--full">
                            <label for="cust-address">آدرس <span class="req">*</span></label>
                            <textarea id="cust-address" name="address" required>{{ old('address') }}</textarea>
                            @error('address')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-postal">کدپستی <span class="req">*</span></label>
                            <input id="cust-postal" name="postal_code" type="text" inputmode="numeric" value="{{ old('postal_code') }}" maxlength="10" required>
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
            <div class="cust-modal-body">
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
                    <div class="loan-manage-placeholder">درخواست وام‌ها: این بخش به‌زودی با لیست درخواست‌های وام تکمیل می‌شود.</div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="transactions" hidden>
                    <div class="loan-manage-placeholder">تراکنش‌ها: این بخش به‌زودی با تراکنش‌های مرتبط با وام تکمیل می‌شود.</div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="sms" hidden>
                    <div class="loan-manage-placeholder">پیامک‌ها: این بخش به‌زودی با تاریخچه پیامک‌های وام تکمیل می‌شود.</div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="tickets" hidden>
                    <div class="loan-manage-placeholder">تیکت‌ها: این بخش به‌زودی با تیکت‌های پشتیبانی مشتری تکمیل می‌شود.</div>
                </div>
                <div class="loan-tab-panel" data-loan-panel="guarantees" hidden>
                    <div class="loan-manage-placeholder">تضامین: این بخش به‌زودی با اطلاعات تضامین مشتری تکمیل می‌شود.</div>
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
                        <input id="loan-installments-count" name="installments_count" type="number" min="1" step="1" required value="12">
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
                        <span class="loan-interest-note" id="loan-installment-help">به‌صورت خودکار محاسبه می‌شود؛ قابل ویرایش است.</span>
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
                                <div class="cust-field"><label>کد ملی ضامن</label><input name="guarantor_national_id" id="loan-guarantee-guarantor-national-id" inputmode="numeric" maxlength="10" placeholder="اختیاری"></div>
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
                                <div class="cust-field" style="grid-column: 1 / -1;">
                                    <label class="loan-guarantee-check-row" for="loan-guarantee-cheque-returned">
                                        <input type="checkbox" name="cheque_returned" value="1" id="loan-guarantee-cheque-returned">
                                        عودت شده؟
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
            var custStoreUrl = @json(route('admin.customers.store'));
            var appDisplayName = @json($appDisplayName ?? config('app.name'));

            function custEditDataUrl(id) {
                return custListBaseUrl + '/' + id + '/edit-data';
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

            function customerLoanStoreUrl(id) {
                return custListBaseUrl + '/' + id + '/loan-files';
            }
            function customerLoanUpdateUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId;
            }
            function customerLoanDeleteUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId;
            }
            function customerLoanSendSmsUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/send-sms';
            }
            function customerLoanGuaranteesUrl(customerId, loanFileId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees';
            }
            function customerLoanGuaranteeDeleteUrl(customerId, loanFileId, guaranteeId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees/' + guaranteeId;
            }
            function customerLoanGuaranteeUpdateUrl(customerId, loanFileId, guaranteeId) {
                return custListBaseUrl + '/' + customerId + '/loan-files/' + loanFileId + '/guarantees/' + guaranteeId;
            }

            var organizationsListUrl = @json(route('admin.organizations.index'));
            var guarantorOtpSendUrl = @json(route('admin.guarantor-otp.send'));
            var guarantorOtpVerifyUrl = @json(route('admin.guarantor-otp.verify'));
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
            var modalTitle = document.getElementById('cust-modal-title');
            var modalDesc = document.getElementById('cust-modal-desc');
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
            var loanManageClose = document.getElementById('loan-manage-close');
            var loanFilesSummary = document.getElementById('loan-files-summary');
            var loanFilesList = document.getElementById('loan-files-list');
            var loanOpenCreateModalBtn = document.getElementById('loan-open-create-modal');
            var loanManageOpenEditBtn = document.getElementById('loan-manage-open-edit');
            var loanManageOpenWalletBtn = document.getElementById('loan-manage-open-wallet');
            var loanTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-loan-tab]'));
            var loanTabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-loan-panel]'));
            var loanManageCustomerNameView = document.getElementById('loan-manage-customer-name');
            var loanManageCustomerMobileView = document.getElementById('loan-manage-customer-mobile');
            var loanManageCreditStatusView = document.getElementById('loan-manage-credit-status');
            var loanManageWalletBalanceView = document.getElementById('loan-manage-wallet-balance');
            var walletCurrentCustomerId = null;
            var walletCurrentCustomerName = '';
            var walletCurrentCustomerMobile = '';
            var quickSmsCurrentCustomerId = null;
            var quickSmsCurrentType = '';
            var quickSmsCurrentCustomerName = '';
            var quickSmsTemplatesData = @json($quickSmsTemplates->values());
            var walletSmsTemplates = [];
            var walletAdjustSubmitting = false;
            var walletLockSubmitting = false;
            var customerFormSubmitting = false;
            var quickSmsSubmitting = false;
            var loanManageCurrentCustomerId = null;
            var loanManageCurrentCustomerName = '';
            var loanManageCurrentCustomerMobile = '';
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

            function openQuickSmsModal(customerId, customerName, customerMobile, smsType) {
                quickSmsCurrentCustomerId = customerId;
                quickSmsCurrentType = smsType;
                quickSmsCurrentCustomerName = customerName || '';
                if (quickSmsTitle) {
                    quickSmsTitle.textContent = smsType === 'wallet_link' ? 'ارسال پیامک لینک شارژ' : 'ارسال پیامک خوش‌آمدگویی';
                }
                if (quickSmsSubtitle) {
                    quickSmsSubtitle.textContent = 'گیرنده: ' + (customerName || '') + ' - ' + (customerMobile || '');
                }
                if (quickSmsForm) {
                    quickSmsForm.reset();
                }
                if (quickSmsText) {
                    quickSmsText.value = smsType === 'wallet_link'
                        ? 'سلام ' + (customerName || '') + '، لینک شارژ کیف پول شما: —'
                        : 'سلام ' + (customerName || '') + '، به سامانه خوش آمدید.';
                }
                if (quickSmsOverlay) {
                    quickSmsOverlay.hidden = false;
                    quickSmsOverlay.setAttribute('aria-hidden', 'false');
                }
            }

            function closeQuickSmsModal() {
                if (!quickSmsOverlay) return;
                quickSmsOverlay.hidden = true;
                quickSmsOverlay.setAttribute('aria-hidden', 'true');
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

            function renderLoanFilesForCustomer(customerId) {
                if (!loanFilesList || !loanFilesSummary) return;
                var meta = loanManageMap ? loanManageMap[String(customerId || '')] : null;
                var rows = (meta && Array.isArray(meta.loan_files)) ? meta.loan_files : [];
                var count = rows.length;
                var total = rows.reduce(function (sum, row) {
                    return sum + Number(row.total_repayable_toman || 0);
                }, 0);
                var remain = rows.reduce(function (sum, row) {
                    return sum + Number(row.remaining_amount_toman || 0);
                }, 0);
                loanFilesSummary.textContent = count
                    ? ('تعداد پرونده: ' + formatToman(count) + ' | مجموع بازپرداخت: ' + formatToman(total) + ' تومان | مانده: ' + formatToman(remain) + ' تومان')
                    : 'برای این مشتری هنوز پرونده وام ثبت نشده است.';
                if (!count) {
                    loanFilesList.innerHTML = '<div class="loan-files-empty">هنوز پرونده‌ای ثبت نشده است.</div>';
                    return;
                }

                loanFilesList.innerHTML = rows.map(function (row) {
                    var paidInstallments = Number(row.paid_installments_count || 0);
                    var paidAmount = Number(row.paid_installments_amount_toman || 0);
                    var remainingAmount = Number(row.remaining_amount_toman || 0);
                    var discountAmount = Number(row.discount_amount_toman || 0);
                    var settlementText = row.is_settled ? 'بلی' : 'خیر';
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

                    return '<article class="loan-file-card' + (row.is_settled ? ' loan-file-card--settled' : '') + '">' +
                        (row.is_settled ? '<span class="loan-file-corner-ribbon"><span>تسویه شده</span></span>' : '') +
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
                                    '<div class="loan-file-item"><span class="loan-file-k">تعداد اقساط پرداختی:</span><span class="loan-file-v">' + formatToman(paidInstallments) + '</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">مجموع مبلغ اقساط پرداخت شده:</span><span class="loan-file-v">' + formatToman(paidAmount) + ' تومان</span></div>' +
                                    '<div class="loan-file-item"><span class="loan-file-k">دیرکرد / زودکرد:</span><span class="loan-file-v">—</span></div>' +
                                    '<div class="loan-file-item loan-file-item--stack">' +
                                        '<span class="loan-file-k">تخفیف:</span>' +
                                        '<span class="loan-file-v">' + formatToman(discountAmount) + ' تومان</span>' +
                                        '<span><button type="button" class="loan-file-btn loan-file-btn--disc">ثبت تخفیف</button></span>' +
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
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="پرینت"><i class="fa-solid fa-print" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="پیامک" data-loan-sms-id="' + String(row.id || '') + '" data-loan-default-sms="' + escapeHtmlAttr(defaultLoanSmsText) + '"><i class="fa-regular fa-message" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini loan-file-btn--danger" title="حذف" data-loan-delete-id="' + String(row.id || '') + '" data-loan-delete-code="' + escapeHtmlAttr(row.loan_code || '') + '"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>' +
                                '<button type="button" class="loan-file-btn loan-file-btn--mini" title="ویرایش" data-loan-edit-id="' + String(row.id || '') + '"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></button>' +
                            '</div>' +
                            '<div class="loan-file-actions-right">' +
                                '<button type="button" class="loan-file-btn">مشاهده اقساط و پرداخت</button>' +
                                '<button type="button" class="loan-file-btn" data-loan-guarantees-id="' + String(row.id || '') + '">تضامین</button>' +
                                '<button type="button" class="loan-file-btn">مشاهده مبلغ تسویه آنی</button>' +
                                '<button type="button" class="loan-file-btn">فسخ قرارداد</button>' +
                            '</div>' +
                        '</div>' +
                    '</article>';
                }).join('');
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
                var payableAfterDownPayment = Math.max(0, (amount + profit) - downPayment);
                if ((force || !loanInstallmentAutoDirty) && payableAfterDownPayment > 0 && count > 0) {
                    var calculated = Math.floor(payableAfterDownPayment / count);
                    loanInstallmentAmountInput.value = formatThousandsInputValue(String(calculated));
                }
                var installment = parseThousandsInput(loanInstallmentAmountInput.value);
                var sum = installment * count;
                loanTotalCheck.textContent = 'اصل وام: ' + formatToman(amount) + ' | نرخ بهره: ' + String(interestRate) + '% | بهره تخمینی: ' + formatToman(profit) + ' | قابل بازپرداخت: ' + formatToman(payableAfterDownPayment) + ' | جمع اقساط: ' + formatToman(sum);
                loanTotalCheck.style.color = sum > payableAfterDownPayment ? '#b91c1c' : 'var(--muted)';
            }

            function openLoanCreateModal() {
                if (!loanManageCurrentCustomerId || !loanCreateOverlay || !loanCreateForm) return;
                loanFormMode = 'create';
                loanEditingFileId = null;
                loanInstallmentAutoDirty = false;
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
                if (loanInstallmentsCountInput) loanInstallmentsCountInput.value = '12';
                if (loanInstallmentIntervalCountInput) loanInstallmentIntervalCountInput.value = '1';
                var submitBtn = loanCreateForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'ثبت وام';
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
                loanFormMode = 'edit';
                loanEditingFileId = Number(row.id || 0);
                loanInstallmentAutoDirty = false;
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
                loanGuaranteeForm.reset();
                resetGuarantorOtpUi();
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
                var gtype = String(guaranteeData.type || 'other');
                setGuaranteeType(gtype, gtype === 'org_other' || gtype === 'org_self');
                resetGuaranteeFilePreview();
                loanGuaranteeRemoveExistingAttachment = false;

                var meta = guaranteeData.meta && typeof guaranteeData.meta === 'object' ? guaranteeData.meta : {};
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
                var chequeReturnedCb = document.getElementById('loan-guarantee-cheque-returned');
                if (chequeCollectedCb) chequeCollectedCb.checked = !!meta.cheque_collected;
                if (chequeReturnedCb) chequeReturnedCb.checked = !!meta.cheque_returned;
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
            }

            function openLoanManageModal(customerId, customerName, customerMobile) {
                loanManageCurrentCustomerId = customerId || null;
                loanManageCurrentCustomerName = customerName || '';
                loanManageCurrentCustomerMobile = customerMobile || '';
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
                loanTabButtons.forEach(function (btn) {
                    var active = btn.getAttribute('data-loan-tab') === tabId;
                    btn.classList.toggle('is-active', active);
                });
                loanTabPanels.forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-loan-panel') !== tabId;
                });
            }

            function closeLoanManageModal() {
                if (!loanManageOverlay) return;
                loanManageOverlay.hidden = true;
                loanManageOverlay.setAttribute('aria-hidden', 'true');
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
                    if (quickCustomerId) {
                        openQuickSmsModal(parseInt(quickCustomerId, 10), quickCustomerName, quickCustomerMobile, quickSmsType);
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
                        resetGuarantorOtpUi();
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
                        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                            .then(function (res) {
                                if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'ارسال نشد.');
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
                        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                            .then(function (res) {
                                if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'تایید نشد.');
                                var tok = document.getElementById('loan-guarantee-guarantor-verification-token');
                                if (tok) tok.value = String(res.json.verification_token || '');
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
                        return r.json().then(function (json) { return { ok: r.ok, json: json }; });
                    }).then(function (res) {
                        if (!res.ok) throw new Error((res.json && res.json.message) ? res.json.message : 'ذخیره ضمانت ناموفق بود.');
                        loanGuaranteeForm.reset();
                        loanGuaranteeFormMode = 'create';
                        loanGuaranteeEditingId = null;
                        setGuaranteeType('org_self');
                        resetGuaranteeFilePreview();
                        openLoanGuaranteeModal(loanGuaranteeCurrentLoanId);
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
            if (loanCustomInterestRateInput) {
                loanCustomInterestRateInput.addEventListener('input', function () {
                    syncLoanInstallmentCalculation(true);
                });
            }
            if (loanCreateForm) {
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
                    var payableCap = Math.max(0, (payload.amount_toman + calculatedProfit) - payload.down_payment_toman);
                    if ((payload.installment_amount_toman * payload.installments_count) > payableCap) {
                        setLoanFieldError(loanInstallmentAmountInput, 'مبلغ هر قسط زیاد است؛ جمع اقساط از مبلغ وام بیشتر شده.');
                        if (window.AdminSwal && window.AdminSwal.error) AdminSwal.error('جمع مبلغ اقساط نباید از مبلغ قابل بازپرداخت (اصل + بهره - پیش‌پرداخت) بیشتر باشد.');
                        return;
                    }

                    function submitLoanCreate(finalPayload) {
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
            syncUsername();
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
            syncUsername();
            openModal();
            @endif
        })();
    </script>
@endpush
