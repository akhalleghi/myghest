@php($upPayReady = (bool) ($userOnlinePaymentReady ?? false))
@php($upPayUrl = $userInstallmentOnlinePayUrl ?? route('user.installments.online-pay.start'))
@php($upWalletPayUrl = $userInstallmentWalletPayUrl ?? route('user.installments.wallet-pay'))
@php($walletBal = (int) ($customerWalletBalanceToman ?? 0))

<dialog id="portal-installment-pay-dialog" class="portal-dialog portal-dialog--pay-inst" aria-labelledby="portal-installment-pay-title">
    <div class="portal-dialog__inner">
        <button type="button" class="portal-dialog__close" data-portal-installment-pay-close aria-label="بستن">&times;</button>
        <h3 id="portal-installment-pay-title" class="portal-dialog__title">
            <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
            پرداخت قسط
        </h3>
        <p class="portal-dialog__hint" style="margin-top:0.35rem;text-align:center;line-height:1.65">
            ابتدا مبلغ و مشخصات را بررسی کنید؛ سپس یکی از دو روش زیر را انتخاب کنید.
        </p>
        <div class="portal-dialog__inst-dl" id="portal-installment-pay-kv" aria-live="polite"></div>

        <div class="portal-pay-path-card portal-pay-path-card--gateway">
            <p class="portal-dialog__lead" style="font-size:0.88rem;font-weight:900;color:var(--text)">
                <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                پرداخت از درگاه بانکی
            </p>
            <p class="portal-dialog__hint" style="margin-top:0.35rem;text-align:center">
                روش پیشنهادی برای پرداخت سریع و مطمئن قسط.
            </p>
            <p class="portal-dialog__vpn-hint" id="portal-installment-pay-vpn">
                پیش از ورود به درگاه، از خاموش بودن VPN خود اطمینان حاصل نمایید.
            </p>
            <form class="portal-dialog__actions" method="post" action="{{ $upPayUrl }}" id="portal-installment-pay-form">
                @csrf
                <input type="hidden" name="customer_loan_installment_id" id="portal-installment-pay-id" value="" required>
                <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block" id="portal-installment-pay-submit" @if(!$upPayReady) disabled title="درگاه پرداخت در تنظیمات مدیریت تکمیل نشده است." @endif>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    ورود به درگاه و پرداخت قسط
                </button>
                @unless($upPayReady)
                    <p class="portal-dialog__hint" style="margin-top:0.5rem;text-align:center">درگاه پرداخت توسط مدیریت فعال نشده است؛ می‌توانید در صورت کفایت موجودی از کیف پول زیر استفاده کنید.</p>
                @endunless
            </form>
        </div>

        <div class="portal-pay-alt-block">
            <p class="portal-pay-alt-heading">یا پرداخت با کیف پول</p>
            <div class="portal-pay-wallet-panel">
                <p class="portal-dialog__lead portal-dialog__lead--muted" style="margin:0;text-align:center;font-size:0.8rem">
                    موجودی کیف پول شما:
                    <strong id="portal-installment-pay-wallet-line">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format(max(0, $walletBal), 0, '.', ',')) }} تومان</strong>
                </p>
                <div id="portal-installment-pay-wallet-hint" class="portal-dialog__hint" style="display:none;text-align:center;margin-top:0.35rem"></div>
                <div id="portal-installment-pay-wallet-topup-wrap" class="portal-dialog__actions" style="display:none;margin-top:0.35rem;padding-top:0">
                    <button type="button" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block" id="portal-installment-pay-wallet-topup-btn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        شارژ کیف پول (پوشش کمبود)
                    </button>
                </div>
                <form class="portal-dialog__actions portal-pay-wallet-form" method="post" action="{{ $upWalletPayUrl }}" id="portal-installment-wallet-pay-form">
                    @csrf
                    <input type="hidden" name="customer_loan_installment_id" id="portal-installment-wallet-pay-id" value="" required>
                    <input type="hidden" name="payment_idempotency_key" id="portal-installment-wallet-idempotency" value="" required>
                    <button type="submit" class="portal-loan__btn portal-loan__btn--ghost portal-loan__btn--block" id="portal-installment-wallet-pay-submit">
                        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                        پرداخت این قسط از کیف پول
                    </button>
                </form>
            </div>
        </div>
    </div>
</dialog>

@push('scripts')
    <script>
        (function () {
            var dialog = document.getElementById('portal-installment-pay-dialog');
            var form = document.getElementById('portal-installment-pay-form');
            var inputId = document.getElementById('portal-installment-pay-id');
            var kv = document.getElementById('portal-installment-pay-kv');
            var wForm = document.getElementById('portal-installment-wallet-pay-form');
            var wId = document.getElementById('portal-installment-wallet-pay-id');
            var wIdem = document.getElementById('portal-installment-wallet-idempotency');
            var wSubmit = document.getElementById('portal-installment-wallet-pay-submit');
            var wHint = document.getElementById('portal-installment-pay-wallet-hint');
            var topWrap = document.getElementById('portal-installment-pay-wallet-topup-wrap');
            var topBtn = document.getElementById('portal-installment-pay-wallet-topup-btn');
            var lastWalletShortToman = 0;
            if (!dialog || !form || !inputId || !kv || !wForm || !wId || !wIdem || !wSubmit) return;

            if (topBtn) {
                topBtn.addEventListener('click', function () {
                    var s = lastWalletShortToman;
                    closeDialog();
                    if (typeof window.portalOpenWalletTopupPrefill === 'function') {
                        window.portalOpenWalletTopupPrefill(s);
                    }
                });
            }

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

            function esc(s) {
                if (s == null) return '';
                var d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML;
            }

            function row(k, v) {
                return '<div><span class="portal-dialog__inst-k">' + esc(k) + '</span><span class="portal-dialog__inst-v">' + esc(v) + '</span></div>';
            }

            function readDs(el) {
                var ds = el.dataset || {};
                return {
                    installmentId: ds.installmentId || '',
                    loanTitle: ds.loanTitle || '',
                    loanCodeFa: ds.loanCodeFa || '',
                    sequenceFa: ds.sequenceFa || '',
                    amountFa: ds.amountFa || '',
                    dueJalali: ds.dueJalali || '',
                    paidFa: ds.paidFa || '',
                    slotRemainingFa: ds.slotRemainingFa || '',
                    onlinePayableFa: ds.onlinePayableFa || '',
                    onlinePayableToman: parseInt(String(ds.onlinePayableToman || '0'), 10) || 0,
                    statusLine: ds.statusLine || ''
                };
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

            window.openPortalInstallmentPayModal = function (sourceEl) {
                if (!sourceEl) return;
                var root = sourceEl.closest ? (sourceEl.closest('[data-inst-root]') || sourceEl) : sourceEl;
                var d = readDs(root);
                if (!d.installmentId) return;

                var balSynced = 0;
                if (typeof window.portalApplyWalletBalanceToGlobals === 'function') {
                    balSynced = window.portalApplyWalletBalanceToGlobals();
                }
                var wLine = document.getElementById('portal-installment-pay-wallet-line');
                if (wLine && typeof window.portalFormatFaTomanLine === 'function') {
                    wLine.textContent = window.portalFormatFaTomanLine(balSynced);
                }

                inputId.value = String(d.installmentId);
                wId.value = String(d.installmentId);
                wIdem.value = newIdempotencyKey();

                var parts = [];
                parts.push(row('عنوان وام', d.loanTitle || '—'));
                parts.push(row('شماره قسط', d.sequenceFa || '—'));
                parts.push(row('مبلغ قابل پرداخت', d.onlinePayableFa || d.slotRemainingFa || '—'));
                kv.innerHTML = parts.join('');

                var bal = typeof window.__PORTAL_WALLET_BALANCE_TOMAN__ === 'number' ? window.__PORTAL_WALLET_BALANCE_TOMAN__ : balSynced;
                var need = d.onlinePayableToman > 0 ? d.onlinePayableToman : 0;
                var short = need > bal ? (need - bal) : 0;
                lastWalletShortToman = short;
                if (topWrap) {
                    topWrap.style.display = need > 0 && short > 0 ? 'block' : 'none';
                }
                if (wHint) {
                    if (need > 0 && short > 0) {
                        wHint.style.display = 'block';
                        wHint.innerHTML =
                            'برای پرداخت این قسط از کیف پول، حداقل <strong>' + esc(faMoneyFromToman(need)) + '</strong> نیاز است. ' +
                            'کمبود: <strong>' + esc(faMoneyFromToman(short)) + '</strong> — با دکمهٔ زیر می‌توانید همین مبلغ را در فرم شارژ پیش‌نویس کنید.';
                    } else if (need < 1) {
                        wHint.style.display = 'none';
                        wHint.textContent = '';
                    } else {
                        wHint.style.display = 'none';
                        wHint.textContent = '';
                    }
                }
                if (wSubmit) {
                    wSubmit.disabled = need < 1 || short > 0;
                    if (need < 1) {
                        wSubmit.title = 'مبلغی برای پرداخت تعریف نشده است.';
                    } else if (short > 0) {
                        wSubmit.title = 'موجودی کیف پول کافی نیست؛ از دکمهٔ شارژ یا پرداخت آنلاین استفاده کنید.';
                    } else {
                        wSubmit.title = '';
                    }
                }

                if (typeof dialog.showModal === 'function') dialog.showModal();
            };

            document.querySelectorAll('[data-portal-installment-pay-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            function portalPayOnlineBlockedMessage() {
                return 'ابتدا قسط‌های قبلی را پرداخت نمایید.';
            }

            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;

                var payBlocked = t.closest('[data-portal-pay-online-blocked]');
                if (payBlocked) {
                    e.preventDefault();
                    e.stopPropagation();
                    var msg = portalPayOnlineBlockedMessage();
                    if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                        window.AdminSwal.fire({ icon: 'info', title: 'پرداخت قسط', text: msg });
                    } else {
                        window.alert(msg);
                    }
                    return;
                }

                var payBtn = t.closest('[data-portal-pay-online]');
                if (payBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var src = payBtn.closest('[data-inst-root]') || payBtn;
                    window.openPortalInstallmentPayModal(src);
                    return;
                }

                var inst = t.closest('.portal-inst[data-portal-inst-online-pay="1"]');
                if (inst && !t.closest('a, button, summary')) {
                    window.openPortalInstallmentPayModal(inst);
                }

                var tr = t.closest('tr[data-portal-inst-online-pay="1"]');
                if (tr && !t.closest('a, button')) {
                    window.openPortalInstallmentPayModal(tr);
                }

                var card = t.closest('article.portal-loans-inst-card[data-portal-inst-online-pay="1"]');
                if (card && !t.closest('a, button')) {
                    window.openPortalInstallmentPayModal(card);
                }
            });
        })();
    </script>
@endpush
