@extends('layouts.user.app')

@section('title', $pageTitle)

@push('head')
    <style>
        .lr-page {
            width: 100%;
            max-width: 100%;
            margin-inline: 0;
            box-sizing: border-box;
        }

        .lr-head {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
            margin-bottom: 0.95rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--border);
        }
        .lr-head__ico { font-size: 1.2rem; color: var(--primary-dark); opacity: 0.9; }
        .lr-head__title {
            margin: 0; font-size: clamp(1rem, 3.6vw, 1.15rem); font-weight: 800;
            color: var(--text); letter-spacing: -0.02em;
        }
        .lr-head__badge {
            margin-inline-start: 0.2rem;
            font-size: 0.74rem;
            font-weight: 800;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            border: 1px solid rgba(37, 99, 235, 0.22);
        }
        html[data-theme="dark"] .lr-head__badge { border-color: rgba(59, 130, 246, 0.35); }
        .lr-head__lead {
            width: 100%; margin: 0.2rem 0 0;
            font-size: 0.86rem; color: var(--muted); line-height: 1.6;
        }

        .lr-toolbar {
            display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between;
            margin-bottom: 0.85rem;
        }
        .lr-search {
            flex: 1 1 14rem; min-width: 0; display: flex; gap: 0.4rem; align-items: center;
        }
        .lr-search input {
            flex: 1; min-width: 0;
            padding: 0.5rem 0.65rem;
            border-radius: 0.65rem;
            border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text);
            font-family: inherit; font-size: 0.86rem;
        }
        .lr-search input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .lr-toolbar__cta {
            display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; flex-shrink: 0;
        }
        .lr-icon-btn {
            flex-shrink: 0;
            width: 2.5rem; height: 2.5rem; border-radius: 0.65rem;
            display: grid; place-items: center;
            border: 1px solid var(--border); background: var(--bg-card); color: var(--text);
            cursor: pointer; font-size: 0.95rem;
            transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease, filter 0.12s ease;
        }
        .lr-icon-btn:hover:not(:disabled) {
            background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); color: var(--primary-dark);
        }
        .lr-icon-btn:disabled { opacity: 0.65; cursor: not-allowed; }
        .lr-icon-btn.is-loading i { animation: lr-spin 0.85s linear infinite; }
        @keyframes lr-spin { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }

        .lr-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            background: var(--bg-card);
            -webkit-overflow-scrolling: touch;
        }
        .lr-tbl {
            width: 100%; border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 60rem;
        }
        .lr-tbl th, .lr-tbl td {
            padding: 0.55rem 0.6rem;
            border-bottom: 1px solid var(--border);
            text-align: start; vertical-align: top;
        }
        .lr-tbl th {
            background: var(--primary-soft);
            font-weight: 800; color: var(--text); white-space: nowrap;
            position: sticky; top: 0; z-index: 1;
        }
        html[data-theme="dark"] .lr-tbl th { background: rgba(37, 99, 235, 0.16); }
        .lr-tbl td { color: var(--muted); font-weight: 600; }
        .lr-tbl tr:last-child td { border-bottom: 0; }
        .lr-tbl tr:hover td { background: rgba(37, 99, 235, 0.04); }
        html[data-theme="dark"] .lr-tbl tr:hover td { background: rgba(59, 130, 246, 0.08); }

        .lr-id-cell {
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            color: var(--text);
            white-space: nowrap;
        }
        .lr-id-cell i { color: var(--primary-dark); opacity: 0.78; margin-inline-end: 0.25rem; }
        .lr-money {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
        }
        .lr-money small {
            font-size: 0.66rem; font-weight: 700;
            color: var(--muted); margin-inline-start: 0.2rem;
        }
        .lr-info-cell { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
        .lr-info-cell strong { color: var(--text); font-weight: 800; }
        .lr-info-cell small { opacity: 0.8; font-weight: 600; }
        .lr-muted-cell { color: var(--muted); font-style: italic; }

        .lr-status-chip {
            display: inline-flex; align-items: center; gap: 0.32rem;
            padding: 0.22rem 0.55rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 800;
            background: var(--primary-soft); color: var(--primary-dark);
            border: 1px solid rgba(37, 99, 235, 0.22);
            white-space: nowrap;
        }
        .lr-status-chip i { font-size: 0.7em; opacity: 0.9; }
        .lr-status-chip--draft   { background: rgba(148, 163, 184, 0.18); color: #475569; border-color: rgba(148, 163, 184, 0.4); }
        .lr-status-chip--pending { background: rgba(217, 119, 6, 0.14); color: #b45309; border-color: rgba(217, 119, 6, 0.3); }
        .lr-status-chip--review  { background: rgba(124, 58, 237, 0.14); color: #6d28d9; border-color: rgba(124, 58, 237, 0.32); }
        .lr-status-chip--approved{ background: rgba(16, 185, 129, 0.16); color: #047857; border-color: rgba(16, 185, 129, 0.32); }
        .lr-status-chip--rejected{ background: rgba(220, 38, 38, 0.14); color: #b91c1c; border-color: rgba(220, 38, 38, 0.32); }
        html[data-theme="dark"] .lr-status-chip--draft { color: #cbd5e1; }
        html[data-theme="dark"] .lr-status-chip--pending { color: #fbbf24; }
        html[data-theme="dark"] .lr-status-chip--review { color: #a78bfa; }
        html[data-theme="dark"] .lr-status-chip--approved { color: #6ee7b7; }
        html[data-theme="dark"] .lr-status-chip--rejected { color: #fca5a5; }

        .lr-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; }
        .lr-btn {
            font-family: inherit;
            font-size: 0.72rem; font-weight: 800;
            padding: 0.34rem 0.55rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            background: var(--bg-card); cursor: pointer; color: var(--text);
            display: inline-flex; align-items: center; gap: 0.32rem;
            text-decoration: none;
            transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
        }
        .lr-btn:hover { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); color: var(--primary-dark); }
        .lr-btn--danger { color: #b91c1c; border-color: rgba(185, 28, 28, 0.35); }
        .lr-btn--danger:hover { background: rgba(220, 38, 38, 0.12); color: #b91c1c; border-color: rgba(220, 38, 38, 0.5); }
        .lr-btn--ghost { background: transparent; }

        .lr-no-result-row td { padding: 1.5rem 0.5rem; text-align: center; color: var(--muted); font-weight: 700; }

        .lr-empty {
            padding: 2.25rem 1.25rem;
            text-align: center;
            color: var(--muted);
            font-weight: 700;
            border: 1px dashed var(--border);
            border-radius: 1rem;
            background: var(--bg-card);
        }
        .lr-empty__ico {
            font-size: 2.6rem;
            opacity: 0.4;
            margin-bottom: 0.55rem;
            display: block;
            color: var(--primary-dark);
        }
        .lr-empty__title { display: block; font-size: 0.95rem; color: var(--text); margin-bottom: 0.4rem; }
        .lr-empty__sub { display: block; font-size: 0.8rem; color: var(--muted); font-weight: 600; line-height: 1.7; }
        .lr-empty__cta {
            display: inline-flex; align-items: center; gap: 0.4rem;
            margin-top: 1rem;
            padding: 0.5rem 0.9rem;
            border-radius: 0.65rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff; font-size: 0.82rem; font-weight: 800; border: none; cursor: pointer;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
        }
        .lr-empty__cta:hover { filter: brightness(1.05); }

        .lr-cards { display: none; flex-direction: column; gap: 0.7rem; }
        .lr-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.95rem;
            padding: 0.75rem 0.82rem 0.82rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        html[data-theme="dark"] .lr-card { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22); }
        .lr-card__head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.45rem;
            margin-bottom: 0.5rem; padding-bottom: 0.4rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.45);
        }
        html[data-theme="dark"] .lr-card__head { border-bottom-color: rgba(148, 163, 184, 0.28); }
        .lr-card__id { font-weight: 800; font-size: 0.86rem; color: var(--text); display: inline-flex; align-items: center; gap: 0.32rem; }
        .lr-card__id i { color: var(--primary-dark); opacity: 0.85; }
        .lr-card__kv { margin: 0; display: flex; flex-direction: column; gap: 0.42rem; font-size: 0.78rem; }
        .lr-card__kv-row {
            display: grid;
            grid-template-columns: minmax(0, 7rem) minmax(0, 1fr);
            gap: 0.35rem 0.55rem;
            align-items: start;
            line-height: 1.5;
        }
        .lr-card__kv-row dt { margin: 0; font-weight: 800; color: var(--muted); }
        .lr-card__kv-row dd { margin: 0; font-weight: 700; color: var(--text); text-align: end; word-break: break-word; }
        .lr-card__foot {
            margin-top: 0.65rem;
            padding-top: 0.55rem;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }
        html[data-theme="dark"] .lr-card__foot { border-top-color: rgba(148, 163, 184, 0.28); }
        .lr-card__foot .lr-actions { gap: 0.42rem; }
        .lr-card__foot .lr-btn { flex: 1 1 auto; justify-content: center; min-height: 2.3rem; font-size: 0.76rem; }

        @media (max-width: 860px) {
            .lr-desktop-only { display: none !important; }
            .lr-cards { display: flex !important; }
        }
        @media (max-width: 480px) {
            .lr-search { flex: 1 1 100%; }
            .lr-toolbar__cta { flex: 1 1 100%; justify-content: space-between; }
        }

        /* ==========================================================
           مودال wizard درخواست وام
           ========================================================== */
        #lr-wizard {
            display: none;
            width: min(96vw, 60rem);
            max-width: min(96vw, 60rem);
            margin: 0;
            border: 0;
            padding: 0;
            background: transparent;
            box-sizing: border-box;
        }
        #lr-wizard[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            width: min(96vw, 60rem);
            max-width: min(96vw, 60rem);
            height: fit-content;
            max-height: min(94vh, 50rem);
            margin: auto;
            z-index: 60;
            overflow: hidden;
        }
        #lr-wizard::backdrop {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
        }
        html[data-theme="dark"] #lr-wizard::backdrop { background: rgba(0, 0, 0, 0.6); }

        .lr-wiz {
            max-height: min(94vh, 50rem);
            width: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-card);
            border-radius: 1rem;
            border: 1px solid var(--border);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
        }
        html[data-theme="dark"] .lr-wiz {
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.55);
        }

        .lr-wiz__head {
            flex-shrink: 0;
            position: relative;
            padding: 0.85rem 1rem 0.65rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.07), transparent 80%);
        }
        .lr-wiz__close {
            position: absolute;
            top: 0.4rem;
            inset-inline-end: 0.45rem;
            width: 2.1rem; height: 2.1rem;
            border: none; background: transparent;
            color: var(--muted); font-size: 1.4rem; line-height: 1;
            cursor: pointer; border-radius: 0.45rem;
        }
        .lr-wiz__close:hover { background: var(--primary-soft); color: var(--text); }
        .lr-wiz__title {
            margin: 0 0 0.85rem;
            padding-inline-end: 2.25rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            display: flex; align-items: center; gap: 0.42rem;
        }
        .lr-wiz__title i { color: var(--primary-dark); opacity: 0.92; }

        /* stepper */
        .lr-stepper {
            margin: 0; padding: 0; list-style: none;
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.35rem;
            position: relative;
        }
        .lr-stepper::before {
            content: '';
            position: absolute;
            top: 1.05rem;
            inset-inline-start: 1.4rem;
            inset-inline-end: 1.4rem;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        .lr-stepper__item {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.32rem;
            min-width: 0;
            flex: 1 1 0;
            text-align: center;
            color: var(--muted);
            font-weight: 700;
        }
        .lr-stepper__num {
            width: 2.1rem; height: 2.1rem;
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 0.86rem; font-weight: 900;
            background: var(--bg-card);
            border: 2px solid var(--border);
            color: var(--muted);
            transition: all 0.18s ease;
            font-variant-numeric: tabular-nums;
        }
        .lr-stepper__label {
            font-size: 0.74rem;
            line-height: 1.3;
            max-width: 100%;
            color: inherit;
        }
        .lr-stepper__item.is-active .lr-stepper__num {
            background: var(--primary);
            border-color: var(--primary-dark);
            color: #fff;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
        }
        .lr-stepper__item.is-active { color: var(--primary-dark); }
        .lr-stepper__item.is-complete .lr-stepper__num {
            background: #059669;
            border-color: #047857;
            color: #fff;
        }
        .lr-stepper__item.is-complete { color: #047857; }
        html[data-theme="dark"] .lr-stepper__item.is-complete { color: #34d399; }

        .lr-wiz__body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: 0.9rem 1rem 0.5rem;
        }

        .lr-step { display: none; }
        .lr-step.is-active { display: block; }

        /* step 1 layout */
        .lr-step1-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 0.85rem;
            align-items: start;
        }
        @media (max-width: 720px) {
            .lr-step1-grid { grid-template-columns: 1fr; }
        }

        .lr-card-soft {
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.75rem 0.8rem 0.85rem;
            background: var(--bg-card);
        }
        .lr-card-soft + .lr-card-soft { margin-top: 0.75rem; }

        .lr-field { margin-bottom: 0.7rem; }
        .lr-field:last-child { margin-bottom: 0; }
        .lr-field > label {
            display: block;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .lr-field > label .lr-req { color: #dc2626; margin-inline-start: 0.18rem; }
        .lr-field select,
        .lr-field input[type="text"],
        .lr-field input[type="number"],
        .lr-field textarea {
            width: 100%;
            padding: 0.5rem 0.65rem;
            border-radius: 0.6rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            font-family: inherit;
            font-size: 0.86rem;
            box-sizing: border-box;
        }
        .lr-field select:focus,
        .lr-field input:focus,
        .lr-field textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .lr-field textarea { min-height: 4rem; resize: vertical; }
        .lr-field-hint {
            margin: 0.3rem 0 0;
            font-size: 0.7rem;
            color: var(--muted);
            font-weight: 600;
        }
        .lr-field-error {
            margin: 0.3rem 0 0;
            font-size: 0.72rem;
            color: #b91c1c;
            font-weight: 700;
        }

        .lr-plan-info {
            margin-top: 0.65rem;
            padding-top: 0.65rem;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-direction: column;
            gap: 0.42rem;
        }
        .lr-plan-info__row {
            display: grid;
            grid-template-columns: minmax(8rem, auto) minmax(0, 1fr);
            gap: 0.4rem 0.6rem;
            align-items: baseline;
            font-size: 0.8rem;
        }
        .lr-plan-info__k {
            font-weight: 800;
            color: var(--muted);
        }
        .lr-plan-info__v {
            font-weight: 700;
            color: var(--text);
            word-break: break-word;
        }
        .lr-plan-info__v--accent { color: var(--primary-dark); }
        .lr-chip-list {
            display: flex; flex-wrap: wrap; gap: 0.3rem;
        }
        .lr-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.18rem 0.5rem;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.14);
            color: #6d28d9;
            border: 1px solid rgba(124, 58, 237, 0.32);
            font-size: 0.72rem;
            font-weight: 800;
        }
        html[data-theme="dark"] .lr-chip { color: #c4b5fd; }
        .lr-chip i { font-size: 0.7em; opacity: 0.85; }
        .lr-no-docs {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.12);
            color: #6d28d9;
            border: 1px solid rgba(124, 58, 237, 0.28);
            font-size: 0.72rem;
            font-weight: 800;
        }
        html[data-theme="dark"] .lr-no-docs { color: #c4b5fd; }

        /* number input with +/- */
        .lr-num-input {
            display: flex;
            align-items: stretch;
            gap: 0;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            overflow: hidden;
            background: var(--bg-card);
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }
        .lr-num-input:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .lr-num-input__btn {
            flex-shrink: 0;
            width: 2.4rem;
            border: 0;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 1.05rem;
            font-weight: 900;
            line-height: 1;
            cursor: pointer;
            transition: background 0.12s ease, color 0.12s ease;
        }
        .lr-num-input__btn:hover:not(:disabled) {
            background: var(--primary);
            color: #fff;
        }
        .lr-num-input__btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .lr-num-input input {
            flex: 1;
            min-width: 0;
            border: 0 !important;
            background: transparent !important;
            text-align: center;
            font-weight: 800;
            font-size: 0.94rem !important;
            color: var(--text);
            box-shadow: none !important;
            font-variant-numeric: tabular-nums;
        }
        .lr-num-input input:focus { outline: none; }
        .lr-num-input__unit {
            display: inline-flex;
            align-items: center;
            padding: 0 0.7rem;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--muted);
            background: rgba(148, 163, 184, 0.12);
            border-inline-start: 1px solid var(--border);
        }
        .lr-num-input--with-unit input { text-align: end; padding-inline-end: 0.5rem; }

        .lr-field-row {
            display: grid;
            grid-template-columns: minmax(7rem, auto) minmax(0, 1fr);
            gap: 0.35rem 0.6rem;
            align-items: center;
            margin-bottom: 0.62rem;
        }
        .lr-field-row > label {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--muted);
        }
        .lr-field-row--summary {
            border-top: 1px dashed var(--border);
            margin-top: 0.65rem;
            padding-top: 0.65rem;
        }
        .lr-summary-v {
            font-weight: 900;
            color: var(--primary-dark);
            font-size: 0.92rem;
            text-align: end;
            font-variant-numeric: tabular-nums;
        }

        .lr-totals {
            margin-top: 0.7rem;
            display: flex; flex-direction: column; gap: 0.45rem;
        }
        .lr-total-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            padding: 0.55rem 0.75rem;
            border-radius: 0.7rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
            font-size: 0.84rem;
        }
        .lr-total-box__k { font-weight: 800; color: var(--text); }
        .lr-total-box__v {
            font-weight: 900;
            color: var(--text);
            font-variant-numeric: tabular-nums;
        }
        .lr-total-box--net {
            border-color: rgba(37, 99, 235, 0.32);
            background: rgba(37, 99, 235, 0.05);
        }
        .lr-total-box--net .lr-total-box__v { color: var(--primary-dark); }
        .lr-total-box--gross {
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            border-color: var(--primary-dark);
        }
        .lr-total-box--gross .lr-total-box__k,
        .lr-total-box--gross .lr-total-box__v { color: #fff; }
        html[data-theme="dark"] .lr-total-box--net {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .lr-desc-wrap { margin-top: 0.85rem; }

        .lr-step-error {
            margin: 0.85rem 0 0;
            padding: 0.55rem 0.7rem;
            border-radius: 0.6rem;
            background: rgba(220, 38, 38, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(220, 38, 38, 0.3);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .lr-step-coming {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 3rem 1rem;
            text-align: center;
            color: var(--muted);
        }
        .lr-step-coming i { font-size: 2.5rem; color: var(--primary-dark); opacity: 0.55; }
        .lr-step-coming h3 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--text); }
        .lr-step-coming p { margin: 0; font-size: 0.85rem; line-height: 1.6; }

        /* ===== مرحله ۲ — تکمیل مدارک ===== */
        .lr-doc-alert {
            margin: 0 0 0.85rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.7rem;
            background: rgba(8, 145, 178, 0.08);
            border: 1.5px solid rgba(8, 145, 178, 0.45);
            color: #0e7490;
            font-size: 0.84rem;
            font-weight: 700;
            display: flex; align-items: center; gap: 0.6rem;
            line-height: 1.7;
        }
        html[data-theme="dark"] .lr-doc-alert { color: #67e8f9; border-color: rgba(34, 211, 238, 0.4); background: rgba(8, 145, 178, 0.12); }
        .lr-doc-alert i { color: #0891b2; font-size: 1.1rem; flex-shrink: 0; }
        html[data-theme="dark"] .lr-doc-alert i { color: #22d3ee; }

        .lr-no-docs-msg {
            margin: 0 0 0.5rem;
            padding: 1.1rem 1.2rem;
            border-radius: 0.85rem;
            background: rgba(124, 58, 237, 0.08);
            border: 1.5px solid rgba(124, 58, 237, 0.32);
            color: var(--text);
            font-size: 0.88rem;
            line-height: 1.9;
            text-align: center;
            font-weight: 700;
            display: flex; flex-direction: column; gap: 0.45rem; align-items: center;
        }
        .lr-no-docs-msg i { font-size: 1.7rem; color: #7c3aed; opacity: 0.85; }
        html[data-theme="dark"] .lr-no-docs-msg i { color: #c4b5fd; }

        .lr-doc-card {
            margin: 0 0 0.85rem;
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            overflow: hidden;
            background: var(--bg-card);
        }
        .lr-doc-card__head {
            padding: 0.6rem 0.95rem;
            background: rgba(148, 163, 184, 0.22);
            color: var(--text);
            font-weight: 800;
            font-size: 0.88rem;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        html[data-theme="dark"] .lr-doc-card__head { background: rgba(148, 163, 184, 0.12); }
        .lr-doc-card__body {
            padding: 0.7rem 0.85rem 0.8rem;
            display: flex; flex-direction: column;
            gap: 0;
        }

        .lr-doc-row {
            display: grid;
            grid-template-columns: 7rem minmax(0, 1fr) 7.5rem;
            gap: 0.85rem;
            align-items: center;
            padding: 0.55rem 0;
            border-bottom: 1px dashed var(--border);
        }
        .lr-doc-row:first-child { padding-top: 0; }
        .lr-doc-row:last-of-type { border-bottom: 0; padding-bottom: 0; }
        @media (max-width: 640px) {
            .lr-doc-row {
                grid-template-columns: 1fr;
                gap: 0.65rem;
                text-align: start;
            }
            .lr-doc-row__status, .lr-doc-row__preview { align-items: stretch; }
            .lr-doc-row__preview { flex-direction: row; justify-content: center; }
        }

        .lr-doc-row__status {
            display: flex; flex-direction: column;
            align-items: center; gap: 0.42rem;
        }
        .lr-status-chip-row {
            display: inline-flex; align-items: center;
            padding: 0.32rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            background: rgba(124, 58, 237, 0.16);
            color: #6d28d9;
            border: 1px solid rgba(124, 58, 237, 0.32);
            white-space: nowrap;
        }
        html[data-theme="dark"] .lr-status-chip-row { color: #c4b5fd; }
        .lr-status-chip-row--pending {
            background: rgba(148, 163, 184, 0.18);
            color: var(--muted);
            border-color: var(--border);
        }
        .lr-status-chip-row--pending-text {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--muted);
            text-align: center;
            line-height: 1.4;
        }

        .lr-doc-row__desc input,
        .lr-doc-row__desc textarea {
            width: 100%;
            padding: 0.45rem 0.55rem;
            border: 0; border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font-family: inherit;
            font-size: 0.86rem;
            box-sizing: border-box;
            border-radius: 0;
            text-align: start;
            outline: none;
            transition: border-color 0.12s ease;
        }
        .lr-doc-row__desc input::placeholder,
        .lr-doc-row__desc textarea::placeholder { color: var(--muted); opacity: 0.85; }
        .lr-doc-row__desc input:focus,
        .lr-doc-row__desc textarea:focus { border-bottom-color: var(--primary); }

        .lr-doc-row__preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.42rem;
        }
        .lr-preview-box {
            width: 4.4rem; height: 4.4rem;
            border-radius: 0.55rem;
            border: 1.5px dashed var(--border);
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            color: var(--muted);
            cursor: pointer;
            transition: border-color 0.12s ease, background 0.12s ease;
            position: relative;
        }
        .lr-preview-box:hover { border-color: var(--primary); }
        .lr-preview-box img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .lr-preview-box .lr-preview-ico { font-size: 1.45rem; }
        .lr-preview-box.has-file { border-style: solid; border-color: var(--primary); }
        .lr-preview-box .lr-preview-ext {
            position: absolute;
            inset-inline-end: 0.18rem;
            bottom: 0.18rem;
            font-size: 0.55rem;
            background: var(--primary);
            color: #fff;
            padding: 0.05rem 0.32rem;
            border-radius: 0.3rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            font-variant: small-caps;
        }

        .lr-preview-actions {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
        }
        .lr-preview-action {
            background: transparent; border: 0;
            cursor: pointer; padding: 0;
            display: inline-flex; flex-direction: column;
            align-items: center; gap: 0.1rem;
            font-size: 0.95rem;
            transition: opacity 0.12s ease, transform 0.12s ease;
        }
        .lr-preview-action:hover:not(:disabled) { transform: translateY(-1px); }
        .lr-preview-action:disabled { opacity: 0.35; cursor: not-allowed; }
        .lr-preview-action span {
            font-size: 0.62rem;
            font-weight: 700;
            color: inherit;
            line-height: 1;
        }
        .lr-preview-action--upload { color: #2563eb; }
        .lr-preview-action--download { color: #059669; }
        html[data-theme="dark"] .lr-preview-action--download { color: #34d399; }
        .lr-preview-action--remove { color: #dc2626; }

        .lr-doc-add-row-wrap {
            display: flex; justify-content: center;
            padding: 0.55rem 0 0;
        }
        .lr-doc-add-row {
            padding: 0.45rem 1rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 0.79rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
            display: inline-flex; align-items: center; gap: 0.42rem;
        }
        .lr-doc-add-row:hover:not(:disabled) {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary-dark);
        }
        .lr-doc-add-row:disabled { opacity: 0.5; cursor: not-allowed; }
        html[data-theme="dark"] .lr-doc-add-row { background: rgba(148, 163, 184, 0.06); }

        /* ===== مرحله ۳ — تعیین وضعیت ===== */
        .lr-status-card {
            margin: 0 auto;
            max-width: 38rem;
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            overflow: hidden;
            background: var(--bg-card);
        }
        .lr-info-row {
            padding: 0.7rem 1rem;
            display: flex; flex-direction: column; gap: 0.3rem;
            text-align: center;
        }
        .lr-info-row + .lr-info-row { border-top: 1px dashed var(--border); }
        .lr-info-row__k {
            font-weight: 800;
            color: var(--text);
            font-size: 0.9rem;
        }
        .lr-info-row__v {
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.85;
        }
        .lr-info-row__v small { display: block; opacity: 0.9; }

        /* ===== footer: 3-column grid برای layout دکمه‌ها ===== */
        .lr-wiz__foot {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 0.5rem;
        }
        .lr-foot-slot { display: flex; align-items: center; gap: 0.45rem; }
        .lr-foot-slot--right { justify-self: start; }   /* در RTL: راست */
        .lr-foot-slot--center { justify-self: center; }
        .lr-foot-slot--left { justify-self: end; }      /* در RTL: چپ */

        /* اطمینان از احترام به attribute hidden در همه دکمه‌های footer */
        .lr-wiz__foot [hidden] { display: none !important; }

        .lr-btn-submit {
            background: linear-gradient(180deg, #8b5cf6, #6d28d9);
            color: #fff;
            border: 0;
            padding: 0.6rem 1.3rem;
            border-radius: 0.55rem;
            font-size: 0.88rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.45rem;
            transition: filter 0.12s ease, transform 0.12s ease;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.32);
        }
        .lr-btn-submit:hover:not(:disabled) { filter: brightness(1.05); transform: translateY(-1px); }
        .lr-btn-submit:active:not(:disabled) { transform: translateY(0); }
        .lr-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

        .lr-btn-close {
            background: rgba(148, 163, 184, 0.22);
            color: var(--text);
            border: 0;
            padding: 0.5rem 1.1rem;
            border-radius: 0.55rem;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.12s ease;
        }
        .lr-btn-close:hover { background: rgba(148, 163, 184, 0.34); }
        html[data-theme="dark"] .lr-btn-close { background: rgba(148, 163, 184, 0.12); }

        @media (max-width: 520px) {
            .lr-wiz__foot {
                grid-template-columns: 1fr;
                gap: 0.4rem;
            }
            .lr-foot-slot--center { justify-self: stretch; order: 1; }
            .lr-foot-slot--right { justify-self: stretch; order: 2; }
            .lr-foot-slot--left { justify-self: stretch; order: 3; }
            .lr-foot-slot { justify-content: center; }
            .lr-btn-submit { width: 100%; justify-content: center; }
        }

        .lr-no-plans {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 2.5rem 1rem;
            text-align: center;
        }
        .lr-no-plans i { font-size: 2.4rem; color: var(--primary-dark); opacity: 0.45; }
        .lr-no-plans h3 { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text); }
        .lr-no-plans p { margin: 0; font-size: 0.82rem; color: var(--muted); line-height: 1.7; max-width: 32rem; }

        .lr-wiz__foot {
            flex-shrink: 0;
            padding: 0.75rem 1rem 0.9rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 0.45rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .lr-wiz__foot-spacer { flex: 1; }
        .lr-wiz__foot .lr-btn--prev {
            background: var(--bg-card);
            color: var(--text);
        }
        .lr-wiz__foot .lr-btn--next {
            font-size: 0.86rem;
            padding: 0.55rem 1.05rem;
        }
        .lr-wiz__foot .lr-btn--next i { font-size: 0.78em; }

        @media (max-width: 520px) {
            .lr-stepper__label { font-size: 0.68rem; }
            .lr-plan-info__row { grid-template-columns: 1fr; gap: 0.15rem; }
            .lr-field-row { grid-template-columns: 1fr; gap: 0.32rem; }
            .lr-field-row > label { margin-bottom: 0.15rem; }
        }
    </style>
@endpush

@section('content')
    <section class="lr-page" aria-labelledby="lr-page-title">
        <div class="lr-head">
            <i class="fa-solid fa-hand-holding-dollar lr-head__ico" aria-hidden="true"></i>
            <h1 id="lr-page-title" class="lr-head__title">درخواست‌های وام</h1>
            <span class="lr-head__badge" aria-label="تعداد درخواست‌ها">{{ $loanRequestsCountFa }} مورد</span>
            <p class="lr-head__lead">
                از این بخش می‌توانید درخواست وام جدید ثبت کنید و وضعیت درخواست‌های قبلی، نظر کارشناس و اطلاعات نمایندهٔ مربوطه را پیگیری نمایید.
            </p>
        </div>

        <div class="lr-toolbar" role="region" aria-label="ابزار جستجو و عملیات">
            <div class="lr-search">
                <label class="visually-hidden" for="lr-search-q">جستجو در درخواست‌ها</label>
                <input
                    type="search"
                    id="lr-search-q"
                    placeholder="جستجو (شماره درخواست، عنوان وام، نام نماینده…)"
                    autocomplete="off"
                    maxlength="120"
                    spellcheck="false"
                >
                <button type="button" class="lr-btn" id="lr-search-btn" aria-label="جستجو">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span>جستجو</span>
                </button>
            </div>
            <div class="lr-toolbar__cta">
                <button
                    type="button"
                    class="lr-icon-btn"
                    id="lr-reload-btn"
                    title="بازخوانی صفحه"
                    aria-label="بازخوانی صفحه و اطلاعات"
                >
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    class="portal-loan__btn portal-loan__btn--primary"
                    id="lr-open-create"
                >
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    درخواست وام جدید
                </button>
            </div>
        </div>

        <div class="lr-wrap lr-desktop-only" role="region" aria-label="جدول درخواست‌های وام">
            <table class="lr-tbl" id="lr-tbl">
                <thead>
                    <tr>
                        <th scope="col">شماره درخواست</th>
                        <th scope="col">وام</th>
                        <th scope="col">مبلغ</th>
                        <th scope="col">تاریخ ثبت درخواست</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">اطلاعات نماینده</th>
                        <th scope="col">نظر کارشناس</th>
                        <th scope="col">عملیات‌ها</th>
                    </tr>
                </thead>
                <tbody id="lr-tbody">
                    @forelse ($loanRequests as $item)
                        {{-- هنگام پیاده‌سازی مدل، این بخش با اطلاعات واقعی پر می‌شود --}}
                    @empty
                        <tr id="lr-empty-row">
                            <td colspan="8" style="padding:0;border-bottom:0">
                                <div class="lr-empty">
                                    <i class="fa-regular fa-folder-open lr-empty__ico" aria-hidden="true"></i>
                                    <span class="lr-empty__title">هنوز درخواست وامی ثبت نکرده‌اید.</span>
                                    <span class="lr-empty__sub">برای ثبت اولین درخواست، دکمهٔ «درخواست وام جدید» را بزنید.</span>
                                    <button type="button" class="lr-empty__cta" data-lr-create-cta>
                                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                        ثبت درخواست وام
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    <tr id="lr-no-result-row" class="lr-no-result-row" hidden>
                        <td colspan="8">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="font-size:1.2rem;opacity:0.55;margin-inline-end:0.4rem"></i>
                            موردی با عبارت جست‌وجو یافت نشد.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="lr-cards" class="lr-cards" role="list" aria-live="polite">
            @forelse ($loanRequests as $item)
                {{-- کارت موبایل برای هر درخواست (پس از پیاده‌سازی مدل تکمیل می‌شود) --}}
            @empty
                <div class="lr-empty" id="lr-empty-card">
                    <i class="fa-regular fa-folder-open lr-empty__ico" aria-hidden="true"></i>
                    <span class="lr-empty__title">هنوز درخواست وامی ثبت نکرده‌اید.</span>
                    <span class="lr-empty__sub">برای ثبت اولین درخواست، دکمهٔ «درخواست وام جدید» را بزنید.</span>
                    <button type="button" class="lr-empty__cta" data-lr-create-cta>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        ثبت درخواست وام
                    </button>
                </div>
            @endforelse
            <div class="lr-empty" id="lr-no-result-card" hidden>
                <i class="fa-solid fa-magnifying-glass lr-empty__ico" aria-hidden="true"></i>
                <span class="lr-empty__title">موردی با عبارت جست‌وجو یافت نشد.</span>
            </div>
        </div>
    </section>

    {{-- ===========================================================
         مودال ویزارد درخواست وام (۳ مرحله) — مرحله ۱ کامل پیاده‌سازی شده
         =========================================================== --}}
    <dialog id="lr-wizard" aria-labelledby="lr-wiz-title">
        <div class="lr-wiz" role="document">
            <header class="lr-wiz__head">
                <button type="button" class="lr-wiz__close" data-lr-wiz-close aria-label="بستن">&times;</button>
                <h2 id="lr-wiz-title" class="lr-wiz__title">
                    <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                    درخواست وام
                </h2>
                <ol class="lr-stepper" aria-label="مراحل ثبت درخواست">
                    <li class="lr-stepper__item is-active" data-lr-step="1" aria-current="step">
                        <span class="lr-stepper__num">۱</span>
                        <span class="lr-stepper__label">انتخاب وام و اقساط</span>
                    </li>
                    <li class="lr-stepper__item" data-lr-step="2">
                        <span class="lr-stepper__num">۲</span>
                        <span class="lr-stepper__label">تکمیل مدارک</span>
                    </li>
                    <li class="lr-stepper__item" data-lr-step="3">
                        <span class="lr-stepper__num">۳</span>
                        <span class="lr-stepper__label">تعیین وضعیت</span>
                    </li>
                </ol>
            </header>

            <div class="lr-wiz__body">
                {{-- ───────────── مرحله ۱ ───────────── --}}
                <section class="lr-step is-active" data-lr-step-panel="1" aria-label="مرحله یک: انتخاب وام و اقساط">
                    @if (empty($loanPlans))
                        <div class="lr-no-plans" role="status">
                            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                            <h3>هنوز هیچ طرح وام فعالی برای انتخاب وجود ندارد.</h3>
                            <p>
                                لطفاً بعداً مجدداً مراجعه کنید؛ مدیر سامانه پس از فعال کردن گزینهٔ «وام در لیست طرح‌ها قرار بگیرد» در پنل ادمین،
                                طرح‌ها در این فهرست ظاهر می‌شوند.
                            </p>
                        </div>
                    @else
                        <div class="lr-step1-grid">
                            {{-- ستون انتخاب وام و اطلاعات طرح --}}
                            <div class="lr-card-soft">
                                <div class="lr-field">
                                    <label for="lr-plan-select">
                                        عنوان طرح وام مورد نظر خود را انتخاب کنید:
                                        <span class="lr-req" aria-hidden="true">*</span>
                                    </label>
                                    <select id="lr-plan-select" name="loan_type_id" required>
                                        <option value="">— انتخاب کنید —</option>
                                        @foreach ($loanPlans as $plan)
                                            <option value="{{ $plan['id'] }}">{{ $plan['title_with_code'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="lr-plan-info" id="lr-plan-info" hidden>
                                    <div class="lr-plan-info__row">
                                        <span class="lr-plan-info__k">درصد بهره:</span>
                                        <span class="lr-plan-info__v lr-plan-info__v--accent" id="lr-info-rate">—</span>
                                    </div>
                                    <div class="lr-plan-info__row">
                                        <span class="lr-plan-info__k">جریمه دیرکرد روزانه:</span>
                                        <span class="lr-plan-info__v" id="lr-info-late">—</span>
                                    </div>
                                    <div class="lr-plan-info__row">
                                        <span class="lr-plan-info__k">سود زودکرد روزانه:</span>
                                        <span class="lr-plan-info__v" id="lr-info-early">—</span>
                                    </div>
                                    <div class="lr-plan-info__row">
                                        <span class="lr-plan-info__k">مدارک مورد نیاز اولیه:</span>
                                        <span class="lr-plan-info__v" id="lr-info-docs">—</span>
                                    </div>
                                </div>
                            </div>

                            {{-- ستون پارامترها (پس از انتخاب وام نمایش داده می‌شود) --}}
                            <div class="lr-card-soft" id="lr-params-col" hidden>
                                <div class="lr-field">
                                    <label for="lr-amount-input">
                                        مبلغ وام مورد نظر خود را تعیین کنید:
                                        <span class="lr-req" aria-hidden="true">*</span>
                                    </label>
                                    <div class="lr-num-input lr-num-input--with-unit">
                                        <button type="button" class="lr-num-input__btn" data-lr-step="amount" data-lr-dir="-1" aria-label="کاهش مبلغ">−</button>
                                        <input
                                            type="text"
                                            id="lr-amount-input"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            value=""
                                        >
                                        <span class="lr-num-input__unit">تومان</span>
                                        <button type="button" class="lr-num-input__btn" data-lr-step="amount" data-lr-dir="+1" aria-label="افزایش مبلغ">+</button>
                                    </div>
                                    <p class="lr-field-hint" id="lr-amount-hint"></p>
                                    <p class="lr-field-error" id="lr-amount-error" hidden></p>
                                </div>

                                <div class="lr-field-row">
                                    <label for="lr-count-input">دوره بازپرداخت:</label>
                                    <div class="lr-num-input lr-num-input--with-unit">
                                        <button type="button" class="lr-num-input__btn" data-lr-step="count" data-lr-dir="-1" aria-label="کاهش">−</button>
                                        <input
                                            type="text"
                                            id="lr-count-input"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            value="۱"
                                            readonly
                                        >
                                        <span class="lr-num-input__unit" id="lr-count-unit">ماهه</span>
                                        <button type="button" class="lr-num-input__btn" data-lr-step="count" data-lr-dir="+1" aria-label="افزایش">+</button>
                                    </div>
                                </div>

                                <div class="lr-field-row">
                                    <label for="lr-gap-input">فاصله بین هر قسط:</label>
                                    <div class="lr-num-input lr-num-input--with-unit">
                                        <button type="button" class="lr-num-input__btn" data-lr-step="gap" data-lr-dir="-1" aria-label="کاهش">−</button>
                                        <input
                                            type="text"
                                            id="lr-gap-input"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            value="۱"
                                            readonly
                                        >
                                        <span class="lr-num-input__unit" id="lr-gap-unit">ماه</span>
                                        <button type="button" class="lr-num-input__btn" data-lr-step="gap" data-lr-dir="+1" aria-label="افزایش">+</button>
                                    </div>
                                </div>

                                <div class="lr-field-row lr-field-row--summary">
                                    <span style="font-weight:800;color:var(--muted);font-size:0.78rem">مبلغ هر قسط:</span>
                                    <span class="lr-summary-v" id="lr-installment-out">—</span>
                                </div>

                                <div class="lr-totals">
                                    <div class="lr-total-box lr-total-box--net">
                                        <span class="lr-total-box__k">خالص دریافتی:</span>
                                        <span class="lr-total-box__v" id="lr-net-out">—</span>
                                    </div>
                                    <div class="lr-total-box lr-total-box--gross">
                                        <span class="lr-total-box__k">کل بازپرداخت:</span>
                                        <span class="lr-total-box__v" id="lr-total-out">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lr-card-soft lr-desc-wrap" id="lr-desc-wrap" hidden>
                            <div class="lr-field" style="margin:0">
                                <label for="lr-desc-input">
                                    شرح کالاها و خدمات:
                                    <span class="lr-req" aria-hidden="true">*</span>
                                </label>
                                <textarea id="lr-desc-input" rows="3" minlength="3" maxlength="2000" required aria-required="true" placeholder="کالاها و خدمات مورد نظر خود را شرح دهید…"></textarea>
                                <p class="lr-field-hint" id="lr-desc-hint">حداقل ۳ و حداکثر ۲٬۰۰۰ نویسه.</p>
                                <p class="lr-field-error" id="lr-desc-error" hidden></p>
                            </div>
                        </div>

                        <p class="lr-step-error" id="lr-step1-error" hidden></p>
                    @endif
                </section>

                {{-- ───────────── مرحله ۲ — تکمیل مدارک ───────────── --}}
                <section class="lr-step" data-lr-step-panel="2" aria-label="مرحله دو: تکمیل مدارک" hidden>
                    {{-- محتوای این بخش به صورت پویا توسط JS بر اساس مدارک طرح انتخاب‌شده ساخته می‌شود --}}
                    <div id="lr-step2-root">
                        <div class="lr-step-coming">
                            <i class="fa-regular fa-hourglass" aria-hidden="true"></i>
                            <p>در حال آماده‌سازی…</p>
                        </div>
                    </div>
                    <p class="lr-step-error" id="lr-step2-error" hidden></p>
                </section>

                {{-- ───────────── مرحله ۳ — تعیین وضعیت ───────────── --}}
                <section class="lr-step" data-lr-step-panel="3" aria-label="مرحله سه: تعیین وضعیت" hidden>
                    <div class="lr-status-card">
                        <div class="lr-info-row">
                            <span class="lr-info-row__k">وضعیت:</span>
                            <span class="lr-info-row__v" id="lr-st3-status">در انتظار بررسی کارشناس</span>
                        </div>
                        <div class="lr-info-row">
                            <span class="lr-info-row__k">تاریخ ثبت درخواست:</span>
                            <span class="lr-info-row__v" id="lr-st3-date">—</span>
                        </div>
                        <div class="lr-info-row">
                            <span class="lr-info-row__k">نظر کارشناس:</span>
                            <span class="lr-info-row__v">
                                تاکنون کارشناس نظری ثبت نکرده است.
                                <small id="lr-st3-physical">وضعیت تحویل شدن مدارک فیزیکی به شرکت: به شرکت نرسیده</small>
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="lr-wiz__foot">
                {{-- در RTL: راست view → مرحلهٔ قبل --}}
                <div class="lr-foot-slot lr-foot-slot--right">
                    <button type="button" class="lr-btn lr-btn--prev" id="lr-step-prev" hidden>
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        مرحلهٔ قبل
                    </button>
                </div>
                {{-- مرکز → ارسال درخواست --}}
                <div class="lr-foot-slot lr-foot-slot--center">
                    <button type="button" class="lr-btn-submit" id="lr-submit-btn" hidden>
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال درخواست به کارشناس
                    </button>
                </div>
                {{-- در RTL: چپ view → مرحلهٔ بعد / بستن --}}
                <div class="lr-foot-slot lr-foot-slot--left">
                    <button type="button" class="lr-btn-close" id="lr-close-btn" hidden>
                        بستن
                    </button>
                    <button type="button" class="portal-loan__btn portal-loan__btn--primary lr-btn--next" id="lr-step-next" disabled>
                        مرحلهٔ بعد
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                </div>
            </footer>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__LR_PLANS__ = @json($loanPlans ?? []);
    </script>
    <script>
        (function () {
            'use strict';

            var searchInput = document.getElementById('lr-search-q');
            var searchBtn   = document.getElementById('lr-search-btn');
            var reloadBtn   = document.getElementById('lr-reload-btn');
            var openCreate  = document.getElementById('lr-open-create');
            var tbody       = document.getElementById('lr-tbody');
            var cardsRoot   = document.getElementById('lr-cards');
            var emptyRow    = document.getElementById('lr-empty-row');
            var emptyCard   = document.getElementById('lr-empty-card');
            var noResultRow = document.getElementById('lr-no-result-row');
            var noResultCard = document.getElementById('lr-no-result-card');

            function hasAnyDataRow() {
                if (!tbody) return false;
                var rows = tbody.querySelectorAll('tr[data-lr-row]');
                return rows.length > 0;
            }

            function applyFilter() {
                if (!searchInput) return;
                var q = (searchInput.value || '').trim().toLowerCase();
                var rows = tbody ? tbody.querySelectorAll('tr[data-lr-row]') : [];
                var cards = cardsRoot ? cardsRoot.querySelectorAll('[data-lr-card]') : [];
                var matched = 0;
                var apply = function (el) {
                    var hay = (el.getAttribute('data-search') || '').toLowerCase();
                    var show = !q || hay.indexOf(q) !== -1;
                    el.style.display = show ? '' : 'none';
                    if (show) matched++;
                };
                rows.forEach(apply);
                cards.forEach(apply);

                var dataRowCount = (rows.length || cards.length) > 0;
                var noResultActive = !!q && dataRowCount && matched === 0;
                if (noResultRow) noResultRow.hidden = !noResultActive;
                if (noResultCard) noResultCard.hidden = !noResultActive;

                // اگر اصلاً ردیف داده‌ای نداریم، empty-state اولیه را همیشه نشان بده
                if (!dataRowCount) {
                    if (emptyRow) emptyRow.style.display = '';
                    if (emptyCard) emptyCard.style.display = '';
                }
            }

            if (searchBtn) searchBtn.addEventListener('click', applyFilter);
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); applyFilter(); }
                });
                // فیلتر زنده وقتی ورودی پاک می‌شود تا empty-state اولیه برگردد
                searchInput.addEventListener('input', function () {
                    if (!searchInput.value.trim()) applyFilter();
                });
            }

            if (reloadBtn) {
                reloadBtn.addEventListener('click', function () {
                    if (reloadBtn.disabled) return;
                    reloadBtn.classList.add('is-loading');
                    reloadBtn.disabled = true;
                    // برای جلوگیری از حالت تخیل ناقص اگر مرورگر کند بود
                    window.setTimeout(function () { window.location.reload(); }, 80);
                });
            }

            // ===============================================================
            //  WIZARD: درخواست وام جدید
            // ===============================================================
            var wizardDialog = document.getElementById('lr-wizard');
            var LR_PLANS = Array.isArray(window.__LR_PLANS__) ? window.__LR_PLANS__ : [];
            var LR = {
                step: 1,
                planId: null,
                plan: null,
                amount: 0,
                count: 1,
                gap: 1,
                desc: '',
                docState: [],
                docStatePlanId: null,
                submitted: false,
            };

            var FA_DIGITS = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

            function faNum(s) {
                return String(s).replace(/[0-9]/g, function (c) { return FA_DIGITS[c.charCodeAt(0) - 48]; });
            }
            function unFaNum(s) {
                return String(s)
                    .replace(/[\u06F0-\u06F9]/g, function (c) { return String(c.charCodeAt(0) - 0x06F0); })
                    .replace(/[\u0660-\u0669]/g, function (c) { return String(c.charCodeAt(0) - 0x0660); });
            }
            function formatMoney(n) {
                n = Math.max(0, Math.floor(Number(n) || 0));
                return faNum(n.toLocaleString('en-US')) + ' تومان';
            }
            function clamp(n, mn, mx) { return Math.min(mx, Math.max(mn, n)); }

            // ---------- بازه‌های مجاز ----------
            // مبلغ: حداقل ۱۰۰هزار تومان، حداکثر = max_loan_amount طرح یا ۱۰ میلیارد تومان
            function amountBounds(plan) {
                var max = (plan && plan.max_loan_amount) ? plan.max_loan_amount : 10000000000;
                return { min: 100000, max: max, step: 100000 };
            }
            // فاصله: ۱ تا max_installment_gap طرح (پیش‌فرض ۲۴)
            function gapBounds(plan) {
                var max = (plan && plan.max_installment_gap) ? plan.max_installment_gap : 24;
                return { min: 1, max: Math.max(1, max) };
            }
            // تعداد قسط: بسته به repayment.type
            //  - unlimited: ۱ تا ۳۶۰
            //  - max_until: ۱ تا max_months
            //  - allowed_months: محدود به مقادیر مجاز
            function countBoundsAndAllowed(plan) {
                var rep = (plan && plan.repayment) || { type: 'unlimited' };
                if (rep.type === 'unlimited') {
                    return { min: 1, max: 360, allowed: null };
                }
                if (rep.type === 'max_until') {
                    var mm = Math.max(1, Number(rep.max_months || 1));
                    return { min: 1, max: mm, allowed: null };
                }
                if (rep.type === 'allowed_months') {
                    var opts = (rep.allowed_rows || [])
                        .map(function (r) { return Number(r.months || 0); })
                        .filter(function (x) { return x > 0; });
                    opts = Array.from(new Set(opts)).sort(function (a, b) { return a - b; });
                    if (opts.length === 0) {
                        return { min: 1, max: 1, allowed: [1] };
                    }
                    return { min: opts[0], max: opts[opts.length - 1], allowed: opts };
                }
                return { min: 1, max: 360, allowed: null };
            }
            // اگر سقف برای تعداد ماه انتخابی (allowed_rows[i].cap) تعریف شده باشد، مبلغ هم نباید از آن بالاتر برود.
            function capForCount(plan, countMonths) {
                var rep = (plan && plan.repayment) || {};
                if (rep.type !== 'allowed_months') return null;
                var rows = rep.allowed_rows || [];
                for (var i = 0; i < rows.length; i++) {
                    if (Number(rows[i].months) === Number(countMonths)) {
                        return rows[i].cap ? Number(rows[i].cap) : null;
                    }
                }
                return null;
            }

            // ---------- فرمول‌ها (مطابق LoanFileFinanceCalculator) ----------
            function durationMonths(count, gap, gapUnit) {
                var mul = gapUnit === 'weekly' ? (12 / 52) : 1;
                return Math.max(0, count * gap * mul);
            }
            function calcProfit(amount, ratePercent, method, count, gap, gapUnit) {
                var months = durationMonths(count, gap, gapUnit);
                if (amount <= 0 || ratePercent <= 0 || months <= 0) return 0;
                var rateFactor = ratePercent / 100;
                var profit = method === 'bank'
                    ? (amount * rateFactor * (months / 12))
                    : (amount * rateFactor * months);
                return Math.max(0, Math.round(profit));
            }

            // ---------- بازشدن و بستن مودال ----------
            function openWizard() {
                if (!wizardDialog) return;
                resetWizard();
                if (typeof wizardDialog.showModal === 'function') {
                    wizardDialog.showModal();
                } else {
                    // fallback (مرورگرهای بسیار قدیمی)
                    wizardDialog.setAttribute('open', '');
                }
            }
            function closeWizard() {
                if (!wizardDialog) return;
                if (wizardDialog.open) wizardDialog.close();
                disposeDocState();
            }
            function resetWizard() {
                disposeDocState();
                LR.step = 1;
                LR.planId = null;
                LR.plan = null;
                LR.amount = 0;
                LR.count = 1;
                LR.gap = 1;
                LR.desc = '';
                LR.submitted = false;
                var sel = document.getElementById('lr-plan-select');
                if (sel) sel.value = '';
                var info = document.getElementById('lr-plan-info');
                if (info) info.hidden = true;
                var params = document.getElementById('lr-params-col');
                if (params) params.hidden = true;
                var desc = document.getElementById('lr-desc-wrap');
                if (desc) desc.hidden = true;
                var descIn = document.getElementById('lr-desc-input');
                if (descIn) descIn.value = '';
                var descErr = document.getElementById('lr-desc-error');
                if (descErr) descErr.hidden = true;
                var err = document.getElementById('lr-step1-error');
                if (err) err.hidden = true;
                var ae = document.getElementById('lr-amount-error');
                if (ae) ae.hidden = true;
                var st2err = document.getElementById('lr-step2-error');
                if (st2err) st2err.hidden = true;
                var st2root = document.getElementById('lr-step2-root');
                if (st2root) st2root.innerHTML = '';
                updateStepper();
                updateNextBtn();
                showStep(1);
            }

            // ---------- اسکلت stepper / step navigation ----------
            function updateStepper() {
                document.querySelectorAll('[data-lr-step]').forEach(function (li) {
                    var n = Number(li.getAttribute('data-lr-step'));
                    li.classList.toggle('is-active', n === LR.step);
                    li.classList.toggle('is-complete', n < LR.step);
                    if (n === LR.step) li.setAttribute('aria-current', 'step');
                    else li.removeAttribute('aria-current');
                });
            }
            function showStep(n) {
                LR.step = n;
                document.querySelectorAll('[data-lr-step-panel]').forEach(function (p) {
                    var v = Number(p.getAttribute('data-lr-step-panel'));
                    var active = v === n;
                    p.classList.toggle('is-active', active);
                    p.hidden = !active;
                });
                var prev = document.getElementById('lr-step-prev');
                var next = document.getElementById('lr-step-next');
                var sub = document.getElementById('lr-submit-btn');
                var close = document.getElementById('lr-close-btn');

                // footer slots بر اساس مرحله
                if (n === 1) {
                    if (prev) prev.hidden = true;
                    if (next) next.hidden = false;
                    if (sub) sub.hidden = true;
                    if (close) close.hidden = true;
                } else if (n === 2) {
                    if (prev) prev.hidden = false;
                    if (next) next.hidden = true;
                    if (sub) sub.hidden = false;
                    if (close) close.hidden = true;
                } else if (n === 3) {
                    // پس از ارسال موفق: مرحلهٔ قبل و بستن
                    if (prev) prev.hidden = false;
                    if (next) next.hidden = true;
                    if (sub) sub.hidden = true;
                    if (close) close.hidden = false;
                }

                updateStepper();
                updateNextBtn();
            }

            // ---------- اعتبارسنجی و دکمه بعد ----------
            var DESC_MIN = 3;
            var DESC_MAX = 2000;

            function descLengthValid() {
                var t = (LR.desc || '').trim();
                return t.length >= DESC_MIN && t.length <= DESC_MAX;
            }

            function step1Valid() {
                if (!LR.plan) return false;
                var ab = amountBounds(LR.plan);
                var gb = gapBounds(LR.plan);
                var cb = countBoundsAndAllowed(LR.plan);
                if (LR.amount < ab.min || LR.amount > ab.max) return false;
                if (LR.gap < gb.min || LR.gap > gb.max) return false;
                if (cb.allowed) {
                    if (cb.allowed.indexOf(LR.count) === -1) return false;
                } else {
                    if (LR.count < cb.min || LR.count > cb.max) return false;
                }
                var cap = capForCount(LR.plan, LR.count);
                if (cap && LR.amount > cap) return false;
                if (!descLengthValid()) return false;
                return true;
            }
            function updateNextBtn() {
                var btn = document.getElementById('lr-step-next');
                if (!btn) return;
                if (LR.step === 1) {
                    btn.disabled = !step1Valid() || LR_PLANS.length === 0;
                } else {
                    btn.disabled = false;
                }
            }

            // ---------- انتخاب طرح ----------
            function fillPlanInfo(plan) {
                document.getElementById('lr-info-rate').textContent =
                    (plan.interest_rate_fa || '۰') + '٪ (' + (plan.profit_method_label || '') + ')';

                document.getElementById('lr-info-late').textContent = plan.daily_late_coefficient > 0
                    ? 'دارد'
                    : 'ندارد';

                document.getElementById('lr-info-early').textContent = plan.daily_early_coefficient > 0
                    ? 'دارد'
                    : 'ندارد';

                var docsEl = document.getElementById('lr-info-docs');
                var docs = Array.isArray(plan.initial_documents) ? plan.initial_documents : [];
                if (docs.length === 0) {
                    docsEl.innerHTML = '<span class="lr-no-docs"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> ندارد - سایر مدارک پس از ارزیابی</span>';
                } else {
                    var chips = docs.map(function (d) {
                        var safe = escapeHtml(d.title || '');
                        return '<span class="lr-chip"><i class="fa-regular fa-file-lines" aria-hidden="true"></i>' + safe + '</span>';
                    }).join('');
                    docsEl.innerHTML = '<span class="lr-chip-list">' + chips + '</span>';
                }
            }
            function escapeHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function setupAmountInitial(plan) {
                // مقدار اولیه پیشنهادی برای مبلغ: نصف سقف یا ۱ میلیون
                var ab = amountBounds(plan);
                var suggested;
                if (plan.max_loan_amount) {
                    suggested = Math.floor(plan.max_loan_amount / 2);
                } else {
                    suggested = 1000000;
                }
                suggested = clamp(suggested, ab.min, ab.max);
                LR.amount = suggested;
            }

            function setupCountInitial(plan) {
                var cb = countBoundsAndAllowed(plan);
                if (cb.allowed && cb.allowed.length) {
                    LR.count = cb.allowed[0];
                } else {
                    LR.count = 1;
                }
            }

            function setupGapInitial(plan) {
                LR.gap = 1;
            }

            function onPlanChange(planId) {
                var plan = LR_PLANS.find(function (p) { return String(p.id) === String(planId); }) || null;
                LR.plan = plan;
                LR.planId = plan ? plan.id : null;

                // اگر طرح عوض شد، state مدارک قبلی را پاک کن
                if (LR.docStatePlanId !== LR.planId) {
                    disposeDocState();
                }

                var info = document.getElementById('lr-plan-info');
                var params = document.getElementById('lr-params-col');
                var desc = document.getElementById('lr-desc-wrap');

                if (!plan) {
                    if (info) info.hidden = true;
                    if (params) params.hidden = true;
                    if (desc) desc.hidden = true;
                    updateNextBtn();
                    return;
                }

                fillPlanInfo(plan);
                setupAmountInitial(plan);
                setupCountInitial(plan);
                setupGapInitial(plan);

                if (info) info.hidden = false;
                if (params) params.hidden = false;
                if (desc) desc.hidden = false;

                refreshOutputs();
                refreshDescUi();
            }

            // ---------- خروجی‌های زنده ----------
            function refreshOutputs() {
                if (!LR.plan) return;
                var ab = amountBounds(LR.plan);
                var gb = gapBounds(LR.plan);
                var cb = countBoundsAndAllowed(LR.plan);

                LR.gap = clamp(LR.gap, gb.min, gb.max);
                if (cb.allowed) {
                    if (cb.allowed.indexOf(LR.count) === -1) {
                        // نزدیک‌ترین مقدار مجاز
                        var nearest = cb.allowed.reduce(function (a, b) {
                            return Math.abs(b - LR.count) < Math.abs(a - LR.count) ? b : a;
                        });
                        LR.count = nearest;
                    }
                } else {
                    LR.count = clamp(LR.count, cb.min, cb.max);
                }
                // محدودیت مبلغ با کلیپ نمی‌شود تا کاربر بتواند فکر کند و خطا را ببیند
                var cap = capForCount(LR.plan, LR.count);
                var effectiveMax = cap ? Math.min(ab.max, cap) : ab.max;

                // pretty input values
                var amountInput = document.getElementById('lr-amount-input');
                if (amountInput) {
                    var pretty = LR.amount > 0 ? faNum(LR.amount.toLocaleString('en-US')) : '';
                    amountInput.value = pretty;
                }
                document.getElementById('lr-count-input').value = faNum(String(LR.count));
                document.getElementById('lr-gap-input').value = faNum(String(LR.gap));

                document.getElementById('lr-count-unit').textContent = LR.plan.installment_gap_unit === 'weekly' ? 'هفته‌ای' : 'ماهه';
                document.getElementById('lr-gap-unit').textContent = LR.plan.installment_gap_unit === 'weekly' ? 'هفته' : 'ماه';

                // hint مبلغ
                var hintParts = [];
                hintParts.push('حداقل ' + formatMoney(ab.min));
                if (ab.max < 10000000000) {
                    hintParts.push('حداکثر ' + formatMoney(ab.max));
                }
                if (cap && cap < ab.max) {
                    hintParts.push('سقف برای این دوره: ' + formatMoney(cap));
                }
                document.getElementById('lr-amount-hint').textContent = hintParts.join(' • ');

                // اعتبارسنجی مبلغ
                var amountErr = document.getElementById('lr-amount-error');
                if (amountErr) {
                    if (LR.amount > 0 && (LR.amount < ab.min || LR.amount > effectiveMax)) {
                        var msg = LR.amount < ab.min
                            ? 'مبلغ نباید کمتر از ' + formatMoney(ab.min) + ' باشد.'
                            : 'مبلغ نباید بیشتر از ' + formatMoney(effectiveMax) + ' باشد.';
                        amountErr.textContent = msg;
                        amountErr.hidden = false;
                    } else {
                        amountErr.hidden = true;
                    }
                }

                // محاسبهٔ سود/قسط/کل
                var profit = calcProfit(LR.amount, LR.plan.interest_rate, LR.plan.profit_method, LR.count, LR.gap, LR.plan.installment_gap_unit);
                var total = LR.amount + profit;
                var installment = LR.count > 0 ? Math.ceil(total / LR.count) : 0;

                document.getElementById('lr-installment-out').textContent = total > 0 ? formatMoney(installment) : '—';
                document.getElementById('lr-net-out').textContent = LR.amount > 0 ? formatMoney(LR.amount) : '—';
                document.getElementById('lr-total-out').textContent = total > 0 ? formatMoney(total) : '—';

                updateNextBtn();
            }

            // ---------- UI شرح کالا و خدمات ----------
            function refreshDescUi() {
                var hint = document.getElementById('lr-desc-hint');
                var err = document.getElementById('lr-desc-error');
                var t = (LR.desc || '').trim();
                var len = t.length;
                if (hint) {
                    hint.textContent = 'حداقل ' + faNum(DESC_MIN) + ' و حداکثر ' + faNum(DESC_MAX.toLocaleString('en-US')) + ' نویسه — تعداد فعلی: ' + faNum(len);
                }
                if (err) {
                    if (len === 0) {
                        err.textContent = 'پر کردن این فیلد الزامی است.';
                        err.hidden = false;
                    } else if (len < DESC_MIN) {
                        err.textContent = 'متن باید حداقل ' + faNum(DESC_MIN) + ' نویسه باشد.';
                        err.hidden = false;
                    } else if (len > DESC_MAX) {
                        err.textContent = 'متن نباید بیشتر از ' + faNum(DESC_MAX.toLocaleString('en-US')) + ' نویسه باشد.';
                        err.hidden = false;
                    } else {
                        err.hidden = true;
                    }
                }
                updateNextBtn();
            }

            // ============================================================
            // مرحله ۲: تکمیل مدارک
            // ============================================================
            //
            // ساختار: LR.docState = [
            //     { presetKey, title, rows: [{ id, file, previewUrl, description }], nextRowId, maxRows }
            // ]
            //
            // امنیت:
            //  • mime/size/extension whitelist
            //  • حداکثر ۵ ردیف برای هر مدرک
            //  • escapeHtml در همه‌جا (innerHTML از داده‌های سرور یا کاربر)
            //  • Object URLها هنگام حذف/پاک‌سازی revoke می‌شوند تا memory leak نشود
            //
            var ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
            var ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            var MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
            var MAX_ROWS_PER_DOC = 5;

            function extOf(name) {
                var s = String(name || '');
                var i = s.lastIndexOf('.');
                if (i < 0) return '';
                return s.substring(i + 1).toLowerCase();
            }
            function validateFile(file) {
                if (!file) return { ok: false, msg: 'فایلی انتخاب نشده است.' };
                if (file.size > MAX_FILE_SIZE) return { ok: false, msg: 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.' };
                var mt = String(file.type || '').toLowerCase();
                var ex = extOf(file.name);
                if (ALLOWED_MIMES.indexOf(mt) === -1 && ALLOWED_EXTS.indexOf(ex) === -1) {
                    return { ok: false, msg: 'فقط فایل‌های JPG، PNG، WEBP یا PDF مجاز هستند.' };
                }
                return { ok: true };
            }
            function revokePreviewUrl(row) {
                if (row && row.previewUrl) {
                    try { URL.revokeObjectURL(row.previewUrl); } catch (e) {}
                    row.previewUrl = null;
                }
            }

            // ساخت state مدارک از روی initial_documents طرح انتخاب‌شده
            function buildDocState(plan) {
                disposeDocState();
                LR.docState = [];
                LR.docStatePlanId = plan ? plan.id : null;
                if (!plan || !Array.isArray(plan.initial_documents)) return;
                plan.initial_documents.forEach(function (d, idx) {
                    LR.docState.push({
                        presetKey: 'doc_' + idx,
                        title: String(d.title || ('مدرک ' + (idx + 1))),
                        rows: [{ id: 1, file: null, previewUrl: null, description: '' }],
                        nextRowId: 2,
                    });
                });
            }
            function disposeDocState() {
                if (!Array.isArray(LR.docState)) return;
                LR.docState.forEach(function (doc) {
                    if (!doc || !Array.isArray(doc.rows)) return;
                    doc.rows.forEach(revokePreviewUrl);
                });
                LR.docState = [];
                LR.docStatePlanId = null;
            }
            function ensureDocStateForCurrentPlan() {
                if (!LR.plan) return;
                if (LR.docStatePlanId !== LR.plan.id) {
                    buildDocState(LR.plan);
                }
            }

            // وضعیت چیپ هر مدرک
            function docStatusInfo(doc) {
                var hasAnyFile = doc.rows.some(function (r) { return !!r.file; });
                if (hasAnyFile) return { label: 'ثبت‌شده', cls: 'lr-status-chip-row--done', sub: '' };
                return { label: 'ثبت', cls: 'lr-status-chip-row--pending', sub: 'در انتظار ثبت توسط کاربر' };
            }

            // ---------- HTML برای مرحله ۲ ----------
            function renderStep2() {
                var root = document.getElementById('lr-step2-root');
                if (!root) return;
                if (!LR.plan) {
                    root.innerHTML = '<div class="lr-step-coming"><i class="fa-regular fa-circle-question"></i><p>ابتدا در مرحلهٔ پیشین یک طرح را انتخاب کنید.</p></div>';
                    return;
                }

                if (!step2NeedsDocs()) {
                    root.innerHTML =
                        '<div class="lr-no-docs-msg" role="status">' +
                            '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>' +
                            '<div>وام دارای مدارک نمی‌باشد، جهت ثبت نهایی درخواست بر روی دکمهٔ «ارسال درخواست به کارشناس» پایین فرم کلیک نمایید.</div>' +
                        '</div>';
                    return;
                }

                // وضعیتی که حداقل یک مدرک نیاز دارد
                var alertHtml =
                    '<div class="lr-doc-alert" role="status">' +
                        '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>' +
                        '<div>کاربر گرامی، پس از ثبت مدارک، بر روی دکمهٔ «ارسال درخواست به کارشناس» پایین فرم کلیک نمایید.</div>' +
                    '</div>';

                var cardsHtml = LR.docState.map(function (doc) {
                    var rowsHtml = doc.rows.map(function (r) { return rowHtml(doc, r); }).join('');
                    var addDisabled = doc.rows.length >= MAX_ROWS_PER_DOC ? 'disabled' : '';
                    return (
                        '<div class="lr-doc-card" data-lr-doc="' + escapeHtml(doc.presetKey) + '">' +
                            '<div class="lr-doc-card__head">' + escapeHtml(doc.title) + '</div>' +
                            '<div class="lr-doc-card__body">' +
                                rowsHtml +
                                '<div class="lr-doc-add-row-wrap">' +
                                    '<button type="button" class="lr-doc-add-row" data-lr-add-row="' + escapeHtml(doc.presetKey) + '" ' + addDisabled + '>' +
                                        '<i class="fa-solid fa-plus" aria-hidden="true"></i>' +
                                        ' افزودن ردیف پیوست' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>'
                    );
                }).join('');

                root.innerHTML = alertHtml + cardsHtml;
                attachStep2Handlers();
            }

            function rowHtml(doc, row) {
                var st = docStatusInfo(doc);
                var hasFile = !!row.file;
                var previewInner;
                if (hasFile && row.file && row.file.type && row.file.type.indexOf('image/') === 0 && row.previewUrl) {
                    previewInner = '<img src="' + escapeAttr(row.previewUrl) + '" alt="' + escapeAttr(row.file.name || '') + '">';
                } else if (hasFile) {
                    previewInner = '<i class="fa-regular fa-file-pdf lr-preview-ico" aria-hidden="true"></i>' +
                        '<span class="lr-preview-ext">' + escapeHtml(extOf(row.file.name) || 'PDF') + '</span>';
                } else {
                    previewInner = '<i class="fa-regular fa-image lr-preview-ico" aria-hidden="true"></i>';
                }
                var presetKey = escapeAttr(doc.presetKey);
                var rowId = escapeAttr(String(row.id));
                var pendingText = !hasFile
                    ? '<div class="lr-status-chip-row--pending-text">در انتظار ثبت توسط کاربر</div>'
                    : '';

                return (
                    '<div class="lr-doc-row" data-lr-row="' + rowId + '" data-lr-doc-row="' + presetKey + '">' +
                        '<div class="lr-doc-row__status">' +
                            '<span class="lr-status-chip-row ' + escapeAttr(st.cls) + '">' + escapeHtml(hasFile ? 'ثبت‌شده' : 'ثبت') + '</span>' +
                            pendingText +
                        '</div>' +
                        '<div class="lr-doc-row__desc">' +
                            '<input type="text" maxlength="500" placeholder="توضیحات خود را در صورت لزوم اینجا وارد کنید" ' +
                                'value="' + escapeAttr(row.description || '') + '" ' +
                                'data-lr-row-desc="' + presetKey + '|' + rowId + '">' +
                        '</div>' +
                        '<div class="lr-doc-row__preview">' +
                            '<label class="lr-preview-box ' + (hasFile ? 'has-file' : '') + '" tabindex="0" aria-label="انتخاب فایل">' +
                                previewInner +
                                '<input type="file" hidden accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" ' +
                                    'data-lr-row-file="' + presetKey + '|' + rowId + '">' +
                            '</label>' +
                            '<div class="lr-preview-actions">' +
                                '<button type="button" class="lr-preview-action lr-preview-action--remove" ' +
                                    'data-lr-row-remove="' + presetKey + '|' + rowId + '" ' +
                                    (hasFile ? '' : 'disabled') + ' aria-label="حذف">' +
                                    '<i class="fa-solid fa-trash" aria-hidden="true"></i>' +
                                    '<span>حذف</span>' +
                                '</button>' +
                                '<button type="button" class="lr-preview-action lr-preview-action--upload" ' +
                                    'data-lr-row-upload="' + presetKey + '|' + rowId + '" aria-label="آپلود">' +
                                    '<i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>' +
                                    '<span>آپلود</span>' +
                                '</button>' +
                                '<button type="button" class="lr-preview-action lr-preview-action--download" ' +
                                    'data-lr-row-download="' + presetKey + '|' + rowId + '" ' +
                                    (hasFile ? '' : 'disabled') + ' aria-label="دانلود">' +
                                    '<i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>' +
                                    '<span>دانلود</span>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }

            function escapeAttr(s) { return escapeHtml(s).replace(/"/g, '&quot;'); }

            function step2NeedsDocs() {
                if (!LR.plan) return false;
                return Array.isArray(LR.plan.initial_documents) && LR.plan.initial_documents.length > 0;
            }

            function findDoc(presetKey) {
                return (LR.docState || []).find(function (d) { return d.presetKey === presetKey; }) || null;
            }
            function findRow(doc, rowId) {
                if (!doc) return null;
                rowId = Number(rowId);
                return doc.rows.find(function (r) { return Number(r.id) === rowId; }) || null;
            }

            function attachStep2Handlers() {
                var root = document.getElementById('lr-step2-root');
                if (!root) return;

                // file change
                root.querySelectorAll('[data-lr-row-file]').forEach(function (input) {
                    input.addEventListener('change', function () {
                        var parts = input.getAttribute('data-lr-row-file').split('|');
                        handleFileSelect(parts[0], parts[1], input);
                    });
                });

                // upload button (open file picker)
                root.querySelectorAll('[data-lr-row-upload]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var key = btn.getAttribute('data-lr-row-upload');
                        var fileInput = root.querySelector('[data-lr-row-file="' + key + '"]');
                        if (fileInput) fileInput.click();
                    });
                });

                // remove
                root.querySelectorAll('[data-lr-row-remove]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (btn.disabled) return;
                        var parts = btn.getAttribute('data-lr-row-remove').split('|');
                        handleRowRemove(parts[0], parts[1]);
                    });
                });

                // download
                root.querySelectorAll('[data-lr-row-download]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (btn.disabled) return;
                        var parts = btn.getAttribute('data-lr-row-download').split('|');
                        handleRowDownload(parts[0], parts[1]);
                    });
                });

                // description input
                root.querySelectorAll('[data-lr-row-desc]').forEach(function (input) {
                    input.addEventListener('input', function () {
                        var parts = input.getAttribute('data-lr-row-desc').split('|');
                        var doc = findDoc(parts[0]);
                        var row = findRow(doc, parts[1]);
                        if (row) row.description = input.value || '';
                    });
                });

                // add row
                root.querySelectorAll('[data-lr-add-row]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (btn.disabled) return;
                        var doc = findDoc(btn.getAttribute('data-lr-add-row'));
                        if (!doc) return;
                        if (doc.rows.length >= MAX_ROWS_PER_DOC) return;
                        doc.rows.push({ id: doc.nextRowId++, file: null, previewUrl: null, description: '' });
                        renderStep2();
                    });
                });
            }

            function handleFileSelect(presetKey, rowId, fileInput) {
                var doc = findDoc(presetKey);
                var row = findRow(doc, rowId);
                if (!doc || !row) return;
                var file = fileInput && fileInput.files && fileInput.files[0];
                if (!file) return;

                var v = validateFile(file);
                if (!v.ok) {
                    fileInput.value = '';
                    showFileError(v.msg);
                    return;
                }

                revokePreviewUrl(row);
                row.file = file;
                row.previewUrl = (file.type && file.type.indexOf('image/') === 0) ? URL.createObjectURL(file) : null;

                // پاک‌سازی state input تا انتخاب همان فایل دوباره ممکن باشد
                try { fileInput.value = ''; } catch (e) {}

                renderStep2();
                updateNextBtn();
            }

            function handleRowRemove(presetKey, rowId) {
                var doc = findDoc(presetKey);
                var row = findRow(doc, rowId);
                if (!doc || !row) return;

                // اگر فایل دارد فقط فایل را پاک کن. اگر ندارد و بیش از یک ردیف هست، خود ردیف را حذف کن.
                if (row.file) {
                    revokePreviewUrl(row);
                    row.file = null;
                    row.description = row.description || '';
                } else if (doc.rows.length > 1) {
                    revokePreviewUrl(row);
                    doc.rows = doc.rows.filter(function (r) { return Number(r.id) !== Number(rowId); });
                }
                renderStep2();
                updateNextBtn();
            }

            function handleRowDownload(presetKey, rowId) {
                var doc = findDoc(presetKey);
                var row = findRow(doc, rowId);
                if (!doc || !row || !row.file) return;

                var url = row.previewUrl;
                var createdHere = false;
                if (!url) {
                    url = URL.createObjectURL(row.file);
                    createdHere = true;
                }
                var a = document.createElement('a');
                a.href = url;
                a.download = row.file.name || (doc.title || 'document');
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    try { a.remove(); } catch (e) {}
                    if (createdHere) URL.revokeObjectURL(url);
                }, 80);
            }

            // SweetAlert داخل خود dialog (top layer) تا روی مودال نمایش داده شود نه پشت آن
            function swalInWizard(opts) {
                if (!window.AdminSwal || typeof AdminSwal.fire !== 'function') {
                    var t = (opts && (opts.text || opts.title)) || '';
                    if (t) window.alert(String(t).replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''));
                    return Promise.resolve({});
                }
                var o = Object.assign({}, opts || {});
                if (wizardDialog && wizardDialog.open) {
                    o.target = wizardDialog;
                    o.heightAuto = false;
                }
                return AdminSwal.fire(o);
            }
            function showFileError(msg) {
                swalInWizard({ icon: 'error', title: 'خطا در فایل', text: msg });
            }

            // ============================================================
            // مرحله ۳: تعیین وضعیت + ثبت موفق
            // ============================================================
            function todayJalaliFa() {
                // تبدیل تاریخ امروز به جلالی به‌صورت فارسی YYYY/MM/DD
                try {
                    var fmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-arabext', {
                        year: 'numeric', month: '2-digit', day: '2-digit'
                    }).format(new Date());
                    return fmt;
                } catch (e) {
                    var d = new Date();
                    return faNum(d.getFullYear() + '/' + (d.getMonth() + 1) + '/' + d.getDate());
                }
            }
            function renderStep3() {
                var dEl = document.getElementById('lr-st3-date');
                if (dEl) dEl.textContent = todayJalaliFa();
            }

            // اعتبارسنجی مدارک: هر کارت باید حداقل یک ردیف با فایل داشته باشد
            function step2Valid() {
                if (!LR.plan) return false;
                if (!step2NeedsDocs()) return true; // بدون مدارک: نیازی به آپلود نیست
                if (!Array.isArray(LR.docState) || LR.docState.length === 0) return false;
                return LR.docState.every(function (doc) {
                    return doc.rows.some(function (r) { return !!r.file; });
                });
            }

            function submitLoanRequest() {
                var submitBtn = document.getElementById('lr-submit-btn');
                if (!step2Valid()) {
                    // اگر مدرک گم شده، فهرستش را نشان بده
                    var missing = (LR.docState || [])
                        .filter(function (d) { return !d.rows.some(function (r) { return !!r.file; }); })
                        .map(function (d) { return d.title; });
                    var msg = missing.length
                        ? 'لطفاً برای مدرک‌های زیر فایل آپلود کنید:<br>• ' + missing.map(escapeHtml).join('<br>• ')
                        : 'لطفاً مدارک خواسته‌شده را تکمیل کنید.';
                    swalInWizard({ icon: 'warning', title: 'مدارک ناقص است', html: msg });
                    return;
                }
                if (submitBtn) submitBtn.disabled = true;

                // در فاز فعلی بک‌اند پیاده نشده. وقتی مدل LoanRequest ساخته شد،
                // اینجا fetch POST با FormData (+ CSRF) اضافه می‌شود.
                // فعلاً مستقیماً به مرحلهٔ ۳ می‌رویم تا تجربه کاربری کامل باشد.

                renderStep3();
                showStep(3);

                swalInWizard({
                    icon: 'success',
                    title: 'درخواست شما ثبت شد',
                    text: 'درخواست برای بررسی به کارشناس ارسال شد.',
                    timer: 1800,
                    showConfirmButton: false,
                });
                if (submitBtn) submitBtn.disabled = false;
            }

            // ---------- +/- handlers ----------
            function bumpField(field, dir) {
                if (!LR.plan) return;
                if (field === 'amount') {
                    var ab = amountBounds(LR.plan);
                    var cap = capForCount(LR.plan, LR.count);
                    var maxA = cap ? Math.min(ab.max, cap) : ab.max;
                    var step = ab.step;
                    LR.amount = clamp(LR.amount + dir * step, ab.min, maxA);
                } else if (field === 'count') {
                    var cb = countBoundsAndAllowed(LR.plan);
                    if (cb.allowed) {
                        var idx = cb.allowed.indexOf(LR.count);
                        if (idx === -1) {
                            LR.count = dir > 0 ? cb.allowed[0] : cb.allowed[cb.allowed.length - 1];
                        } else {
                            var newIdx = idx + dir;
                            if (newIdx < 0) newIdx = 0;
                            if (newIdx >= cb.allowed.length) newIdx = cb.allowed.length - 1;
                            LR.count = cb.allowed[newIdx];
                        }
                    } else {
                        LR.count = clamp(LR.count + dir, cb.min, cb.max);
                    }
                } else if (field === 'gap') {
                    var gb = gapBounds(LR.plan);
                    LR.gap = clamp(LR.gap + dir, gb.min, gb.max);
                }
                refreshOutputs();
            }

            // ---------- ثبت رویدادها روی مودال wizard ----------
            if (wizardDialog) {
                // close
                document.querySelectorAll('[data-lr-wiz-close]').forEach(function (b) {
                    b.addEventListener('click', closeWizard);
                });
                // backdrop click
                wizardDialog.addEventListener('click', function (e) {
                    if (e.target === wizardDialog) closeWizard();
                });

                // native close (Esc / form method=dialog)
                wizardDialog.addEventListener('close', function () {
                    disposeDocState();
                });

                // plan select
                var planSelect = document.getElementById('lr-plan-select');
                if (planSelect) {
                    planSelect.addEventListener('change', function () {
                        onPlanChange(planSelect.value);
                    });
                }

                // amount input (typing)
                var amountInput = document.getElementById('lr-amount-input');
                if (amountInput) {
                    amountInput.addEventListener('input', function () {
                        var n = parseInt(unFaNum(amountInput.value).replace(/[^0-9]/g, ''), 10);
                        LR.amount = isNaN(n) ? 0 : n;
                        // نمایش با کاما (بدون از دست دادن مکان نشانگر در پایان)
                        amountInput.value = LR.amount > 0 ? faNum(LR.amount.toLocaleString('en-US')) : '';
                        refreshOutputs();
                    });
                    amountInput.addEventListener('blur', function () { refreshOutputs(); });
                }

                // +/- buttons
                document.querySelectorAll('[data-lr-step][data-lr-dir]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var f = btn.getAttribute('data-lr-step');
                        var d = parseInt(btn.getAttribute('data-lr-dir'), 10) || 0;
                        bumpField(f, d);
                    });
                });

                // description
                var descInput = document.getElementById('lr-desc-input');
                if (descInput) {
                    descInput.addEventListener('input', function () {
                        LR.desc = descInput.value || '';
                        refreshDescUi();
                    });
                    descInput.addEventListener('blur', refreshDescUi);
                }

                // step navigation
                var prevBtn = document.getElementById('lr-step-prev');
                var nextBtn = document.getElementById('lr-step-next');
                if (prevBtn) prevBtn.addEventListener('click', function () {
                    if (LR.step > 1) showStep(LR.step - 1);
                });
                if (nextBtn) nextBtn.addEventListener('click', function () {
                    if (LR.step !== 1) return;
                    if (!step1Valid()) {
                        var err = document.getElementById('lr-step1-error');
                        if (err) {
                            var reason = !descLengthValid()
                                ? 'لطفاً «شرح کالاها و خدمات» را تکمیل کنید.'
                                : 'لطفاً تمام موارد مرحلهٔ یک را به‌درستی پر کنید.';
                            err.textContent = reason;
                            err.hidden = false;
                        }
                        if (!descLengthValid()) {
                            refreshDescUi();
                            var di = document.getElementById('lr-desc-input');
                            if (di) di.focus();
                        }
                        return;
                    }
                    var err2 = document.getElementById('lr-step1-error');
                    if (err2) err2.hidden = true;

                    // اگر طرح عوض شده، state مدارک بازساخته می‌شود؛
                    // اگر همان طرح است، فایل‌های قبلاً آپلودشده حفظ می‌شوند.
                    ensureDocStateForCurrentPlan();
                    renderStep2();
                    showStep(2);
                });

                // submit (ارسال درخواست به کارشناس)
                var submitBtn = document.getElementById('lr-submit-btn');
                if (submitBtn) submitBtn.addEventListener('click', submitLoanRequest);

                // close (در مرحله ۳)
                var closeBtn = document.getElementById('lr-close-btn');
                if (closeBtn) closeBtn.addEventListener('click', closeWizard);
            }

            function openCreateWizard() {
                if (!wizardDialog) {
                    // fallback: اگر مودال موجود نباشد به هر دلیل
                    showComingSoon();
                    return;
                }
                openWizard();
            }
            function showComingSoon() {
                var title = 'به‌زودی فعال می‌شود';
                var text  = 'بخش ثبت درخواست وام جدید در حال آماده‌سازی است و به‌زودی در اختیار شما قرار می‌گیرد.';
                if (window.AdminSwal && typeof AdminSwal.fire === 'function') {
                    AdminSwal.fire({ icon: 'info', title: title, text: text, confirmButtonText: 'باشه' });
                } else if (window.Swal && typeof Swal.fire === 'function') {
                    Swal.fire({ icon: 'info', title: title, text: text });
                } else {
                    window.alert(title + '\n' + text);
                }
            }

            if (openCreate) openCreate.addEventListener('click', openCreateWizard);
            document.querySelectorAll('[data-lr-create-cta]').forEach(function (b) {
                b.addEventListener('click', openCreateWizard);
            });
        })();
    </script>
@endpush
