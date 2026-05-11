@extends('layouts.user.app')

@section('title', $pageTitle)

@section('content')
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
                                        data-remaining-fa="{{ $loan['remaining_amount_fa'] }}"
                                        data-late-fa="{{ $loan['late_fee_estimate_fa'] }}"
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
                                        >
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
                                                <div><span class="portal-inst__k">مبلغ</span><span class="portal-inst__v">{{ $inst['amount_fa'] }}</span></div>
                                                <div><span class="portal-inst__k">سررسید</span><span class="portal-inst__v">{{ $inst['due_jalali'] }}</span></div>
                                                <div><span class="portal-inst__k">پرداختی</span><span class="portal-inst__v">{{ $inst['paid_fa'] }}</span></div>
                                                <div><span class="portal-inst__k">تاریخ واریز</span><span class="portal-inst__v">{{ $inst['deposit_jalali'] }}</span></div>
                                            </div>
                                            @if(($inst['status_note'] ?? '') !== '—')
                                                <p class="portal-inst__note">{{ $inst['status_note'] }}</p>
                                            @endif
                                            @if(!empty($inst['actions_enabled']))
                                                <div class="portal-inst__actions">
                                                    <a
                                                        class="portal-loan__btn portal-loan__btn--ghost"
                                                        href="{{ route('user.deposits.index', ['installment' => (int) $inst['id']]) }}"
                                                    >
                                                        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                                        اعلام واریزی
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="portal-loan__btn portal-loan__btn--primary"
                                                        data-portal-pay-online
                                                        data-installment-label="قسط {{ $inst['sequence_fa'] }}"
                                                    >
                                                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                                        پرداخت آنلاین
                                                    </button>
                                                </div>
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
                <article class="portal-sum-card portal-sum-card--wallet">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                        <h3 class="portal-sum-card__title">کیف پول</h3>
                    </div>
                    <p class="portal-sum-card__value portal-sum-card__value--money">{{ $portalSummary['wallet_balance_fa'] }}</p>
                    <p class="portal-sum-card__hint">موجودی قابل استفاده</p>
                </article>
                <article class="portal-sum-card portal-sum-card--tickets">
                    <div class="portal-sum-card__head">
                        <span class="portal-sum-card__ico-wrap" aria-hidden="true">
                            <i class="fa-solid fa-ticket"></i>
                        </span>
                        <h3 class="portal-sum-card__title">تیکت‌ها</h3>
                    </div>
                    <p class="portal-sum-card__value">{{ $portalSummary['tickets_count_fa'] }}</p>
                    <p class="portal-sum-card__hint">تیکت فعال — به‌زودی</p>
                </article>
            </div>
        </section>
    @endif

    

    <dialog id="portal-settle-dialog" class="portal-dialog" aria-labelledby="portal-settle-dialog-title">
        <div class="portal-dialog__inner">
            <button type="button" class="portal-dialog__close" data-portal-dialog-close aria-label="بستن">&times;</button>
            <h3 id="portal-settle-dialog-title" class="portal-dialog__title">
                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                تسویه کلی بدهی
            </h3>
            <p class="portal-dialog__lead">ماندهٔ تعهد قسطی (پس از تخفیف‌های ثبت‌شده):</p>
            <p class="portal-dialog__amount" id="portal-settle-remaining">—</p>
            <p class="portal-dialog__lead portal-dialog__lead--muted">برآورد جریمهٔ دیرکرد تا امروز:</p>
            <p class="portal-dialog__sub" id="portal-settle-late">—</p>
            <p class="portal-dialog__hint">مبالغ برآوردی هستند. پرداخت آنلاین به‌زودی فعال می‌شود.</p>
            <div class="portal-dialog__actions">
                <button type="button" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block" data-portal-settle-pay>
                    <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                    پرداخت
                </button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        (function () {
            var dialog = document.getElementById('portal-settle-dialog');
            if (!dialog) return;

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

            document.querySelectorAll('[data-portal-settle-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var rem = btn.getAttribute('data-remaining-fa') || '—';
                    var late = btn.getAttribute('data-late-fa') || '—';
                    var elR = document.getElementById('portal-settle-remaining');
                    var elL = document.getElementById('portal-settle-late');
                    if (elR) elR.textContent = rem;
                    if (elL) elL.textContent = late;
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                });
            });

            document.querySelectorAll('[data-portal-dialog-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });

            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            document.querySelectorAll('[data-portal-settle-pay]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                        window.AdminSwal.fire({
                            icon: 'info',
                            title: 'پرداخت آنلاین',
                            text: 'امکان پرداخت آنلاین تسویه کلی به‌زودی فعال می‌شود؛ فعلاً از اعلام واریزی یا مراجعه حضوری استفاده کنید.',
                        });
                    }
                    closeDialog();
                });
            });

            document.querySelectorAll('[data-portal-pay-online]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var label = btn.getAttribute('data-installment-label') || 'این قسط';
                    if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                        window.AdminSwal.fire({
                            icon: 'info',
                            title: 'پرداخت آنلاین',
                            text: 'پرداخت آنلاین برای «' + label + '» به‌زودی فعال می‌شود. در صورت نیاز فعلاً از «اعلام واریزی» استفاده کنید.',
                        });
                    }
                });
            });
        })();
    </script>
@endpush
