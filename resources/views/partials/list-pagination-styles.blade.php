<style>
    .mg-pagination-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem 1rem;
        padding: 0.65rem 0.85rem;
        border-top: 1px solid var(--border, rgba(148, 163, 184, 0.35));
        background: color-mix(in oklab, var(--bg-card, #fff) 92%, transparent);
    }
    .mg-pagination-bar--standalone {
        border: 1px solid var(--border, rgba(148, 163, 184, 0.35));
        border-radius: 0.75rem;
        margin-top: 0.65rem;
    }
    .mg-pagination-bar__start {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.55rem 0.85rem;
        min-width: 0;
    }
    .mg-pagination-bar__summary {
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--muted, #64748b);
        line-height: 1.5;
    }
    .mg-per-page-form {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
        margin: 0;
    }
    .mg-per-page-form label {
        font-size: 0.74rem;
        font-weight: 800;
        color: var(--muted, #64748b);
        white-space: nowrap;
    }
    .mg-per-page-form select {
        font-family: inherit;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text, #0f172a);
        border: 1px solid var(--border, rgba(148, 163, 184, 0.45));
        border-radius: 0.55rem;
        padding: 0.32rem 1.75rem 0.32rem 0.55rem;
        background: var(--bg-card, #fff);
        cursor: pointer;
        min-width: 4.25rem;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: left 0.45rem center;
        background-size: 0.85rem;
    }
    html[dir="rtl"] .mg-per-page-form select {
        padding: 0.32rem 0.55rem 0.32rem 1.75rem;
        background-position: right 0.45rem center;
    }
    .mg-per-page-form select:focus {
        outline: 2px solid rgba(37, 99, 235, 0.35);
        outline-offset: 1px;
        border-color: rgba(37, 99, 235, 0.45);
    }
    .mg-pager-nav { width: 100%; }
    @media (min-width: 640px) {
        .mg-pager-nav { width: auto; }
    }
    .mg-pager-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
    }
    .mg-pager-item span,
    .mg-pager-item a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.28rem;
        min-width: 2.15rem;
        min-height: 2.15rem;
        padding: 0.28rem 0.55rem;
        border-radius: 0.55rem;
        border: 1px solid var(--border, rgba(148, 163, 184, 0.4));
        font-size: 0.76rem;
        font-weight: 800;
        text-decoration: none;
        color: var(--text, #0f172a);
        background: var(--bg-card, #fff);
        font-variant-numeric: tabular-nums;
    }
    .mg-pager-item a:hover {
        border-color: rgba(37, 99, 235, 0.4);
        background: var(--primary-soft, #eff6ff);
        color: var(--primary-dark, #1d4ed8);
    }
    .mg-pager-item.is-active span {
        background: linear-gradient(180deg, var(--primary, #2563eb), var(--primary-dark, #1d4ed8));
        border-color: var(--primary-dark, #1d4ed8);
        color: #fff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
    }
    .mg-pager-item.is-disabled span {
        opacity: 0.45;
        cursor: not-allowed;
        background: transparent;
    }
    @media (max-width: 520px) {
        .mg-pagination-bar {
            flex-direction: column;
            align-items: stretch;
        }
        .mg-pagination-bar__start {
            flex-direction: column;
            align-items: stretch;
        }
        .mg-per-page-form {
            width: 100%;
            justify-content: space-between;
        }
        .mg-per-page-form select {
            flex: 1;
            max-width: 8rem;
        }
    }
</style>
