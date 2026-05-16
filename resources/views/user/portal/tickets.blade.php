@extends('layouts.user.app')

@section('title', $pageTitle)

@push('head')
    @include('partials.support-ticket-chat-styles')
    <style>
        .ut-page { width: 100%; max-width: 100%; }
        .ut-head { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; justify-content: space-between; margin-bottom: 0.85rem; }
        .ut-h1 { margin: 0; font-size: 1.08rem; font-weight: 800; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
        .ut-lead { margin: 0.2rem 0 0; font-size: 0.82rem; color: var(--muted); line-height: 1.55; width: 100%; }
        .ut-btn { font-family: inherit; font-size: 0.78rem; font-weight: 800; padding: 0.48rem 0.85rem; border-radius: 0.62rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 0.38rem; }
        .ut-btn--pri { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; border-color: var(--primary-dark); }
        .ut-btn--ghost { background: transparent; }
        .ut-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 0.75rem; }
        .ut-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.8rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; font-family: inherit; }
        .ut-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .ut-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
        .ut-search { display: flex; gap: 0.4rem; flex: 1; min-width: min(100%, 14rem); }
        .ut-search input { flex: 1; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.45rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .ut-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); min-height: 6rem; }
        .ut-tbl { width: 100%; border-collapse: collapse; font-size: 0.76rem; min-width: 48rem; }
        .ut-tbl th, .ut-tbl td { padding: 0.5rem 0.55rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .ut-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .ut-tbl tr:last-child td { border-bottom: 0; }
        .ut-subject { font-weight: 800; max-width: 12rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ut-excerpt { max-width: 14rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ut-dt { white-space: nowrap; color: var(--muted); font-weight: 600; }
        .ut-status { display: inline-block; padding: 0.14rem 0.48rem; border-radius: 999px; font-size: 0.67rem; font-weight: 800; background: var(--primary-soft); color: var(--primary-dark); }
        .ut-empty, .ut-loading { padding: 2rem 1rem; text-align: center; color: var(--muted); font-size: 0.84rem; }
        .ut-cards { display: none; flex-direction: column; gap: 0.65rem; padding: 0.65rem; }
        .ut-card { border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.65rem; background: var(--bg-card); }
        .ut-card h3 { margin: 0 0 0.35rem; font-size: 0.88rem; font-weight: 800; }
        .ut-card p { margin: 0.2rem 0; font-size: 0.76rem; color: var(--muted); }
        .ut-card__foot { margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.35rem; }
        @media (max-width: 720px) {
            .ut-desktop-only { display: none !important; }
            .ut-cards { display: flex !important; }
        }
        #ut-compose-dialog, #ut-detail-dialog {
            display: none; padding: 0; border: none; border-radius: 1rem;
            max-width: min(96vw, 44rem); width: min(96vw, 44rem);
            max-height: min(92vh, 52rem); background: var(--bg-card); color: var(--text);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28); overflow: hidden;
        }
        #ut-compose-dialog[open], #ut-detail-dialog[open] {
            display: flex; flex-direction: column; position: fixed; inset: 0; margin: auto;
        }
        #ut-compose-dialog::backdrop, #ut-detail-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
        }
        .ut-dialog-inner { display: flex; flex-direction: column; min-height: 0; flex: 1; position: relative; }
        .ut-dialog-close { position: absolute; top: 0.45rem; inset-inline-end: 0.45rem; width: 2rem; height: 2rem; border: none; background: transparent; color: var(--muted); font-size: 1.35rem; cursor: pointer; z-index: 2; }
        .ut-dialog-head { flex-shrink: 0; padding: 1rem 2.5rem 0.5rem 1rem; border-bottom: 1px dashed var(--border); }
        .ut-dialog-title { margin: 0; font-size: 1rem; font-weight: 800; }
        .ut-dialog-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 0.85rem 1rem; }
        .ut-dialog-footer { flex-shrink: 0; padding: 0.75rem 1rem 1rem; border-top: 1px dashed var(--border); display: flex; gap: 0.45rem; justify-content: flex-end; }
        .ut-field { margin-bottom: 0.75rem; }
        .ut-field label { display: block; font-size: 0.74rem; font-weight: 800; color: var(--muted); margin-bottom: 0.28rem; }
        .ut-field input[type="text"], .ut-field input[type="file"], .ut-field textarea {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem;
            padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem;
        }
        .ut-ck-wrap .ck-editor__editable { min-height: 9rem; max-height: 14rem; direction: rtl; text-align: right; }
        .ut-detail-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.45rem; margin-bottom: 0.75rem; font-size: 0.78rem; }
        .ut-detail-meta span { display: block; color: var(--muted); font-weight: 800; margin-bottom: 0.1rem; }
        .ut-detail-body { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.65rem; line-height: 1.7; font-size: 0.84rem; }
        .ut-detail-att { margin-top: 0.45rem; display: flex; flex-wrap: wrap; gap: 0.35rem; }
        .ut-detail-att a { font-size: 0.74rem; font-weight: 700; color: var(--primary-dark); text-decoration: none; }
        .ut-dialog-inner--detail { display: flex; flex-direction: column; flex: 1; min-height: 0; }
        .ut-pagination { margin-top: 0.75rem; display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap; }
    </style>
@endpush

@section('content')
    <div class="ut-page">
        <div class="ut-head">
            <div>
                <h1 class="ut-h1"><i class="fa-solid fa-ticket" aria-hidden="true"></i> {{ $pageTitle }}</h1>
                <p class="ut-lead">ارسال درخواست به پشتیبانی، مشاهده تیکت‌های دریافتی و پیگیری گفتگوها.</p>
            </div>
            <button type="button" class="ut-btn ut-btn--pri" id="ut-open-compose">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                ارسال تیکت جدید
            </button>
        </div>

        <div class="ut-tabs" role="tablist">
            <button type="button" class="ut-tab is-active" data-ut-tab="received">تیکت‌های دریافتی</button>
            <button type="button" class="ut-tab" data-ut-tab="sent">تیکت‌های ارسالی</button>
        </div>

        <div class="ut-toolbar">
            <div class="ut-search">
                <input type="search" id="ut-search-input" placeholder="جستجو در موضوع یا متن…" autocomplete="off">
                <button type="button" class="ut-btn ut-btn--ghost" id="ut-search-btn"><i class="fa-solid fa-magnifying-glass"></i> جستجو</button>
            </div>
        </div>

        <div class="ut-wrap">
            <div class="ut-loading" id="ut-loading">در حال بارگذاری…</div>
            <table class="ut-tbl ut-desktop-only" id="ut-table" hidden>
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>موضوع</th>
                        <th>وضعیت</th>
                        <th>خلاصه</th>
                        <th>ضمیمه</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="ut-tbody"></tbody>
            </table>
            <div class="ut-cards" id="ut-cards"></div>
            <div class="ut-empty" id="ut-empty" hidden>تیکتی یافت نشد.</div>
        </div>
        <div class="ut-pagination" id="ut-pagination" hidden></div>
    </div>

    <dialog id="ut-compose-dialog">
        <div class="ut-dialog-inner">
            <button type="button" class="ut-dialog-close" data-ut-close-compose aria-label="بستن">&times;</button>
            <div class="ut-dialog-head"><h2 class="ut-dialog-title">ارسال تیکت جدید</h2></div>
            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <div class="ut-dialog-scroll" id="ut-compose-scroll">
                    <div class="ut-field">
                        <label for="ut-subject">عنوان</label>
                        <input type="text" id="ut-subject" name="subject" required maxlength="255">
                    </div>
                    <div class="ut-field ut-ck-wrap">
                        <label for="ut-compose-body">متن</label>
                        <textarea id="ut-compose-body" name="body_html" rows="5"></textarea>
                    </div>
                    <div class="ut-field">
                        <label for="ut-compose-file">ضمیمه (اختیاری)</label>
                        <input type="file" id="ut-compose-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                    </div>
                </div>
                <div class="ut-dialog-footer">
                    <button type="button" class="ut-btn ut-btn--ghost" data-ut-close-compose>انصراف</button>
                    <button type="submit" class="ut-btn ut-btn--pri" id="ut-compose-submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="ut-detail-dialog">
        <div class="ut-dialog-inner ut-dialog-inner--detail">
            <button type="button" class="ut-dialog-close" data-ut-close-detail aria-label="بستن">&times;</button>
            <div class="ut-dialog-head"><h2 class="ut-dialog-title" id="ut-detail-title">جزئیات</h2></div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="ut-detail-body"></div>
                <div class="st-detail-reply-zone" id="ut-reply-wrap" hidden>
                    <form id="ut-reply-form" enctype="multipart/form-data" novalidate>
                        <div class="ut-field ut-ck-wrap">
                            <label for="ut-reply-body">پاسخ شما</label>
                            <textarea id="ut-reply-body" name="body_html" rows="3"></textarea>
                        </div>
                        <div class="ut-field">
                            <label for="ut-reply-file">ضمیمه (اختیاری)</label>
                            <input type="file" id="ut-reply-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        </div>
                        <button type="submit" class="ut-btn ut-btn--pri" id="ut-reply-submit">
                            <i class="fa-solid fa-reply" aria-hidden="true"></i>
                            ارسال پاسخ
                        </button>
                    </form>
                </div>
            </div>
            <div class="ut-dialog-footer">
                <button type="button" class="ut-btn ut-btn--ghost" data-ut-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__UT_PAGE__ = {
            listUrl: @json(route('user.tickets.list')),
            storeUrl: @json(route('user.tickets.store')),
            ticketsBase: @json(url('user/tickets')),
            csrf: @json(csrf_token()),
        };
    </script>
    @vite(['resources/js/admin-tickets-ckeditor.js', 'resources/js/user-tickets-portal.js'])
@endpush
