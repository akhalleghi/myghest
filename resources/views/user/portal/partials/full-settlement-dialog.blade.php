@php($upPayReady = (bool) ($userOnlinePaymentReady ?? false))
@php($onlinePayAdminEnabled = (bool) ($customerOnlinePaymentEnabled ?? true))
@php($upFsPayUrl = $userLoanFullSettlementOnlinePayUrl ?? route('user.loans.full-settlement.online-pay.start'))
@php($upWalletFsUrl = $userLoanFullSettlementWalletPayUrl ?? route('user.loans.full-settlement.wallet-pay'))
@php($settleNs = $settleDialogNamespace ?? 'portal-settle')
@php($settleReturnRoute = $settleReturnRouteName ?? 'user.dashboard')
@php($settleCloseAttr = $settleCloseDataAttr ?? 'data-portal-dialog-close')

<dialog id="{{ $settleNs }}-dialog" class="portal-dialog portal-dialog--settle" aria-labelledby="{{ $settleNs }}-title">
    <div class="portal-dialog__inner portal-dialog__inner--settle">
        <button type="button" class="portal-dialog__close" {{ $settleCloseAttr }} aria-label="بستن">&times;</button>
        <h3 id="{{ $settleNs }}-title" class="portal-dialog__title">
            <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
            تسویه کامل بدهی
        </h3>

        <div class="portal-settle-summary" aria-live="polite">
            <div class="portal-settle-summary__total">
                <span class="portal-settle-summary__k">مبلغ قابل پرداخت</span>
                <strong class="portal-settle-summary__v" id="{{ $settleNs }}-total">—</strong>
            </div>
            <div class="portal-settle-summary__rows">
                <div class="portal-settle-summary__row">
                    <span class="portal-settle-summary__k">ماندهٔ بدهی</span>
                    <span class="portal-settle-summary__v" id="{{ $settleNs }}-remaining">—</span>
                </div>
                <div class="portal-settle-summary__row" id="{{ $settleNs }}-late-row">
                    <span class="portal-settle-summary__k">جریمهٔ دیرکرد</span>
                    <span class="portal-settle-summary__v" id="{{ $settleNs }}-late">—</span>
                </div>
            </div>
        </div>

        <form class="portal-settle-actions" method="post" action="{{ $upFsPayUrl }}" id="{{ $settleNs }}-pay-form">
            @csrf
            <input type="hidden" name="customer_loan_file_id" id="{{ $settleNs }}-loan-file-id" value="" required>
            <input type="hidden" name="return_route" value="{{ $settleReturnRoute }}">
            <span class="portal-online-pay-stack" style="width:100%">
                <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block @unless($upPayReady) portal-loan__btn--disabled @endunless" id="{{ $settleNs }}-pay-submit" @if(!$upPayReady) disabled title="{{ $onlinePayAdminEnabled ? 'درگاه پرداخت فعال نیست.' : 'پرداخت آنلاین توسط مدیریت غیرفعال شده است.' }}" @endif>
                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    پرداخت از درگاه بانکی
                </button>
                @unless($onlinePayAdminEnabled)
                    <span class="portal-online-pay-off-label">غیرفعال</span>
                @endunless
            </span>
            @if($onlinePayAdminEnabled && $upPayReady)
                <p class="portal-settle-note">قبل از ورود به درگاه، VPN را خاموش کنید.</p>
            @elseif($onlinePayAdminEnabled)
                <p class="portal-settle-note">درگاه پرداخت در حال حاضر فعال نیست.</p>
            @endif
        </form>

        <p class="portal-settle-divider" aria-hidden="true">یا</p>

        <div class="portal-settle-wallet-block">
            <div class="portal-settle-summary__row portal-settle-summary__row--wallet">
                <span class="portal-settle-summary__k">موجودی کیف پول</span>
                <strong class="portal-settle-summary__v" id="{{ $settleNs }}-wallet-line">
                    {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format(max(0, (int) ($customerWalletBalanceToman ?? 0)), 0, '.', ',')) }} تومان
                </strong>
            </div>
            <p class="portal-settle-wallet-hint" id="{{ $settleNs }}-wallet-hint" style="display:none" aria-live="polite"></p>
            <div id="{{ $settleNs }}-wallet-topup-wrap" class="portal-settle-actions" style="display:none">
                @if(!empty($customerOnlinePaymentEnabled))
                    <button type="button" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block" id="{{ $settleNs }}-wallet-topup-btn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        شارژ کیف پول
                    </button>
                @else
                    <span class="portal-online-pay-stack" style="width:100%">
                        <button type="button" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block portal-loan__btn--disabled" id="{{ $settleNs }}-wallet-topup-btn" disabled aria-disabled="true" title="پرداخت آنلاین توسط مدیریت غیرفعال شده است.">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            شارژ کیف پول
                        </button>
                        <span class="portal-online-pay-off-label">غیرفعال</span>
                    </span>
                @endif
            </div>
            <form class="portal-settle-actions" method="post" action="{{ $upWalletFsUrl }}" id="{{ $settleNs }}-wallet-form">
                @csrf
                <input type="hidden" name="customer_loan_file_id" id="{{ $settleNs }}-wallet-loan-file-id" value="" required>
                <input type="hidden" name="return_route" value="{{ $settleReturnRoute }}">
                <input type="hidden" name="payment_idempotency_key" id="{{ $settleNs }}-wallet-idem" value="" required>
                <button type="submit" class="portal-loan__btn portal-loan__btn--wallet portal-loan__btn--block" id="{{ $settleNs }}-wallet-submit">
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    تسویه از کیف پول
                </button>
            </form>
        </div>
    </div>
</dialog>
