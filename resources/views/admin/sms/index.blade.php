@extends('layouts.admin.app')

@section('title', 'مدیریت پیامک')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .sms-page { max-width: 100%; }
        .sms-title { margin: 0 0 0.9rem; font-size: 1.08rem; font-weight: 800; color: var(--text); }
        .sms-sub { margin: 0 0 0.8rem; font-size: 0.84rem; color: var(--muted); }
        .sms-tabs { display: flex; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .sms-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.75rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); cursor: pointer; font-family: inherit; }
        .sms-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); }
        .sms-tab.is-disabled { opacity: 0.55; cursor: not-allowed; }
        .sms-tab-panel[hidden] { display: none !important; }
        .sms-credit-layout {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
            align-items: stretch;
        }
        .sms-credit-card,
        .sms-credit-token-card {
            border: 1px solid var(--border);
            border-radius: 0.95rem;
            padding: 1rem 1.05rem 1.1rem;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .sms-credit-card {
            background: linear-gradient(165deg, color-mix(in oklab, var(--primary-soft) 62%, var(--bg-card)), var(--bg-card));
        }
        .sms-credit-token-card {
            background: linear-gradient(165deg, color-mix(in oklab, var(--bg-card) 88%, var(--primary-soft)), var(--bg-card));
            margin-top: 0;
        }
        html[data-theme="dark"] .sms-credit-card,
        html[data-theme="dark"] .sms-credit-token-card { box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28); }
        .sms-credit-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.85rem; }
        .sms-credit-title-wrap { display: flex; align-items: flex-start; gap: 0.65rem; min-width: 0; }
        .sms-credit-icon {
            width: 2.35rem; height: 2.35rem; border-radius: 0.7rem; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--primary-soft); color: var(--primary-dark);
        }
        .sms-credit-title { margin: 0; font-size: 0.92rem; font-weight: 900; color: var(--text); }
        .sms-credit-sub { margin: 0.28rem 0 0; font-size: 0.74rem; color: var(--muted); line-height: 1.65; }
        .sms-credit-refresh {
            border: 1px solid rgba(37, 99, 235, 0.35); border-radius: 0.62rem; padding: 0.45rem 0.8rem;
            background: var(--primary-soft); color: var(--primary-dark); font-size: 0.76rem; font-weight: 800;
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .sms-credit-refresh:disabled { opacity: 0.55; cursor: not-allowed; }
        .sms-credit-amount-box {
            border: 1px dashed rgba(37, 99, 235, 0.28); border-radius: 0.85rem;
            background: color-mix(in oklab, var(--bg-card) 88%, var(--primary-soft));
            padding: 1rem 0.95rem; text-align: center; flex: 1;
            display: flex; flex-direction: column; justify-content: center;
        }
        .sms-credit-amount-label { margin: 0 0 0.35rem; font-size: 0.74rem; font-weight: 700; color: var(--muted); }
        .sms-credit-amount {
            margin: 0; font-size: clamp(1.35rem, 3.2vw, 1.85rem); font-weight: 900; color: var(--primary-dark);
            letter-spacing: 0.01em; line-height: 1.35; direction: ltr; unicode-bidi: isolate;
        }
        .sms-credit-unit { margin: 0.35rem 0 0; font-size: 0.78rem; font-weight: 800; color: var(--text); }
        .sms-credit-meta { margin: 0.75rem 0 0; font-size: 0.72rem; color: var(--muted); line-height: 1.6; }
        .sms-credit-status { margin: 0.7rem 0 0; font-size: 0.76rem; font-weight: 700; line-height: 1.6; min-height: 1.2rem; }
        .sms-credit-status.is-ok { color: #047857; }
        .sms-credit-status.is-err { color: #b91c1c; }
        .sms-credit-status.is-loading { color: var(--muted); }
        .sms-credit-token-head { margin: 0 0 0.2rem; font-size: 0.92rem; font-weight: 900; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
        .sms-credit-token-sub { margin: 0.28rem 0 0.65rem; font-size: 0.74rem; color: var(--muted); line-height: 1.65; }
        .sms-credit-token-state {
            display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; font-weight: 800;
            border-radius: 999px; padding: 0.22rem 0.55rem; margin-bottom: 0.65rem; width: fit-content;
        }
        .sms-credit-token-state.is-on { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-credit-token-state.is-off { background: rgba(245, 158, 11, 0.16); color: #b45309; }
        .sms-credit-token-form { display: flex; flex-direction: column; gap: 0.55rem; margin-top: auto; }
        .sms-credit-token-field { width: 100%; min-width: 0; }
        .sms-credit-token-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-credit-token-field input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.5rem 0.62rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem;
        }
        .sms-credit-token-submit {
            border: none; border-radius: 0.62rem; padding: 0.52rem 1rem; align-self: start;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.78rem; font-weight: 700; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        @media (max-width: 920px) {
            .sms-credit-layout { grid-template-columns: 1fr; }
        }
        .sms-free-card {
            border: 1px solid var(--border);
            border-radius: 0.95rem;
            background: linear-gradient(165deg, color-mix(in oklab, var(--primary-soft) 55%, var(--bg-card)), var(--bg-card));
            padding: 1rem 1.05rem 1.1rem;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            max-width: 46rem;
        }
        html[data-theme="dark"] .sms-free-card { box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28); }
        .sms-free-head { display: flex; align-items: flex-start; gap: 0.65rem; margin-bottom: 0.75rem; }
        .sms-free-title { margin: 0; font-size: 0.92rem; font-weight: 900; color: var(--text); }
        .sms-free-sub { margin: 0.28rem 0 0; font-size: 0.74rem; color: var(--muted); line-height: 1.65; }
        .sms-free-form { display: grid; gap: 0.7rem; }
        .sms-free-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-free-field textarea {
            width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.55rem 0.65rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; line-height: 1.65; resize: vertical;
        }
        .sms-free-field textarea[name="recipients"] { min-height: 6.2rem; direction: ltr; text-align: left; }
        .sms-free-field textarea[name="message"] { min-height: 7.2rem; }
        .sms-free-hint { margin: 0.28rem 0 0; font-size: 0.7rem; color: var(--muted); line-height: 1.55; }
        .sms-free-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
        .sms-free-submit {
            border: none; border-radius: 0.62rem; padding: 0.52rem 1.05rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.78rem; font-weight: 800; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .sms-panel-select-card { border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); padding: 0.75rem 0.85rem; margin-bottom: 0.8rem; }
        .sms-panel-select-head { font-size: 0.8rem; font-weight: 800; color: var(--text); margin-bottom: 0.2rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-panel-select-sub { margin: 0 0 0.55rem; color: var(--muted); font-size: 0.74rem; }
        .sms-panel-select-field { max-width: 22rem; width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.5rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-conn-badge { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.74rem; font-weight: 800; border-radius: 999px; padding: 0.25rem 0.56rem; margin-bottom: 0.5rem; }
        .sms-conn-badge--connected { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-conn-badge--disconnected { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
        .sms-conn-badge--not-configured { background: rgba(148, 163, 184, 0.18); color: #475569; }
        .sms-settings-form { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: end; }
        .sms-settings-field { min-width: 14rem; flex: 1 1 16rem; }
        .sms-settings-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-settings-field input, .sms-settings-field select { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.5rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-field-error { margin-top: 0.22rem; font-size: 0.72rem; color: #b91c1c; font-weight: 700; }
        .sms-settings-submit { border: none; border-radius: 0.62rem; padding: 0.52rem 1rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
        .sms-settings-note { margin: 0 0 0.6rem; font-size: 0.74rem; color: var(--muted); }
        .sms-toggle-row { min-width: 100%; padding: 0.46rem 0.55rem; border: 1px dashed var(--border); border-radius: 0.62rem; background: color-mix(in oklab, var(--bg-card) 88%, var(--primary-soft)); }
        .sms-toggle-label { display: inline-flex; align-items: center; gap: 0.45rem; margin: 0; font-size: 0.79rem; font-weight: 700; color: var(--text); cursor: pointer; }
        .sms-toggle-label input[type="checkbox"] { width: 1rem; height: 1rem; accent-color: var(--primary); }
        .sms-reminder-grid { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap: 0.65rem; }
        .sms-reminder-section { border: 1px solid var(--border); border-radius: 0.72rem; background: var(--bg-card); padding: 0.58rem; display: grid; gap: 0.52rem; min-width: 0; }
        .sms-reminder-section-title { margin: 0; font-size: 0.76rem; font-weight: 800; color: var(--text); display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-reminder-section-sub { margin: -0.2rem 0 0.1rem; font-size: 0.71rem; color: var(--muted); line-height: 1.55; }
        .sms-reminder-hidden { display: none !important; }
        .sms-reminder-full { grid-column: 1 / -1; }
        .sms-timepicker-row { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
        .sms-timepicker-row select { width: auto; min-width: 5.2rem; }
        .sms-timepicker-sep { font-weight: 800; color: var(--muted); }
        @media (max-width: 860px) {
            .sms-reminder-grid { grid-template-columns: 1fr; }
        }
        .sms-template-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .sms-template-toolbar-note { margin: 0; font-size: 0.75rem; color: var(--muted); }
        .sms-template-add-btn { border: none; border-radius: 0.62rem; padding: 0.5rem 0.9rem; background: linear-gradient(180deg, #2563eb, #1d4ed8); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.38rem; }
        .sms-template-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 0.85rem; }
        .sms-template-item { border: 1px solid var(--border); border-radius: 0.9rem; background: var(--bg-card); padding: 0.78rem 0.82rem; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05); transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease; display: flex; flex-direction: column; min-height: 100%; }
        .sms-template-item:hover { transform: translateY(-2px); border-color: rgba(37, 99, 235, 0.35); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1); }
        html[data-theme="dark"] .sms-template-item { box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22); }
        html[data-theme="dark"] .sms-template-item:hover { box-shadow: 0 12px 28px rgba(0, 0, 0, 0.34); border-color: rgba(96, 165, 250, 0.42); }
        .sms-template-item-head { display: flex; justify-content: space-between; align-items: center; gap: 0.45rem; margin-bottom: 0.25rem; }
        .sms-template-item-title { margin: 0; font-size: 0.83rem; font-weight: 800; color: var(--text); line-height: 1.45; }
        .sms-template-item-meta { margin: 0 0 0.48rem; font-size: 0.72rem; color: var(--muted); }
        .sms-template-system-badge { display: inline-flex; align-items: center; gap: 0.24rem; border-radius: 999px; padding: 0.18rem 0.46rem; font-size: 0.67rem; font-weight: 800; color: #0369a1; background: rgba(14, 165, 233, 0.14); margin-inline-start: 0.3rem; }
        .sms-template-item-body-wrap { background: var(--primary-soft); border-radius: 0.7rem; padding: 0.52rem 0.58rem; border: 1px dashed rgba(148, 163, 184, 0.35); flex: 1; }
        .sms-template-item-body { margin: 0; font-size: 0.75rem; color: var(--text); line-height: 1.75; white-space: pre-wrap; word-break: break-word; }
        .sms-template-item-actions { margin-top: 0.62rem; display: flex; gap: 0.4rem; }
        .sms-template-action-btn { border: 1px solid var(--border); border-radius: 0.55rem; padding: 0.34rem 0.62rem; font-size: 0.72rem; font-weight: 700; background: var(--bg-card); color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none; transition: background 0.12s ease, border-color 0.12s ease; }
        .sms-template-action-btn:hover { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); }
        .sms-template-action-btn--danger { color: #b91c1c; border-color: rgba(239, 68, 68, 0.32); }
        .sms-template-action-btn--danger:hover { background: rgba(248, 113, 113, 0.14); border-color: rgba(239, 68, 68, 0.4); }
        .sms-template-empty { border: 1px dashed var(--border); border-radius: 0.75rem; padding: 1rem; font-size: 0.8rem; color: var(--muted); text-align: center; background: var(--bg-card); }
        .sms-template-modal-overlay { position: fixed; inset: 0; z-index: 1300; background: rgba(15, 23, 42, 0.54); display: grid; place-items: center; padding: 0.9rem; }
        .sms-template-modal-overlay[hidden] { display: none !important; }
        .sms-template-modal { width: min(860px, 100%); max-height: min(88vh, 760px); overflow: auto; border: 1px solid var(--border); border-radius: 1rem; background: var(--bg-card); box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24); }
        .sms-template-modal-head { padding: 0.8rem 0.95rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; }
        .sms-template-modal-title { margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--text); }
        .sms-template-close-btn { width: 2rem; height: 2rem; border: 0; border-radius: 0.55rem; background: var(--primary-soft); color: var(--primary-dark); cursor: pointer; }
        .sms-template-modal-body { padding: 0.9rem 0.95rem 1rem; }
        .sms-template-form { display: grid; gap: 0.7rem; }
        .sms-template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
        .sms-template-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-template-field input, .sms-template-field select, .sms-template-field textarea { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.52rem 0.64rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-template-field textarea { min-height: 8.8rem; resize: vertical; }
        .sms-patterns-label { font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-patterns { border: 1px dashed var(--border); border-radius: 0.65rem; padding: 0.5rem; display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .sms-pattern-chip { border: 1px solid rgba(124, 58, 237, 0.28); border-radius: 999px; padding: 0.24rem 0.55rem; font-size: 0.72rem; font-weight: 700; color: #7c3aed; background: rgba(124, 58, 237, 0.11); cursor: pointer; }
        .sms-template-preview { border: 1px solid var(--border); border-radius: 0.62rem; background: var(--bg-card); padding: 0.6rem 0.65rem; min-height: 4.5rem; font-size: 0.78rem; line-height: 1.7; white-space: pre-wrap; color: var(--text); }
        .sms-template-submit { justify-self: start; border: none; border-radius: 0.62rem; padding: 0.52rem 1rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
        @media (max-width: 760px) {
            .sms-template-grid { grid-template-columns: 1fr; }
        }

        .sms-filters { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .sms-statuses { display: inline-flex; gap: 0.45rem; flex-wrap: wrap; }
        .sms-status { border: 1px solid var(--border); border-radius: 999px; padding: 0.38rem 0.65rem; font-size: 0.75rem; font-weight: 700; color: var(--muted); text-decoration: none; background: var(--bg-card); }
        .sms-status.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .sms-search { min-width: min(100%, 19rem); flex: 1 1 16rem; max-width: 25rem; }
        .sms-search form { display: flex; gap: 0.45rem; }
        .sms-search input { width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.5rem 0.7rem; font-size: 0.84rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .sms-search button { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.7rem; background: var(--bg-card); color: var(--text); cursor: pointer; }
        .sms-export-btn { border: 1px solid rgba(22, 163, 74, 0.38); border-radius: 0.65rem; padding: 0.48rem 0.72rem; background: rgba(34, 197, 94, 0.14); color: #166534; cursor: pointer; font-size: 0.78rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; }
        .sms-export-btn:hover { background: rgba(34, 197, 94, 0.22); }
        html[data-theme="dark"] .sms-export-btn { color: #86efac; border-color: rgba(74, 222, 128, 0.4); background: rgba(22, 101, 52, 0.34); }

        .sms-date-toolbar { border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.7rem 0.75rem; margin-bottom: 0.75rem; background: var(--bg-card); display: flex; flex-direction: column; gap: 0.65rem; }
        .sms-day-nav { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; justify-content: center; }
        .sms-day-btn { text-decoration: none; border: 1px solid var(--border); border-radius: 0.6rem; font-size: 0.78rem; font-weight: 700; padding: 0.42rem 0.7rem; color: var(--text); background: var(--bg-card); }
        .sms-day-current { border: 1px dashed var(--border); border-radius: 0.6rem; padding: 0.42rem 0.7rem; min-width: 9.7rem; text-align: center; font-size: 0.83rem; font-weight: 700; color: var(--text); background: var(--primary-soft); }
        .sms-range-toggle { text-align: center; }
        .sms-range-toggle button { border: 1px solid var(--border); border-radius: 0.6rem; font-size: 0.78rem; font-weight: 700; padding: 0.42rem 0.7rem; background: var(--bg-card); color: var(--text); cursor: pointer; }
        .sms-range-panel { border-top: 1px solid var(--border); padding-top: 0.65rem; }
        .sms-range-panel[hidden] { display: none !important; }
        .sms-range-form { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: end; justify-content: center; }
        .sms-range-field { min-width: 10rem; }
        .sms-range-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.2rem; }
        .sms-range-field input { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .sms-range-form button { border: none; border-radius: 0.62rem; padding: 0.52rem 0.9rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }

        .sms-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.9rem; overflow: visible; }
        .sms-table-wrap { overflow-x: auto; overflow-y: visible; }
        .sms-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .sms-table th, .sms-table td { padding: 0.58rem 0.72rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .sms-table th { white-space: nowrap; background: var(--primary-soft); color: var(--text); font-weight: 800; }
        .sms-msg { max-width: 22rem; line-height: 1.6; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sms-badge { display: inline-block; padding: 0.16rem 0.45rem; border-radius: 0.35rem; font-size: 0.71rem; font-weight: 700; }
        .sms-badge--pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .sms-badge--delivered { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-badge--undelivered { background: rgba(248, 113, 113, 0.2); color: #b91c1c; }
        .sms-action-btn { border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.3rem 0.52rem; font-size: 0.72rem; font-weight: 700; color: var(--text); background: var(--bg-card); cursor: pointer; }
        .sms-actions { position: relative; display: inline-block; }
        .sms-actions-menu { position: fixed; min-width: 9.6rem; z-index: 1500; border: 1px solid var(--border); border-radius: 0.6rem; background: var(--bg-card); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); padding: 0.28rem; }
        .sms-actions-menu[hidden] { display: none !important; }
        .sms-actions-item { width: 100%; text-align: start; border: 0; border-radius: 0.45rem; padding: 0.42rem 0.5rem; font-family: inherit; font-size: 0.74rem; font-weight: 700; background: transparent; color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-actions-item:hover { background: var(--primary-soft); }
        .sms-actions-item--danger { color: #b91c1c; }
        .sms-actions-item--danger:hover { background: rgba(248, 113, 113, 0.14); }
        .sms-empty { text-align: center; padding: 1.25rem; color: var(--muted); font-size: 0.84rem; }
        .sms-pagination { padding: 0.65rem 0.8rem; }
        .sms-notify-row { width: 100%; display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; }
        .sms-notify-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
        .sms-notify-pick-btn { border: 1px solid rgba(37, 99, 235, 0.35); border-radius: 0.62rem; padding: 0.45rem 0.8rem; background: var(--primary-soft); color: var(--primary-dark); font-size: 0.76rem; font-weight: 800; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-notify-pick-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .sms-notify-message { width: 100%; min-height: 5.5rem; resize: vertical; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.55rem 0.65rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; line-height: 1.65; }
        .sms-notify-preview { width: 100%; border: 1px dashed var(--border); border-radius: 0.62rem; padding: 0.55rem 0.65rem; background: color-mix(in oklab, var(--bg-card) 90%, var(--primary-soft)); font-size: 0.78rem; line-height: 1.7; color: var(--text); white-space: pre-wrap; }
        .sms-notify-patterns { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.35rem; }
        .sms-notify-pattern { border: 1px solid rgba(124, 58, 237, 0.28); border-radius: 999px; padding: 0.2rem 0.5rem; font-size: 0.7rem; font-weight: 700; color: #7c3aed; background: rgba(124, 58, 237, 0.1); cursor: pointer; font-family: inherit; }
        .sms-notify-fields.sms-reminder-hidden { display: none !important; }
        .sms-notify-hub { display: grid; gap: 0.85rem; margin-bottom: 0.85rem; }
        .sms-notify-hub__head { border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.75rem 0.9rem; background: linear-gradient(180deg, color-mix(in oklab, var(--primary-soft) 55%, var(--bg-card)), var(--bg-card)); }
        .sms-notify-hub__title { margin: 0; font-size: 0.92rem; font-weight: 900; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
        .sms-notify-hub__lead { margin: 0.35rem 0 0; font-size: 0.74rem; color: var(--muted); line-height: 1.65; }
        .sms-notify-block { border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); overflow: hidden; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
        .sms-notify-block.is-on { border-color: rgba(37, 99, 235, 0.38); box-shadow: 0 8px 22px rgba(37, 99, 235, 0.08); }
        html[data-theme="dark"] .sms-notify-block.is-on { box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28); }
        .sms-notify-block__head { display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.75rem 0.9rem; border-bottom: 1px solid var(--border); background: color-mix(in oklab, var(--bg-card) 92%, var(--primary-soft)); }
        .sms-notify-block__icon { width: 2.1rem; height: 2.1rem; border-radius: 0.62rem; display: inline-flex; align-items: center; justify-content: center; background: var(--primary-soft); color: var(--primary-dark); flex-shrink: 0; }
        .sms-notify-block__icon--self { background: rgba(16, 185, 129, 0.14); color: #047857; }
        html[data-theme="dark"] .sms-notify-block__icon--self { color: #6ee7b7; }
        .sms-notify-block__icon--customer { background: rgba(245, 158, 11, 0.16); color: #b45309; }
        html[data-theme="dark"] .sms-notify-block__icon--customer { color: #fcd34d; }
        .sms-notify-block__icon--installment { background: rgba(99, 102, 241, 0.14); color: #4338ca; }
        html[data-theme="dark"] .sms-notify-block__icon--installment { color: #a5b4fc; }
        .sms-notify-block__icon--settlement { background: rgba(14, 165, 233, 0.14); color: #0369a1; }
        html[data-theme="dark"] .sms-notify-block__icon--settlement { color: #7dd3fc; }
        .sms-notify-block__icon--deposit { background: rgba(20, 184, 166, 0.14); color: #0f766e; }
        html[data-theme="dark"] .sms-notify-block__icon--deposit { color: #5eead4; }
        .sms-notify-block__icon--ticket { background: rgba(236, 72, 153, 0.12); color: #be185d; }
        html[data-theme="dark"] .sms-notify-block__icon--ticket { color: #f9a8d4; }
        .sms-notify-block__icon--loan-request { background: rgba(139, 92, 246, 0.14); color: #6d28d9; }
        html[data-theme="dark"] .sms-notify-block__icon--loan-request { color: #c4b5fd; }
        .sms-notify-block__name { margin: 0; font-size: 0.84rem; font-weight: 800; color: var(--text); }
        .sms-notify-block__desc { margin: 0.2rem 0 0; font-size: 0.72rem; color: var(--muted); line-height: 1.55; }
        .sms-notify-block__form { display: flex; flex-direction: column; gap: 0.65rem; padding: 0.75rem 0.9rem 0.85rem; }
        .sms-notify-block__toggle { margin: 0; }
        .sms-notify-block__body { display: grid; gap: 0.65rem; padding-top: 0.15rem; }
        .sms-notify-block__foot { padding-top: 0.35rem; border-top: 1px dashed var(--border); }
        .sms-notify-preview-label { margin: 0.45rem 0 0.25rem; font-size: 0.72rem; font-weight: 700; color: var(--muted); }
        .sms-notify-message-field { min-width: 100%; margin: 0; }
        .sms-mini-modal-overlay { position: fixed; inset: 0; z-index: 1400; background: rgba(15, 23, 42, 0.52); display: grid; place-items: center; padding: 0.75rem; }
        .sms-mini-modal-overlay[hidden] { display: none !important; }
        .sms-mini-modal { width: min(640px, 100%); max-height: min(86vh, 720px); overflow: auto; border: 1px solid var(--border); border-radius: 0.95rem; background: var(--bg-card); box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22); }
        .sms-mini-modal--wide { width: min(760px, 100%); }
        .sms-mini-modal-head { padding: 0.72rem 0.85rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .sms-mini-modal-title { margin: 0; font-size: 0.88rem; font-weight: 800; color: var(--text); }
        .sms-mini-modal-close { width: 2rem; height: 2rem; border: 0; border-radius: 0.55rem; background: var(--primary-soft); color: var(--primary-dark); cursor: pointer; }
        .sms-mini-modal-body { padding: 0.75rem 0.85rem 0.9rem; }
        .sms-mini-modal-foot { padding: 0.65rem 0.85rem; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 0.45rem; justify-content: flex-end; }
        .sms-mini-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .sms-mini-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 28rem; }
        .sms-mini-table th, .sms-mini-table td { padding: 0.5rem 0.62rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .sms-mini-table th { background: var(--primary-soft); font-weight: 800; white-space: nowrap; }
        .sms-mini-table td { color: var(--muted); font-weight: 600; }
        .sms-mini-empty { text-align: center; padding: 0.85rem; color: var(--muted); font-size: 0.78rem; }
        .sms-mini-btn { border: 1px solid var(--border); border-radius: 0.58rem; padding: 0.42rem 0.75rem; font-size: 0.76rem; font-weight: 700; cursor: pointer; font-family: inherit; background: var(--bg-card); color: var(--text); }
        .sms-mini-btn--pri { border: none; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; }
        .sms-mini-btn--danger { color: #b91c1c; border-color: rgba(239, 68, 68, 0.35); }
        .sms-picker-toolbar { display: flex; flex-wrap: wrap; gap: 0.45rem 0.65rem; align-items: center; justify-content: space-between; margin-bottom: 0.55rem; }
        .sms-picker-search { flex: 1 1 14rem; display: flex; align-items: center; gap: 0.45rem; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.38rem 0.55rem; background: var(--bg-card); }
        .sms-picker-search i { color: var(--muted); font-size: 0.82rem; }
        .sms-picker-search input { flex: 1; min-width: 0; border: 0; background: transparent; color: var(--text); font-family: inherit; font-size: 0.8rem; outline: none; }
        .sms-picker-count { margin: 0; font-size: 0.72rem; font-weight: 700; color: var(--muted); white-space: nowrap; }
        .sms-picker-list { display: grid; gap: 0.4rem; max-height: min(52vh, 420px); overflow: auto; padding-inline-end: 0.15rem; }
        .sms-picker-item { display: grid; grid-template-columns: auto 1fr auto; gap: 0.55rem 0.65rem; align-items: center; border: 1px solid var(--border); border-radius: 0.72rem; padding: 0.55rem 0.65rem; cursor: pointer; background: var(--bg-card); transition: border-color 0.12s ease, background 0.12s ease; }
        .sms-picker-item:hover { border-color: rgba(37, 99, 235, 0.35); background: var(--primary-soft); }
        .sms-picker-item.is-selected { border-color: rgba(37, 99, 235, 0.45); background: color-mix(in oklab, var(--primary-soft) 70%, var(--bg-card)); }
        .sms-picker-item.is-hidden { display: none !important; }
        .sms-picker-item input { width: 1rem; height: 1rem; accent-color: var(--primary); }
        .sms-picker-item__name { margin: 0; font-size: 0.8rem; font-weight: 800; color: var(--text); line-height: 1.45; }
        .sms-picker-item__username { margin: 0.1rem 0 0; font-size: 0.72rem; color: var(--muted); font-weight: 600; }
        .sms-picker-item__mobile { margin: 0; font-size: 0.74rem; font-weight: 700; color: var(--text); direction: ltr; text-align: end; white-space: nowrap; }
        .sms-picker-item__badge { font-size: 0.66rem; font-weight: 800; border-radius: 999px; padding: 0.16rem 0.45rem; white-space: nowrap; }
        .sms-picker-item__badge--ok { background: rgba(16, 185, 129, 0.14); color: #047857; }
        .sms-picker-item__badge--bad { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
        .sms-recipient-dup-hint { display: block; margin-top: 0.15rem; font-size: 0.68rem; font-weight: 700; color: #b45309; }
        @media (max-width: 640px) {
            .sms-mini-modal { width: 100%; border-radius: 0.85rem; }
        }
    </style>
@endpush

@section('content')
    @php
        $smsAllowedTabs = $smsAllowedTabs ?? [];
        $smsUiFeatures = $smsUiFeatures ?? [];
        $smsActiveTab = $smsActiveTab ?? array_key_first($smsAllowedTabs);
        $smsFeat = static fn (string $key): bool => ! empty($smsUiFeatures[$key]);
    @endphp
    <div class="sms-page">
        <h1 class="sms-title">مدیریت پیامک</h1>
        @php
            $smsTabLabels = array_values($smsAllowedTabs);
            $smsPageSubtitle = count($smsAllowedTabs) === 1 && isset($smsAllowedTabs['settings'])
                ? 'تنظیمات اتصال پنل، تست ارسال و سایر گزینه‌های پیامکی که به آن‌ها دسترسی دارید.'
                : (count($smsAllowedTabs) === 1 && isset($smsAllowedTabs['templates'])
                    ? 'مدیریت الگوهای پیامک مطابق دسترسی‌های تعریف‌شده برای شما.'
                    : (count($smsAllowedTabs) === 1 && isset($smsAllowedTabs['credit'])
                        ? 'مشاهده اعتبار باقیمانده پنل پیامک فعال سامانه.'
                        : (count($smsAllowedTabs) === 1 && isset($smsAllowedTabs['free_send'])
                            ? 'ارسال پیامک آزاد به یک یا چند شماره موبایل (ضامن، کارت ویزیت و سایر افراد خارج از لیست مشتریان اقساطی).'
                            : (count($smsAllowedTabs) === 1 && isset($smsAllowedTabs['reports'])
                                ? 'گزارش ارسال پیامک‌ها، جستجو و فیلتر وضعیت، و بازهٔ زمانی روزانه/دلخواه.'
                                : 'بخش‌های پیامکی که به آن‌ها دسترسی دارید: '.implode('، ', $smsTabLabels).'.'))));
        @endphp
        <p class="sms-sub">{{ $smsPageSubtitle }}</p>

        @include('admin.sms.partials.tabs-nav')

        @if(isset($smsAllowedTabs['reports']))
        <section class="sms-tab-panel" data-sms-panel="reports" @if($smsActiveTab !== 'reports') hidden @endif>
        <div class="sms-date-toolbar">
            <div class="sms-day-nav">
                <a class="sms-day-btn" href="{{ request()->fullUrlWithQuery(['mode' => 'day', 'date' => $prevDate]) }}">روز قبل</a>
                <div class="sms-day-current">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($selectedDateJalali) }}</div>
                <a class="sms-day-btn" href="{{ request()->fullUrlWithQuery(['mode' => 'day', 'date' => $nextDate]) }}">روز بعد</a>
            </div>
            <div class="sms-range-toggle">
                <button type="button" id="sms-toggle-range">انتخاب بازه زمانی</button>
            </div>
            <div class="sms-range-panel" id="sms-range-panel" @if (! $isRangeMode) hidden @endif>
                <form method="get" class="sms-range-form">
                    <input type="hidden" name="mode" value="range">
                    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    <div class="sms-range-field">
                        <label for="sms-from-jdate">از تاریخ</label>
                        <input id="sms-from-jdate" name="from_jdate" type="text" value="{{ $fromJDate }}" autocomplete="off">
                    </div>
                    <div class="sms-range-field">
                        <label for="sms-to-jdate">تا تاریخ</label>
                        <input id="sms-to-jdate" name="to_jdate" type="text" value="{{ $toJDate }}" autocomplete="off">
                    </div>
                    <button type="submit">اعمال</button>
                </form>
            </div>
        </div>

        <div class="sms-filters">
            <div class="sms-statuses">
                <a class="sms-status @if($status === '') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => null]) }}">همه</a>
                <a class="sms-status @if($status === 'pending') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'pending']) }}">در انتظارها</a>
                <a class="sms-status @if($status === 'delivered') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'delivered']) }}">تحویل شده‌ها</a>
                <a class="sms-status @if($status === 'undelivered') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'undelivered']) }}">تحویل نشده‌ها</a>
            </div>
            <div class="sms-search">
                <form method="get">
                    <input type="hidden" name="mode" value="{{ $isRangeMode ? 'range' : 'day' }}">
                    @if ($isRangeMode)
                        <input type="hidden" name="from_jdate" value="{{ $fromJDate }}">
                        <input type="hidden" name="to_jdate" value="{{ $toJDate }}">
                    @else
                        <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    @endif
                    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="جستجو در متن، دریافت‌کننده، نوع یا پنل...">
                    <button type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                    @if($smsFeat('reports.export'))
                    <a
                        class="sms-export-btn"
                        href="{{ route('admin.sms.export-excel', request()->query()) }}"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
                    @endif
                </form>
            </div>
        </div>

        <div class="sms-card">
            <div class="sms-table-wrap">
                <table class="sms-table">
                    <thead>
                        <tr>
                            <th>پنل پیامک</th>
                            <th>وضعیت</th>
                            <th>زمان ارسال</th>
                            <th>متن</th>
                            <th>دریافت کننده</th>
                            <th>نوع</th>
                            <th>عملیات</th>
                            <th>هزینه</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $statusClass = match ($log->status) {
                                    \App\Models\SmsLog::STATUS_PENDING => 'sms-badge--pending',
                                    \App\Models\SmsLog::STATUS_DELIVERED => 'sms-badge--delivered',
                                    default => 'sms-badge--undelivered',
                                };
                                $sentAt = $log->sent_at ? jalali($log->sent_at)->format('Y/m/d H:i') : '—';
                                $typeLabel = match ((string) $log->type) {
                                    'admin-free-send' => 'ارسال آزاد',
                                    'panel-test' => 'تست پنل',
                                    default => (string) $log->type,
                                };
                            @endphp
                            <tr>
                                <td>{{ $log->sms_panel }}</td>
                                <td><span class="sms-badge {{ $statusClass }}">{{ $log->statusLabel() }}</span></td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($sentAt) }}</td>
                                <td><div class="sms-msg" title="{{ $log->message_text }}">{{ $log->message_text }}</div></td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($log->recipient) }}</td>
                                <td>{{ $typeLabel }}</td>
                                <td>
                                    <div class="sms-actions" data-sms-actions>
                                        <button type="button" class="sms-action-btn" data-sms-actions-toggle>
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            عملیات
                                        </button>
                                        <div class="sms-actions-menu" data-sms-actions-menu hidden>
                                            <button
                                                type="button"
                                                class="sms-actions-item"
                                                data-sms-view-detail
                                                data-message="{{ $log->message_text }}"
                                                data-recipient="{{ $log->recipient }}"
                                                data-type="{{ $log->type }}"
                                                data-status="{{ $log->statusLabel() }}"
                                            >
                                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                                جزئیات
                                            </button>
                                            @if($smsFeat('reports.destroy'))
                                            <form method="post" action="{{ route('admin.sms.destroy', $log) }}" data-sms-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sms-actions-item sms-actions-item--danger">
                                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                                    حذف
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((float) $log->cost, 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="sms-empty">پیامکی در بازه انتخابی یافت نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.list-pagination', ['paginator' => $logs])
        </div>
        </section>
        @endif

        @if(isset($smsAllowedTabs['free_send']))
        <section class="sms-tab-panel" data-sms-panel="free_send" @if($smsActiveTab !== 'free_send') hidden @endif>
            <div class="sms-free-card">
                <div class="sms-free-head">
                    <span class="sms-credit-icon" aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></span>
                    <div>
                        <h2 class="sms-free-title">ارسال آزاد پیامک</h2>
                        <p class="sms-free-sub">
                            ارسال پیام به یک یا چند شماره موبایل — مناسب ضامن، کارت ویزیت و افرادی که لزوماً مشتری اقساطی سامانه نیستند.
                            ارسال از طریق پنل پیامک فعال سامانه انجام می‌شود و در گزارش پیامک‌ها ثبت می‌گردد.
                        </p>
                    </div>
                </div>

                @if($smsFeat('free_send.send'))
                <form method="post" action="{{ route('admin.sms.free-send') }}" class="sms-free-form" autocomplete="off">
                    @csrf
                    <div class="sms-free-field">
                        <label for="sms-free-recipients">شماره‌های گیرنده <span class="req" style="color:#b91c1c;">*</span></label>
                        <textarea
                            id="sms-free-recipients"
                            name="recipients"
                            maxlength="4000"
                            placeholder="مثال:&#10;09121234567&#10;09351234567&#10;یا با کاما جدا کنید"
                        >{{ old('recipients') }}</textarea>
                        <p class="sms-free-hint">هر خط یک شماره، یا جدا با کاما/فاصله. حداکثر ۳۰ شماره معتبر در هر ارسال. فرمت: ۰۹xxxxxxxxx</p>
                        @error('recipients')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-free-field">
                        <label for="sms-free-message">متن پیامک <span class="req" style="color:#b91c1c;">*</span></label>
                        <textarea
                            id="sms-free-message"
                            name="message"
                            maxlength="700"
                            placeholder="متن پیام را وارد کنید…"
                        >{{ old('message') }}</textarea>
                        <p class="sms-free-hint">حداکثر ۷۰۰ کاراکتر. همین متن برای همه گیرنده‌ها ارسال می‌شود.</p>
                        @error('message')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-free-actions">
                        <button type="submit" class="sms-free-submit">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            ارسال پیامک
                        </button>
                    </div>
                </form>
                @else
                    <div class="sms-template-empty">شما به ارسال آزاد پیامک دسترسی ندارید.</div>
                @endif
            </div>
        </section>
        @endif

        @if(isset($smsAllowedTabs['credit']))
        @php
            $smsPanelApiTokenStatus = $smsPanelApiTokenStatus ?? ['configured' => false, 'hint' => ''];
        @endphp
        <section class="sms-tab-panel" data-sms-panel="credit" @if($smsActiveTab !== 'credit') hidden @endif>
            <div class="sms-credit-layout">
            <div class="sms-credit-card" id="sms-credit-card">
                <div class="sms-credit-head">
                    <div class="sms-credit-title-wrap">
                        <span class="sms-credit-icon" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
                        <div>
                            <h2 class="sms-credit-title">اعتبار باقیمانده پنل پیامک</h2>
                            <p class="sms-credit-sub">موجودی پنل فعال سپاهان‌گستر با توکن WebAPI ساخته‌شده در پنل پیامک استعلام می‌شود.</p>
                        </div>
                    </div>
                    @if($smsFeat('credit.view'))
                    <button type="button" class="sms-credit-refresh" id="sms-credit-refresh">
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        به‌روزرسانی
                    </button>
                    @endif
                </div>
                <div class="sms-credit-amount-box">
                    <p class="sms-credit-amount-label">موجودی فعلی</p>
                    <p class="sms-credit-amount" id="sms-credit-amount">—</p>
                    <p class="sms-credit-unit" id="sms-credit-unit">ریال</p>
                </div>
                <p class="sms-credit-meta" id="sms-credit-meta">برای مشاهده موجودی، روی به‌روزرسانی بزنید.</p>
                <p class="sms-credit-status is-loading" id="sms-credit-status" role="status" aria-live="polite"></p>
            </div>

            @if($smsFeat('credit.view'))
            <div class="sms-credit-token-card">
                <div class="sms-credit-title-wrap" style="margin-bottom:0.15rem;">
                    <span class="sms-credit-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
                    <div>
                        <h2 class="sms-credit-token-head" style="margin:0;">توکن WebAPI سپاهان‌گستر</h2>
                    </div>
                </div>
                <p class="sms-credit-token-sub">
                    از پنل سپاهان‌گستر (تنظیمات وب ← تولید توکن) یک توکن بسازید و اینجا ذخیره کنید.
                    توکن به‌صورت رمزنگاری‌شده نگهداری می‌شود و در مرورگر نمایش داده نمی‌شود.
                </p>
                <div class="sms-credit-token-state {{ !empty($smsPanelApiTokenStatus['configured']) ? 'is-on' : 'is-off' }}" id="sms-credit-token-state">
                    @if(!empty($smsPanelApiTokenStatus['configured']))
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        توکن تنظیم شده است
                        @if(!empty($smsPanelApiTokenStatus['hint']))
                            <span>({{ $smsPanelApiTokenStatus['hint'] }})</span>
                        @endif
                    @else
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        توکن هنوز تنظیم نشده است
                    @endif
                </div>
                <form method="post" action="{{ route('admin.sms.panel-api-token.update') }}" class="sms-credit-token-form" autocomplete="off">
                    @csrf
                    <div class="sms-credit-token-field">
                        <label for="sms-panel-api-token">توکن جدید</label>
                        <input
                            id="sms-panel-api-token"
                            type="password"
                            name="api_token"
                            value="{{ old('api_token') }}"
                            placeholder="توکن را از پنل سپاهان‌گستر وارد کنید"
                            maxlength="128"
                            autocomplete="new-password"
                        >
                        @error('api_token')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-credit-token-submit" type="submit">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        ذخیره توکن
                    </button>
                </form>
            </div>
            @endif
            </div>
        </section>
        @endif

        @if(isset($smsAllowedTabs['templates']))
        <section class="sms-tab-panel" data-sms-panel="templates" @if($smsActiveTab !== 'templates') hidden @endif>
            <div class="sms-template-toolbar">
                <p class="sms-template-toolbar-note">قالب‌های آماده و سفارشی را مدیریت کنید. پترن‌ها در زمان ارسال با داده واقعی جایگزین می‌شوند. قالب «احراز هویت موبایل ضامن (سازمانی)» برای پیامک کد تأیید ضمانت در پرونده وام استفاده می‌شود. قالب «تایید پیامکی ایجاد پرونده وام» هنگام ثبت وام جدید برای مشتری (در صورت فعال بودن تنظیم مربوطه) ارسال می‌شود. قالب «تایید پیامکی عودت ضمانت» هنگام ثبت عودت چک یا اوراق ضمانتی به مشتری ارسال می‌شود. همه از همین تب قابل ویرایش هستند.</p>
                @if($smsFeat('templates.create'))
                <button type="button" class="sms-template-add-btn" id="sms-template-open-modal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    افزودن الگو جدید
                </button>
                @endif
            </div>

            @if(! $smsFeat('templates.view'))
                <div class="sms-template-empty">شما به مشاهدهٔ الگوها دسترسی ندارید.</div>
            @elseif($smsTemplates->isEmpty())
                <div class="sms-template-empty">هنوز قالب پیامکی ثبت نشده است.</div>
            @else
                <div class="sms-template-list">
                    @foreach($smsTemplates as $tpl)
                        <article class="sms-template-item">
                            <div class="sms-template-item-head">
                                <h3 class="sms-template-item-title">{{ $tpl->title }}</h3>
                                @if($tpl->is_system)
                                    <span class="sms-template-system-badge">
                                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                        پیش‌فرض سیستم
                                    </span>
                                @endif
                            </div>
                            <p class="sms-template-item-meta">دسته: {{ $smsTemplateCategories[$tpl->category] ?? $tpl->category }}</p>
                            <div class="sms-template-item-body-wrap">
                                <p class="sms-template-item-body">{{ $tpl->body }}</p>
                            </div>
                            <div class="sms-template-item-actions">
                                @if($smsFeat('templates.update'))
                                <button type="button" class="sms-template-action-btn" data-template-edit
                                    data-template-id="{{ $tpl->id }}"
                                    data-template-title="{{ $tpl->title }}"
                                    data-template-category="{{ $tpl->category }}"
                                    data-template-body="{{ $tpl->body }}"
                                >
                                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                                    ویرایش
                                </button>
                                @endif
                                @if($smsFeat('templates.delete') && ! $tpl->is_system)
                                    <form method="post" action="{{ route('admin.sms.templates.destroy', $tpl) }}" data-template-delete-form>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="sms-template-action-btn sms-template-action-btn--danger">
                                            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                            حذف
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
        @endif

        @if(isset($smsAllowedTabs['settings']))
        <section class="sms-tab-panel" data-sms-panel="settings" @if($smsActiveTab !== 'settings') hidden @endif>
            @if($smsFeat('settings.panel'))
            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-tower-cell" aria-hidden="true"></i>
                    انتخاب پنل پیامک
                </div>
                <p class="sms-panel-select-sub">پنل فعال سامانه را انتخاب کنید و اطلاعات اتصال را ذخیره کنید.</p>
                <div class="sms-conn-badge sms-conn-badge--{{ $smsPanelConnectionState['state'] }}">
                    <i class="fa-solid fa-signal" aria-hidden="true"></i>
                    وضعیت اتصال: {{ $smsPanelConnectionState['label'] }}
                </div>
                <p class="sms-settings-note">
                    {{ $smsPanelConnectionState['message'] }}
                    @if($smsPanelLastConnectedAt)
                        <span> - آخرین بررسی: {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(jalali($smsPanelLastConnectedAt)->format('Y/m/d H:i')) }}</span>
                    @endif
                </p>

                <form method="post" action="{{ route('admin.sms.panel-settings.update') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-settings-field">
                        <label for="sms-provider">پنل پیامک</label>
                        <select id="sms-provider" name="provider">
                            @foreach($smsPanelProviders as $providerKey => $providerLabel)
                                <option value="{{ $providerKey }}" @selected(old('provider', $smsPanelSelectedProvider) === $providerKey)>{{ $providerLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-username">نام کاربری پنل</label>
                        <input id="sms-panel-username" type="text" name="username" value="{{ old('username', $smsPanelUsername) }}">
                        @error('username')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-sender-number">شماره فرستنده</label>
                        <input id="sms-panel-sender-number" type="text" name="sender_number" value="{{ old('sender_number', $smsPanelSenderNumber) }}" placeholder="مثال: 50003300">
                        @error('sender_number')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-password">رمز عبور پنل</label>
                        <input id="sms-panel-password" type="password" name="password" value="">
                        @error('password')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i>
                        ذخیره و تست اتصال
                    </button>
                </form>
            </div>
            @endif

            @if($smsFeat('settings.panel'))
            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-vial-circle-check" aria-hidden="true"></i>
                    تست پنل پیامک
                </div>
                <p class="sms-panel-select-sub">برای بررسی ارسال واقعی، شماره تماس و متن پیام تست را وارد کنید.</p>

                <form method="post" action="{{ route('admin.sms.panel-test.send') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-settings-field">
                        <label for="sms-test-recipient">شماره تماس</label>
                        <input id="sms-test-recipient" type="text" name="test_recipient" value="{{ old('test_recipient') }}" placeholder="مثال: 09123456789">
                        @error('test_recipient')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-test-message">متن پیام</label>
                        <input id="sms-test-message" type="text" name="test_message" value="{{ old('test_message', 'پیام تست پنل پیامک') }}">
                        @error('test_message')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال پیام تست
                    </button>
                </form>
            </div>
            @endif

            @if($smsFeat('settings.scenarios'))
            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                    الگوهای پیش‌فرض سناریوها
                </div>
                <p class="sms-panel-select-sub">برای هر سناریوی پیامکی، یکی از الگوهای تعریف‌شده را انتخاب کنید. این تنظیمات جدا از اتصال پنل ذخیره می‌شوند.</p>

                <form method="post" action="{{ route('admin.sms.scenario-templates.update') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-settings-field">
                        <label for="tpl-installment-thanks">قالب پیامک ثبت قسط و تشکر</label>
                        <select id="tpl-installment-thanks" name="tpl_installment_thanks_id">
                            <option value="">انتخاب نشده</option>
                            @foreach($smsTemplates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('tpl_installment_thanks_id', $smsScenarioTemplateIds['tpl_installment_thanks_id'] ?? '') == (string) $tpl->id)>
                                    {{ $tpl->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="sms-panel-select-sub" style="margin-top:0.35rem">این قالب پس از پرداخت قسط از پنل مشتری به‌صورت خودکار برای ایشان ارسال می‌شود. در متن الگو می‌توانید پترن <code>@{{purchase_credit}}</code> را برای ارسال «اعتبار خرید باقیمانده» (سقف ضمانت با ۳۰٪ کمتر منهای مانده اقساط) درج کنید.</p>
                        @error('tpl_installment_thanks_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="tpl-login">قالب پیامک ورود به سیستم</label>
                        <select id="tpl-login" name="tpl_login_id">
                            <option value="">انتخاب نشده</option>
                            @foreach($smsTemplates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('tpl_login_id', $smsScenarioTemplateIds['tpl_login_id'] ?? '') == (string) $tpl->id)>
                                    {{ $tpl->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('tpl_login_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="tpl-register-verify">قالب پیامک رمز تاییدیه ثبت نام</label>
                        <select id="tpl-register-verify" name="tpl_register_verify_code_id">
                            <option value="">انتخاب نشده</option>
                            @foreach($smsTemplates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('tpl_register_verify_code_id', $smsScenarioTemplateIds['tpl_register_verify_code_id'] ?? '') == (string) $tpl->id)>
                                    {{ $tpl->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('tpl_register_verify_code_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="tpl-register-welcome">قالب پیامک خوش آمد ثبت نام</label>
                        <select id="tpl-register-welcome" name="tpl_register_welcome_id">
                            <option value="">انتخاب نشده</option>
                            @foreach($smsTemplates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('tpl_register_welcome_id', $smsScenarioTemplateIds['tpl_register_welcome_id'] ?? '') == (string) $tpl->id)>
                                    {{ $tpl->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('tpl_register_welcome_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        ذخیره الگوهای سناریو
                    </button>
                </form>
            </div>
            @endif

            @if($smsFeat('settings.reminders'))
            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    تنظیمات پیامک‌های یادآوری
                </div>
                <p class="sms-panel-select-sub">ابتدا فعال‌سازی پیامک یادآوری را تعیین کنید. در صورت فعال‌سازی، سایر تنظیمات نمایش داده می‌شوند.</p>
                <p class="sms-panel-select-sub" style="margin-top:0.35rem;color:#64748b;font-size:0.85rem;">
                    برای ارسال خودکار سر ساعت انتخابی، زمان‌باز Laravel را روی سرور فعال کنید:
                    <code style="font-size:0.8rem;">* * * * * php {{ base_path('artisan') }} schedule:run</code>
                </p>

                <form method="post" action="{{ route('admin.sms.reminder-settings.update') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-toggle-row">
                        <label class="sms-toggle-label">
                            <input
                                type="checkbox"
                                name="reminder_enabled"
                                id="sms-reminder-enabled"
                                value="1"
                                @checked(old('reminder_enabled', $smsReminderSettings['reminder_enabled'] ?? '') === '1')
                            >
                            پیامک های یادآوری فعال باشد؟
                        </label>
                        @error('reminder_enabled')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div id="sms-reminder-fields" class="sms-reminder-grid">
                        <div class="sms-reminder-section sms-reminder-full">
                            <p class="sms-reminder-section-title"><i class="fa-regular fa-clock"></i> زمان‌بندی ارسال</p>
                            <p class="sms-reminder-section-sub">همه پیامک‌های یادآوری در ساعت مشخص‌شده پردازش می‌شوند.</p>
                            <div class="sms-settings-field">
                            <label for="sms-reminder-send-time">پیام ها راس چه ساعتی ارسال شوند؟</label>
                            @php($reminderTimeRaw = old('reminder_send_time', $smsReminderSettings['reminder_send_time'] ?? '09:00'))
                            @php($timeParts = explode(':', (string) $reminderTimeRaw))
                            @php($selectedHour = str_pad((string) ((int) ($timeParts[0] ?? 9)), 2, '0', STR_PAD_LEFT))
                            @php($selectedMinute = str_pad((string) ((int) ($timeParts[1] ?? 0)), 2, '0', STR_PAD_LEFT))
                            <input type="hidden" id="sms-reminder-send-time" name="reminder_send_time" value="{{ $selectedHour.':'.$selectedMinute }}">
                            <div class="sms-timepicker-row" aria-label="انتخاب زمان ارسال">
                                <select id="sms-reminder-hour">
                                    @for($h = 0; $h <= 23; $h++)
                                        @php($hv = str_pad((string) $h, 2, '0', STR_PAD_LEFT))
                                        <option value="{{ $hv }}" @selected($selectedHour === $hv)>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($hv) }}</option>
                                    @endfor
                                </select>
                                <span class="sms-timepicker-sep">:</span>
                                <select id="sms-reminder-minute">
                                    @for($m = 0; $m <= 59; $m += 5)
                                        @php($mv = str_pad((string) $m, 2, '0', STR_PAD_LEFT))
                                        <option value="{{ $mv }}" @selected($selectedMinute === $mv)>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($mv) }}</option>
                                    @endfor
                                </select>
                            </div>
                            @error('reminder_send_time')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="sms-reminder-section sms-reminder-full">
                            <p class="sms-reminder-section-title"><i class="fa-regular fa-calendar-check"></i> یادآوری روز سررسید</p>
                            <label class="sms-toggle-label">
                                <input
                                    type="checkbox"
                                    name="due_day_enabled"
                                    id="sms-due-day-enabled"
                                    value="1"
                                    @checked(old('due_day_enabled', $smsReminderSettings['due_day_enabled'] ?? '') === '1')
                                >
                                پیامک یادآوری روز سررسید ارسال شود؟
                            </label>
                            <div class="sms-settings-field" id="sms-due-day-template-wrap">
                                <label for="sms-due-day-template">انتخاب قالب پیامک روز سررسید</label>
                                <select id="sms-due-day-template" name="due_day_template_id">
                                    <option value="">انتخاب نشده</option>
                                    @foreach($smsTemplates as $tpl)
                                        <option value="{{ $tpl->id }}" @selected(old('due_day_template_id', $smsReminderSettings['due_day_template_id'] ?? '') == (string) $tpl->id)>{{ $tpl->title }}</option>
                                    @endforeach
                                </select>
                                @error('due_day_template_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="sms-reminder-section sms-reminder-full">
                            <p class="sms-reminder-section-title"><i class="fa-solid fa-hourglass-half"></i> یادآوری پیش از موعد</p>
                            <label class="sms-toggle-label">
                                <input
                                    type="checkbox"
                                    name="before_due_enabled"
                                    id="sms-before-due-enabled"
                                    value="1"
                                    @checked(old('before_due_enabled', $smsReminderSettings['before_due_enabled'] ?? '') === '1')
                                >
                                پیامک یادآوری سررسید پیش از موعد ارسال شود؟
                            </label>
                            <div class="sms-settings-field" id="sms-before-due-template-wrap">
                                <label for="sms-before-due-template">انتخاب قالب سررسید پیش از موعد</label>
                                <select id="sms-before-due-template" name="before_due_template_id">
                                    <option value="">انتخاب نشده</option>
                                    @foreach($smsTemplates as $tpl)
                                        <option value="{{ $tpl->id }}" @selected(old('before_due_template_id', $smsReminderSettings['before_due_template_id'] ?? '') == (string) $tpl->id)>{{ $tpl->title }}</option>
                                    @endforeach
                                </select>
                                @error('before_due_template_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="sms-settings-field" id="sms-before-due-days-wrap">
                                <label for="sms-before-due-days">چند روز قبل از سررسید ارسال شود؟</label>
                                <input id="sms-before-due-days" type="number" min="1" max="365" step="1" name="before_due_days" value="{{ old('before_due_days', $smsReminderSettings['before_due_days'] ?? '') }}" placeholder="مثلاً 5">
                                @error('before_due_days')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="sms-reminder-section sms-reminder-full">
                            <p class="sms-reminder-section-title"><i class="fa-solid fa-triangle-exclamation"></i> پیامک معوق</p>
                            <div class="sms-settings-field">
                                <label for="sms-overdue-days-after">پیامک اقساط معوق چند روز پس از سررسید ارسال شود؟</label>
                                <input id="sms-overdue-days-after" type="number" min="0" max="365" step="1" name="overdue_days_after" value="{{ old('overdue_days_after', $smsReminderSettings['overdue_days_after'] ?? '') }}" placeholder="مثلاً 3">
                                @error('overdue_days_after')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="sms-settings-field">
                                <label for="sms-overdue-repeat-mode">بازه ارسال پیامک معوق تا زمان وصول</label>
                                @php($overdueRepeatMode = old('overdue_repeat_mode', $smsReminderSettings['overdue_repeat_mode'] ?? 'once'))
                                <select id="sms-overdue-repeat-mode" name="overdue_repeat_mode">
                                    <option value="once" @selected($overdueRepeatMode === 'once')>فقط یک‌بار (روز شروع ارسال معوق)</option>
                                    <option value="daily" @selected($overdueRepeatMode === 'daily')>هر روز</option>
                                    <option value="weekly" @selected($overdueRepeatMode === 'weekly')>هفتگی (هر ۷ روز)</option>
                                    <option value="interval" @selected($overdueRepeatMode === 'interval')>هر n روز یکبار</option>
                                </select>
                                @error('overdue_repeat_mode')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="sms-settings-field" id="sms-overdue-interval-wrap">
                                <label for="sms-overdue-repeat-interval">هر چند روز یکبار ارسال شود؟</label>
                                <input id="sms-overdue-repeat-interval" type="number" min="2" max="365" step="1" name="overdue_repeat_interval_days" value="{{ old('overdue_repeat_interval_days', $smsReminderSettings['overdue_repeat_interval_days'] ?? '7') }}" placeholder="مثلاً 3">
                                @error('overdue_repeat_interval_days')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="sms-settings-field">
                                <label for="sms-overdue-template">قالب پیامک اقساط معوق شده</label>
                                <select id="sms-overdue-template" name="overdue_template_id">
                                    <option value="">انتخاب نشده</option>
                                    @foreach($smsTemplates as $tpl)
                                        <option value="{{ $tpl->id }}" @selected(old('overdue_template_id', $smsReminderSettings['overdue_template_id'] ?? '') == (string) $tpl->id)>{{ $tpl->title }}</option>
                                    @endforeach
                                </select>
                                @error('overdue_template_id')<div class="sms-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        ذخیره تنظیمات یادآوری
                    </button>
                </form>
            </div>
            @endif

            @if($smsFeat('settings.messages'))
            @include('admin.sms.partials.admin-login-notify-settings')
            @endif

            @if(
                ! $smsFeat('settings.panel')
                && ! $smsFeat('settings.scenarios')
                && ! $smsFeat('settings.reminders')
                && ! $smsFeat('settings.messages')
            )
                <div class="sms-template-empty">در این بخش هنوز زیرمجوز مشخصی برای حساب شما فعال نشده است. از مدیر بخواهید یکی از گزینه‌های «تنظیمات پنل پیامک» (اتصال پنل، سناریو، یادآوری یا پیام‌ها) را تیک بزند.</div>
            @endif

        </section>
        @endif
    </div>

    @if($smsFeat('templates.create') || $smsFeat('templates.update'))
    <div class="sms-template-modal-overlay" id="sms-template-modal-overlay" @if(! ($errors->has('title') || $errors->has('category') || $errors->has('body'))) hidden @endif>
        <div class="sms-template-modal" role="dialog" aria-modal="true" aria-labelledby="sms-template-modal-title">
            <div class="sms-template-modal-head">
                <h2 class="sms-template-modal-title" id="sms-template-modal-title">ایجاد الگوی پیامک</h2>
                <button type="button" class="sms-template-close-btn" id="sms-template-close-modal" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sms-template-modal-body">
                <form method="post" action="{{ route('admin.sms.templates.store') }}" class="sms-template-form" id="sms-template-form">
                    @csrf
                    <input type="hidden" name="_method" id="sms-template-form-method" value="POST">
                    <div class="sms-template-grid">
                        <div class="sms-template-field">
                            <label for="sms-template-title">عنوان قالب *</label>
                            <input id="sms-template-title" type="text" name="title" value="{{ old('title') }}">
                            @error('title')<div class="sms-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="sms-template-field">
                            <label for="sms-template-category">لیست دسته قالب *</label>
                            <select id="sms-template-category" name="category">
                                <option value="">انتخاب کنید</option>
                                @foreach($smsTemplateCategories as $categoryKey => $categoryLabel)
                                    <option value="{{ $categoryKey }}" @selected(old('category') === $categoryKey)>{{ $categoryLabel }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="sms-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <div class="sms-patterns-label">پترن‌های قابل استفاده (برای درج داخل قالب کلیک کنید)</div>
                        <div class="sms-patterns">
                            @foreach($smsTemplatePatterns as $patternKey => $pattern)
                                <button type="button" class="sms-pattern-chip" data-sms-pattern="{{ $patternKey }}">{{ $pattern['label'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="sms-template-field">
                        <label for="sms-template-body">الگوی قالب *</label>
                        <textarea id="sms-template-body" name="body">{{ old('body') }}</textarea>
                        @error('body')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <div class="sms-patterns-label">نمونه پیامک:</div>
                        <div class="sms-template-preview" id="sms-template-preview"></div>
                    </div>

                    <button class="sms-template-submit" type="submit">ذخیره</button>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        (function () {
            var toggleBtn = document.getElementById('sms-toggle-range');
            var panel = document.getElementById('sms-range-panel');
            if (toggleBtn && panel) {
                toggleBtn.addEventListener('click', function () {
                    panel.hidden = !panel.hidden;
                });
            }

            var actionBoxes = Array.from(document.querySelectorAll('[data-sms-actions]'));
            function placeMenu(toggle, menu) {
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

            actionBoxes.forEach(function (box) {
                var toggle = box.querySelector('[data-sms-actions-toggle]');
                var menu = box.querySelector('[data-sms-actions-menu]');
                if (!toggle || !menu) return;
                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var isHidden = menu.hidden;
                    actionBoxes.forEach(function (otherBox) {
                        var otherMenu = otherBox.querySelector('[data-sms-actions-menu]');
                        if (otherMenu) otherMenu.hidden = true;
                    });
                    if (isHidden) {
                        placeMenu(toggle, menu);
                    } else {
                        menu.hidden = true;
                    }
                });
            });
            document.addEventListener('click', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (menu) menu.hidden = true;
                });
            });
            window.addEventListener('resize', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (!menu || menu.hidden) return;
                    var toggle = box.querySelector('[data-sms-actions-toggle]');
                    if (toggle) placeMenu(toggle, menu);
                });
            });
            window.addEventListener('scroll', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (!menu || menu.hidden) return;
                    var toggle = box.querySelector('[data-sms-actions-toggle]');
                    if (toggle) placeMenu(toggle, menu);
                });
            }, true);

            document.querySelectorAll('[data-sms-view-detail]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.AdminSwal) return;
                    AdminSwal.info(
                        'گیرنده: ' + (btn.getAttribute('data-recipient') || '—')
                        + '\nنوع: ' + (btn.getAttribute('data-type') || '—')
                        + '\nوضعیت: ' + (btn.getAttribute('data-status') || '—')
                        + '\n\nمتن پیام:\n' + (btn.getAttribute('data-message') || '')
                    , 'جزئیات پیامک');
                });
            });

            document.querySelectorAll('[data-sms-delete-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (!window.AdminSwal) {
                        form.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف پیامک',
                        text: 'این رکورد حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            var tabButtons = Array.from(document.querySelectorAll('[data-sms-tab]'));
            var tabPanels = Array.from(document.querySelectorAll('.sms-page [data-sms-panel]'));
            var serverActiveTab = @json($smsActiveTab);
            var smsCreditUrl = @json(route('admin.sms.panel-credit'));
            var smsCreditCanView = @json($smsFeat('credit.view'));
            var smsCreditLoaded = false;
            var smsCreditLoading = false;

            function setSmsCreditStatus(text, mode) {
                var el = document.getElementById('sms-credit-status');
                if (!el) return;
                el.textContent = text || '';
                el.classList.remove('is-ok', 'is-err', 'is-loading');
                if (mode) el.classList.add(mode);
            }

            function loadSmsPanelCredit(force) {
                if (!smsCreditCanView || smsCreditLoading) return;
                if (smsCreditLoaded && !force) return;

                var amountEl = document.getElementById('sms-credit-amount');
                var unitEl = document.getElementById('sms-credit-unit');
                var metaEl = document.getElementById('sms-credit-meta');
                var refreshBtn = document.getElementById('sms-credit-refresh');
                smsCreditLoading = true;
                if (refreshBtn) refreshBtn.disabled = true;
                setSmsCreditStatus('در حال دریافت اعتبار از پنل پیامک…', 'is-loading');

                fetch(smsCreditUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { okHttp: res.ok, data: data || {} };
                    }).catch(function () {
                        return { okHttp: res.ok, data: {} };
                    });
                }).then(function (payload) {
                    var data = payload.data || {};
                    if (payload.okHttp && data.ok) {
                        if (amountEl) amountEl.textContent = data.credit_fa || '—';
                        if (unitEl) unitEl.textContent = data.unit || 'ریال';
                        if (metaEl) {
                            metaEl.textContent = data.checked_at_fa
                                ? ('آخرین استعلام: ' + data.checked_at_fa)
                                : 'اعتبار با موفقیت دریافت شد.';
                        }
                        setSmsCreditStatus(data.message || 'اعتبار باقیمانده با موفقیت دریافت شد.', 'is-ok');
                        smsCreditLoaded = true;
                        return;
                    }
                    if (amountEl && !smsCreditLoaded) amountEl.textContent = '—';
                    setSmsCreditStatus((data && data.message) ? data.message : 'دریافت اعتبار ناموفق بود.', 'is-err');
                }).catch(function () {
                    setSmsCreditStatus('ارتباط با سرور برای استعلام اعتبار برقرار نشد.', 'is-err');
                }).finally(function () {
                    smsCreditLoading = false;
                    if (refreshBtn) refreshBtn.disabled = false;
                });
            }

            function activateTab(tabId) {
                if (!tabId || tabPanels.length === 0) {
                    return;
                }
                var hasPanel = tabPanels.some(function (tabPanel) {
                    return tabPanel.getAttribute('data-sms-panel') === tabId;
                });
                if (!hasPanel) {
                    tabId = tabPanels[0].getAttribute('data-sms-panel');
                }
                tabButtons.forEach(function (btn) {
                    var isActive = btn.getAttribute('data-sms-tab') === tabId;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                tabPanels.forEach(function (tabPanel) {
                    tabPanel.hidden = tabPanel.getAttribute('data-sms-panel') !== tabId;
                });
                if (tabId === 'credit') {
                    loadSmsPanelCredit(false);
                }
            }
            tabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activateTab(btn.getAttribute('data-sms-tab'));
                });
            });
            var creditRefreshBtn = document.getElementById('sms-credit-refresh');
            if (creditRefreshBtn) {
                creditRefreshBtn.addEventListener('click', function () {
                    loadSmsPanelCredit(true);
                });
            }
            if (tabPanels.length === 1) {
                tabPanels[0].hidden = false;
                if (tabPanels[0].getAttribute('data-sms-panel') === 'credit') {
                    loadSmsPanelCredit(false);
                }
            } else if (serverActiveTab) {
                activateTab(serverActiveTab);
            } else if (tabPanels[0]) {
                activateTab(tabPanels[0].getAttribute('data-sms-panel'));
            }

            var templateOverlay = document.getElementById('sms-template-modal-overlay');
            var templateOpenBtn = document.getElementById('sms-template-open-modal');
            var templateCloseBtn = document.getElementById('sms-template-close-modal');
            var templateBody = document.getElementById('sms-template-body');
            var templatePreview = document.getElementById('sms-template-preview');
            var templateForm = document.getElementById('sms-template-form');
            var templateMethodInput = document.getElementById('sms-template-form-method');
            var templateModalTitle = document.getElementById('sms-template-modal-title');
            var templateTitleInput = document.getElementById('sms-template-title');
            var templateCategoryInput = document.getElementById('sms-template-category');
            var templatePatterns = @json($smsTemplatePatterns);
            var templateStoreUrl = @json(route('admin.sms.templates.store'));
            var templateUpdateUrlTpl = @json(route('admin.sms.templates.update', ['smsTemplate' => '__ID__']));
            function openTemplateModal() {
                if (!templateOverlay) return;
                templateOverlay.hidden = false;
            }
            function closeTemplateModal() {
                if (!templateOverlay) return;
                templateOverlay.hidden = true;
            }
            function renderTemplatePreview() {
                if (!templateBody || !templatePreview) return;
                var text = templateBody.value || '';
                Object.keys(templatePatterns).forEach(function (key) {
                    var pattern = templatePatterns[key];
                    var tokenRegex = new RegExp('\\{\\{\\s*' + key + '\\s*\\}\\}', 'gi');
                    text = text.replace(tokenRegex, pattern.sample);
                });
                templatePreview.textContent = text.trim() !== '' ? text : 'پیش‌نمایش پیامک اینجا نمایش داده می‌شود.';
            }
            if (templateOpenBtn) {
                templateOpenBtn.addEventListener('click', function () {
                    if (templateForm) templateForm.action = templateStoreUrl;
                    if (templateMethodInput) templateMethodInput.value = 'POST';
                    if (templateModalTitle) templateModalTitle.textContent = 'ایجاد الگوی پیامک';
                    if (templateTitleInput) templateTitleInput.value = '';
                    if (templateCategoryInput) templateCategoryInput.value = '';
                    if (templateBody) templateBody.value = '';
                    openTemplateModal();
                    activateTab('templates');
                    renderTemplatePreview();
                });
            }
            if (templateCloseBtn) {
                templateCloseBtn.addEventListener('click', closeTemplateModal);
            }
            if (templateOverlay) {
                templateOverlay.addEventListener('click', function (event) {
                    if (event.target === templateOverlay) closeTemplateModal();
                });
            }
            document.querySelectorAll('[data-sms-pattern]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (!templateBody) return;
                    var key = chip.getAttribute('data-sms-pattern');
                    if (!key) return;
                    var token = '{' + '{' + key + '}' + '}';
                    var start = templateBody.selectionStart || 0;
                    var end = templateBody.selectionEnd || 0;
                    var value = templateBody.value || '';
                    templateBody.value = value.slice(0, start) + token + value.slice(end);
                    var nextPos = start + token.length;
                    templateBody.setSelectionRange(nextPos, nextPos);
                    templateBody.focus();
                    renderTemplatePreview();
                });
            });
            document.querySelectorAll('[data-template-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-template-id') || '';
                    if (!id) return;
                    if (templateForm) templateForm.action = templateUpdateUrlTpl.replace('__ID__', id);
                    if (templateMethodInput) templateMethodInput.value = 'PUT';
                    if (templateModalTitle) templateModalTitle.textContent = 'ویرایش الگوی پیامک';
                    if (templateTitleInput) templateTitleInput.value = btn.getAttribute('data-template-title') || '';
                    if (templateCategoryInput) templateCategoryInput.value = btn.getAttribute('data-template-category') || '';
                    if (templateBody) templateBody.value = btn.getAttribute('data-template-body') || '';
                    openTemplateModal();
                    activateTab('templates');
                    renderTemplatePreview();
                });
            });
            document.querySelectorAll('[data-template-delete-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (!window.AdminSwal) {
                        form.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف الگوی پیامک',
                        text: 'این الگو حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
            if (templateBody) {
                templateBody.addEventListener('input', renderTemplatePreview);
            }
            renderTemplatePreview();

            var reminderEnabled = document.getElementById('sms-reminder-enabled');
            var reminderFields = document.getElementById('sms-reminder-fields');
            var reminderSendTimeInput = document.getElementById('sms-reminder-send-time');
            var reminderHourSelect = document.getElementById('sms-reminder-hour');
            var reminderMinuteSelect = document.getElementById('sms-reminder-minute');
            var dueDayEnabled = document.getElementById('sms-due-day-enabled');
            var dueDayTemplateWrap = document.getElementById('sms-due-day-template-wrap');
            var beforeDueEnabled = document.getElementById('sms-before-due-enabled');
            var beforeDueTemplateWrap = document.getElementById('sms-before-due-template-wrap');
            var beforeDueDaysWrap = document.getElementById('sms-before-due-days-wrap');
            var overdueRepeatMode = document.getElementById('sms-overdue-repeat-mode');
            var overdueIntervalWrap = document.getElementById('sms-overdue-interval-wrap');
            function syncReminderTimeValue() {
                if (!reminderSendTimeInput || !reminderHourSelect || !reminderMinuteSelect) return;
                reminderSendTimeInput.value = String(reminderHourSelect.value || '00') + ':' + String(reminderMinuteSelect.value || '00');
            }
            function setVisibility(el, visible) {
                if (!el) return;
                el.classList.toggle('sms-reminder-hidden', !visible);
            }
            function setEnabledRecursive(root, enabled) {
                if (!root) return;
                root.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                    if (field.id === 'sms-reminder-enabled') return;
                    field.disabled = !enabled;
                });
            }
            function syncReminderVisibility() {
                var rootEnabled = !!(reminderEnabled && reminderEnabled.checked);
                setVisibility(reminderFields, rootEnabled);
                setEnabledRecursive(reminderFields, rootEnabled);
                var dueEnabled = rootEnabled && !!(dueDayEnabled && dueDayEnabled.checked);
                setVisibility(dueDayTemplateWrap, dueEnabled);
                setEnabledRecursive(dueDayTemplateWrap, dueEnabled);
                var beforeEnabled = rootEnabled && !!(beforeDueEnabled && beforeDueEnabled.checked);
                setVisibility(beforeDueTemplateWrap, beforeEnabled);
                setVisibility(beforeDueDaysWrap, beforeEnabled);
                setEnabledRecursive(beforeDueTemplateWrap, beforeEnabled);
                setEnabledRecursive(beforeDueDaysWrap, beforeEnabled);
                var intervalVisible = rootEnabled && overdueRepeatMode && overdueRepeatMode.value === 'interval';
                setVisibility(overdueIntervalWrap, intervalVisible);
                setEnabledRecursive(overdueIntervalWrap, intervalVisible);
            }
            if (reminderEnabled) reminderEnabled.addEventListener('change', syncReminderVisibility);
            if (dueDayEnabled) dueDayEnabled.addEventListener('change', syncReminderVisibility);
            if (beforeDueEnabled) beforeDueEnabled.addEventListener('change', syncReminderVisibility);
            if (overdueRepeatMode) overdueRepeatMode.addEventListener('change', syncReminderVisibility);
            if (reminderHourSelect) reminderHourSelect.addEventListener('change', syncReminderTimeValue);
            if (reminderMinuteSelect) reminderMinuteSelect.addEventListener('change', syncReminderTimeValue);
            syncReminderTimeValue();
            syncReminderVisibility();

            var smsAdminPickerAdmins = @json($smsAdminPickerAdmins ?? []);
            var smsLoginNotifyAppName = @json($appDisplayName ?? 'سامانه');
            var adminLoginEnabled = document.getElementById('sms-admin-login-enabled');
            var adminLoginFields = document.getElementById('sms-admin-login-fields');
            var adminLoginMessage = document.getElementById('sms-admin-login-message');
            var adminLoginPreview = document.getElementById('sms-admin-login-preview');
            var adminLoginRecipientInputs = document.getElementById('sms-admin-login-recipient-inputs');
            var adminLoginRecipientCount = document.getElementById('sms-admin-login-recipient-count');
            var recipientsModal = document.getElementById('sms-admin-recipients-modal');
            var pickerModal = document.getElementById('sms-admin-picker-modal');
            var recipientsTbody = document.getElementById('sms-admin-recipients-tbody');
            var recipientsEmpty = document.getElementById('sms-admin-recipients-empty');
            var pickerList = document.getElementById('sms-admin-picker-list');
            var pickerSearch = document.getElementById('sms-admin-picker-search');
            var pickerCountEl = document.getElementById('sms-admin-picker-count');
            var pickerNoResults = document.getElementById('sms-admin-picker-no-results');
            var notifyForm = document.getElementById('sms-admin-login-notify-form');
            var adminLoginSelfEnabled = document.getElementById('sms-admin-login-self-enabled');
            var adminLoginSelfFields = document.getElementById('sms-admin-self-fields');
            var adminLoginSelfMessage = document.getElementById('sms-admin-self-message');
            var adminLoginSelfPreview = document.getElementById('sms-admin-self-preview');
            var notifyBlockManagers = document.getElementById('sms-notify-block-managers');
            var notifyBlockSelf = document.getElementById('sms-notify-block-self');
            var draftRecipientIds = [];
            var savedRecipientIds = [];
            var pickerSelectionIds = [];
            var pickerSearchQuery = '';

            function escNotifyHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function faNotifyDigits(n) {
                var map = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                return String(n).replace(/\d/g, function (d) { return map[parseInt(d, 10)]; });
            }
            function parseInitialRecipientIds() {
                if (!adminLoginRecipientInputs) return [];
                return Array.from(adminLoginRecipientInputs.querySelectorAll('input[name="recipient_admin_ids[]"]'))
                    .map(function (input) { return parseInt(input.value, 10); })
                    .filter(function (id) { return id > 0; });
            }
            function adminPickerById(id) {
                for (var i = 0; i < smsAdminPickerAdmins.length; i++) {
                    if (smsAdminPickerAdmins[i].id === id) return smsAdminPickerAdmins[i];
                }
                return null;
            }
            function mountSmsNotifyAdminPicker(cfg) {
                var enabledEl = document.getElementById(cfg.enabledId);
                var fieldsEl = document.getElementById(cfg.fieldsId);
                var blockEl = document.getElementById(cfg.blockId);
                var messageEl = document.getElementById(cfg.messageId);
                var previewEl = document.getElementById(cfg.previewId);
                var recipientInputs = document.getElementById(cfg.recipientInputsId);
                var recipientCount = document.getElementById(cfg.recipientCountId);
                var recipientsModal = document.getElementById(cfg.recipientsModalId);
                var pickerModal = document.getElementById(cfg.pickerModalId);
                var recipientsTbody = document.getElementById(cfg.recipientsTbodyId);
                var recipientsEmpty = document.getElementById(cfg.recipientsEmptyId);
                var pickerList = document.getElementById(cfg.pickerListId);
                var pickerSearch = document.getElementById(cfg.pickerSearchId);
                var pickerCountEl = document.getElementById(cfg.pickerCountId);
                var pickerNoResults = document.getElementById(cfg.pickerNoResultsId);
                var notifyForm = document.getElementById(cfg.notifyFormId);
                var draftIds = [];
                var savedIds = [];
                var pickerSelectionIds = [];
                var pickerSearchQuery = '';
                var hiddenInputName = cfg.hiddenInputName;

                function parseInitialIds() {
                    if (!recipientInputs) return [];
                    return Array.from(recipientInputs.querySelectorAll('input[name="' + hiddenInputName + '"]'))
                        .map(function (input) { return parseInt(input.value, 10); })
                        .filter(function (id) { return id > 0; });
                }
                function syncFieldsVisibility() {
                    var enabled = !!(enabledEl && enabledEl.checked);
                    if (fieldsEl) fieldsEl.classList.toggle('sms-reminder-hidden', !enabled);
                    syncNotifyBlockOn(blockEl, enabled);
                }
                function renderPreview() {
                    if (!previewEl || !messageEl) return;
                    var tpl = messageEl.value || '';
                    var text = cfg.renderPreview(tpl);
                    previewEl.textContent = text.trim() !== ''
                        ? text.trim()
                        : 'پیش‌نمایش متن پیامک اینجا نمایش داده می‌شود.';
                }
                function renderRecipientsTable(ids) {
                    if (!recipientsTbody) return;
                    var html = '';
                    var mobileMap = {};
                    ids.forEach(function (id) {
                        var admin = adminPickerById(id);
                        if (!admin) return;
                        var mobileNorm = normalizeNotifyMobile(admin.mobile);
                        var dupHint = '';
                        if (mobileNorm) {
                            if (mobileMap[mobileNorm]) dupHint = '<span class="sms-recipient-dup-hint">شماره تکراری با ' + escNotifyHtml(mobileMap[mobileNorm]) + '</span>';
                            else mobileMap[mobileNorm] = admin.full_name || admin.username;
                        }
                        var mobileLabel = mobileNorm ? faNotifyDigits(mobileNorm) : faNotifyDigits(admin.mobile || '—');
                        html += '<tr data-admin-id="' + id + '">'
                            + '<td>' + escNotifyHtml(admin.full_name || '—') + '</td>'
                            + '<td>' + escNotifyHtml(admin.username || '—') + '</td>'
                            + '<td dir="ltr" style="text-align:end">' + escNotifyHtml(mobileLabel)
                            + (admin.mobile_valid === false ? ' <span class="sms-picker-item__badge sms-picker-item__badge--bad">نامعتبر</span>' : '')
                            + dupHint + '</td>'
                            + '<td><button type="button" class="sms-mini-btn sms-mini-btn--danger" data-sms-remove-recipient="' + id + '">حذف</button></td>'
                            + '</tr>';
                    });
                    recipientsTbody.innerHTML = html;
                    if (recipientsEmpty) recipientsEmpty.hidden = ids.length > 0;
                    recipientsTbody.querySelectorAll('[data-sms-remove-recipient]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var rid = parseInt(btn.getAttribute('data-sms-remove-recipient'), 10);
                            draftIds = draftIds.filter(function (x) { return x !== rid; });
                            renderRecipientsTable(draftIds);
                        });
                    });
                }
                function renderPickerList(selectedIds) {
                    if (!pickerList) return;
                    var q = (pickerSearchQuery || '').trim().toLowerCase();
                    var visible = 0;
                    var html = '';
                    smsAdminPickerAdmins.forEach(function (admin) {
                        var blob = adminSearchBlob(admin);
                        var matches = q === '' || blob.indexOf(q) !== -1;
                        if (!matches) return;
                        visible += 1;
                        var checked = selectedIds.indexOf(admin.id) !== -1;
                        var mobileNorm = normalizeNotifyMobile(admin.mobile);
                        var mobileShown = mobileNorm ? faNotifyDigits(mobileNorm) : faNotifyDigits(admin.mobile || '—');
                        var badge = admin.mobile_valid
                            ? '<span class="sms-picker-item__badge sms-picker-item__badge--ok">قابل ارسال</span>'
                            : '<span class="sms-picker-item__badge sms-picker-item__badge--bad">موبایل نامعتبر</span>';
                        html += '<label class="sms-picker-item' + (checked ? ' is-selected' : '') + '" data-admin-id="' + admin.id + '">'
                            + '<input type="checkbox" value="' + admin.id + '"' + (checked ? ' checked' : '') + '>'
                            + '<div>'
                            + '<p class="sms-picker-item__name">' + escNotifyHtml(admin.full_name || '—') + '</p>'
                            + '<p class="sms-picker-item__username">@' + escNotifyHtml(admin.username || '—') + '</p>'
                            + '</div>'
                            + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.2rem">'
                            + '<p class="sms-picker-item__mobile">' + escNotifyHtml(mobileShown) + '</p>'
                            + badge
                            + '</div>'
                            + '</label>';
                    });
                    pickerList.innerHTML = html;
                    if (pickerNoResults) pickerNoResults.hidden = visible > 0;
                    if (pickerCountEl) {
                        pickerCountEl.textContent = faNotifyDigits(visible) + ' مورد نمایش · ' + faNotifyDigits(selectedIds.length) + ' انتخاب‌شده';
                    }
                    pickerList.querySelectorAll('.sms-picker-item input[type="checkbox"]').forEach(function (cb) {
                        cb.addEventListener('change', function () {
                            var id = parseInt(cb.value, 10);
                            if (!id) return;
                            if (cb.checked) {
                                if (pickerSelectionIds.indexOf(id) === -1) pickerSelectionIds.push(id);
                            } else {
                                pickerSelectionIds = pickerSelectionIds.filter(function (x) { return x !== id; });
                            }
                            var label = cb.closest('.sms-picker-item');
                            if (label) label.classList.toggle('is-selected', cb.checked);
                            if (pickerCountEl) {
                                pickerCountEl.textContent = faNotifyDigits(visible) + ' مورد نمایش · ' + faNotifyDigits(pickerSelectionIds.length) + ' انتخاب‌شده';
                            }
                        });
                    });
                }
                function syncHiddenInputs(ids) {
                    if (!recipientInputs) return;
                    recipientInputs.innerHTML = ids.map(function (id) {
                        return '<input type="hidden" name="' + hiddenInputName + '" value="' + id + '">';
                    }).join('');
                    if (recipientCount) {
                        recipientCount.textContent = '(' + faNotifyDigits(ids.length) + ')';
                    }
                }

                savedIds = parseInitialIds();
                draftIds = savedIds.slice();
                syncFieldsVisibility();
                renderPreview();

                if (enabledEl) enabledEl.addEventListener('change', syncFieldsVisibility);
                if (messageEl) messageEl.addEventListener('input', renderPreview);
                document.querySelectorAll(cfg.patternSelector).forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        if (!messageEl) return;
                        var token = chip.getAttribute(cfg.patternDataAttr) || '';
                        var start = messageEl.selectionStart || 0;
                        var end = messageEl.selectionEnd || 0;
                        var val = messageEl.value || '';
                        messageEl.value = val.slice(0, start) + token + val.slice(end);
                        messageEl.focus();
                        renderPreview();
                    });
                });
                var openRecipientsBtn = document.getElementById(cfg.openRecipientsBtnId);
                if (openRecipientsBtn) {
                    openRecipientsBtn.addEventListener('click', function () {
                        draftIds = savedIds.slice();
                        renderRecipientsTable(draftIds);
                        openNotifyModal(recipientsModal);
                    });
                }
                var openPickerBtn = document.getElementById(cfg.openPickerBtnId);
                if (openPickerBtn) {
                    openPickerBtn.addEventListener('click', function () {
                        pickerSearchQuery = '';
                        if (pickerSearch) pickerSearch.value = '';
                        pickerSelectionIds = draftIds.slice();
                        renderPickerList(pickerSelectionIds);
                        openNotifyModal(pickerModal);
                    });
                }
                if (pickerSearch) {
                    pickerSearch.addEventListener('input', function () {
                        pickerSearchQuery = pickerSearch.value || '';
                        renderPickerList(pickerSelectionIds);
                    });
                }
                var pickerApplyBtn = document.getElementById(cfg.pickerApplyBtnId);
                if (pickerApplyBtn) {
                    pickerApplyBtn.addEventListener('click', function () {
                        draftIds = pickerSelectionIds.slice().filter(function (id, idx, arr) {
                            return id > 0 && arr.indexOf(id) === idx;
                        });
                        renderRecipientsTable(draftIds);
                        syncHiddenInputs(draftIds);
                        closeNotifyModal(pickerModal);
                    });
                }
                var recipientsSaveBtn = document.getElementById(cfg.recipientsSaveBtnId);
                if (recipientsSaveBtn) {
                    recipientsSaveBtn.addEventListener('click', function () {
                        savedIds = draftIds.slice();
                        syncHiddenInputs(savedIds);
                        closeNotifyModal(recipientsModal);
                    });
                }
                if (notifyForm) {
                    notifyForm.addEventListener('submit', function () {
                        var ids = draftIds.length ? draftIds : savedIds;
                        syncHiddenInputs(ids);
                    });
                }
                document.querySelectorAll('[' + cfg.modalCloseAttr + ']').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var which = btn.getAttribute(cfg.modalCloseAttr);
                        if (which === 'recipients') {
                            draftIds = savedIds.slice();
                            closeNotifyModal(recipientsModal);
                        } else if (which === 'picker') {
                            closeNotifyModal(pickerModal);
                        }
                    });
                });
                [recipientsModal, pickerModal].forEach(function (overlay) {
                    if (!overlay) return;
                    overlay.addEventListener('click', function (event) {
                        if (event.target !== overlay) return;
                        if (overlay.id === cfg.recipientsModalId) {
                            draftIds = savedIds.slice();
                        }
                        overlay.hidden = true;
                    });
                });
            }
            function syncNotifyBlockOn(blockEl, enabled) {
                if (blockEl) blockEl.classList.toggle('is-on', !!enabled);
            }
            function syncAdminLoginFieldsVisibility() {
                var enabled = !!(adminLoginEnabled && adminLoginEnabled.checked);
                if (adminLoginFields) {
                    adminLoginFields.classList.toggle('sms-reminder-hidden', !enabled);
                }
                syncNotifyBlockOn(notifyBlockManagers, enabled);
            }
            function syncAdminLoginSelfFieldsVisibility() {
                var enabled = !!(adminLoginSelfEnabled && adminLoginSelfEnabled.checked);
                if (adminLoginSelfFields) {
                    adminLoginSelfFields.classList.toggle('sms-reminder-hidden', !enabled);
                }
                syncNotifyBlockOn(notifyBlockSelf, enabled);
            }
            function renderAdminLoginPreview() {
                if (!adminLoginPreview || !adminLoginMessage) return;
                var tpl = adminLoginMessage.value || '';
                var text = tpl
                    .replace(/\{admin_full_name\}/g, 'علی احمدی')
                    .replace(/\{admin_name\}/g, 'علی احمدی')
                    .replace(/\{admin_first_name\}/g, 'علی')
                    .replace(/\{admin_last_name\}/g, 'احمدی')
                    .replace(/\{admin_username\}/g, 'admin.demo')
                    .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                adminLoginPreview.textContent = text.trim() !== ''
                    ? text.trim()
                    : 'پیش‌نمایش متن پیامک اینجا نمایش داده می‌شود.';
            }
            function renderAdminLoginSelfPreview() {
                if (!adminLoginSelfPreview || !adminLoginSelfMessage) return;
                var tpl = adminLoginSelfMessage.value || '';
                var text = tpl
                    .replace(/\{admin_full_name\}/g, 'علی احمدی')
                    .replace(/\{admin_name\}/g, 'علی احمدی')
                    .replace(/\{admin_first_name\}/g, 'علی')
                    .replace(/\{admin_last_name\}/g, 'احمدی')
                    .replace(/\{admin_username\}/g, 'admin.demo')
                    .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                adminLoginSelfPreview.textContent = text.trim() !== ''
                    ? text.trim()
                    : 'پیش‌نمایش متن پیامک اینجا نمایش داده می‌شود.';
            }
            function normalizeNotifyMobile(raw) {
                var digits = String(raw || '').replace(/\D+/g, '');
                if (digits.indexOf('98') === 0 && digits.length === 12) digits = '0' + digits.slice(2);
                if (digits.charAt(0) === '9' && digits.length === 10) digits = '0' + digits;
                return /^09\d{9}$/.test(digits) ? digits : '';
            }
            function adminSearchBlob(admin) {
                return [
                    admin.full_name || '',
                    admin.username || '',
                    admin.mobile || '',
                    normalizeNotifyMobile(admin.mobile),
                ].join(' ').toLowerCase();
            }
            function renderRecipientsTable(ids) {
                if (!recipientsTbody) return;
                var html = '';
                var mobileMap = {};
                ids.forEach(function (id) {
                    var admin = adminPickerById(id);
                    if (!admin) return;
                    var mobileNorm = normalizeNotifyMobile(admin.mobile);
                    var dupHint = '';
                    if (mobileNorm) {
                        if (mobileMap[mobileNorm]) dupHint = '<span class="sms-recipient-dup-hint">شماره تکراری با ' + escNotifyHtml(mobileMap[mobileNorm]) + '</span>';
                        else mobileMap[mobileNorm] = admin.full_name || admin.username;
                    }
                    var mobileLabel = mobileNorm ? faNotifyDigits(mobileNorm) : faNotifyDigits(admin.mobile || '—');
                    html += '<tr data-admin-id="' + id + '">'
                        + '<td>' + escNotifyHtml(admin.full_name || '—') + '</td>'
                        + '<td>' + escNotifyHtml(admin.username || '—') + '</td>'
                        + '<td dir="ltr" style="text-align:end">' + escNotifyHtml(mobileLabel)
                        + (admin.mobile_valid === false ? ' <span class="sms-picker-item__badge sms-picker-item__badge--bad">نامعتبر</span>' : '')
                        + dupHint + '</td>'
                        + '<td><button type="button" class="sms-mini-btn sms-mini-btn--danger" data-sms-remove-recipient="' + id + '">حذف</button></td>'
                        + '</tr>';
                });
                recipientsTbody.innerHTML = html;
                if (recipientsEmpty) recipientsEmpty.hidden = ids.length > 0;
                recipientsTbody.querySelectorAll('[data-sms-remove-recipient]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var rid = parseInt(btn.getAttribute('data-sms-remove-recipient'), 10);
                        draftRecipientIds = draftRecipientIds.filter(function (x) { return x !== rid; });
                        renderRecipientsTable(draftRecipientIds);
                    });
                });
            }
            function renderPickerList(selectedIds) {
                if (!pickerList) return;
                var q = (pickerSearchQuery || '').trim().toLowerCase();
                var visible = 0;
                var html = '';
                smsAdminPickerAdmins.forEach(function (admin) {
                    var blob = adminSearchBlob(admin);
                    var matches = q === '' || blob.indexOf(q) !== -1;
                    if (!matches) return;
                    visible += 1;
                    var checked = selectedIds.indexOf(admin.id) !== -1;
                    var mobileNorm = normalizeNotifyMobile(admin.mobile);
                    var mobileShown = mobileNorm ? faNotifyDigits(mobileNorm) : faNotifyDigits(admin.mobile || '—');
                    var badge = admin.mobile_valid
                        ? '<span class="sms-picker-item__badge sms-picker-item__badge--ok">قابل ارسال</span>'
                        : '<span class="sms-picker-item__badge sms-picker-item__badge--bad">موبایل نامعتبر</span>';
                    html += '<label class="sms-picker-item' + (checked ? ' is-selected' : '') + '" data-admin-id="' + admin.id + '">'
                        + '<input type="checkbox" value="' + admin.id + '"' + (checked ? ' checked' : '') + '>'
                        + '<div>'
                        + '<p class="sms-picker-item__name">' + escNotifyHtml(admin.full_name || '—') + '</p>'
                        + '<p class="sms-picker-item__username">@' + escNotifyHtml(admin.username || '—') + '</p>'
                        + '</div>'
                        + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.2rem">'
                        + '<p class="sms-picker-item__mobile">' + escNotifyHtml(mobileShown) + '</p>'
                        + badge
                        + '</div>'
                        + '</label>';
                });
                pickerList.innerHTML = html;
                if (pickerNoResults) pickerNoResults.hidden = visible > 0;
                if (pickerCountEl) {
                    pickerCountEl.textContent = faNotifyDigits(visible) + ' مورد نمایش · ' + faNotifyDigits(selectedIds.length) + ' انتخاب‌شده';
                }
                pickerList.querySelectorAll('.sms-picker-item input[type="checkbox"]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        var id = parseInt(cb.value, 10);
                        if (!id) return;
                        if (cb.checked) {
                            if (pickerSelectionIds.indexOf(id) === -1) pickerSelectionIds.push(id);
                        } else {
                            pickerSelectionIds = pickerSelectionIds.filter(function (x) { return x !== id; });
                        }
                        var label = cb.closest('.sms-picker-item');
                        if (label) label.classList.toggle('is-selected', cb.checked);
                        if (pickerCountEl) {
                            pickerCountEl.textContent = faNotifyDigits(visible) + ' مورد نمایش · ' + faNotifyDigits(pickerSelectionIds.length) + ' انتخاب‌شده';
                        }
                    });
                });
            }
            function syncRecipientHiddenInputs(ids) {
                if (!adminLoginRecipientInputs) return;
                adminLoginRecipientInputs.innerHTML = ids.map(function (id) {
                    return '<input type="hidden" name="recipient_admin_ids[]" value="' + id + '">';
                }).join('');
                if (adminLoginRecipientCount) {
                    adminLoginRecipientCount.textContent = '(' + faNotifyDigits(ids.length) + ')';
                }
            }
            function openNotifyModal(el) { if (el) el.hidden = false; }
            function closeNotifyModal(el) { if (el) el.hidden = true; }

            savedRecipientIds = parseInitialRecipientIds();
            draftRecipientIds = savedRecipientIds.slice();
            syncAdminLoginFieldsVisibility();
            syncAdminLoginSelfFieldsVisibility();
            renderAdminLoginPreview();
            renderAdminLoginSelfPreview();

            if (adminLoginEnabled) {
                adminLoginEnabled.addEventListener('change', syncAdminLoginFieldsVisibility);
            }
            if (adminLoginSelfEnabled) {
                adminLoginSelfEnabled.addEventListener('change', syncAdminLoginSelfFieldsVisibility);
            }
            if (adminLoginMessage) {
                adminLoginMessage.addEventListener('input', renderAdminLoginPreview);
            }
            if (adminLoginSelfMessage) {
                adminLoginSelfMessage.addEventListener('input', renderAdminLoginSelfPreview);
            }
            document.querySelectorAll('[data-sms-login-pattern]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (!adminLoginMessage) return;
                    var token = chip.getAttribute('data-sms-login-pattern') || '';
                    var start = adminLoginMessage.selectionStart || 0;
                    var end = adminLoginMessage.selectionEnd || 0;
                    var val = adminLoginMessage.value || '';
                    adminLoginMessage.value = val.slice(0, start) + token + val.slice(end);
                    adminLoginMessage.focus();
                    renderAdminLoginPreview();
                });
            });
            document.querySelectorAll('[data-sms-self-pattern]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (!adminLoginSelfMessage) return;
                    var token = chip.getAttribute('data-sms-self-pattern') || '';
                    var start = adminLoginSelfMessage.selectionStart || 0;
                    var end = adminLoginSelfMessage.selectionEnd || 0;
                    var val = adminLoginSelfMessage.value || '';
                    adminLoginSelfMessage.value = val.slice(0, start) + token + val.slice(end);
                    adminLoginSelfMessage.focus();
                    renderAdminLoginSelfPreview();
                });
            });
            var openRecipientsBtn = document.getElementById('sms-admin-login-open-recipients');
            if (openRecipientsBtn) {
                openRecipientsBtn.addEventListener('click', function () {
                    draftRecipientIds = savedRecipientIds.slice();
                    renderRecipientsTable(draftRecipientIds);
                    openNotifyModal(recipientsModal);
                });
            }
            var openPickerBtn = document.getElementById('sms-admin-open-picker');
            if (openPickerBtn) {
                openPickerBtn.addEventListener('click', function () {
                    pickerSearchQuery = '';
                    if (pickerSearch) pickerSearch.value = '';
                    pickerSelectionIds = draftRecipientIds.slice();
                    renderPickerList(pickerSelectionIds);
                    openNotifyModal(pickerModal);
                });
            }
            if (pickerSearch) {
                pickerSearch.addEventListener('input', function () {
                    pickerSearchQuery = pickerSearch.value || '';
                    renderPickerList(pickerSelectionIds);
                });
            }
            var pickerApplyBtn = document.getElementById('sms-admin-picker-apply');
            if (pickerApplyBtn) {
                pickerApplyBtn.addEventListener('click', function () {
                    draftRecipientIds = pickerSelectionIds.slice().filter(function (id, idx, arr) {
                        return id > 0 && arr.indexOf(id) === idx;
                    });
                    renderRecipientsTable(draftRecipientIds);
                    syncRecipientHiddenInputs(draftRecipientIds);
                    closeNotifyModal(pickerModal);
                });
            }
            var recipientsSaveBtn = document.getElementById('sms-admin-recipients-save');
            if (recipientsSaveBtn) {
                recipientsSaveBtn.addEventListener('click', function () {
                    savedRecipientIds = draftRecipientIds.slice();
                    syncRecipientHiddenInputs(savedRecipientIds);
                    closeNotifyModal(recipientsModal);
                });
            }
            if (notifyForm) {
                notifyForm.addEventListener('submit', function () {
                    var ids = draftRecipientIds.length ? draftRecipientIds : savedRecipientIds;
                    syncRecipientHiddenInputs(ids);
                });
            }
            document.querySelectorAll('[data-sms-admin-modal-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var which = btn.getAttribute('data-sms-admin-modal-close');
                    if (which === 'recipients') {
                        draftRecipientIds = savedRecipientIds.slice();
                        closeNotifyModal(recipientsModal);
                    } else if (which === 'picker') {
                        closeNotifyModal(pickerModal);
                    }
                });
            });
            [recipientsModal, pickerModal].forEach(function (overlay) {
                if (!overlay) return;
                overlay.addEventListener('click', function (event) {
                    if (event.target !== overlay) return;
                    if (overlay.id === 'sms-admin-recipients-modal') {
                        draftRecipientIds = savedRecipientIds.slice();
                    }
                    overlay.hidden = true;
                });
            });

            mountSmsNotifyAdminPicker({
                enabledId: 'sms-customer-installment-payment-notify-enabled',
                fieldsId: 'sms-customer-installment-payment-notify-fields',
                blockId: 'sms-notify-block-customer-installment-payment',
                messageId: 'sms-customer-installment-payment-notify-message',
                previewId: 'sms-customer-installment-payment-notify-preview',
                recipientInputsId: 'sms-customer-installment-payment-notify-recipient-inputs',
                recipientCountId: 'sms-customer-installment-payment-notify-recipient-count',
                hiddenInputName: 'customer_installment_payment_recipient_admin_ids[]',
                recipientsModalId: 'sms-customer-installment-payment-recipients-modal',
                pickerModalId: 'sms-customer-installment-payment-picker-modal',
                recipientsTbodyId: 'sms-customer-installment-payment-recipients-tbody',
                recipientsEmptyId: 'sms-customer-installment-payment-recipients-empty',
                pickerListId: 'sms-customer-installment-payment-picker-list',
                pickerSearchId: 'sms-customer-installment-payment-picker-search',
                pickerCountId: 'sms-customer-installment-payment-picker-count',
                pickerNoResultsId: 'sms-customer-installment-payment-picker-no-results',
                openRecipientsBtnId: 'sms-customer-installment-payment-notify-open-recipients',
                openPickerBtnId: 'sms-customer-installment-payment-open-picker',
                pickerApplyBtnId: 'sms-customer-installment-payment-picker-apply',
                recipientsSaveBtnId: 'sms-customer-installment-payment-recipients-save',
                notifyFormId: 'sms-customer-installment-payment-notify-form',
                modalCloseAttr: 'data-sms-customer-installment-payment-modal-close',
                patternDataAttr: 'data-sms-customer-installment-payment-pattern',
                patternSelector: '[data-sms-customer-installment-payment-pattern]',
                renderPreview: function (tpl) {
                    return tpl
                        .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                        .replace(/\{customer_name\}/g, 'رضا محمدی')
                        .replace(/\{customer_first_name\}/g, 'رضا')
                        .replace(/\{customer_last_name\}/g, 'محمدی')
                        .replace(/\{customer_username\}/g, '1234567890')
                        .replace(/\{installment_number\}/g, '۳')
                        .replace(/\{installment_sequence\}/g, '۳')
                        .replace(/\{installment_amount\}/g, '۱,۲۵۰,۰۰۰')
                        .replace(/\{installment_amount_toman\}/g, '۱,۲۵۰,۰۰۰')
                        .replace(/\{loan_number\}/g, 'LN-۱۰۲۴')
                        .replace(/\{loan_code\}/g, 'LN-۱۰۲۴')
                        .replace(/\{payment_method\}/g, 'درگاه بانکی')
                        .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                }
            });

            mountSmsNotifyAdminPicker({
                enabledId: 'sms-customer-login-notify-enabled',
                fieldsId: 'sms-customer-login-notify-fields',
                blockId: 'sms-notify-block-customer-login',
                messageId: 'sms-customer-login-notify-message',
                previewId: 'sms-customer-login-notify-preview',
                recipientInputsId: 'sms-customer-login-notify-recipient-inputs',
                recipientCountId: 'sms-customer-login-notify-recipient-count',
                hiddenInputName: 'customer_login_recipient_admin_ids[]',
                recipientsModalId: 'sms-customer-login-recipients-modal',
                pickerModalId: 'sms-customer-login-picker-modal',
                recipientsTbodyId: 'sms-customer-login-recipients-tbody',
                recipientsEmptyId: 'sms-customer-login-recipients-empty',
                pickerListId: 'sms-customer-login-picker-list',
                pickerSearchId: 'sms-customer-login-picker-search',
                pickerCountId: 'sms-customer-login-picker-count',
                pickerNoResultsId: 'sms-customer-login-picker-no-results',
                openRecipientsBtnId: 'sms-customer-login-notify-open-recipients',
                openPickerBtnId: 'sms-customer-login-open-picker',
                pickerApplyBtnId: 'sms-customer-login-picker-apply',
                recipientsSaveBtnId: 'sms-customer-login-recipients-save',
                notifyFormId: 'sms-customer-login-notify-form',
                modalCloseAttr: 'data-sms-customer-login-modal-close',
                patternDataAttr: 'data-sms-customer-login-pattern',
                patternSelector: '[data-sms-customer-login-pattern]',
                renderPreview: function (tpl) {
                    return tpl
                        .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                        .replace(/\{customer_name\}/g, 'رضا محمدی')
                        .replace(/\{customer_first_name\}/g, 'رضا')
                        .replace(/\{customer_last_name\}/g, 'محمدی')
                        .replace(/\{customer_username\}/g, '1234567890')
                        .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                }
            });

            [
                {
                    enabledId: 'sms-customer-full-settlement-notify-enabled',
                    fieldsId: 'sms-customer-full-settlement-notify-fields',
                    blockId: 'sms-notify-block-full-settlement',
                    messageId: 'sms-customer-full-settlement-notify-message',
                    previewId: 'sms-customer-full-settlement-notify-preview',
                    recipientInputsId: 'sms-customer-full-settlement-notify-recipient-inputs',
                    recipientCountId: 'sms-customer-full-settlement-notify-recipient-count',
                    hiddenInputName: 'customer_full_settlement_recipient_admin_ids[]',
                    recipientsModalId: 'sms-customer-full-settlement-recipients-modal',
                    pickerModalId: 'sms-customer-full-settlement-picker-modal',
                    recipientsTbodyId: 'sms-customer-full-settlement-recipients-tbody',
                    recipientsEmptyId: 'sms-customer-full-settlement-recipients-empty',
                    pickerListId: 'sms-customer-full-settlement-picker-list',
                    pickerSearchId: 'sms-customer-full-settlement-picker-search',
                    pickerCountId: 'sms-customer-full-settlement-picker-count',
                    pickerNoResultsId: 'sms-customer-full-settlement-picker-no-results',
                    openRecipientsBtnId: 'sms-customer-full-settlement-notify-open-recipients',
                    openPickerBtnId: 'sms-customer-full-settlement-open-picker',
                    pickerApplyBtnId: 'sms-customer-full-settlement-picker-apply',
                    recipientsSaveBtnId: 'sms-customer-full-settlement-recipients-save',
                    notifyFormId: 'sms-customer-full-settlement-notify-form',
                    modalCloseAttr: 'data-sms-customer-full-settlement-modal-close',
                    patternDataAttr: 'data-sms-customer-full-settlement-pattern',
                    patternSelector: '[data-sms-customer-full-settlement-pattern]',
                    renderPreview: function (tpl) {
                        return tpl
                            .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                            .replace(/\{loan_number\}/g, 'LN-۱۰۲۴')
                            .replace(/\{settlement_amount\}/g, '۱۵,۰۰۰,۰۰۰')
                            .replace(/\{payment_method\}/g, 'درگاه بانکی')
                            .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                    }
                },
                {
                    enabledId: 'sms-customer-deposit-declaration-notify-enabled',
                    fieldsId: 'sms-customer-deposit-declaration-notify-fields',
                    blockId: 'sms-notify-block-deposit-declaration',
                    messageId: 'sms-customer-deposit-declaration-notify-message',
                    previewId: 'sms-customer-deposit-declaration-notify-preview',
                    recipientInputsId: 'sms-customer-deposit-declaration-notify-recipient-inputs',
                    recipientCountId: 'sms-customer-deposit-declaration-notify-recipient-count',
                    hiddenInputName: 'customer_deposit_declaration_recipient_admin_ids[]',
                    recipientsModalId: 'sms-customer-deposit-declaration-recipients-modal',
                    pickerModalId: 'sms-customer-deposit-declaration-picker-modal',
                    recipientsTbodyId: 'sms-customer-deposit-declaration-recipients-tbody',
                    recipientsEmptyId: 'sms-customer-deposit-declaration-recipients-empty',
                    pickerListId: 'sms-customer-deposit-declaration-picker-list',
                    pickerSearchId: 'sms-customer-deposit-declaration-picker-search',
                    pickerCountId: 'sms-customer-deposit-declaration-picker-count',
                    pickerNoResultsId: 'sms-customer-deposit-declaration-picker-no-results',
                    openRecipientsBtnId: 'sms-customer-deposit-declaration-notify-open-recipients',
                    openPickerBtnId: 'sms-customer-deposit-declaration-open-picker',
                    pickerApplyBtnId: 'sms-customer-deposit-declaration-picker-apply',
                    recipientsSaveBtnId: 'sms-customer-deposit-declaration-recipients-save',
                    notifyFormId: 'sms-customer-deposit-declaration-notify-form',
                    modalCloseAttr: 'data-sms-customer-deposit-declaration-modal-close',
                    patternDataAttr: 'data-sms-customer-deposit-declaration-pattern',
                    patternSelector: '[data-sms-customer-deposit-declaration-pattern]',
                    renderPreview: function (tpl) {
                        return tpl
                            .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                            .replace(/\{installment_number\}/g, '۲')
                            .replace(/\{deposit_amount\}/g, '۸۵۰,۰۰۰')
                            .replace(/\{loan_number\}/g, 'LN-۱۰۲۴')
                            .replace(/\{payment_method\}/g, 'واریز بانکی')
                            .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                    }
                },
                {
                    enabledId: 'sms-customer-support-ticket-notify-enabled',
                    fieldsId: 'sms-customer-support-ticket-notify-fields',
                    blockId: 'sms-notify-block-support-ticket',
                    messageId: 'sms-customer-support-ticket-notify-message',
                    previewId: 'sms-customer-support-ticket-notify-preview',
                    recipientInputsId: 'sms-customer-support-ticket-notify-recipient-inputs',
                    recipientCountId: 'sms-customer-support-ticket-notify-recipient-count',
                    hiddenInputName: 'customer_support_ticket_recipient_admin_ids[]',
                    recipientsModalId: 'sms-customer-support-ticket-recipients-modal',
                    pickerModalId: 'sms-customer-support-ticket-picker-modal',
                    recipientsTbodyId: 'sms-customer-support-ticket-recipients-tbody',
                    recipientsEmptyId: 'sms-customer-support-ticket-recipients-empty',
                    pickerListId: 'sms-customer-support-ticket-picker-list',
                    pickerSearchId: 'sms-customer-support-ticket-picker-search',
                    pickerCountId: 'sms-customer-support-ticket-picker-count',
                    pickerNoResultsId: 'sms-customer-support-ticket-picker-no-results',
                    openRecipientsBtnId: 'sms-customer-support-ticket-notify-open-recipients',
                    openPickerBtnId: 'sms-customer-support-ticket-open-picker',
                    pickerApplyBtnId: 'sms-customer-support-ticket-picker-apply',
                    recipientsSaveBtnId: 'sms-customer-support-ticket-recipients-save',
                    notifyFormId: 'sms-customer-support-ticket-notify-form',
                    modalCloseAttr: 'data-sms-customer-support-ticket-modal-close',
                    patternDataAttr: 'data-sms-customer-support-ticket-pattern',
                    patternSelector: '[data-sms-customer-support-ticket-pattern]',
                    renderPreview: function (tpl) {
                        return tpl
                            .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                            .replace(/\{ticket_subject\}/g, 'مشکل ورود به پنل')
                            .replace(/\{ticket_id\}/g, '۱۲۴')
                            .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                    }
                },
                {
                    enabledId: 'sms-customer-loan-request-notify-enabled',
                    fieldsId: 'sms-customer-loan-request-notify-fields',
                    blockId: 'sms-notify-block-loan-request',
                    messageId: 'sms-customer-loan-request-notify-message',
                    previewId: 'sms-customer-loan-request-notify-preview',
                    recipientInputsId: 'sms-customer-loan-request-notify-recipient-inputs',
                    recipientCountId: 'sms-customer-loan-request-notify-recipient-count',
                    hiddenInputName: 'customer_loan_request_recipient_admin_ids[]',
                    recipientsModalId: 'sms-customer-loan-request-recipients-modal',
                    pickerModalId: 'sms-customer-loan-request-picker-modal',
                    recipientsTbodyId: 'sms-customer-loan-request-recipients-tbody',
                    recipientsEmptyId: 'sms-customer-loan-request-recipients-empty',
                    pickerListId: 'sms-customer-loan-request-picker-list',
                    pickerSearchId: 'sms-customer-loan-request-picker-search',
                    pickerCountId: 'sms-customer-loan-request-picker-count',
                    pickerNoResultsId: 'sms-customer-loan-request-picker-no-results',
                    openRecipientsBtnId: 'sms-customer-loan-request-notify-open-recipients',
                    openPickerBtnId: 'sms-customer-loan-request-open-picker',
                    pickerApplyBtnId: 'sms-customer-loan-request-picker-apply',
                    recipientsSaveBtnId: 'sms-customer-loan-request-recipients-save',
                    notifyFormId: 'sms-customer-loan-request-notify-form',
                    modalCloseAttr: 'data-sms-customer-loan-request-modal-close',
                    patternDataAttr: 'data-sms-customer-loan-request-pattern',
                    patternSelector: '[data-sms-customer-loan-request-pattern]',
                    renderPreview: function (tpl) {
                        return tpl
                            .replace(/\{customer_full_name\}/g, 'رضا محمدی')
                            .replace(/\{loan_type\}/g, 'وام خرید کالا')
                            .replace(/\{request_amount\}/g, '۵۰,۰۰۰,۰۰۰')
                            .replace(/\{request_id\}/g, '۸۹')
                            .replace(/\{app_name\}/g, smsLoginNotifyAppName);
                    }
                }
            ].forEach(function (cfg) { mountSmsNotifyAdminPicker(cfg); });

            function initJalaliPicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    console.error('pDatepicker is not available.');
                    return;
                }

                window.jQuery('#sms-from-jdate, #sms-to-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: {
                        calendarSwitch: false
                    },
                });
            }

            if (window.jQuery) {
                window.jQuery(function () {
                    initJalaliPicker();
                });
            } else {
                initJalaliPicker();
            }
        })();
    </script>
@endpush
