@php($upPayReady = (bool) ($userOnlinePaymentReady ?? false))
@php($onlinePayAdminEnabled = (bool) ($customerOnlinePaymentEnabled ?? true))
@php($upPayUrl = $userInstallmentOnlinePayUrl ?? route('user.installments.online-pay.start'))
@php($portalInstPayReturnRoute = request()->routeIs('user.dashboard') ? 'user.dashboard' : 'user.loans.index')

<dialog id="portal-installment-pay-dialog" class="portal-dialog portal-dialog--pay-inst" aria-labelledby="portal-installment-pay-title">
    <div class="portal-dialog__inner">
        <button type="button" class="portal-dialog__close" data-portal-installment-pay-close aria-label="بستن">&times;</button>
        <h3 id="portal-installment-pay-title" class="portal-dialog__title">
            <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
            پرداخت آنلاین قسط
        </h3>
        <p class="portal-dialog__hint" style="margin-top:0.35rem;text-align:center;line-height:1.65">
            مشخصات قسط را بررسی کنید؛ سپس برای ورود به درگاه بانکی اقدام کنید.
        </p>
        <div class="portal-dialog__inst-dl" id="portal-installment-pay-kv" aria-live="polite"></div>

        <div class="portal-pay-path-card portal-pay-path-card--gateway">
            <p class="portal-dialog__vpn-hint" id="portal-installment-pay-vpn">
                پیش از ورود به درگاه، از خاموش بودن VPN خود اطمینان حاصل نمایید.
            </p>
            <form class="portal-dialog__actions" method="post" action="{{ $upPayUrl }}" id="portal-installment-pay-form">
                @csrf
                <input type="hidden" name="return_route" value="{{ $portalInstPayReturnRoute }}">
                <input type="hidden" name="customer_loan_installment_id" id="portal-installment-pay-id" value="" required>
                <span class="portal-online-pay-stack" style="width:100%">
                    <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block @unless($upPayReady) portal-loan__btn--disabled @endunless" id="portal-installment-pay-submit" @if(!$upPayReady) disabled title="{{ $onlinePayAdminEnabled ? 'درگاه پرداخت در تنظیمات مدیریت تکمیل نشده است.' : 'پرداخت آنلاین توسط مدیریت غیرفعال شده است.' }}" @endif>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        ورود به درگاه و پرداخت قسط
                    </button>
                    @unless($onlinePayAdminEnabled)
                        <span class="portal-online-pay-off-label">غیرفعال</span>
                    @endunless
                </span>
                @if($onlinePayAdminEnabled && !$upPayReady)
                    <p class="portal-dialog__hint" style="margin-top:0.5rem;text-align:center">درگاه پرداخت توسط مدیریت فعال نشده است.</p>
                @endif
            </form>
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
            if (!dialog || !form || !inputId || !kv) return;

            function closeDialog() {
                if (dialog.open) dialog.close();
            }

            window.portalCloseOpenPortalDialogs = function () {
                document.querySelectorAll('dialog[open]').forEach(function (d) {
                    try { d.close(); } catch (e) { /* noop */ }
                });
            };

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
                    sequenceFa: ds.sequenceFa || '',
                    onlinePayableFa: ds.onlinePayableFa || '',
                    slotRemainingFa: ds.slotRemainingFa || ''
                };
            }

            function portalPayOnlineBlockedMessage() {
                return 'ابتدا قسط‌های قبلی را پرداخت نمایید.';
            }

            function showPortalAlert(icon, title, text) {
                if (typeof window.portalCloseOpenPortalDialogs === 'function') {
                    window.portalCloseOpenPortalDialogs();
                }
                if (typeof window.AdminSwal !== 'undefined' && window.AdminSwal.fire) {
                    window.AdminSwal.fire({ icon: icon, title: title, text: text });
                } else {
                    window.alert(text);
                }
            }

            window.openPortalInstallmentPayModal = function (sourceEl) {
                if (!sourceEl) return;
                var root = sourceEl.closest ? (sourceEl.closest('[data-inst-root]') || sourceEl) : sourceEl;
                var d = readDs(root);
                if (!d.installmentId) return;

                inputId.value = String(d.installmentId);

                var parts = [];
                parts.push(row('عنوان وام', d.loanTitle || '—'));
                parts.push(row('شماره قسط', d.sequenceFa || '—'));
                parts.push(row('مبلغ قابل پرداخت', d.onlinePayableFa || d.slotRemainingFa || '—'));
                kv.innerHTML = parts.join('');

                if (typeof dialog.showModal === 'function') dialog.showModal();
            };

            document.querySelectorAll('[data-portal-installment-pay-close]').forEach(function (b) {
                b.addEventListener('click', closeDialog);
            });
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) closeDialog();
            });

            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;

                var payBlocked = t.closest('[data-portal-pay-online-blocked]');
                if (payBlocked) {
                    e.preventDefault();
                    e.stopPropagation();
                    showPortalAlert('info', 'پرداخت قسط', portalPayOnlineBlockedMessage());
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
