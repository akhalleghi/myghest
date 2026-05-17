<style>
    .ctk-page { width: 100%; max-width: 100%; }
    .ctk-head { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem; }
    .ctk-head-text { flex: 1 1 12rem; min-width: 0; }
    .ctk-h1 { margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
    .ctk-lead { margin: 0.25rem 0 0; font-size: 0.78rem; color: var(--muted); line-height: 1.55; }
    .ctk-btn { font-family: inherit; font-size: 0.76rem; font-weight: 800; padding: 0.45rem 0.8rem; border-radius: 0.62rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; }
    .ctk-btn--pri { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; border-color: var(--primary-dark); }
    .ctk-btn--ghost { background: transparent; }
    .ctk-tabs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.65rem; }
    .ctk-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.42rem 0.75rem; font-size: 0.76rem; font-weight: 700; color: var(--muted); background: var(--bg-card); cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.32rem; }
    .ctk-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
    .ctk-tab-badge { font-size: 0.66rem; font-weight: 800; padding: 0.1rem 0.4rem; border-radius: 999px; background: rgba(148, 163, 184, 0.2); }
    .ctk-tab.is-active .ctk-tab-badge { background: rgba(37, 99, 235, 0.18); }
    .ctk-toolbar { margin-bottom: 0.65rem; }
    .ctk-search form { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
    .ctk-search input { flex: 1 1 12rem; min-width: min(100%, 10rem); border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.42rem 0.6rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .ctk-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); transition: opacity 0.2s ease; }
    .ctk-wrap[aria-busy="true"] { opacity: 0.65; pointer-events: none; }
    .ctk-tbl { width: 100%; border-collapse: collapse; font-size: 0.74rem; min-width: 44rem; }
    .ctk-tbl th, .ctk-tbl td { padding: 0.48rem 0.52rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
    .ctk-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
    .ctk-tbl tr:last-child td { border-bottom: 0; }
    .ctk-subject { font-weight: 800; color: var(--text); max-width: 10rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ctk-excerpt { max-width: 14rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ctk-party { max-width: 9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); font-weight: 700; }
    .ctk-dt { white-space: nowrap; color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
    .ctk-att { color: var(--primary-dark); font-size: 0.85rem; }
    .ctk-empty { padding: 1.75rem 1rem; text-align: center; color: var(--muted); font-size: 0.82rem; }
    .ctk-pagination { margin-top: 0.65rem; font-size: 0.78rem; }
    .ctk-pagination nav { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: center; }
    .ctk-pagination a, .ctk-pagination span { padding: 0.28rem 0.55rem; border-radius: 0.45rem; border: 1px solid var(--border); text-decoration: none; color: var(--text); }
    .ctk-pagination .active span { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); font-weight: 800; }
    .ctk-hint { margin: 0.28rem 0 0; font-size: 0.72rem; color: var(--muted); line-height: 1.5; }

    #ctk-compose-dialog,
    #ctk-detail-dialog {
        display: none; padding: 0; border: none; border-radius: 1rem;
        max-width: min(96vw, 42rem); width: min(96vw, 42rem);
        max-height: min(92vh, 50rem); background: var(--bg-card); color: var(--text);
        box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28); overflow: hidden;
    }
    #ctk-compose-dialog[open], #ctk-detail-dialog[open] {
        display: flex; flex-direction: column; position: fixed; inset: 0; margin: auto;
    }
    #ctk-compose-dialog::backdrop, #ctk-detail-dialog::backdrop {
        background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
    }
    .ctk-dialog-inner { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; width: 100%; max-height: inherit; position: relative; overflow: hidden; }
    .ctk-dialog-close { position: absolute; top: 0.45rem; inset-inline-end: 0.45rem; width: 2rem; height: 2rem; border: none; background: transparent; color: var(--muted); font-size: 1.35rem; cursor: pointer; z-index: 2; border-radius: 0.4rem; }
    .ctk-dialog-close:hover { background: var(--primary-soft); color: var(--text); }
    .ctk-dialog-head { flex-shrink: 0; padding: 0.85rem 2.25rem 0.45rem 0.85rem; border-bottom: 1px dashed var(--border); }
    .ctk-dialog-title { margin: 0; font-size: 0.92rem; font-weight: 800; }
    #ctk-compose-form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
    .ctk-dialog-scroll { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; padding: 0.75rem 1rem; -webkit-overflow-scrolling: touch; }
    .ctk-dialog-inner--detail { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
    .ctk-dialog-footer { flex-shrink: 0; padding: 0.65rem 0.85rem 0.85rem; border-top: 1px dashed var(--border); display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: flex-end; background: var(--bg-card); }
    .ctk-field { margin-bottom: 0.65rem; }
    .ctk-field label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.25rem; }
    .ctk-field input[type="text"], .ctk-field input[type="file"], .ctk-field select { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.45rem 0.58rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .ctk-ck-wrap .ck-editor__editable { min-height: 8rem; max-height: 14rem; direction: rtl; text-align: right; }
    .ctk-detail-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem 0.55rem; margin-bottom: 0.75rem; font-size: 0.76rem; }
    @media (max-width: 520px) { .ctk-detail-meta { grid-template-columns: 1fr; } }
    .ctk-detail-meta-item span { display: block; color: var(--muted); font-weight: 800; margin-bottom: 0.1rem; }
    .ctk-detail-meta-item strong { font-weight: 700; color: var(--text); }
    .ctk-detail-reply { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--border); }
    .ctk-status-row { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-bottom: 0.65rem; }
    .ctk-status-row select { flex: 1; min-width: 9rem; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.4rem 0.52rem; font-family: inherit; font-size: 0.78rem; background: var(--bg-card); color: var(--text); }
    .ctk-sms-option { margin-top: 0.3rem; padding-top: 0.4rem; border-top: 1px dashed var(--border); }
    .ctk-sms-option[hidden] { display: none !important; }
    .ctk-sms-check { display: inline-flex; align-items: center; gap: 0.38rem; font-size: 0.74rem; font-weight: 700; color: var(--text); cursor: pointer; margin-bottom: 0.3rem; }
    .ctk-sms-check input { accent-color: var(--primary); width: 1rem; height: 1rem; }
    .ctk-sms-fields[hidden] { display: none !important; }
    .ctk-sms-fields label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--muted); margin-bottom: 0.22rem; }
    .ctk-sms-fields textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.45rem 0.58rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.76rem; line-height: 1.6; resize: vertical; min-height: 4rem; }
    .ctk-sms-preview-wrap { margin-top: 0.45rem; }
    .ctk-sms-preview-wrap[hidden] { display: none !important; }
    .ctk-sms-preview-meta { display: block; font-size: 0.68rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 0.3rem; }
    .ctk-sms-preview { margin: 0; padding: 0.5rem 0.6rem; border: 1px dashed var(--border); border-radius: 0.55rem; background: color-mix(in oklab, var(--primary-soft) 55%, var(--bg-card)); font-family: inherit; font-size: 0.74rem; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
    html[data-theme="dark"] .ctk-sms-preview { background: rgba(37, 99, 235, 0.12); }
    .ctk-status { display: inline-block; padding: 0.12rem 0.45rem; border-radius: 999px; font-size: 0.65rem; font-weight: 800; background: var(--primary-soft); color: var(--primary-dark); white-space: nowrap; }
    .ctk-status--closed { background: rgba(148, 163, 184, 0.22); color: var(--muted); }
    .ctk-status--hold { background: rgba(245, 158, 11, 0.18); color: #b45309; }
    @media (max-width: 640px) {
        .ctk-tbl { min-width: 36rem; font-size: 0.7rem; }
        .ctk-head { flex-direction: column; align-items: stretch; }
        .ctk-head .ctk-btn--pri { width: 100%; justify-content: center; }
    }
</style>
