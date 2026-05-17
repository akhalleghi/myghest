@extends('layouts.user.app')

@section('title', $pageTitle)

@push('head')
    @include('partials.list-pagination-styles')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .dep-page {
            width: 100%;
            max-width: 100%;
            margin-inline: 0;
            box-sizing: border-box;
        }
        .dep-toolbar {
            display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between;
            margin-bottom: 0.85rem;
        }
        .dep-search {
            flex: 1 1 12rem; min-width: 0; display: flex; gap: 0.4rem; align-items: center;
        }
        .dep-search input {
            flex: 1; min-width: 0; padding: 0.45rem 0.6rem; border-radius: 0.65rem; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.86rem;
        }
        .dep-desktop-only { display: block; }
        .dep-mobile-only { display: none; }
        .dep-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); }
        .dep-tbl { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 42rem; }
        .dep-tbl th, .dep-tbl td { padding: 0.48rem 0.5rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .dep-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .dep-tbl td { color: var(--muted); font-weight: 600; }
        .dep-tbl tr:last-child td { border-bottom: 0; }
        .dep-cards { flex-direction: column; gap: 0.65rem; padding: 0.15rem 0; }
        .dep-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 0.65rem 0.72rem 0.75rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        html[data-theme="dark"] .dep-card { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2); }
        .dep-card__title { margin: 0 0 0.45rem; font-size: 0.88rem; font-weight: 800; color: var(--text); line-height: 1.35; }
        .dep-card__kv { margin: 0; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.76rem; }
        .dep-card__kv-row { display: grid; grid-template-columns: minmax(0, 5.5rem) minmax(0, 1fr); gap: 0.3rem 0.5rem; align-items: start; line-height: 1.4; }
        .dep-card__kv-row dt { margin: 0; font-weight: 800; color: var(--muted); }
        .dep-card__kv-row dd { margin: 0; font-weight: 700; color: var(--text); text-align: end; word-break: break-word; }
        .dep-card__foot { margin-top: 0.55rem; padding-top: 0.5rem; border-top: 1px dashed rgba(148, 163, 184, 0.45); }
        .dep-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; }
        .dep-btn { font-family: inherit; font-size: 0.72rem; font-weight: 700; padding: 0.32rem 0.5rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--bg-card); cursor: pointer; color: var(--text); }
        .dep-btn--pri { background: var(--primary); color: #fff; border-color: var(--primary-dark); }
        .dep-btn--danger { color: #b91c1c; border-color: rgba(185, 28, 28, 0.35); }
        .dep-btn--note { font-size: 0.68rem; padding: 0.28rem 0.48rem; gap: 0.3rem; }
        .dep-status-note { margin-top: 0.4rem; }
        #dep-admin-note-dialog {
            display: none;
            width: min(96vw, 24rem);
            max-width: min(96vw, 24rem);
            margin: 0;
            box-sizing: border-box;
        }
        /* مرکز واقعی viewport؛ با RTL و استایل پیش‌فرض <dialog> سازگار */
        #dep-admin-note-dialog[open] {
            display: block;
            position: fixed;
            inset: 0;
            width: min(96vw, 24rem);
            max-width: min(96vw, 24rem);
            height: fit-content;
            max-height: min(88vh, 36rem);
            margin: auto;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }
        .dep-admin-note-body {
            margin: 0;
            font-size: 0.86rem;
            line-height: 1.55;
            color: var(--text);
            white-space: pre-wrap;
            word-break: break-word;
        }
        #dep-view-dialog {
            display: none;
            width: min(96vw, 46rem);
            max-width: min(96vw, 46rem);
            margin: 0;
            border: 0;
            padding: 0;
            background: transparent;
            box-sizing: border-box;
        }
        #dep-view-dialog[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            width: min(96vw, 46rem);
            max-width: min(96vw, 46rem);
            height: fit-content;
            max-height: min(92vh, 44rem);
            margin: auto;
            z-index: 60;
            overflow: hidden;
        }
        #dep-view-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45);
        }
        .dep-view-dialog__box {
            max-height: min(92vh, 44rem);
            width: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
            position: relative;
            background: var(--bg-card);
            border-radius: 0.85rem;
            border: 1px solid var(--border);
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
        }
        html[data-theme="dark"] .dep-view-dialog__box {
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.45);
        }
        .dep-view-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: 0 0.15rem 0.35rem 0;
        }
        .dep-view-footer {
            flex-shrink: 0;
            margin-top: 0.25rem;
            padding-top: 0.65rem;
            border-top: 1px dashed var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.45rem;
        }
        .dep-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem 0.75rem;
            align-items: start;
            margin-bottom: 0.75rem;
        }
        @media (max-width: 520px) {
            .dep-detail-grid { grid-template-columns: 1fr; }
        }
        .dep-detail-item {
            display: flex;
            gap: 0.55rem;
            align-items: flex-start;
            padding: 0.55rem 0.6rem;
            border-radius: 0.65rem;
            border: 1px solid var(--border);
            background: var(--bg-card);
        }
        html[data-theme="dark"] .dep-detail-item {
            background: rgba(30, 41, 59, 0.35);
        }
        .dep-detail-item__ico {
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 0.88rem;
        }
        .dep-detail-item__body { min-width: 0; flex: 1; }
        .dep-detail-item__k {
            display: block;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.15rem;
        }
        .dep-detail-item__v {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.45;
            word-break: break-word;
        }
        .dep-detail-item--wide { grid-column: 1 / -1; }
        .dep-detail-note .dep-detail-item__v { white-space: pre-wrap; }
        .dep-attach-panel {
            margin-bottom: 0.35rem;
            border: 1px dashed rgba(148, 163, 184, 0.55);
            border-radius: 0.85rem;
            padding: 0.75rem;
            background: linear-gradient(145deg, rgba(239, 246, 255, 0.55), rgba(248, 250, 252, 0.9));
        }
        html[data-theme="dark"] .dep-attach-panel {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.55), rgba(15, 23, 42, 0.65));
            border-color: rgba(148, 163, 184, 0.28);
        }
        .dep-attach-head {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.55rem;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
        }
        .dep-attach-head i { color: var(--primary-dark); opacity: 0.9; }
        .dep-attach-frame {
            border-radius: 0.65rem;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--bg-card);
            text-align: center;
            max-height: 14rem;
        }
        .dep-attach-frame img {
            display: block;
            max-width: 100%;
            max-height: 14rem;
            width: auto;
            height: auto;
            margin: 0 auto;
            object-fit: contain;
        }
        .dep-attach-pdf {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 0.5rem;
            text-align: center;
        }
        .dep-attach-pdf i { font-size: 2.75rem; color: #b91c1c; opacity: 0.92; }
        .dep-attach-pdf p { margin: 0; font-size: 0.8rem; font-weight: 700; color: var(--muted); }
        .dep-attach-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center; margin-top: 0.35rem; }
        .dep-attach-empty {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.5rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            justify-content: center;
        }
        .dep-attach-empty i { font-size: 1.25rem; opacity: 0.55; }
        .dep-empty { padding: 1.5rem; text-align: center; color: var(--muted); font-weight: 700; }
        #dep-dialog.dep-dialog--form .dep-dialog__box {
            max-height: min(92vh, 44rem);
            width: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
            position: relative;
        }
        #dep-dialog.dep-dialog--form .portal-dialog__title {
            flex-shrink: 0;
        }
        #dep-dialog.dep-dialog--form .dep-dialog__scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding-inline-end: 0.15rem;
        }
        #dep-dialog.dep-dialog--form .dep-dialog__footer {
            flex-shrink: 0;
            margin-top: 0.35rem;
            padding-top: 0.65rem;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }
        .dep-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem 0.75rem;
            align-items: start;
        }
        .dep-field--full { grid-column: 1 / -1; }
        .dep-field label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.2rem; }
        .dep-field input, .dep-field select, .dep-field textarea {
            width: 100%; padding: 0.45rem 0.55rem; border-radius: 0.55rem; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.84rem;
            box-sizing: border-box;
        }
        .dep-field textarea { min-height: 3.5rem; resize: vertical; }
        /* تقویم داخل دیالوگ: موقعیت با JS نسبت به .dep-dialog__box تنظیم می‌شود */
        #dep-dialog.datepicker-portal-host .datepicker-container {
            z-index: 40;
            margin: 0;
        }
        .dep-req { color: #dc2626; }
        .dep-file-row { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
        .dep-file-prev { max-width: 100%; max-height: 8rem; border-radius: 0.45rem; border: 1px solid var(--border); }
        .dep-pagination-wrap { margin-top: 0.65rem; }
        @media (max-width: 720px) {
            .dep-desktop-only { display: none !important; }
            .dep-mobile-only { display: flex !important; }
        }
        @media (max-width: 520px) {
            .dep-modal-grid { grid-template-columns: 1fr; }
            .dep-field--full { grid-column: 1; }
        }
    </style>
@endpush

@section('content')
    <section class="dep-page" aria-labelledby="dep-page-title">
        <h1 id="dep-page-title" class="portal-loans-page__title" style="margin:0 0 0.75rem;font-size:1.1rem;font-weight:800;color:var(--text)">اعلام واریزی‌ها</h1>
        <p style="margin:0 0 1rem;color:var(--muted);font-size:0.88rem;line-height:1.5">
            واریزهایی مثل کارت‌به‌کارت را اینجا ثبت کنید؛ پس از بررسی توسط کارشناس، وضعیت به‌روز می‌شود.
        </p>

        <div class="dep-toolbar">
            <div class="dep-search">
                <label class="visually-hidden" for="dep-search-q">جستجو</label>
                <input type="search" id="dep-search-q" placeholder="جستجو (نام وام، کد پرونده، پیگیری…)" autocomplete="off">
                <button type="button" class="portal-loan__btn portal-loan__btn--ghost" id="dep-search-btn">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    جستجو
                </button>
            </div>
            <button type="button" class="portal-loan__btn portal-loan__btn--primary" id="dep-open-create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                اعلام واریزی جدید
            </button>
        </div>

        <div class="dep-wrap dep-desktop-only">
            <table class="dep-tbl">
                <thead>
                    <tr>
                        <th>نام وام</th>
                        <th>مشخصات قسط</th>
                        <th>تاریخ واریز</th>
                        <th>مبلغ واریزی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="dep-tbody">
                    <tr><td colspan="6" class="dep-empty">در حال بارگذاری…</td></tr>
                </tbody>
            </table>
        </div>
        <div id="dep-cards" class="dep-cards dep-mobile-only" role="list" aria-live="polite"></div>
        <div id="dep-pagination-wrap" class="dep-pagination-wrap" aria-label="صفحه‌بندی"></div>
    </section>

    <dialog id="dep-dialog" class="portal-dialog portal-dialog--wide dep-dialog--form datepicker-portal-host" aria-labelledby="dep-dialog-title">
        <div class="portal-dialog__inner dep-dialog__box">
            <button type="button" class="portal-dialog__close" data-dep-dialog-close aria-label="بستن">&times;</button>
            <h2 id="dep-dialog-title" class="portal-dialog__title">اعلام واریزی جدید</h2>
            <div class="dep-dialog__scroll">
                <form id="dep-form" class="dep-modal-grid">
                    <input type="hidden" name="edit_id" id="dep-edit-id" value="">
                    <div class="dep-field">
                        <label for="dep-loan">وام <span class="dep-req">*</span></label>
                        <select id="dep-loan" name="customer_loan_file_id" required>
                            <option value="">— انتخاب کنید —</option>
                        </select>
                    </div>
                    <div class="dep-field">
                        <label for="dep-inst">شماره قسط <span class="dep-req">*</span></label>
                        <select id="dep-inst" name="customer_loan_installment_id" required disabled>
                            <option value="">ابتدا وام را انتخاب کنید</option>
                        </select>
                    </div>
                    <div class="dep-field">
                        <label for="dep-deposited-jdate">تاریخ واریز <span class="dep-req">*</span></label>
                        <input type="text" id="dep-deposited-jdate" name="deposited_jdate" required autocomplete="off" placeholder="۱۴۰۳/۰۶/۱۵">
                    </div>
                    <div class="dep-field">
                        <label for="dep-amount">مبلغ واریزی (تومان) <span class="dep-req">*</span></label>
                        <input type="number" id="dep-amount" name="amount_toman" required min="1" step="1" inputmode="numeric" placeholder="مثلاً 500000">
                    </div>
                    <div class="dep-field">
                        <label for="dep-method">نحوه پرداخت <span class="dep-req">*</span></label>
                        <select id="dep-method" name="user_payment_method" required>
                            <option value="cash">نقدی</option>
                            <option value="bank">بانک (فیش، کارت به کارت)</option>
                            <option value="online">آنلاین</option>
                        </select>
                    </div>
                    <div class="dep-field">
                        <label for="dep-tracking">شماره فیش / پیگیری</label>
                        <input type="text" id="dep-tracking" name="tracking_number" maxlength="190" autocomplete="off">
                    </div>
                    <div class="dep-field dep-field--full">
                        <label for="dep-note">توضیحات</label>
                        <textarea id="dep-note" name="customer_note" maxlength="5000"></textarea>
                    </div>
                    <div class="dep-field dep-field--full">
                        <label for="dep-file">فایل پیوست</label>
                        <input type="file" id="dep-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        <div class="dep-file-row" style="margin-top:0.35rem">
                            <a href="#" id="dep-file-download" class="dep-btn" style="display:none" target="_blank" rel="noopener">دانلود پیوست فعلی</a>
                            <button type="button" class="dep-btn dep-btn--danger" id="dep-file-clear" style="display:none">حذف انتخاب فایل</button>
                        </div>
                        <div id="dep-file-preview-wrap" style="margin-top:0.4rem;display:none">
                            <img src="" alt="" id="dep-file-preview-img" class="dep-file-prev" style="display:none">
                            <p id="dep-file-preview-pdf" style="display:none;margin:0;font-size:0.75rem;font-weight:700;color:var(--muted)">فایل PDF انتخاب شد.</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="dep-dialog__footer">
                <button type="submit" form="dep-form" class="portal-loan__btn portal-loan__btn--primary" id="dep-submit">ثبت</button>
                <button type="button" class="portal-loan__btn portal-loan__btn--ghost" data-dep-dialog-close>انصراف</button>
            </div>
        </div>
    </dialog>

    <dialog id="dep-admin-note-dialog" class="portal-dialog" aria-labelledby="dep-admin-note-title">
        <div class="portal-dialog__inner">
            <button type="button" class="portal-dialog__close" data-dep-admin-note-close aria-label="بستن">&times;</button>
            <h2 id="dep-admin-note-title" class="portal-dialog__title">
                <i class="fa-solid fa-user-tie" style="margin-inline-end:0.35rem;opacity:0.9" aria-hidden="true"></i>
                توضیحات مدیر
            </h2>
            <p id="dep-admin-note-body" class="dep-admin-note-body"></p>
            <div class="portal-dialog__actions" style="margin-top:0.75rem">
                <button type="button" class="portal-loan__btn portal-loan__btn--ghost" data-dep-admin-note-close>بستن</button>
            </div>
        </div>
    </dialog>

    <dialog id="dep-view-dialog" class="datepicker-portal-host" aria-labelledby="dep-view-title">
        <div class="portal-dialog__inner dep-view-dialog__box">
            <button type="button" class="portal-dialog__close" data-dep-view-close aria-label="بستن">&times;</button>
            <h2 id="dep-view-title" class="portal-dialog__title">
                <i class="fa-regular fa-eye" style="margin-inline-end:0.35rem;opacity:0.9" aria-hidden="true"></i>
                جزئیات اعلام واریزی
            </h2>
            <div class="dep-view-scroll">
                <div id="dep-view-fields" class="dep-detail-grid" aria-live="polite"></div>
                <div id="dep-view-attach" class="dep-attach-panel" style="display:none"></div>
            </div>
            <div class="dep-view-footer">
                <button type="button" class="portal-loan__btn portal-loan__btn--ghost" data-dep-view-close>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        window.__PORTAL_LOANS_FOR_DEPOSITS__ = @json($portalLoansJson ?? []);
        window.__DEP_ROUTES__ = {
            list: @json(route('user.deposits.list')),
            store: @json(route('user.deposits.items.store')),
            updateBase: @json(url('/user/deposits/items')),
            destroyBase: @json(url('/user/deposits/items')),
            ackReview: @json(route('user.deposits.review-notifications.ack')),
        };
    </script>
    <script>
        (function () {
            var loans = window.__PORTAL_LOANS_FOR_DEPOSITS__ || [];
            var routes = window.__DEP_ROUTES__ || {};
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var csrfV = csrf ? csrf.getAttribute('content') : '';

            function headersJson() {
                return {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfV,
                    'X-Requested-With': 'XMLHttpRequest'
                };
            }

            var tbody = document.getElementById('dep-tbody');
            var cardsRoot = document.getElementById('dep-cards');
            var pagerWrap = document.getElementById('dep-pagination-wrap');
            var searchInput = document.getElementById('dep-search-q');
            var currentPage = 1;
            var currentPerPage = 15;
            var currentQ = '';
            var depLastRows = {};

            function escapeHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function renderOperations(row) {
                var parts = [];
                if (row.attachment_url) {
                    parts.push('<a class="dep-btn" href="' + escapeHtml(row.attachment_url) + '" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> پیوست</a>');
                }
                if (!row.can_edit) {
                    parts.push('<button type="button" class="dep-btn" data-dep-view="' + row.id + '"><i class="fa-regular fa-eye" aria-hidden="true"></i> مشاهده</button>');
                }
                if (row.can_edit) {
                    parts.push('<button type="button" class="dep-btn" data-dep-edit="' + row.id + '"><i class="fa-solid fa-pen" aria-hidden="true"></i> ویرایش</button>');
                    parts.push('<button type="button" class="dep-btn dep-btn--danger" data-dep-del="' + row.id + '"><i class="fa-solid fa-trash" aria-hidden="true"></i> حذف</button>');
                }
                if (!parts.length) return '<span style="opacity:0.65">—</span>';
                return '<div class="dep-actions">' + parts.join('') + '</div>';
            }

            function statusCellHtml(row) {
                var base = escapeHtml(row.status_fa || '');
                var noteBtn = '';
                if (row.admin_note && String(row.admin_note).trim()) {
                    noteBtn = '<div class="dep-status-note"><button type="button" class="dep-btn dep-btn--note" data-dep-admin-note="' + row.id + '">' +
                        '<i class="fa-solid fa-comment-medical" aria-hidden="true"></i> مشاهده توضیحات مدیر</button></div>';
                }
                return base + noteBtn;
            }

            function buildCardHtml(row) {
                var title = escapeHtml(row.loan_title || '') +
                    '<br><small style="opacity:0.85;font-weight:700">' + escapeHtml(row.loan_code_fa || '') + '</small>';
                return '<article class="dep-card" role="listitem" data-id="' + row.id + '">' +
                    '<h3 class="dep-card__title">' + title + '</h3>' +
                    '<dl class="dep-card__kv">' +
                    '<div class="dep-card__kv-row"><dt>قسط</dt><dd>' + escapeHtml(row.installment_label_fa || '') + '</dd></div>' +
                    '<div class="dep-card__kv-row"><dt>تاریخ واریز</dt><dd>' + escapeHtml(row.deposited_jalali_fa || '') + '</dd></div>' +
                    '<div class="dep-card__kv-row"><dt>مبلغ</dt><dd>' + escapeHtml(row.amount_fa || '') + '</dd></div>' +
                    '<div class="dep-card__kv-row"><dt>وضعیت</dt><dd>' + statusCellHtml(row) + '</dd></div>' +
                    '</dl>' +
                    '<div class="dep-card__foot">' + renderOperations(row) + '</div>' +
                    '</article>';
            }

            function bindDepPagination() {
                if (!pagerWrap) return;
                pagerWrap.querySelectorAll('a[href]').forEach(function (link) {
                    if (link.dataset.depPagBound === '1') return;
                    link.dataset.depPagBound = '1';
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        try {
                            var u = new URL(link.href, window.location.origin);
                            loadPage(parseInt(u.searchParams.get('page'), 10) || 1);
                        } catch (err) { /* noop */ }
                    });
                });
                pagerWrap.querySelectorAll('.mg-per-page-form select[name="per_page"]').forEach(function (sel) {
                    if (sel.dataset.depPagBound === '1') return;
                    sel.dataset.depPagBound = '1';
                    sel.addEventListener('change', function (e) {
                        e.preventDefault();
                        currentPerPage = parseInt(String(sel.value), 10) || 15;
                        loadPage(1);
                    });
                });
            }

            function loadPage(page) {
                currentPage = page || 1;
                var u = new URL(routes.list, window.location.origin);
                u.searchParams.set('page', String(currentPage));
                u.searchParams.set('per_page', String(currentPerPage));
                if (currentQ) u.searchParams.set('q', currentQ);
                tbody.innerHTML = '<tr><td colspan="6" class="dep-empty">در حال بارگذاری…</td></tr>';
                if (cardsRoot) cardsRoot.innerHTML = '<p class="dep-empty" style="margin:0">در حال بارگذاری…</p>';
                fetch(u.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var rows = data.data || [];
                        depLastRows = {};
                        rows.forEach(function (r) { depLastRows[r.id] = r; });
                        if (!rows.length) {
                            tbody.innerHTML = '<tr><td colspan="6" class="dep-empty">رکوردی ثبت نشده است.</td></tr>';
                            if (cardsRoot) cardsRoot.innerHTML = '<p class="dep-empty" style="margin:0">رکوردی ثبت نشده است.</p>';
                        } else {
                            tbody.innerHTML = rows.map(function (row) {
                                return '<tr data-id="' + row.id + '">' +
                                    '<td>' + escapeHtml(row.loan_title) + '<br><small style="opacity:0.85">' + escapeHtml(row.loan_code_fa) + '</small></td>' +
                                    '<td>' + escapeHtml(row.installment_label_fa) + '</td>' +
                                    '<td>' + escapeHtml(row.deposited_jalali_fa) + '</td>' +
                                    '<td>' + escapeHtml(row.amount_fa) + '</td>' +
                                    '<td>' + statusCellHtml(row) + '</td>' +
                                    '<td>' + renderOperations(row) + '</td>' +
                                    '</tr>';
                            }).join('');
                            if (cardsRoot) cardsRoot.innerHTML = rows.map(buildCardHtml).join('');
                        }
                        renderPaginationBar(data.pagination_html || '', data.meta || {});
                        bindRowActions();
                    })
                    .catch(function () {
                        tbody.innerHTML = '<tr><td colspan="6" class="dep-empty">خطا در بارگذاری.</td></tr>';
                        if (cardsRoot) cardsRoot.innerHTML = '<p class="dep-empty" style="margin:0">خطا در بارگذاری.</p>';
                    });
            }

            function renderPaginationBar(html, meta) {
                if (!pagerWrap) return;
                var total = meta && meta.total ? meta.total : 0;
                if (!total) {
                    pagerWrap.innerHTML = '';
                    return;
                }
                pagerWrap.innerHTML = html || '';
                bindDepPagination();
            }

            var adminNoteDialog = document.getElementById('dep-admin-note-dialog');
            var adminNoteBody = document.getElementById('dep-admin-note-body');
            var viewDialog = document.getElementById('dep-view-dialog');
            var viewFieldsEl = document.getElementById('dep-view-fields');
            var viewAttachEl = document.getElementById('dep-view-attach');
            function closeAdminNoteDialog() {
                if (adminNoteDialog && adminNoteDialog.open) adminNoteDialog.close();
            }
            document.querySelectorAll('[data-dep-admin-note-close]').forEach(function (b) {
                b.addEventListener('click', closeAdminNoteDialog);
            });
            if (adminNoteDialog) {
                adminNoteDialog.addEventListener('click', function (e) { if (e.target === adminNoteDialog) closeAdminNoteDialog(); });
            }

            function closeViewDialog() {
                if (viewDialog && viewDialog.open) viewDialog.close();
            }
            document.querySelectorAll('[data-dep-view-close]').forEach(function (b) {
                b.addEventListener('click', closeViewDialog);
            });
            if (viewDialog) {
                viewDialog.addEventListener('click', function (e) { if (e.target === viewDialog) closeViewDialog(); });
            }

            function detailItem(iconClass, label, value, extraClass) {
                var v = (value === undefined || value === null) ? '' : String(value);
                if (!v.trim()) v = '—';
                return '<div class="dep-detail-item' + (extraClass ? ' ' + extraClass : '') + '">' +
                    '<span class="dep-detail-item__ico" aria-hidden="true"><i class="fa-solid ' + iconClass + '"></i></span>' +
                    '<div class="dep-detail-item__body">' +
                    '<span class="dep-detail-item__k">' + escapeHtml(label) + '</span>' +
                    '<span class="dep-detail-item__v">' + escapeHtml(v) + '</span>' +
                    '</div></div>';
            }

            function renderViewAttachment(attachTarget, att) {
                if (!attachTarget) return;
                if (!att || !att.has) {
                    attachTarget.style.display = 'block';
                    attachTarget.innerHTML =
                        '<div class="dep-attach-head"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> پیوست</div>' +
                        '<div class="dep-attach-empty"><i class="fa-regular fa-file" aria-hidden="true"></i> پیوستی ثبت نشده است.</div>';
                    return;
                }
                attachTarget.style.display = 'block';
                var head = '<div class="dep-attach-head"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> پیوست</div>';
                var dl = att.download_url ? escapeHtml(att.download_url) : '#';
                var inline = att.inline_url ? escapeHtml(att.inline_url) : '';
                if (att.kind === 'pdf') {
                    attachTarget.innerHTML = head +
                        '<div class="dep-attach-pdf">' +
                        '<i class="fa-solid fa-file-pdf" aria-hidden="true"></i>' +
                        '<p>فایل PDF ارسال‌شده</p>' +
                        '<div class="dep-attach-actions">' +
                        '<a class="dep-btn dep-btn--pri" href="' + inline + '" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> باز کردن</a>' +
                        '<a class="dep-btn" href="' + dl + '"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود</a>' +
                        '</div></div>';
                    return;
                }
                attachTarget.innerHTML = head +
                    '<div class="dep-attach-frame"><img src="' + inline + '" alt="پیوست اعلام واریزی"></div>' +
                    '<div class="dep-attach-actions" style="margin-top:0.5rem">' +
                    '<a class="dep-btn" href="' + dl + '"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود فایل</a>' +
                    '<a class="dep-btn" href="' + inline + '" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> باز در تب جدید</a>' +
                    '</div>';
            }

            function fillViewModal(row) {
                if (!viewFieldsEl) return;
                if (!row) {
                    viewFieldsEl.innerHTML = '<div class="dep-detail-item dep-detail-item--wide"><span class="dep-detail-item__v">اطلاعات یافت نشد.</span></div>';
                    renderViewAttachment(viewAttachEl, null);
                    return;
                }
                var parts = [];
                if (row.status_fa) parts.push(detailItem('fa-circle-info', 'وضعیت', row.status_fa));
                parts.push(detailItem('fa-file-invoice-dollar', 'عنوان وام', row.loan_title));
                parts.push(detailItem('fa-hashtag', 'کد پرونده', row.loan_code_fa));
                parts.push(detailItem('fa-list-ol', 'مشخصات قسط', row.installment_label_fa));
                parts.push(detailItem('fa-calendar-days', 'تاریخ واریز (اعلام‌شده)', row.deposited_jalali_fa));
                parts.push(detailItem('fa-coins', 'مبلغ واریزی (اعلام‌شده)', row.amount_fa));
                parts.push(detailItem('fa-credit-card', 'نحوهٔ پرداخت', row.user_payment_method_fa));
                parts.push(detailItem('fa-barcode', 'شماره فیش / پیگیری', row.tracking_number || ''));
                var note = (row.customer_note && String(row.customer_note).trim()) ? String(row.customer_note) : '';
                parts.push(detailItem('fa-note-sticky', 'توضیحات شما', note, 'dep-detail-item--wide dep-detail-note'));
                var adm = (row.admin_note && String(row.admin_note).trim()) ? String(row.admin_note) : '';
                if (adm) parts.push(detailItem('fa-user-tie', 'توضیحات مدیر', adm, 'dep-detail-item--wide dep-detail-note'));
                if (row.reviewed_at_fa) parts.push(detailItem('fa-clock', 'تاریخ رسیدگی', row.reviewed_at_fa));
                viewFieldsEl.innerHTML = parts.join('');
                renderViewAttachment(viewAttachEl, row.attachment || null);
            }

            function bindRowActions() {
                document.querySelectorAll('#dep-tbody [data-dep-view], #dep-cards [data-dep-view]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = parseInt(btn.getAttribute('data-dep-view'), 10);
                        var row = depLastRows[id];
                        if (!row) return;
                        fillViewModal(row);
                        if (typeof viewDialog.showModal === 'function') viewDialog.showModal();
                    });
                });
                document.querySelectorAll('#dep-tbody [data-dep-admin-note], #dep-cards [data-dep-admin-note]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = parseInt(btn.getAttribute('data-dep-admin-note'), 10);
                        var row = depLastRows[id];
                        if (!row || !row.admin_note) return;
                        if (adminNoteBody) adminNoteBody.textContent = String(row.admin_note);
                        if (typeof adminNoteDialog.showModal === 'function') adminNoteDialog.showModal();
                    });
                });
                document.querySelectorAll('#dep-tbody [data-dep-del], #dep-cards [data-dep-del]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = btn.getAttribute('data-dep-del');
                        if (!id || !confirm('این اعلام واریزی حذف شود؟')) return;
                        fetch(routes.destroyBase + '/' + encodeURIComponent(id), {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrfV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                            .then(function (x) {
                                if (x.ok) { loadPage(currentPage); if (window.AdminSwal) AdminSwal.fire({ icon: 'success', title: x.j.message || 'حذف شد' }); }
                                else if (window.AdminSwal) AdminSwal.fire({ icon: 'error', title: x.j.message || 'خطا' });
                            });
                    });
                });
                document.querySelectorAll('#dep-tbody [data-dep-edit], #dep-cards [data-dep-edit]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = parseInt(btn.getAttribute('data-dep-edit'), 10);
                        if (!id) return;
                        openModalForEdit(id);
                    });
                });
            }

            document.getElementById('dep-search-btn').addEventListener('click', function () {
                currentQ = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
                loadPage(1);
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); document.getElementById('dep-search-btn').click(); }
            });

            var dialog = document.getElementById('dep-dialog');
            function closeDepDialog() { if (dialog && dialog.open) dialog.close(); }
            document.querySelectorAll('[data-dep-dialog-close]').forEach(function (b) {
                b.addEventListener('click', closeDepDialog);
            });
            if (dialog) {
                dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDepDialog(); });
                dialog.addEventListener('close', function () { destroyDepDatepicker(); });
            }

            var loanSel = document.getElementById('dep-loan');
            var instSel = document.getElementById('dep-inst');
            loans.forEach(function (L) {
                if (L.is_revoked) return;
                var o = document.createElement('option');
                o.value = String(L.id);
                o.textContent = (L.loan_title || 'وام') + ' — ' + (L.loan_code || '');
                loanSel.appendChild(o);
            });

            function fillInstForLoan(loanId) {
                instSel.innerHTML = '<option value="">— انتخاب قسط —</option>';
                instSel.disabled = true;
                if (!loanId) return;
                var L = loans.find(function (x) { return String(x.id) === String(loanId); });
                if (!L || !Array.isArray(L.installments)) return;
                instSel.disabled = false;
                L.installments.forEach(function (ins) {
                    var o = document.createElement('option');
                    o.value = String(ins.id);
                    o.textContent = 'قسط ' + (ins.sequence_fa || ins.sequence) + ' — ' + (ins.amount_fa || '') + ' — سررسید ' + (ins.due_jalali || '');
                    instSel.appendChild(o);
                });
            }
            loanSel.addEventListener('change', function () { fillInstForLoan(loanSel.value); });

            var fileInput = document.getElementById('dep-file');
            var prevWrap = document.getElementById('dep-file-preview-wrap');
            var prevImg = document.getElementById('dep-file-preview-img');
            var prevPdf = document.getElementById('dep-file-preview-pdf');
            var fileClear = document.getElementById('dep-file-clear');
            var fileDl = document.getElementById('dep-file-download');
            fileInput.addEventListener('change', function () {
                var f = fileInput.files && fileInput.files[0];
                prevWrap.style.display = f ? 'block' : 'none';
                prevImg.style.display = 'none';
                prevPdf.style.display = 'none';
                fileClear.style.display = f ? 'inline-block' : 'none';
                if (!f) return;
                if (f.type.indexOf('image/') === 0) {
                    prevImg.style.display = 'block';
                    prevImg.src = URL.createObjectURL(f);
                } else {
                    prevPdf.style.display = 'block';
                }
            });
            fileClear.addEventListener('click', function () {
                fileInput.value = '';
                prevWrap.style.display = 'none';
                prevImg.src = '';
                fileClear.style.display = 'none';
            });

            var depPickerScrollEl = null;

            function detachDepPickerPositionSync() {
                window.removeEventListener('resize', positionDepDatepickerUnderInput);
                if (depPickerScrollEl) {
                    depPickerScrollEl.removeEventListener('scroll', positionDepDatepickerUnderInput);
                    depPickerScrollEl = null;
                }
            }

            function positionDepDatepickerUnderInput() {
                var dlg = document.getElementById('dep-dialog');
                var box = dlg && dlg.querySelector('.dep-dialog__box');
                var inp = document.getElementById('dep-deposited-jdate');
                var $jq = window.jQuery;
                if (!dlg || !box || !inp || !$jq) return;
                var $c = $jq(box).find('> .datepicker-container').last();
                if (!$c.length) return;
                var ir = inp.getBoundingClientRect();
                var br = box.getBoundingClientRect();
                var gap = 6;
                var left = ir.left - br.left;
                var top = ir.bottom - br.top + gap;
                var cw = $c.outerWidth() || 260;
                var ch = $c.outerHeight() || 280;
                var pad = 8;
                if (left + cw > br.width - pad) {
                    left = Math.max(pad, br.width - pad - cw);
                }
                if (left < pad) left = pad;
                var spaceBelow = br.bottom - ir.bottom - gap;
                var spaceAbove = ir.top - br.top - gap;
                if (spaceBelow < ch && spaceAbove > spaceBelow) {
                    top = ir.top - br.top - ch - gap;
                }
                if (top + ch > br.height - pad) {
                    top = Math.max(pad, br.height - pad - ch);
                }
                if (top < pad) top = pad;
                $c.css({
                    position: 'absolute',
                    left: left + 'px',
                    top: top + 'px',
                    right: 'auto',
                    bottom: 'auto',
                    margin: 0
                });
            }

            function attachDepPickerPositionSync() {
                detachDepPickerPositionSync();
                depPickerScrollEl = document.querySelector('#dep-dialog .dep-dialog__scroll');
                if (depPickerScrollEl) {
                    depPickerScrollEl.addEventListener('scroll', positionDepDatepickerUnderInput, { passive: true });
                }
                window.addEventListener('resize', positionDepDatepickerUnderInput, { passive: true });
            }

            function destroyDepDatepicker() {
                detachDepPickerPositionSync();
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var $el = window.jQuery('#dep-deposited-jdate');
                if (!$el.length) return;
                try {
                    if ($el.data('datepicker')) {
                        $el.pDatepicker('destroy');
                    }
                } catch (err) { /* noop */ }
            }

            function initDepDatepicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                var el = document.getElementById('dep-deposited-jdate');
                if (!el) return;
                destroyDepDatepicker();
                var dlg = document.getElementById('dep-dialog');
                var $jq = window.jQuery;
                $jq('#dep-deposited-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    calendar: { persian: { locale: 'fa' } },
                    toolbox: { calendarSwitch: { enabled: false } },
                    observer: true,
                    onShow: function (model) {
                        if (!dlg || !model || !model.view || !model.view.$container) return;
                        var box = dlg.querySelector('.dep-dialog__box');
                        if (!box) return;
                        var $c = model.view.$container;
                        if ($c.length && !$jq.contains(box, $c[0])) {
                            $jq(box).append($c);
                        }
                        var run = function () {
                            positionDepDatepickerUnderInput();
                            attachDepPickerPositionSync();
                        };
                        requestAnimationFrame(function () {
                            requestAnimationFrame(run);
                        });
                    },
                    onHide: function () {
                        detachDepPickerPositionSync();
                    }
                });
            }

            function resetForm() {
                destroyDepDatepicker();
                document.getElementById('dep-edit-id').value = '';
                document.getElementById('dep-dialog-title').textContent = 'اعلام واریزی جدید';
                document.getElementById('dep-form').reset();
                instSel.innerHTML = '<option value="">ابتدا وام را انتخاب کنید</option>';
                instSel.disabled = true;
                fileClear.click();
                fileDl.style.display = 'none';
                fileDl.removeAttribute('href');
            }

            document.getElementById('dep-open-create').addEventListener('click', function () {
                resetForm();
                if (typeof dialog.showModal === 'function') dialog.showModal();
                setTimeout(initDepDatepicker, 50);
            });

            function openModalForEdit(id) {
                var row = depLastRows[id];
                if (!row) return;
                resetForm();
                document.getElementById('dep-edit-id').value = String(id);
                document.getElementById('dep-dialog-title').textContent = 'ویرایش اعلام واریزی';
                loanSel.value = String(row.customer_loan_file_id || '');
                fillInstForLoan(loanSel.value);
                instSel.value = String(row.customer_loan_installment_id || '');
                document.getElementById('dep-deposited-jdate').value = row.deposited_jdate || '';
                document.getElementById('dep-amount').value = String(row.amount_toman || '');
                document.getElementById('dep-method').value = row.user_payment_method || 'bank';
                document.getElementById('dep-tracking').value = row.tracking_number || '';
                document.getElementById('dep-note').value = row.customer_note || '';
                if (row.attachment_url) {
                    fileDl.href = row.attachment_url;
                    fileDl.style.display = 'inline-block';
                }
                if (typeof dialog.showModal === 'function') dialog.showModal();
                setTimeout(initDepDatepicker, 50);
            }

            document.getElementById('dep-form').addEventListener('submit', function (e) {
                e.preventDefault();
                var editId = document.getElementById('dep-edit-id').value.trim();
                var fd = new FormData();
                fd.append('customer_loan_file_id', loanSel.value);
                fd.append('customer_loan_installment_id', instSel.value);
                fd.append('deposited_jdate', document.getElementById('dep-deposited-jdate').value.trim());
                fd.append('amount_toman', document.getElementById('dep-amount').value);
                fd.append('user_payment_method', document.getElementById('dep-method').value);
                fd.append('tracking_number', document.getElementById('dep-tracking').value.trim());
                fd.append('customer_note', document.getElementById('dep-note').value);
                if (fileInput.files && fileInput.files[0]) fd.append('attachment', fileInput.files[0]);

                var url = routes.store;
                if (editId) url = routes.updateBase + '/' + encodeURIComponent(editId) + '/update';

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j, st: r.status }; }); })
                    .then(function (x) {
                        if (x.ok) {
                            closeDepDialog();
                            loadPage(currentPage);
                            if (window.AdminSwal) AdminSwal.fire({ icon: 'success', title: x.j.message || 'ثبت شد' });
                        } else {
                            var msg = (x.j && x.j.message) ? x.j.message : 'خطا';
                            if (x.j && x.j.errors) {
                                var k = Object.keys(x.j.errors)[0];
                                if (k && x.j.errors[k] && x.j.errors[k][0]) msg = x.j.errors[k][0];
                            }
                            if (window.AdminSwal) AdminSwal.fire({ icon: 'error', title: msg });
                        }
                    });
            });

            loadPage(1);
            if (routes.ackReview) {
                fetch(routes.ackReview, { method: 'POST', headers: headersJson() }).catch(function () {});
            }
        })();
    </script>
@endpush
