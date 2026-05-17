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
            finalStack.className = 'rpt-cell-stack';
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

function rptInitReportsPage() {
    var cfg = rptParseConfig();
    var memberLoans = rptInitMemberLoansReport(cfg);
    var installmentDue = rptInitInstallmentDueReport(cfg);
    var depositsByDate = rptInitDepositsByDateReport(cfg);
    var settledMembers = rptInitSettledMembersReport(cfg);
    var walletTransactions = rptInitWalletTransactionsByDateReport(cfg);
    rptBindReportEscapeHandlers([memberLoans, installmentDue, depositsByDate, settledMembers, walletTransactions]);
    rptInitQuickSms(cfg);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rptInitReportsPage);
} else {
    rptInitReportsPage();
}
