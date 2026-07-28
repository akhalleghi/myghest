@php($upPayReady = (bool) ($userOnlinePaymentReady ?? false))
@php($onlinePayAdminEnabled = (bool) ($customerOnlinePaymentEnabled ?? true))
@php($upFsAllPayUrl = $userLoanFullSettlementAllOnlinePayUrl ?? route('user.loans.full-settlement-all.online-pay.start'))
@php($upWalletFsAllUrl = $userLoanFullSettlementAllWalletPayUrl ?? route('user.loans.full-settlement-all.wallet-pay'))
@php($settleAllNs = $settleAllDialogNamespace ?? 'portal-settle-all')
@php($settleAllReturnRoute = $settleAllReturnRouteName ?? 'user.dashboard')
@php($settleAllCloseAttr = $settleAllCloseDataAttr ?? 'data-portal-settle-all-close')
@php($settleAllQuote = $settleAllQuote ?? null)

@if(!empty($settleAllQuote) && (int) ($settleAllQuote['amount_toman'] ?? 0) > 0)
<dialog id="{{ $settleAllNs }}-dialog" class="portal-dialog portal-dialog--settle" aria-labelledby="{{ $settleAllNs }}-title">
    <div class="portal-dialog__inner portal-dialog__inner--settle">
        <button type="button" class="portal-dialog__close" {{ $settleAllCloseAttr }} aria-label="بستن">&times;</button>
        <h3 id="{{ $settleAllNs }}-title" class="portal-dialog__title">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            تسویه کامل همه وام‌ها
        </h3>

        <p class="portal-settle-note" style="margin-top:0">
            {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) (int) ($settleAllQuote['files_count'] ?? 0)) }}
            پروندهٔ قابل تسویه در یک مرحله تسویه می‌شود.
        </p>

        <div class="portal-settle-summary" aria-live="polite">
            <div class="portal-settle-summary__total portal-settle-summary__total--stack">
                <span class="portal-settle-summary__k">مبلغ قابل پرداخت</span>
                <strong class="portal-settle-summary__v" id="{{ $settleAllNs }}-total">{{ $settleAllQuote['amount_fa'] ?? '—' }}</strong>
            </div>
            <div class="portal-settle-summary__rows">
                <div class="portal-settle-summary__row">
                    <span class="portal-settle-summary__k">ماندهٔ بدهی</span>
                    <span class="portal-settle-summary__v" id="{{ $settleAllNs }}-remaining">{{ $settleAllQuote['principal_fa'] ?? '—' }}</span>
                </div>
                @if((int) ($settleAllQuote['late_fee_toman'] ?? 0) > 0)
                    <div class="portal-settle-summary__row" id="{{ $settleAllNs }}-late-row">
                        <span class="portal-settle-summary__k">جمع جریمهٔ دیرکرد</span>
                        <span class="portal-settle-summary__v" id="{{ $settleAllNs }}-late">{{ $settleAllQuote['late_fee_fa'] ?? '—' }}</span>
                    </div>
                @endif
            </div>
        </div>

        <form class="portal-settle-actions" method="post" action="{{ $upFsAllPayUrl }}" id="{{ $settleAllNs }}-pay-form">
            @csrf
            <input type="hidden" name="return_route" value="{{ $settleAllReturnRoute }}">
            <span class="portal-online-pay-stack" style="width:100%">
                <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block @unless($upPayReady) portal-loan__btn--disabled @endunless" id="{{ $settleAllNs }}-pay-submit" @if(!$upPayReady) disabled title="{{ $onlinePayAdminEnabled ? 'درگاه پرداخت فعال نیست.' : 'پرداخت آنلاین توسط مدیریت غیرفعال شده است.' }}" @endif>
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
                <strong class="portal-settle-summary__v" id="{{ $settleAllNs }}-wallet-line">
                    {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format(max(0, (int) ($customerWalletBalanceToman ?? 0)), 0, '.', ',')) }} تومان
                </strong>
            </div>
            <p class="portal-settle-wallet-hint" id="{{ $settleAllNs }}-wallet-hint" style="display:none" aria-live="polite"></p>
            <div id="{{ $settleAllNs }}-wallet-topup-wrap" class="portal-settle-actions" style="display:none">
                @if(!empty($customerOnlinePaymentEnabled))
                    <button type="button" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block" id="{{ $settleAllNs }}-wallet-topup-btn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        شارژ کیف پول
                    </button>
                @else
                    <span class="portal-online-pay-stack" style="width:100%">
                        <button type="button" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block portal-loan__btn--disabled" id="{{ $settleAllNs }}-wallet-topup-btn" disabled aria-disabled="true" title="پرداخت آنلاین توسط مدیریت غیرفعال شده است.">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            شارژ کیف پول
                        </button>
                        <span class="portal-online-pay-off-label">غیرفعال</span>
                    </span>
                @endif
            </div>
            <form class="portal-settle-actions" method="post" action="{{ $upWalletFsAllUrl }}" id="{{ $settleAllNs }}-wallet-form">
                @csrf
                <input type="hidden" name="return_route" value="{{ $settleAllReturnRoute }}">
                <input type="hidden" name="payment_idempotency_key" id="{{ $settleAllNs }}-wallet-idem" value="" required>
                <button type="submit" class="portal-loan__btn portal-loan__btn--wallet portal-loan__btn--block" id="{{ $settleAllNs }}-wallet-submit"
                    data-need-toman="{{ (int) ($settleAllQuote['amount_toman'] ?? 0) }}">
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    تسویه همه از کیف پول
                </button>
            </form>
        </div>
    </div>
</dialog>
@endif
