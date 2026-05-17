import { SupportTicketUi, parseJsonResponse } from './support-ticket-ui.js';

function getConfig() {
    return window.__CTK_EMBED__ || {};
}

function syncComposeBody() {
    if (typeof window.syncCtkEmbedComposeEditor === 'function') {
        window.syncCtkEmbedComposeEditor();
    }
}

function syncReplyBody() {
    if (typeof window.syncCtkEmbedReplyEditor === 'function') {
        window.syncCtkEmbedReplyEditor();
    }
}

function hasEditorContent(elementId) {
    if (elementId === 'ctk-reply-body') {
        syncReplyBody();
    } else if (elementId === 'ctk-compose-body') {
        syncComposeBody();
    }
    if (typeof window.syncTicketEditorField === 'function') {
        return window.syncTicketEditorField(elementId);
    }
    const ta = document.getElementById(elementId);
    if (!ta) {
        return false;
    }
    const plain = ta.value.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim();

    return plain !== '';
}

function bootCustomerTicketsEmbed() {
    const cfg = getConfig();
    const stUi = SupportTicketUi;
    const composeDialog = document.getElementById('ctk-compose-dialog');
    const detailDialog = document.getElementById('ctk-detail-dialog');
    const composeForm = document.getElementById('ctk-compose-form');
    let snapshots = Object.assign({}, cfg.snapshots || {});
    const listUrl = cfg.listUrl || '';
    const storeUrl = cfg.storeUrl || '';
    const ticketApiBase = (cfg.ticketApiBase || '').replace(/\/$/, '');
    const csrf = cfg.csrf || '';
    const customerLabel = cfg.customerLabel || 'مشتری';
    const listState = {
        tab: cfg.activeTab || 'received',
        q: cfg.searchQ || '',
        page: Number(new URLSearchParams(window.location.search).get('page')) || 1,
    };
    let activeTicketId = null;

    const smsComposeTemplate = cfg.smsComposeTemplate || '';
    const appDisplayName = cfg.appDisplayName || 'سامانه';

    function esc(s) {
        return stUi.esc(s);
    }

    function emptyTableMessage() {
        if (listState.tab === 'sent') {
            return 'هنوز تیکتی برای این مشتری ارسال نشده است.';
        }
        return 'این مشتری هنوز تیکتی ارسال نکرده است.';
    }

    function bindViewButtons() {
        document.querySelectorAll('.ctk-view-btn').forEach(function (btn) {
            if (btn.dataset.ctkBound === '1') {
                return;
            }
            btn.dataset.ctkBound = '1';
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-ticket-id');
                if (id) {
                    openDetail(id);
                }
            });
        });
    }

    function updateTabBadges(receivedCount, sentCount) {
        document.querySelectorAll('[data-ctk-count="received"]').forEach(function (el) {
            el.textContent = String(receivedCount ?? el.textContent);
        });
        document.querySelectorAll('[data-ctk-count="sent"]').forEach(function (el) {
            el.textContent = String(sentCount ?? el.textContent);
        });
        document.querySelectorAll('[data-ctk-tab]').forEach(function (tabEl) {
            const tab = tabEl.getAttribute('data-ctk-tab');
            tabEl.classList.toggle('is-active', tab === listState.tab);
        });
    }

    function renderTicketsTable(payload) {
        const root = document.getElementById('ctk-table-root');
        const pagWrap = document.getElementById('ctk-pagination-wrap');
        if (!root) {
            return;
        }
        const rows = payload.data || [];
        const partyLabel = payload.party_column_label || (listState.tab === 'sent' ? 'گیرنده' : 'فرستنده');
        if (!rows.length) {
            root.innerHTML =
                '<div class="ctk-empty" id="ctk-empty">'
                + '<i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>'
                + esc(emptyTableMessage())
                + '</div>';
            if (pagWrap) {
                pagWrap.innerHTML = '';
            }
            return;
        }
        let html = '<table class="ctk-tbl"><thead><tr>'
            + '<th scope="col">تاریخ و ساعت</th>'
            + '<th scope="col">' + esc(partyLabel) + '</th>'
            + '<th scope="col">موضوع</th>'
            + '<th scope="col">وضعیت</th>'
            + '<th scope="col">متن تیکت</th>'
            + '<th scope="col">ضمیمه</th>'
            + '<th scope="col">عملیات</th>'
            + '</tr></thead><tbody>';
        rows.forEach(function (row) {
            const st = row.status || '';
            let stClass = 'ctk-status';
            if (st === 'closed') {
                stClass += ' ctk-status--closed';
            } else if (st === 'on_hold') {
                stClass += ' ctk-status--hold';
            }
            html += '<tr>';
            html += '<td class="ctk-dt">' + esc(row.datetime_fa) + '</td>';
            html += '<td class="ctk-party" title="' + esc(row.party_label) + '">' + esc(row.party_label) + '</td>';
            html += '<td class="ctk-subject" title="' + esc(row.subject) + '">' + esc(row.subject) + '</td>';
            html += '<td><span class="' + stClass + '">' + esc(row.status_label || st) + '</span></td>';
            html += '<td class="ctk-excerpt" title="' + esc(row.excerpt) + '">' + esc(row.excerpt) + '</td>';
            html += '<td>';
            if (row.has_attachment) {
                html += '<span class="ctk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>';
            } else {
                html += '<span class="ctk-dt">—</span>';
            }
            html += '</td>';
            html += '<td><button type="button" class="ctk-btn ctk-btn--ghost ctk-view-btn" data-ticket-id="' + esc(String(row.id)) + '">'
                + '<i class="fa-solid fa-eye" aria-hidden="true"></i> مشاهده</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        root.innerHTML = html;
        if (pagWrap) {
            pagWrap.innerHTML = payload.pagination_html || '';
        }
        bindViewButtons();
        bindPaginationLinks();
    }

    function bindPaginationLinks() {
        const pagWrap = document.getElementById('ctk-pagination-wrap');
        if (!pagWrap) {
            return;
        }
        pagWrap.querySelectorAll('a[href]').forEach(function (link) {
            if (link.dataset.ctkPagBound === '1') {
                return;
            }
            link.dataset.ctkPagBound = '1';
            link.addEventListener('click', function (e) {
                e.preventDefault();
                try {
                    const u = new URL(link.href, window.location.origin);
                    const page = Number(u.searchParams.get('page')) || 1;
                    listState.page = page;
                    refreshTicketsList();
                } catch {
                    /* noop */
                }
            });
        });
    }

    function refreshTicketsList(options) {
        const opts = options || {};
        if (opts.tab) {
            listState.tab = opts.tab;
        }
        if (typeof opts.page === 'number') {
            listState.page = opts.page;
        }
        const wrap = document.getElementById('ctk-wrap');
        if (wrap) {
            wrap.setAttribute('aria-busy', 'true');
        }
        let url = listUrl
            + '?tab=' + encodeURIComponent(listState.tab)
            + '&page=' + encodeURIComponent(String(listState.page || 1));
        if (listState.q) {
            url += '&q=' + encodeURIComponent(listState.q);
        }
        return fetch(url, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(parseJsonResponse)
            .then(function (payload) {
                Object.assign(snapshots, payload.snapshots || {});
                if (payload.active_tab) {
                    listState.tab = payload.active_tab;
                }
                renderTicketsTable(payload);
                updateTabBadges(payload.received_count, payload.sent_count);
            })
            .finally(function () {
                if (wrap) {
                    wrap.removeAttribute('aria-busy');
                }
            });
    }

    function renderDetail(snapshot) {
        const body = document.getElementById('ctk-detail-body');
        if (!body || !snapshot) {
            return;
        }
        const partyLabel = snapshot.list_type === 'sent' ? 'گیرنده' : 'فرستنده';
        let html = '';
        html += '<div class="ctk-detail-meta">';
        html += '<div class="ctk-detail-meta-item"><span>موضوع</span><strong>' + esc(snapshot.subject) + '</strong></div>';
        html += '<div class="ctk-detail-meta-item"><span>' + esc(partyLabel) + '</span><strong>' + esc(snapshot.party_label) + '</strong></div>';
        html += '<div class="ctk-detail-meta-item"><span>وضعیت</span><strong>' + esc(snapshot.status_label || snapshot.status) + '</strong></div>';
        html += '<div class="ctk-detail-meta-item"><span>تاریخ</span><strong>' + esc(snapshot.datetime_fa) + '</strong></div>';
        html += '</div>';
        if (snapshot.status_options) {
            html += '<div class="ctk-status-row">';
            html += '<label for="ctk-status-select" style="font-size:0.72rem;font-weight:800;color:var(--muted)">تغییر وضعیت</label>';
            html += '<select id="ctk-status-select">';
            Object.keys(snapshot.status_options).forEach(function (key) {
                const sel = key === snapshot.status ? ' selected' : '';
                html += '<option value="' + esc(key) + '"' + sel + '>' + esc(snapshot.status_options[key]) + '</option>';
            });
            html += '</select>';
            html += '<button type="button" class="ctk-btn ctk-btn--ghost" id="ctk-status-save">ذخیره وضعیت</button>';
            html += '</div>';
        }
        html += stUi.renderChatHtml(snapshot.messages || [], esc);
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
        const title = document.getElementById('ctk-detail-title');
        if (title) {
            title.textContent = snapshot.subject || 'جزئیات تیکت';
        }
    }

    function setReplyVisible(show) {
        const wrap = document.getElementById('ctk-detail-reply-wrap');
        if (wrap) {
            wrap.hidden = !show;
        }
        if (show && typeof window.initCtkEmbedReplyEditor === 'function') {
            window.initCtkEmbedReplyEditor();
        } else if (!show && typeof window.destroyCtkEmbedReplyEditor === 'function') {
            window.destroyCtkEmbedReplyEditor();
        }
    }

    function prepareSmsFields(snapshot) {
        const optionWrap = document.getElementById('ctk-sms-option-wrap');
        const fieldsWrap = document.getElementById('ctk-sms-fields');
        const checkbox = document.getElementById('ctk-send-sms');
        const textarea = document.getElementById('ctk-sms-text');
        const available = !!(snapshot && snapshot.sms_panel_available);
        if (optionWrap) {
            optionWrap.hidden = !available;
        }
        if (!available) {
            return;
        }
        if (checkbox) {
            checkbox.checked = false;
        }
        if (fieldsWrap) {
            fieldsWrap.hidden = true;
        }
        if (textarea) {
            textarea.value = (snapshot && snapshot.sms_default_text) ? snapshot.sms_default_text : '';
        }
    }

    function bindSmsToggle() {
        const checkbox = document.getElementById('ctk-send-sms');
        const fieldsWrap = document.getElementById('ctk-sms-fields');
        if (!checkbox || !fieldsWrap || checkbox.dataset.ctkBound === '1') {
            return;
        }
        checkbox.dataset.ctkBound = '1';
        checkbox.addEventListener('change', function () {
            fieldsWrap.hidden = !checkbox.checked;
        });
    }

    function personalizeSmsText(template, subject) {
        const subj = String(subject || '').trim();
        const subjDisplay = subj !== '' ? subj : '(عنوان تیکت را وارد کنید)';
        const greeting = customerLabel ? 'مشتری گرامی ' + customerLabel : 'مشتری گرامی';
        return String(template || '')
            .replace(/\{customer_greeting\}/g, greeting)
            .replace(/\{customer_name\}/g, customerLabel)
            .replace(/\{subject\}/g, subjDisplay)
            .replace(/\{app_name\}/g, appDisplayName);
    }

    function updateComposeSmsPreview() {
        const checkbox = document.getElementById('ctk-compose-send-sms');
        const previewWrap = document.getElementById('ctk-compose-sms-preview-wrap');
        const previewMeta = document.getElementById('ctk-compose-sms-preview-meta');
        const previewBody = document.getElementById('ctk-compose-sms-preview');
        const ta = document.getElementById('ctk-compose-sms-text');
        const subjectInput = document.getElementById('ctk-subject');
        if (!checkbox || !checkbox.checked || !previewWrap || !previewMeta || !previewBody) {
            if (previewWrap) {
                previewWrap.hidden = true;
            }
            return;
        }
        previewWrap.hidden = false;
        const template = (ta && ta.value.trim()) ? ta.value : smsComposeTemplate;
        const subject = subjectInput ? subjectInput.value.trim() : '';
        previewMeta.textContent = 'پیش‌نمایش برای: ' + customerLabel;
        previewBody.textContent = personalizeSmsText(template, subject);
    }

    function prepareComposeSmsFields() {
        const optionWrap = document.getElementById('ctk-compose-sms-option-wrap');
        const fieldsWrap = document.getElementById('ctk-compose-sms-fields');
        const checkbox = document.getElementById('ctk-compose-send-sms');
        const ta = document.getElementById('ctk-compose-sms-text');
        if (!cfg.smsPanelAvailable) {
            if (optionWrap) {
                optionWrap.hidden = true;
            }
            return;
        }
        if (optionWrap) {
            optionWrap.hidden = false;
        }
        if (checkbox) {
            checkbox.checked = false;
        }
        if (fieldsWrap) {
            fieldsWrap.hidden = true;
        }
        if (ta) {
            ta.value = smsComposeTemplate;
            ta.dataset.userEdited = '';
        }
        const previewWrap = document.getElementById('ctk-compose-sms-preview-wrap');
        if (previewWrap) {
            previewWrap.hidden = true;
        }
    }

    function bindComposeSmsToggle() {
        const checkbox = document.getElementById('ctk-compose-send-sms');
        const fieldsWrap = document.getElementById('ctk-compose-sms-fields');
        const ta = document.getElementById('ctk-compose-sms-text');
        if (!checkbox || !fieldsWrap || checkbox.dataset.ctkComposeBound === '1') {
            return;
        }
        checkbox.dataset.ctkComposeBound = '1';
        checkbox.addEventListener('change', function () {
            fieldsWrap.hidden = !checkbox.checked;
            if (checkbox.checked) {
                if (ta && (!ta.dataset.userEdited || ta.value.trim() === '')) {
                    ta.value = smsComposeTemplate;
                }
                updateComposeSmsPreview();
            }
        });
        if (ta && ta.dataset.smsInputBound !== '1') {
            ta.dataset.smsInputBound = '1';
            ta.addEventListener('input', function () {
                ta.dataset.userEdited = '1';
                updateComposeSmsPreview();
            });
        }
        document.getElementById('ctk-subject')?.addEventListener('input', function () {
            if (checkbox.checked) {
                updateComposeSmsPreview();
            }
        });
    }

    function bindStatusSave() {
        const btn = document.getElementById('ctk-status-save');
        const sel = document.getElementById('ctk-status-select');
        if (!btn || !sel || !activeTicketId) {
            return;
        }
        btn.onclick = function () {
            stUi.setBtnLoading(btn, true, 'در حال ذخیره…');
            fetch(ticketApiBase + '/' + activeTicketId + '/status', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: sel.value }),
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    snapshots[String(activeTicketId)] = data.ticket;
                    showTicketDetail(data.ticket);
                    stUi.notify({
                        icon: 'success',
                        title: 'وضعیت',
                        text: data.message,
                        timer: 1800,
                        showConfirmButton: false,
                    }, { closeDialogIds: ['ctk-compose-dialog', 'ctk-detail-dialog'] });
                    return refreshTicketsList();
                })
                .catch(function (err) {
                    stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: [] });
                })
                .finally(function () {
                    stUi.setBtnLoading(btn, false);
                });
        };
    }

    function showTicketDetail(snapshot) {
        activeTicketId = snapshot && snapshot.id ? snapshot.id : null;
        renderDetail(snapshot);
        setReplyVisible(!!(snapshot && snapshot.can_reply));
        prepareSmsFields(snapshot);
        bindStatusSave();
    }

    function openDetail(id) {
        const snap = snapshots[String(id)] || snapshots[id];
        if (!snap) {
            return;
        }
        showTicketDetail(snap);
        if (detailDialog && typeof detailDialog.showModal === 'function') {
            detailDialog.showModal();
        }
    }

    function closeCompose() {
        if (composeDialog && composeDialog.open) {
            composeDialog.close();
        }
        if (typeof window.destroyCtkEmbedComposeEditor === 'function') {
            window.destroyCtkEmbedComposeEditor();
        }
    }

    function closeDetail() {
        setReplyVisible(false);
        if (detailDialog && detailDialog.open) {
            detailDialog.close();
        }
    }

    function openCompose() {
        if (!composeDialog || typeof composeDialog.showModal !== 'function') {
            return;
        }
        if (composeForm) {
            composeForm.reset();
        }
        prepareComposeSmsFields();
        composeDialog.showModal();
        if (typeof window.initCtkEmbedComposeEditor === 'function') {
            window.initCtkEmbedComposeEditor();
        }
    }

    document.getElementById('ctk-open-compose')?.addEventListener('click', openCompose);
    document.querySelectorAll('[data-ctk-close-compose]').forEach(function (b) {
        b.addEventListener('click', closeCompose);
    });
    document.querySelectorAll('[data-ctk-close-detail]').forEach(function (b) {
        b.addEventListener('click', closeDetail);
    });
    composeDialog?.addEventListener('click', function (e) {
        if (e.target === composeDialog) {
            closeCompose();
        }
    });
    detailDialog?.addEventListener('click', function (e) {
        if (e.target === detailDialog) {
            closeDetail();
        }
    });

    document.querySelectorAll('[data-ctk-tab]').forEach(function (tabBtn) {
        tabBtn.addEventListener('click', function () {
            const tab = tabBtn.getAttribute('data-ctk-tab') || 'received';
            if (tab === listState.tab) {
                return;
            }
            listState.tab = tab;
            listState.page = 1;
            refreshTicketsList();
        });
    });

    document.getElementById('ctk-search-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('ctk-search-input');
        listState.q = input ? String(input.value || '').trim() : '';
        listState.page = 1;
        refreshTicketsList();
    });

    composeForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        stUi.clearInlineAlert('ctk-compose-scroll');
        if (!hasEditorContent('ctk-compose-body')) {
            stUi.showInlineAlert('ctk-compose-scroll', 'متن تیکت الزامی است.');
            return;
        }
        const composeSmsCb = document.getElementById('ctk-compose-send-sms');
        const composeSmsText = document.getElementById('ctk-compose-sms-text');
        if (composeSmsCb && composeSmsCb.checked) {
            const smsVal = composeSmsText ? composeSmsText.value.trim() : '';
            if (!smsVal) {
                stUi.showInlineAlert('ctk-compose-scroll', 'متن پیامک را وارد کنید.');
                const fieldsWrap = document.getElementById('ctk-compose-sms-fields');
                if (fieldsWrap) {
                    fieldsWrap.hidden = false;
                }
                return;
            }
        }
        const submitBtn = document.getElementById('ctk-compose-submit');
        stUi.setBtnLoading(submitBtn, true);
        fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: new FormData(composeForm),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                closeCompose();
                stUi.notify({
                    icon: 'success',
                    title: 'ارسال تیکت',
                    text: data.message || 'تیکت ارسال شد.',
                    timer: 2200,
                    showConfirmButton: false,
                }, { closeDialogIds: ['ctk-compose-dialog', 'ctk-detail-dialog'] });
                listState.tab = 'sent';
                listState.page = 1;
                return refreshTicketsList();
            })
            .catch(function (err) {
                stUi.notify({
                    icon: 'error',
                    title: 'خطا',
                    text: err.message || 'خطا در ارسال تیکت',
                }, { closeDialogIds: ['ctk-compose-dialog'] });
            })
            .finally(function () {
                stUi.setBtnLoading(submitBtn, false);
            });
    });

    document.getElementById('ctk-reply-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!activeTicketId) {
            stUi.notify({ icon: 'warning', title: 'پاسخ', text: 'تیکت انتخاب نشده است.' }, { closeDialogIds: [] });
            return;
        }
        stUi.clearInlineAlert('ctk-detail-reply-wrap');
        if (!hasEditorContent('ctk-reply-body')) {
            stUi.showInlineAlert('ctk-detail-reply-wrap', 'متن پاسخ الزامی است.');
            return;
        }
        const sendSmsCb = document.getElementById('ctk-send-sms');
        const smsTextEl = document.getElementById('ctk-sms-text');
        if (sendSmsCb && sendSmsCb.checked) {
            const smsVal = smsTextEl ? smsTextEl.value.trim() : '';
            if (!smsVal) {
                stUi.showInlineAlert('ctk-detail-reply-wrap', 'متن پیامک را وارد کنید.');
                const fieldsWrap = document.getElementById('ctk-sms-fields');
                if (fieldsWrap) {
                    fieldsWrap.hidden = false;
                }
                return;
            }
        }
        const replyBtn = document.getElementById('ctk-reply-submit');
        const form = e.target;
        stUi.setBtnLoading(replyBtn, true);
        fetch(ticketApiBase + '/' + activeTicketId + '/reply', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: new FormData(form),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                snapshots[String(activeTicketId)] = data.ticket;
                form.reset();
                if (typeof window.destroyCtkEmbedReplyEditor === 'function') {
                    window.destroyCtkEmbedReplyEditor();
                }
                closeDetail();
                stUi.notify({
                    icon: 'success',
                    title: 'پاسخ',
                    text: data.message || 'پاسخ ثبت شد.',
                    timer: 1800,
                    showConfirmButton: false,
                }, { closeDialogIds: ['ctk-compose-dialog', 'ctk-detail-dialog'] });
                return refreshTicketsList();
            })
            .catch(function (err) {
                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: ['ctk-detail-dialog'] });
            })
            .finally(function () {
                stUi.setBtnLoading(replyBtn, false);
            });
    });

    bindViewButtons();
    bindPaginationLinks();
    bindSmsToggle();
    bindComposeSmsToggle();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCustomerTicketsEmbed);
} else {
    bootCustomerTicketsEmbed();
}
