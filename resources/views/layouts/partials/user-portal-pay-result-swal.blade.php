@php
    $rawPortalPay = session('portal_pay_result');
    $portalPaySwal = null;
    if (is_array($rawPortalPay) && array_key_exists('success', $rawPortalPay) && array_key_exists('message', $rawPortalPay)) {
        $tid = $rawPortalPay['track_id'] ?? null;
        $bid = $rawPortalPay['bank_ref'] ?? null;
        $amtRaw = $rawPortalPay['amount_toman'] ?? null;
        $amt = is_numeric($amtRaw) ? (int) $amtRaw : 0;
        $portalPaySwal = [
            'success' => (bool) $rawPortalPay['success'],
            'message' => (string) $rawPortalPay['message'],
            'track_id' => is_string($tid) && trim($tid) !== '' ? trim($tid) : null,
            'bank_ref' => is_string($bid) && trim($bid) !== '' ? trim($bid) : null,
            'amount_toman' => $amt > 0 ? $amt : null,
        ];
    }
@endphp
@if($portalPaySwal !== null)
    <style>
        .swal-pay-portal-wrap .swal-pay-portal-popup {
            border-radius: 1.2rem;
            padding: 0;
            padding-bottom: 1.05rem;
            overflow: hidden;
            max-width: 22.5rem;
            width: calc(100% - 1.5rem) !important;
            border: 1px solid rgba(148, 163, 184, 0.45);
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
            background: linear-gradient(165deg, #ffffff 0%, #f8fafc 48%, #f1f5f9 100%);
        }

        html[data-theme="dark"] .swal-pay-portal-wrap .swal-pay-portal-popup {
            background: linear-gradient(165deg, #1e293b 0%, #0f172a 100%);
            border-color: rgba(71, 85, 105, 0.65);
            box-shadow: 0 22px 55px rgba(0, 0, 0, 0.45);
        }

        .swal-pay-portal-wrap .swal-pay-portal-popup .swal2-title {
            margin: 0;
            padding: 1rem 1.1rem 0.65rem;
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1.45;
        }

        .swal-pay-portal-wrap.swal-pay-portal--ok .swal-pay-portal-popup .swal2-title {
            color: #0f766e;
            background: linear-gradient(90deg, rgba(20, 184, 166, 0.14), rgba(16, 185, 129, 0.08));
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal-pay-portal-popup .swal2-title {
            color: #b91c1c;
            background: linear-gradient(90deg, rgba(248, 113, 113, 0.18), rgba(239, 68, 68, 0.08));
        }

        html[data-theme="dark"] .swal-pay-portal-wrap.swal-pay-portal--ok .swal-pay-portal-popup .swal2-title {
            color: #5eead4;
        }

        html[data-theme="dark"] .swal-pay-portal-wrap.swal-pay-portal--fail .swal-pay-portal-popup .swal2-title {
            color: #fca5a5;
        }

        .swal-pay-portal-wrap .swal-pay-portal-popup .swal2-html-container {
            margin: 0;
            padding: 0 1rem 0.35rem;
        }

        .swal-pay-portal-body {
            text-align: right;
        }

        .swal-pay-portal-msg {
            margin: 0 0 0.75rem;
            font-size: 0.88rem;
            line-height: 1.65;
            color: #334155;
            font-weight: 600;
        }

        html[data-theme="dark"] .swal-pay-portal-msg {
            color: #e2e8f0;
        }

        .swal-pay-portal-kv {
            border-radius: 0.65rem;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(255, 255, 255, 0.65);
        }

        html[data-theme="dark"] .swal-pay-portal-kv {
            background: rgba(30, 41, 59, 0.55);
            border-color: rgba(71, 85, 105, 0.55);
        }

        .swal-pay-portal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.65rem;
            font-size: 0.8rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.4);
        }

        .swal-pay-portal-row:last-child {
            border-bottom: none;
        }

        .swal-pay-portal-k {
            color: #64748b;
            font-weight: 700;
            flex-shrink: 0;
        }

        html[data-theme="dark"] .swal-pay-portal-k {
            color: #94a3b8;
        }

        .swal-pay-portal-v {
            font-weight: 800;
            color: #0f172a;
            text-align: left;
            word-break: break-all;
            font-variant-numeric: tabular-nums;
        }

        html[data-theme="dark"] .swal-pay-portal-v {
            color: #f8fafc;
        }

        .swal-pay-portal-timer {
            margin: 0.75rem 0 0;
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
            text-align: center;
        }

        html[data-theme="dark"] .swal-pay-portal-timer {
            color: #94a3b8;
        }

        .swal-pay-portal-timer strong {
            font-size: 0.95rem;
            color: #0d9488;
            font-weight: 900;
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal-pay-portal-timer strong {
            color: #dc2626;
        }

        .swal-pay-portal-wrap .swal-pay-portal-popup .swal2-actions {
            margin: 0.65rem 1rem 0.55rem !important;
            padding: 0 !important;
        }

        .swal-pay-portal-wrap .swal-pay-portal-btn {
            border-radius: 0.65rem !important;
            font-weight: 800 !important;
            padding: 0.55rem 1.35rem !important;
            font-size: 0.88rem !important;
            margin-top: 0.35rem !important;
            box-shadow: 0 4px 14px rgba(13, 148, 136, 0.28) !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--ok .swal-pay-portal-btn {
            background: linear-gradient(135deg, #0d9488, #059669) !important;
            border: none !important;
            color: #fff !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal-pay-portal-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            border: none !important;
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3) !important;
        }

        .swal-pay-portal-wrap .swal2-timer-progress-bar {
            background: linear-gradient(90deg, #0d9488, #10b981) !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal2-timer-progress-bar {
            background: linear-gradient(90deg, #ef4444, #f97316) !important;
        }

        /* آیکون سفارشی: دیسک گرادیانی + Font Awesome (بدون خطوط پیش‌فرض Swal) */
        @keyframes swal-pay-icon-in {
            0% {
                opacity: 0;
                transform: scale(0.5) rotate(-12deg);
            }
            70% {
                opacity: 1;
                transform: scale(1.06) rotate(3deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes swal-pay-icon-glow {
            0%,
            100% {
                box-shadow:
                    0 12px 32px rgba(13, 148, 136, 0.38),
                    0 0 0 1px rgba(255, 255, 255, 0.28) inset,
                    0 0 0 0 rgba(20, 184, 166, 0.45);
            }
            50% {
                box-shadow:
                    0 14px 36px rgba(13, 148, 136, 0.48),
                    0 0 0 1px rgba(255, 255, 255, 0.32) inset,
                    0 0 0 10px rgba(20, 184, 166, 0);
            }
        }

        @keyframes swal-pay-icon-glow-fail {
            0%,
            100% {
                box-shadow:
                    0 12px 32px rgba(220, 38, 38, 0.35),
                    0 0 0 1px rgba(255, 255, 255, 0.22) inset,
                    0 0 0 0 rgba(248, 113, 113, 0.4);
            }
            50% {
                box-shadow:
                    0 14px 36px rgba(220, 38, 38, 0.45),
                    0 0 0 1px rgba(255, 255, 255, 0.26) inset,
                    0 0 0 10px rgba(248, 113, 113, 0);
            }
        }

        .swal-pay-portal-wrap .swal2-icon {
            position: relative;
            margin: 1rem auto 0.35rem !important;
            border: none !important;
            width: 4.35rem !important;
            height: 4.35rem !important;
            border-radius: 50% !important;
            animation: swal-pay-icon-in 0.55s cubic-bezier(0.34, 1.45, 0.64, 1) both !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--ok .swal2-icon.swal2-success {
            background: linear-gradient(155deg, #2dd4bf 0%, #14b8a6 38%, #0d9488 72%, #047857 100%) !important;
            animation:
                swal-pay-icon-in 0.55s cubic-bezier(0.34, 1.45, 0.64, 1) both,
                swal-pay-icon-glow 2.4s ease-in-out infinite 0.6s !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal2-icon.swal2-error {
            background: linear-gradient(155deg, #fca5a5 0%, #f87171 35%, #ef4444 70%, #b91c1c 100%) !important;
            animation:
                swal-pay-icon-in 0.55s cubic-bezier(0.34, 1.45, 0.64, 1) both,
                swal-pay-icon-glow-fail 2.4s ease-in-out infinite 0.6s !important;
        }

        .swal-pay-portal-wrap.swal-pay-portal--ok .swal2-icon.swal2-success > :not(.swal2-icon-content),
        .swal-pay-portal-wrap.swal-pay-portal--fail .swal2-icon.swal2-error > :not(.swal2-icon-content) {
            display: none !important;
        }

        .swal-pay-portal-wrap .swal2-icon .swal2-icon-content {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
        }

        .swal-pay-portal-wrap .swal-pay-portal-fa {
            font-size: 1.9rem;
            line-height: 1;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.18));
        }

        .swal-pay-portal-wrap.swal-pay-portal--fail .swal-pay-portal-fa {
            font-size: 1.75rem;
            font-weight: 900;
        }

        html[data-theme="dark"] .swal-pay-portal-wrap.swal-pay-portal--ok .swal2-icon.swal2-success {
            background: linear-gradient(155deg, #5eead4 0%, #14b8a6 45%, #0f766e 100%) !important;
        }

        html[data-theme="dark"] .swal-pay-portal-wrap.swal-pay-portal--fail .swal2-icon.swal2-error {
            background: linear-gradient(155deg, #fecaca 0%, #f87171 40%, #dc2626 100%) !important;
        }
    </style>
    <script>
        (function () {
            var payload = @json($portalPaySwal);
            function faDigits(s) {
                return String(s).replace(/\d/g, function (d) {
                    return '۰۱۲۳۴۵۶۷۸۹'[parseInt(d, 10)];
                });
            }
            function addThousandsCommas(digits) {
                if (!digits) return '';
                var rev = String(digits).split('').reverse().join('');
                var parts = [];
                for (var i = 0; i < rev.length; i += 3) {
                    parts.push(rev.substr(i, 3).split('').reverse().join(''));
                }
                return parts.reverse().join(',');
            }
            function formatTomanDisplay(toman) {
                var n = parseInt(toman, 10);
                if (!Number.isFinite(n) || n < 1) return '—';
                return faDigits(addThousandsCommas(String(n))) + ' تومان';
            }
            function esc(s) {
                if (s == null) return '';
                var d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML;
            }
            function run() {
                if (typeof Swal === 'undefined') return;
                var ok = !!payload.success;
                var track = payload.track_id != null && String(payload.track_id).trim() !== ''
                    ? faDigits(String(payload.track_id).trim())
                    : '—';
                var bank = payload.bank_ref != null && String(payload.bank_ref).trim() !== ''
                    ? faDigits(String(payload.bank_ref).trim())
                    : '—';
                var amountLine = '';
                if (payload.amount_toman != null && parseInt(payload.amount_toman, 10) > 0) {
                    amountLine =
                        '<div class="swal-pay-portal-row"><span class="swal-pay-portal-k">مبلغ پرداخت</span>' +
                        '<span class="swal-pay-portal-v" dir="rtl">' + esc(formatTomanDisplay(payload.amount_toman)) + '</span></div>';
                }
                var msg = String(payload.message || '');
                var title = ok ? 'پرداخت موفق' : 'پرداخت ناموفق';
                var wrapClass = ok ? 'swal-pay-portal-wrap swal-pay-portal--ok' : 'swal-pay-portal-wrap swal-pay-portal--fail';
                var html =
                    '<div class="swal-pay-portal-body">' +
                    '<p class="swal-pay-portal-msg">' + esc(msg) + '</p>' +
                    '<div class="swal-pay-portal-kv" role="group" aria-label="جزئیات تراکنش">' +
                    '<div class="swal-pay-portal-row"><span class="swal-pay-portal-k">شماره پیگیری</span>' +
                    '<span class="swal-pay-portal-v" dir="ltr">' + track + '</span></div>' +
                    '<div class="swal-pay-portal-row"><span class="swal-pay-portal-k">شماره تراکنش بانکی</span>' +
                    '<span class="swal-pay-portal-v" dir="ltr">' + bank + '</span></div>' +
                    amountLine +
                    '</div>' +
                    '<p class="swal-pay-portal-timer">بسته شدن خودکار تا <strong data-portal-pay-swal-timer="">۱۵</strong> ثانیه دیگر</p>' +
                    '</div>';
                var timerIv = null;
                Swal.fire({
                    icon: ok ? 'success' : 'error',
                    iconHtml: ok
                        ? '<i class="fa-solid fa-check swal-pay-portal-fa" aria-hidden="true"></i>'
                        : '<i class="fa-solid fa-xmark swal-pay-portal-fa" aria-hidden="true"></i>',
                    title: title,
                    html: html,
                    confirmButtonText: 'متوجه شدم',
                    buttonsStyling: false,
                    customClass: {
                        container: wrapClass,
                        popup: 'swal-pay-portal-popup',
                        confirmButton: 'swal-pay-portal-btn',
                    },
                    reverseButtons: true,
                    timer: 15000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                    focusConfirm: true,
                    didOpen: function () {
                        var p = document.querySelector('.swal2-popup');
                        if (p) p.setAttribute('dir', 'rtl');
                        var el = document.querySelector('[data-portal-pay-swal-timer]');
                        function tick() {
                            if (!Swal.isVisible()) {
                                if (timerIv) clearInterval(timerIv);
                                return;
                            }
                            var left = typeof Swal.getTimerLeft === 'function' ? Swal.getTimerLeft() : 0;
                            var sec = Math.max(0, Math.ceil(left / 1000));
                            if (el) el.textContent = faDigits(String(sec));
                        }
                        tick();
                        timerIv = setInterval(tick, 250);
                    },
                    willClose: function () {
                        if (timerIv) clearInterval(timerIv);
                    },
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        })();
    </script>
@endif
