@extends('layouts.user.app')

@section('title', $pageTitle)

@section('content')
    @php($pl = $portalLoans ?? ['loan_count' => 0, 'loans' => [], 'loan_count_fa' => '۰'])

    <section class="portal-loans-page" aria-labelledby="portal-loans-page-title">
        <header class="portal-loans-page__head">
            <div class="portal-loans-page__head-main">
                <i class="fa-solid fa-file-invoice-dollar portal-loans-page__head-ico" aria-hidden="true"></i>
                <h1 id="portal-loans-page-title" class="portal-loans-page__title">لیست وام‌ها</h1>
            </div>
            <span class="portal-loans-page__badge" title="تعداد پرونده‌ها">{{ $pl['loan_count_fa'] }} پرونده</span>
        </header>

        @if(empty($pl['loans']))
            <div class="portal-loans-page__empty">
                <i class="fa-regular fa-folder-open portal-loans-page__empty-ico" aria-hidden="true"></i>
                <p>پرونده‌ای ثبت نشده است.</p>
            </div>
        @else
            <div class="portal-loans-page__grid">
                @foreach ($pl['loans'] as $loan)
                    @php($ribbon = $loan['ribbon'] ?? null)
                    <article
                        class="portal-loan-board {{ $ribbon ? 'portal-loan-board--state-'.$ribbon : '' }}"
                        data-loan-id="{{ (int) $loan['id'] }}"
                    >
                        <div class="portal-loan-board__bar">
                            <div class="portal-loan-board__code-card">
                                <span class="portal-loan-board__code-k"><i class="fa-solid fa-hashtag" aria-hidden="true"></i> کد پرونده</span>
                                <span class="portal-loan-board__code-v">{{ $loan['loan_code'] }}</span>
                            </div>
                            @if($ribbon)
                                <div class="portal-loan-board__ribbon-slot" role="status">
                                    <div class="portal-loan-board__ribbon">
                                        <i class="{{ $loan['ribbon_icon'] }} portal-loan-board__ribbon-ico" aria-hidden="true"></i>
                                        <span class="portal-loan-board__ribbon-text">
                                            @if($ribbon === 'settled')
                                                تسویه شده
                                            @elseif($ribbon === 'revoked')
                                                فسخ شده
                                            @else
                                                بستانکار
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="portal-loan-board__cols">
                            <div class="portal-loan-board__col portal-loan-board__col--contract">
                                <h2 class="portal-loan-board__col-title">{{ $loan['loan_title'] }}</h2>
                                <hr class="portal-loan-board__sep" aria-hidden="true">
                                <dl class="portal-loan-board__kv">
                                    <div class="portal-loan-board__kv-row">
                                        <dt>شماره پرونده</dt>
                                        <dd><span class="portal-loan-board__val-ltr">{{ $loan['loan_code_fa'] }}</span></dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>شماره فرعی</dt>
                                        <dd>{{ $loan['sub_file_display_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>تاریخ شروع</dt>
                                        <dd>{{ $loan['loan_start_jalali'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>مبلغ وام</dt>
                                        <dd>{{ $loan['amount_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>مبلغ وام با بهره</dt>
                                        <dd>{{ $loan['total_repayable_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>تعداد اقساط</dt>
                                        <dd>{{ $loan['installments_total_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>مبلغ قسط</dt>
                                        <dd>{{ $loan['installment_amount_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>مبلغ پیش‌پرداخت</dt>
                                        <dd>{{ $loan['down_payment_fa'] }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="portal-loan-board__col portal-loan-board__col--repay">
                                <h2 class="portal-loan-board__col-title portal-loan-board__col-title--sub">بازپرداخت‌ها</h2>
                                <hr class="portal-loan-board__sep portal-loan-board__sep--fine" aria-hidden="true">
                                <dl class="portal-loan-board__kv">
                                    <div class="portal-loan-board__kv-row">
                                        <dt>تعداد اقساط پرداختی</dt>
                                        <dd>{{ $loan['paid_installments_slot_count_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>اقساط پرداخت‌شده</dt>
                                        <dd>{{ $loan['paid_amount_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>دیرکرد / زودکرد</dt>
                                        <dd>
                                            <span class="portal-loan-board__kv-strong">{{ $loan['early_late_money_line_fa'] }}</span>
                                            <span class="portal-loan-board__kv-note">{{ $loan['early_late_detail_fa'] }}</span>
                                        </dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>تخفیف</dt>
                                        <dd>{{ $loan['discount_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row portal-loan-board__kv-row--emph">
                                        <dt>مبلغ باقیمانده</dt>
                                        <dd>{{ $loan['remaining_status_line_fa'] }}</dd>
                                    </div>
                                    <div class="portal-loan-board__kv-row">
                                        <dt>تسویه شده</dt>
                                        <dd>{{ $loan['settled_yes_no_fa'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="portal-loan-board__footer">
                            <button
                                type="button"
                                class="portal-loan__btn portal-loan__btn--ghost"
                                data-portal-open-inst-list="{{ (int) $loan['id'] }}"
                            >
                                <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                                مشاهده اقساط
                            </button>
                            @if(!empty($loan['show_settle_button']))
                                <button
                                    type="button"
                                    class="portal-loan__btn portal-loan__btn--primary"
                                    data-portal-settle-open
                                    data-remaining-fa="{{ $loan['remaining_amount_fa'] }}"
                                    data-late-fa="{{ $loan['late_fee_estimate_fa'] }}"
                                >
                                    <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                                    تسویه بدهی
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--disabled"
                                    data-portal-settle-disabled
                                    data-settle-disable-msg="{{ e($loan['settle_disabled_reason_fa'] ?? 'تسویه در دسترس نیست.') }}"
                                    aria-disabled="true"
                                >
                                    <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                                    تسویه بدهی
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <dialog id="portal-loans-settle-dialog" class="portal-dialog" aria-labelledby="portal-loans-settle-title">
        <div class="portal-dialog__inner">
            <button type="button" class="portal-dialog__close" data-portal-loans-dialog-close aria-label="بستن">&times;</button>
            <h3 id="portal-loans-settle-title" class="portal-dialog__title">
                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                تسویه بدهی
            </h3>
            <p class="portal-dialog__lead">ماندهٔ تعهد قسطی (پس از تخفیف‌های ثبت‌شده):</p>
            <p class="portal-dialog__amount" id="portal-loans-settle-remaining">—</p>
            <p class="portal-dialog__lead portal-dialog__lead--muted">برآورد جریمهٔ دیرکرد تا امروز:</p>
            <p class="portal-dialog__sub" id="portal-loans-settle-late">—</p>
            <p class="portal-dialog__hint">مبالغ برآوردی هستند. پرداخت آنلاین به‌زودی فعال می‌شود.</p>
            <div class="portal-dialog__actions">
                <button type="button" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block" data-portal-loans-settle-pay>
                    <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                    پرداخت آنلاین
                </button>
            </div>
        </div>
    </dialog>

    <dialog id="portal-loans-inst-dialog" class="portal-dialog portal-dialog--wide" aria-labelledby="portal-loans-inst-title">
        <div class="portal-dialog__inner portal-dialog__inner--wide">
            <button type="button" class="portal-dialog__close" data-portal-loans-dialog-close aria-label="بستن">&times;</button>
            <h3 id="portal-loans-inst-title" class="portal-dialog__title">
                <i class="fa-solid fa-list-ol" aria-hidden="true"></i>
                <span id="portal-loans-inst-heading">اقساط</span>
            </h3>
            <p class="portal-loans-inst__sub" id="portal-loans-inst-sub"></p>
            <div class="portal-loans-inst__scroll">
                <div class="portal-loans-inst__desktop-table">
                    <table class="portal-loans-inst__tbl">
                        <thead>
                            <tr>
                                <th scope="col">شماره قسط</th>
                                <th scope="col">مبلغ قسط</th>
                                <th scope="col">سررسید</th>
                                <th scope="col">مبلغ پرداختی</th>
                                <th scope="col">تاریخ واریز</th>
                                <th scope="col">دیرکرد / زودکرد</th>
                                <th scope="col">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="portal-loans-inst-tbody"></tbody>
                    </table>
                </div>
                <div id="portal-loans-inst-cards" class="portal-loans-inst-cards" role="list"></div>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__PORTAL_LOANS_LIST__ = @json($pl['loans'] ?? []);
        window.__PORTAL_LOANS_ROUTES__ = { depositsIndex: @json(route('user.deposits.index')) };
    </script>
    <script>
        (function () {
            var settleDialog = document.getElementById('portal-loans-settle-dialog');
            var instDialog = document.getElementById('portal-loans-inst-dialog');
            var loans = window.__PORTAL_LOANS_LIST__ || [];
            var routes = window.__PORTAL_LOANS_ROUTES__ || { depositsIndex: '' };

            function closeLoansDialogs() {
                if (settleDialog && settleDialog.open) settleDialog.close();
                if (instDialog && instDialog.open) instDialog.close();
            }

            document.querySelectorAll('[data-portal-loans-dialog-close]').forEach(function (b) {
                b.addEventListener('click', closeLoansDialogs);
            });

            if (settleDialog) {
                settleDialog.addEventListener('click', function (e) {
                    if (e.target === settleDialog) closeLoansDialogs();
                });
            }
            if (instDialog) {
                instDialog.addEventListener('click', function (e) {
                    if (e.target === instDialog) closeLoansDialogs();
                });
            }

            document.querySelectorAll('[data-portal-settle-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!settleDialog) return;
                    var rem = btn.getAttribute('data-remaining-fa') || '—';
                    var late = btn.getAttribute('data-late-fa') || '—';
                    var elR = document.getElementById('portal-loans-settle-remaining');
                    var elL = document.getElementById('portal-loans-settle-late');
                    if (elR) elR.textContent = rem;
                    if (elL) elL.textContent = late;
                    if (typeof settleDialog.showModal === 'function') settleDialog.showModal();
                });
            });

            document.querySelectorAll('[data-portal-settle-disabled]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var msg = btn.getAttribute('data-settle-disable-msg') || 'تسویه در دسترس نیست.';
                    if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                        window.AdminSwal.fire({ icon: 'info', title: 'تسویه بدهی', text: msg });
                    } else {
                        window.alert(msg);
                    }
                });
            });

            document.querySelectorAll('[data-portal-loans-settle-pay]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                        window.AdminSwal.fire({
                            icon: 'info',
                            title: 'پرداخت آنلاین',
                            text: 'امکان پرداخت آنلاین تسویه کلی به‌زودی فعال می‌شود؛ فعلاً از اعلام واریزی یا مراجعه حضوری استفاده کنید.',
                        });
                    }
                    closeLoansDialogs();
                });
            });

            function findLoan(id) {
                var sid = String(id);
                for (var i = 0; i < loans.length; i++) {
                    if (String(loans[i].id) === sid) return loans[i];
                }
                return null;
            }

            function depositHref(installmentId) {
                var base = routes.depositsIndex || '';
                var sep = base.indexOf('?') >= 0 ? '&' : '?';
                return base + sep + 'installment=' + encodeURIComponent(String(installmentId));
            }

            function setInstallmentPayDataset(el, loan, inst) {
                if (!el) return;
                el.setAttribute('data-installment-id', String(inst.id || ''));
                el.setAttribute('data-loan-title', loan.loan_title != null ? String(loan.loan_title) : '');
                el.setAttribute('data-loan-code-fa', loan.loan_code_fa != null ? String(loan.loan_code_fa) : '');
                el.setAttribute('data-sequence-fa', inst.sequence_fa != null ? String(inst.sequence_fa) : String(inst.sequence || ''));
                el.setAttribute('data-amount-fa', inst.amount_fa != null ? String(inst.amount_fa) : '');
                el.setAttribute('data-due-jalali', inst.due_jalali != null ? String(inst.due_jalali) : '');
                el.setAttribute('data-paid-fa', inst.paid_fa != null ? String(inst.paid_fa) : '');
                el.setAttribute('data-slot-remaining-fa', inst.slot_remaining_fa != null ? String(inst.slot_remaining_fa) : '');
                el.setAttribute('data-status-line', inst.status_line != null ? String(inst.status_line) : '');
            }

            function appendInstActions(container, loan, inst) {
                var wrap = document.createElement('div');
                wrap.className = 'portal-loans-inst__actions';

                var aDep = document.createElement('a');
                aDep.className = 'portal-loan__btn portal-loan__btn--ghost portal-loan__btn--table';
                aDep.href = depositHref(inst.id);
                aDep.innerHTML = '<i class="fa-solid fa-building-columns" aria-hidden="true"></i> اعلام واریزی';

                var btnPay = document.createElement('button');
                btnPay.type = 'button';
                btnPay.className = 'portal-loan__btn portal-loan__btn--primary portal-loan__btn--table';
                btnPay.setAttribute('data-portal-pay-online', '');
                btnPay.setAttribute('data-installment-label', 'قسط ' + (inst.sequence_fa || String(inst.sequence || '')));
                btnPay.innerHTML = '<i class="fa-solid fa-credit-card" aria-hidden="true"></i> پرداخت آنلاین';
                setInstallmentPayDataset(btnPay, loan, inst);

                if (inst.actions_enabled) {
                    wrap.appendChild(aDep);
                    wrap.appendChild(btnPay);
                } else {
                    var span = document.createElement('span');
                    span.className = 'portal-loans-inst__locked';
                    span.textContent = '—';
                    wrap.appendChild(span);
                }
                container.appendChild(wrap);
            }

            function cardKvRow(dtText, ddText) {
                var row = document.createElement('div');
                row.className = 'portal-loans-inst-card__kv-row';
                var dt = document.createElement('dt');
                dt.textContent = dtText;
                var dd = document.createElement('dd');
                dd.textContent = ddText != null ? String(ddText) : '';
                row.appendChild(dt);
                row.appendChild(dd);
                return row;
            }

            function fillInstTable(loan) {
                var tbody = document.getElementById('portal-loans-inst-tbody');
                var cardsRoot = document.getElementById('portal-loans-inst-cards');
                var h = document.getElementById('portal-loans-inst-heading');
                var sub = document.getElementById('portal-loans-inst-sub');
                if (!tbody || !loan) return;
                tbody.textContent = '';
                if (cardsRoot) cardsRoot.textContent = '';
                if (h) h.textContent = 'اقساط — ' + (loan.loan_title || 'وام');
                if (sub) {
                    sub.textContent = 'کد پرونده: ' + (loan.loan_code_fa || loan.loan_code || '') + ' — ' + (loan.loan_start_jalali || '');
                }
                var list = loan.installments || [];
                list.forEach(function (inst) {
                    var tr = document.createElement('tr');
                    if (inst.actions_enabled) {
                        tr.setAttribute('data-inst-root', '1');
                    }
                    setInstallmentPayDataset(tr, loan, inst);
                    var tdSeq = document.createElement('td');
                    tdSeq.textContent = inst.sequence_fa != null ? String(inst.sequence_fa) : '';
                    var tdAmt = document.createElement('td');
                    tdAmt.textContent = inst.amount_fa != null ? String(inst.amount_fa) : '';
                    var tdDue = document.createElement('td');
                    tdDue.textContent = inst.due_jalali != null ? String(inst.due_jalali) : '';
                    var tdPaid = document.createElement('td');
                    tdPaid.textContent = inst.paid_fa != null ? String(inst.paid_fa) : '';
                    var tdDep = document.createElement('td');
                    tdDep.textContent = inst.deposit_jalali != null ? String(inst.deposit_jalali) : '';
                    var tdLate = document.createElement('td');
                    tdLate.className = 'portal-loans-inst__cell-late';
                    tdLate.textContent = inst.early_late_cell_fa != null ? String(inst.early_late_cell_fa) : '—';
                    var tdAct = document.createElement('td');
                    appendInstActions(tdAct, loan, inst);
                    tr.appendChild(tdSeq);
                    tr.appendChild(tdAmt);
                    tr.appendChild(tdDue);
                    tr.appendChild(tdPaid);
                    tr.appendChild(tdDep);
                    tr.appendChild(tdLate);
                    tr.appendChild(tdAct);
                    tbody.appendChild(tr);

                    if (cardsRoot) {
                        var card = document.createElement('article');
                        card.className = 'portal-loans-inst-card';
                        if (inst.actions_enabled) {
                            card.setAttribute('data-inst-root', '1');
                        }
                        setInstallmentPayDataset(card, loan, inst);
                        card.setAttribute('role', 'listitem');
                        var head = document.createElement('header');
                        head.className = 'portal-loans-inst-card__head';
                        var badge = document.createElement('span');
                        badge.className = 'portal-loans-inst-card__badge';
                        badge.textContent = 'قسط ' + (inst.sequence_fa != null ? String(inst.sequence_fa) : '');
                        head.appendChild(badge);
                        card.appendChild(head);
                        var dl = document.createElement('dl');
                        dl.className = 'portal-loans-inst-card__kv';
                        dl.appendChild(cardKvRow('مبلغ قسط', inst.amount_fa));
                        dl.appendChild(cardKvRow('سررسید', inst.due_jalali));
                        dl.appendChild(cardKvRow('مبلغ پرداختی', inst.paid_fa));
                        dl.appendChild(cardKvRow('تاریخ واریز', inst.deposit_jalali));
                        dl.appendChild(cardKvRow('دیرکرد / زودکرد', inst.early_late_cell_fa != null ? String(inst.early_late_cell_fa) : '—'));
                        card.appendChild(dl);
                        var foot = document.createElement('div');
                        foot.className = 'portal-loans-inst-card__foot';
                        appendInstActions(foot, loan, inst);
                        card.appendChild(foot);
                        cardsRoot.appendChild(card);
                    }
                });
            }

            document.querySelectorAll('[data-portal-open-inst-list]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-portal-open-inst-list');
                    var loan = findLoan(id);
                    if (!instDialog || !loan) return;
                    fillInstTable(loan);
                    if (typeof instDialog.showModal === 'function') instDialog.showModal();
                });
            });

        })();
    </script>
@endpush
