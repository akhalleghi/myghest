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
