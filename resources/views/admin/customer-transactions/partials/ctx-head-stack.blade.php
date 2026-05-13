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
