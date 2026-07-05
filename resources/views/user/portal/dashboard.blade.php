@extends('layouts.user.app')

@section('title', $pageTitle)

@section('content')
    @php($portalWalletBal = max(0, (int) (($portalSummary['wallet_balance_toman'] ?? null) ?? ($customerWalletBalanceToman ?? 0))))
    @if(!empty($showUserBankingCard))
        <section class="portal-banking" aria-labelledby="portal-banking-title">
            <div class="portal-banking__shell">
                <h2 id="portal-banking-title" class="portal-banking__title">اطلاعات بانکی</h2>
                <div class="portal-banking__grid">
                    <div class="portal-banking__media">
                        <img
                            class="portal-banking__img"
                            src="{{ asset('images/portal/bank-details.png') }}"
                            width="280"
                            height="200"
                            loading="lazy"
                            decoding="async"
                            alt=""
                            role="presentation"
                        >
                    </div>
                    <div class="portal-banking__body">
                        <div class="portal-banking__html" dir="rtl">
                            {!! $bankingInfoHtmlSafe !!}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @php($pl = $portalLoans ?? ['loan_count' => 0, 'loans' => [], 'loan_count_fa' => '۰'])
    <section class="portal-loans" aria-labelledby="portal-loans-title">
        <div class="portal-loans__shell">
            <header class="portal-loans__head">
                <div class="portal-loans__head-main">
                    <i class="fa-solid fa-folder-open portal-loans__head-ico" aria-hidden="true"></i>
                    <h2 id="portal-loans-title" class="portal-loans__title">پرونده‌های اقساط من</h2>
                </div>
                <span class="portal-loans__badge" title="تعداد پرونده‌ها">{{ $pl['loan_count_fa'] }} پرونده</span>
            </header>

            @if(empty($pl['loans']))
                <div class="portal-loans__empty">
                    <i class="fa-regular fa-folder-open portal-loans__empty-ico" aria-hidden="true"></i>
                    <p>پرونده‌ای ثبت نشده است.</p>
                </div>
            @else
                <div class="portal-loans__list">
                    @foreach ($pl['loans'] as $loan)
                        @php($ribbon = $loan['ribbon'] ?? null)
                        <article
                            class="portal-loan {{ $ribbon ? 'portal-loan--state-'.$ribbon : '' }}"
                            data-loan-id="{{ (int) $loan['id'] }}"
                            data-contract-locked="{{ ($loan['contract_locked'] ?? false) ? '1' : '0' }}"
                        >
                            <div class="portal-loan__bar {{ $ribbon ? '' : 'portal-loan__bar--solo' }}">
                                <div class="portal-loan__code-card">
                                    <span class="portal-loan__code-card-k"><i class="fa-solid fa-hashtag" aria-hidden="true"></i> کد پرونده</span>
                                    <span class="portal-loan__code-card-v">{{ $loan['loan_code'] }}</span>
                                </div>
                                @if($ribbon)
                                    <div class="portal-loan__ribbon-slot" role="status">
                                        <div class="portal-loan__ribbon">
                                            <i class="{{ $loan['ribbon_icon'] }} portal-loan__ribbon-ico" aria-hidden="true"></i>
                                            <span class="portal-loan__ribbon-text">
                                                @if($ribbon === 'settled')
                                                    تسویه شده
                                                @elseif($ribbon === 'revoked')
                                                    فسخ شده
                                                @else
                                                    بستانکار
                                                @endif
                                            </span>
                                            @if($ribbon === 'settled' && !empty($loan['settled_at_jalali']))
                                                <span class="portal-loan__ribbon-sub">{{ $loan['settled_at_jalali'] }}</span>
                                            @elseif($ribbon === 'revoked' && !empty($loan['revoked_at_jalali']))
                                                <span class="portal-loan__ribbon-sub">{{ $loan['revoked_at_jalali'] }}</span>
                                            @elseif($ribbon === 'creditor' && !empty($loan['creditor_overpay_fa']))
                                                <span class="portal-loan__ribbon-sub">{{ $loan['creditor_overpay_fa'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="portal-loan__top">
                                <h3 class="portal-loan__title">{{ $loan['loan_title'] }}</h3>
                            </div>

                            <div class="portal-loan__progress" aria-label="پیشرفت بازپرداخت">
                                <div class="portal-loan__progress-track">
                                    <div class="portal-loan__progress-fill" style="width: {{ (int) ($loan['progress_percent'] ?? 0) }}%"></div>
                                </div>
                                <span class="portal-loan__progress-label">
                                    <i class="fa-solid fa-chart-simple portal-loan__inline-ico" aria-hidden="true"></i>
                                    {{ $loan['progress_percent'] ?? 0 }}٪ بازپرداخت‌شده
                                </span>
                            </div>

                            <div class="portal-loan__stats">
                                <span class="portal-loan__stat">
                                    <span class="portal-loan__stat-in">
                                        <span class="portal-loan__stat-head">
                                            <i class="fa-regular fa-calendar portal-loan__stat-ico" aria-hidden="true"></i>
                                            <span class="portal-loan__stat-k">شروع</span>
                                        </span>
                                        <span class="portal-loan__stat-v">{{ $loan['loan_start_jalali'] }}</span>
                                    </span>
                                </span>
                                <span class="portal-loan__stat">
                                    <span class="portal-loan__stat-in">
                                        <span class="portal-loan__stat-head">
                                            <i class="fa-solid fa-sack-dollar portal-loan__stat-ico" aria-hidden="true"></i>
                                            <span class="portal-loan__stat-k">مبلغ وام</span>
                                        </span>
                                        <span class="portal-loan__stat-v">{{ $loan['amount_fa'] }}</span>
                                    </span>
                                </span>
                                <span class="portal-loan__stat">
                                    <span class="portal-loan__stat-in">
                                        <span class="portal-loan__stat-head">
                                            <i class="fa-solid fa-receipt portal-loan__stat-ico" aria-hidden="true"></i>
                                            <span class="portal-loan__stat-k">هر قسط</span>
                                        </span>
                                        <span class="portal-loan__stat-v">{{ $loan['installment_amount_fa'] }}</span>
                                    </span>
                                </span>
                                <span class="portal-loan__stat portal-loan__stat--paid">
                                    <span class="portal-loan__stat-in">
                                        <span class="portal-loan__stat-head">
                                            <i class="fa-solid fa-check-double portal-loan__stat-ico" aria-hidden="true"></i>
                                            <span class="portal-loan__stat-k">پرداخت‌شده</span>
                                        </span>
                                        <span class="portal-loan__stat-v">{{ $loan['paid_amount_fa'] }} <span class="portal-loan__stat-paren">({{ $loan['paid_installments_count_fa'] }} قسط)</span></span>
                                    </span>
                                </span>
                                <span class="portal-loan__stat portal-loan__stat--remain {{ ($loan['is_creditor'] ?? false) ? 'portal-loan__stat--creditor' : (($loan['settled_for_ui'] ?? false) ? 'portal-loan__stat--ok' : 'portal-loan__stat--warn') }}">
                                    <span class="portal-loan__stat-in">
                                        @if($loan['is_creditor'] ?? false)
                                            <span class="portal-loan__stat-head">
                                                <i class="fa-solid fa-scale-balanced portal-loan__stat-ico" aria-hidden="true"></i>
                                                <span class="portal-loan__stat-k">بستانکاری</span>
                                            </span>
                                            <span class="portal-loan__stat-v">{{ $loan['creditor_overpay_fa'] }}</span>
                                        @else
                                            <span class="portal-loan__stat-head">
                                                <i class="fa-solid fa-wallet portal-loan__stat-ico" aria-hidden="true"></i>
                                                <span class="portal-loan__stat-k">باقیمانده تعهد</span>
                                            </span>
                                            <span class="portal-loan__stat-v">{{ $loan['remaining_amount_fa'] }} <span class="portal-loan__stat-paren">({{ $loan['remaining_installments_count_fa'] }} قسط)</span></span>
                                        @endif
                                    </span>
                                </span>
                            </div>

                            @if(!empty($loan['show_settle_button']))
                                <div class="portal-loan__settle-row">
                                    <button
                                        type="button"
                                        class="portal-loan__btn portal-loan__btn--settle"
                                        data-portal-settle-open
                                        data-loan-file-id="{{ (int) $loan['id'] }}"
                                        data-remaining-fa="{{ $loan['remaining_amount_fa'] }}"
                                        data-late-fa="{{ $loan['late_fee_estimate_fa'] }}"
                                        data-late-toman="{{ (int) ($loan['late_fee_estimate_toman'] ?? 0) }}"
                                        data-total-fa="{{ $loan['full_settlement_online_fa'] ?? '' }}"
                                        data-settlement-toman="{{ (int) ($loan['full_settlement_online_toman'] ?? 0) }}"
                                    >
                                        <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                                        تسویه کلی بدهی
                                    </button>
                                </div>
                            @endif

                            <details class="portal-loan__details">
                                <summary class="portal-loan__summary">
                                    <span class="portal-loan__summary-inner">
                                        <i class="fa-solid fa-list-ol" aria-hidden="true"></i>
                                        جزئیات اقساط
                                    </span>
                                </summary>
                                <div class="portal-loan__inst-list">
                                    @foreach ($loan['installments'] as $inst)
                                        <div
                                            class="portal-inst portal-inst--tone-{{ $inst['status_tone'] ?? 'pending' }}"
                                            id="portal-inst-{{ (int) $inst['id'] }}"
                                            @if(!empty($inst['actions_enabled']))
                                                data-inst-root="1"
                                            @endif
                                            @if(!empty($inst['online_pay_eligible']))
                                                data-portal-inst-online-pay="1"
                                            @endif
                                            data-installment-id="{{ (int) $inst['id'] }}"
                                            data-loan-title="{{ e($loan['loan_title'] ?? '') }}"
                                            data-loan-code-fa="{{ e($loan['loan_code_fa'] ?? '') }}"
                                            data-sequence-fa="{{ e($inst['sequence_fa'] ?? '') }}"
                                            data-amount-fa="{{ e($inst['amount_fa'] ?? '') }}"
                                            data-due-jalali="{{ e($inst['due_jalali'] ?? '') }}"
                                            data-paid-fa="{{ e($inst['paid_fa'] ?? '') }}"
                                            data-slot-remaining-fa="{{ e($inst['slot_remaining_fa'] ?? '') }}"
                                            data-slot-remaining-toman="{{ (int) ($inst['slot_remaining_toman'] ?? 0) }}"
                                            data-payment-ceiling-toman="{{ (int) ($inst['payment_ceiling_toman'] ?? 0) }}"
                                            data-payment-ceiling-fa="{{ e($inst['payment_ceiling_fa'] ?? '') }}"
                                            data-nominal-amount-toman="{{ (int) ($inst['amount_toman'] ?? 0) }}"
                                            data-paid-amount-toman="{{ (int) ($inst['paid_amount_toman'] ?? 0) }}"
                                            data-online-payable-fa="{{ e($inst['online_payable_fa'] ?? '') }}"
                                            data-online-payable-toman="{{ (int) ($inst['online_payable_toman'] ?? 0) }}"
                                            data-status-line="{{ e($inst['status_line'] ?? '') }}"
                                        >
                                            @php($onlinePayTomanInst = (int) ($inst['online_payable_toman'] ?? 0))
                                            @php($slotRemTomanInst = (int) ($inst['slot_remaining_toman'] ?? 0))
                                            <div class="portal-inst__head">
                                                <span class="portal-inst__n">
                                                    <i class="fa-regular fa-file-lines portal-loan__inline-ico" aria-hidden="true"></i>
                                                    قسط {{ $inst['sequence_fa'] }}
                                                </span>
                                                <span class="portal-inst__status">
                                                    <i class="{{ $inst['status_icon'] ?? 'fa-regular fa-clock' }}" aria-hidden="true"></i>
                                                    {{ $inst['status_line'] }}
                                                </span>
                                            </div>
                                            <div class="portal-inst__tiles">
                                                <div>
                                                    <span class="portal-inst__k">
                                                        @if(!empty($inst['online_pay_eligible']) && $onlinePayTomanInst > 0)
                                                            قابل پرداخت آنلاین
                                                        @else
                                                            مبلغ قسط
                                                        @endif
                                                    </span>
                                                    <span class="portal-inst__v">
                                                        @if(!empty($inst['online_pay_eligible']) && $onlinePayTomanInst > 0)
                                                            {{ $inst['online_payable_fa'] ?? '—' }}
                                                        @else
                                                            {{ $inst['amount_fa'] ?? '—' }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <div><span class="portal-inst__k">سررسید</span><span class="portal-inst__v">{{ $inst['due_jalali'] }}</span></div>
                                                <div><span class="portal-inst__k">پرداختی</span><span class="portal-inst__v">{{ $inst['paid_fa'] }}</span></div>
                                                <div><span class="portal-inst__k">تاریخ واریز</span><span class="portal-inst__v">{{ $inst['deposit_jalali'] }}</span></div>
                                            </div>
                                            @if(!empty($inst['online_pay_eligible']) && $onlinePayTomanInst > 0 && $onlinePayTomanInst < $slotRemTomanInst)
                                                <p class="portal-inst__note">مبلغ نامی ماندهٔ این قسط {{ $inst['slot_remaining_fa'] }} است؛ با توجه به پرونده، مبلغ قابل پرداخت در درگاه همان مبلغ «قابل پرداخت آنلاین» است.</p>
                                            @endif
                                            @if(($inst['status_note'] ?? '') !== '—')
                                                <p class="portal-inst__note">{{ $inst['status_note'] }}</p>
                                            @endif
                                            @if(!empty($inst['actions_enabled']))
                                                @php($showWalletPayBtn = !empty($inst['wallet_pay_eligible']) && $portalWalletBal > 0)
                                                @php($showOnlinePayBtn = !empty($inst['online_pay_eligible']) || !empty($inst['online_pay_prior_sequence_block']))
                                                @php($instActionBtnCount = 1 + ($showWalletPayBtn ? 1 : 0) + ($showOnlinePayBtn ? 1 : 0))
                                                <div class="portal-inst__actions{{ $instActionBtnCount > 2 ? ' portal-inst__actions--multi' : '' }}">
                                                    <a
                                                        class="portal-loan__btn portal-loan__btn--ghost{{ !empty($inst['has_deposit_declaration']) ? ' portal-loan__btn--declared' : '' }}"
                                                        href="{{ route('user.deposits.index', ['installment' => (int) $inst['id']]) }}"
                                                    >
                                                        <span class="portal-loan__btn__inner">
                                                            <span class="portal-loan__btn__label">
                                                                <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                                                اعلام واریزی
                                                            </span>
                                                            @if(!empty($inst['has_deposit_declaration']) && !empty($inst['deposit_declaration_created_jalali']))
                                                                <span class="portal-loan__btn__declare-date">{{ $inst['deposit_declaration_created_jalali'] }}</span>
                                                            @endif
                                                        </span>
                                                    </a>
                                                    @if($showWalletPayBtn)
                                                        <button
                                                            type="button"
                                                            class="portal-loan__btn portal-loan__btn--wallet"
                                                            data-portal-pay-wallet
                                                            data-installment-label="قسط {{ $inst['sequence_fa'] }}"
                                                        >
                                                            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                                            پرداخت از کیف پول
                                                        </button>
                                                    @endif
                                                    @if(!empty($inst['online_pay_eligible']))
                                                        <button
                                                            type="button"
                                                            class="portal-loan__btn portal-loan__btn--primary"
                                                            data-portal-pay-online
                                                            data-installment-label="قسط {{ $inst['sequence_fa'] }}"
                                                        >
                                                            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                                            پرداخت آنلاین
                                                        </button>
                                                    @elseif(!empty($inst['online_pay_prior_sequence_block']))
                                                        <button
                                                            type="button"
                                                            class="portal-loan__btn portal-loan__btn--primary"
                                                            data-portal-pay-online-blocked
                                                            data-installment-label="قسط {{ $inst['sequence_fa'] }}"
                                                        >
                                                            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                                            پرداخت آنلاین
                                                        </button>
                                                    @endif
                                                </div>
                                                @if(!empty($inst['online_pay_prior_sequence_block']))
                                                    <p class="portal-inst__note" role="note">برای پرداخت آنلاین این قسط، ابتدا قسط‌های قبلی را به‌طور کامل تسویه کنید.</p>
                                                @endif
                                            @else
                                                <div class="portal-inst__locked" role="note">
                                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                                    @if($loan['is_revoked'] ?? false)
                                                        به‌دلیل فسخ قرارداد امکان ثبت پرداخت از اینجا وجود ندارد.
                                                    @elseif($loan['is_creditor'] ?? false)
                                                        تعهد شما پوشش داده شده است؛ اقدامی لازم نیست.
                                                    @elseif($inst['slot_fully_paid'] ?? false)
                                                        این قسط از نظر نامی تسویه شده است.
                                                    @elseif($loan['contract_locked'] ?? false)
                                                        پرونده از نظر تعهد تسویه است؛ پرداخت‌ها بین اقساط توسط سیستم هماهنگ شده‌اند.
                                                    @else
                                                        اقدام در دسترس نیست.
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if(!empty($portalSummary))
        <section class="portal-summary" aria-labelledby="portal-summary-title">
            <h2 id="portal-summary-title" class="visually-hidden">خلاصه مالی و پشتیبانی</h2>
            <div class="portal-summary__grid">
                <article class="portal-sum-card portal-sum-card--loans">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-sack-dollar"></i>
                        </span>
                        <h3 class="portal-sum-card__title">مجموع وام‌ها</h3>
                    </div>
                    <p class="portal-sum-card__value portal-sum-card__value--money">{{ $portalSummary['total_loans_principal_fa'] }}</p>
                    <p class="portal-sum-card__hint">جمع اصل وام پرونده‌های فعال</p>
                </article>
                <article class="portal-sum-card portal-sum-card--paid">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </span>
                        <h3 class="portal-sum-card__title">مجموع پرداخت‌ها</h3>
                    </div>
                    <p class="portal-sum-card__value portal-sum-card__value--money">{{ $portalSummary['total_payments_fa'] }}</p>
                    <p class="portal-sum-card__hint">واریز ثبت‌شده روی اقساط</p>
                </article>
                <article class="portal-sum-card portal-sum-card--remain">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </span>
                        <h3 class="portal-sum-card__title">باقیمانده اقساط</h3>
                    </div>
                    <p class="portal-sum-card__value portal-sum-card__value--money">{{ $portalSummary['remaining_installments_fa'] }}</p>
                    <p class="portal-sum-card__hint">ماندهٔ تعهد (پس از تخفیف)</p>
                </article>
                <article
                    class="portal-sum-card portal-sum-card--wallet"
                    role="button"
                    tabindex="0"
                    data-portal-wallet-topup-open
                    aria-haspopup="dialog"
                    aria-controls="portal-wallet-topup-dialog"
                    aria-labelledby="portal-dash-wallet-card-title"
                    aria-describedby="portal-dash-wallet-card-hint"
                >
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                        <h3 class="portal-sum-card__title" id="portal-dash-wallet-card-title">کیف پول</h3>
                    </div>
                    <p class="portal-sum-card__value portal-sum-card__value--money">{{ $portalSummary['wallet_balance_fa'] }}</p>
                    <p class="portal-sum-card__hint" id="portal-dash-wallet-card-hint">موجودی قابل استفاده — برای شارژ کلیک کنید</p>
                </article>
                <article class="portal-sum-card portal-sum-card--tickets">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-ticket"></i>
                        </span>
                        <h3 class="portal-sum-card__title">تیکت‌ها</h3>
                    </div>
                    <p class="portal-sum-card__value">{{ $portalSummary['tickets_count_fa'] }}</p>
                    <p class="portal-sum-card__hint">تیکت باز — <a href="{{ route('user.tickets.index') }}">مشاهده</a></p>
                </article>
            </div>
        </section>
    @endif

    

    @include('user.portal.partials.full-settlement-dialog', [
        'settleDialogNamespace' => 'portal-settle',
        'settleReturnRouteName' => 'user.dashboard',
        'settleCloseDataAttr' => 'data-portal-dialog-close',
    ])
@endsection

@push('scripts')
    <script>
        (function () {
            var dialog = document.getElementById('portal-settle-dialog');
            if (!dialog) return;
            var lastDashSettleWalletShortToman = 0;

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

            function newIdempotencyKey() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID();
                }
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (Math.random() * 16) | 0;
                    var v = c === 'x' ? r : (r & 0x3) | 0x8;
                    return v.toString(16);
                });
            }

            function faMoneyFromToman(t) {
                if (!Number.isFinite(t) || t < 1) return '—';
                var s = String(Math.floor(t));
                var rev = s.split('').reverse().join('');
                var parts = [];
                for (var i = 0; i < rev.length; i += 3) {
                    parts.push(rev.substr(i, 3).split('').reverse().join(''));
                }
                var joined = parts.reverse().join(',');
                return joined.replace(/\d/g, function (d) {
                    return '۰۱۲۳۴۵۶۷۸۹'[parseInt(d, 10)];
                }) + ' تومان';
            }

            document.querySelectorAll('[data-portal-settle-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var balHeader = 0;
                    if (typeof window.portalApplyWalletBalanceToGlobals === 'function') {
                        balHeader = window.portalApplyWalletBalanceToGlobals();
                    }
                    var wLineEl = document.getElementById('portal-settle-wallet-line');
                    if (wLineEl && typeof window.portalFormatFaTomanLine === 'function') {
                        wLineEl.textContent = window.portalFormatFaTomanLine(balHeader);
                    }
                    var rem = btn.getAttribute('data-remaining-fa') || '—';
                    var late = btn.getAttribute('data-late-fa') || '—';
                    var total = btn.getAttribute('data-total-fa') || '—';
                    var lid = btn.getAttribute('data-loan-file-id') || '';
                    var needT = parseInt(btn.getAttribute('data-settlement-toman') || '0', 10) || 0;
                    var lateT = parseInt(btn.getAttribute('data-late-toman') || '0', 10) || 0;
                    var elR = document.getElementById('portal-settle-remaining');
                    var elL = document.getElementById('portal-settle-late');
                    var elT = document.getElementById('portal-settle-total');
                    var lateRow = document.getElementById('portal-settle-late-row');
                    var hid = document.getElementById('portal-settle-loan-file-id');
                    var wHid = document.getElementById('portal-settle-wallet-loan-file-id');
                    var wIdem = document.getElementById('portal-settle-wallet-idem');
                    var wSub = document.getElementById('portal-settle-wallet-submit');
                    var wHint = document.getElementById('portal-settle-wallet-hint');
                    var settleTopWrap = document.getElementById('portal-settle-wallet-topup-wrap');
                    if (elR) elR.textContent = rem;
                    if (elL) elL.textContent = late;
                    if (elT) elT.textContent = total;
                    if (lateRow) lateRow.style.display = lateT > 0 ? '' : 'none';
                    if (hid) hid.value = lid;
                    if (wHid) wHid.value = lid;
                    if (wIdem) wIdem.value = newIdempotencyKey();
                    var bal = typeof window.__PORTAL_WALLET_BALANCE_TOMAN__ === 'number' ? window.__PORTAL_WALLET_BALANCE_TOMAN__ : balHeader;
                    var short = needT > bal ? (needT - bal) : 0;
                    lastDashSettleWalletShortToman = short;
                    if (settleTopWrap) {
                        settleTopWrap.style.display = needT > 0 && short > 0 ? 'block' : 'none';
                    }
                    if (wHint) {
                        if (needT > 0 && short > 0) {
                            wHint.style.display = 'block';
                            wHint.textContent = 'موجودی کافی نیست؛ کمبود: ' + faMoneyFromToman(short);
                        } else {
                            wHint.style.display = 'none';
                            wHint.textContent = '';
                        }
                    }
                    if (wSub) {
                        wSub.disabled = needT < 1 || short > 0;
                        wSub.title = short > 0 ? 'موجودی کیف پول کافی نیست.' : '';
                    }
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                });
            });

            document.querySelectorAll('[data-portal-dialog-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });

            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            var settleTopBtn = document.getElementById('portal-settle-wallet-topup-btn');
            if (settleTopBtn) {
                settleTopBtn.addEventListener('click', function () {
                    if (dialog.open) closeDialog();
                    if (typeof window.portalOpenWalletTopupPrefill === 'function') {
                        window.portalOpenWalletTopupPrefill(lastDashSettleWalletShortToman);
                    }
                });
            }

            var settleWalletForm = document.getElementById('portal-settle-wallet-form');
            if (settleWalletForm) {
                settleWalletForm.addEventListener('submit', function (e) {
                    var wHid = document.getElementById('portal-settle-wallet-loan-file-id');
                    if (!wHid || String(wHid.value || '').trim() === '') {
                        e.preventDefault();
                        if (window.AdminSwal && AdminSwal.fire) {
                            AdminSwal.fire({ icon: 'warning', title: 'تسویه', text: 'پرونده انتخاب نشده است.' });
                        }
                    }
                });
            }

            var settleForm = document.getElementById('portal-settle-pay-form');
            if (settleForm) {
                settleForm.addEventListener('submit', function (e) {
                    var hid = document.getElementById('portal-settle-loan-file-id');
                    if (!hid || String(hid.value || '').trim() === '') {
                        e.preventDefault();
                        if (window.AdminSwal && AdminSwal.fire) {
                            AdminSwal.fire({ icon: 'warning', title: 'تسویه', text: 'پرونده انتخاب نشده است.' });
                        }
                    }
                });
            }
        })();
    </script>
@endpush
