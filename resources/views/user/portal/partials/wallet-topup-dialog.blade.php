@php($upWalletReady = (bool) ($userOnlinePaymentReady ?? false))
@php($upWalletTopupUrl = $userWalletOnlineTopupUrl ?? route('user.wallet.online-topup.start'))
@php($wMin = 10000)
@php($wMax = 500000000)
@php($portalWalletTopupPrefillToman = max(0, (int) session()->pull('portal_wallet_topup_prefill_toman', 0)))

<dialog id="portal-wallet-topup-dialog" class="portal-dialog portal-dialog--wallet-topup" aria-labelledby="portal-wallet-topup-title">
    <div class="portal-dialog__inner">
        <button type="button" class="portal-dialog__close" data-portal-wallet-topup-close aria-label="بستن">&times;</button>
        <h3 id="portal-wallet-topup-title" class="portal-dialog__title">
            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
            کیف پول شما
        </h3>
        <p class="portal-dialog__lead portal-dialog__lead--muted" style="margin:0;text-align:center">موجودی فعلی</p>
        <p class="portal-dialog__wallet-balance" id="portal-wallet-topup-balance-line">
            {{ $customerWalletBalanceFormatted }}<span class="up-wallet-currency">تومان</span>
        </p>
        <p class="portal-dialog__vpn-hint" id="portal-wallet-topup-vpn">
            پیش از ورود به درگاه، از خاموش بودن VPN خود اطمینان حاصل نمایید.
        </p>
        <form class="portal-dialog__wallet-form" method="post" action="{{ $upWalletTopupUrl }}" id="portal-wallet-topup-form" novalidate>
            @csrf
            <input type="hidden" name="amount_toman" id="portal-wallet-topup-amount-hidden" value="">
            <div>
                <label for="portal-wallet-topup-amount">مبلغ شارژ (تومان)</label>
                <input
                    type="text"
                    class="portal-wallet-topup-amount-field"
                    id="portal-wallet-topup-amount"
                    inputmode="numeric"
                    pattern="[0-9۰-۹,\s]*"
                    placeholder="مثلاً ۵۰۰٬۰۰۰"
                    required
                    autocomplete="off"
                    aria-describedby="portal-wallet-topup-amount-hint"
                >
                <p class="portal-dialog__hint" id="portal-wallet-topup-amount-hint" style="margin-top:0.35rem">
                    حداقل {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format($wMin, 0, '.', ',')) }} و حداکثر
                    {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format($wMax, 0, '.', ',')) }} تومان در هر تراکنش.
                </p>
            </div>
            <div class="portal-dialog__actions" style="margin-top:0.25rem;padding-top:0">
                <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block" id="portal-wallet-topup-submit" @if(!$upWalletReady) disabled title="درگاه پرداخت در تنظیمات مدیریت تکمیل نشده است." @endif>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    شارژ کیف پول
                </button>
                @unless($upWalletReady)
                    <p class="portal-dialog__hint" style="margin-top:0.45rem;text-align:center">درگاه پرداخت توسط مدیریت فعال نشده است؛ پس از تکمیل تنظیمات مالی ادمین دوباره تلاش کنید.</p>
                @endunless
            </div>
        </form>
    </div>
</dialog>

@push('scripts')
    <script>window.__PORTAL_WALLET_TOPUP_PREFILL_TOMAN__ = {{ (int) $portalWalletTopupPrefillToman }};</script>
    @if($errors->has('amount_toman'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var msg = @json($errors->first('amount_toman'));
                if (window.AdminSwal && AdminSwal.fire) {
                    AdminSwal.fire({ icon: 'error', title: 'مبلغ شارژ', text: msg });
                } else if (window.alert) {
                    window.alert(msg);
                }
            });
        </script>
    @endif
    <script>
        (function () {
            var openBtn = document.getElementById('up-wallet-open-btn');
            var dialog = document.getElementById('portal-wallet-topup-dialog');
            var form = document.getElementById('portal-wallet-topup-form');
            var amountVisible = document.getElementById('portal-wallet-topup-amount');
            var amountHidden = document.getElementById('portal-wallet-topup-amount-hidden');
            if (!dialog || !form || !amountVisible || !amountHidden) return;

            var minV = {{ (int) $wMin }};
            var maxV = {{ (int) $wMax }};
            var maxDigits = String(maxV).length;

            function faDigits(s) {
                return String(s).replace(/\d/g, function (d) {
                    return '۰۱۲۳۴۵۶۷۸۹'[parseInt(d, 10)];
                });
            }
            function normalizeDigits(s) {
                var fa = '۰۱۲۳۴۵۶۷۸۹';
                var ar = '٠١٢٣٤٥٦٧٨٩';
                var t = String(s || '');
                var out = '';
                for (var i = 0; i < t.length; i++) {
                    var ch = t[i];
                    var fi = fa.indexOf(ch);
                    if (fi >= 0) {
                        out += String(fi);
                        continue;
                    }
                    var ai = ar.indexOf(ch);
                    if (ai >= 0) {
                        out += String(ai);
                        continue;
                    }
                    if (ch >= '0' && ch <= '9') out += ch;
                }
                if (out.length > maxDigits) out = out.slice(0, maxDigits);
                return out;
            }
            function addThousandsCommas(digits) {
                if (!digits) return '';
                var rev = digits.split('').reverse().join('');
                var parts = [];
                for (var i = 0; i < rev.length; i += 3) {
                    parts.push(rev.substr(i, 3).split('').reverse().join(''));
                }
                return parts.reverse().join(',');
            }
            function formatDisplayFromDigits(digits) {
                return digits ? faDigits(addThousandsCommas(digits)) : '';
            }
            function formatRangeHint() {
                return faDigits(addThousandsCommas(String(minV))) + ' تا ' + faDigits(addThousandsCommas(String(maxV)));
            }

            function syncFromDigits(digits) {
                amountHidden.value = digits;
                amountVisible.value = formatDisplayFromDigits(digits);
            }

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

            function closeOtherPortalPaymentDialogs() {
                [
                    'portal-installment-pay-dialog',
                    'portal-loans-settle-dialog',
                    'portal-settle-dialog',
                ].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el && el.open) el.close();
                });
            }

            function clampPrefillToman(raw) {
                var x = Math.floor(Number(raw) || 0);
                if (x < 1) return 0;
                if (x < minV) x = minV;
                if (x > maxV) x = maxV;
                return x;
            }

            document.querySelectorAll('[data-portal-wallet-topup-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            function openWalletTopupModal(prefillToman) {
                if (typeof dialog.showModal !== 'function') return;
                var n = clampPrefillToman(prefillToman);
                syncFromDigits(n > 0 ? String(n) : '');
                closeOtherPortalPaymentDialogs();
                dialog.showModal();
                setTimeout(function () {
                    try {
                        amountVisible.focus();
                    } catch (e) { /* noop */ }
                }, 80);
            }

            window.portalOpenWalletTopupPrefill = function (toman) {
                openWalletTopupModal(toman);
            };

            function bindOpenTrigger(el) {
                el.addEventListener('click', function () {
                    openWalletTopupModal(0);
                });
                if (el.tagName !== 'BUTTON') {
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            openWalletTopupModal(0);
                        }
                    });
                }
            }

            if (openBtn) bindOpenTrigger(openBtn);
            document.querySelectorAll('[data-portal-wallet-topup-open]').forEach(bindOpenTrigger);

            function runWhenDomReady(fn) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', fn);
                } else {
                    fn();
                }
            }
            runWhenDomReady(function () {
                var pre = typeof window.__PORTAL_WALLET_TOPUP_PREFILL_TOMAN__ === 'number'
                    ? window.__PORTAL_WALLET_TOPUP_PREFILL_TOMAN__
                    : 0;
                if (pre > 0) {
                    openWalletTopupModal(pre);
                }
            });

            amountVisible.addEventListener('input', function () {
                var digits = normalizeDigits(amountVisible.value);
                var n = digits ? parseInt(digits, 10) : NaN;
                if (Number.isFinite(n) && n > maxV) {
                    digits = String(maxV);
                }
                syncFromDigits(digits);
                try {
                    var len = amountVisible.value.length;
                    amountVisible.setSelectionRange(len, len);
                } catch (e) { /* noop */ }
            });

            amountVisible.addEventListener('blur', function () {
                var digits = normalizeDigits(amountVisible.value);
                var n = digits ? parseInt(digits, 10) : NaN;
                if (Number.isFinite(n) && n > maxV) digits = String(maxV);
                syncFromDigits(digits);
            });

            form.addEventListener('submit', function (e) {
                var digits = normalizeDigits(amountVisible.value) || String(amountHidden.value || '').replace(/\D/g, '');
                var n = parseInt(digits, 10);
                if (!Number.isFinite(n) || n < minV || n > maxV) {
                    e.preventDefault();
                    var msg = 'مبلغ را بین ' + formatRangeHint() + ' تومان وارد کنید.';
                    if (window.AdminSwal && AdminSwal.fire) {
                        AdminSwal.fire({ icon: 'warning', title: 'مبلغ نامعتبر', text: msg });
                    } else {
                        window.alert(msg);
                    }
                    return;
                }
                amountHidden.value = String(n);
            });
        })();
    </script>
@endpush
