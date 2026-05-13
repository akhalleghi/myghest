    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        (function () {
            var lrqListBase = @json($lrqHttpResourceBase ?? rtrim(route('admin.loan-requests.index'), '/'));
            var lrqCustomersIndex = @json(route('admin.customers.index'));
            var lrqStatusDefIndex = @json(rtrim(route('admin.loan-request-status-definitions.index'), '/'));
            var lrqStatusDefStore = @json(route('admin.loan-request-status-definitions.store'));

            function lrqEditContextUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/edit-context';
            }
            function lrqConvertPreviewUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/convert-preview';
            }
            function lrqConvertUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id) + '/convert';
            }
            function lrqStatusDefItemUrl(id) {
                return lrqStatusDefIndex + '/' + encodeURIComponent(id);
            }
            function csrfToken() {
                var m = document.querySelector('meta[name="csrf-token"]');
                return m ? m.getAttribute('content') || '' : '';
            }
            function formatNum(n) {
                var x = parseInt(String(n || '0'), 10);
                if (isNaN(x)) x = 0;
                return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            /**
             * SweetAlert2 یک thenable شخصی برمی‌گرداند که .catch / .finally ندارد.
             * این helper هر thenable را در یک Promise واقعی wrap می‌کند و هرگز reject نمی‌شود
             * تا فراخوانی .catch / .finally روی نتیجه همیشه امن باشد.
             */
            function wrapSwalThenable(p) {
                if (!p || typeof p.then !== 'function') return Promise.resolve();
                return new Promise(function (resolve) {
                    try {
                        p.then(
                            function (v) { resolve(v); },
                            function () { resolve(); }
                        );
                    } catch (e) {
                        resolve();
                    }
                });
            }
            /** همیشه Promise؛ در غیر این صورت خطای sync در then به‌اشتباه «ارتباط با سرور» گزارش می‌شود. */
            function adminSwalAsPromise(method, firstArg) {
                if (!window.AdminSwal || !AdminSwal[method] || typeof AdminSwal[method] !== 'function') {
                    return Promise.resolve();
                }
                try {
                    return wrapSwalThenable(AdminSwal[method].call(AdminSwal, firstArg));
                } catch (e) {
                    return Promise.resolve();
                }
            }
            /**
             * خواندن بدنهٔ JSON پاسخ fetch؛ هرگز reject نمی‌کند تا با خطای شبکه قاطی نشود.
             */
            function readFetchJsonBody(response) {
                return response.text().then(function (text) {
                    var body = {};
                    try {
                        body = text ? JSON.parse(text) : {};
                    } catch (eParse) {
                        body = {};
                    }
                    return { ok: response.ok, status: response.status, body: body };
                }).catch(function (eRead) {
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('readFetchJsonBody', eRead);
                    }
                    var st = response && typeof response.status === 'number' ? response.status : 0;
                    return { ok: false, status: st, body: {} };
                });
            }
            function safeAdminMessage(val, fallback) {
                if (val == null || val === '') return fallback;
                try {
                    if (typeof val === 'string') return val;
                    if (typeof val === 'number' && isFinite(val)) return String(val);
                    if (typeof val === 'boolean') return val ? 'بله' : 'خیر';
                } catch (e) { /* noop */ }
                return fallback;
            }
            /**
             * پس از پاسخ موفق سرور: پر کردن مدال، SweetAlert، بستن مدال.
             * همیشه Promiseای برمی‌گرداند که در نهایت fulfilled می‌شود تا catchٔ عمومی «ارتباط با سرور» اشتباهی اجرا نشود.
             */
            function completeLoanRequestSaveAfterOk(res) {
                var fillModalErr = null;
                try {
                    if (res.body && res.body.edit_context) {
                        fillEditModal(res.body.edit_context);
                    }
                } catch (eFill) {
                    fillModalErr = eFill;
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('fillEditModal after save', eFill);
                    }
                }
                var msgOk = safeAdminMessage(res.body && res.body.message, 'ذخیره شد.');
                var chain = adminSwalAsPromise('success', msgOk).catch(function () {});
                if (res.body && res.body.sms_note) {
                    chain = chain.then(function () {
                        return adminSwalAsPromise('warning', safeAdminMessage(res.body.sms_note, ''));
                    }).catch(function () {});
                }
                if (fillModalErr) {
                    chain = chain.then(function () {
                        return adminSwalAsPromise(
                            'warning',
                            'ذخیره در سرور انجام شد اما به‌روزرسانی نمایش مدال ناموفق بود. در صورت نیاز صفحه را تازه‌سازی کنید.'
                        );
                    }).catch(function () {});
                }
                return chain
                    .then(
                        function () {
                            try {
                                closeEditModal();
                            } catch (eClose) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('closeEditModal after save', eClose);
                                }
                            }
                            // پس از موفقیت سرور و بسته شدن مدال، جدول با reload به‌روزرسانی می‌شود.
                            // query string (فیلترها/تاریخ‌ها/صفحه‌بندی) خودبه‌خود حفظ می‌شود.
                            try { window.location.reload(); } catch (eR) { /* noop */ }
                        },
                        function (errSwal) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('loan save swal chain', errSwal);
                            }
                            try {
                                closeEditModal();
                            } catch (eClose2) { /* noop */ }
                            try { window.location.reload(); } catch (eR2) { /* noop */ }
                        }
                    )
                    .catch(function (errFinal) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('loan save ui tail', errFinal);
                        }
                        return null;
                    });
            }
            function parseDigits(s) {
                return parseInt(String(s || '').replace(/[^\d]/g, '') || '0', 10) || 0;
            }
            function lrqResourceUrl(id) {
                return lrqListBase + '/' + encodeURIComponent(id);
            }
            function escapeHtmlText(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function initPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                window.jQuery('#lrq-from-jdate, #lrq-to-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
            }
            if (window.jQuery) {
                window.jQuery(function () { initPickers(); });
            } else {
                initPickers();
            }

            function lrqUniqueRowChecks() {
                var seen = {};
                var out = [];
                document.querySelectorAll('[data-lrq-row-check]').forEach(function (cb) {
                    var id = cb.getAttribute('data-lrq-id');
                    if (!id || seen[id]) return;
                    seen[id] = true;
                    out.push(cb);
                });
                return out;
            }

            function lrqSyncRowChecks(sourceCb) {
                var id = sourceCb.getAttribute('data-lrq-id');
                if (!id) return;
                var v = !!sourceCb.checked;
                document.querySelectorAll('[data-lrq-row-check][data-lrq-id="' + id + '"]').forEach(function (cb) {
                    if (cb !== sourceCb) cb.checked = v;
                });
            }

            var master = document.getElementById('lrq-select-all');
            if (master) {
                master.addEventListener('change', function () {
                    var on = !!master.checked;
                    document.querySelectorAll('[data-lrq-row-check]').forEach(function (cb) {
                        cb.checked = on;
                    });
                });
            }
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.matches || !t.matches('[data-lrq-row-check]')) return;
                lrqSyncRowChecks(t);
                var m = document.getElementById('lrq-select-all');
                if (!m) return;
                var rows = lrqUniqueRowChecks();
                if (!rows.length) {
                    m.checked = false;
                    return;
                }
                var every = true;
                for (var i = 0; i < rows.length; i++) {
                    if (!rows[i].checked) { every = false; break; }
                }
                m.checked = every;
            });

            var editOverlay = document.getElementById('lrq-edit-overlay');
            var sdefOverlay = document.getElementById('lrq-sdef-overlay');
            var statusLogOverlay = document.getElementById('lrq-statuslog-overlay');
            var smsLogOverlay = document.getElementById('lrq-smslog-overlay');
            var editLoading = document.getElementById('lrq-edit-loading');
            var editFormWrap = document.getElementById('lrq-edit-form-wrap');
            var editClose = document.getElementById('lrq-edit-close');
            var sdefClose = document.getElementById('lrq-sdef-close');
            var statusLogClose = document.getElementById('lrq-statuslog-close');
            var smsLogClose = document.getElementById('lrq-smslog-close');
            var sdefListEl = document.getElementById('lrq-sdef-list');
            var sdefAddBtn = document.getElementById('lrq-sdef-add-row');
            var btnOpenStatusDefs = document.getElementById('lrq-open-status-defs');
            var lrqEditCtx = { customerId: 0, requestId: 0 };
            var lrqStatusLogSearchTimer = null;

            function syncLrqModalScrollLock() {
                var convertOv = document.getElementById('lrq-convert-overlay');
                var anyOpen =
                    (editOverlay && !editOverlay.hidden) ||
                    (sdefOverlay && !sdefOverlay.hidden) ||
                    (statusLogOverlay && !statusLogOverlay.hidden) ||
                    (smsLogOverlay && !smsLogOverlay.hidden) ||
                    (convertOv && !convertOv.hidden);
                document.documentElement.style.overflow = anyOpen ? 'hidden' : '';
            }

            function setOverlay(open, el) {
                if (!el) return;
                el.hidden = !open;
                el.setAttribute('aria-hidden', open ? 'false' : 'true');
                syncLrqModalScrollLock();
            }

            function closeEditModal() {
                setOverlay(false, editOverlay);
            }
            function closeSdefModal() {
                setOverlay(false, sdefOverlay);
            }
            function closeStatusLogModal() {
                setOverlay(false, statusLogOverlay);
            }
            function closeSmsLogModal() {
                setOverlay(false, smsLogOverlay);
            }

            function openCustomersUrl(params) {
                var u = new URL(lrqCustomersIndex, window.location.origin);
                Object.keys(params).forEach(function (k) {
                    u.searchParams.set(k, params[k]);
                });
                window.location.href = u.toString();
            }

            function fillEditModal(data) {
                function lrqEl(id) {
                    return document.getElementById(id);
                }
                function setLrqText(id, s) {
                    var n = lrqEl(id);
                    if (n) n.textContent = s;
                }
                function setLrqValue(id, s) {
                    var n = lrqEl(id);
                    if (n) n.value = s;
                }
                var c = data.customer || {};
                var r = data.request || {};
                lrqEditCtx.customerId = parseInt(String(c.id || 0), 10);
                lrqEditCtx.requestId = parseInt(String(r.id || 0), 10);
                // نگه‌داری آخرین payload سرور برای دسترسی سایر مدال‌ها (مثل «تبدیل به وام»).
                try { lrqEditCtx._lastData = data; } catch (eCtxStash) { /* noop */ }

                setLrqText('lrq-edit-cust-name', c.full_name || '—');
                setLrqText('lrq-edit-cust-username', c.username || '—');
                setLrqText('lrq-edit-national', c.national_id_fa || '—');
                setLrqText('lrq-edit-mobile', c.mobile_fa || '—');
                setLrqText('lrq-edit-father', c.father_name_fa || '—');
                setLrqText('lrq-edit-loan-count', String(c.loan_count != null ? c.loan_count : '—'));
                setLrqText('lrq-edit-loans-total', (c.loans_total_fa || '—') + ' تومان');
                setLrqText('lrq-edit-remain', (c.installments_remaining_fa || '—') + ' تومان');
                setLrqText('lrq-edit-membership', c.membership_at_fa || '—');
                setLrqText('lrq-edit-last-login', c.last_login_fa || '—');
                setLrqText('lrq-edit-wallet', (c.wallet_balance_fa || '0') + ' تومان');
                setLrqText('lrq-edit-good', c.good_standing_label || 'نامشخص');

                setLrqText('lrq-edit-req-date', (r.submitted_date_fa || '—') + ' ' + (r.submitted_time_fa || ''));
                setLrqText('lrq-edit-req-status-label', r.status_label || '—');

                var loanSel = lrqEl('lrq-edit-loan-type');
                if (loanSel) {
                    loanSel.innerHTML = '';
                    (data.loan_types || []).forEach(function (lt) {
                        var o = document.createElement('option');
                        o.value = String(lt.id);
                        o.textContent = lt.label || ('#' + lt.id);
                        loanSel.appendChild(o);
                    });
                    loanSel.value = String(r.loan_type_id || '');
                }

                setLrqValue('lrq-edit-amount', formatNum(r.amount_toman));
                setLrqValue('lrq-edit-inst-count', formatNum(r.installments_count));
                setLrqValue('lrq-edit-inst-gap', formatNum(r.installment_interval_count));
                setLrqValue('lrq-edit-inst-amt', formatNum(r.installment_amount_toman));
                setLrqText('lrq-edit-gap-unit-hint', 'واحد فاصله اقساط: ' + (r.installment_interval_unit_fa || '—'));

                var stSel = lrqEl('lrq-edit-status');
                if (stSel) {
                    stSel.innerHTML = '';
                    (data.status_options || []).forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = s.code;
                        o.textContent = s.title;
                        stSel.appendChild(o);
                    });
                    stSel.value = r.status || '';
                }

                var exAd = document.getElementById('lrq-edit-expert-admin');
                if (exAd) exAd.value = r.expert_note != null ? String(r.expert_note) : '';
                var exCu = document.getElementById('lrq-edit-expert-customer');
                if (exCu) exCu.value = r.expert_note_customer != null ? String(r.expert_note_customer) : '';
                var descEl = document.getElementById('lrq-edit-description');
                if (descEl) descEl.textContent = (r.description != null && String(r.description).trim() !== '') ? String(r.description) : '—';
                var cbDoc = document.getElementById('lrq-edit-doc-received');
                if (cbDoc) cbDoc.checked = !!r.documents_physical_received;
                var cbSms = document.getElementById('lrq-edit-send-sms');
                if (cbSms) cbSms.checked = false;

                var loanManageA = lrqEl('lrq-edit-open-loan-manage');
                if (loanManageA) {
                    if (lrqEditCtx.customerId) {
                        var u2 = new URL(lrqCustomersIndex, window.location.origin);
                        u2.searchParams.set('open_loan_manage', '1');
                        u2.searchParams.set('customer_id', String(lrqEditCtx.customerId));
                        loanManageA.href = u2.toString();
                    } else {
                        loanManageA.href = '#';
                    }
                }

                var editCustBtn = lrqEl('lrq-edit-open-customer-form');
                if (editCustBtn) {
                    editCustBtn.onclick = function () {
                        if (!lrqEditCtx.customerId) return;
                        openCustomersUrl({ open_customer_edit: '1', customer_id: String(lrqEditCtx.customerId) });
                    };
                    editCustBtn.disabled = !lrqEditCtx.customerId;
                }

                lrqEditCtx.documents = Array.isArray(data.documents) ? data.documents.slice() : [];
                lrqEditCtx.document_review_statuses = Array.isArray(data.document_review_statuses) ? data.document_review_statuses.slice() : [];
                renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses);

                // وضعیت دکمه «تبدیل به وام»: اگر قبلاً تبدیل شده، غیرفعال می‌شود و توضیح مناسب نمایش می‌یابد.
                var convertBtn = document.getElementById('lrq-edit-convert-loan');
                if (convertBtn) {
                    if (r.is_converted_to_loan) {
                        convertBtn.disabled = true;
                        convertBtn.title = 'این درخواست قبلاً به وام تبدیل شده است' + (r.converted_at_fa ? ' — در ' + r.converted_at_fa : '');
                        convertBtn.setAttribute('aria-disabled', 'true');
                    } else {
                        convertBtn.disabled = false;
                        convertBtn.title = 'تبدیل این درخواست به یک پروندهٔ وام برای مشتری';
                        convertBtn.removeAttribute('aria-disabled');
                    }
                }

                // نوار قرمز بزرگ بالای مدال: فقط در صورت تبدیل‌شدن این درخواست به وام نمایش داده می‌شود.
                var convertedBanner = document.getElementById('lrq-edit-converted-banner');
                var convertedMeta = document.getElementById('lrq-edit-converted-meta');
                if (convertedBanner) {
                    if (r.is_converted_to_loan) {
                        convertedBanner.hidden = false;
                        if (convertedMeta) {
                            convertedMeta.textContent = r.converted_at_fa ? '(در ' + r.converted_at_fa + ')' : '';
                        }
                    } else {
                        convertedBanner.hidden = true;
                        if (convertedMeta) convertedMeta.textContent = '';
                    }
                }
            }

            function lrqAdminDocResourceUrl(rid, docId) {
                return lrqResourceUrl(rid) + '/documents/' + encodeURIComponent(docId);
            }

            function lrqDocAdminCardHtml(doc, statusDefs) {
                var desc = (doc.description != null && String(doc.description).trim() !== '')
                    ? escapeHtmlText(String(doc.description))
                    : 'توضیحاتی ثبت نشده است.';
                var url = escapeHtmlText(doc.file_url || '');
                var previewBlock = '';
                if (doc.is_image && doc.file_url) {
                    previewBlock =
                        '<div class="lrq-doc-admin-preview"><img src="' + url + '" alt="" loading="lazy"/>' +
                        '<div><a class="lrq-doc-admin-dl" href="' + url + '" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود</a></div></div>';
                } else {
                    previewBlock =
                        '<div class="lrq-doc-admin-preview">' +
                        '<a class="lrq-doc-admin-dl" href="' + url + '" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-download" aria-hidden="true"></i> دانلود / مشاهده</a></div>';
                }
                var seg = '';
                (statusDefs || []).forEach(function (st) {
                    var c = String(st.code || '');
                    var active = String(doc.review_status || '') === c ? ' is-active' : '';
                    seg += '<button type="button" class="lrq-doc-status-btn' + active + '" data-code="' + escapeHtmlText(c) + '">' + escapeHtmlText(String(st.label || c)) + '</button>';
                });
                var ex = doc.expert_note != null ? String(doc.expert_note) : '';
                return (
                    '<div class="lrq-doc-admin-card" data-lrq-doc-id="' + escapeHtmlText(String(doc.id)) + '">' +
                    '<div class="lrq-doc-admin-card-head">' + escapeHtmlText(String(doc.document_title || 'مدرک')) +
                    ' <span class="lrq-doc-admin-chip">(' + escapeHtmlText(String(doc.review_status_label || '')) + ')</span></div>' +
                    '<div class="lrq-doc-admin-body">' +
                    '<div class="lrq-doc-admin-cust"><strong>توضیحات مشتری</strong><p>' + desc + '</p></div>' +
                    previewBlock +
                    '<div class="lrq-doc-status-wrap"><span class="lrq-doc-status-label">تغییر وضعیت</span><div class="lrq-doc-status-seg">' + seg + '</div></div>' +
                    '<div class="lrq-doc-expert-wrap"><span class="lrq-doc-status-label">نظر کارشناس (مشتری نیز می‌بیند)</span><textarea class="lrq-doc-expert-note" rows="3">' + escapeHtmlText(ex) + '</textarea></div>' +
                    '<div class="lrq-doc-admin-actions">' +
                    '<button type="button" class="lrq-doc-admin-del" title="حذف این مدرک از درخواست" aria-label="حذف مدرک"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
                    '<button type="button" class="lrq-doc-admin-save">ثبت</button>' +
                    '</div></div></div>'
                );
            }

            function renderLrqAdminDocuments(docs, statusDefs) {
                var host = document.getElementById('lrq-edit-docs-host');
                if (!host) return;
                if (!docs || !docs.length) {
                    host.innerHTML = '<p class="lrq-muted" style="margin:0;font-size:0.8rem;">مدرکی برای این درخواست ثبت نشده است.</p>';
                    return;
                }
                var html = '';
                for (var i = 0; i < docs.length; i++) {
                    html += lrqDocAdminCardHtml(docs[i], statusDefs);
                }
                host.innerHTML = html;
            }

            function loadStatusLogsTable() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var qIn = document.getElementById('lrq-statuslog-q');
                var q = qIn ? String(qIn.value || '').trim() : '';
                var url = lrqResourceUrl(rid) + '/status-logs' + (q ? ('?q=' + encodeURIComponent(q)) : '');
                var tbody = document.getElementById('lrq-statuslog-tbody');
                var empty = document.getElementById('lrq-statuslog-empty');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="4" class="lrq-muted">در حال بارگذاری…</td></tr>';
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var rows = data.logs || [];
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (empty) empty.hidden = false;
                        return;
                    }
                    if (empty) empty.hidden = true;
                    rows.forEach(function (row) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + escapeHtmlText(row.user_label) + '</td>' +
                            '<td>' + escapeHtmlText(row.created_at_fa) + '</td>' +
                            '<td>' + escapeHtmlText(row.from_status_customer) + '</td>' +
                            '<td>' + escapeHtmlText(row.to_status_customer) + '</td>';
                        tbody.appendChild(tr);
                    });
                }).catch(function () {
                    tbody.innerHTML = '<tr><td colspan="4" class="lrq-muted">بارگذاری ناموفق بود.</td></tr>';
                });
            }

            function openStatusLogModal() {
                if (!statusLogOverlay || !lrqEditCtx.requestId) return;
                var qIn = document.getElementById('lrq-statuslog-q');
                if (qIn) qIn.value = '';
                setOverlay(true, statusLogOverlay);
                loadStatusLogsTable();
            }

            function loadSmsLogsTable() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var url = lrqResourceUrl(rid) + '/status-sms-logs';
                var tbody = document.getElementById('lrq-smslog-tbody');
                var empty = document.getElementById('lrq-smslog-empty');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="7" class="lrq-muted">در حال بارگذاری…</td></tr>';
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    var rows = data.logs || [];
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (empty) empty.hidden = false;
                        return;
                    }
                    if (empty) empty.hidden = true;
                    rows.forEach(function (row) {
                        var tr = document.createElement('tr');
                        var st = escapeHtmlText(row.status_label || '');
                        var btn = '<button type="button" class="lrq-log-mini" data-lrq-sms-resend="' + String(row.id) + '">ارسال مجدد</button>';
                        tr.innerHTML =
                            '<td>' + escapeHtmlText(row.sms_panel) + '</td>' +
                            '<td>' + st + '</td>' +
                            '<td>' + escapeHtmlText(row.sent_at_fa) + '</td>' +
                            '<td class="lrq-log-msg">' + escapeHtmlText(row.message_text) + '</td>' +
                            '<td>' + escapeHtmlText(row.recipient) + '</td>' +
                            '<td>' + escapeHtmlText(row.type_label || row.type) + '</td>' +
                            '<td>' + btn + '</td>';
                        tbody.appendChild(tr);
                    });
                }).catch(function () {
                    tbody.innerHTML = '<tr><td colspan="7" class="lrq-muted">بارگذاری ناموفق بود.</td></tr>';
                });
            }

            function openSmsLogModal() {
                if (!smsLogOverlay || !lrqEditCtx.requestId) return;
                setOverlay(true, smsLogOverlay);
                loadSmsLogsTable();
            }

            function saveLoanRequestEdit() {
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var loanSel = document.getElementById('lrq-edit-loan-type');
                var stSel = document.getElementById('lrq-edit-status');
                var payload = {
                    loan_type_id: parseInt(String(loanSel && loanSel.value ? loanSel.value : '0'), 10),
                    amount_toman: parseDigits(document.getElementById('lrq-edit-amount') && document.getElementById('lrq-edit-amount').value),
                    installments_count: parseDigits(document.getElementById('lrq-edit-inst-count') && document.getElementById('lrq-edit-inst-count').value),
                    installment_interval_count: parseDigits(document.getElementById('lrq-edit-inst-gap') && document.getElementById('lrq-edit-inst-gap').value),
                    status: stSel ? String(stSel.value || '') : '',
                    expert_note: (document.getElementById('lrq-edit-expert-admin') && document.getElementById('lrq-edit-expert-admin').value) || '',
                    expert_note_customer: (document.getElementById('lrq-edit-expert-customer') && document.getElementById('lrq-edit-expert-customer').value) || '',
                    documents_physical_received: !!(document.getElementById('lrq-edit-doc-received') && document.getElementById('lrq-edit-doc-received').checked),
                    send_status_sms: !!(document.getElementById('lrq-edit-send-sms') && document.getElementById('lrq-edit-send-sms').checked)
                };
                var btn = document.getElementById('lrq-edit-save');
                if (btn) btn.disabled = true;
                // خطای واقعی شبکه فقط هنگامی باید «ارتباط با سرور» نمایش دهد که خود fetch reject شود.
                // به همین دلیل onRejected را روی همان fetch می‌بندیم و خطاهای post-success در داخل
                // try/catch داخلی نگه داشته می‌شوند تا اشتباهاً به‌عنوان «خطای شبکه» گزارش نشوند.
                fetch(lrqResourceUrl(rid), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('saveLoanRequestEdit transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return;
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'ذخیره انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            return;
                        }
                        try {
                            return completeLoanRequestSaveAfterOk(res);
                        } catch (eHandle) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('saveLoanRequestEdit handleResponse', eHandle);
                            }
                            try { closeEditModal(); } catch (eClose) { /* noop */ }
                            if (window.AdminSwal && AdminSwal.warning) {
                                AdminSwal.warning('تغییرات در سرور ذخیره شد، اما در نمایش نتیجه خطایی رخ داد. در صورت نیاز صفحه را تازه‌سازی کنید.');
                            }
                        }
                    })
                    .then(null, function (eUnexpected) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('saveLoanRequestEdit post-success', eUnexpected);
                        }
                        // به‌عمد پیام «ارتباط با سرور» نشان نمی‌دهیم؛ سرور موفق پاسخ داده و تغییرات ذخیره شده‌اند.
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                    });
            }

            function refreshMainStatusSelect() {
                var stSel = document.getElementById('lrq-edit-status');
                if (!stSel || editOverlay.hidden) return;
                var keep = stSel.value;
                fetch(lrqStatusDefIndex, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    stSel.innerHTML = '';
                    (data.definitions || []).forEach(function (d) {
                        var o = document.createElement('option');
                        o.value = d.code;
                        o.textContent = d.title;
                        stSel.appendChild(o);
                    });
                    stSel.value = keep;
                }).catch(function () { /* noop */ });
            }

            function openLrqEditModal(requestId) {
                if (!editOverlay) return;
                editLoading.hidden = false;
                editFormWrap.hidden = true;
                setOverlay(true, editOverlay);
                fetch(lrqEditContextUrl(requestId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    fillEditModal(data);
                    editLoading.hidden = true;
                    editFormWrap.hidden = false;
                }).catch(function () {
                    closeEditModal();
                    if (window.AdminSwal && AdminSwal.error) {
                        AdminSwal.error('بارگذاری اطلاعات درخواست ناموفق بود.');
                    }
                });
            }

            document.addEventListener('click', function (e) {
                var t = e.target;
                var openBtn = t.closest && t.closest('[data-lrq-open-edit]');
                if (openBtn) {
                    e.preventDefault();
                    var rid = parseInt(openBtn.getAttribute('data-lrq-open-edit') || '0', 10);
                    if (rid) openLrqEditModal(rid);
                }
            });

            function lrqRemoveRequestRowsFromDom(rid) {
                var key = String(rid);
                document.querySelectorAll('[data-lrq-row-check][data-lrq-id="' + key + '"]').forEach(function (cb) {
                    var tr = cb.closest('tr');
                    if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
                    var card = cb.closest('article.lrq-card');
                    if (card && card.parentNode) card.parentNode.removeChild(card);
                });
            }

            function lrqMaybeShowEmptyAfterDelete() {
                var deskBody = document.querySelector('.lrq-desktop-only .lrq-tbl tbody');
                if (deskBody && !deskBody.querySelector('tr')) {
                    var emptyTr = document.createElement('tr');
                    emptyTr.innerHTML = '<td colspan="6" class="lrq-empty">در این بازه تاریخ، درخواست وامی ثبت نشده است.</td>';
                    deskBody.appendChild(emptyTr);
                }
                var mobile = document.querySelector('.lrq-mobile-stack');
                if (mobile && !mobile.querySelector('article.lrq-card') && !mobile.querySelector('.lrq-card-empty')) {
                    var emptyDiv = document.createElement('div');
                    emptyDiv.className = 'lrq-card-empty';
                    emptyDiv.setAttribute('role', 'status');
                    emptyDiv.textContent = 'در این بازه تاریخ، درخواست وامی ثبت نشده است.';
                    mobile.appendChild(emptyDiv);
                }
                var master = document.getElementById('lrq-select-all');
                if (master) master.checked = false;
            }

            function performLrqDelete(rid) {
                // خطای واقعی شبکه فقط هنگامی باید «ارتباط با سرور برقرار نشد» نمایش دهد که خود fetch reject شود.
                // به همین دلیل onRejected را روی همان fetch می‌بندیم و منطق UI را در try/catch داخلی نگه می‌داریم
                // تا یک خطای synchronous در پردازش پاسخ موفق، اشتباهاً به‌عنوان «خطای شبکه» گزارش نشود.
                fetch(lrqResourceUrl(rid), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('performLrqDelete transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return;
                        if (!res.ok) {
                            var err = safeAdminMessage(res.body && res.body.message, 'حذف انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(err);
                            return;
                        }
                        var msgOk = safeAdminMessage(res.body && res.body.message, 'حذف شد.');
                        try {
                            if (editOverlay && !editOverlay.hidden && lrqEditCtx.requestId === rid) {
                                try { closeEditModal(); } catch (eClose) { /* noop */ }
                            }
                            lrqRemoveRequestRowsFromDom(rid);
                            lrqMaybeShowEmptyAfterDelete();
                        } catch (eUi) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('performLrqDelete UI tail', eUi);
                            }
                        }
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success(msgOk);
                    });
            }

            document.addEventListener('click', function (e) {
                var t = e.target;
                var delBtn = t.closest && t.closest('[data-lrq-delete]');
                if (!delBtn) return;
                e.preventDefault();
                var rid = parseInt(delBtn.getAttribute('data-lrq-delete') || '0', 10);
                if (!rid) return;
                var reqNo = delBtn.getAttribute('data-lrq-delete-no') || '';
                var confirmTitle = 'حذف درخواست وام' + (reqNo ? ' شماره ' + reqNo : '');
                var confirmText = 'با حذف این درخواست، تمام مدارک، فایل‌های پیوست و لاگ‌های تغییر وضعیت آن نیز حذف می‌شوند. این عمل قابل بازگشت نیست.';
                if (window.AdminSwal && AdminSwal.confirm) {
                    wrapSwalThenable(AdminSwal.confirm({
                        title: confirmTitle,
                        text: confirmText,
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف'
                    })).then(function (result) {
                        if (result && result.isConfirmed) performLrqDelete(rid);
                    });
                    return;
                }
                if (window.confirm(confirmTitle + '\n' + confirmText)) {
                    performLrqDelete(rid);
                }
            });

            if (editClose) editClose.addEventListener('click', closeEditModal);
            var loanManageNav = document.getElementById('lrq-edit-open-loan-manage');
            if (loanManageNav) {
                loanManageNav.addEventListener('click', function (e) {
                    if (!lrqEditCtx.customerId) e.preventDefault();
                });
            }
            if (editOverlay) {
                editOverlay.addEventListener('click', function (e) {
                    if (e.target === editOverlay) closeEditModal();
                });
            }
            if (editFormWrap) {
                editFormWrap.addEventListener('click', function (e) {
                    var t = e.target;
                    var stBtn = t.closest && t.closest('.lrq-doc-status-btn');
                    if (stBtn && editFormWrap.contains(stBtn)) {
                        var seg = stBtn.closest('.lrq-doc-status-seg');
                        if (!seg) return;
                        var btns = seg.querySelectorAll('.lrq-doc-status-btn');
                        for (var si = 0; si < btns.length; si++) btns[si].classList.remove('is-active');
                        stBtn.classList.add('is-active');
                        return;
                    }
                    var saveBtn = t.closest && t.closest('.lrq-doc-admin-save');
                    if (saveBtn && editFormWrap.contains(saveBtn)) {
                        e.preventDefault();
                        var card = saveBtn.closest('.lrq-doc-admin-card');
                        if (!card || !lrqEditCtx.requestId) return;
                        var docId = parseInt(card.getAttribute('data-lrq-doc-id') || '0', 10);
                        var act = card.querySelector('.lrq-doc-status-btn.is-active');
                        if (!act) {
                            if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('یک وضعیت را انتخاب کنید.');
                            return;
                        }
                        var code = act.getAttribute('data-code') || '';
                        var ta = card.querySelector('.lrq-doc-expert-note');
                        var note = ta ? String(ta.value || '') : '';
                        saveBtn.disabled = true;
                        fetch(lrqAdminDocResourceUrl(lrqEditCtx.requestId, docId), {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ review_status: code, expert_note: note })
                        }).then(function (r) {
                            return r.text().then(function (text) {
                                var body = {};
                                try { body = text ? JSON.parse(text) : {}; } catch (e2) {}
                                return { ok: r.ok, body: body };
                            });
                        }, function (errNet) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('docUpdate transport', errNet);
                            }
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط برقرار نشد.');
                            return null;
                        }).then(function (res) {
                            if (!res) return;
                            if (!res.ok) {
                                var msg = (res.body && res.body.message) ? String(res.body.message) : 'ذخیره نشد.';
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                                return;
                            }
                            var okMsg = (res.body && res.body.message) ? String(res.body.message) : 'ذخیره شد.';
                            try {
                                var d = res.body && res.body.document;
                                if (d && lrqEditCtx.documents) {
                                    lrqEditCtx.documents = lrqEditCtx.documents.map(function (row) {
                                        if (Number(row.id) !== Number(d.id)) return row;
                                        return Object.assign({}, row, {
                                            review_status: d.review_status,
                                            review_status_label: d.review_status_label,
                                            expert_note: d.expert_note
                                        });
                                    });
                                    renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses || []);
                                }
                            } catch (eUi) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('docUpdate UI tail', eUi);
                                }
                            }
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(okMsg);
                        }).finally(function () {
                            saveBtn.disabled = false;
                        });
                        return;
                    }
                    var delBtn = t.closest && t.closest('.lrq-doc-admin-del');
                    if (delBtn && editFormWrap.contains(delBtn)) {
                        e.preventDefault();
                        if (!window.confirm('این مدرک از درخواست حذف شود؟ در صورت حذف، کاربر دیگر ملزم به بارگذاری این فایل نیست (مگر خودش دوباره آپلود کند).')) return;
                        var card2 = delBtn.closest('.lrq-doc-admin-card');
                        if (!card2 || !lrqEditCtx.requestId) return;
                        var docId2 = parseInt(card2.getAttribute('data-lrq-doc-id') || '0', 10);
                        delBtn.disabled = true;
                        fetch(lrqAdminDocResourceUrl(lrqEditCtx.requestId, docId2), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        }).then(function (r) {
                            return r.text().then(function (text) {
                                var body = {};
                                try { body = text ? JSON.parse(text) : {}; } catch (e2) {}
                                return { ok: r.ok, body: body };
                            });
                        }, function (errNet) {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('docDelete transport', errNet);
                            }
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط برقرار نشد.');
                            return null;
                        }).then(function (res) {
                            if (!res) return;
                            if (!res.ok) {
                                var msg = (res.body && res.body.message) ? String(res.body.message) : 'حذف انجام نشد.';
                                if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                                return;
                            }
                            var okMsg = (res.body && res.body.message) ? String(res.body.message) : 'حذف شد.';
                            try {
                                if (res.body && res.body.edit_context) {
                                    fillEditModal(res.body.edit_context);
                                } else if (lrqEditCtx.documents) {
                                    lrqEditCtx.documents = lrqEditCtx.documents.filter(function (row) { return Number(row.id) !== docId2; });
                                    renderLrqAdminDocuments(lrqEditCtx.documents, lrqEditCtx.document_review_statuses || []);
                                }
                            } catch (eDelFill) {
                                if (typeof console !== 'undefined' && console.error) {
                                    console.error('fillEditModal after document delete', eDelFill);
                                }
                            }
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success(okMsg);
                        }).finally(function () {
                            delBtn.disabled = false;
                        });
                    }
                });
            }
            if (sdefClose) sdefClose.addEventListener('click', closeSdefModal);
            if (sdefOverlay) {
                sdefOverlay.addEventListener('click', function (e) {
                    if (e.target === sdefOverlay) closeSdefModal();
                });
            }

            if (statusLogClose) statusLogClose.addEventListener('click', closeStatusLogModal);
            if (smsLogClose) smsLogClose.addEventListener('click', closeSmsLogModal);
            if (statusLogOverlay) {
                statusLogOverlay.addEventListener('click', function (e) {
                    if (e.target === statusLogOverlay) closeStatusLogModal();
                });
            }
            if (smsLogOverlay) {
                smsLogOverlay.addEventListener('click', function (e) {
                    if (e.target === smsLogOverlay) closeSmsLogModal();
                });
            }

            var btnOpenStatusLog = document.getElementById('lrq-edit-open-status-log');
            if (btnOpenStatusLog) btnOpenStatusLog.addEventListener('click', openStatusLogModal);
            var btnOpenSmsLog = document.getElementById('lrq-edit-open-sms-log');
            if (btnOpenSmsLog) btnOpenSmsLog.addEventListener('click', openSmsLogModal);

            var btnSaveEdit = document.getElementById('lrq-edit-save');
            if (btnSaveEdit) btnSaveEdit.addEventListener('click', saveLoanRequestEdit);

            // ===================== مدال «تبدیل به وام» =====================
            var convertOverlay = document.getElementById('lrq-convert-overlay');
            var convertCloseBtn = document.getElementById('lrq-convert-close');
            var convertCancelBtn = document.getElementById('lrq-convert-cancel');
            var convertSubmitBtn = document.getElementById('lrq-convert-submit');
            var convertOpenBtn = document.getElementById('lrq-edit-convert-loan');
            var convertStartInput = document.getElementById('lrq-convert-start-jdate');
            var convertDueInput = document.getElementById('lrq-convert-due-jdate');
            var convertSummary = document.getElementById('lrq-convert-summary');
            var convertHint = document.getElementById('lrq-convert-hint');
            var convertCtx = { initialized: false, busy: false };

            function escapeConvertHtml(s) {
                return escapeHtmlText(s);
            }

            function initConvertDatepickers() {
                if (convertCtx.initialized) return;
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) return;
                window.jQuery('#lrq-convert-start-jdate, #lrq-convert-due-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: { calendarSwitch: false }
                });
                convertCtx.initialized = true;
            }

            function renderConvertSummary(r, c) {
                if (!convertSummary) return;
                var rows = [
                    { k: 'مشتری', v: (c.full_name || '—') + (c.username ? ' — ' + c.username : '') },
                    { k: 'کد ملی', v: c.national_id_fa || '—' },
                    { k: 'موبایل', v: c.mobile_fa || '—' },
                    { k: 'مبلغ وام', v: formatNum(r.amount_toman) + ' تومان' },
                    { k: 'تعداد اقساط', v: formatNum(r.installments_count) + ' قسط' },
                    { k: 'فاصلهٔ هر قسط', v: formatNum(r.installment_interval_count) + ' ' + (r.installment_interval_unit_fa || '') },
                    { k: 'مبلغ هر قسط (پیش‌فرض)', v: formatNum(r.installment_amount_toman) + ' تومان' }
                ];
                var html = '';
                for (var i = 0; i < rows.length; i++) {
                    html += '<div class="lrq-convert-row"><span class="k">' + escapeConvertHtml(rows[i].k) + '</span><span class="v">' + escapeConvertHtml(rows[i].v) + '</span></div>';
                }
                convertSummary.innerHTML = html;
            }

            function openConvertModal() {
                if (!convertOverlay) return;
                var r = (lrqEditCtx && lrqEditCtx._lastData && lrqEditCtx._lastData.request) || null;
                var c = (lrqEditCtx && lrqEditCtx._lastData && lrqEditCtx._lastData.customer) || null;
                if (!r || !c || !lrqEditCtx.requestId) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('اطلاعات درخواست در دسترس نیست؛ مدال را ببندید و دوباره باز کنید.');
                    return;
                }
                if (r.is_converted_to_loan) {
                    if (window.AdminSwal && AdminSwal.info) {
                        AdminSwal.info('این درخواست قبلاً به وام تبدیل شده است' + (r.converted_at_fa ? ' (در ' + r.converted_at_fa + ')' : '') + '.');
                    }
                    return;
                }
                renderConvertSummary(r, c);
                if (convertStartInput) convertStartInput.value = '';
                if (convertDueInput) convertDueInput.value = '';
                setOverlay(true, convertOverlay);
                initConvertDatepickers();
                setTimeout(function () { if (convertStartInput) { try { convertStartInput.focus(); } catch (e) {} } }, 50);
            }

            function closeConvertModal() {
                if (!convertOverlay) return;
                setOverlay(false, convertOverlay);
                convertCtx.busy = false;
                if (convertSubmitBtn) convertSubmitBtn.disabled = false;
            }

            function buildConvertConfirmHtml(p) {
                function row(k, v, strong) {
                    return '<tr>'
                        + '<th style="text-align:start;padding:0.35rem 0.5rem;border-bottom:1px solid rgba(148,163,184,0.35);font-weight:600;white-space:nowrap;color:#64748b">' + escapeConvertHtml(k) + '</th>'
                        + '<td style="text-align:end;padding:0.35rem 0.5rem;border-bottom:1px solid rgba(148,163,184,0.35);' + (strong ? 'font-weight:800;' : 'font-weight:600;') + '">' + escapeConvertHtml(v) + '</td>'
                        + '</tr>';
                }
                var c = p.customer || {};
                var lt = p.loan_type || {};
                var rows = '';
                rows += row('مشتری', (c.full_name || '—') + (c.username ? ' (' + c.username + ')' : ''));
                rows += row('کد ملی', c.national_id_fa || '—');
                rows += row('موبایل', c.mobile_fa || '—');
                rows += row('نوع وام', lt.title || '—');
                rows += row('روش محاسبهٔ سود', lt.profit_method_label || '—');
                rows += row('درصد سود', (lt.interest_rate_fa || '—') + '٪');
                rows += row('مبلغ وام', formatNum(p.amount_toman) + ' تومان', true);
                rows += row('تعداد اقساط', formatNum(p.installments_count) + ' قسط');
                rows += row('فاصلهٔ بین اقساط', formatNum(p.installment_interval_count) + ' ' + (p.installment_interval_unit_fa || ''));
                rows += row('مبلغ هر قسط', formatNum(p.installment_amount_toman) + ' تومان', true);
                rows += row('سود کل', formatNum(p.profit_toman) + ' تومان');
                rows += row('جمع کل قابل بازپرداخت', formatNum(p.total_repayable_toman) + ' تومان', true);
                rows += row('تاریخ شروع وام', p.loan_start_jdate_fa || '—', true);
                rows += row('سررسید واریز به مشتری', p.disbursement_due_jdate_fa || '—');
                if (p.first_due_jdate_fa) rows += row('اولین سررسید قسط', p.first_due_jdate_fa);
                if (p.last_due_jdate_fa) rows += row('آخرین سررسید قسط', p.last_due_jdate_fa);
                return ''
                    + '<div style="font-size:0.82rem;line-height:1.7;text-align:start;direction:rtl">'
                    + '<p style="margin:0 0 0.55rem;color:#475569">ایجاد وام با مشخصات زیر برای مشتری مورد تأیید است؟ پس از تأیید، پروندهٔ وام و جدول اقساط ساخته می‌شود و از این پس قابل ویرایش از طریق «مدیریت وام‌های مشتری» است.</p>'
                    + '<table style="width:100%;border-collapse:collapse;background:rgba(99,102,241,0.04);border:1px solid rgba(148,163,184,0.35);border-radius:0.6rem;overflow:hidden">'
                    + '<tbody>' + rows + '</tbody>'
                    + '</table>'
                    + '</div>';
            }

            function fetchConvertPreview(rid, startJ, dueJ) {
                var u = new URL(lrqConvertPreviewUrl(rid), window.location.origin);
                if (startJ) u.searchParams.set('loan_start_jdate', startJ);
                if (dueJ) u.searchParams.set('disbursement_due_jdate', dueJ);
                return fetch(u.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(readFetchJsonBody);
            }

            function performLrqConvert() {
                if (convertCtx.busy) return;
                var rid = lrqEditCtx.requestId;
                if (!rid) return;
                var startJ = (convertStartInput && convertStartInput.value ? String(convertStartInput.value).trim() : '');
                var dueJ = (convertDueInput && convertDueInput.value ? String(convertDueInput.value).trim() : '');
                if (!startJ) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('تاریخ شروع وام را وارد کنید.');
                    if (convertStartInput) { try { convertStartInput.focus(); } catch (e) {} }
                    return;
                }
                if (!dueJ) {
                    if (window.AdminSwal && AdminSwal.warning) AdminSwal.warning('تاریخ سررسید واریز به حساب مشتری را وارد کنید.');
                    if (convertDueInput) { try { convertDueInput.focus(); } catch (e) {} }
                    return;
                }
                convertCtx.busy = true;
                if (convertSubmitBtn) convertSubmitBtn.disabled = true;

                fetchConvertPreview(rid, startJ, dueJ)
                    .then(null, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvertPreview transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        convertCtx.busy = false;
                        if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                        return null;
                    })
                    .then(function (res) {
                        if (!res) return null;
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'پیش‌نمایش امکان‌پذیر نیست.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        var preview = res.body || {};
                        if (preview.already_converted) {
                            if (window.AdminSwal && AdminSwal.info) AdminSwal.info('این درخواست قبلاً به وام تبدیل شده است.');
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        if (!window.AdminSwal || !AdminSwal.fire) {
                            // در نبود SweetAlert از confirm استاندارد استفاده می‌کنیم.
                            if (window.confirm('ایجاد وام با مشخصات نمایش داده شده تأیید می‌شود؟')) {
                                return doConvertCommit(rid, startJ, dueJ);
                            }
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        }
                        return wrapSwalThenable(AdminSwal.fire({
                            icon: 'question',
                            title: 'تأیید ایجاد وام',
                            html: buildConvertConfirmHtml(preview),
                            width: 720,
                            showCancelButton: true,
                            confirmButtonText: 'تأیید و ایجاد وام',
                            cancelButtonText: 'انصراف',
                            reverseButtons: true,
                            focusCancel: true
                        })).then(function (result) {
                            if (result && result.isConfirmed) {
                                return doConvertCommit(rid, startJ, dueJ);
                            }
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return null;
                        });
                    });
            }

            function doConvertCommit(rid, startJ, dueJ) {
                return fetch(lrqConvertUrl(rid), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ loan_start_jdate: startJ, disbursement_due_jdate: dueJ })
                })
                    .then(readFetchJsonBody, function (errNet) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvert transport', errNet);
                        }
                        if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارتباط با سرور برقرار نشد.');
                        return null;
                    })
                    .then(function (res) {
                        if (!res) {
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return;
                        }
                        if (!res.ok) {
                            var msg = safeAdminMessage(res.body && res.body.message, 'ایجاد وام انجام نشد.');
                            if (window.AdminSwal && AdminSwal.error) AdminSwal.error(msg);
                            convertCtx.busy = false;
                            if (convertSubmitBtn) convertSubmitBtn.disabled = false;
                            return;
                        }
                        var okMsg = safeAdminMessage(res.body && res.body.message, 'وام با موفقیت ایجاد شد.');
                        try { closeConvertModal(); } catch (eCC) { /* noop */ }
                        try { closeEditModal(); } catch (eCE) { /* noop */ }
                        // پس از موفقیت، SweetAlert موفقیت را نشان می‌دهیم و در ادامه جدول را رفرش می‌کنیم.
                        adminSwalAsPromise('success', okMsg).then(function () {
                            try { window.location.reload(); } catch (eR) { /* noop */ }
                        });
                    })
                    .then(null, function (eUnexpected) {
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('lrqConvert post-success', eUnexpected);
                        }
                    });
            }

            if (convertOpenBtn) convertOpenBtn.addEventListener('click', function () {
                if (convertOpenBtn.disabled) return;
                openConvertModal();
            });
            if (convertCloseBtn) convertCloseBtn.addEventListener('click', closeConvertModal);
            if (convertCancelBtn) convertCancelBtn.addEventListener('click', closeConvertModal);
            if (convertSubmitBtn) convertSubmitBtn.addEventListener('click', performLrqConvert);
            if (convertOverlay) {
                convertOverlay.addEventListener('click', function (e) {
                    if (e.target === convertOverlay) closeConvertModal();
                });
            }
            // ===================== پایان مدال «تبدیل به وام» =====================

            var statusLogExport = document.getElementById('lrq-statuslog-export');
            if (statusLogExport) {
                statusLogExport.addEventListener('click', function () {
                    var rid = lrqEditCtx.requestId;
                    if (!rid) return;
                    var qIn = document.getElementById('lrq-statuslog-q');
                    var q = qIn ? String(qIn.value || '').trim() : '';
                    var u = lrqResourceUrl(rid) + '/status-logs/export' + (q ? ('?q=' + encodeURIComponent(q)) : '');
                    window.location.href = u;
                });
            }
            var statusLogQ = document.getElementById('lrq-statuslog-q');
            if (statusLogQ) {
                statusLogQ.addEventListener('input', function () {
                    if (lrqStatusLogSearchTimer) clearTimeout(lrqStatusLogSearchTimer);
                    lrqStatusLogSearchTimer = setTimeout(function () {
                        if (statusLogOverlay && !statusLogOverlay.hidden) loadStatusLogsTable();
                    }, 350);
                });
            }

            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('[data-lrq-sms-resend]');
                if (!btn) return;
                var logId = parseInt(btn.getAttribute('data-lrq-sms-resend') || '0', 10);
                if (!logId || !lrqEditCtx.requestId) return;
                var url = lrqResourceUrl(lrqEditCtx.requestId) + '/status-sms-logs/' + encodeURIComponent(String(logId)) + '/resend';
                btn.disabled = true;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (window.AdminSwal) {
                        if (data.ok && AdminSwal.success) {
                            AdminSwal.success(String(data.message || 'انجام شد.'));
                        } else if (!data.ok && AdminSwal.error) {
                            AdminSwal.error(String(data.message || 'ناموفق'));
                        }
                    }
                    if (smsLogOverlay && !smsLogOverlay.hidden) loadSmsLogsTable();
                }).catch(function () {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('ارسال مجدد ناموفق بود.');
                }).finally(function () {
                    btn.disabled = false;
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (smsLogOverlay && !smsLogOverlay.hidden) {
                    closeSmsLogModal();
                    e.preventDefault();
                    return;
                }
                if (statusLogOverlay && !statusLogOverlay.hidden) {
                    closeStatusLogModal();
                    e.preventDefault();
                    return;
                }
                if (sdefOverlay && !sdefOverlay.hidden) {
                    closeSdefModal();
                    e.preventDefault();
                    return;
                }
                if (editOverlay && !editOverlay.hidden) {
                    closeEditModal();
                    e.preventDefault();
                }
            });

            var _sdefCache = null;

            function renderSdefList(data) {
                _sdefCache = data;
                if (!sdefListEl) return;
                sdefListEl.innerHTML = '';
                var stages = data.stage_slots || {};
                var sms = data.sms_templates || [];
                (data.definitions || []).forEach(function (d, idx) {
                    sdefListEl.appendChild(sdefCardEl(d, idx, stages, sms, false));
                });
            }

            function sdefLockOthers(activeWrap) {
                document.querySelectorAll('[data-sdef-row]').forEach(function (w) {
                    if (w === activeWrap) return;
                    var m = w.getAttribute('data-sdef-mutable') === '1';
                    if (!m) return;
                    sdefSetRowEditing(w, false);
                });
            }

            function sdefSetRowEditing(wrap, editing) {
                var mutable = wrap.getAttribute('data-sdef-mutable') === '1';
                wrap.classList.toggle('lrq-sdef-card--editing', editing && mutable);
                wrap.classList.toggle('lrq-sdef-card--locked', !editing || !mutable);
                wrap.setAttribute('data-sdef-editing', editing && mutable ? '1' : '0');
                wrap.querySelectorAll('[data-sdef-field]').forEach(function (el) {
                    el.disabled = !mutable || !editing;
                });
                var act = wrap.querySelector('[data-sdef-action-btn]');
                if (!act) return;
                if (!mutable) {
                    act.hidden = true;
                    act.disabled = true;
                    return;
                }
                act.hidden = false;
                act.disabled = false;
                if (editing) {
                    act.setAttribute('data-sdef-mode', 'save');
                    act.className = 'lrq-sdef-btn lrq-sdef-btn--save';
                    act.title = 'ذخیره';
                    act.setAttribute('aria-label', 'ذخیره');
                    act.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                } else {
                    act.setAttribute('data-sdef-mode', 'edit');
                    act.className = 'lrq-sdef-btn lrq-sdef-btn--edit';
                    act.title = 'ویرایش';
                    act.setAttribute('aria-label', 'ویرایش');
                    act.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i>';
                }
            }

            function sdefPerformSave(wrap, d, title, stageSel, smsSel, chkDup) {
                var payload = {
                    title: title.value.trim(),
                    stage_slot: stageSel.value || null,
                    sms_template_id: smsSel.value ? parseInt(smsSel.value, 10) : null,
                    allow_duplicate_request: chkDup.checked
                };
                if (!payload.title) {
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('عنوان وضعیت را وارد کنید.');
                    return;
                }
                var csrf = csrfToken();
                if (d.id) {
                    fetch(lrqStatusDefItemUrl(d.id), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        if (r.ok) return r.json();
                        return r.json().then(function (j) {
                            var msg = (j && j.message) ? j.message : 'ذخیره نشد.';
                            throw new Error(msg);
                        });
                    }).then(function () {
                        return fetch(lrqStatusDefIndex, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                    }).then(function (r) { return r.json(); }).then(function (fresh) {
                        renderSdefList(fresh);
                        refreshMainStatusSelect();
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success('ذخیره شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error(err && err.message ? err.message : 'ذخیره نشد.');
                        }
                    });
                } else {
                    fetch(lrqStatusDefStore, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).then(function (r) {
                        if (r.ok) return r.json();
                        return r.json().then(function (j) {
                            var msg = (j && j.message) ? j.message : 'ایجاد وضعیت ناموفق بود.';
                            throw new Error(msg);
                        });
                    }).then(function () {
                        return fetch(lrqStatusDefIndex, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                    }).then(function (r) { return r.json(); }).then(function (fresh) {
                        renderSdefList(fresh);
                        refreshMainStatusSelect();
                        if (window.AdminSwal && AdminSwal.success) AdminSwal.success('وضعیت جدید ایجاد شد.');
                    }).catch(function (err) {
                        if (window.AdminSwal && AdminSwal.error) {
                            AdminSwal.error(err && err.message ? err.message : 'ایجاد وضعیت ناموفق بود.');
                        }
                    });
                }
            }

            function sdefCardEl(d, idx, stages, smsList, isNewBlank) {
                var wrap = document.createElement('div');
                wrap.className = 'lrq-sdef-card';
                wrap.setAttribute('data-sdef-row', '');
                wrap.setAttribute('data-sdef-id', d.id != null ? String(d.id) : '');
                var mutable = d.is_mutable !== false;
                wrap.setAttribute('data-sdef-mutable', mutable ? '1' : '0');

                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.value = d.id != null ? String(d.id) : '';

                var title = document.createElement('input');
                title.type = 'text';
                title.className = 'lrq-sdef-input';
                title.value = d.title || '';
                title.setAttribute('data-sdef-field', '');
                title.setAttribute('aria-label', 'عنوان وضعیت');

                var stageSel = document.createElement('select');
                stageSel.className = 'lrq-sdef-select';
                stageSel.setAttribute('data-sdef-field', '');
                stageSel.setAttribute('aria-label', 'جایگاه در ویزارد');
                var opt0 = document.createElement('option');
                opt0.value = '';
                opt0.textContent = '— انتخاب جایگاه —';
                stageSel.appendChild(opt0);
                Object.keys(stages).forEach(function (k) {
                    var o = document.createElement('option');
                    o.value = k;
                    o.textContent = stages[k];
                    stageSel.appendChild(o);
                });
                stageSel.value = d.stage_slot || '';

                var smsSel = document.createElement('select');
                smsSel.className = 'lrq-sdef-select';
                smsSel.setAttribute('data-sdef-field', '');
                smsSel.setAttribute('aria-label', 'قالب پیامک');
                var sm0 = document.createElement('option');
                sm0.value = '';
                sm0.textContent = '— قالب پیامک —';
                smsSel.appendChild(sm0);
                smsList.forEach(function (st) {
                    var o = document.createElement('option');
                    o.value = String(st.id);
                    o.textContent = st.title;
                    smsSel.appendChild(o);
                });
                if (d.sms_template_id != null && String(d.sms_template_id) !== '') {
                    smsSel.value = String(d.sms_template_id);
                } else if (smsList.length > 0 && isNewBlank) {
                    smsSel.value = String(smsList[0].id);
                }

                var lblTitle = document.createElement('label');
                lblTitle.className = 'lrq-sdef-lbl';
                lblTitle.textContent = 'عنوان وضعیت';
                var titleRow = document.createElement('div');
                titleRow.className = 'lrq-sdef-title-row';
                titleRow.appendChild(lblTitle);
                titleRow.appendChild(title);

                var lblSt = document.createElement('label');
                lblSt.className = 'lrq-sdef-lbl';
                lblSt.textContent = 'جایگاه (نمایش در ویزارد کاربر)';
                var boxSt = document.createElement('div');
                boxSt.className = 'lrq-sdef-field';
                boxSt.appendChild(lblSt);
                boxSt.appendChild(stageSel);

                var lblSms = document.createElement('label');
                lblSms.className = 'lrq-sdef-lbl';
                lblSms.textContent = 'قالب پیامک';
                var boxSms = document.createElement('div');
                boxSms.className = 'lrq-sdef-field';
                boxSms.appendChild(lblSms);
                boxSms.appendChild(smsSel);

                var fieldsRow = document.createElement('div');
                fieldsRow.className = 'lrq-sdef-fields-row';
                fieldsRow.appendChild(boxSt);
                fieldsRow.appendChild(boxSms);

                var chkMut = document.createElement('input');
                chkMut.type = 'checkbox';
                chkMut.checked = mutable;
                chkMut.disabled = true;
                var chkDup = document.createElement('input');
                chkDup.type = 'checkbox';
                chkDup.checked = !!d.allow_duplicate_request;
                chkDup.setAttribute('data-sdef-field', '');
                chkDup.setAttribute('aria-label', 'اجازه درخواست تکراری');

                var checks = document.createElement('div');
                checks.className = 'lrq-sdef-checks';
                var l1 = document.createElement('label');
                l1.appendChild(chkMut);
                l1.appendChild(document.createTextNode(' قابل تغییر و حذف؟'));
                var l2 = document.createElement('label');
                l2.appendChild(chkDup);
                l2.appendChild(document.createTextNode(' اجازه درخواست تکراری'));
                checks.appendChild(l1);
                checks.appendChild(l2);

                var actions = document.createElement('div');
                actions.className = 'lrq-sdef-actions';
                var btnAction = document.createElement('button');
                btnAction.type = 'button';
                btnAction.setAttribute('data-sdef-action-btn', '');
                var btnDel = document.createElement('button');
                btnDel.type = 'button';
                btnDel.className = 'lrq-sdef-btn lrq-sdef-btn--del';
                btnDel.title = 'حذف';
                btnDel.setAttribute('aria-label', 'حذف');
                btnDel.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
                btnDel.disabled = !mutable || !d.id;
                actions.appendChild(btnAction);
                actions.appendChild(btnDel);

                wrap.appendChild(idInput);
                wrap.appendChild(titleRow);
                if (d.code && !isNewBlank) {
                    var codeP = document.createElement('p');
                    codeP.className = 'lrq-sdef-muted';
                    codeP.style.marginTop = '0.35rem';
                    codeP.textContent = 'کلید سیستمی: ' + d.code;
                    wrap.appendChild(codeP);
                }
                wrap.appendChild(fieldsRow);
                wrap.appendChild(checks);
                wrap.appendChild(actions);

                var startEditing = isNewBlank || !d.id;
                sdefSetRowEditing(wrap, startEditing);

                btnAction.addEventListener('click', function () {
                    if (!mutable) return;
                    var mode = btnAction.getAttribute('data-sdef-mode') || 'edit';
                    if (mode === 'edit') {
                        sdefLockOthers(wrap);
                        sdefSetRowEditing(wrap, true);
                    } else {
                        sdefPerformSave(wrap, d, title, stageSel, smsSel, chkDup);
                    }
                });

                btnDel.addEventListener('click', function () {
                    if (!d.id) {
                        wrap.remove();
                        return;
                    }
                    var doDel = function () {
                        fetch(lrqStatusDefItemUrl(d.id), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        }).then(function (r) {
                            if (r.ok) return r.json();
                            return r.json().then(function (j) {
                                var msg = (j && j.message) ? j.message : 'حذف ممکن نیست.';
                                throw new Error(msg);
                            });
                        }).then(function () {
                            wrap.remove();
                            refreshMainStatusSelect();
                            if (window.AdminSwal && AdminSwal.success) AdminSwal.success('حذف شد.');
                        }).catch(function (err) {
                            if (window.AdminSwal && AdminSwal.error) {
                                AdminSwal.error(err && err.message ? err.message : 'حذف ممکن نیست.');
                            }
                        });
                    };
                    if (window.AdminSwal && AdminSwal.confirm) {
                        wrapSwalThenable(AdminSwal.confirm({
                            title: 'حذف وضعیت',
                            text: 'این وضعیت از فهرست حذف شود؟ اگر روی درخواستی استفاده شده باشد حذف انجام نمی‌شود.',
                            confirmButtonText: 'بله، حذف شود',
                            cancelButtonText: 'انصراف',
                        })).then(function (result) {
                            if (result && result.isConfirmed) doDel();
                        });
                        return;
                    }
                    if (window.confirm('حذف شود؟')) doDel();
                });

                return wrap;
            }

            function openSdefModal() {
                if (!sdefOverlay) return;
                setOverlay(true, sdefOverlay);
                sdefListEl.innerHTML = '<div class="lrq-empty">در حال بارگذاری…</div>';
                fetch(lrqStatusDefIndex, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('bad');
                    return r.json();
                }).then(function (data) {
                    renderSdefList(data);
                }).catch(function () {
                    closeSdefModal();
                    if (window.AdminSwal && AdminSwal.error) AdminSwal.error('بارگذاری وضعیت‌ها ناموفق بود.');
                });
            }

            if (btnOpenStatusDefs) {
                btnOpenStatusDefs.addEventListener('click', function () {
                    openSdefModal();
                });
            }
            if (sdefAddBtn) {
                sdefAddBtn.addEventListener('click', function () {
                    if (!_sdefCache) return;
                    var stages = _sdefCache.stage_slots || {};
                    var sms = _sdefCache.sms_templates || [];
                    var blank = { id: null, title: '', stage_slot: 'before_documents', sms_template_id: null, is_mutable: true, allow_duplicate_request: false };
                    sdefListEl.appendChild(sdefCardEl(blank, 0, stages, sms, true));
                });
            }
        })();

        // close the status filter popover when clicking outside or pressing Escape
        (function () {
            var details = document.getElementById('lrq-status-filter');
            if (!details) return;
            var summary = details.querySelector('summary');
            if (summary) {
                details.addEventListener('toggle', function () {
                    summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
                });
            }
            document.addEventListener('click', function (ev) {
                if (!details.open) return;
                if (details.contains(ev.target)) return;
                details.open = false;
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && details.open) {
                    details.open = false;
                    if (summary) summary.focus();
                }
            });
        })();
    </script>
