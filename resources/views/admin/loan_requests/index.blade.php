@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .lrq-page { width: 100%; max-width: 100%; box-sizing: border-box; }
        .lrq-h1 { margin: 0 0 0.45rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .lrq-lead { margin: 0 0 1rem; font-size: 0.82rem; color: var(--muted); line-height: 1.55; }

        .lrq-date-card {
            border: 1px solid var(--border);
            border-radius: 0.9rem;
            background: var(--bg-card);
            padding: 1rem 1.1rem 1.1rem;
            margin-bottom: 1rem;
            max-width: 28rem;
            margin-inline: auto;
            text-align: center;
        }
        .lrq-date-form { display: flex; flex-direction: column; gap: 0.65rem; align-items: stretch; }
        .lrq-date-row { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: flex-end; justify-content: center; }
        .lrq-date-field { flex: 1 1 10rem; min-width: 9rem; text-align: start; }
        .lrq-date-field label { display: block; font-size: 0.76rem; font-weight: 800; color: var(--muted); margin-bottom: 0.25rem; }
        .lrq-date-field input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.62rem;
            padding: 0.5rem 0.62rem; background: var(--bg-card); color: var(--text);
            font-family: inherit; font-size: 0.84rem;
        }
        .lrq-btn-fetch {
            border: none; border-radius: 0.75rem; padding: 0.62rem 1.25rem;
            background: linear-gradient(180deg, #e11d48, #be123c);
            color: #fff; font-size: 0.86rem; font-weight: 800; cursor: pointer; font-family: inherit;
            box-shadow: 0 8px 22px rgba(190, 18, 60, 0.28);
            margin-top: 0.25rem;
        }
        .lrq-btn-fetch:hover { filter: brightness(1.04); }
        .lrq-btn-fetch:active { transform: translateY(1px); }

        .lrq-search-row {
            display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between;
            margin-bottom: 0.65rem;
        }
        .lrq-search-form { flex: 1 1 16rem; min-width: 0; max-width: 28rem; display: flex; gap: 0.4rem; }
        .lrq-search-form input {
            flex: 1; min-width: 0; border: 1px solid var(--border); border-radius: 0.65rem;
            padding: 0.48rem 0.65rem; font-size: 0.82rem; background: var(--bg-card); color: var(--text); font-family: inherit;
        }
        .lrq-search-form button {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.7rem;
            background: var(--bg-card); color: var(--text); cursor: pointer;
        }
        .lrq-select-all {
            display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--text);
            cursor: pointer; user-select: none;
        }
        .lrq-select-all input { width: 1.05rem; height: 1.05rem; accent-color: var(--primary); }

        .lrq-desktop-only { display: block; }
        .lrq-mobile-stack { display: none; flex-direction: column; gap: 0.85rem; }

        .lrq-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); }
        .lrq-tbl { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 60rem; }
        .lrq-th-check { width: 2.6rem; text-align: center !important; }
        .lrq-td-check { text-align: center; vertical-align: middle !important; }
        .lrq-row-check { width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; }
        .lrq-tbl th, .lrq-tbl td { padding: 0.55rem 0.6rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .lrq-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .lrq-tbl td { color: var(--muted); font-weight: 600; }
        .lrq-tbl tr:last-child td { border-bottom: 0; }
        .lrq-tbl tbody tr:hover td { background: rgba(37, 99, 235, 0.04); }

        .lrq-req-cell { display: flex; flex-direction: column; gap: 0.28rem; min-width: 0; max-width: 17rem; }
        .lrq-req-line { font-size: 0.76rem; line-height: 1.45; }
        .lrq-req-line strong { color: var(--text); font-weight: 800; }
        .lrq-cust-cell { display: flex; flex-direction: column; gap: 0.2rem; min-width: 9rem; }
        .lrq-cust-name {
            font-weight: 800; color: var(--primary-dark); text-decoration: none; border: none; background: none;
            cursor: pointer; font-family: inherit; font-size: 0.8rem; padding: 0; text-align: start;
        }
        .lrq-cust-name:hover { text-decoration: underline; }
        .lrq-cust-name:disabled { opacity: 0.55; cursor: not-allowed; text-decoration: none; }
        .lrq-cust-sub { font-size: 0.72rem; color: var(--muted); font-weight: 600; }
        .lrq-muted { color: var(--muted); font-style: italic; }

        .lrq-badge {
            display: inline-flex; align-items: center; gap: 0.28rem; padding: 0.22rem 0.55rem;
            border-radius: 999px; font-size: 0.72rem; font-weight: 800; white-space: nowrap;
        }
        .lrq-badge--pending { background: rgba(217, 119, 6, 0.14); color: #b45309; border: 1px solid rgba(217, 119, 6, 0.28); }
        .lrq-badge--review { background: rgba(124, 58, 237, 0.12); color: #6d28d9; border: 1px solid rgba(124, 58, 237, 0.28); }
        .lrq-badge--approved { background: rgba(16, 185, 129, 0.14); color: #047857; border: 1px solid rgba(16, 185, 129, 0.28); }
        .lrq-badge--rejected { background: rgba(220, 38, 38, 0.12); color: #b91c1c; border: 1px solid rgba(220, 38, 38, 0.28); }
        .lrq-badge--muted { background: rgba(148, 163, 184, 0.16); color: #475569; border: 1px solid rgba(148, 163, 184, 0.35); }

        .lrq-expert { max-width: 16rem; line-height: 1.55; font-size: 0.76rem; word-break: break-word; }

        .lrq-ops { display: inline-flex; gap: 0.35rem; flex-wrap: wrap; }
        .lrq-ico-btn {
            width: 2.1rem; height: 2.1rem; border-radius: 0.55rem; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text); cursor: not-allowed; opacity: 0.5;
            display: inline-flex; align-items: center; justify-content: center; font-size: 0.88rem;
        }
        .lrq-ico-btn:disabled[title] { cursor: help; }
        .lrq-ico-btn--action {
            opacity: 1; cursor: pointer;
            border-color: rgba(124, 58, 237, 0.45);
            background: rgba(124, 58, 237, 0.1);
            color: #6d28d9;
        }
        .lrq-ico-btn--action:hover { filter: brightness(1.05); }
        .lrq-ico-btn--danger.lrq-ico-btn--action {
            border-color: rgba(220, 38, 38, 0.45);
            background: rgba(220, 38, 38, 0.08);
            color: #b91c1c;
        }

        .lrq-modal-overlay {
            position: fixed; inset: 0; z-index: 12000;
            background: rgba(15, 23, 42, 0.45);
            display: flex; align-items: flex-start; justify-content: center;
            padding: 1rem; overflow: auto; box-sizing: border-box;
        }
        .lrq-modal-overlay[hidden] { display: none !important; }
        .lrq-modal-overlay--nested { z-index: 12100; background: rgba(15, 23, 42, 0.55); }
        .lrq-modal-overlay--top { z-index: 12250; background: rgba(15, 23, 42, 0.55); }
        .lrq-edit-modal {
            width: min(1100px, 100%); margin: 0.5rem auto 1.5rem;
            border-radius: 1rem; border: 1px solid var(--border);
            background: var(--bg-card); box-shadow: 0 22px 60px rgba(15, 23, 42, 0.2);
            max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
        }
        .lrq-edit-modal-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(124, 58, 237, 0.08), transparent);
        }
        .lrq-edit-modal-title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--text); }
        .lrq-edit-modal-close {
            border: none; background: rgba(148, 163, 184, 0.2); color: var(--text);
            width: 2.25rem; height: 2.25rem; border-radius: 0.55rem; cursor: pointer; font-size: 1.2rem; line-height: 1;
        }
        .lrq-edit-modal-body { padding: 0.85rem 1rem 1rem; overflow: auto; flex: 1; min-height: 0; }
        .lrq-converted-banner {
            display: flex; align-items: center; justify-content: center; flex-wrap: wrap;
            gap: 0.6rem; padding: 0.85rem 1rem; margin: 0 0 0.95rem;
            border: 2px solid rgba(220, 38, 38, 0.6); border-radius: 0.85rem;
            background: linear-gradient(180deg, rgba(254, 226, 226, 0.95), rgba(254, 202, 202, 0.85));
            color: #b91c1c; font-weight: 900; font-size: 1.05rem; line-height: 1.6; text-align: center;
            box-shadow: 0 6px 18px rgba(220, 38, 38, 0.18);
        }
        [data-theme="dark"] .lrq-converted-banner {
            background: linear-gradient(180deg, rgba(127, 29, 29, 0.35), rgba(127, 29, 29, 0.25));
            color: #fecaca; border-color: rgba(248, 113, 113, 0.7);
        }
        .lrq-converted-banner i { font-size: 1.35rem; }
        .lrq-converted-banner-text { letter-spacing: 0.01em; }
        .lrq-converted-banner-meta { font-size: 0.78rem; font-weight: 700; color: #7f1d1d; opacity: 0.85; }
        [data-theme="dark"] .lrq-converted-banner-meta { color: #fecaca; }
        .lrq-edit-layout {
            display: grid; grid-template-columns: minmax(0, 15.5rem) minmax(0, 1fr); gap: 1rem;
            align-items: start;
        }
        @media (max-width: 860px) {
            .lrq-edit-layout { grid-template-columns: 1fr; }
        }
        .lrq-edit-side {
            border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.85rem 0.75rem;
            background: rgba(248, 250, 252, 0.6);
        }
        [data-theme="dark"] .lrq-edit-side { background: rgba(15, 23, 42, 0.45); }
        .lrq-edit-avatar {
            width: 3rem; height: 3rem; border-radius: 999px; background: rgba(37, 99, 235, 0.15);
            color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;
            margin-inline: auto 0.65rem; flex-shrink: 0;
        }
        .lrq-edit-side-top { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.65rem; }
        .lrq-edit-name { margin: 0; font-size: 0.92rem; font-weight: 800; color: var(--text); line-height: 1.35; }
        .lrq-edit-user { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--muted); font-weight: 600; }
        .lrq-edit-actions { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.5rem; }
        .lrq-edit-pill-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            border-radius: 0.65rem; border: 1px solid rgba(37, 99, 235, 0.45);
            background: rgba(37, 99, 235, 0.1); color: #1d4ed8; font-size: 0.78rem; font-weight: 800;
            padding: 0.45rem 0.55rem; cursor: pointer; font-family: inherit; text-decoration: none;
        }
        .lrq-edit-pill-btn:hover { filter: brightness(1.03); }
        .lrq-edit-pill-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .lrq-edit-sep {
            border: none; border-top: 1px dashed var(--border); margin: 0.75rem 0;
        }
        .lrq-edit-dl { margin: 0; display: grid; gap: 0.45rem 0.5rem; font-size: 0.76rem; }
        .lrq-edit-dl > div { display: grid; grid-template-columns: auto 1fr; gap: 0.35rem; align-items: baseline; }
        .lrq-edit-dl dt { margin: 0; color: var(--muted); font-weight: 700; }
        .lrq-edit-dl dd { margin: 0; color: var(--primary-dark); font-weight: 800; text-align: end; word-break: break-word; }
        .lrq-edit-badge { display: inline-flex; padding: 0.12rem 0.45rem; border-radius: 999px; background: rgba(148, 163, 184, 0.25); font-size: 0.72rem; font-weight: 800; color: #475569; }

        .lrq-edit-main { min-width: 0; }
        .lrq-edit-bar {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; margin-bottom: 0.85rem;
        }
        @media (max-width: 520px) { .lrq-edit-bar { grid-template-columns: 1fr; } }
        .lrq-edit-bar-cell {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.5rem 0.65rem;
            background: rgba(148, 163, 184, 0.08); font-size: 0.78rem; font-weight: 700; color: var(--muted);
        }
        .lrq-edit-bar-cell span { display: block; color: var(--text); font-weight: 800; margin-top: 0.2rem; font-size: 0.82rem; }
        .lrq-field { margin-bottom: 0.65rem; }
        .lrq-field label, .lrq-field .lrq-field-label {
            display: block; font-size: 0.74rem; font-weight: 800; color: var(--muted); margin-bottom: 0.28rem;
        }
        .lrq-field select, .lrq-field input[type="text"], .lrq-field input[type="number"] {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.6rem;
            padding: 0.48rem 0.55rem; font-size: 0.82rem; background: var(--bg-card); color: var(--text); font-family: inherit;
        }
        .lrq-field-row-4 {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem;
        }
        @media (max-width: 720px) { .lrq-field-row-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 400px) { .lrq-field-row-4 { grid-template-columns: 1fr; } }
        .lrq-status-row {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.5rem;
        }
        .lrq-status-row .lrq-field { flex: 1 1 12rem; margin-bottom: 0; min-width: 0; }
        .lrq-btn-ghost {
            border: 1px solid rgba(124, 58, 237, 0.5); background: rgba(124, 58, 237, 0.08);
            color: #6d28d9; font-weight: 800; font-size: 0.78rem; padding: 0.5rem 0.75rem; border-radius: 0.65rem;
            cursor: pointer; font-family: inherit; white-space: nowrap;
        }
        .lrq-btn-ghost:hover { filter: brightness(1.04); }
        .lrq-field textarea {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.6rem;
            padding: 0.48rem 0.55rem; font-size: 0.82rem; background: var(--bg-card); color: var(--text);
            font-family: inherit; min-height: 4.5rem; resize: vertical;
        }
        .lrq-desc-readonly {
            border: 1px dashed var(--border); border-radius: 0.65rem; padding: 0.55rem 0.65rem;
            font-size: 0.8rem; line-height: 1.55; color: var(--text); background: rgba(148, 163, 184, 0.08);
            white-space: pre-wrap; word-break: break-word; min-height: 3rem; max-height: 10rem; overflow: auto;
        }
        .lrq-check-row {
            display: flex; flex-direction: column; gap: 0.45rem; margin: 0.5rem 0 0.25rem;
            font-size: 0.78rem; font-weight: 700; color: var(--text);
        }
        .lrq-check-row label { display: inline-flex; align-items: flex-start; gap: 0.4rem; cursor: pointer; }
        .lrq-check-row input { margin-top: 0.12rem; flex-shrink: 0; width: 1.05rem; height: 1.05rem; accent-color: var(--primary); }
        .lrq-edit-foot {
            display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between;
            margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid var(--border);
        }
        .lrq-edit-foot-start { display: inline-flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
        .lrq-edit-foot-end { display: inline-flex; flex-wrap: wrap; gap: 0.45rem; justify-content: flex-end; margin-inline-start: auto; }
        .lrq-btn-ico {
            width: 2.35rem; height: 2.35rem; border-radius: 0.65rem; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text); cursor: pointer; font-size: 0.95rem;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .lrq-btn-ico:hover { filter: brightness(1.04); border-color: rgba(124, 58, 237, 0.45); color: #6d28d9; }
        .lrq-btn-primary {
            border: none; border-radius: 0.65rem; padding: 0.55rem 1rem;
            background: linear-gradient(180deg, #7c3aed, #6d28d9); color: #fff; font-weight: 800; font-size: 0.82rem;
            cursor: pointer; opacity: 1; font-family: inherit;
        }
        .lrq-btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
        .lrq-btn-outline {
            border: 1px solid rgba(124, 58, 237, 0.55); border-radius: 0.65rem; padding: 0.55rem 1rem;
            background: transparent; color: #6d28d9; font-weight: 800; font-size: 0.82rem;
            cursor: pointer; opacity: 1; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .lrq-btn-outline:hover:not(:disabled) {
            background: rgba(124, 58, 237, 0.08);
            border-color: rgba(124, 58, 237, 0.75);
        }
        .lrq-btn-outline:focus-visible {
            outline: 2px solid rgba(124, 58, 237, 0.45);
            outline-offset: 1px;
        }
        .lrq-btn-outline:disabled { opacity: 0.55; cursor: not-allowed; }

        .lrq-docs-admin-section {
            margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);
        }
        .lrq-docs-admin-h { margin: 0 0 0.65rem; font-size: 0.88rem; font-weight: 800; color: var(--text); }
        /*
         * محفظهٔ کارت‌های مدارک: عمداً اسکرول داخلی ندارد. اسکرول کل صفحه فقط در سطح
         * `.lrq-edit-modal-body` انجام می‌شود تا کاربر همیشه کل کارت (تصویر، تغییر وضعیت،
         * نظر کارشناس، دکمه‌ها) را به‌صورت یکپارچه ببیند و دچار اسکرول داخل اسکرول نشود.
         */
        .lrq-edit-docs-host {
            display: flex; flex-direction: column; gap: 0.95rem;
            padding: 0.15rem 0.25rem;
        }
        .lrq-doc-admin-card {
            border: 1px solid var(--border); border-radius: 0.85rem; overflow: hidden;
            background: var(--bg-card); box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            display: flex; flex-direction: column;
        }
        .lrq-doc-admin-card-head {
            padding: 0.5rem 0.75rem; font-size: 0.8rem; font-weight: 800;
            background: rgba(148, 163, 184, 0.18); color: var(--text); border-bottom: 1px solid var(--border);
        }
        /*
         * ارتفاع کارت کاملاً «هوشمند» است: هیچ سقفی روی body یا ستون‌ها قرار نگرفته،
         * `align-items: start` باعث می‌شود ستون‌ها در ردیف اول از بالا تراز شوند، و چون
         * هر بخش بعدی (وضعیت، نظر، دکمه‌ها) با `grid-column: 1 / -1` تمام عرض را اشغال
         * می‌کند، هیچ نقطه‌ای از کارت زیر بخش دیگر مخفی نمی‌شود.
         */
        .lrq-doc-admin-body {
            padding: 0.85rem 0.85rem 0.95rem; display: grid; gap: 0.85rem;
            grid-template-columns: 1fr; align-items: start;
        }
        @media (min-width: 720px) {
            .lrq-doc-admin-body { grid-template-columns: minmax(0, 1fr) minmax(11rem, 14rem); }
        }
        .lrq-doc-admin-cust { font-size: 0.78rem; min-width: 0; }
        .lrq-doc-admin-cust strong { display: block; margin-bottom: 0.3rem; font-size: 0.72rem; color: var(--muted); }
        .lrq-doc-admin-cust p { margin: 0; line-height: 1.6; white-space: pre-wrap; word-break: break-word; color: var(--text); }
        .lrq-doc-admin-preview {
            display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
            gap: 0.4rem; min-width: 0;
        }
        .lrq-doc-admin-preview img {
            display: block; max-width: 100%; max-height: 11rem; width: auto; height: auto;
            border-radius: 0.55rem; border: 1px solid var(--border); object-fit: contain; background: rgba(148, 163, 184, 0.08);
        }
        .lrq-doc-admin-dl {
            display: inline-flex; align-items: center; gap: 0.25rem; margin-top: 0.35rem; font-size: 0.74rem; font-weight: 700; color: #15803d; text-decoration: none;
        }
        .lrq-doc-admin-dl:hover { text-decoration: underline; }
        .lrq-doc-status-wrap { grid-column: 1 / -1; }
        .lrq-doc-status-label { font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.35rem; display: block; }
        .lrq-doc-status-seg {
            display: flex; flex-wrap: wrap; gap: 0.25rem;
        }
        .lrq-doc-status-btn {
            flex: 1 1 auto; min-width: 0; border: 1px solid var(--border); border-radius: 0.5rem;
            padding: 0.35rem 0.4rem; font-size: 0.68rem; font-weight: 700; font-family: inherit; cursor: pointer;
            background: var(--bg-card); color: var(--text); line-height: 1.25;
        }
        .lrq-doc-status-btn.is-active {
            background: rgba(124, 58, 237, 0.14); border-color: rgba(124, 58, 237, 0.45); color: #6d28d9;
        }
        .lrq-doc-expert-wrap { grid-column: 1 / -1; }
        .lrq-doc-expert-wrap textarea {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.55rem;
            padding: 0.45rem 0.5rem; font-size: 0.78rem; font-family: inherit; min-height: 3.2rem; resize: vertical; background: var(--bg-card); color: var(--text);
        }
        .lrq-doc-admin-actions {
            grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: flex-end;
            margin-top: 0.25rem; padding-top: 0.45rem; border-top: 1px dashed rgba(148, 163, 184, 0.35);
        }
        .lrq-doc-admin-save {
            border: none; border-radius: 0.55rem; padding: 0.42rem 0.85rem;
            background: linear-gradient(180deg, #7c3aed, #6d28d9); color: #fff; font-weight: 800; font-size: 0.76rem; cursor: pointer; font-family: inherit;
        }
        .lrq-doc-admin-save:disabled { opacity: 0.55; cursor: not-allowed; }
        .lrq-doc-admin-del {
            border: none; border-radius: 0.55rem; padding: 0.42rem 0.55rem; background: rgba(220, 38, 38, 0.12); color: #b91c1c; cursor: pointer; font-size: 0.85rem;
        }
        .lrq-doc-admin-del:hover { background: rgba(220, 38, 38, 0.2); }
        .lrq-doc-admin-del:disabled { opacity: 0.45; cursor: not-allowed; }
        .lrq-doc-admin-chip { font-size: 0.72rem; font-weight: 600; color: var(--muted); }

        .lrq-log-modal {
            width: min(960px, 100%); margin: 0.5rem auto 1.5rem; border-radius: 1rem; border: 1px solid var(--border);
            background: var(--bg-card); max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.22);
        }
        .lrq-log-modal-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.65rem;
            padding: 0.65rem 0.85rem; border-bottom: 1px solid var(--border);
        }
        .lrq-log-modal-head h2 { margin: 0; font-size: 0.92rem; font-weight: 800; }
        .lrq-log-toolbar { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; padding: 0.55rem 0.85rem; border-bottom: 1px solid var(--border); }
        .lrq-log-toolbar input {
            flex: 1 1 10rem; min-width: 0; border: 1px solid var(--border); border-radius: 0.55rem;
            padding: 0.42rem 0.55rem; font-size: 0.8rem; background: var(--bg-card); color: var(--text); font-family: inherit;
        }
        .lrq-log-body { padding: 0.55rem 0.75rem 0.85rem; overflow: auto; flex: 1; min-height: 0; }
        .lrq-log-tbl { width: 100%; border-collapse: collapse; font-size: 0.76rem; }
        .lrq-log-tbl th, .lrq-log-tbl td { padding: 0.45rem 0.4rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .lrq-log-tbl th { background: var(--primary-soft); font-weight: 800; white-space: nowrap; }
        .lrq-log-msg { max-width: 14rem; word-break: break-word; line-height: 1.45; }
        .lrq-log-mini { font-size: 0.72rem; font-weight: 700; border: none; border-radius: 0.5rem; padding: 0.28rem 0.45rem; cursor: pointer; font-family: inherit; background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }
        .lrq-log-mini:hover { filter: brightness(1.05); }

        .lrq-convert-modal {
            width: min(620px, 100%); margin: 0.5rem auto 1.5rem; border-radius: 1rem; border: 1px solid var(--border);
            background: var(--bg-card); max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.22);
        }
        .lrq-convert-head { display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.7rem 0.95rem; border-bottom: 1px solid var(--border); }
        .lrq-convert-head h2 { margin: 0; font-size: 0.95rem; font-weight: 800; }
        .lrq-convert-body { padding: 0.85rem 0.95rem 0.65rem; overflow: auto; flex: 1; min-height: 0; }
        .lrq-convert-hint { margin: 0 0 0.65rem; font-size: 0.78rem; color: var(--muted); line-height: 1.6; }
        .lrq-convert-summary {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.45rem 0.65rem;
            padding: 0.55rem 0.65rem; margin: 0 0 0.85rem; border: 1px dashed var(--border); border-radius: 0.7rem;
            background: rgba(99, 102, 241, 0.05); font-size: 0.78rem;
        }
        .lrq-convert-summary .lrq-convert-row { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
        .lrq-convert-summary .lrq-convert-row .k { color: var(--muted); font-size: 0.7rem; }
        .lrq-convert-summary .lrq-convert-row .v { font-weight: 800; word-break: break-word; }
        .lrq-convert-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
        @media (max-width: 600px) { .lrq-convert-grid { grid-template-columns: 1fr; } }
        .lrq-convert-grid .lrq-field input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.5rem 0.6rem; font-size: 0.85rem;
            font-family: inherit; background: var(--bg-card); color: var(--text); direction: ltr; text-align: center; letter-spacing: 0.04em;
        }
        .lrq-convert-grid .lrq-field input:focus { outline: 2px solid rgba(99, 102, 241, 0.35); outline-offset: 1px; }
        .lrq-convert-foot { display: flex; align-items: center; justify-content: flex-end; gap: 0.55rem; padding: 0.65rem 0.95rem; border-top: 1px solid var(--border); background: rgba(15, 23, 42, 0.03); }
        [data-theme="dark"] .lrq-convert-foot { background: rgba(255, 255, 255, 0.04); }

        .lrq-sdef-modal { width: min(920px, 100%); margin: 0.5rem auto 1.5rem; border-radius: 1rem; border: 1px solid var(--border); background: var(--bg-card); max-height: calc(100vh - 2rem); display: flex; flex-direction: column; box-shadow: 0 22px 60px rgba(15, 23, 42, 0.22); }
        .lrq-sdef-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.65rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); }
        .lrq-sdef-head h2 { margin: 0; font-size: 0.95rem; font-weight: 800; }
        .lrq-sdef-head-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
        .lrq-sdef-body { padding: 0.75rem 1rem 1rem; overflow: auto; flex: 1; min-height: 0; }
        .lrq-sdef-card {
            border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.75rem 0.85rem 0.65rem; margin-bottom: 0.75rem;
            background: #fff; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .lrq-sdef-card--locked { border-color: rgba(148, 163, 184, 0.45); }
        .lrq-sdef-card--editing {
            border-color: rgba(124, 58, 237, 0.45);
            box-shadow: 0 6px 22px rgba(124, 58, 237, 0.12);
        }
        [data-theme="dark"] .lrq-sdef-card { background: var(--bg-card); }
        .lrq-sdef-title-row { margin-bottom: 0.65rem; }
        .lrq-sdef-fields-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;
            margin-bottom: 0.55rem;
        }
        @media (max-width: 640px) { .lrq-sdef-fields-row { grid-template-columns: 1fr; } }
        .lrq-sdef-field {
            border: 1px solid rgba(148, 163, 184, 0.55);
            border-radius: 0.75rem;
            padding: 0.55rem 0.65rem 0.6rem;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(255, 255, 255, 0.98));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }
        [data-theme="dark"] .lrq-sdef-field {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.55), rgba(15, 23, 42, 0.35));
            border-color: rgba(71, 85, 105, 0.65);
            box-shadow: none;
        }
        .lrq-sdef-lbl {
            display: block; font-size: 0.72rem; font-weight: 800; color: var(--muted);
            margin-bottom: 0.38rem; letter-spacing: 0.01em;
        }
        .lrq-sdef-input, .lrq-sdef-select {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border);
            border-radius: 0.6rem; padding: 0.48rem 0.58rem; font-size: 0.82rem;
            background: var(--bg-card); color: var(--text); font-family: inherit;
        }
        .lrq-sdef-input:focus, .lrq-sdef-select:focus {
            outline: none; border-color: rgba(124, 58, 237, 0.55);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }
        .lrq-sdef-input:disabled, .lrq-sdef-select:disabled {
            opacity: 0.88; cursor: not-allowed; background: rgba(241, 245, 249, 0.9); color: var(--text);
        }
        [data-theme="dark"] .lrq-sdef-input:disabled, [data-theme="dark"] .lrq-sdef-select:disabled {
            background: rgba(15, 23, 42, 0.65);
        }
        .lrq-sdef-checks { display: flex; flex-wrap: wrap; gap: 0.65rem; font-size: 0.74rem; font-weight: 700; color: var(--text); margin-top: 0.45rem; }
        .lrq-sdef-checks label { display: inline-flex; align-items: center; gap: 0.3rem; cursor: pointer; }
        .lrq-sdef-actions {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.45rem;
            margin-top: 0.65rem; padding-top: 0.65rem; border-top: 1px dashed rgba(148, 163, 184, 0.5);
        }
        .lrq-sdef-btn {
            min-width: 2.35rem; height: 2.35rem; border-radius: 0.65rem; border: none; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; font-size: 0.92rem; color: #fff;
            transition: filter 0.12s ease, transform 0.08s ease;
        }
        .lrq-sdef-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .lrq-sdef-btn--edit { background: linear-gradient(180deg, #7c3aed, #6d28d9); }
        .lrq-sdef-btn--save { background: linear-gradient(180deg, #059669, #047857); }
        .lrq-sdef-btn--del { background: linear-gradient(180deg, #ef4444, #dc2626); }
        .lrq-sdef-btn:hover:not(:disabled) { filter: brightness(1.05); }
        .lrq-sdef-btn:active:not(:disabled) { transform: translateY(1px); }
        .lrq-sdef-add {
            width: 100%; margin-top: 0.35rem; border: 1px dashed rgba(124, 58, 237, 0.45); border-radius: 0.85rem;
            padding: 0.65rem; background: rgba(124, 58, 237, 0.06); color: #6d28d9; font-weight: 800; font-size: 0.84rem;
            cursor: pointer; font-family: inherit;
        }
        .lrq-sdef-add:hover { filter: brightness(1.03); }
        .lrq-sdef-muted { font-size: 0.72rem; color: var(--muted); margin-top: 0.35rem; }

        .lrq-empty { text-align: center; padding: 1.5rem 1rem; color: var(--muted); font-size: 0.86rem; font-weight: 600; }

        .lrq-card {
            border: 1px solid var(--border);
            border-radius: 1rem;
            background: var(--bg-card);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .lrq-card-hd {
            display: flex; align-items: flex-start; gap: 0.55rem;
            padding: 0.75rem 0.85rem;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.06), transparent);
            border-bottom: 1px solid var(--border);
        }
        .lrq-card-hd-check { flex-shrink: 0; padding-top: 0.12rem; }
        .lrq-card-hd-main { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem 0.65rem; justify-content: space-between; }
        .lrq-card-reqno { font-size: 0.82rem; font-weight: 800; color: var(--text); }
        .lrq-card-body { padding: 0.75rem 0.85rem 0.65rem; display: flex; flex-direction: column; gap: 0.65rem; }
        .lrq-card-section-title {
            font-size: 0.68rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em;
            margin: 0 0 0.35rem;
        }
        .lrq-card-kv { display: grid; grid-template-columns: 1fr; gap: 0.45rem 0.65rem; }
        @media (min-width: 380px) {
            .lrq-card-kv { grid-template-columns: auto 1fr; }
        }
        .lrq-card-k { font-size: 0.72rem; font-weight: 700; color: var(--muted); }
        .lrq-card-v { font-size: 0.8rem; font-weight: 700; color: var(--text); line-height: 1.45; word-break: break-word; }
        .lrq-card-block { padding: 0.55rem 0.65rem; border-radius: 0.65rem; background: rgba(148, 163, 184, 0.08); border: 1px solid rgba(148, 163, 184, 0.2); }
        .lrq-card-expert .lrq-expert { max-width: none; font-size: 0.78rem; }
        .lrq-card-ft {
            display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem;
            padding: 0.65rem 0.85rem; border-top: 1px solid var(--border); background: rgba(248, 250, 252, 0.5);
        }
        [data-theme="dark"] .lrq-card-ft { background: rgba(15, 23, 42, 0.35); }
        .lrq-card-empty {
            border: 1px dashed var(--border); border-radius: 1rem; padding: 1.35rem 1rem;
            text-align: center; color: var(--muted); font-size: 0.86rem; font-weight: 600; background: var(--bg-card);
        }

        @media (max-width: 900px) {
            .lrq-desktop-only { display: none !important; }
            .lrq-mobile-stack { display: flex; }
        }

        .lrq-pagination { padding: 0.75rem 0.5rem; }

        /* === Toolbar: status filter + tools (export / print) === */
        .lrq-toolbar {
            display: flex; flex-wrap: wrap; align-items: stretch; gap: 0.6rem;
            margin: 0.4rem 0 0.75rem;
        }
        .lrq-toolbar__group {
            display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
        }
        .lrq-toolbar__spacer { flex: 1; min-width: 0; }

        details.lrq-status-filter {
            position: relative;
        }
        details.lrq-status-filter > summary {
            list-style: none; cursor: pointer; user-select: none;
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.55rem 0.85rem;
            border: 1px solid rgba(37, 99, 235, 0.35);
            background: var(--bg-card); color: var(--text);
            border-radius: 0.7rem; font-weight: 700; font-size: 0.82rem;
            font-family: inherit;
        }
        details.lrq-status-filter > summary::-webkit-details-marker { display: none; }
        details.lrq-status-filter > summary:hover { border-color: var(--primary); }
        details.lrq-status-filter[open] > summary { border-color: var(--primary); background: var(--primary-soft); color: var(--primary-dark); }
        html[data-theme="dark"] details.lrq-status-filter[open] > summary { background: rgba(30, 58, 138, 0.22); }

        .lrq-status-filter__count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 1.35rem; height: 1.35rem; padding: 0 0.35rem;
            border-radius: 999px; background: var(--primary); color: #fff;
            font-size: 0.7rem; font-weight: 900;
        }

        .lrq-status-panel {
            position: absolute; z-index: 30; top: calc(100% + 0.35rem); inset-inline-start: 0;
            min-width: 18rem; max-width: 24rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 0.85rem; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.16);
            padding: 0.7rem 0.75rem; display: flex; flex-direction: column; gap: 0.55rem;
        }
        html[data-theme="dark"] .lrq-status-panel { box-shadow: 0 14px 30px rgba(0,0,0,0.45); }

        .lrq-status-panel__hint {
            font-size: 0.74rem; color: var(--muted); font-weight: 700; margin: 0 0 0.15rem;
        }
        .lrq-status-panel__list {
            display: flex; flex-direction: column; gap: 0.25rem;
            max-height: 14rem; overflow-y: auto; padding: 0.1rem 0.15rem;
        }
        .lrq-status-panel__item {
            display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.45rem;
            border-radius: 0.5rem; font-size: 0.8rem; font-weight: 700; color: var(--text); cursor: pointer;
        }
        .lrq-status-panel__item:hover { background: rgba(37, 99, 235, 0.06); }
        html[data-theme="dark"] .lrq-status-panel__item:hover { background: rgba(30, 58, 138, 0.22); }
        .lrq-status-panel__item input[type="checkbox"] { width: 1rem; height: 1rem; accent-color: var(--primary); }
        .lrq-status-panel__actions {
            display: flex; gap: 0.4rem; justify-content: space-between; padding-top: 0.25rem;
            border-top: 1px dashed var(--border);
        }
        .lrq-status-panel__btn {
            font-family: inherit; font-size: 0.78rem; font-weight: 800; padding: 0.4rem 0.65rem;
            border-radius: 0.55rem; cursor: pointer; border: 1px solid transparent;
        }
        .lrq-status-panel__btn--primary {
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: #fff; box-shadow: 0 6px 14px rgba(37, 99, 235, 0.22);
        }
        .lrq-status-panel__btn--primary:hover { filter: brightness(1.05); }
        .lrq-status-panel__btn--ghost {
            background: transparent; color: var(--primary-dark);
            border-color: rgba(37, 99, 235, 0.4);
        }
        .lrq-status-panel__btn--ghost:hover { background: rgba(37, 99, 235, 0.06); }

        .lrq-tool-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.55rem 0.85rem; border-radius: 0.7rem;
            font-family: inherit; font-weight: 800; font-size: 0.82rem;
            cursor: pointer; text-decoration: none; border: 1px solid transparent;
            transition: filter 0.12s ease, border-color 0.12s ease, background 0.12s ease;
        }
        .lrq-tool-btn--excel {
            background: linear-gradient(180deg, #16a34a, #15803d);
            color: #fff; box-shadow: 0 6px 14px rgba(22, 163, 74, 0.22);
        }
        .lrq-tool-btn--excel:hover { filter: brightness(1.05); }
        .lrq-tool-btn--print {
            background: var(--bg-card); color: var(--primary-dark);
            border-color: rgba(37, 99, 235, 0.4);
        }
        .lrq-tool-btn--print:hover { background: var(--primary-soft); border-color: var(--primary); }
        html[data-theme="dark"] .lrq-tool-btn--print:hover { background: rgba(30, 58, 138, 0.22); }

        .lrq-active-status-chips {
            display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;
            margin-top: 0.4rem;
        }
        .lrq-active-status-chip {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.2rem 0.55rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 800;
            background: var(--primary-soft); color: var(--primary-dark);
            border: 1px solid rgba(37, 99, 235, 0.35);
        }
        html[data-theme="dark"] .lrq-active-status-chip {
            background: rgba(30, 58, 138, 0.28); color: #c7d2fe; border-color: rgba(99, 102, 241, 0.5);
        }
        .lrq-active-status-chip__x {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1rem; height: 1rem; border-radius: 999px;
            background: rgba(37, 99, 235, 0.18); color: var(--primary-dark);
            font-size: 0.6rem; text-decoration: none;
        }
        .lrq-active-status-chip__x:hover { background: rgba(37, 99, 235, 0.32); }
        .lrq-active-status-clear {
            font-size: 0.74rem; font-weight: 800; color: #b91c1c; text-decoration: none;
            border: 1px dashed rgba(220, 38, 38, 0.5); padding: 0.2rem 0.55rem; border-radius: 999px;
        }
        .lrq-active-status-clear:hover { background: rgba(254, 226, 226, 0.6); }
    </style>
@endpush

@section('content')
    <div class="lrq-page">
        <h1 class="lrq-h1">{{ $pageTitle }}</h1>
        <p class="lrq-lead">
            بازهٔ تاریخ ثبت درخواست را انتخاب کنید و روی «دریافت اطلاعات» بزنید. سپس در جدول زیر می‌توانید جستجو کنید و برای مشاهدهٔ پروندهٔ وام مشتری، روی نام او کلیک کنید.
        </p>

        @php
            $selectedStatuses = $selectedStatuses ?? [];
            $statusOptions = $statusOptions ?? [];
            $statusTitleMap = collect($statusOptions)->pluck('title', 'code')->all();
            $exportQuery = array_filter([
                'from_jdate' => $fromJDate,
                'to_jdate' => $toJDate,
                'q' => $search,
                'status' => $selectedStatuses,
            ], static function ($v): bool {
                return $v !== '' && $v !== null && $v !== [];
            });
        @endphp

        <div class="lrq-date-card">
            <form method="get" action="{{ route('admin.loan-requests.index') }}" class="lrq-date-form" id="lrq-date-form">
                @if ($search !== '')
                    <input type="hidden" name="q" value="{{ e($search) }}">
                @endif
                @foreach ($selectedStatuses as $sc)
                    <input type="hidden" name="status[]" value="{{ e($sc) }}">
                @endforeach
                <div class="lrq-date-row">
                    <div class="lrq-date-field">
                        <label for="lrq-from-jdate">از تاریخ</label>
                        <input type="text" name="from_jdate" id="lrq-from-jdate" value="{{ e($fromJDate) }}" autocomplete="off" required>
                    </div>
                    <div class="lrq-date-field">
                        <label for="lrq-to-jdate">تا تاریخ</label>
                        <input type="text" name="to_jdate" id="lrq-to-jdate" value="{{ e($toJDate) }}" autocomplete="off" required>
                    </div>
                </div>
                <button type="submit" class="lrq-btn-fetch">دریافت اطلاعات</button>
            </form>
        </div>

        <div class="lrq-search-row">
            <form method="get" class="lrq-search-form" action="{{ route('admin.loan-requests.index') }}">
                <input type="hidden" name="from_jdate" value="{{ e($fromJDate) }}">
                <input type="hidden" name="to_jdate" value="{{ e($toJDate) }}">
                @foreach ($selectedStatuses as $sc)
                    <input type="hidden" name="status[]" value="{{ e($sc) }}">
                @endforeach
                <input type="search" name="q" value="{{ e($search) }}" placeholder="اطلاعات مورد نظر خود جهت جستجو وارد کنید" maxlength="200" autocomplete="off">
                <button type="submit" aria-label="جستجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
            </form>
            <label class="lrq-select-all">
                <input type="checkbox" id="lrq-select-all" aria-label="انتخاب همه">
                انتخاب همه
            </label>
        </div>

        <div class="lrq-toolbar" role="toolbar" aria-label="ابزار فیلتر و خروجی">
            <div class="lrq-toolbar__group">
                <details class="lrq-status-filter" id="lrq-status-filter">
                    <summary aria-haspopup="listbox" aria-expanded="false">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        فیلتر وضعیت‌ها
                        @if (count($selectedStatuses) > 0)
                            <span class="lrq-status-filter__count" aria-label="تعداد انتخاب‌شده">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($selectedStatuses)) }}</span>
                        @endif
                    </summary>
                    <div class="lrq-status-panel" role="dialog" aria-label="انتخاب وضعیت‌ها">
                        <p class="lrq-status-panel__hint">وضعیت‌هایی که می‌خواهید نمایش داده شوند را انتخاب و «اعمال فیلتر» را بزنید. در صورت خالی بودن، همه وضعیت‌ها نمایش داده می‌شوند.</p>
                        <form method="get" action="{{ route('admin.loan-requests.index') }}" id="lrq-status-filter-form">
                            <input type="hidden" name="from_jdate" value="{{ e($fromJDate) }}">
                            <input type="hidden" name="to_jdate" value="{{ e($toJDate) }}">
                            @if ($search !== '')
                                <input type="hidden" name="q" value="{{ e($search) }}">
                            @endif
                            <div class="lrq-status-panel__list">
                                @forelse ($statusOptions as $opt)
                                    <label class="lrq-status-panel__item">
                                        <input type="checkbox" name="status[]" value="{{ e($opt['code']) }}"
                                            @checked(in_array($opt['code'], $selectedStatuses, true))>
                                        <span>{{ $opt['title'] }}</span>
                                    </label>
                                @empty
                                    <p class="lrq-status-panel__hint" style="text-align:center">وضعیتی تعریف نشده است.</p>
                                @endforelse
                            </div>
                            <div class="lrq-status-panel__actions">
                                <button type="submit" class="lrq-status-panel__btn lrq-status-panel__btn--primary">اعمال فیلتر</button>
                                <a href="{{ route('admin.loan-requests.index', array_filter(['from_jdate' => $fromJDate, 'to_jdate' => $toJDate, 'q' => $search], static fn ($v) => $v !== '' && $v !== null)) }}"
                                    class="lrq-status-panel__btn lrq-status-panel__btn--ghost">پاک‌سازی</a>
                            </div>
                        </form>
                    </div>
                </details>
            </div>

            <div class="lrq-toolbar__spacer"></div>

            <div class="lrq-toolbar__group">
                <a class="lrq-tool-btn lrq-tool-btn--excel" href="{{ route('admin.loan-requests.export', $exportQuery) }}"
                    title="دریافت خروجی اکسل از همین فهرست فیلتر شده">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
                <a class="lrq-tool-btn lrq-tool-btn--print" href="{{ route('admin.loan-requests.print', $exportQuery) }}"
                    target="_blank" rel="noopener" title="چاپ A4 از همین فهرست فیلتر شده">
                    <i class="fa-solid fa-print" aria-hidden="true"></i>
                    چاپ
                </a>
            </div>
        </div>

        @if (count($selectedStatuses) > 0)
            <div class="lrq-active-status-chips" aria-label="وضعیت‌های فعال در فیلتر">
                @foreach ($selectedStatuses as $sc)
                    @php
                        $remaining = array_values(array_filter($selectedStatuses, static fn ($x) => $x !== $sc));
                        $removeUrl = route('admin.loan-requests.index', array_filter([
                            'from_jdate' => $fromJDate,
                            'to_jdate' => $toJDate,
                            'q' => $search,
                            'status' => $remaining,
                        ], static fn ($v) => $v !== '' && $v !== null && $v !== []));
                    @endphp
                    <span class="lrq-active-status-chip">
                        {{ $statusTitleMap[$sc] ?? $sc }}
                        <a class="lrq-active-status-chip__x" href="{{ $removeUrl }}" title="حذف از فیلتر" aria-label="حذف وضعیت از فیلتر">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </a>
                    </span>
                @endforeach
                <a class="lrq-active-status-clear" href="{{ route('admin.loan-requests.index', array_filter(['from_jdate' => $fromJDate, 'to_jdate' => $toJDate, 'q' => $search], static fn ($v) => $v !== '' && $v !== null)) }}">
                    حذف همه فیلترهای وضعیت
                </a>
            </div>
        @endif

        <div class="lrq-wrap lrq-desktop-only" role="region" aria-label="جدول درخواست‌های وام">
            <table class="lrq-tbl">
                <thead>
                    <tr>
                        <th scope="col" class="lrq-th-check"><span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">انتخاب</span></th>
                        <th scope="col">اطلاعات درخواست</th>
                        <th scope="col">نام مشتری</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">نظر کارشناس</th>
                        <th scope="col">عملیات‌ها</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loanRequests as $row)
                        <tr>
                            <td class="lrq-td-check">
                                <input type="checkbox" class="lrq-row-check" data-lrq-row-check data-lrq-id="{{ $row['id'] }}" aria-label="انتخاب ردیف">
                            </td>
                            <td>
                                <div class="lrq-req-cell">
                                    <div class="lrq-req-line"><strong>شماره:</strong> {{ $row['request_no_fa'] }}</div>
                                    <div class="lrq-req-line"><strong>مبلغ:</strong> {{ $row['amount_fa'] }} تومان</div>
                                    <div class="lrq-req-line"><strong>تاریخ و ساعت:</strong> {{ $row['datetime_fa'] }}</div>
                                    <div class="lrq-req-line"><strong>وام:</strong> {{ $row['loan_title'] }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="lrq-cust-cell">
                                    @if ($row['customer_id'] > 0)
                                        @php
                                            $custUrl = route('admin.customers.index', [
                                                'open_loan_manage' => '1',
                                                'customer_id' => $row['customer_id'],
                                            ]);
                                        @endphp
                                        <a href="{{ $custUrl }}" class="lrq-cust-name">{{ $row['customer_name'] }}</a>
                                    @else
                                        <span class="lrq-cust-name" style="cursor:default;color:var(--muted)">{{ $row['customer_name'] }}</span>
                                    @endif
                                    <span class="lrq-cust-sub">کد ملی: {{ $row['national_id_fa'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $row['status_badge_class'] }}">{{ $row['status_label'] }}</span>
                            </td>
                            <td>
                                <div class="lrq-expert">{!! $row['expert_note_html'] !!}</div>
                            </td>
                            <td>
                                <div class="lrq-ops">
                                    <button type="button" class="lrq-ico-btn lrq-ico-btn--action" data-lrq-open-edit="{{ $row['id'] }}" title="ویرایش درخواست" aria-label="ویرایش درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                    <button type="button" class="lrq-ico-btn lrq-ico-btn--action lrq-ico-btn--danger" data-lrq-delete="{{ $row['id'] }}" data-lrq-delete-no="{{ $row['request_no_fa'] }}" title="حذف درخواست" aria-label="حذف درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="lrq-empty">در این بازه تاریخ، درخواست وامی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lrq-mobile-stack" role="region" aria-label="کارت‌های درخواست وام (موبایل)">
            @forelse ($loanRequests as $row)
                <article class="lrq-card">
                    <div class="lrq-card-hd">
                        <div class="lrq-card-hd-check">
                            <input type="checkbox" class="lrq-row-check" data-lrq-row-check data-lrq-id="{{ $row['id'] }}" aria-label="انتخاب درخواست {{ $row['request_no_fa'] }}">
                        </div>
                        <div class="lrq-card-hd-main">
                            <span class="lrq-card-reqno">شماره {{ $row['request_no_fa'] }}</span>
                            <span class="{{ $row['status_badge_class'] }}">{{ $row['status_label'] }}</span>
                        </div>
                    </div>
                    <div class="lrq-card-body">
                        <div>
                            <p class="lrq-card-section-title">جزئیات درخواست</p>
                            <div class="lrq-card-kv">
                                <span class="lrq-card-k">مبلغ</span>
                                <span class="lrq-card-v">{{ $row['amount_fa'] }} تومان</span>
                                <span class="lrq-card-k">تاریخ و ساعت</span>
                                <span class="lrq-card-v">{{ $row['datetime_fa'] }}</span>
                                <span class="lrq-card-k">وام</span>
                                <span class="lrq-card-v">{{ $row['loan_title'] }}</span>
                            </div>
                        </div>
                        <div class="lrq-card-block">
                            <p class="lrq-card-section-title" style="margin-bottom:0.4rem">مشتری</p>
                            @if ($row['customer_id'] > 0)
                                @php
                                    $custUrl = route('admin.customers.index', [
                                        'open_loan_manage' => '1',
                                        'customer_id' => $row['customer_id'],
                                    ]);
                                @endphp
                                <a href="{{ $custUrl }}" class="lrq-cust-name">{{ $row['customer_name'] }}</a>
                            @else
                                <span class="lrq-cust-name" style="cursor:default;color:var(--muted)">{{ $row['customer_name'] }}</span>
                            @endif
                            <span class="lrq-cust-sub" style="display:block;margin-top:0.25rem">کد ملی: {{ $row['national_id_fa'] }}</span>
                        </div>
                        <div class="lrq-card-expert">
                            <p class="lrq-card-section-title">نظر کارشناس</p>
                            <div class="lrq-expert">{!! $row['expert_note_html'] !!}</div>
                        </div>
                    </div>
                    <div class="lrq-card-ft">
                        <div class="lrq-ops">
                            <button type="button" class="lrq-ico-btn lrq-ico-btn--action" data-lrq-open-edit="{{ $row['id'] }}" title="ویرایش درخواست" aria-label="ویرایش"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                            <button type="button" class="lrq-ico-btn lrq-ico-btn--action lrq-ico-btn--danger" data-lrq-delete="{{ $row['id'] }}" data-lrq-delete-no="{{ $row['request_no_fa'] }}" title="حذف درخواست" aria-label="حذف درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="lrq-card-empty" role="status">در این بازه تاریخ، درخواست وامی ثبت نشده است.</div>
            @endforelse
        </div>

        @if ($loanRequests->hasPages())
            <div class="lrq-pagination">{{ $loanRequests->links() }}</div>
        @endif

        <div id="lrq-edit-overlay" class="lrq-modal-overlay" hidden aria-hidden="true">
            <div class="lrq-edit-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-edit-title">
                <div class="lrq-edit-modal-head">
                    <h2 id="lrq-edit-title" class="lrq-edit-modal-title">مشخصات درخواست وام</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-edit-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-edit-modal-body">
                    <div id="lrq-edit-loading" class="lrq-empty" hidden>در حال بارگذاری…</div>
                    <div id="lrq-edit-form-wrap" hidden>
                        <div id="lrq-edit-converted-banner" class="lrq-converted-banner" hidden role="status" aria-live="polite">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span class="lrq-converted-banner-text">این درخواست به وام تخصیص داده شده است</span>
                            <span class="lrq-converted-banner-meta" id="lrq-edit-converted-meta"></span>
                        </div>
                        <div class="lrq-edit-layout">
                            <aside class="lrq-edit-side" aria-label="اطلاعات مشتری">
                                <div class="lrq-edit-side-top">
                                    <div class="lrq-edit-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
                                    <div>
                                        <p class="lrq-edit-name" id="lrq-edit-cust-name">—</p>
                                        <p class="lrq-edit-user">نام کاربری: <span id="lrq-edit-cust-username">—</span></p>
                                    </div>
                                </div>
                                <div class="lrq-edit-actions">
                                    <button type="button" class="lrq-edit-pill-btn" id="lrq-edit-open-customer-form"><i class="fa-solid fa-pen" aria-hidden="true"></i> ویرایش اطلاعات</button>
                                    <a class="lrq-edit-pill-btn" id="lrq-edit-open-loan-manage" href="#"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> پرونده مشتری</a>
                                </div>
                                <hr class="lrq-edit-sep">
                                <dl class="lrq-edit-dl">
                                    <div><dt>کد ملی</dt><dd id="lrq-edit-national">—</dd></div>
                                    <div><dt>موبایل</dt><dd id="lrq-edit-mobile">—</dd></div>
                                    <div><dt>نام پدر</dt><dd id="lrq-edit-father">—</dd></div>
                                    <div><dt>تعداد وام</dt><dd id="lrq-edit-loan-count">—</dd></div>
                                    <div><dt>مجموع وام‌ها</dt><dd id="lrq-edit-loans-total">—</dd></div>
                                    <div><dt>مانده اقساط</dt><dd id="lrq-edit-remain">—</dd></div>
                                    <div><dt>تاریخ و ساعت عضویت</dt><dd id="lrq-edit-membership">—</dd></div>
                                    <div><dt>آخرین ورود</dt><dd id="lrq-edit-last-login">—</dd></div>
                                    <div><dt>اعتبار کیف پول</dt><dd id="lrq-edit-wallet">—</dd></div>
                                    <div><dt>وضعیت خوش‌حسابی</dt><dd><span class="lrq-edit-badge" id="lrq-edit-good">نامشخص</span></dd></div>
                                </dl>
                            </aside>
                            <div class="lrq-edit-main">
                                <div class="lrq-edit-bar">
                                    <div class="lrq-edit-bar-cell">تاریخ درخواست <span id="lrq-edit-req-date">—</span></div>
                                    <div class="lrq-edit-bar-cell">وضعیت جاری درخواست <span id="lrq-edit-req-status-label">—</span></div>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-loan-type">نوع وام <span style="color:#b91c1c">*</span></label>
                                    <select id="lrq-edit-loan-type"></select>
                                </div>
                                <div class="lrq-field-row-4">
                                    <div class="lrq-field">
                                        <label for="lrq-edit-amount">مبلغ (تومان)</label>
                                        <input type="text" id="lrq-edit-amount" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-count">تعداد اقساط</label>
                                        <input type="text" id="lrq-edit-inst-count" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-gap">فاصله بین هر قسط</label>
                                        <input type="text" id="lrq-edit-inst-gap" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-amt">مبلغ هر قسط (تومان)</label>
                                        <input type="text" id="lrq-edit-inst-amt" inputmode="numeric" autocomplete="off" readonly>
                                    </div>
                                </div>
                                <p class="lrq-sdef-muted" id="lrq-edit-gap-unit-hint"></p>
                                <div class="lrq-status-row">
                                    <div class="lrq-field">
                                        <label for="lrq-edit-status">تغییر وضعیت به</label>
                                        <select id="lrq-edit-status"></select>
                                    </div>
                                    <button type="button" class="lrq-btn-ghost" id="lrq-open-status-defs">مدیریت وضعیت‌ها</button>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-expert-admin">نظر کارشناس (جهت ادمین)</label>
                                    <textarea id="lrq-edit-expert-admin" rows="3" placeholder="فقط در پنل ادمین دیده می‌شود"></textarea>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-expert-customer">نظر کارشناس (جهت مشتری)</label>
                                    <textarea id="lrq-edit-expert-customer" rows="3" placeholder="در پنل کاربر نمایش داده می‌شود"></textarea>
                                </div>
                                <div class="lrq-field">
                                    <span class="lrq-field-label">کالاها و خدمات (ثبت‌شده توسط مشتری)</span>
                                    <div id="lrq-edit-description" class="lrq-desc-readonly" aria-readonly="true">—</div>
                                </div>
                                <div class="lrq-check-row">
                                    <label>
                                        <input type="checkbox" id="lrq-edit-doc-received">
                                        <span>مدارک ارسال‌شده به دست شرکت رسیده است</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" id="lrq-edit-send-sms">
                                        <span>ارسال پیامک وضعیت درخواست به مشتری (هنگام تغییر وضعیت و ذخیره)</span>
                                    </label>
                                </div>
                                <div class="lrq-edit-foot">
                                    <div class="lrq-edit-foot-start">
                                        <button type="button" class="lrq-btn-ico" id="lrq-edit-open-status-log" title="گزارش تغییر وضعیت" aria-label="گزارش تغییر وضعیت">
                                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="lrq-btn-ico" id="lrq-edit-open-sms-log" title="لیست پیامک‌های وضعیت" aria-label="لیست پیامک‌های وضعیت">
                                            <i class="fa-solid fa-sms" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="lrq-edit-foot-end">
                                        <button type="button" class="lrq-btn-primary" id="lrq-edit-save">ذخیره تغییرات</button>
                                        <button type="button" class="lrq-btn-outline" id="lrq-edit-convert-loan">تبدیل به وام</button>
                                    </div>
                                </div>
                                <div class="lrq-docs-admin-section" id="lrq-docs-admin-section">
                                    <h3 class="lrq-docs-admin-h">مدارک</h3>
                                    <div id="lrq-edit-docs-host" class="lrq-edit-docs-host"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="lrq-statuslog-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-log-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-statuslog-title">
                <div class="lrq-log-modal-head">
                    <h2 id="lrq-statuslog-title">لاگ تغییر وضعیت درخواست</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-statuslog-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-log-toolbar">
                    <input type="search" id="lrq-statuslog-q" placeholder="جستجو در لاگ…" autocomplete="off">
                    <button type="button" class="lrq-btn-ghost" id="lrq-statuslog-export">خروجی اکسل</button>
                </div>
                <div class="lrq-log-body">
                    <table class="lrq-log-tbl">
                        <thead>
                            <tr>
                                <th>کاربر</th>
                                <th>تاریخ و ساعت</th>
                                <th>از وضعیت (مشتری)</th>
                                <th>به وضعیت (مشتری)</th>
                            </tr>
                        </thead>
                        <tbody id="lrq-statuslog-tbody"></tbody>
                    </table>
                    <div id="lrq-statuslog-empty" class="lrq-empty" hidden>لاگی ثبت نشده است.</div>
                </div>
            </div>
        </div>

        <div id="lrq-smslog-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-log-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-smslog-title">
                <div class="lrq-log-modal-head">
                    <h2 id="lrq-smslog-title">لیست پیامک‌های تغییر وضعیت درخواست</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-smslog-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-log-body">
                    <table class="lrq-log-tbl">
                        <thead>
                            <tr>
                                <th>پنل پیامک</th>
                                <th>وضعیت</th>
                                <th>زمان ارسال</th>
                                <th>متن</th>
                                <th>دریافت‌کننده</th>
                                <th>نوع</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="lrq-smslog-tbody"></tbody>
                    </table>
                    <div id="lrq-smslog-empty" class="lrq-empty" hidden>پیامکی ثبت نشده است.</div>
                </div>
            </div>
        </div>

        <div id="lrq-convert-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-convert-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-convert-title">
                <div class="lrq-convert-head">
                    <h2 id="lrq-convert-title">تبدیل درخواست به وام</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-convert-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-convert-body">
                    <p class="lrq-convert-hint" id="lrq-convert-hint">دو تاریخ زیر را در تقویم شمسی وارد کنید. مبلغ وام، تعداد و فاصلهٔ اقساط و مبلغ هر قسط از مشخصات همین درخواست برداشته می‌شوند.</p>
                    <div class="lrq-convert-summary" id="lrq-convert-summary"></div>
                    <div class="lrq-convert-grid">
                        <div class="lrq-field">
                            <label for="lrq-convert-start-jdate">تاریخ شروع وام</label>
                            <input type="text" id="lrq-convert-start-jdate" autocomplete="off" placeholder="مثال: ۱۴۰۵/۰۲/۱۵" inputmode="numeric">
                        </div>
                        <div class="lrq-field">
                            <label for="lrq-convert-due-jdate">سررسید واریز به حساب مشتری</label>
                            <input type="text" id="lrq-convert-due-jdate" autocomplete="off" placeholder="مثال: ۱۴۰۵/۰۲/۲۰" inputmode="numeric">
                        </div>
                    </div>
                </div>
                <div class="lrq-convert-foot">
                    <button type="button" class="lrq-btn-ghost" id="lrq-convert-cancel">انصراف</button>
                    <button type="button" class="lrq-btn-primary" id="lrq-convert-submit">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        <span>ایجاد وام</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="lrq-sdef-overlay" class="lrq-modal-overlay lrq-modal-overlay--nested" hidden aria-hidden="true">
            <div class="lrq-sdef-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-sdef-title">
                <div class="lrq-sdef-head">
                    <h2 id="lrq-sdef-title">مدیریت تعریف وضعیت درخواست‌ها</h2>
                    <div class="lrq-sdef-head-actions">
                        <a href="{{ route('admin.sms.index') }}" target="_blank" rel="noopener noreferrer" class="lrq-btn-ghost" style="text-decoration:none;display:inline-flex;align-items:center">مدیریت قالب‌های پیامک</a>
                        <button type="button" class="lrq-edit-modal-close" id="lrq-sdef-close" aria-label="بستن">&times;</button>
                    </div>
                </div>
                <div class="lrq-sdef-body">
                    <div id="lrq-sdef-list"></div>
                    <button type="button" class="lrq-sdef-add" id="lrq-sdef-add-row"><i class="fa-solid fa-plus" aria-hidden="true"></i> افزودن وضعیت جدید</button>
                    <p class="lrq-sdef-muted">برای ویرایش هر وضعیت ابتدا دکمهٔ «مداد» را بزنید؛ پس از تغییر، دکمه به «ذخیره» (تیک) تبدیل می‌شود. قالب‌های پیامک در مسیر «مدیریت پیامک → الگوهای پیامک» با دستهٔ «درخواست وام (وضعیت)» قابل ویرایش‌اند. وضعیت‌هایی که روی درخواست استفاده شده‌اند قابل حذف نیستند.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        (function () {
            var lrqListBase = @json(rtrim(route('admin.loan-requests.index'), '/'));
            var lrqCustomersIndex = @json(route('admin.customers.index'));
            var lrqStatusDefIndex = @json(rtrim(route('admin.loan-request-status-definitions.index'), '/'));
            var lrqStatusDefStore = @json(route('admin.loan-request-status-definitions.store'));

            function lrqEditContextUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/edit-context';
            }
            function lrqConvertPreviewUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/convert-preview';
            }
            function lrqConvertUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/convert';
            }
            function lrqStatusDefItemUrl(id) {
                return lrqStatusDefIndex + '/' + encodeURIComponent(id);
            }
            function csrfToken() {
                var m = document.querySelector('meta[name="csrf-token"]');
                return m ? m.getAttribute('content') || '' : '';
            }
            function formatNum(n) {
                var x = parseInt(String(n || '0'), 10);
                if (isNaN(x)) x = 0;
                return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            /**
             * SweetAlert2 یک thenable شخصی برمی‌گرداند که .catch / .finally ندارد.
             * این helper هر thenable را در یک Promise واقعی wrap می‌کند و هرگز reject نمی‌شود
             * تا فراخوانی .catch / .finally روی نتیجه همیشه امن باشد.
             */
            function wrapSwalThenable(p) {
                if (!p || typeof p.then !== 'function') return Promise.resolve();
                return new Promise(function (resolve) {
                    try {
                        p.then(
                            function (v) { resolve(v); },
                            function () { resolve(); }
                        );
                    } catch (e) {
                        resolve();
                    }
                });
            }
            /** همیشه Promise؛ در غیر این صورت خطای sync در then به‌اشتباه «ارتباط با سرور» گزارش می‌شود. */
            function adminSwalAsPromise(method, firstArg) {
                if (!window.AdminSwal || !AdminSwal[method] || typeof AdminSwal[method] !== 'function') {
                    return Promise.resolve();
                }
                try {
                    return wrapSwalThenable(AdminSwal[method].call(AdminSwal, firstArg));
                } catch (e) {
                    return Promise.resolve();
                }
            }
            /**
             * خواندن بدنهٔ JSON پاسخ fetch؛ هرگز reject نمی‌کند تا با خطای شبکه قاطی نشود.
             */
            function readFetchJsonBody(response) {
                return response.text().then(function (text) {
                    var body = {};
                    try {
                        body = text ? JSON.parse(text) : {};
                    } catch (eParse) {
                        body = {};
                    }
                    return { ok: response.ok, status: response.status, body: body };
                }).catch(function (eRead) {
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('readFetchJsonBody', eRead);
                    }
                    var st = response && typeof response.status === 'number' ? response.status : 0;
                    return { ok: false, status: st, body: {} };
                });
            }
            function safeAdminMessage(val, fallback) {
                if (val == null || val === '') return fallback;
                try {
                    if (typeof val === 'string') return val;
                    if (typeof val === 'number' && isFinite(val)) return String(val);
                    if (typeof val === 'boolean') return val ? 'بله' : 'خیر';
                } catch (e) { /* noop */ }
                return fallback;
            }
            /**
             * پس از پاسخ موفق سرور: پر کردن مدال، SweetAlert، بستن مدال.
             * همیشه Promiseای برمی‌گرداند که در نهایت fulfilled می‌شود تا catchٔ عمومی «ارتباط با سرور» اشتباهی اجرا نشود.
             */
            function completeLoanRequestSaveAfterOk(res) {
                var fillModalErr = null;
                try {
                    if (res.body && res.body.edit_context) {
                        fillEditModal(res.body.edit_context);
                    }
                } catch (eFill) {
                    fillModalErr = eFill;
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('fillEditModal after save', eFill);
                    }
                }
                var msgOk = safeAdminMessage(res.body && res.body.message, 'ذخیره شد.');
                var chain = adminSwalAsPromise('success', msgOk).catch(function () {});
                if (res.body && res.body.sms_note) {
                    chain = chain.then(function () {
                        return adminSwalAsPromise('warning', safeAdminMessage(res.body.sms_note, ''));
                    }).catch(function () {});
                }
                if (fillModalErr) {
                    chain = chain.then(function () {
                        return adminSwalAsPromise(
                            'warning',
                            'ذخیره در سرور انجام شد اما به‌روزرسانی نمایش مدال ناموفق بود. در صورت نیاز صفحه را تازه‌سازی کنید.'
                        );
                    }).catch(function () {});
                }
                return chain
                    .then(
                        function () {
                            try {
                                closeEditModal();
                            } catch (eClose) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('closeEditModal after save', eClose);
                                }
                            }
                            // پس از موفقیت سرور و بسته شدن مدال، جدول با reload به‌روزرسانی می‌شود.
                            // query string (فیلترها/تاریخ‌ها/صفحه‌بندی) خودبه‌خود حفظ می‌شود.
                            try { window.location.reload(); } catch (eR) { /* noop */ }
                        },
                        function (errSwal) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('loan save swal chain', errSwal);
                            }
                            try {
                                closeEditModal();
                            } catch (eClose2) { /* noop */ }
                            try { window.location.reload(); } catch (eR2) { /* noop */ }
                        }
                    )
                    .catch(function (errFinal) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('loan save ui tail', errFinal);
                        }
                        return null;
                    });
            }
            function parseDigits(s) {
                return parseInt(String(s || '').replace(/[^\d]/g, '') || '0', 10) || 0;
            }
            function lrqResourceUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id);
            }
            function escapeHtmlText(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function initPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                window.jQuery('#lrq-from-jdate, #lrq-to-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
            }
            if (window.jQuery) {
                window.jQuery(function () { initPickers(); });
            } else {
                initPickers();
            }

            function lrqUniqueRowChecks() {
                var seen = {};
                var out = [];
                document.querySelectorAll('[data-lrq-row-check]').forEach(function (cb) {
                    var id = cb.getAttribute('data-lrq-id');
                    if (!id || seen[id]) return;
                    seen[id] = true;
                    out.push(cb);
                });
                return out;
            }

            function lrqSyncRowChecks(sourceCb) {
                var id = sourceCb.getAttribute('data-lrq-id');
                if (!id) return;
                var v = !!sourceCb.checked;
                document.querySelectorAll('[data-lrq-row-check][data-lrq-id="' + id + '"]').forEach(function (cb) {
                    if (cb !== sourceCb) cb.checked = v;
                });
            }

            var master = document.getElementById('lrq-select-all');
            if (master) {
                master.addEventListener('change', function () {
                    var on = !!master.checked;
                    document.querySelectorAll('[data-lrq-row-check]').forEach(function (cb) {
                        cb.checked = on;
                    });
                });
            }
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.matches || !t.matches('[data-lrq-row-check]')) return;
                lrqSyncRowChecks(t);
                var m = document.getElementById('lrq-select-all');
                if (!m) return;
                var rows = lrqUniqueRowChecks();
                if (!rows.length) {
                    m.checked = false;
                    return;
                }
                var every = true;
                for (var i = 0; i < rows.length; i++) {
                    if (!rows[i].checked) { every = false; break; }
                }
                m.checked = every;
            });

            var editOverlay = document.getElementById('lrq-edit-overlay');
            var sdefOverlay = document.getElementById('lrq-sdef-overlay');
            var statusLogOverlay = document.getElementById('lrq-statuslog-overlay');
            var smsLogOverlay = document.getElementById('lrq-smslog-overlay');
            var editLoading = document.getElementById('lrq-edit-loading');
            var editFormWrap = document.getElementById('lrq-edit-form-wrap');
            var editClose = document.getElementById('lrq-edit-close');
            var sdefClose = document.getElementById('lrq-sdef-close');
            var statusLogClose = document.getElementById('lrq-statuslog-close');
            var smsLogClose = document.getElementById('lrq-smslog-close');
            var sdefListEl = document.getElementById('lrq-sdef-list');
            var sdefAddBtn = document.getElementById('lrq-sdef-add-row');
            var btnOpenStatusDefs = document.getElementById('lrq-open-status-defs');
            var lrqEditCtx = { customerId: 0, requestId: 0 };
            var lrqStatusLogSearchTimer = null;

            function syncLrqModalScrollLock() {
                var convertOv = document.getElementById('lrq-convert-overlay');
                var anyOpen =
                    (editOverlay && !editOverlay.hidden) ||
                    (sdefOverlay && !sdefOverlay.hidden) ||
                    (statusLogOverlay && !statusLogOverlay.hidden) ||
                    (smsLogOverlay && !smsLogOverlay.hidden) ||
                    (convertOv && !convertOv.hidden);
                document.documentElement.style.overflow = anyOpen ? 'hidden' : '';
            }

            function setOverlay(open, el) {
                if (!el) return;
                el.hidden = !open;
                el.setAttribute('aria-hidden', open ? 'false' : 'true');
                syncLrqModalScrollLock();
            }

            function closeEditModal() {
                setOverlay(false, editOverlay);
            }
            function closeSdefModal() {
                setOverlay(false, sdefOverlay);
            }
            function closeStatusLogModal() {
                setOverlay(false, statusLogOverlay);
            }
            function closeSmsLogModal() {
                setOverlay(false, smsLogOverlay);
            }

            function openCustomersUrl(params) {
                var u = new URL(lrqCustomersIndex, window.location.origin);
                Object.keys(params).forEach(function (k) {
                    u.searchParams.set(k, params[k]);
                });
                window.location.href = u.toString();
            }

            function fillEditModal(data) {
                function lrqEl(id) {
                    return document.getElementById(id);
                }
                function setLrqText(id, s) {
                    var n = lrqEl(id);
                    if (n) n.textContent = s;
                }
                function setLrqValue(id, s) {
                    var n = lrqEl(id);
                    if (n) n.value = s;
                }
                var c = data.customer || {};
                var r = data.request || {};
                lrqEditCtx.customerId = parseInt(String(c.id || 0), 10);
                lrqEditCtx.requestId = parseInt(String(r.id || 0), 10);
                // نگه‌داری آخرین payload سرور برای دسترسی سایر مدال‌ها (مثل «تبدیل به وام»).
                try { lrqEditCtx._lastData = data; } catch (eCtxStash) { /* noop */ }

                setLrqText('lrq-edit-cust-name', c.full_name || '—');
                setLrqText('lrq-edit-cust-username', c.username || '—');
                setLrqText('lrq-edit-national', c.national_id_fa || '—');
                setLrqText('lrq-edit-mobile', c.mobile_fa || '—');
                setLrqText('lrq-edit-father', c.father_name_fa || '—');
                setLrqText('lrq-edit-loan-count', String(c.loan_count != null ? c.loan_count : '—'));
                setLrqText('lrq-edit-loans-total', (c.loans_total_fa || '—') + ' تومان');
                setLrqText('lrq-edit-remain', (c.installments_remaining_fa || '—') + ' تومان');
                setLrqText('lrq-edit-membership', c.membership_at_fa || '—');
                setLrqText('lrq-edit-last-login', c.last_login_fa || '—');
                setLrqText('lrq-edit-wallet', (c.wallet_balance_fa || '0') + ' تومان');
                setLrqText('lrq-edit-good', c.good_standing_label || 'نامشخص');

                setLrqText('lrq-edit-req-date', (r.submitted_date_fa || '—') + ' ' + (r.submitted_time_fa || ''));
                setLrqText('lrq-edit-req-status-label', r.status_label || '—');

                var loanSel = lrqEl('lrq-edit-loan-type');
                if (loanSel) {
                    loanSel.innerHTML = '';
                    (data.loan_types || []).forEach(function (lt) {
                        var o = document.createElement('option');
                        o.value = String(lt.id);
                        o.textContent = lt.label || ('#' + lt.id);
                        loanSel.appendChild(o);
                    });
                    loanSel.value = String(r.loan_type_id || '');
                }

                setLrqValue('lrq-edit-amount', formatNum(r.amount_toman));
                setLrqValue('lrq-edit-inst-count', formatNum(r.installments_count));
                setLrqValue('lrq-edit-inst-gap', formatNum(r.installment_interval_count));
                setLrqValue('lrq-edit-inst-amt', formatNum(r.installment_amount_toman));
                setLrqText('lrq-edit-gap-unit-hint', 'واحد فاصله اقساط: ' + (r.installment_interval_unit_fa || '—'));

                var stSel = lrqEl('lrq-edit-status');
                if (stSel) {
                    stSel.innerHTML = '';
                    (data.status_options || []).forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = s.code;
                        o.textContent = s.title;
                        stSel.appendChild(o);
                    });
                    stSel.value = r.status || '';
                }

                var exAd = document.getElementById('lrq-edit-expert-admin');
                if (exAd) exAd.value = r.expert_note != null ? String(r.expert_note) : '';
                var exCu = document.getElementById('lrq-edit-expert-customer');
                if (exCu) exCu.value = r.expert_note_customer != null ? String(r.expert_note_customer) : '';
                var descEl = document.getElementById('lrq-edit-description');
                if (descEl) descEl.textContent = (r.description != null && String(r.description).trim() !== '') ? String(r.description) : '—';
                var cbDoc = document.getElementById('lrq-edit-doc-received');
                if (cbDoc) cbDoc.checked = !!r.documents_physical_received;
                var cbSms = document.getElementById('lrq-edit-send-sms');
                if (cbSms) cbSms.checked = false;

                var loanManageA = lrqEl('lrq-edit-open-loan-manage');
                if (loanManageA) {
                    if (lrqEditCtx.customerId) {
                        var u2 = new URL(lrqCustomersIndex, window.location.origin);
                        u2.searchParams.set('open_loan_manage', '1');
                        u2.searchParams.set('customer_id', String(lrqEditCtx.customerId));
                        loanManageA.href = u2.toString();
                    } else {
                        loanManageA.href = '#';
                    }
                }

                var editCustBtn = lrqEl('lrq-edit-open-customer-form');
                if (editCustBtn) {
                    editCustBtn.onclick = function () {
                        if (!lrqEditCtx.customerId) return;
                        openCustomersUrl({ open_customer_edit: '1', customer_id: String(lrqEditCtx.customerId) });
                    };
                    editCustBtn.disabled = !lrqEditCtx.customerId;
                }

                lrqEditCtx.documents = Array.isArray(data.documents) ? data.documents.slice() : [];
                lrqEditCtx.document_review_statuses = Array.isArray(data.document_review_statuses) ? data.document_review_statuses.slice() : [];
                renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses);

                // وضعیت دکمه «تبدیل به وام»: اگر قبلاً تبدیل شده، غیرفعال می‌شود و توضیح مناسب نمایش می‌یابد.
                var convertBtn = document.getElementById('lrq-edit-convert-loan');
                if (convertBtn) {
                    if (r.is_converted_to_loan) {
                        convertBtn.disabled = true;
                        convertBtn.title = 'این درخواست قبلاً به وام تبدیل شده است' + (r.converted_at_fa ? ' — در ' + r.converted_at_fa : '');
                        convertBtn.setAttribute('aria-disabled', 'true');
                    } else {
                        convertBtn.disabled = false;
                        convertBtn.title = 'تبدیل این درخواست به یک پروندهٔ وام برای مشتری';
                        convertBtn.removeAttribute('aria-disabled');
                    }
                }

                // نوار قرمز بزرگ بالای مدال: فقط در صورت تبدیل‌شدن این درخواست به وام نمایش داده می‌شود.
                var convertedBanner = document.getElementById('lrq-edit-converted-banner');
                var convertedMeta = document.getElementById('lrq-edit-converted-meta');
                if (convertedBanner) {
                    if (r.is_converted_to_loan) {
                        convertedBanner.hidden = false;
                        if (convertedMeta) {
                            convertedMeta.textContent = r.converted_at_fa ? '(در ' + r.converted_at_fa + ')' : '';
                        }
                    } else {
                        convertedBanner.hidden = true;
                        if (convertedMeta) convertedMeta.textContent = '';
                    }
                }
            }

            function lrqAdminDocResourceUrl(rid, docId) {
                return lrqResourceUrl(rid) + '/documents/' + encodeURIComponent(docId);
            }

            function lrqDocAdminCardHtml(doc, statusDefs) {
                var desc = (doc.description != null && String(doc.description).trim() !== '')
                    ? escapeHtmlText(String(doc.description))
                    : 'توضیحاتی ثبت نشده است.';
                var url = escapeHtmlText(doc.file_url || '');
                var previewBlock = '';
                if (doc.is_image && doc.file_url) {
                    previewBlock =
                        '<div class="lrq-doc-admin-preview"><img src="' + url + '" alt="" loading="lazy"/>' +
                        '<div><a class="lrq-doc-admin-dl" href="' + url + '" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود</a></div></div>';
                } else {
                    previewBlock =
                        '<div class="lrq-doc-admin-preview">' +
                        '<a class="lrq-doc-admin-dl" href="' + url + '" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود / مشاهده</a></div>';
                }
                var seg = '';
                (statusDefs || []).forEach(function (st) {
                    var c = String(st.code || '');
                    var active = String(doc.review_status || '') === c ? ' is-active' : '';
                    seg += '<button type="button" class="lrq-doc-status-btn' + active + '" data-code="' + escapeHtmlText(c) + '">' + escapeHtmlText(String(st.label || c)) + '</button>';
                });
                var ex = doc.expert_note != null ? String(doc.expert_note) : '';
                return (
                    '<div class="lrq-doc-admin-card" data-lrq-doc-id="' + escapeHtmlText(String(doc.id)) + '">' +
                    '<div class="lrq-doc-admin-card-head">' + escapeHtmlText(String(doc.document_title || 'مدرک')) +
                    ' <span class="lrq-doc-admin-chip">(' + escapeHtmlText(String(doc.review_status_label || '')) + ')</span></div>' +
                    '<div class="lrq-doc-admin-body">' +
                    '<div class="lrq-doc-admin-cust"><strong>توضیحات مشتری</strong><p>' + desc + '</p></div>' +
                    previewBlock +
                    '<div class="lrq-doc-status-wrap"><span class="lrq-doc-status-label">تغییر وضعیت</span><div class="lrq-doc-status-seg">' + seg + '</div></div>' +
                    '<div class="lrq-doc-expert-wrap"><span class="lrq-doc-status-label">نظر کارشناس (مشتری نیز می‌بیند)</span><textarea class="lrq-doc-expert-note" rows="3">' + escapeHtmlText(ex) + '</textarea></div>' +
                    '<div class="lrq-doc-admin-actions">' +
                    '<button type="button" class="lrq-doc-admin-del" title="حذف این مدرک از درخواست" aria-label="حذف مدرک"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
                    '<button type="button" class="lrq-doc-admin-save">ثبت</button>' +
                    '</div></div></div>'
                );
            }

            function renderLrqAdminDocuments(docs, statusDefs) {
                var host = document.getElementById('lrq-edit-docs-host');
                if (!host) return;
                if (!docs || !docs.length) {
                    host.innerHTML = '<p class="lrq-muted" style="margin:0;font-size:0.8rem;">مدرکی برای این درخواست ثبت نشده است.</p>';
                    return;
                }
                var html = '';
                for (var i = 0; i < docs.length; i++) {
                    html += lrqDocAdminCardHtml(docs[i], statusDefs);
                }
                host.innerHTML = html;
            }

            function loadStatusLogsTable() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var qIn = document.getElementById('lrq-statuslog-q');
                var q = qIn ? String(qIn.value || '').trim() : '';
                var url = lrqResourceUrl(rid) + '/status-logs' + (q ? ('?q=' + encodeURIComponent(q)) : '');
                var tbody = document.getElementById('lrq-statuslog-tbody');
                var empty = document.getElementById('lrq-statuslog-empty');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="4" class="lrq-muted">در حال بارگذاری…</td></tr>';
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var rows = data.logs || [];
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (empty) empty.hidden = false;
                        return;
                    }
                    if (empty) empty.hidden = true;
                    rows.forEach(function (row) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + escapeHtmlText(row.user_label) + '</td>' +
                            '<td>' + escapeHtmlText(row.created_at_fa) + '</td>' +
                            '<td>' + escapeHtmlText(row.from_status_customer) + '</td>' +
                            '<td>' + escapeHtmlText(row.to_status_customer) + '</td>';
                        tbody.appendChild(tr);
                    });
                }).catch(function () {
                    tbody.innerHTML = '<tr><td colspan="4" class="lrq-muted">بارگذاری ناموفق بود.</td></tr>';
                });
            }

            function openStatusLogModal() {
                if (!statusLogOverlay || !lrqEditCtx.requestId) return;
                var qIn = document.getElementById('lrq-statuslog-q');
                if (qIn) qIn.value = '';
                setOverlay(true, statusLogOverlay);
                loadStatusLogsTable();
            }

            function loadSmsLogsTable() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var url = lrqResourceUrl(rid) + '/status-sms-logs';
                var tbody = document.getElementById('lrq-smslog-tbody');
                var empty = document.getElementById('lrq-smslog-empty');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="7" class="lrq-muted">در حال بارگذاری…</td></tr>';
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var rows = data.logs || [];
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (empty) empty.hidden = false;
                        return;
                    }
                    if (empty) empty.hidden = true;
                    rows.forEach(function (row) {
                        var tr = document.createElement('tr');
                        var st = escapeHtmlText(row.status_label || '');
                        var btn = '<button type="button" class="lrq-log-mini" data-lrq-sms-resend="' + String(row.id) + '">ارسال مجدد</button>';
                        tr.innerHTML =
                            '<td>' + escapeHtmlText(row.sms_panel) + '</td>' +
                            '<td>' + st + '</td>' +
                            '<td>' + escapeHtmlText(row.sent_at_fa) + '</td>' +
                            '<td class="lrq-log-msg">' + escapeHtmlText(row.message_text) + '</td>' +
                            '<td>' + escapeHtmlText(row.recipient) + '</td>' +
                            '<td>' + escapeHtmlText(row.type_label || row.type) + '</td>' +
                            '<td>' + btn + '</td>';
                        tbody.appendChild(tr);
                    });
                }).catch(function () {
                    tbody.innerHTML = '<tr><td colspan="7" class="lrq-muted">بارگذاری ناموفق بود.</td></tr>';
                });
            }

            function openSmsLogModal() {
                if (!smsLogOverlay || !lrqEditCtx.requestId) return;
                setOverlay(true, smsLogOverlay);
                loadSmsLogsTable();
            }

            function saveLoanRequestEdit() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var loanSel = document.getElementById('lrq-edit-loan-type');
                var stSel = document.getElementById('lrq-edit-status');
                var payload = {
                    loan_type_id: parseInt(String(loanSel && loanSel.value ? loanSel.value : '0'), 10),
                    amount_toman: parseDigits(document.getElementById('lrq-edit-amount') && document.getElementById('lrq-edit-amount').value),
                    installments_count: parseDigits(document.getElementById('lrq-edit-inst-count') && document.getElementById('lrq-edit-inst-count').value),
                    installment_interval_count: parseDigits(document.getElementById('lrq-edit-inst-gap') && document.getElementById('lrq-edit-inst-gap').value),
                    status: stSel ? String(stSel.value || '') : '',
                    expert_note: (document.getElementById('lrq-edit-expert-admin') && document.getElementById('lrq-edit-expert-admin').value) || '',
                    expert_note_customer: (document.getElementById('lrq-edit-expert-customer') && document.getElementById('lrq-edit-expert-customer').value) || '',
                    documents_physical_received: !!(document.getElementById('lrq-edit-doc-received') && document.getElementById('lrq-edit-doc-received').checked),
                    send_status_sms: !!(document.getElementById('lrq-edit-send-sms') && document.getElementById('lrq-edit-send-sms').checked)
                };
                var btn = document.getElementById('lrq-edit-save');
                if (btn) btn.disabled = true;
                // خطای واقعی شبکه فقط هنگامی باید «ارتباط با سرور» نمایش دهد که خود fetch reject شود.
                // به همین دلیل onRejected را روی همان fetch می‌بندیم و خطاهای post-success در داخل
                // try/catch داخلی نگه داشته می‌شوند تا اشتباهاً به‌عنوان «خطای شبکه» گزارش نشوند.
                fetch(lrqResourceUrl(rid), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('saveLoanRequestEdit transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return;
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'ذخیره انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            return;
                        }
                        try {
                            return completeLoanRequestSaveAfterOk(res);
                        } catch (eHandle) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('saveLoanRequestEdit handleResponse', eHandle);
                            }
                            try { closeEditModal(); } catch (eClose) { /* noop */ }
                            if (window.AdminSwal && AdminSwal.warning) {
                                AdminSwal.warning('تغییرات در سرور ذخیره شد، اما در نمایش نتیجه خطایی رخ داد. در صورت نیاز صفحه را تازه‌سازی کنید.');
                            }
                        }
                    })
                    .then(null, function (eUnexpected) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('saveLoanRequestEdit post-success', eUnexpected);
                        }
                        // به‌عمد پیام «ارتباط با سرور» نشان نمی‌دهیم؛ سرور موفق پاسخ داده و تغییرات ذخیره شده‌اند.
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                    });
            }

            function refreshMainStatusSelect() {
                var stSel = document.getElementById('lrq-edit-status');
                if (!stSel || editOverlay.hidden) return;
                var keep = stSel.value;
                fetch(lrqStatusDefIndex, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    stSel.innerHTML = '';
                    (data.definitions || []).forEach(function (d) {
                        var o = document.createElement('option');
                        o.value = d.code;
                        o.textContent = d.title;
                        stSel.appendChild(o);
                    });
                    stSel.value = keep;
                }).catch(function () { /* noop */ });
            }

            function openLrqEditModal(requestId) {
                if (!editOverlay) return;
                editLoading.hidden = false;
                editFormWrap.hidden = true;
                setOverlay(true, editOverlay);
                fetch(lrqEditContextUrl(requestId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    fillEditModal(data);
                    editLoading.hidden = true;
                    editFormWrap.hidden = false;
                }).catch(function () {
                    closeEditModal();
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error('بارگذاری اطلاعات درخواست ناموفق بود.');
                    }
                });
            }

            document.addEventListener('click', function (e) {
                var t = e.target;
                var openBtn = t.closest && t.closest('[data-lrq-open-edit]');
                if (openBtn) {
                    e.preventDefault();
                    var rid = parseInt(openBtn.getAttribute('data-lrq-open-edit') || '0', 10);
                    if (rid) openLrqEditModal(rid);
                }
            });

            function lrqRemoveRequestRowsFromDom(rid) {
                var key = String(rid);
                document.querySelectorAll('[data-lrq-row-check][data-lrq-id="' + key + '"]').forEach(function (cb) {
                    var tr = cb.closest('tr');
                    if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
                    var card = cb.closest('article.lrq-card');
                    if (card && card.parentNode) card.parentNode.removeChild(card);
                });
            }

            function lrqMaybeShowEmptyAfterDelete() {
                var deskBody = document.querySelector('.lrq-desktop-only .lrq-tbl tbody');
                if (deskBody && !deskBody.querySelector('tr')) {
                    var emptyTr = document.createElement('tr');
                    emptyTr.innerHTML = '<td colspan="6" class="lrq-empty">در این بازه تاریخ، درخواست وامی ثبت نشده است.</td>';
                    deskBody.appendChild(emptyTr);
                }
                var mobile = document.querySelector('.lrq-mobile-stack');
                if (mobile && !mobile.querySelector('article.lrq-card') && !mobile.querySelector('.lrq-card-empty')) {
                    var emptyDiv = document.createElement('div');
                    emptyDiv.className = 'lrq-card-empty';
                    emptyDiv.setAttribute('role', 'status');
                    emptyDiv.textContent = 'در این بازه تاریخ، درخواست وامی ثبت نشده است.';
                    mobile.appendChild(emptyDiv);
                }
                var master = document.getElementById('lrq-select-all');
                if (master) master.checked = false;
            }

            function performLrqDelete(rid) {
                // خطای واقعی شبکه فقط هنگامی باید «ارتباط با سرور برقرار نشد» نمایش دهد که خود fetch reject شود.
                // به همین دلیل onRejected را روی همان fetch می‌بندیم و منطق UI را در try/catch داخلی نگه می‌داریم
                // تا یک خطای synchronous در پردازش پاسخ موفق، اشتباهاً به‌عنوان «خطای شبکه» گزارش نشود.
                fetch(lrqResourceUrl(rid), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('performLrqDelete transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return;
                        if (!res.ok) {
                            var err = safeAdminMessage(res.body && res.body.message, 'حذف انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err);
                            return;
                        }
                        var msgOk = safeAdminMessage(res.body && res.body.message, 'حذف شد.');
                        try {
                            if (editOverlay && !editOverlay.hidden && lrqEditCtx.requestId === rid) {
                                try { closeEditModal(); } catch (eClose) { /* noop */ }
                            }
                            lrqRemoveRequestRowsFromDom(rid);
                            lrqMaybeShowEmptyAfterDelete();
                        } catch (eUi) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('performLrqDelete UI tail', eUi);
                            }
                        }
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success(msgOk);
                    });
            }

            document.addEventListener('click', function (e) {
                var t = e.target;
                var delBtn = t.closest && t.closest('[data-lrq-delete]');
                if (!delBtn) return;
                e.preventDefault();
                var rid = parseInt(delBtn.getAttribute('data-lrq-delete') || '0', 10);
                if (!rid) return;
                var reqNo = delBtn.getAttribute('data-lrq-delete-no') || '';
                var confirmTitle = 'حذف درخواست وام' + (reqNo ? ' شماره ' + reqNo : '');
                var confirmText = 'با حذف این درخواست، تمام مدارک، فایل‌های پیوست و لاگ‌های تغییر وضعیت آن نیز حذف می‌شوند. این عمل قابل بازگشت نیست.';
                if (window.AdminSwal && AdminSwal.confirm) {
                    wrapSwalThenable(AdminSwal.confirm({
                        title: confirmTitle,
                        text: confirmText,
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف'
                    })).then(function (result) {
                        if (result && result.isConfirmed) performLrqDelete(rid);
                    });
                    return;
                }
                if (window.confirm(confirmTitle + '\n' + confirmText)) {
                    performLrqDelete(rid);
                }
            });

            if (editClose) editClose.addEventListener('click', closeEditModal);
            var loanManageNav = document.getElementById('lrq-edit-open-loan-manage');
            if (loanManageNav) {
                loanManageNav.addEventListener('click', function (e) {
                    if (!lrqEditCtx.customerId) e.preventDefault();
                });
            }
            if (editOverlay) {
                editOverlay.addEventListener('click', function (e) {
                    if (e.target === editOverlay) closeEditModal();
                });
            }
            if (editFormWrap) {
                editFormWrap.addEventListener('click', function (e) {
                    var t = e.target;
                    var stBtn = t.closest && t.closest('.lrq-doc-status-btn');
                    if (stBtn && editFormWrap.contains(stBtn)) {
                        var seg = stBtn.closest('.lrq-doc-status-seg');
                        if (!seg) return;
                        var btns = seg.querySelectorAll('.lrq-doc-status-btn');
                        for (var si = 0; si < btns.length; si++) btns[si].classList.remove('is-active');
                        stBtn.classList.add('is-active');
                        return;
                    }
                    var saveBtn = t.closest && t.closest('.lrq-doc-admin-save');
                    if (saveBtn && editFormWrap.contains(saveBtn)) {
                        e.preventDefault();
                        var card = saveBtn.closest('.lrq-doc-admin-card');
                        if (!card || !lrqEditCtx.requestId) return;
                        var docId = parseInt(card.getAttribute('data-lrq-doc-id') || '0', 10);
                        var act = card.querySelector('.lrq-doc-status-btn.is-active');
                        if (!act) {
                            if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('یک وضعیت را انتخاب کنید.');
                            return;
                        }
                        var code = act.getAttribute('data-code') || '';
                        var ta = card.querySelector('.lrq-doc-expert-note');
                        var note = ta ? String(ta.value || '') : '';
                        saveBtn.disabled = true;
                        fetch(lrqAdminDocResourceUrl(lrqEditCtx.requestId, docId), {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ review_status: code, expert_note: note })
                        }).then(function (r) {
                            return r.text().then(function (text) {
                                var body = {};
                                try { body = text ? JSON.parse(text) : {}; } catch (e2) {}
                                return { ok: r.ok, body: body };
                            });
                        }, function (errNet) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('docUpdate transport', errNet);
                            }
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط برقرار نشد.');
                            return null;
                        }).then(function (res) {
                            if (!res) return;
                            if (!res.ok) {
                                var msg = (res.body && res.body.message) ? String(res.body.message) : 'ذخیره نشد.';
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                                return;
                            }
                            var okMsg = (res.body && res.body.message) ? String(res.body.message) : 'ذخیره شد.';
                            try {
                                var d = res.body && res.body.document;
                                if (d && lrqEditCtx.documents) {
                                    lrqEditCtx.documents = lrqEditCtx.documents.map(function (row) {
                                        if (Number(row.id) !== Number(d.id)) return row;
                                        return Object.assign({}, row, {
                                            review_status: d.review_status,
                                            review_status_label: d.review_status_label,
                                            expert_note: d.expert_note
                                        });
                                    });
                                    renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses || []);
                                }
                            } catch (eUi) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('docUpdate UI tail', eUi);
                                }
                            }
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(okMsg);
                        }).finally(function () {
                            saveBtn.disabled = false;
                        });
                        return;
                    }
                    var delBtn = t.closest && t.closest('.lrq-doc-admin-del');
                    if (delBtn && editFormWrap.contains(delBtn)) {
                        e.preventDefault();
                        if (!window.confirm('این مدرک از درخواست حذف شود؟ در صورت حذف، کاربر دیگر ملزم به بارگذاری این فایل نیست (مگر خودش دوباره آپلود کند).')) return;
                        var card2 = delBtn.closest('.lrq-doc-admin-card');
                        if (!card2 || !lrqEditCtx.requestId) return;
                        var docId2 = parseInt(card2.getAttribute('data-lrq-doc-id') || '0', 10);
                        delBtn.disabled = true;
                        fetch(lrqAdminDocResourceUrl(lrqEditCtx.requestId, docId2), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        }).then(function (r) {
                            return r.text().then(function (text) {
                                var body = {};
                                try { body = text ? JSON.parse(text) : {}; } catch (e2) {}
                                return { ok: r.ok, body: body };
                            });
                        }, function (errNet) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('docDelete transport', errNet);
                            }
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط برقرار نشد.');
                            return null;
                        }).then(function (res) {
                            if (!res) return;
                            if (!res.ok) {
                                var msg = (res.body && res.body.message) ? String(res.body.message) : 'حذف انجام نشد.';
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                                return;
                            }
                            var okMsg = (res.body && res.body.message) ? String(res.body.message) : 'حذف شد.';
                            try {
                                if (res.body && res.body.edit_context) {
                                    fillEditModal(res.body.edit_context);
                                } else if (lrqEditCtx.documents) {
                                    lrqEditCtx.documents = lrqEditCtx.documents.filter(function (row) { return Number(row.id) !== docId2; });
                                    renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses || []);
                                }
                            } catch (eDelFill) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('fillEditModal after document delete', eDelFill);
                                }
                            }
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(okMsg);
                        }).finally(function () {
                            delBtn.disabled = false;
                        });
                    }
                });
            }
            if (sdefClose) sdefClose.addEventListener('click', closeSdefModal);
            if (sdefOverlay) {
                sdefOverlay.addEventListener('click', function (e) {
                    if (e.target === sdefOverlay) closeSdefModal();
                });
            }

            if (statusLogClose) statusLogClose.addEventListener('click', closeStatusLogModal);
            if (smsLogClose) smsLogClose.addEventListener('click', closeSmsLogModal);
            if (statusLogOverlay) {
                statusLogOverlay.addEventListener('click', function (e) {
                    if (e.target === statusLogOverlay) closeStatusLogModal();
                });
            }
            if (smsLogOverlay) {
                smsLogOverlay.addEventListener('click', function (e) {
                    if (e.target === smsLogOverlay) closeSmsLogModal();
                });
            }

            var btnOpenStatusLog = document.getElementById('lrq-edit-open-status-log');
            if (btnOpenStatusLog) btnOpenStatusLog.addEventListener('click', openStatusLogModal);
            var btnOpenSmsLog = document.getElementById('lrq-edit-open-sms-log');
            if (btnOpenSmsLog) btnOpenSmsLog.addEventListener('click', openSmsLogModal);

            var btnSaveEdit = document.getElementById('lrq-edit-save');
            if (btnSaveEdit) btnSaveEdit.addEventListener('click', saveLoanRequestEdit);

            // ===================== مدال «تبدیل به وام» =====================
            var convertOverlay = document.getElementById('lrq-convert-overlay');
            var convertCloseBtn = document.getElementById('lrq-convert-close');
            var convertCancelBtn = document.getElementById('lrq-convert-cancel');
            var convertSubmitBtn = document.getElementById('lrq-convert-submit');
            var convertOpenBtn = document.getElementById('lrq-edit-convert-loan');
            var convertStartInput = document.getElementById('lrq-convert-start-jdate');
            var convertDueInput = document.getElementById('lrq-convert-due-jdate');
            var convertSummary = document.getElementById('lrq-convert-summary');
            var convertHint = document.getElementById('lrq-convert-hint');
            var convertCtx = { initialized: false, busy: false };

            function escapeConvertHtml(s) {
                return escapeHtmlText(s);
            }

            function initConvertDatepickers() {
                if (convertCtx.initialized) return;
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                window.jQuery('#lrq-convert-start-jdate, #lrq-convert-due-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
                convertCtx.initialized = true;
            }

            function renderConvertSummary(r, c) {
                if (!convertSummary) return;
                var rows = [
                    { k: 'مشتری', v: (c.full_name || '—') + (c.username ? ' — ' + c.username : '') },
                    { k: 'کد ملی', v: c.national_id_fa || '—' },
                    { k: 'موبایل', v: c.mobile_fa || '—' },
                    { k: 'مبلغ وام', v: formatNum(r.amount_toman) + ' تومان' },
                    { k: 'تعداد اقساط', v: formatNum(r.installments_count) + ' قسط' },
                    { k: 'فاصلهٔ هر قسط', v: formatNum(r.installment_interval_count) + ' ' + (r.installment_interval_unit_fa || '') },
                    { k: 'مبلغ هر قسط (پیش‌فرض)', v: formatNum(r.installment_amount_toman) + ' تومان' }
                ];
                var html = '';
                for (var i = 0; i < rows.length; i++) {
                    html += '<div class="lrq-convert-row"><span class="k">' + escapeConvertHtml(rows[i].k) + '</span><span class="v">' + escapeConvertHtml(rows[i].v) + '</span></div>';
                }
                convertSummary.innerHTML = html;
            }

            function openConvertModal() {
                if (!convertOverlay) return;
                var r = (lrqEditCtx && lrqEditCtx._lastData && lrqEditCtx._lastData.request) || null;
                var c = (lrqEditCtx && lrqEditCtx._lastData && lrqEditCtx._lastData.customer) || null;
                if (!r || !c || !lrqEditCtx.requestId) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('اطلاعات درخواست در دسترس نیست؛ مدال را ببندید و دوباره باز کنید.');
                    return;
                }
                if (r.is_converted_to_loan) {
                    if (window.AdminSwal && AdminSwal.info) {
                        AdminSwal.info('این درخواست قبلاً به وام تبدیل شده است' + (r.converted_at_fa ? ' (در ' + r.converted_at_fa + ')' : '') + '.');
                    }
                    return;
                }
                renderConvertSummary(r, c);
                if (convertStartInput) convertStartInput.value = '';
                if (convertDueInput) convertDueInput.value = '';
                setOverlay(true, convertOverlay);
                initConvertDatepickers();
                setTimeout(function () { if (convertStartInput) { try { convertStartInput.focus(); } catch (e) {} } }, 50);
            }

            function closeConvertModal() {
                if (!convertOverlay) return;
                setOverlay(false, convertOverlay);
                convertCtx.busy = false;
                if (convertSubmitBtn) convertSubmitBtn.disabled = false;
            }

            function buildConvertConfirmHtml(p) {
                function row(k, v, strong) {
                    return '<tr>'
                        + '<th style="text-align:start;padding:0.35rem 0.5rem;border-bottom:1px solid rgba(148,163,184,0.35);font-weight:600;white-space:nowrap;color:#64748b">' + escapeConvertHtml(k) + '</th>'
                        + '<td style="text-align:end;padding:0.35rem 0.5rem;border-bottom:1px solid rgba(148,163,184,0.35);' + (strong ? 'font-weight:800;' : 'font-weight:600;') + '">' + escapeConvertHtml(v) + '</td>'
                        + '</tr>';
                }
                var c = p.customer || {};
                var lt = p.loan_type || {};
                var rows = '';
                rows += row('مشتری', (c.full_name || '—') + (c.username ? ' (' + c.username + ')' : ''));
                rows += row('کد ملی', c.national_id_fa || '—');
                rows += row('موبایل', c.mobile_fa || '—');
                rows += row('نوع وام', lt.title || '—');
                rows += row('روش محاسبهٔ سود', lt.profit_method_label || '—');
                rows += row('درصد سود', (lt.interest_rate_fa || '—') + '٪');
                rows += row('مبلغ وام', formatNum(p.amount_toman) + ' تومان', true);
                rows += row('تعداد اقساط', formatNum(p.installments_count) + ' قسط');
                rows += row('فاصلهٔ بین اقساط', formatNum(p.installment_interval_count) + ' ' + (p.installment_interval_unit_fa || ''));
                rows += row('مبلغ هر قسط', formatNum(p.installment_amount_toman) + ' تومان', true);
                rows += row('سود کل', formatNum(p.profit_toman) + ' تومان');
                rows += row('جمع کل قابل بازپرداخت', formatNum(p.total_repayable_toman) + ' تومان', true);
                rows += row('تاریخ شروع وام', p.loan_start_jdate_fa || '—', true);
                rows += row('سررسید واریز به مشتری', p.disbursement_due_jdate_fa || '—');
                if (p.first_due_jdate_fa) rows += row('اولین سررسید قسط', p.first_due_jdate_fa);
                if (p.last_due_jdate_fa) rows += row('آخرین سررسید قسط', p.last_due_jdate_fa);
                return ''
                    + '<div style="font-size:0.82rem;line-height:1.7;text-align:start;direction:rtl">'
                    + '<p style="margin:0 0 0.55rem;color:#475569">ایجاد وام با مشخصات زیر برای مشتری مورد تأیید است؟ پس از تأیید، پروندهٔ وام و جدول اقساط ساخته می‌شود و از این پس قابل ویرایش از طریق «مدیریت وام‌های مشتری» است.</p>'
                    + '<table style="width:100%;border-collapse:collapse;background:rgba(99,102,241,0.04);border:1px solid rgba(148,163,184,0.35);border-radius:0.6rem;overflow:hidden">'
                    + '<tbody>' + rows + '</tbody>'
                    + '</table>'
                    + '</div>';
            }

            function fetchConvertPreview(rid, startJ, dueJ) {
                var u = new URL(lrqConvertPreviewUrl(rid), window.location.origin);
                if (startJ) u.searchParams.set('loan_start_jdate', startJ);
                if (dueJ) u.searchParams.set('disbursement_due_jdate', dueJ);
                return fetch(u.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(readFetchJsonBody);
            }

            function performLrqConvert() {
                if (convertCtx.busy) return;
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var startJ = (convertStartInput && convertStartInput.value ? String(convertStartInput.value).trim() : '');
                var dueJ = (convertDueInput && convertDueInput.value ? String(convertDueInput.value).trim() : '');
                if (!startJ) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('تاریخ شروع وام را وارد کنید.');
                    if (convertStartInput) { try { convertStartInput.focus(); } catch (e) {} }
                    return;
                }
                if (!dueJ) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('تاریخ سررسید واریز به حساب مشتری را وارد کنید.');
                    if (convertDueInput) { try { convertDueInput.focus(); } catch (e) {} }
                    return;
                }
                convertCtx.busy = true;
                if (convertSubmitBtn) convertSubmitBtn.disabled = true;

                fetchConvertPreview(rid, startJ, dueJ)
                    .then(null, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvertPreview transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        convertCtx.busy = false;
                        if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return null;
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'پیش‌نمایش امکان‌پذیر نیست.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        var preview = res.body || {};
                        if (preview.already_converted) {
                            if (window.AdminSwal && AdminSwal.info) AdminSwal.info('این درخواست قبلاً به وام تبدیل شده است.');
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        if (!window.AdminSwal || !AdminSwal.fire) {
                            // در نبود SweetAlert از confirm استاندارد استفاده می‌کنیم.
                            if (window.confirm('ایجاد وام با مشخصات نمایش داده شده تأیید می‌شود؟')) {
                                return doConvertCommit(rid, startJ, dueJ);
                            }
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        return wrapSwalThenable(AdminSwal.fire({
                            icon: 'question',
                            title: 'تأیید ایجاد وام',
                            html: buildConvertConfirmHtml(preview),
                            width: 720,
                            showCancelButton: true,
                            confirmButtonText: 'تأیید و ایجاد وام',
                            cancelButtonText: 'انصراف',
                            reverseButtons: true,
                            focusCancel: true
                        })).then(function (result) {
                            if (result && result.isConfirmed) {
                                return doConvertCommit(rid, startJ, dueJ);
                            }
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        });
                    });
            }

            function doConvertCommit(rid, startJ, dueJ) {
                return fetch(lrqConvertUrl(rid), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ loan_start_jdate: startJ, disbursement_due_jdate: dueJ })
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvert transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) {
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return;
                        }
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'ایجاد وام انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return;
                        }
                        var okMsg = safeAdminMessage(res.body && res.body.message, 'وام با موفقیت ایجاد شد.');
                        try { closeConvertModal(); } catch (eCC) { /* noop */ }
                        try { closeEditModal(); } catch (eCE) { /* noop */ }
                        // پس از موفقیت، SweetAlert موفقیت را نشان می‌دهیم و در ادامه جدول را رفرش می‌کنیم.
                        adminSwalAsPromise('success', okMsg).then(function () {
                            try { window.location.reload(); } catch (eR) { /* noop */ }
                        });
                    })
                    .then(null, function (eUnexpected) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvert post-success', eUnexpected);
                        }
                    });
            }

            if (convertOpenBtn) convertOpenBtn.addEventListener('click', function () {
                if (convertOpenBtn.disabled) return;
                openConvertModal();
            });
            if (convertCloseBtn) convertCloseBtn.addEventListener('click', closeConvertModal);
            if (convertCancelBtn) convertCancelBtn.addEventListener('click', closeConvertModal);
            if (convertSubmitBtn) convertSubmitBtn.addEventListener('click', performLrqConvert);
            if (convertOverlay) {
                convertOverlay.addEventListener('click', function (e) {
                    if (e.target === convertOverlay) closeConvertModal();
                });
            }
            // ===================== پایان مدال «تبدیل به وام» =====================

            var statusLogExport = document.getElementById('lrq-statuslog-export');
            if (statusLogExport) {
                statusLogExport.addEventListener('click', function () {
                    var rid = lrqEditCtx.requestId;
                    if (!rid) return;
                    var qIn = document.getElementById('lrq-statuslog-q');
                    var q = qIn ? String(qIn.value || '').trim() : '';
                    var u = lrqResourceUrl(rid) + '/status-logs/export' + (q ? ('?q=' + encodeURIComponent(q)) : '');
                    window.location.href = u;
                });
            }
            var statusLogQ = document.getElementById('lrq-statuslog-q');
            if (statusLogQ) {
                statusLogQ.addEventListener('input', function () {
                    if (lrqStatusLogSearchTimer) clearTimeout(lrqStatusLogSearchTimer);
                    lrqStatusLogSearchTimer = setTimeout(function () {
                        if (statusLogOverlay && !statusLogOverlay.hidden) loadStatusLogsTable();
                    }, 350);
                });
            }

            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('[data-lrq-sms-resend]');
                if (!btn) return;
                var logId = parseInt(btn.getAttribute('data-lrq-sms-resend') || '0', 10);
                if (!logId || !lrqEditCtx.requestId) return;
                var url = lrqResourceUrl(lrqEditCtx.requestId) + '/status-sms-logs/' + encodeURIComponent(String(logId)) + '/resend';
                btn.disabled = true;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (window.AdminSwal) {
                        if (data.ok && AdminSwal.success) {
                            AdminSwal.success(String(data.message || 'انجام شد.'));
                        } else if (!data.ok && AdminSwal.error) {
                            AdminSwal.error(String(data.message || 'ناموفق'));
                        }
                    }
                    if (smsLogOverlay && !smsLogOverlay.hidden) loadSmsLogsTable();
                }).catch(function () {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارسال مجدد ناموفق بود.');
                }).finally(function () {
                    btn.disabled = false;
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (smsLogOverlay && !smsLogOverlay.hidden) {
                    closeSmsLogModal();
                    e.preventDefault();
                    return;
                }
                if (statusLogOverlay && !statusLogOverlay.hidden) {
                    closeStatusLogModal();
                    e.preventDefault();
                    return;
                }
                if (sdefOverlay && !sdefOverlay.hidden) {
                    closeSdefModal();
                    e.preventDefault();
                    return;
                }
                if (editOverlay && !editOverlay.hidden) {
                    closeEditModal();
                    e.preventDefault();
                }
            });

            var _sdefCache = null;

            function renderSdefList(data) {
                _sdefCache = data;
                if (!sdefListEl) return;
                sdefListEl.innerHTML = '';
                var stages = data.stage_slots || {};
                var sms = data.sms_templates || [];
                (data.definitions || []).forEach(function (d, idx) {
                    sdefListEl.appendChild(sdefCardEl(d, idx, stages, sms, false));
                });
            }

            function sdefLockOthers(activeWrap) {
                document.querySelectorAll('[data-sdef-row]').forEach(function (w) {
                    if (w === activeWrap) return;
                    var m = w.getAttribute('data-sdef-mutable') === '1';
                    if (!m) return;
                    sdefSetRowEditing(w, false);
                });
            }

            function sdefSetRowEditing(wrap, editing) {
                var mutable = wrap.getAttribute('data-sdef-mutable') === '1';
                wrap.classList.toggle('lrq-sdef-card--editing', editing && mutable);
                wrap.classList.toggle('lrq-sdef-card--locked', !editing || !mutable);
                wrap.setAttribute('data-sdef-editing', editing && mutable ? '1' : '0');
                wrap.querySelectorAll('[data-sdef-field]').forEach(function (el) {
                    el.disabled = !mutable || !editing;
                });
                var act = wrap.querySelector('[data-sdef-action-btn]');
                if (!act) return;
                if (!mutable) {
                    act.hidden = true;
                    act.disabled = true;
                    return;
                }
                act.hidden = false;
                act.disabled = false;
                if (editing) {
                    act.setAttribute('data-sdef-mode', 'save');
                    act.className = 'lrq-sdef-btn lrq-sdef-btn--save';
                    act.title = 'ذخیره';
                    act.setAttribute('aria-label', 'ذخیره');
                    act.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                } else {
                    act.setAttribute('data-sdef-mode', 'edit');
                    act.className = 'lrq-sdef-btn lrq-sdef-btn--edit';
                    act.title = 'ویرایش';
                    act.setAttribute('aria-label', 'ویرایش');
                    act.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i>';
                }
            }

            function sdefPerformSave(wrap, d, title, stageSel, smsSel, chkDup) {
                var payload = {
                    title: title.value.trim(),
                    stage_slot: stageSel.value || null,
                    sms_template_id: smsSel.value ? parseInt(smsSel.value, 10) : null,
                    allow_duplicate_request: chkDup.checked
                };
                if (!payload.title) {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('عنوان وضعیت را وارد کنید.');
                    return;
                }
                var csrf = csrfToken();
                if (d.id) {
                    fetch(lrqStatusDefItemUrl(d.id), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        if (r.ok) return r.json();
                        return r.json().then(function (j) {
                            var msg = (j && j.message) ? j.message : 'ذخیره نشد.';
                            throw new Error(msg);
                        });
                    }).then(function () {
                        return fetch(lrqStatusDefIndex, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                    }).then(function (r) { return r.json(); }).then(function (fresh) {
                        renderSdefList(fresh);
                        refreshMainStatusSelect();
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success('ذخیره شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error(err && err.message ? err.message : 'ذخیره نشد.');
                        }
                    });
                } else {
                    fetch(lrqStatusDefStore, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        if (r.ok) return r.json();
                        return r.json().then(function (j) {
                            var msg = (j && j.message) ? j.message : 'ایجاد وضعیت ناموفق بود.';
                            throw new Error(msg);
                        });
                    }).then(function () {
                        return fetch(lrqStatusDefIndex, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                    }).then(function (r) { return r.json(); }).then(function (fresh) {
                        renderSdefList(fresh);
                        refreshMainStatusSelect();
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success('وضعیت جدید ایجاد شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error(err && err.message ? err.message : 'ایجاد وضعیت ناموفق بود.');
                        }
                    });
                }
            }

            function sdefCardEl(d, idx, stages, smsList, isNewBlank) {
                var wrap = document.createElement('div');
                wrap.className = 'lrq-sdef-card';
                wrap.setAttribute('data-sdef-row', '');
                wrap.setAttribute('data-sdef-id', d.id != null ? String(d.id) : '');
                var mutable = d.is_mutable !== false;
                wrap.setAttribute('data-sdef-mutable', mutable ? '1' : '0');

                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.value = d.id != null ? String(d.id) : '';

                var title = document.createElement('input');
                title.type = 'text';
                title.className = 'lrq-sdef-input';
                title.value = d.title || '';
                title.setAttribute('data-sdef-field', '');
                title.setAttribute('aria-label', 'عنوان وضعیت');

                var stageSel = document.createElement('select');
                stageSel.className = 'lrq-sdef-select';
                stageSel.setAttribute('data-sdef-field', '');
                stageSel.setAttribute('aria-label', 'جایگاه در ویزارد');
                var opt0 = document.createElement('option');
                opt0.value = '';
                opt0.textContent = '— انتخاب جایگاه —';
                stageSel.appendChild(opt0);
                Object.keys(stages).forEach(function (k) {
                    var o = document.createElement('option');
                    o.value = k;
                    o.textContent = stages[k];
                    stageSel.appendChild(o);
                });
                stageSel.value = d.stage_slot || '';

                var smsSel = document.createElement('select');
                smsSel.className = 'lrq-sdef-select';
                smsSel.setAttribute('data-sdef-field', '');
                smsSel.setAttribute('aria-label', 'قالب پیامک');
                var sm0 = document.createElement('option');
                sm0.value = '';
                sm0.textContent = '— قالب پیامک —';
                smsSel.appendChild(sm0);
                smsList.forEach(function (st) {
                    var o = document.createElement('option');
                    o.value = String(st.id);
                    o.textContent = st.title;
                    smsSel.appendChild(o);
                });
                if (d.sms_template_id != null && String(d.sms_template_id) !== '') {
                    smsSel.value = String(d.sms_template_id);
                } else if (smsList.length > 0 && isNewBlank) {
                    smsSel.value = String(smsList[0].id);
                }

                var lblTitle = document.createElement('label');
                lblTitle.className = 'lrq-sdef-lbl';
                lblTitle.textContent = 'عنوان وضعیت';
                var titleRow = document.createElement('div');
                titleRow.className = 'lrq-sdef-title-row';
                titleRow.appendChild(lblTitle);
                titleRow.appendChild(title);

                var lblSt = document.createElement('label');
                lblSt.className = 'lrq-sdef-lbl';
                lblSt.textContent = 'جایگاه (نمایش در ویزارد کاربر)';
                var boxSt = document.createElement('div');
                boxSt.className = 'lrq-sdef-field';
                boxSt.appendChild(lblSt);
                boxSt.appendChild(stageSel);

                var lblSms = document.createElement('label');
                lblSms.className = 'lrq-sdef-lbl';
                lblSms.textContent = 'قالب پیامک';
                var boxSms = document.createElement('div');
                boxSms.className = 'lrq-sdef-field';
                boxSms.appendChild(lblSms);
                boxSms.appendChild(smsSel);

                var fieldsRow = document.createElement('div');
                fieldsRow.className = 'lrq-sdef-fields-row';
                fieldsRow.appendChild(boxSt);
                fieldsRow.appendChild(boxSms);

                var chkMut = document.createElement('input');
                chkMut.type = 'checkbox';
                chkMut.checked = mutable;
                chkMut.disabled = true;
                var chkDup = document.createElement('input');
                chkDup.type = 'checkbox';
                chkDup.checked = !!d.allow_duplicate_request;
                chkDup.setAttribute('data-sdef-field', '');
                chkDup.setAttribute('aria-label', 'اجازه درخواست تکراری');

                var checks = document.createElement('div');
                checks.className = 'lrq-sdef-checks';
                var l1 = document.createElement('label');
                l1.appendChild(chkMut);
                l1.appendChild(document.createTextNode(' قابل تغییر و حذف؟'));
                var l2 = document.createElement('label');
                l2.appendChild(chkDup);
                l2.appendChild(document.createTextNode(' اجازه درخواست تکراری'));
                checks.appendChild(l1);
                checks.appendChild(l2);

                var actions = document.createElement('div');
                actions.className = 'lrq-sdef-actions';
                var btnAction = document.createElement('button');
                btnAction.type = 'button';
                btnAction.setAttribute('data-sdef-action-btn', '');
                var btnDel = document.createElement('button');
                btnDel.type = 'button';
                btnDel.className = 'lrq-sdef-btn lrq-sdef-btn--del';
                btnDel.title = 'حذف';
                btnDel.setAttribute('aria-label', 'حذف');
                btnDel.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
                btnDel.disabled = !mutable || !d.id;
                actions.appendChild(btnAction);
                actions.appendChild(btnDel);

                wrap.appendChild(idInput);
                wrap.appendChild(titleRow);
                if (d.code && !isNewBlank) {
                    var codeP = document.createElement('p');
                    codeP.className = 'lrq-sdef-muted';
                    codeP.style.marginTop = '0.35rem';
                    codeP.textContent = 'کلید سیستمی: ' + d.code;
                    wrap.appendChild(codeP);
                }
                wrap.appendChild(fieldsRow);
                wrap.appendChild(checks);
                wrap.appendChild(actions);

                var startEditing = isNewBlank || !d.id;
                sdefSetRowEditing(wrap, startEditing);

                btnAction.addEventListener('click', function () {
                    if (!mutable) return;
                    var mode = btnAction.getAttribute('data-sdef-mode') || 'edit';
                    if (mode === 'edit') {
                        sdefLockOthers(wrap);
                        sdefSetRowEditing(wrap, true);
                    } else {
                        sdefPerformSave(wrap, d, title, stageSel, smsSel, chkDup);
                    }
                });

                btnDel.addEventListener('click', function () {
                    if (!d.id) {
                        wrap.remove();
                        return;
                    }
                    var doDel = function () {
                        fetch(lrqStatusDefItemUrl(d.id), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        }).then(function (r) {
                            if (r.ok) return r.json();
                            return r.json().then(function (j) {
                                var msg = (j && j.message) ? j.message : 'حذف ممکن نیست.';
                                throw new Error(msg);
                            });
                        }).then(function () {
                            wrap.remove();
                            refreshMainStatusSelect();
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success('حذف شد.');
                        }).catch(function (err) {
                            if (window.AdminSwal && AdminSwal.error) {
                                AdminSwal.error(err && err.message ? err.message : 'حذف ممکن نیست.');
                            }
                        });
                    };
                    if (window.AdminSwal && AdminSwal.confirm) {
                        wrapSwalThenable(AdminSwal.confirm({
                            title: 'حذف وضعیت',
                            text: 'این وضعیت از فهرست حذف شود؟ اگر روی درخواستی استفاده شده باشد حذف انجام نمی‌شود.',
                            confirmButtonText: 'بله، حذف شود',
                            cancelButtonText: 'انصراف',
                        })).then(function (result) {
                            if (result && result.isConfirmed) doDel();
                        });
                        return;
                    }
                    if (window.confirm('حذف شود؟')) doDel();
                });

                return wrap;
            }

            function openSdefModal() {
                if (!sdefOverlay) return;
                setOverlay(true, sdefOverlay);
                sdefListEl.innerHTML = '<div class="lrq-empty">در حال بارگذاری…</div>';
                fetch(lrqStatusDefIndex, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    renderSdefList(data);
                }).catch(function () {
                    closeSdefModal();
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('بارگذاری وضعیت‌ها ناموفق بود.');
                });
            }

            if (btnOpenStatusDefs) {
                btnOpenStatusDefs.addEventListener('click', function () {
                    openSdefModal();
                });
            }
            if (sdefAddBtn) {
                sdefAddBtn.addEventListener('click', function () {
                    if (!_sdefCache) return;
                    var stages = _sdefCache.stage_slots || {};
                    var sms = _sdefCache.sms_templates || [];
                    var blank = { id: null, title: '', stage_slot: 'before_documents', sms_template_id: null, is_mutable: true, allow_duplicate_request: false };
                    sdefListEl.appendChild(sdefCardEl(blank, 0, stages, sms, true));
                });
            }
        })();

        // close the status filter popover when clicking outside or pressing Escape
        (function () {
            var details = document.getElementById('lrq-status-filter');
            if (!details) return;
            var summary = details.querySelector('summary');
            if (summary) {
                details.addEventListener('toggle', function () {
                    summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
                });
            }
            document.addEventListener('click', function (ev) {
                if (!details.open) return;
                if (details.contains(ev.target)) return;
                details.open = false;
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && details.open) {
                    details.open = false;
                    if (summary) summary.focus();
                }
            });
        })();
    </script>
@endpush
