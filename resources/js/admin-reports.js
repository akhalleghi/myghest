/**
 * صفحه گزارش‌های ادمین — بدون وابستگی CDN.
 */

function rptParseConfig() {
    var el = document.getElementById('rpt-page-config');
    if (!el) {
        return {};
    }
    try {
        return JSON.parse(el.textContent || '{}');
    } catch {
        return {};
    }
}

function rptFaNum(n) {
    return String(n ?? '').replace(/\d/g, function (d) {
        return '۰۱۲۳۴۵۶۷۸۹'[Number(d)];
    });
}

function rptFormatToman(n) {
    return rptFaNum(Number(n || 0).toLocaleString('en-US')) + ' تومان';
}

function rptFormatAmount(n) {
    return rptFaNum(Number(n || 0).toLocaleString('en-US'));
}

function rptEscapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function rptEscapeAttr(s) {
    return rptEscapeHtml(s).replace(/'/g, '&#39;');
}

function rptInitDatePickers(fromId, toId) {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
        return;
    }
    var opts = {
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: false,
        calendarType: 'persian',
        initialValueType: 'persian',
        toolbox: { calendarSwitch: false },
    };
    ['#' + fromId, '#' + toId].forEach(function (sel) {
        var $el = window.jQuery(sel);
        if (!$el.length) {
            return;
        }
        try {
            if ($el.data('datepicker')) {
                $el.pDatepicker('destroy');
            }
        } catch {
            /* noop */
        }
        $el.pDatepicker(opts);
    });
}

function rptInitMemberLoansReport(cfg) {
    var overlay = document.getElementById('rpt-modal-member-loans');
    var form = document.getElementById('rpt-member-loans-date-form');
    var tbody = document.getElementById('rpt-ml-tbody');
    var meta = document.getElementById('rpt-ml-meta');
    var searchInput = document.getElementById('rpt-ml-search');
    var settledSelect = document.getElementById('rpt-ml-settled');
    var fromInput = document.getElementById('rpt-ml-from');
    var toInput = document.getElementById('rpt-ml-to');
    var exportExcelLink = document.getElementById('rpt-ml-export-excel');

    if (!overlay || !form || !tbody) {
        return;
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.memberLoansExportUrl) {
            return;
        }
        var url = new URL(cfg.memberLoansExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (settledSelect && String(settledSelect.value || '')) {
            url.searchParams.set('settled', String(settledSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverSettled = '';

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-ml-from', 'rpt-ml-to');
        rptUpdateExportUrl();
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptStackLinkCell(tr, cellClass, href, title, lines, dataLabel) {
        var td = rptTd(tr, 'rpt-td--stack ' + cellClass, dataLabel);
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack';
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href;
        link.textContent = title;
        stack.appendChild(link);
        lines.forEach(function (line) {
            if (!line || !line.text) {
                return;
            }
            var span = document.createElement('span');
            if (line.ltr) {
                span.className = 'rpt-val-ltr';
            }
            span.textContent = line.text;
            stack.appendChild(span);
        });
        td.appendChild(stack);
    }

    function rptStackAmountCell(tr, principal, total) {
        var td = rptTd(tr, 'rpt-td--amount rpt-td--num', 'مبلغ وام');
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack rpt-cell-stack--amount';
        var p = document.createElement('span');
        p.className = 'rpt-val-ltr rpt-amt-principal';
        p.textContent = rptFormatAmount(principal);
        var t = document.createElement('span');
        t.className = 'rpt-val-ltr rpt-amt-total';
        t.textContent = rptFormatAmount(total);
        stack.appendChild(p);
        stack.appendChild(t);
        td.appendChild(stack);
    }

    function rptNumCell(tr, value, extraClass, dataLabel) {
        var td = rptTd(tr, 'rpt-td--num ' + (extraClass || ''), dataLabel);
        var span = document.createElement('span');
        span.className = 'rpt-val-ltr rpt-num';
        if (Number(value) < 0) {
            span.classList.add('rpt-num--neg');
        }
        span.textContent = rptFormatAmount(value);
        td.appendChild(span);

        return td;
    }

    function rptBuildSmsCell(tr, row) {
        var td = rptTd(tr, 'rpt-td--sms', 'پیامک');
        var wrap = document.createElement('div');
        wrap.className = 'rpt-sms-actions';
        [
            { type: 'installment_pre_due', cls: 'rpt-sms-btn--pre', label: 'پ', title: 'پیش از موعد' },
            { type: 'installment_due', cls: 'rpt-sms-btn--due', label: 'س', title: 'سررسید' },
            { type: 'installment_overdue', cls: 'rpt-sms-btn--over', label: 'م', title: 'معوق' },
            { type: 'installment_thanks', cls: 'rpt-sms-btn--thanks', label: 'ت', title: 'تشکر' },
        ].forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rpt-sms-btn ' + item.cls;
            btn.setAttribute('data-rpt-sms', '');
            btn.setAttribute('data-sms-type', item.type);
            btn.setAttribute('data-customer-id', String(row.customer_id));
            btn.setAttribute('data-installment-id', String(row.sms_installment_id || ''));
            btn.setAttribute('data-loan-file-id', String(row.loan_file_id));
            btn.setAttribute('data-customer-name', row.customer_name || '');
            btn.setAttribute('data-customer-mobile', row.customer_mobile || '');
            btn.title = item.title;
            btn.textContent = item.label;
            if (!row.sms_installment_id) {
                btn.disabled = true;
            }
            wrap.appendChild(btn);
        });
        td.appendChild(wrap);
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 12;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';
            var loanLines = [{ text: row.loan_type_title || '' }];
            if (row.loan_description) {
                loanLines.push({ text: row.loan_description });
            }

            rptStackLinkCell(
                tr,
                'rpt-td--loan',
                row.loan_manage_url,
                'پرونده: ' + rptFaNum(row.loan_code || '—'),
                loanLines,
                'عنوان وام'
            );

            rptStackLinkCell(
                tr,
                'rpt-td--customer',
                row.customer_manage_url,
                row.customer_name || '—',
                [
                    { text: rptFaNum(row.customer_mobile || ''), ltr: true },
                    {
                        text:
                            row.customer_national_id && row.customer_national_id !== '—'
                                ? 'کد ملی: ' + rptFaNum(row.customer_national_id)
                                : '',
                    },
                ],
                'مشتری'
            );

            rptStackAmountCell(tr, row.principal_toman, row.total_repayable_toman);

            var tdCount = rptTd(tr, 'rpt-td--num', 'اقساط');
            tdCount.innerHTML = '<span class="rpt-num">' + rptFaNum(row.installments_count) + '</span>';

            rptNumCell(tr, row.installment_amount_toman, '', 'مبلغ قسط');

            var tdStart = rptTd(tr, 'rpt-td--num', 'شروع');
            tdStart.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.loan_start_jdate || '—') + '</span>';

            var tdSettled = rptTd(tr, 'rpt-td--num', 'تسویه');
            tdSettled.textContent = row.is_settled_label || '—';

            rptNumCell(tr, row.paid_amount_toman, '', 'پرداختی');
            rptNumCell(tr, row.remaining_amount_toman, '', 'مانده');

            var tdDelay = rptTd(tr, 'rpt-td--num', 'تأخیر');
            if (Number(row.overdue_installments_count) > 0) {
                var delaySpan = document.createElement('span');
                delaySpan.style.fontSize = '0.7rem';
                delaySpan.style.whiteSpace = 'normal';
                delaySpan.textContent =
                    rptFaNum(row.overdue_installments_count) +
                    ' قسط معوق' +
                    (Number(row.late_fee_toman) > 0 ? '\n' + rptFormatAmount(row.late_fee_toman) : '');
                tdDelay.appendChild(delaySpan);
            } else {
                tdDelay.textContent = '';
                tdDelay.classList.add('rpt-td--empty');
            }

            var tdDiscount = rptTd(tr, 'rpt-td--num', 'تخفیف');
            if (Number(row.discount_toman) > 0) {
                tdDiscount.innerHTML =
                    '<span class="rpt-val-ltr rpt-num">' + rptFormatAmount(row.discount_toman) + '</span>';
            } else {
                tdDiscount.classList.add('rpt-td--empty');
            }

            rptBuildSmsCell(tr, row);
            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        var settled = settledSelect ? String(settledSelect.value || '') : '';

        var filtered = allRows.filter(function (row) {
            if (settled === 'yes' && !row.is_settled) {
                return false;
            }
            if (settled === 'no' && row.is_settled) {
                return false;
            }
            if (!q) {
                return true;
            }
            var hay = [
                row.loan_title,
                row.loan_code,
                row.loan_type_title,
                row.customer_name,
                row.customer_national_id,
                row.customer_mobile,
            ]
                .join(' ')
                .toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' پرونده';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverSettled = settledSelect ? String(settledSelect.value || '') : '';

        tbody.innerHTML = '<tr><td colspan="12" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }

        var url = new URL(cfg.memberLoansDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverSettled) {
            url.searchParams.set('settled', serverSettled);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' پرونده';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (settledSelect) {
                    settledSelect.value = serverSettled;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="12" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (settledSelect) {
        settledSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="member-loans-by-date"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitInstallmentDueReport(cfg) {
    var overlay = document.getElementById('rpt-modal-installment-due');
    var form = document.getElementById('rpt-installment-due-date-form');
    var tbody = document.getElementById('rpt-id-tbody');
    var meta = document.getElementById('rpt-id-meta');
    var searchInput = document.getElementById('rpt-id-search');
    var paymentStatusSelect = document.getElementById('rpt-id-payment-status');
    var overdueSelect = document.getElementById('rpt-id-overdue');
    var fromInput = document.getElementById('rpt-id-from');
    var toInput = document.getElementById('rpt-id-to');
    var exportExcelLink = document.getElementById('rpt-id-export-excel');

    if (!overlay || !form || !tbody) {
        return;
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptStackLinkCell(tr, cellClass, href, title, lines, dataLabel) {
        var td = rptTd(tr, 'rpt-td--stack ' + cellClass, dataLabel);
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack';
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href;
        link.textContent = title;
        stack.appendChild(link);
        lines.forEach(function (line) {
            if (!line || !line.text) {
                return;
            }
            var span = document.createElement('span');
            if (line.ltr) {
                span.className = 'rpt-val-ltr';
            }
            span.textContent = line.text;
            stack.appendChild(span);
        });
        td.appendChild(stack);
    }

    function rptBuildSmsCell(tr, row) {
        var td = rptTd(tr, 'rpt-td--sms', 'پیامک‌ها');
        var wrap = document.createElement('div');
        wrap.className = 'rpt-sms-actions';
        [
            { type: 'installment_pre_due', cls: 'rpt-sms-btn--pre', label: 'پ', title: 'پیش از موعد' },
            { type: 'installment_due', cls: 'rpt-sms-btn--due', label: 'س', title: 'سررسید' },
            { type: 'installment_overdue', cls: 'rpt-sms-btn--over', label: 'م', title: 'معوق' },
            { type: 'installment_thanks', cls: 'rpt-sms-btn--thanks', label: 'ت', title: 'تشکر' },
        ].forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rpt-sms-btn ' + item.cls;
            btn.setAttribute('data-rpt-sms', '');
            btn.setAttribute('data-sms-type', item.type);
            btn.setAttribute('data-customer-id', String(row.customer_id));
            btn.setAttribute('data-installment-id', String(row.sms_installment_id || ''));
            btn.setAttribute('data-loan-file-id', String(row.loan_file_id));
            btn.setAttribute('data-customer-name', row.customer_name || '');
            btn.setAttribute('data-customer-mobile', row.customer_mobile || '');
            btn.title = item.title;
            btn.textContent = item.label;
            if (!row.sms_installment_id || row.loan_locked) {
                btn.disabled = true;
            }
            wrap.appendChild(btn);
        });
        td.appendChild(wrap);
    }

    function rptBuildOpsCell(tr, row) {
        var td = rptTd(tr, 'rpt-td--ops', 'عملیات');
        var wrap = document.createElement('div');
        wrap.className = 'rpt-ops';
        var link = document.createElement('a');
        link.className = 'rpt-op-btn';
        link.href = row.loan_manage_url || '#';
        link.title = 'اقساط و واریز';
        link.setAttribute('aria-label', 'اقساط و واریز');
        link.innerHTML = '<i class="fa-solid fa-list" aria-hidden="true"></i>';
        wrap.appendChild(link);
        td.appendChild(wrap);
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.installmentDueExportUrl) {
            return;
        }
        var url = new URL(cfg.installmentDueExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (paymentStatusSelect && String(paymentStatusSelect.value || '')) {
            url.searchParams.set('payment_status', String(paymentStatusSelect.value));
        }
        if (overdueSelect && String(overdueSelect.value || '')) {
            url.searchParams.set('overdue', String(overdueSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverPaymentStatus = '';
    var serverOverdue = '';

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-id-from', 'rpt-id-to');
        rptUpdateExportUrl();
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 11;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            rptStackLinkCell(
                tr,
                'rpt-td--customer',
                row.customer_manage_url,
                row.customer_name || '—',
                [
                    { text: rptFaNum(row.customer_mobile || ''), ltr: true },
                    {
                        text:
                            row.customer_national_id && row.customer_national_id !== '—'
                                ? 'کد ملی: ' + rptFaNum(row.customer_national_id)
                                : '',
                    },
                ],
                'مشتری'
            );

            var loanLines = [
                { text: row.loan_type_title || '' },
                { text: 'قسط ' + rptFaNum(row.sequence || '') },
            ];
            if (row.loan_description) {
                loanLines.push({ text: row.loan_description });
            }

            rptStackLinkCell(
                tr,
                'rpt-td--loan',
                row.loan_manage_url,
                'پرونده: ' + rptFaNum(row.loan_code || '—'),
                loanLines,
                'اطلاعات وام'
            );

            var tdInst = rptTd(tr, 'rpt-td--num', 'مبلغ قسط');
            tdInst.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' +
                (row.installment_amount_formatted || rptFormatAmount(row.installment_amount_toman)) +
                '</span>';

            var tdPaid = rptTd(tr, 'rpt-td--num', 'مبلغ واریزی');
            tdPaid.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.paid_amount_formatted || '—') + '</span>';

            var tdDue = rptTd(tr, 'rpt-td--num', 'تاریخ سررسید');
            tdDue.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.due_jdate || '—') + '</span>';

            var tdDeposit = rptTd(tr, 'rpt-td--num', 'تاریخ واریز');
            tdDeposit.style.whiteSpace = 'normal';
            tdDeposit.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.paid_jdate || '—') + '</span>';

            var tdMethod = rptTd(tr, 'rpt-td--text', 'نحوه پرداخت');
            tdMethod.textContent = row.payment_methods_label || '—';

            var tdEarly = rptTd(tr, 'rpt-td--text', 'دیرکرد/زودکرد');
            tdEarly.textContent = row.early_late_label || '—';

            var tdNotes = rptTd(tr, 'rpt-td--text', 'توضیحات');
            if (row.notes_text) {
                tdNotes.textContent = row.notes_text;
            } else {
                tdNotes.textContent = '';
                tdNotes.classList.add('rpt-td--empty');
            }

            rptBuildSmsCell(tr, row);
            rptBuildOpsCell(tr, row);
            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }
            var hay = [
                row.loan_code,
                row.loan_type_title,
                row.loan_description,
                row.customer_name,
                row.customer_national_id,
                row.customer_mobile,
                row.payment_methods_label,
                row.notes_text,
            ]
                .join(' ')
                .toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' قسط';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverPaymentStatus = paymentStatusSelect ? String(paymentStatusSelect.value || '') : '';
        serverOverdue = overdueSelect ? String(overdueSelect.value || '') : '';

        tbody.innerHTML = '<tr><td colspan="11" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }

        var url = new URL(cfg.installmentDueDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverPaymentStatus) {
            url.searchParams.set('payment_status', serverPaymentStatus);
        }
        if (serverOverdue) {
            url.searchParams.set('overdue', serverOverdue);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' قسط';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (paymentStatusSelect) {
                    paymentStatusSelect.value = serverPaymentStatus;
                }
                if (overdueSelect) {
                    overdueSelect.value = serverOverdue;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="11" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (paymentStatusSelect) {
        paymentStatusSelect.addEventListener('change', function () {
            loadData();
        });
    }

    if (overdueSelect) {
        overdueSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="installment-due-by-date"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitDepositsByDateReport(cfg) {
    var overlay = document.getElementById('rpt-modal-deposits-by-date');
    var form = document.getElementById('rpt-deposits-by-date-form');
    var tbody = document.getElementById('rpt-dep-tbody');
    var meta = document.getElementById('rpt-dep-meta');
    var searchInput = document.getElementById('rpt-dep-search');
    var paymentMethodSelect = document.getElementById('rpt-dep-payment-method');
    var fromInput = document.getElementById('rpt-dep-from');
    var toInput = document.getElementById('rpt-dep-to');
    var exportExcelLink = document.getElementById('rpt-dep-export-excel');

    if (!overlay || !form || !tbody) {
        return;
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptStackLinkCell(tr, cellClass, href, title, lines, dataLabel) {
        var td = rptTd(tr, 'rpt-td--stack ' + cellClass, dataLabel);
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack';
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href;
        link.textContent = title;
        stack.appendChild(link);
        lines.forEach(function (line) {
            if (!line || !line.text) {
                return;
            }
            var span = document.createElement('span');
            if (line.ltr) {
                span.className = 'rpt-val-ltr';
            }
            span.textContent = line.text;
            stack.appendChild(span);
        });
        td.appendChild(stack);
    }

    function rptBuildOpsCell(tr, row) {
        var td = rptTd(tr, 'rpt-td--ops', 'عملیات');
        var wrap = document.createElement('div');
        wrap.className = 'rpt-ops';
        var link = document.createElement('a');
        link.className = 'rpt-op-btn';
        link.href = row.loan_manage_url || '#';
        link.title = 'اقساط و واریز';
        link.setAttribute('aria-label', 'اقساط و واریز');
        link.innerHTML = '<i class="fa-solid fa-list" aria-hidden="true"></i>';
        wrap.appendChild(link);
        td.appendChild(wrap);
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.depositsByDateExportUrl) {
            return;
        }
        var url = new URL(cfg.depositsByDateExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (paymentMethodSelect && String(paymentMethodSelect.value || '')) {
            url.searchParams.set('payment_method', String(paymentMethodSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverPaymentMethod = '';

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-dep-from', 'rpt-dep-to');
        rptUpdateExportUrl();
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 10;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            rptStackLinkCell(
                tr,
                'rpt-td--customer',
                row.customer_manage_url,
                row.customer_name || '—',
                [
                    { text: rptFaNum(row.customer_mobile || ''), ltr: true },
                    {
                        text:
                            row.customer_national_id && row.customer_national_id !== '—'
                                ? 'کد ملی: ' + rptFaNum(row.customer_national_id)
                                : '',
                    },
                ],
                'مشتری'
            );

            var loanLines = [{ text: row.loan_type_title || '' }];
            if (row.loan_description) {
                loanLines.push({ text: row.loan_description });
            }
            if (row.sequence) {
                loanLines.push({ text: 'قسط ' + rptFaNum(row.sequence) });
            }

            rptStackLinkCell(
                tr,
                'rpt-td--loan',
                row.loan_manage_url,
                rptFaNum(row.loan_code || '—'),
                loanLines,
                'وام'
            );

            var tdInst = rptTd(tr, 'rpt-td--num', 'مبلغ قسط');
            tdInst.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' +
                (row.installment_amount_formatted || rptFormatAmount(row.installment_amount_toman)) +
                '</span>';

            var tdDeposit = rptTd(tr, 'rpt-td--num', 'مبلغ واریزی');
            tdDeposit.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' +
                (row.deposit_amount_formatted || rptFormatAmount(row.deposit_amount_toman)) +
                '</span>';

            var tdDue = rptTd(tr, 'rpt-td--num', 'تاریخ سررسید');
            tdDue.innerHTML = '<span class="rpt-val-ltr rpt-num">' + (row.due_jdate || '—') + '</span>';

            var tdDepDate = rptTd(tr, 'rpt-td--num', 'تاریخ واریز');
            tdDepDate.innerHTML = '<span class="rpt-val-ltr rpt-num">' + (row.deposit_jdate || '—') + '</span>';

            var tdMethod = rptTd(tr, 'rpt-td--text', 'نحوه پرداخت');
            tdMethod.textContent = row.payment_method_label || '—';

            var tdEarly = rptTd(tr, 'rpt-td--text', 'دیرکرد/زودکرد');
            tdEarly.textContent = row.early_late_label || '—';

            var tdNotes = rptTd(tr, 'rpt-td--text', 'توضیحات');
            if (row.notes_text) {
                tdNotes.textContent = row.notes_text;
            } else {
                tdNotes.textContent = '';
                tdNotes.classList.add('rpt-td--empty');
            }

            rptBuildOpsCell(tr, row);
            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }
            var hay = [
                row.loan_code,
                row.loan_type_title,
                row.loan_description,
                row.customer_name,
                row.customer_national_id,
                row.customer_mobile,
                row.payment_method_label,
                row.notes_text,
            ]
                .join(' ')
                .toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' واریز';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverPaymentMethod = paymentMethodSelect ? String(paymentMethodSelect.value || '') : '';

        tbody.innerHTML = '<tr><td colspan="10" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }

        var url = new URL(cfg.depositsByDateDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverPaymentMethod) {
            url.searchParams.set('payment_method', serverPaymentMethod);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' واریز';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (paymentMethodSelect) {
                    paymentMethodSelect.value = serverPaymentMethod;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="10" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="deposits-by-date"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitSettledMembersReport(cfg) {
    var overlay = document.getElementById('rpt-modal-settled-members');
    var form = document.getElementById('rpt-settled-members-date-form');
    var tbody = document.getElementById('rpt-sm-tbody');
    var meta = document.getElementById('rpt-sm-meta');
    var searchInput = document.getElementById('rpt-sm-search');
    var minLoansSelect = document.getElementById('rpt-sm-min-loans');
    var fromInput = document.getElementById('rpt-sm-from');
    var toInput = document.getElementById('rpt-sm-to');
    var exportExcelLink = document.getElementById('rpt-sm-export-excel');

    if (!overlay || !form || !tbody) {
        return;
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptLinkCell(tr, href, text, dataLabel, extraClass) {
        var td = rptTd(tr, 'rpt-td--text ' + (extraClass || ''), dataLabel);
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href || '#';
        link.textContent = text || '—';
        td.appendChild(link);

        return td;
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.settledMembersExportUrl) {
            return;
        }
        var url = new URL(cfg.settledMembersExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (minLoansSelect && String(minLoansSelect.value || '')) {
            url.searchParams.set('min_settled_loans', String(minLoansSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverMinLoans = '1';

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-sm-from', 'rpt-sm-to');
        rptUpdateExportUrl();
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 6;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            rptLinkCell(tr, row.customer_manage_url, row.first_name || '—', 'نام', 'rpt-td--name');
            rptLinkCell(tr, row.customer_manage_url, row.last_name || '—', 'نام خانوادگی', 'rpt-td--name');

            var tdMobile = rptTd(tr, 'rpt-td--num', 'موبایل');
            tdMobile.innerHTML = '<span class="rpt-val-ltr rpt-num">' + (row.mobile || '—') + '</span>';

            var tdCount = rptTd(tr, 'rpt-td--num', 'تعداد وام');
            tdCount.innerHTML = '<span class="rpt-num">' + rptFaNum(row.settled_loans_count) + '</span>';

            var tdTotal = rptTd(tr, 'rpt-td--num', 'مجموع وام‌ها');
            tdTotal.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.total_loans_formatted || rptFormatAmount(row.total_loans_toman)) + '</span>';

            var tdDate = rptTd(tr, 'rpt-td--num', 'تاریخ آخرین تسویه');
            tdDate.innerHTML = '<span class="rpt-val-ltr rpt-num">' + (row.last_settled_jdate || '—') + '</span>';

            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }
            var hay = [
                row.first_name,
                row.last_name,
                row.mobile,
                row.national_id,
            ]
                .join(' ')
                .toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' عضو';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverMinLoans = minLoansSelect ? String(minLoansSelect.value || '1') : '1';

        tbody.innerHTML = '<tr><td colspan="6" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }

        var url = new URL(cfg.settledMembersDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverMinLoans) {
            url.searchParams.set('min_settled_loans', serverMinLoans);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' عضو';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (minLoansSelect) {
                    minLoansSelect.value = serverMinLoans;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (minLoansSelect) {
        minLoansSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="settled-members"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitWalletTransactionsByDateReport(cfg) {
    var overlay = document.getElementById('rpt-modal-wallet-transactions-by-date');
    var form = document.getElementById('rpt-wallet-tx-date-form');
    var tbody = document.getElementById('rpt-wtx-tbody');
    var meta = document.getElementById('rpt-wtx-meta');
    var searchInput = document.getElementById('rpt-wtx-search');
    var directionSelect = document.getElementById('rpt-wtx-direction');
    var sourceSelect = document.getElementById('rpt-wtx-source');
    var fromInput = document.getElementById('rpt-wtx-from');
    var toInput = document.getElementById('rpt-wtx-to');
    var exportExcelLink = document.getElementById('rpt-wtx-export-excel');

    if (!overlay || !form || !tbody) {
        return;
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.walletTransactionsExportUrl) {
            return;
        }
        var url = new URL(cfg.walletTransactionsExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (directionSelect && String(directionSelect.value || '')) {
            url.searchParams.set('direction', String(directionSelect.value));
        }
        if (sourceSelect && String(sourceSelect.value || '')) {
            url.searchParams.set('source', String(sourceSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverDirection = '';
    var serverSource = '';

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-wtx-from', 'rpt-wtx-to');
        rptUpdateExportUrl();
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 6;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            var tdTime = rptTd(tr, 'rpt-td--num', 'زمان');
            tdTime.innerHTML = '<span class="rpt-val-ltr rpt-num">' + (row.created_at_fa || '—') + '</span>';

            var tdGateway = rptTd(tr, 'rpt-td--text', 'درگاه');
            tdGateway.textContent = row.gateway_label || '—';

            var amountClass =
                row.direction === 'deposit' ? 'rpt-td--num rpt-td--amount-deposit' : 'rpt-td--num rpt-td--amount-withdraw';
            var tdAmount = rptTd(tr, amountClass, 'مبلغ');
            tdAmount.innerHTML =
                '<span class="rpt-val-ltr rpt-num">' + (row.amount_formatted || rptFormatAmount(row.amount_toman)) + '</span>';

            var tdDetails = rptTd(tr, 'rpt-td--stack rpt-td--details', 'جزئیات تراکنش');
            var detailsWrap = document.createElement('div');
            detailsWrap.className = 'rpt-cell-stack';
            if (row.customer_manage_url) {
                var custLink = document.createElement('a');
                custLink.className = 'rpt-link';
                custLink.href = row.customer_manage_url;
                custLink.textContent = row.customer_name || '—';
                detailsWrap.appendChild(custLink);
            } else {
                var custSpan = document.createElement('span');
                custSpan.textContent = row.customer_name || '—';
                detailsWrap.appendChild(custSpan);
            }
            var detailsInner = document.createElement('div');
            detailsInner.className = 'rpt-cell-sub';
            detailsInner.innerHTML = row.details_html || '—';
            detailsWrap.appendChild(detailsInner);
            tdDetails.appendChild(detailsWrap);

            var tdFinal = rptTd(tr, 'rpt-td--stack rpt-td--num', 'ثبت نهایی پرداخت');
            var finalStack = document.createElement('div');
            finalStack.className = 'rpt-cell-stack rpt-cell-stack--amount';
            var finalTime = document.createElement('span');
            finalTime.className = 'rpt-val-ltr rpt-num';
            finalTime.textContent = row.finalized_at_fa || '—';
            finalStack.appendChild(finalTime);
            if (row.finalized_status_fa) {
                var finalStatus = document.createElement('span');
                finalStatus.className = 'rpt-cell-sub';
                finalStatus.textContent = row.finalized_status_fa;
                finalStack.appendChild(finalStatus);
            }
            tdFinal.appendChild(finalStack);

            var tdNotes = rptTd(tr, 'rpt-td--text rpt-td--notes', 'توضیحات');
            tdNotes.textContent = row.description_text || '—';

            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }
            var hay = [
                row.customer_name,
                row.customer_mobile_fa,
                row.customer_code_fa,
                row.description_text,
                row.gateway_label,
                row.details_excel,
            ]
                .join(' ')
                .toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' تراکنش';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverDirection = directionSelect ? String(directionSelect.value || '') : '';
        serverSource = sourceSelect ? String(sourceSelect.value || '') : '';

        tbody.innerHTML = '<tr><td colspan="6" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }

        var url = new URL(cfg.walletTransactionsDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverDirection) {
            url.searchParams.set('direction', serverDirection);
        }
        if (serverSource) {
            url.searchParams.set('source', serverSource);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' تراکنش';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (directionSelect) {
                    directionSelect.value = serverDirection;
                }
                if (sourceSelect) {
                    sourceSelect.value = serverSource;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (directionSelect) {
        directionSelect.addEventListener('change', loadData);
    }

    if (sourceSelect) {
        sourceSelect.addEventListener('change', loadData);
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    document.querySelectorAll('[data-rpt-open="wallet-transactions-by-date"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptBindReportEscapeHandlers(reports) {
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        for (var i = 0; i < reports.length; i++) {
            var item = reports[i];
            if (item && item.overlay && !item.overlay.hidden && typeof item.closeModal === 'function') {
                item.closeModal();
                break;
            }
        }
    });
}

/**
 * اتصال یک‌بارهٔ کارت‌های گزارش به مدال‌ها (پشتیبان اگر init جداگانه خطا بخورد).
 *
 * @param {Record<string, { openModal?: function } | undefined>} registry
 */
function rptBindReportCardOpens(registry) {
    document.querySelectorAll('.rpt-card[data-rpt-open]').forEach(function (btn) {
        if (btn.disabled) {
            return;
        }
        var key = String(btn.getAttribute('data-rpt-open') || '');
        if (!key || btn.getAttribute('data-rpt-card-bound') === '1') {
            return;
        }
        btn.setAttribute('data-rpt-card-bound', '1');
        btn.addEventListener('click', function () {
            var report = registry[key];
            if (report && typeof report.openModal === 'function') {
                report.openModal();
            }
        });
    });
}

/**
 * باز کردن خودکار مدال گزارش در صورت وجود ?open=report-id در آدرس (مثلاً از داشبورد).
 *
 * @param {Record<string, { openModal?: function } | undefined>} registry
 */
function rptAutoOpenFromQuery(registry) {
    try {
        var params = new URLSearchParams(window.location.search);
        var key = String(params.get('open') || '').trim();
        if (!key || !Object.prototype.hasOwnProperty.call(registry, key)) {
            return;
        }
        var report = registry[key];
        if (!report || typeof report.openModal !== 'function') {
            return;
        }
        var card = document.querySelector('.rpt-card[data-rpt-open="' + key.replace(/"/g, '') + '"]');
        if (!card || card.disabled) {
            return;
        }
        report.openModal();
        params.delete('open');
        var qs = params.toString();
        var next = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
        window.history.replaceState({}, '', next);
    } catch (e) {
        /* noop */
    }
}

function rptInitQuickSms(cfg) {
    var overlay = document.getElementById('rpt-quick-sms-overlay');
    var form = document.getElementById('rpt-quick-sms-form');
    var closeBtn = document.getElementById('rpt-quick-sms-close');
    var cancelBtn = document.getElementById('rpt-quick-sms-cancel');
    var titleEl = document.getElementById('rpt-quick-sms-title');
    var subEl = document.getElementById('rpt-quick-sms-sub');
    var templateSel = document.getElementById('rpt-quick-sms-template');
    var textEl = document.getElementById('rpt-quick-sms-text');

    if (!overlay || !form) {
        return;
    }

    var current = {
        customerId: null,
        installmentId: null,
        smsType: '',
        customerName: '',
        customerMobile: '',
    };

    var smsTitles = {
        installment_pre_due: 'پیش از سررسید',
        installment_due: 'سررسید',
        installment_overdue: 'معوق',
        installment_thanks: 'تشکر پس از پرداخت',
    };

    function closeQuickSms() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
    }

    function openQuickSms(btn) {
        current.customerId = parseInt(btn.getAttribute('data-customer-id') || '0', 10);
        current.installmentId = parseInt(btn.getAttribute('data-installment-id') || '0', 10);
        current.smsType = String(btn.getAttribute('data-sms-type') || '');
        current.customerName = String(btn.getAttribute('data-customer-name') || '');
        current.customerMobile = String(btn.getAttribute('data-customer-mobile') || '');

        if (!current.customerId || !current.installmentId || !current.smsType) {
            if (window.AdminSwal && AdminSwal.error) {
                AdminSwal.error('برای این ردیف قسط مناسب پیامک یافت نشد.');
            }

            return;
        }

        if (subEl) {
            subEl.textContent = (smsTitles[current.smsType] || '') + ' — ' + current.customerName;
        }
        if (textEl) {
            textEl.value = '';
            textEl.placeholder = 'در حال بارگذاری پیش‌نمایش…';
        }

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');

        var previewUrl =
            (cfg.customersBaseUrl || '') +
            '/' +
            encodeURIComponent(String(current.customerId)) +
            '/sms-modal-preview?sms_type=' +
            encodeURIComponent(current.smsType) +
            '&installment_id=' +
            encodeURIComponent(String(current.installmentId));

        fetch(previewUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                if (textEl) {
                    textEl.placeholder = 'متن پیامک را بنویسید…';
                    if (data.body != null) {
                        textEl.value = String(data.body);
                    }
                }
                if (templateSel && data.template_id != null && String(data.template_id) !== '') {
                    templateSel.value = String(data.template_id);
                }
            })
            .catch(function () {
                if (textEl) {
                    textEl.placeholder = 'متن پیامک را بنویسید…';
                }
            });
    }

    document.addEventListener('click', function (e) {
        var smsBtn = e.target.closest('[data-rpt-sms]');
        if (smsBtn && !smsBtn.disabled) {
            e.preventDefault();
            openQuickSms(smsBtn);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!current.customerId) {
            return;
        }

        var body = {
            sms_type: current.smsType,
            sms_text: textEl ? String(textEl.value || '').trim() : '',
            sms_template_id: templateSel && templateSel.value ? templateSel.value : null,
            installment_id: current.installmentId,
        };

        var postUrl = (cfg.customersBaseUrl || '') + '/' + encodeURIComponent(String(current.customerId)) + '/quick-sms';

        fetch(postUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok) {
                    var msg = (res.data && res.data.message) || 'ارسال پیامک ناموفق بود.';
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error(msg);
                    }

                    return;
                }
                if (window.AdminSwal && AdminSwal.success) {
                    AdminSwal.success((res.data && res.data.message) || 'پیامک ارسال شد.');
                }
                closeQuickSms();
            })
            .catch(function () {
                if (window.AdminSwal && AdminSwal.error) {
                    AdminSwal.error('خطا در ارسال پیامک.');
                }
            });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeQuickSms);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeQuickSms);
    }
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeQuickSms();
        }
    });
}

function rptInitLoanGuaranteesReport(cfg) {
    var overlay = document.getElementById('rpt-modal-loan-guarantees');
    if (!overlay) {
        return;
    }

    var form = document.getElementById('rpt-gr-date-form');
    var tbody = document.getElementById('rpt-gr-tbody');
    var meta = document.getElementById('rpt-gr-meta');
    var summaryEl = document.getElementById('rpt-gr-summary');
    var searchInput = document.getElementById('rpt-gr-search');
    var typeSelect = document.getElementById('rpt-gr-type');
    var settledSelect = document.getElementById('rpt-gr-settled');
    var fromInput = document.getElementById('rpt-gr-from');
    var toInput = document.getElementById('rpt-gr-to');
    var exportExcelLink = document.getElementById('rpt-gr-export-excel');

    function openModalShell() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-gr-from', 'rpt-gr-to');
    }

    function closeModalShell() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (!form || !tbody) {
        return { openModal: openModalShell, closeModal: closeModalShell, overlay: overlay };
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.loanGuaranteesExportUrl) {
            return;
        }
        var url = new URL(cfg.loanGuaranteesExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (typeSelect && String(typeSelect.value || '')) {
            url.searchParams.set('guarantee_type', String(typeSelect.value));
        }
        if (settledSelect && String(settledSelect.value || '')) {
            url.searchParams.set('settled', String(settledSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var summaryCache = null;
    var serverSearch = '';
    var serverType = '';
    var serverSettled = '';

    function openModal() {
        openModalShell();
        rptUpdateExportUrl();
    }

    function closeModal() {
        closeModalShell();
    }

    function updateSummary(shownCount) {
        if (!summaryEl) {
            return;
        }
        var sum = summaryCache;
        if (!sum || typeof sum !== 'object') {
            summaryEl.hidden = true;
            summaryEl.textContent = '';

            return;
        }
        var parts = [];
        parts.push('کل: ' + rptFaNum(sum.total || 0));
        parts.push('سازمانی (خودم): ' + rptFaNum(sum.org_self || 0));
        parts.push('سازمانی (شخص دیگر): ' + rptFaNum(sum.org_other || 0));
        var ch = Number(sum.cheque || 0);
        parts.push('چک: ' + rptFaNum(ch));
        if (ch > 0) {
            parts.push('عودت‌شده: ' + rptFaNum(sum.cheque_returned || 0));
            parts.push('وصول‌شده: ' + rptFaNum(sum.cheque_collected || 0));
        }
        parts.push('طلا: ' + rptFaNum(sum.gold || 0));
        parts.push('سایر: ' + rptFaNum(sum.other || 0));
        var q = searchInput ? String(searchInput.value || '').trim() : '';
        if (q !== '' && typeof shownCount === 'number' && allRows.length > 0 && shownCount !== allRows.length) {
            parts.push('نمایش: ' + rptFaNum(shownCount) + ' از ' + rptFaNum(allRows.length));
        }
        summaryEl.textContent = parts.join(' · ');
        summaryEl.hidden = false;
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 6;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);
            updateSummary(0);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            rptStackLinkCell(
                tr,
                'rpt-td--stack rpt-td--loan',
                row.loan_manage_url || '#',
                'شماره وام: ' + rptFaNum(row.loan_code || '—'),
                [{ text: row.loan_type_title || '' }],
                'اطلاعات وام'
            );

            rptStackLinkCell(
                tr,
                'rpt-td--stack rpt-td--customer',
                row.customer_manage_url || '#',
                row.customer_full_name || '—',
                [
                    {
                        text:
                            row.customer_national_id && row.customer_national_id !== ''
                                ? 'کد ملی: ' + rptFaNum(row.customer_national_id)
                                : '',
                    },
                    {
                        text:
                            row.customer_mobile && row.customer_mobile !== ''
                                ? 'موبایل: ' + rptFaNum(row.customer_mobile)
                                : '',
                        ltr: true,
                    },
                ],
                'اطلاعات مشتری'
            );

            rptNumCell(tr, row.amount_toman, 'rpt-td--amount', 'مبلغ');
            rptNumCell(tr, row.installment_amount_toman, '', 'مبلغ اقساط');

            var tdType = rptTd(tr, 'rpt-td--text', 'نوع ضمانت');
            tdType.textContent = row.guarantee_type_label || '—';

            var tdDetail = rptTd(tr, 'rpt-td--stack', 'اطلاعات ضمانت');
            var detailStack = document.createElement('div');
            detailStack.className = 'rpt-cell-stack';
            if (row.guarantee_highlight_name) {
                var highlight = document.createElement('span');
                highlight.className = 'rpt-guarantee-highlight';
                highlight.textContent = row.guarantee_highlight_name;
                detailStack.appendChild(highlight);
            }
            (Array.isArray(row.guarantee_detail_lines) ? row.guarantee_detail_lines : []).forEach(function (line) {
                if (!line) {
                    return;
                }
                var span = document.createElement('span');
                span.textContent = line;
                detailStack.appendChild(span);
            });
            tdDetail.appendChild(detailStack);

            tbody.appendChild(tr);
        });

        updateSummary(rows.length);
    }

    function rptStackLinkCell(tr, cellClass, href, title, lines, dataLabel) {
        var td = rptTd(tr, 'rpt-td--stack ' + (cellClass || ''), dataLabel);
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack';
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href;
        link.textContent = title;
        stack.appendChild(link);
        lines.forEach(function (line) {
            if (!line || !line.text) {
                return;
            }
            var span = document.createElement('span');
            if (line.ltr) {
                span.className = 'rpt-val-ltr';
            }
            span.textContent = line.text;
            stack.appendChild(span);
        });
        td.appendChild(stack);
    }

    function rptNumCell(tr, value, extraClass, dataLabel) {
        var td = rptTd(tr, 'rpt-td--num ' + (extraClass || ''), dataLabel);
        var span = document.createElement('span');
        span.className = 'rpt-val-ltr rpt-num';
        span.textContent = rptFormatAmount(value);
        td.appendChild(span);

        return td;
    }

    function rowHaystack(row) {
        var lines = Array.isArray(row.guarantee_detail_lines) ? row.guarantee_detail_lines : [];
        return [
            row.loan_code,
            row.loan_type_title,
            row.customer_full_name,
            row.customer_national_id,
            row.customer_mobile,
            row.guarantee_type,
            row.guarantee_type_label,
            row.guarantee_highlight_name,
        ]
            .concat(lines)
            .join(' ')
            .toLowerCase();
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }

            return rowHaystack(row).indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' ضمانت';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverType = typeSelect ? String(typeSelect.value || '') : '';
        serverSettled = settledSelect ? String(settledSelect.value || '') : '';

        tbody.innerHTML = '<tr><td colspan="6" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }
        if (summaryEl) {
            summaryEl.hidden = true;
            summaryEl.textContent = '';
        }

        var url = new URL(cfg.loanGuaranteesDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverType) {
            url.searchParams.set('guarantee_type', serverType);
        }
        if (serverSettled) {
            url.searchParams.set('settled', serverSettled);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                summaryCache = data.summary && typeof data.summary === 'object' ? data.summary : null;
                if (meta && data.meta) {
                    meta.textContent =
                        'بازه: ' +
                        String(data.meta.from_jdate || '') +
                        ' تا ' +
                        String(data.meta.to_jdate || '') +
                        ' — ' +
                        rptFaNum(data.meta.count || allRows.length) +
                        ' ضمانت';
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (typeSelect) {
                    typeSelect.value = serverType;
                }
                if (settledSelect) {
                    settledSelect.value = serverSettled;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
                summaryCache = null;
                if (summaryEl) {
                    summaryEl.hidden = true;
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            loadData();
        });
    }

    if (settledSelect) {
        settledSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="loan-guarantees"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitLoanInterestFeesReport(cfg) {
    var overlay = document.getElementById('rpt-modal-loan-interest-fees');
    if (!overlay) {
        return;
    }

    var form = document.getElementById('rpt-lif-date-form');
    var tbody = document.getElementById('rpt-lif-tbody');
    var meta = document.getElementById('rpt-lif-meta');
    var summaryEl = document.getElementById('rpt-lif-summary');
    var searchInput = document.getElementById('rpt-lif-search');
    var settledSelect = document.getElementById('rpt-lif-settled');
    var fromInput = document.getElementById('rpt-lif-from');
    var toInput = document.getElementById('rpt-lif-to');
    var exportExcelLink = document.getElementById('rpt-lif-export-excel');
    var customerIdInput = document.getElementById('rpt-lif-customer-id');
    var customerSearchInput = document.getElementById('rpt-lif-customer-search');
    var customerClearBtn = document.getElementById('rpt-lif-customer-clear');
    var customerSuggest = document.getElementById('rpt-lif-customer-suggest');

    function openModalShell() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-lif-from', 'rpt-lif-to');
    }

    function closeModalShell() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        hideCustomerSuggest();
    }

    if (!form || !tbody) {
        return { openModal: openModalShell, closeModal: closeModalShell, overlay: overlay };
    }

    function rptTd(tr, className, dataLabel) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        if (dataLabel) {
            td.setAttribute('data-label', dataLabel);
        }
        tr.appendChild(td);

        return td;
    }

    function rptStackLinkCell(tr, cellClass, href, title, lines, dataLabel) {
        var td = rptTd(tr, 'rpt-td--stack ' + (cellClass || ''), dataLabel);
        var stack = document.createElement('div');
        stack.className = 'rpt-cell-stack';
        var link = document.createElement('a');
        link.className = 'rpt-link';
        link.href = href;
        link.textContent = title;
        stack.appendChild(link);
        lines.forEach(function (line) {
            if (!line || !line.text) {
                return;
            }
            var span = document.createElement('span');
            if (line.ltr) {
                span.className = 'rpt-val-ltr';
            }
            span.textContent = line.text;
            stack.appendChild(span);
        });
        td.appendChild(stack);
    }

    function rptNumCell(tr, value, extraClass, dataLabel, amountClass) {
        var td = rptTd(tr, 'rpt-td--num ' + (extraClass || ''), dataLabel);
        var span = document.createElement('span');
        span.className = 'rpt-val-ltr rpt-num' + (amountClass ? ' ' + amountClass : '');
        span.textContent = rptFormatAmount(value);
        td.appendChild(span);

        return td;
    }

    function hideCustomerSuggest() {
        if (!customerSuggest) {
            return;
        }
        customerSuggest.hidden = true;
        customerSuggest.replaceChildren();
    }

    function syncCustomerClearBtn() {
        if (!customerClearBtn || !customerIdInput) {
            return;
        }
        var hasFilter = String(customerIdInput.value || '').trim() !== '';
        customerClearBtn.hidden = !hasFilter;
    }

    function clearCustomerFilter() {
        if (customerIdInput) {
            customerIdInput.value = '';
        }
        if (customerSearchInput) {
            customerSearchInput.value = '';
            customerSearchInput.placeholder = 'همه مشتریان — برای فیلتر یک مشتری جستجو کنید…';
        }
        hideCustomerSuggest();
        syncCustomerClearBtn();
    }

    function selectCustomer(id, text) {
        if (customerIdInput) {
            customerIdInput.value = String(id);
        }
        if (customerSearchInput) {
            customerSearchInput.value = text;
        }
        hideCustomerSuggest();
        syncCustomerClearBtn();
        loadData();
    }

    function renderCustomerSuggest(results) {
        if (!customerSuggest) {
            return;
        }
        customerSuggest.replaceChildren();
        if (!results.length) {
            var emptyBtn = document.createElement('button');
            emptyBtn.type = 'button';
            emptyBtn.textContent = 'موردی یافت نشد';
            emptyBtn.disabled = true;
            customerSuggest.appendChild(emptyBtn);
            customerSuggest.hidden = false;

            return;
        }
        results.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = item.text || '';
            btn.addEventListener('click', function () {
                selectCustomer(item.id, item.text || '');
            });
            customerSuggest.appendChild(btn);
        });
        customerSuggest.hidden = false;
    }

    var customerSearchTimer;
    function fetchCustomerSuggestions(term) {
        if (!cfg.loanInterestFeesCustomersUrl) {
            return;
        }
        var url = new URL(cfg.loanInterestFeesCustomersUrl, window.location.origin);
        url.searchParams.set('q', term);
        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                renderCustomerSuggest(Array.isArray(data.results) ? data.results : []);
            })
            .catch(function () {
                hideCustomerSuggest();
            });
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.loanInterestFeesExportUrl) {
            return;
        }
        var url = new URL(cfg.loanInterestFeesExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (customerIdInput && String(customerIdInput.value || '').trim()) {
            url.searchParams.set('customer_id', String(customerIdInput.value).trim());
        }
        if (settledSelect && String(settledSelect.value || '')) {
            url.searchParams.set('settled', String(settledSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var summaryCache = null;
    var serverSearch = '';
    var serverSettled = '';
    var serverCustomerId = '';

    function openModal() {
        openModalShell();
        rptUpdateExportUrl();
        syncCustomerClearBtn();
    }

    function closeModal() {
        closeModalShell();
    }

    function updateSummary(shownCount) {
        if (!summaryEl) {
            return;
        }
        var sum = summaryCache;
        if (!sum || typeof sum !== 'object') {
            summaryEl.hidden = true;
            summaryEl.textContent = '';

            return;
        }
        var parts = [];
        parts.push('پرونده: ' + rptFaNum(sum.loan_count || 0));
        parts.push('اصل: ' + rptFormatAmount(sum.principal_total || 0));
        parts.push('بهره: ' + rptFormatAmount(sum.profit_total || 0));
        parts.push('پیش‌پرداخت: ' + rptFormatAmount(sum.down_payment_total || 0));
        parts.push('قابل بازپرداخت: ' + rptFormatAmount(sum.repayable_total || 0));
        parts.push('تخفیف: ' + rptFormatAmount(sum.discount_total || 0));
        parts.push('پرداختی: ' + rptFormatAmount(sum.paid_total || 0));
        parts.push('مانده: ' + rptFormatAmount(sum.remaining_total || 0));
        var q = searchInput ? String(searchInput.value || '').trim() : '';
        if (q !== '' && typeof shownCount === 'number' && allRows.length > 0 && shownCount !== allRows.length) {
            parts.push('نمایش: ' + rptFaNum(shownCount) + ' از ' + rptFaNum(allRows.length));
        }
        summaryEl.textContent = parts.join(' · ');
        summaryEl.hidden = false;
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 12;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);
            updateSummary(0);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            rptStackLinkCell(
                tr,
                'rpt-td--customer',
                row.customer_manage_url || '#',
                row.customer_name || '—',
                [
                    {
                        text:
                            row.customer_national_id && row.customer_national_id !== '—'
                                ? 'کد ملی: ' + rptFaNum(row.customer_national_id)
                                : '',
                    },
                    {
                        text:
                            row.customer_mobile && row.customer_mobile !== '—'
                                ? 'موبایل: ' + rptFaNum(row.customer_mobile)
                                : '',
                        ltr: true,
                    },
                ],
                'مشتری'
            );

            rptStackLinkCell(
                tr,
                'rpt-td--loan',
                row.loan_manage_url || '#',
                'شماره وام: ' + rptFaNum(row.loan_code || '—'),
                [{ text: row.loan_type_title || '' }],
                'پرونده وام'
            );

            rptNumCell(tr, row.principal_toman, 'rpt-td--amount', 'اصل');
            rptNumCell(tr, row.profit_toman, '', 'بهره', 'rpt-amt-profit');
            rptNumCell(tr, row.down_payment_toman, '', 'پیش‌پرداخت', 'rpt-amt-fee');
            rptNumCell(tr, row.total_repayable_toman, '', 'قابل بازپرداخت', 'rpt-amt-total');

            var tdRate = rptTd(tr, 'rpt-td--stack', 'نرخ و روش');
            var rateStack = document.createElement('div');
            rateStack.className = 'rpt-cell-stack rpt-cell-stack--amount';
            var rateSpan = document.createElement('span');
            rateSpan.className = 'rpt-val-ltr';
            rateSpan.textContent = row.effective_interest_rate_label || '—';
            rateStack.appendChild(rateSpan);
            var methodSpan = document.createElement('span');
            methodSpan.textContent = row.profit_calculation_method_label || '—';
            rateStack.appendChild(methodSpan);
            tdRate.appendChild(rateStack);

            rptNumCell(tr, row.paid_amount_toman, '', 'پرداختی');
            rptNumCell(tr, row.remaining_amount_toman, '', 'مانده');

            var discount = Number(row.discount_toman || 0);
            if (discount > 0) {
                rptNumCell(tr, discount, '', 'تخفیف');
            } else {
                var tdDiscount = rptTd(tr, 'rpt-td--num', 'تخفیف');
                tdDiscount.textContent = '—';
            }

            var tdSettled = rptTd(tr, 'rpt-td--num', 'تسویه');
            tdSettled.textContent = row.is_settled_label || '—';

            var tdStart = rptTd(tr, 'rpt-td--num', 'شروع');
            tdStart.textContent = row.loan_start_jdate || '—';

            tbody.appendChild(tr);
        });

        updateSummary(rows.length);
    }

    function rowHaystack(row) {
        return [
            row.loan_code,
            row.loan_type_title,
            row.loan_title,
            row.customer_name,
            row.customer_national_id,
            row.customer_mobile,
            row.profit_calculation_method_label,
            row.effective_interest_rate_label,
        ]
            .join(' ')
            .toLowerCase();
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';

        var filtered = allRows.filter(function (row) {
            if (!q) {
                return true;
            }

            return rowHaystack(row).indexOf(q) !== -1;
        });

        renderRows(filtered);
        if (meta) {
            meta.textContent =
                'نمایش ' + rptFaNum(filtered.length) + ' از ' + rptFaNum(allRows.length) + ' پرونده';
        }
        rptUpdateExportUrl();
    }

    function loadData() {
        var fromVal = fromInput ? String(fromInput.value || '').trim() : '';
        var toVal = toInput ? String(toInput.value || '').trim() : '';
        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverSettled = settledSelect ? String(settledSelect.value || '') : '';
        serverCustomerId = customerIdInput ? String(customerIdInput.value || '').trim() : '';

        tbody.innerHTML = '<tr><td colspan="12" class="rpt-empty">در حال بارگذاری…</td></tr>';
        if (meta) {
            meta.textContent = 'در حال دریافت اطلاعات…';
        }
        if (summaryEl) {
            summaryEl.hidden = true;
            summaryEl.textContent = '';
        }

        var url = new URL(cfg.loanInterestFeesDataUrl || '', window.location.origin);
        url.searchParams.set('from_jdate', fromVal);
        url.searchParams.set('to_jdate', toVal);
        if (serverSearch) {
            url.searchParams.set('q', serverSearch);
        }
        if (serverCustomerId) {
            url.searchParams.set('customer_id', serverCustomerId);
        }
        if (serverSettled) {
            url.searchParams.set('settled', serverSettled);
        }

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                summaryCache = data.summary && typeof data.summary === 'object' ? data.summary : null;
                if (meta && data.meta) {
                    var metaParts = [
                        'بازه: ' + String(data.meta.from_jdate || '') + ' تا ' + String(data.meta.to_jdate || ''),
                        rptFaNum(data.meta.count || allRows.length) + ' پرونده',
                    ];
                    if (serverCustomerId && customerSearchInput && String(customerSearchInput.value || '').trim()) {
                        metaParts.push('مشتری: ' + String(customerSearchInput.value).trim());
                    }
                    meta.textContent = metaParts.join(' — ');
                }
                if (searchInput) {
                    searchInput.value = serverSearch;
                }
                if (settledSelect) {
                    settledSelect.value = serverSettled;
                }
                applyClientFilters();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="12" class="rpt-empty" style="color:#b91c1c;">خطا در دریافت گزارش.</td></tr>';
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
                summaryCache = null;
                if (summaryEl) {
                    summaryEl.hidden = true;
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientFilters, 280);
        });
    }

    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', function () {
            var term = String(customerSearchInput.value || '').trim();
            if (customerIdInput && String(customerIdInput.value || '').trim() !== '') {
                customerIdInput.value = '';
                syncCustomerClearBtn();
            }
            clearTimeout(customerSearchTimer);
            if (term.length < 1) {
                hideCustomerSuggest();
                return;
            }
            customerSearchTimer = setTimeout(function () {
                fetchCustomerSuggestions(term);
            }, 280);
        });

        customerSearchInput.addEventListener('focus', function () {
            var term = String(customerSearchInput.value || '').trim();
            if (term.length > 0 && (!customerIdInput || !String(customerIdInput.value || '').trim())) {
                fetchCustomerSuggestions(term);
            }
        });
    }

    if (customerClearBtn) {
        customerClearBtn.addEventListener('click', function () {
            clearCustomerFilter();
            loadData();
        });
    }

    document.addEventListener('click', function (e) {
        if (!customerSuggest || customerSuggest.hidden) {
            return;
        }
        var picker = customerSearchInput ? customerSearchInput.closest('.rpt-customer-picker') : null;
        if (picker && !picker.contains(e.target)) {
            hideCustomerSuggest();
        }
    });

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    if (settledSelect) {
        settledSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.querySelectorAll('[data-rpt-open="loan-interest-fees"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitAdminActivityReport(cfg) {
    var overlay = document.getElementById('rpt-modal-admin-activity');
    if (!overlay) {
        return;
    }

    var form = document.getElementById('rpt-aa-date-form');
    var tbody = document.getElementById('rpt-aa-tbody');
    var meta = document.getElementById('rpt-aa-meta');
    var searchInput = document.getElementById('rpt-aa-search');
    var actionSelect = document.getElementById('rpt-aa-action');
    var fromInput = document.getElementById('rpt-aa-from');
    var toInput = document.getElementById('rpt-aa-to');
    var exportExcelLink = document.getElementById('rpt-aa-export-excel');
    var adminIdInput = document.getElementById('rpt-aa-admin-id');
    var adminSearchInput = document.getElementById('rpt-aa-admin-search');
    var adminClearBtn = document.getElementById('rpt-aa-admin-clear');
    var adminSuggest = document.getElementById('rpt-aa-admin-suggest');

    function openModalShell() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        rptInitDatePickers('rpt-aa-from', 'rpt-aa-to');
    }

    function closeModalShell() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        hideAdminSuggest();
    }

    if (!form || !tbody) {
        return { openModal: openModalShell, closeModal: closeModalShell, overlay: overlay };
    }

    function hideAdminSuggest() {
        if (!adminSuggest) {
            return;
        }
        adminSuggest.hidden = true;
        adminSuggest.replaceChildren();
    }

    function syncAdminClearBtn() {
        if (!adminClearBtn || !adminIdInput) {
            return;
        }
        adminClearBtn.hidden = String(adminIdInput.value || '').trim() === '';
    }

    function clearAdminFilter() {
        if (adminIdInput) {
            adminIdInput.value = '';
        }
        if (adminSearchInput) {
            adminSearchInput.value = '';
            adminSearchInput.placeholder = 'همه ادمین‌ها — برای فیلتر یک ادمین جستجو کنید…';
        }
        hideAdminSuggest();
        syncAdminClearBtn();
    }

    function selectAdmin(id, text) {
        if (adminIdInput) {
            adminIdInput.value = String(id);
        }
        if (adminSearchInput) {
            adminSearchInput.value = text;
        }
        hideAdminSuggest();
        syncAdminClearBtn();
        loadData();
    }

    function renderAdminSuggest(results) {
        if (!adminSuggest) {
            return;
        }
        adminSuggest.replaceChildren();
        if (!results.length) {
            var emptyBtn = document.createElement('button');
            emptyBtn.type = 'button';
            emptyBtn.textContent = 'موردی یافت نشد';
            emptyBtn.disabled = true;
            adminSuggest.appendChild(emptyBtn);
            adminSuggest.hidden = false;

            return;
        }
        results.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = item.text || '';
            btn.addEventListener('click', function () {
                selectAdmin(item.id, item.text || '');
            });
            adminSuggest.appendChild(btn);
        });
        adminSuggest.hidden = false;
    }

    var adminSearchTimer;
    function fetchAdminSuggestions(term) {
        if (!cfg.adminActivityAdminsUrl) {
            return;
        }
        var url = new URL(cfg.adminActivityAdminsUrl, window.location.origin);
        url.searchParams.set('q', term);
        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                renderAdminSuggest(Array.isArray(data.results) ? data.results : []);
            })
            .catch(function () {
                hideAdminSuggest();
            });
    }

    function rptUpdateExportUrl() {
        if (!exportExcelLink || !cfg.adminActivityExportUrl) {
            return;
        }
        var url = new URL(cfg.adminActivityExportUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }
        if (adminIdInput && String(adminIdInput.value || '').trim()) {
            url.searchParams.set('admin_id', String(adminIdInput.value).trim());
        }
        if (actionSelect && String(actionSelect.value || '')) {
            url.searchParams.set('action', String(actionSelect.value));
        }
        exportExcelLink.href = url.toString();
    }

    var allRows = [];
    var serverSearch = '';
    var serverAction = '';
    var serverAdminId = '';

    function openModal() {
        openModalShell();
        rptUpdateExportUrl();
        syncAdminClearBtn();
    }

    function closeModal() {
        closeModalShell();
    }

    function renderRows(rows) {
        tbody.replaceChildren();

        if (!rows.length) {
            var emptyTr = document.createElement('tr');
            var emptyTd = document.createElement('td');
            emptyTd.colSpan = 7;
            emptyTd.className = 'rpt-empty';
            emptyTd.textContent = 'رکوردی یافت نشد.';
            emptyTr.appendChild(emptyTd);
            tbody.appendChild(emptyTr);

            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'rpt-data-row';

            var tdTime = document.createElement('td');
            tdTime.setAttribute('data-label', 'زمان');
            tdTime.textContent = row.performed_at_fa || '—';
            tr.appendChild(tdTime);

            var tdAdmin = document.createElement('td');
            tdAdmin.setAttribute('data-label', 'ادمین');
            var adminStack = document.createElement('div');
            adminStack.className = 'rpt-cell-stack';
            var adminName = document.createElement('span');
            adminName.textContent = row.admin_name || '—';
            adminStack.appendChild(adminName);
            var adminUser = document.createElement('span');
            adminUser.className = 'rpt-val-ltr';
            adminUser.textContent = row.admin_username_fa || row.admin_username || '';
            adminStack.appendChild(adminUser);
            tdAdmin.appendChild(adminStack);
            tr.appendChild(tdAdmin);

            var tdType = document.createElement('td');
            tdType.setAttribute('data-label', 'نوع');
            tdType.textContent = row.action_label || '—';
            tr.appendChild(tdType);

            var tdDesc = document.createElement('td');
            tdDesc.setAttribute('data-label', 'شرح');
            tdDesc.textContent = row.description || '—';
            tr.appendChild(tdDesc);

            var tdPath = document.createElement('td');
            tdPath.setAttribute('data-label', 'مسیر');
            tdPath.className = 'rpt-val-ltr';
            tdPath.textContent = row.url_path || row.route_name || '—';
            tr.appendChild(tdPath);

            var tdIp = document.createElement('td');
            tdIp.setAttribute('data-label', 'IP');
            tdIp.className = 'rpt-val-ltr';
            tdIp.textContent = row.ip_fa || row.ip_address || '—';
            tr.appendChild(tdIp);

            var tdDevice = document.createElement('td');
            tdDevice.setAttribute('data-label', 'دستگاه');
            var deviceStack = document.createElement('div');
            deviceStack.className = 'rpt-cell-stack';
            var deviceType = document.createElement('span');
            deviceType.textContent = row.device_type_fa || '—';
            deviceStack.appendChild(deviceType);
            if (row.browser && row.browser !== '—') {
                var browserSpan = document.createElement('span');
                browserSpan.textContent = row.browser;
                deviceStack.appendChild(browserSpan);
            }
            tdDevice.appendChild(deviceStack);
            tr.appendChild(tdDevice);

            tbody.appendChild(tr);
        });
    }

    function applyClientFilters() {
        var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        if (q === '') {
            renderRows(allRows);

            return;
        }
        var filtered = allRows.filter(function (row) {
            var blob = [
                row.admin_name,
                row.admin_username,
                row.description,
                row.route_name,
                row.url_path,
                row.action_label,
            ]
                .join(' ')
                .toLowerCase();

            return blob.indexOf(q) !== -1;
        });
        renderRows(filtered);
    }

    function loadData() {
        if (!cfg.adminActivityDataUrl) {
            return;
        }
        var url = new URL(cfg.adminActivityDataUrl, window.location.origin);
        if (fromInput && String(fromInput.value || '').trim()) {
            url.searchParams.set('from_jdate', String(fromInput.value).trim());
        }
        if (toInput && String(toInput.value || '').trim()) {
            url.searchParams.set('to_jdate', String(toInput.value).trim());
        }
        if (adminIdInput && String(adminIdInput.value || '').trim()) {
            url.searchParams.set('admin_id', String(adminIdInput.value).trim());
        }
        if (actionSelect && String(actionSelect.value || '')) {
            url.searchParams.set('action', String(actionSelect.value));
        }
        if (searchInput && String(searchInput.value || '').trim()) {
            url.searchParams.set('q', String(searchInput.value).trim());
        }

        serverSearch = searchInput ? String(searchInput.value || '').trim() : '';
        serverAction = actionSelect ? String(actionSelect.value || '') : '';
        serverAdminId = adminIdInput ? String(adminIdInput.value || '').trim() : '';

        if (meta) {
            meta.textContent = 'در حال دریافت…';
        }
        tbody.replaceChildren();
        var loadingTr = document.createElement('tr');
        var loadingTd = document.createElement('td');
        loadingTd.colSpan = 7;
        loadingTd.className = 'rpt-empty';
        loadingTd.textContent = 'در حال بارگذاری…';
        loadingTr.appendChild(loadingTd);
        tbody.appendChild(loadingTr);

        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }

                return r.json();
            })
            .then(function (data) {
                allRows = Array.isArray(data.rows) ? data.rows : [];
                var m = data.meta || {};
                if (meta) {
                    meta.textContent =
                        'بازه: ' +
                        (m.from_jdate || '—') +
                        ' تا ' +
                        (m.to_jdate || '—') +
                        ' — ' +
                        rptFaNum(m.count || allRows.length) +
                        ' رکورد';
                }
                if (searchInput && String(searchInput.value || '').trim() === serverSearch) {
                    applyClientFilters();
                } else {
                    renderRows(allRows);
                }
                rptUpdateExportUrl();
            })
            .catch(function () {
                if (meta) {
                    meta.textContent = 'خطا در دریافت اطلاعات.';
                }
                tbody.replaceChildren();
                var errTr = document.createElement('tr');
                var errTd = document.createElement('td');
                errTd.colSpan = 7;
                errTd.className = 'rpt-empty';
                errTd.textContent = 'خطا در دریافت اطلاعات.';
                errTr.appendChild(errTd);
                tbody.appendChild(errTr);
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData();
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (serverSearch !== '' && String(searchInput.value || '').trim() !== serverSearch) {
                applyClientFilters();
            } else if (allRows.length) {
                applyClientFilters();
            }
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadData();
            }
        });
    }

    if (adminSearchInput) {
        adminSearchInput.addEventListener('input', function () {
            if (adminIdInput) {
                adminIdInput.value = '';
            }
            syncAdminClearBtn();
            var term = String(adminSearchInput.value || '').trim();
            clearTimeout(adminSearchTimer);
            if (term.length < 1) {
                hideAdminSuggest();

                return;
            }
            adminSearchTimer = setTimeout(function () {
                fetchAdminSuggestions(term);
            }, 280);
        });
        adminSearchInput.addEventListener('focus', function () {
            var term = String(adminSearchInput.value || '').trim();
            if (term.length > 0 && (!adminIdInput || !String(adminIdInput.value || '').trim())) {
                fetchAdminSuggestions(term);
            }
        });
    }

    if (adminClearBtn) {
        adminClearBtn.addEventListener('click', function () {
            clearAdminFilter();
            loadData();
        });
    }

    if (actionSelect) {
        actionSelect.addEventListener('change', function () {
            loadData();
        });
    }

    document.addEventListener('click', function (e) {
        if (!adminSuggest || adminSuggest.hidden) {
            return;
        }
        var picker = adminSearchInput ? adminSearchInput.closest('.rpt-customer-picker') : null;
        if (picker && !picker.contains(e.target)) {
            hideAdminSuggest();
        }
    });

    if (fromInput) {
        fromInput.addEventListener('change', rptUpdateExportUrl);
    }
    if (toInput) {
        toInput.addEventListener('change', rptUpdateExportUrl);
    }

    document.querySelectorAll('[data-rpt-open="admin-activity"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal();
        });
    });

    overlay.querySelectorAll('[data-rpt-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    return { openModal: openModal, closeModal: closeModal, overlay: overlay };
}

function rptInitReportsPage() {
    var cfg = rptParseConfig();
    var memberLoans = rptInitMemberLoansReport(cfg);
    var installmentDue = rptInitInstallmentDueReport(cfg);
    var depositsByDate = rptInitDepositsByDateReport(cfg);
    var settledMembers = rptInitSettledMembersReport(cfg);
    var walletTransactions = rptInitWalletTransactionsByDateReport(cfg);
    var loanGuarantees = rptInitLoanGuaranteesReport(cfg);
    var loanInterestFees = rptInitLoanInterestFeesReport(cfg);
    var adminActivity = rptInitAdminActivityReport(cfg);
    var reportRegistry = {
        'member-loans-by-date': memberLoans,
        'installment-due-by-date': installmentDue,
        'deposits-by-date': depositsByDate,
        'settled-members': settledMembers,
        'wallet-transactions-by-date': walletTransactions,
        'loan-guarantees': loanGuarantees,
        'loan-interest-fees': loanInterestFees,
        'admin-activity': adminActivity,
    };
    rptBindReportCardOpens(reportRegistry);
    rptAutoOpenFromQuery(reportRegistry);
    rptBindReportEscapeHandlers([
        memberLoans,
        installmentDue,
        depositsByDate,
        settledMembers,
        walletTransactions,
        loanGuarantees,
        loanInterestFees,
        adminActivity,
    ]);
    rptInitQuickSms(cfg);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rptInitReportsPage);
} else {
    rptInitReportsPage();
}
