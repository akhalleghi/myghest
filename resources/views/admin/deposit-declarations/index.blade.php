@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <style>
        .dd-page {
            width: 100%;
            max-width: 100%;
            margin: 0;
            box-sizing: border-box;
        }
        .dd-h1 { margin: 0 0 0.5rem; font-size: 1.12rem; font-weight: 800; color: var(--text); }
        .dd-lead { margin: 0 0 1rem; font-size: 0.82rem; color: var(--muted); line-height: 1.55; }
        .dd-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; margin-bottom: 0.85rem; }
        .dd-filters label { font-size: 0.72rem; font-weight: 800; color: var(--muted); display: block; margin-bottom: 0.2rem; }
        .dd-filters input, .dd-filters select { padding: 0.4rem 0.55rem; border-radius: 0.55rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .dd-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); }
        .dd-tbl { width: 100%; border-collapse: collapse; font-size: 0.74rem; min-width: 56rem; }
        .dd-tbl th, .dd-tbl td { padding: 0.45rem 0.5rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .dd-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .dd-tbl td { color: var(--muted); font-weight: 600; }
        .dd-tbl tr:last-child td { border-bottom: 0; }
        .dd-btn { font-family: inherit; font-size: 0.7rem; font-weight: 800; padding: 0.3rem 0.5rem; border-radius: 0.45rem; border: 1px solid var(--border); background: var(--bg-card); cursor: pointer; color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .dd-btn--pri { background: var(--primary); color: #fff; border-color: var(--primary-dark); }
        .dd-btn--ok { background: #059669; color: #fff; border-color: #047857; }
        .dd-btn--warn { background: #d97706; color: #fff; border-color: #b45309; }
        .dd-btn--danger { color: #b91c1c; border-color: rgba(185, 28, 28, 0.35); }
        .dd-btn--ghost { background: transparent; }
        .dd-pagination { margin-top: 0.75rem; }
        .dd-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }

        /*
         * <dialog> در حالت بسته باید display:none بماند؛ اگر display:flex بگذاریم،
         * استایل پیش‌فرض مرورگر را override می‌کنیم و بدون showModal هم دیده می‌شود.
         */
        #dd-dialog,
        #dd-view-dialog {
            display: none;
            padding: 0;
            border: none;
            border-radius: 1rem;
            max-width: min(96vw, 46rem);
            width: min(96vw, 46rem);
            max-height: min(92vh, 44rem);
            background: var(--bg-card);
            color: var(--text);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            inset-inline-start: 0;
            inset-inline-end: 0;
            margin-inline: auto;
        }
        #dd-dialog[open],
        #dd-view-dialog[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            margin: auto;
        }
        html[data-theme="dark"] #dd-dialog,
        html[data-theme="dark"] #dd-view-dialog {
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.45);
        }
        #dd-dialog::backdrop,
        #dd-view-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }
        html[data-theme="dark"] #dd-dialog::backdrop,
        html[data-theme="dark"] #dd-view-dialog::backdrop {
            background: rgba(0, 0, 0, 0.55);
        }
        .dd-dialog-inner {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1;
            max-height: inherit;
        }
        .dd-dialog-close {
            position: absolute;
            top: 0.4rem;
            inset-inline-end: 0.4rem;
            width: 2rem;
            height: 2rem;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1.35rem;
            cursor: pointer;
            line-height: 1;
            border-radius: 0.4rem;
            z-index: 2;
        }
        .dd-dialog-close:hover { background: var(--primary-soft); color: var(--text); }
        .dd-dialog-head { flex-shrink: 0; padding: 1rem 2.5rem 0.35rem 1rem; }
        .dd-dialog-title { margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--text); }
        .dd-dialog-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 0.35rem 1rem 0.75rem;
            -webkit-overflow-scrolling: touch;
        }
        .dd-dialog-footer {
            flex-shrink: 0;
            padding: 0.65rem 1rem 1rem;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }

        /* SweetAlert2 داخل <dialog> تا بالای محتوای مودال (همان top layer) بماند */
        #dd-dialog .swal2-container,
        #dd-view-dialog .swal2-container {
            position: absolute !important;
            inset: 0 !important;
            z-index: 200 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .dd-review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem 0.75rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 640px) {
            .dd-review-grid { grid-template-columns: 1fr; }
        }
        .dd-review-item {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            padding: 0.55rem 0.62rem;
            border: 1px solid var(--border);
            border-radius: 0.7rem;
            background: rgba(248, 250, 252, 0.65);
        }
        html[data-theme="dark"] .dd-review-item {
            background: rgba(15, 23, 42, 0.35);
        }
        .dd-review-item__ico {
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 0.55rem;
            display: grid;
            place-items: center;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 0.92rem;
        }
        html[data-theme="dark"] .dd-review-item__ico {
            background: rgba(37, 99, 235, 0.22);
            color: var(--primary-dark);
        }
        .dd-review-item__body { min-width: 0; flex: 1; }
        .dd-review-item__k {
            display: block;
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.15rem;
        }
        .dd-review-item__v {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.45;
            word-break: break-word;
        }
        .dd-review-item--wide { grid-column: 1 / -1; }
        .dd-review-note .dd-review-item__v { white-space: pre-wrap; }

        .dd-attach-panel {
            margin-bottom: 0.85rem;
            border: 1px dashed rgba(148, 163, 184, 0.55);
            border-radius: 0.85rem;
            padding: 0.75rem;
            background: linear-gradient(145deg, rgba(239, 246, 255, 0.55), rgba(248, 250, 252, 0.9));
        }
        html[data-theme="dark"] .dd-attach-panel {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.55), rgba(15, 23, 42, 0.65));
            border-color: rgba(148, 163, 184, 0.28);
        }
        .dd-attach-head {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.55rem;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
        }
        .dd-attach-head i { color: var(--primary-dark); opacity: 0.9; }
        .dd-attach-frame {
            border-radius: 0.65rem;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--bg-card);
            text-align: center;
            max-height: 14rem;
        }
        .dd-attach-frame img {
            display: block;
            max-width: 100%;
            max-height: 14rem;
            width: auto;
            height: auto;
            margin: 0 auto;
            object-fit: contain;
        }
        .dd-attach-pdf {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 0.5rem;
            text-align: center;
        }
        .dd-attach-pdf i { font-size: 2.75rem; color: #b91c1c; opacity: 0.92; }
        .dd-attach-pdf p { margin: 0; font-size: 0.8rem; font-weight: 700; color: var(--muted); }
        .dd-attach-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center; margin-top: 0.35rem; }
        .dd-attach-empty {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.5rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            justify-content: center;
        }
        .dd-attach-empty i { font-size: 1.25rem; opacity: 0.55; }

        .dd-admin-note-block label {
            display: block;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }
        .dd-admin-note-block textarea {
            width: 100%;
            min-height: 4.5rem;
            padding: 0.45rem 0.55rem;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.82rem;
            background: var(--bg-card);
            color: var(--text);
            box-sizing: border-box;
        }
    </style>
@endpush

@section('content')
    <div class="dd-page">
        <h1 class="dd-h1">اعلام واریزها</h1>
        <p class="dd-lead">درخواست‌های ثبت‌شده توسط مشتریان؛ رسیدگی و در صورت نیاز ثبت در پرداختی قسط.</p>

        <form class="dd-filters" method="get" action="{{ route('admin.deposit-declarations.index') }}">
            <div>
                <label for="dd-status">وضعیت</label>
                <select id="dd-status" name="status">
                    <option value="all" @selected(($statusFilter ?? 'all') === 'all')>همه</option>
                    <option value="pending" @selected(($statusFilter ?? '') === 'pending')>در حال بررسی</option>
                    <option value="approved" @selected(($statusFilter ?? '') === 'approved')>تایید شده</option>
                    <option value="approved_applied" @selected(($statusFilter ?? '') === 'approved_applied')>تایید و ثبت در قسط</option>
                    <option value="rejected" @selected(($statusFilter ?? '') === 'rejected')>عدم تایید</option>
                </select>
            </div>
            <div>
                <label for="dd-q">جستجو</label>
                <input type="search" id="dd-q" name="q" value="{{ $searchQ ?? '' }}" placeholder="مشتری، موبایل، کد پرونده، پیگیری…">
            </div>
            <button type="submit" class="dd-btn dd-btn--pri">اعمال</button>
        </form>

        <div class="dd-wrap">
            <table class="dd-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>مشتری</th>
                        <th>وام</th>
                        <th>قسط</th>
                        <th>تاریخ واریز</th>
                        <th>مبلغ</th>
                        <th>نحوه</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($declarations as $d)
                        @php($stFa = \App\Models\CustomerDepositDeclaration::statusLabelsFa()[$d->status] ?? $d->status)
                        @php($mFa = \App\Models\CustomerDepositDeclaration::userPaymentMethodLabelsFa()[$d->user_payment_method] ?? $d->user_payment_method)
                        <tr>
                            <td>{{ $d->id }}</td>
                            <td>
                                {{ $d->customer?->fullName() ?? '—' }}<br>
                                <small style="opacity:0.85">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($d->customer?->mobile ?? '')) }}</small>
                            </td>
                            <td>
                                {{ $d->loanFile?->loanType?->title ?? '—' }}<br>
                                <small style="opacity:0.85">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($d->loanFile?->loan_code ?? '')) }}</small>
                            </td>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) (int) ($d->installment?->sequence ?? 0)) }}</td>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(\Hekmatinasser\Jalali\Jalali::instance(\Carbon\Carbon::parse($d->deposited_at))->format('Y/m/d')) }}</td>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int) $d->amount_toman, 0, '.', ',')) }} تومان</td>
                            <td>{{ $mFa }}</td>
                            <td>{{ $stFa }}</td>
                            <td>
                                <div class="dd-actions">
                                    <button type="button" class="dd-btn dd-btn--ghost dd-open-view" data-id="{{ $d->id }}">
                                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                        مشاهده
                                    </button>
                                    @if($d->isPending())
                                        <button type="button" class="dd-btn dd-btn--pri dd-open-review" data-id="{{ $d->id }}">
                                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                            رسیدگی
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;padding:1.2rem;color:var(--muted);font-weight:700">رکوردی نیست.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="dd-pagination">
            {{ $declarations->links() }}
        </div>
    </div>

    <dialog id="dd-dialog" aria-labelledby="dd-dialog-title">
        <div class="dd-dialog-inner">
            <button type="button" class="dd-dialog-close" data-dd-close aria-label="بستن">&times;</button>
            <div class="dd-dialog-head">
                <h2 id="dd-dialog-title" class="dd-dialog-title">رسیدگی به اعلام واریزی</h2>
            </div>
            <div class="dd-dialog-scroll">
                <div id="dd-review-fields" class="dd-review-grid" aria-live="polite"></div>
                <div id="dd-review-attach" class="dd-attach-panel" style="display:none"></div>
                <div class="dd-admin-note-block">
                    <label for="dd-admin-note">توضیحات مدیر (اختیاری)</label>
                    <textarea id="dd-admin-note" maxlength="5000" placeholder="در صورت عدم تایید یا تایید، توضیح برای مشتری…"></textarea>
                </div>
            </div>
            <div class="dd-dialog-footer">
                <button type="button" class="dd-btn dd-btn--ok" data-dd-action="approve"><i class="fa-solid fa-check" aria-hidden="true"></i> تایید</button>
                <button type="button" class="dd-btn dd-btn--warn" data-dd-action="approve_apply"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i> تایید و ثبت در پرداختی قسط</button>
                <button type="button" class="dd-btn dd-btn--danger" data-dd-action="reject"><i class="fa-solid fa-xmark" aria-hidden="true"></i> عدم تایید</button>
                <button type="button" class="dd-btn dd-btn--ghost" data-dd-close>بستن</button>
            </div>
        </div>
    </dialog>

    <dialog id="dd-view-dialog" aria-labelledby="dd-view-dialog-title">
        <div class="dd-dialog-inner">
            <button type="button" class="dd-dialog-close" data-dd-view-close aria-label="بستن">&times;</button>
            <div class="dd-dialog-head">
                <h2 id="dd-view-dialog-title" class="dd-dialog-title">جزئیات اعلام واریزی</h2>
            </div>
            <div class="dd-dialog-scroll">
                <div id="dd-view-fields" class="dd-review-grid" aria-live="polite"></div>
                <div id="dd-view-attach" class="dd-attach-panel" style="display:none"></div>
            </div>
            <div class="dd-dialog-footer">
                <button type="button" class="dd-btn dd-btn--ghost" data-dd-view-close>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__DD_ROW_SNAPSHOTS__ = @json($rowSnapshots ?? []);
    </script>
    <script>
        (function () {
            var snapshots = window.__DD_ROW_SNAPSHOTS__ || {};
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var csrfV = csrf ? csrf.getAttribute('content') : '';
            var reviewBase = @json(rtrim(url('/admin/deposit-declarations'), '/'));
            var dialog = document.getElementById('dd-dialog');
            var viewDialog = document.getElementById('dd-view-dialog');
            var fieldsEl = document.getElementById('dd-review-fields');
            var attachEl = document.getElementById('dd-review-attach');
            var viewFieldsEl = document.getElementById('dd-view-fields');
            var viewAttachEl = document.getElementById('dd-view-attach');
            var currentId = null;

            function escapeHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function itemHtml(iconClass, label, value, extraClass) {
                var v = (value === undefined || value === null) ? '' : String(value);
                if (!v.trim()) v = '—';
                return '<div class="dd-review-item' + (extraClass ? ' ' + extraClass : '') + '">' +
                    '<span class="dd-review-item__ico" aria-hidden="true"><i class="fa-solid ' + iconClass + '"></i></span>' +
                    '<div class="dd-review-item__body">' +
                    '<span class="dd-review-item__k">' + escapeHtml(label) + '</span>' +
                    '<span class="dd-review-item__v">' + escapeHtml(v) + '</span>' +
                    '</div></div>';
            }

            function renderAttachment(attachTarget, att) {
                if (!attachTarget) return;
                if (!att || !att.has) {
                    attachTarget.style.display = 'block';
                    attachTarget.innerHTML =
                        '<div class="dd-attach-head"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> پیوست مشتری</div>' +
                        '<div class="dd-attach-empty"><i class="fa-regular fa-file" aria-hidden="true"></i> پیوستی ثبت نشده است.</div>';
                    return;
                }
                attachTarget.style.display = 'block';
                var head = '<div class="dd-attach-head"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> پیوست مشتری</div>';
                var dl = att.download_url ? escapeHtml(att.download_url) : '#';
                var inline = att.inline_url ? escapeHtml(att.inline_url) : '';
                if (att.kind === 'pdf') {
                    attachTarget.innerHTML = head +
                        '<div class="dd-attach-pdf">' +
                        '<i class="fa-solid fa-file-pdf" aria-hidden="true"></i>' +
                        '<p>فایل PDF ارسال‌شده توسط مشتری</p>' +
                        '<div class="dd-attach-actions">' +
                        '<a class="dd-btn dd-btn--pri" href="' + inline + '" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> باز کردن</a>' +
                        '<a class="dd-btn dd-btn--ok" href="' + dl + '"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود</a>' +
                        '</div></div>';
                    return;
                }
                attachTarget.innerHTML = head +
                    '<div class="dd-attach-frame"><img src="' + inline + '" alt="پیوست اعلام واریزی"></div>' +
                    '<div class="dd-attach-actions" style="margin-top:0.5rem">' +
                    '<a class="dd-btn dd-btn--ok" href="' + dl + '"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود فایل</a>' +
                    '<a class="dd-btn dd-btn--ghost" href="' + inline + '" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> باز در تب جدید</a>' +
                    '</div>';
            }

            function renderReview(fieldsTarget, attachTarget, p) {
                if (!fieldsTarget) return;
                if (!p) {
                    fieldsTarget.innerHTML = '<div class="dd-review-item dd-review-item--wide"><span class="dd-review-item__v">اطلاعات تکمیلی برای این ردیف یافت نشد.</span></div>';
                    if (attachTarget) { attachTarget.style.display = 'block'; renderAttachment(attachTarget, null); }
                    return;
                }
                var parts = [];
                if (p.status_fa) parts.push(itemHtml('fa-circle-info', 'وضعیت', p.status_fa));
                parts.push(itemHtml('fa-user', 'نام مشتری', p.customer_name));
                parts.push(itemHtml('fa-phone', 'موبایل', p.mobile_fa));
                parts.push(itemHtml('fa-file-invoice-dollar', 'عنوان وام', p.loan_title));
                parts.push(itemHtml('fa-hashtag', 'کد پرونده', p.loan_code_fa));
                parts.push(itemHtml('fa-list-ol', 'شماره قسط', p.installment_seq_fa));
                parts.push(itemHtml('fa-calendar-days', 'تاریخ واریز (اعلام‌شده)', p.deposited_jalali_fa));
                parts.push(itemHtml('fa-coins', 'مبلغ واریزی (اعلام‌شده)', p.amount_fa));
                parts.push(itemHtml('fa-credit-card', 'نحوهٔ پرداخت', p.method_fa));
                parts.push(itemHtml('fa-barcode', 'شماره فیش / پیگیری', p.tracking || ''));
                var note = (p.customer_note && String(p.customer_note).trim()) ? String(p.customer_note) : '';
                parts.push(itemHtml('fa-note-sticky', 'توضیحات مشتری', note, 'dd-review-item--wide dd-review-note'));
                var adm = (p.admin_note && String(p.admin_note).trim()) ? String(p.admin_note) : '';
                if (adm) parts.push(itemHtml('fa-user-tie', 'توضیحات مدیر (ثبت‌شده)', adm, 'dd-review-item--wide dd-review-note'));
                if (p.reviewed_at_fa) parts.push(itemHtml('fa-clock', 'تاریخ رسیدگی', p.reviewed_at_fa));
                if (p.reviewer && String(p.reviewer).trim()) parts.push(itemHtml('fa-user-shield', 'کارشناس رسیدگی', p.reviewer));
                fieldsTarget.innerHTML = parts.join('');
                renderAttachment(attachTarget, p.attachment || null);
            }

            function closeD() { if (dialog && dialog.open) dialog.close(); }
            function closeView() { if (viewDialog && viewDialog.open) viewDialog.close(); }

            document.querySelectorAll('[data-dd-close]').forEach(function (b) { b.addEventListener('click', closeD); });
            document.querySelectorAll('[data-dd-view-close]').forEach(function (b) { b.addEventListener('click', closeView); });
            if (dialog) dialog.addEventListener('click', function (e) { if (e.target === dialog) closeD(); });
            if (viewDialog) viewDialog.addEventListener('click', function (e) { if (e.target === viewDialog) closeView(); });

            document.querySelectorAll('.dd-open-view').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-id');
                    var p = snapshots[String(id)] || snapshots[id];
                    renderReview(viewFieldsEl, viewAttachEl, p);
                    if (typeof viewDialog.showModal === 'function') viewDialog.showModal();
                });
            });

            document.querySelectorAll('.dd-open-review').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentId = btn.getAttribute('data-id');
                    var p = snapshots[String(currentId)] || snapshots[currentId];
                    document.getElementById('dd-admin-note').value = '';
                    renderReview(fieldsEl, attachEl, p);
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                });
            });

            function postReview(action) {
                if (!currentId) return;
                var note = document.getElementById('dd-admin-note').value.trim();
                fetch(reviewBase + '/' + encodeURIComponent(currentId) + '/review', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfV,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ action: action, admin_note: note || null })
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (x) {
                        if (x.ok) {
                            closeD();
                            var okTitle = (x.j && x.j.message) ? x.j.message : 'ثبت شد.';
                            function reloadPage() { window.location.reload(); }
                            if (window.AdminSwal && typeof AdminSwal.fire === 'function') {
                                AdminSwal.fire({
                                    icon: 'success',
                                    title: okTitle,
                                    text: 'عملیات با موفقیت انجام شد.',
                                }).then(reloadPage).catch(reloadPage);
                            } else {
                                reloadPage();
                            }
                        } else {
                            var msg = (x.j && x.j.message) ? x.j.message : 'خطا';
                            if (x.j && x.j.errors) {
                                var k = Object.keys(x.j.errors)[0];
                                if (k && x.j.errors[k] && x.j.errors[k][0]) msg = x.j.errors[k][0];
                            }
                            if (window.AdminSwal && typeof AdminSwal.fire === 'function') {
                                AdminSwal.fire({
                                    icon: 'error',
                                    title: msg,
                                    target: dialog || document.body,
                                });
                            }
                        }
                    });
            }

            function confirmReviewAction(act, thenPost) {
                function fallbackNativeConfirm() {
                    if (act === 'approve_apply') {
                        if (!window.confirm('پرداخت به میزان اعلام‌شده در قسط ثبت شود؟ این عمل قابل بازگشت آسان نیست.')) return;
                    } else if (act === 'reject') {
                        if (!window.confirm('این اعلام رد شود؟')) return;
                    } else if (act === 'approve') {
                        if (!window.confirm('وضعیت این اعلام به «تأیید شده» تغییر کند؟')) return;
                    }
                    thenPost();
                }
                if (!window.AdminSwal || typeof AdminSwal.confirm !== 'function') {
                    fallbackNativeConfirm();
                    return;
                }
                var opts;
                if (act === 'approve') {
                    opts = {
                        icon: 'question',
                        title: 'تأیید اعلام واریزی',
                        text: 'وضعیت این اعلام به «تأیید شده» تغییر کند؟',
                    };
                } else if (act === 'approve_apply') {
                    opts = {
                        icon: 'warning',
                        title: 'ثبت در پرداختی قسط',
                        html: 'پرداخت به میزان اعلام‌شده در قسط ثبت شود؟<br><span style="font-size:0.82em;opacity:0.88">این عمل قابل بازگشت آسان نیست.</span>',
                    };
                } else if (act === 'reject') {
                    opts = {
                        icon: 'warning',
                        title: 'عدم تأیید',
                        text: 'این اعلام واریزی رد شود؟',
                    };
                } else {
                    thenPost();
                    return;
                }
                if (dialog) {
                    opts.target = dialog;
                }
                AdminSwal.confirm(opts).then(function (res) {
                    if (res && res.isConfirmed) thenPost();
                }).catch(function () {
                    fallbackNativeConfirm();
                });
            }

            document.querySelectorAll('[data-dd-action]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var act = b.getAttribute('data-dd-action');
                    confirmReviewAction(act, function () { postReview(act); });
                });
            });
        })();
    </script>
@endpush
