<style>
    /* چیدمان مودال جزئیات تیکت */
    .st-detail-layout {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }
    #tk-detail-dialog[open] .st-detail-layout,
    #ut-detail-dialog[open] .st-detail-layout {
        min-height: min(58vh, 26rem);
    }
    .st-detail-messages {
        flex: 1 1 auto;
        min-height: 10rem;
        overflow-y: auto;
        padding: 0.75rem 1.15rem;
        -webkit-overflow-scrolling: touch;
    }
    .st-detail-reply-zone {
        flex-shrink: 0;
        max-height: 42%;
        overflow-y: auto;
        padding: 0.5rem 1.15rem 0.7rem;
        border-top: 1px dashed var(--border);
        background: color-mix(in oklab, var(--bg-card) 92%, var(--primary-soft));
    }
    html[data-theme="dark"] .st-detail-reply-zone {
        background: rgba(15, 23, 42, 0.45);
    }
    .st-detail-reply-zone .tk-field,
    .st-detail-reply-zone .ut-field {
        margin-bottom: 0.45rem;
    }
    .st-detail-reply-zone .tk-ck-wrap .ck-editor__editable,
    .st-detail-reply-zone .ut-ck-wrap .ck-editor__editable {
        min-height: 3rem !important;
        max-height: 5.25rem !important;
    }
    .st-detail-reply-zone .tk-btn--pri,
    .st-detail-reply-zone .ut-btn--pri {
        font-size: 0.74rem;
        padding: 0.38rem 0.7rem;
    }

    .st-detail-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem 0.65rem;
        margin-bottom: 0.75rem;
        padding: 0.15rem 0.1rem 0;
        font-size: 0.78rem;
    }
    @media (max-width: 560px) {
        .st-detail-meta { grid-template-columns: 1fr; }
    }
    .st-detail-meta-item span {
        display: block;
        color: var(--muted);
        font-weight: 800;
        margin-bottom: 0.1rem;
    }
    .st-detail-meta-item strong {
        font-weight: 700;
        color: var(--text);
    }

    /* حباب‌های گفتگو */
    .st-chat {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        padding: 0.2rem 0.1rem 0.35rem;
    }
    .st-chat-empty {
        margin: 0;
        text-align: center;
        color: var(--muted);
        font-size: 0.8rem;
        padding: 1rem 0.5rem;
    }
    .st-msg {
        display: flex;
        flex-direction: column;
        gap: 0.22rem;
        max-width: min(100%, 85%);
    }
    .st-msg--customer {
        align-self: flex-start;
    }
    .st-msg--staff {
        align-self: flex-end;
    }
    .st-msg__meta {
        font-size: 0.67rem;
        font-weight: 700;
        color: var(--muted);
        padding: 0 0.4rem;
    }
    .st-msg--staff .st-msg__meta {
        text-align: end;
    }
    .st-msg__bubble {
        padding: 0.55rem 0.72rem;
        border-radius: 0.85rem;
        line-height: 1.65;
        font-size: 0.82rem;
        word-break: break-word;
        overflow-x: auto;
    }
    .st-msg__bubble p {
        margin: 0 0 0.35rem;
    }
    .st-msg__bubble p:last-child {
        margin-bottom: 0;
    }
    .st-msg--customer .st-msg__bubble {
        background: linear-gradient(180deg, rgba(37, 99, 235, 0.14), rgba(37, 99, 235, 0.07));
        border: 1px solid rgba(37, 99, 235, 0.28);
        border-end-end-radius: 0.22rem;
        color: var(--text);
    }
    .st-msg--staff .st-msg__bubble {
        background: linear-gradient(180deg, rgba(16, 185, 129, 0.16), rgba(16, 185, 129, 0.08));
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-end-start-radius: 0.22rem;
        color: var(--text);
    }
    html[data-theme="dark"] .st-msg--customer .st-msg__bubble {
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.22), rgba(59, 130, 246, 0.1));
        border-color: rgba(96, 165, 250, 0.35);
    }
    html[data-theme="dark"] .st-msg--staff .st-msg__bubble {
        background: linear-gradient(180deg, rgba(16, 185, 129, 0.22), rgba(16, 185, 129, 0.1));
        border-color: rgba(52, 211, 153, 0.35);
    }
    .st-msg__att {
        display: flex;
        flex-wrap: wrap;
        gap: 0.32rem;
        padding: 0 0.2rem;
    }
    .st-msg__att a {
        font-size: 0.7rem;
        font-weight: 700;
        text-decoration: none;
        color: var(--primary-dark);
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        padding: 0.22rem 0.45rem;
        border: 1px solid var(--border);
        border-radius: 0.45rem;
        background: var(--bg-card);
    }

    .st-inline-alert {
        margin: 0 0 0.65rem;
        padding: 0.45rem 0.55rem;
        border-radius: 0.55rem;
        background: rgba(245, 158, 11, 0.14);
        border: 1px solid rgba(245, 158, 11, 0.35);
        color: #b45309;
        font-size: 0.76rem;
        font-weight: 700;
    }
    .st-inline-alert[hidden] {
        display: none !important;
    }

    #tk-detail-dialog,
    #ut-detail-dialog {
        max-width: min(96vw, 50rem);
        width: min(96vw, 50rem);
    }
</style>
