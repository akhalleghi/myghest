@php($upWalletPayUrl = $userInstallmentWalletPayUrl ?? route('user.installments.wallet-pay'))
@php($portalInstWalletReturnRoute = request()->routeIs('user.dashboard') ? 'user.dashboard' : 'user.loans.index')

<dialog id="portal-installment-wallet-pay-dialog" class="portal-dialog portal-dialog--pay-inst portal-dialog--wallet-inst" aria-labelledby="portal-installment-wallet-pay-title">
    <div class="portal-dialog__inner portal-dialog__inner--wallet-inst">
        <button type="button" class="portal-dialog__close" data-portal-installment-wallet-pay-close aria-label="بستن">&times;</button>
        <h3 id="portal-installment-wallet-pay-title" class="portal-dialog__title">
            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
            پرداخت از کیف پول
        </h3>

        <p class="portal-wallet-inst__lead" id="portal-installment-wallet-pay-lead" aria-live="polite">—</p>

        <form class="portal-wallet-inst__form" method="post" action="{{ $upWalletPayUrl }}" id="portal-installment-wallet-pay-form" novalidate>
            @csrf
            <input type="hidden" name="return_route" value="{{ $portalInstWalletReturnRoute }}">
            <input type="hidden" name="customer_loan_installment_id" id="portal-installment-wallet-pay-id" value="" required>
            <input type="hidden" name="payment_idempotency_key" id="portal-installment-wallet-pay-idem" value="" required>
            <input type="hidden" name="amount_toman" id="portal-installment-wallet-pay-amount-hidden" value="">

            <div class="portal-wallet-inst__row">
                <span class="portal-wallet-inst__k">موجودی کیف پول</span>
                <strong class="portal-wallet-inst__v" id="portal-installment-wallet-pay-balance-line">—</strong>
            </div>

            <div class="portal-wallet-inst__field">
                <label for="portal-installment-wallet-pay-amount" class="portal-wallet-inst__label">مبلغ پرداخت (تومان)</label>
                <input
                    type="text"
                    class="portal-wallet-topup-amount-field"
                    id="portal-installment-wallet-pay-amount"
                    inputmode="numeric"
                    pattern="[0-9۰-۹,\s]*"
                    placeholder="مبلغ پرداخت"
                    required
                    autocomplete="off"
                >
            </div>

            <p class="portal-wallet-inst__after" id="portal-installment-wallet-pay-after" aria-live="polite"></p>

            <div class="portal-dialog__actions portal-wallet-inst__actions">
                <button type="submit" class="portal-loan__btn portal-loan__btn--wallet portal-loan__btn--block" id="portal-installment-wallet-pay-submit">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    تأیید و پرداخت
                </button>
            </div>
        </form>
    </div>
</dialog>

@push('scripts')
    <script>
        (function () {
            var dialog = document.getElementById('portal-installment-wallet-pay-dialog');
            var form = document.getElementById('portal-installment-wallet-pay-form');
            var inputId = document.getElementById('portal-installment-wallet-pay-id');
            var idem = document.getElementById('portal-installment-wallet-pay-idem');
            var amountVisible = document.getElementById('portal-installment-wallet-pay-amount');
            var amountHidden = document.getElementById('portal-installment-wallet-pay-amount-hidden');
            var lead = document.getElementById('portal-installment-wallet-pay-lead');
            var balanceLine = document.getElementById('portal-installment-wallet-pay-balance-line');
            var afterLine = document.getElementById('portal-installment-wallet-pay-after');
            var submitBtn = document.getElementById('portal-installment-wallet-pay-submit');
            if (!dialog || !form || !inputId || !idem || !amountVisible || !amountHidden) return;

            var state = { walletBal: 0, maxPay: 0, sequenceFa: '', loanTitle: '' };

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

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
                    if (fi >= 0) { out += String(fi); continue; }
                    var ai = ar.indexOf(ch);
                    if (ai >= 0) { out += String(ai); continue; }
                    if (ch >= '0' && ch <= '9') out += ch;
                }
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

            function faMoneyFromToman(t) {
                if (!Number.isFinite(t) || t < 1) return '—';
                return formatDisplayFromDigits(String(Math.floor(t))) + ' تومان';
            }

            function parseAmountInput() {
                var digits = normalizeDigits(amountVisible.value) || String(amountHidden.value || '').replace(/\D/g, '');
                var n = parseInt(digits, 10);
                return Number.isFinite(n) && n > 0 ? n : 0;
            }

            function syncAmountDigits(digits) {
                amountHidden.value = digits;
                amountVisible.value = formatDisplayFromDigits(digits);
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

            function readDs(el) {
                var ds = el.dataset || {};
                return {
                    installmentId: ds.installmentId || '',
                    loanTitle: ds.loanTitle || '',
                    sequenceFa: ds.sequenceFa || '',
                    slotRemainingToman: parseInt(String(ds.slotRemainingToman || '0'), 10) || 0,
                    paymentCeilingToman: parseInt(String(ds.paymentCeilingToman || '0'), 10) || 0
                };
            }

            function refreshUi() {
                var payAmt = parseAmountInput();
                if (afterLine) {
                    afterLine.textContent = payAmt > 0
                        ? 'ماندهٔ کیف پول پس از پرداخت: ' + faMoneyFromToman(Math.max(0, state.walletBal - payAmt))
                        : '';
                }
                if (submitBtn) {
                    submitBtn.disabled = state.maxPay < 1 || payAmt < 1 || payAmt > state.maxPay;
                }
            }

            window.openPortalInstallmentWalletPayModal = function (sourceEl) {
                if (!sourceEl) return;
                var root = sourceEl.closest ? (sourceEl.closest('[data-inst-root]') || sourceEl) : sourceEl;
                var d = readDs(root);
                if (!d.installmentId) return;

                var bal = 0;
                if (typeof window.portalApplyWalletBalanceToGlobals === 'function') {
                    bal = window.portalApplyWalletBalanceToGlobals();
                } else if (typeof window.__PORTAL_WALLET_BALANCE_TOMAN__ === 'number') {
                    bal = window.__PORTAL_WALLET_BALANCE_TOMAN__;
                }

                state.walletBal = Math.max(0, Math.floor(Number(bal) || 0));
                state.maxPay = Math.min(state.walletBal, d.paymentCeilingToman > 0 ? d.paymentCeilingToman : 0);
                state.sequenceFa = d.sequenceFa || '';
                state.loanTitle = d.loanTitle || '';

                inputId.value = String(d.installmentId);
                idem.value = newIdempotencyKey();

                if (lead) {
                    lead.textContent = 'قسط ' + (d.sequenceFa || '—') + ' — ' + (d.loanTitle || 'وام');
                }
                if (balanceLine) {
                    balanceLine.textContent = typeof window.portalFormatFaTomanLine === 'function'
                        ? window.portalFormatFaTomanLine(state.walletBal)
                        : faMoneyFromToman(state.walletBal);
                }

                var slotRem = d.slotRemainingToman > 0 ? d.slotRemainingToman : 0;
                var defaultPay = state.maxPay > 0 ? Math.min(state.maxPay, slotRem > 0 ? slotRem : state.maxPay) : 0;
                syncAmountDigits(defaultPay > 0 ? String(defaultPay) : '');
                refreshUi();

                if (typeof dialog.showModal === 'function') dialog.showModal();
                setTimeout(function () {
                    try { amountVisible.focus(); } catch (e) { /* noop */ }
                }, 80);
            };

            document.querySelectorAll('[data-portal-installment-wallet-pay-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            amountVisible.addEventListener('input', function () {
                var digits = normalizeDigits(amountVisible.value);
                var n = digits ? parseInt(digits, 10) : NaN;
                if (Number.isFinite(n) && n > state.maxPay && state.maxPay > 0) {
                    digits = String(state.maxPay);
                }
                syncAmountDigits(digits);
                refreshUi();
            });

            amountVisible.addEventListener('blur', function () {
                var digits = normalizeDigits(amountVisible.value);
                var n = digits ? parseInt(digits, 10) : NaN;
                if (Number.isFinite(n) && n > state.maxPay && state.maxPay > 0) {
                    digits = String(state.maxPay);
                }
                syncAmountDigits(digits);
                refreshUi();
            });

            form.addEventListener('submit', function (e) {
                var payAmt = parseAmountInput();
                if (payAmt < 1 || payAmt > state.maxPay) {
                    e.preventDefault();
                    var msg = state.maxPay < 1
                        ? 'موجودی کیف پول کافی نیست.'
                        : ('حداکثر مبلغ قابل پرداخت: ' + faMoneyFromToman(state.maxPay));
                    if (window.AdminSwal && AdminSwal.fire) {
                        window.AdminSwal.fire({ icon: 'warning', title: 'مبلغ پرداخت', text: msg });
                    } else {
                        window.alert(msg);
                    }
                    return;
                }
                amountHidden.value = String(payAmt);
                closeDialog();
            });

            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var btn = t.closest('[data-portal-pay-wallet]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var src = btn.closest('[data-inst-root]') || btn;
                window.openPortalInstallmentWalletPayModal(src);
            });
        })();
    </script>
@endpush
