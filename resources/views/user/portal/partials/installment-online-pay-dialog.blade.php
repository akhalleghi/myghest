@php($upPayReady = (bool) ($userOnlinePaymentReady ?? false))
@php($upPayUrl = $userInstallmentOnlinePayUrl ?? route('user.installments.online-pay.start'))

<dialog id="portal-installment-pay-dialog" class="portal-dialog portal-dialog--pay-inst" aria-labelledby="portal-installment-pay-title">
    <div class="portal-dialog__inner">
        <button type="button" class="portal-dialog__close" data-portal-installment-pay-close aria-label="بستن">&times;</button>
        <h3 id="portal-installment-pay-title" class="portal-dialog__title">
            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
            پرداخت آنلاین قسط
        </h3>
        <div class="portal-dialog__inst-dl" id="portal-installment-pay-kv" aria-live="polite"></div>
        <p class="portal-dialog__vpn-hint" id="portal-installment-pay-vpn">
            پیش از ورود به درگاه، از خاموش بودن VPN خود اطمینان حاصل نمایید.
        </p>
        <form class="portal-dialog__actions" method="post" action="{{ $upPayUrl }}" id="portal-installment-pay-form">
            @csrf
            <input type="hidden" name="customer_loan_installment_id" id="portal-installment-pay-id" value="" required>
            <button type="submit" class="portal-loan__btn portal-loan__btn--primary portal-loan__btn--block" id="portal-installment-pay-submit" @if(!$upPayReady) disabled title="درگاه پرداخت در تنظیمات مدیریت تکمیل نشده است." @endif>
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                ورود به درگاه پرداخت
            </button>
            @unless($upPayReady)
                <p class="portal-dialog__hint" style="margin-top:0.5rem">درگاه پرداخت توسط مدیریت فعال نشده است؛ پس از تکمیل تنظیمات مالی ادمین دوباره تلاش کنید.</p>
            @endunless
        </form>
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
                    statusLine: ds.statusLine || ''
                };
            }

            window.openPortalInstallmentPayModal = function (sourceEl) {
                if (!sourceEl) return;
                var root = sourceEl.closest ? (sourceEl.closest('[data-inst-root]') || sourceEl) : sourceEl;
                var d = readDs(root);
                if (!d.installmentId) return;

                inputId.value = String(d.installmentId);
                var parts = [];
                parts.push(row('عنوان وام', d.loanTitle || '—'));
                parts.push(row('کد پرونده', d.loanCodeFa || '—'));
                parts.push(row('قسط', d.sequenceFa ? ('قسط ' + d.sequenceFa) : '—'));
                parts.push(row('مبلغ قابل پرداخت در درگاه', d.onlinePayableFa || d.slotRemainingFa || '—'));
                parts.push(row('مبلغ نامی قسط', d.amountFa || '—'));
                parts.push(row('ماندهٔ نامی این قسط', d.slotRemainingFa || '—'));
                parts.push(row('سررسید', d.dueJalali || '—'));
                parts.push(row('پرداختی ثبت‌شده', d.paidFa || '—'));
                parts.push(row('وضعیت', d.statusLine || '—'));
                kv.innerHTML = parts.join('');

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
                        window.AdminSwal.fire({ icon: 'info', title: 'پرداخت آنلاین', text: msg });
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
