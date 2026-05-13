    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        window.__CTX_TX_SNAPSHOTS__ = @json($rowSnapshots ?? []);
        window.__CTX_EMBED__ = @json((bool) ($ctxEmbedCustomer ?? false));
    </script>
    <script>
        (function () {
            var snapshots = window.__CTX_TX_SNAPSHOTS__ || {};
            var dialog = document.getElementById('ctx-dialog');
            var fieldsEl = document.getElementById('ctx-detail-fields');
            var metaBlock = document.getElementById('ctx-meta-block');
            var metaPre = document.getElementById('ctx-meta-pre');
            var custLink = document.getElementById('ctx-customer-link');
            var subEl = document.getElementById('ctx-dialog-sub');

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function card(iconClass, labelText, valueInnerHtml, wide, extraCardClass) {
                var w = wide ? ' ctx-detail-card--wide' : '';
                var ec = extraCardClass ? ' ' + extraCardClass : '';
                return (
                    '<article class="ctx-detail-card' + w + ec + '">' +
                        '<div class="ctx-detail-card__head">' +
                            '<span class="ctx-detail-card__ico" aria-hidden="true"><i class="fa-solid ' + iconClass + '"></i></span>' +
                            '<span class="ctx-detail-card__label">' + esc(labelText) + '</span>' +
                        '</div>' +
                        '<div class="ctx-detail-card__value">' + valueInnerHtml + '</div>' +
                    '</article>'
                );
            }

            function itemText(icon, label, value, wide, textExtraClass) {
                var v = value == null || String(value).trim() === '' ? '—' : String(value);
                var cls = 'ctx-detail-card__text' + (textExtraClass ? ' ' + textExtraClass : '');
                return card(icon, label, '<span class="' + cls + '">' + esc(v) + '</span>', wide, '');
            }

            function badgeTone(st) {
                if (st === 'completed') return 'ok';
                if (st === 'failed') return 'danger';
                if (st === 'redirected') return 'pending';
                return 'muted';
            }

            function cardTimes(createdFa, updatedFa) {
                var c1 = createdFa == null || String(createdFa).trim() === '' ? '—' : String(createdFa);
                var c2 = updatedFa == null || String(updatedFa).trim() === '' ? '—' : String(updatedFa);
                var inner =
                    '<div class="ctx-detail-times-inner">' +
                    '<div><span class="ctx-detail-times__k">زمان ایجاد</span><span class="ctx-detail-times__v">' + esc(c1) + '</span></div>' +
                    '<div><span class="ctx-detail-times__k">آخرین به‌روزرسانی</span><span class="ctx-detail-times__v">' + esc(c2) + '</span></div>' +
                    '</div>';
                return card('fa-clock', 'زمان در سیستم', inner, true, 'ctx-detail-card--times');
            }

            function fitDetailCards() {
                if (!fieldsEl) return;
                fieldsEl.querySelectorAll('.ctx-detail-card').forEach(function (card) {
                    card.classList.remove('ctx-detail-card--sm', 'ctx-detail-card--xs');
                    var val = card.querySelector('.ctx-detail-card__value');
                    if (!val) return;
                    if (val.scrollHeight <= val.clientHeight) return;
                    card.classList.add('ctx-detail-card--sm');
                    void val.offsetHeight;
                    if (val.scrollHeight > val.clientHeight) {
                        card.classList.add('ctx-detail-card--xs');
                    }
                });
            }

            var fitDetailScheduled = false;
            function scheduleFitDetailCards() {
                if (fitDetailScheduled) return;
                fitDetailScheduled = true;
                requestAnimationFrame(function () {
                    fitDetailScheduled = false;
                    fitDetailCards();
                });
            }

            function openDetail(id) {
                var s = snapshots[String(id)] || snapshots[id];
                if (!s || !fieldsEl || !dialog) return;
                var tone = badgeTone(s.status);
                if (subEl) {
                    subEl.textContent = s.created_at_fa ? ('زمان ثبت: ' + String(s.created_at_fa)) : '';
                }

                var html = '';
                html += itemText('fa-user', 'مشتری', s.customer_line, true, '');
                html += card('fa-heading', 'عنوان', '<span class="ctx-detail-card__text">' + esc(s.title) + '</span>', true, '');
                if (s.detail) {
                    html += card(
                        'fa-align-right',
                        'شرح',
                        '<span class="ctx-detail-card__text ctx-detail-card__text--pre">' + esc(s.detail) + '</span>',
                        true,
                        ''
                    );
                }
                html += itemText('fa-hashtag', 'شناسه تراکنش', String(s.id), false, 'ctx-detail-card__text--ltr');
                html += card(
                    'fa-signal',
                    'وضعیت',
                    '<span class="ctx-badge ctx-badge--' + tone + '">' + esc(s.status_label_fa) + '</span>',
                    false,
                    ''
                );
                html += itemText('fa-tags', 'نوع تراکنش', s.kind_label_fa, false, '');
                html += itemText('fa-coins', 'مبلغ (تومان)', s.amount_fa, false, '');
                html += itemText('fa-money-bill-wave', 'مبلغ (ریال)', s.amount_rial_fa, false, '');
                html += itemText('fa-building-columns', 'درگاه', s.gateway_label_fa, false, '');
                html += itemText('fa-barcode', 'شماره پیگیری', s.track_id_fa, false, 'ctx-detail-card__text--ltr');
                html += itemText('fa-receipt', 'مرجع بانک', s.bank_reference_fa, false, 'ctx-detail-card__text--ltr');
                html += itemText('fa-link', 'منبع سیستمی', s.source_short, false, 'ctx-detail-card__text--ltr');
                if (s.failure_reason) {
                    html += card(
                        'fa-triangle-exclamation',
                        'پیام خطا / توضیح',
                        '<span class="ctx-detail-card__text ctx-detail-card__text--pre">' + esc(s.failure_reason) + '</span>',
                        true,
                        'ctx-detail-card--alert'
                    );
                }
                html += cardTimes(s.created_at_fa, s.updated_at_fa);
                fieldsEl.innerHTML = html;

                if (s.meta_json && metaBlock && metaPre) {
                    metaBlock.style.display = '';
                    metaBlock.removeAttribute('hidden');
                    metaPre.textContent = s.meta_json;
                } else if (metaBlock) {
                    metaBlock.style.display = 'none';
                    metaBlock.setAttribute('hidden', 'hidden');
                    metaPre.textContent = '';
                }

                if (custLink && s.customer_profile_url) {
                    custLink.href = s.customer_profile_url;
                    custLink.style.display = 'inline-flex';
                    custLink.target = (window.__CTX_EMBED__ === true || window.__CTX_EMBED__ === 1) ? '_top' : '_blank';
                } else if (custLink) {
                    custLink.style.display = 'none';
                }

                if (typeof dialog.showModal === 'function') dialog.showModal();
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        fitDetailCards();
                    });
                });
            }

            function closeDlg() {
                if (dialog && dialog.open) dialog.close();
                if (subEl) subEl.textContent = '';
            }

            document.querySelectorAll('.ctx-open-detail').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openDetail(btn.getAttribute('data-id'));
                });
            });
            document.querySelectorAll('[data-ctx-close]').forEach(function (b) {
                b.addEventListener('click', closeDlg);
            });
            if (dialog) {
                dialog.addEventListener('click', function (e) {
                    if (e.target === dialog) closeDlg();
                });
            }
            window.addEventListener('resize', function () {
                if (dialog && dialog.open) scheduleFitDetailCards();
            });
        })();
    </script>
    <script>
        (function () {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                return;
            }
            window.jQuery(function () {
                var common = {
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                };
                window.jQuery('#ctx-date-from, #ctx-date-to').each(function () {
                    var $el = window.jQuery(this);
                    var hasVal = String($el.val() || '').trim() !== '';
                    try {
                        if ($el.data('datepicker')) {
                            $el.pDatepicker('destroy');
                        }
                    } catch (e) { /* noop */ }
                    $el.pDatepicker(window.jQuery.extend({}, common, {
                        initialValue: hasVal
                    }));
                });
            });
        })();
    </script>
