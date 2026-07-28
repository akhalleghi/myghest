@php($overdueAllNs = $overdueAllDialogNamespace ?? 'portal-overdue-all')
@php($overdueAllCloseAttr = $overdueAllCloseDataAttr ?? 'data-portal-overdue-all-close')
@php($overdueAllQuote = $overdueAllQuote ?? null)
@php($overdueWalletBal = max(0, (int) ($customerWalletBalanceToman ?? 0)))

@if(!empty($overdueAllQuote) && (int) ($overdueAllQuote['amount_toman'] ?? 0) > 0)
<dialog id="{{ $overdueAllNs }}-dialog" class="portal-dialog portal-dialog--settle" data-portal-overdue-all-root aria-labelledby="{{ $overdueAllNs }}-title">
    <div class="portal-dialog__inner portal-dialog__inner--settle">
        <button type="button" class="portal-dialog__close" {{ $overdueAllCloseAttr }} aria-label="بستن">&times;</button>
        <h3 id="{{ $overdueAllNs }}-title" class="portal-dialog__title">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            تسویه بدهی معوق کل وام‌ها
        </h3>

        <p class="portal-settle-note" style="margin-top:0">
            جمع بدهی معوق
            {{ $overdueAllQuote['installments_count_fa'] ?? '۰' }}
            قسط در
            {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) (int) ($overdueAllQuote['files_count'] ?? 0)) }}
            پرونده.
            اقساط معوق را از همینجا پرداخت کنید؛ جریمهٔ دیرکرد با «تسویه کامل» قابل پرداخت است.
        </p>

        <div class="portal-settle-summary" aria-live="polite">
            <div class="portal-settle-summary__total portal-settle-summary__total--stack">
                <span class="portal-settle-summary__k">جمع بدهی معوق</span>
                <strong class="portal-settle-summary__v">{{ $overdueAllQuote['amount_fa'] ?? '—' }}</strong>
            </div>
            <div class="portal-settle-summary__rows">
                <div class="portal-settle-summary__row">
                    <span class="portal-settle-summary__k">ماندهٔ اقساط معوق</span>
                    <span class="portal-settle-summary__v">{{ $overdueAllQuote['principal_fa'] ?? '—' }}</span>
                </div>
                @if((int) ($overdueAllQuote['late_fee_toman'] ?? 0) > 0)
                    <div class="portal-settle-summary__row">
                        <span class="portal-settle-summary__k">برآورد جریمهٔ دیرکرد</span>
                        <span class="portal-settle-summary__v">{{ $overdueAllQuote['late_fee_fa'] ?? '—' }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="portal-overdue-all-list" role="list">
            @foreach(($overdueAllQuote['files'] ?? []) as $fileRow)
                <section class="portal-overdue-all-file" role="listitem">
                    <header class="portal-overdue-all-file__head">
                        <strong>{{ $fileRow['loan_title'] ?? 'وام' }}</strong>
                        <span class="portal-overdue-all-file__code">{{ $fileRow['loan_code_fa'] ?? ($fileRow['loan_code'] ?? '') }}</span>
                    </header>
                    <ul class="portal-overdue-all-insts">
                        @foreach(($fileRow['installments'] ?? []) as $inst)
                            @php($onlinePayEligible = !empty($inst['online_pay_eligible']))
                            @php($onlinePayBlocked = !empty($inst['online_pay_prior_sequence_block']))
                            @php($slotNeedToman = max(0, (int) ($inst['slot_remaining_toman'] ?? 0)))
                            @php($ceilingToman = max(0, (int) ($inst['payment_ceiling_toman'] ?? 0)))
                            @php($walletNeedToman = $ceilingToman > 0 ? min($slotNeedToman, $ceilingToman) : $slotNeedToman)
                            @php($showWalletPayBtn = !empty($inst['wallet_pay_eligible']) && $walletNeedToman > 0 && $overdueWalletBal >= $walletNeedToman)
                            <li
                                class="portal-overdue-all-inst"
                                data-inst-root="1"
                                data-installment-id="{{ (int) ($inst['id'] ?? 0) }}"
                                data-loan-title="{{ e($fileRow['loan_title'] ?? '') }}"
                                data-loan-code-fa="{{ e($fileRow['loan_code_fa'] ?? '') }}"
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
                                <div class="portal-overdue-all-inst__meta">
                                    <span>قسط {{ $inst['sequence_fa'] ?? '—' }}</span>
                                    <strong>{{ $inst['slot_remaining_fa'] ?? '—' }}</strong>
                                </div>
                                <div class="portal-overdue-all-inst__actions">
                                    @if($showWalletPayBtn)
                                        <button type="button" class="portal-loan__btn portal-loan__btn--wallet" data-portal-pay-wallet>
                                            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                            کیف پول
                                        </button>
                                    @endif
                                    @if($onlinePayEligible)
                                        <button type="button" class="portal-loan__btn portal-loan__btn--primary" data-portal-pay-online>
                                            <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                            درگاه
                                        </button>
                                    @elseif($onlinePayBlocked)
                                        <button type="button" class="portal-loan__btn portal-loan__btn--primary" data-portal-pay-online-blocked>
                                            <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                            درگاه
                                        </button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</dialog>

@push('scripts')
    <script>
        (function () {
            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var payBtn = t.closest('[data-portal-pay-online], [data-portal-pay-wallet], [data-portal-pay-online-blocked]');
                if (!payBtn) return;
                var overdueRoot = payBtn.closest('[data-portal-overdue-all-root]');
                if (!overdueRoot || !overdueRoot.open) return;
                try { overdueRoot.close(); } catch (err) { /* noop */ }
            }, true);
        })();
    </script>
@endpush
@endif
