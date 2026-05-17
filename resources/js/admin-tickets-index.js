import { bindAjaxPagination } from './mg-pagination-ajax.js';
import { SupportTicketUi, parseJsonResponse } from './support-ticket-ui.js';

function getConfig() {
    return window.__TK_PAGE__ || {};
}

function syncComposeBody() {
    if (typeof window.syncAdminTicketComposeEditor === 'function') {
        window.syncAdminTicketComposeEditor();
    }
}

function syncReplyBody() {
    if (typeof window.syncAdminTicketReplyEditor === 'function') {
        window.syncAdminTicketReplyEditor();
    }
}

function hasEditorContent(elementId) {
    if (elementId === 'tk-reply-body') {
        syncReplyBody();
    } else if (elementId === 'tk-compose-body') {
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

function bootAdminTickets() {
    const cfg = getConfig();
    const stUi = SupportTicketUi;
    const composeDialog = document.getElementById('tk-compose-dialog');
    const detailDialog = document.getElementById('tk-detail-dialog');
    const composeForm = document.getElementById('tk-compose-form');
    let snapshots = Object.assign({}, cfg.snapshots || {});
    const customerSearchUrl = cfg.customerSearchUrl || '';
    const composeStoreUrl = cfg.composeStoreUrl || '';
    const ticketsAdminBase = cfg.ticketsAdminBase || '';
    const ticketsListUrl = cfg.ticketsListUrl || '';
    const csrf = cfg.csrf || '';
    const urlParams = new URLSearchParams(window.location.search);
    const listState = {
        tab: cfg.activeTab || 'received',
        q: cfg.searchQ || '',
        page: Number(urlParams.get('page')) || 1,
        perPage: Number(urlParams.get('per_page')) || 15,
    };
    let $single = null;
    let $multi = null;
    let activeTicketId = null;

    function bindViewButtons() {
        document.querySelectorAll('.tk-view-btn').forEach(function (btn) {
            if (btn.dataset.tkBound === '1') {
                return;
            }
            btn.dataset.tkBound = '1';
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-ticket-id');
                if (id) {
                    openDetail(id);
                }
            });
        });
    }

    function updateTabBadges(receivedCount, sentCount) {
        document.querySelectorAll('.tk-tab').forEach(function (tabEl) {
            const href = tabEl.getAttribute('href') || '';
            const badge = tabEl.querySelector('.tk-tab-badge');
            if (!badge) {
                return;
            }
            if (href.indexOf('tab=sent') !== -1) {
                badge.textContent = String(sentCount ?? badge.textContent);
            } else if (href.indexOf('tab=received') !== -1) {
                badge.textContent = String(receivedCount ?? badge.textContent);
            }
        });
        document.querySelectorAll('.tk-tab').forEach(function (tabEl) {
            const href = tabEl.getAttribute('href') || '';
            const isSent = href.indexOf('tab=sent') !== -1;
            const isActive = (listState.tab === 'sent' && isSent) || (listState.tab === 'received' && !isSent);
            tabEl.classList.toggle('is-active', isActive);
        });
    }

    function emptyTableMessage() {
        if (listState.tab === 'sent') {
            return 'هنوز تیکتی ارسال نکرده‌اید. با دکمه «ارسال تیکت جدید» اولین تیکت را بفرستید.';
        }
        return 'تیکت دریافتی ثبت نشده است. تیکت‌های ارسالی از سمت کاربران در این بخش نمایش داده می‌شوند.';
    }

    function renderTicketsTable(payload) {
        const root = document.getElementById('tk-table-root');
        const pagWrap = document.getElementById('tk-pagination-wrap');
        if (!root) {
            return;
        }
        const rows = payload.data || [];
        const partyLabel = payload.party_column_label || (listState.tab === 'sent' ? 'گیرنده' : 'فرستنده');
        if (!rows.length) {
            root.innerHTML =
                '<div class="tk-empty" id="tk-empty">'
                + '<i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>'
                + esc(emptyTableMessage())
                + '</div>';
            if (pagWrap) {
                pagWrap.innerHTML = '';
            }
            return;
        }
        let html = '<table class="tk-tbl"><thead><tr>'
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
            let stClass = 'tk-status';
            if (st === 'closed') {
                stClass += ' tk-status--closed';
            } else if (st === 'on_hold') {
                stClass += ' tk-status--hold';
            }
            html += '<tr>';
            html += '<td class="tk-dt">' + esc(row.datetime_fa) + '</td>';
            html += '<td class="tk-party" title="' + esc(row.party_label) + '">' + esc(row.party_label) + '</td>';
            html += '<td class="tk-subject" title="' + esc(row.subject) + '">' + esc(row.subject) + '</td>';
            html += '<td><span class="' + stClass + '">' + esc(row.status_label || st) + '</span></td>';
            html += '<td class="tk-excerpt" title="' + esc(row.excerpt) + '">' + esc(row.excerpt) + '</td>';
            html += '<td>';
            if (row.has_attachment) {
                html += '<span class="tk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>';
            } else {
                html += '<span class="tk-dt">—</span>';
            }
            html += '</td>';
            html += '<td><button type="button" class="tk-btn tk-btn--ghost tk-view-btn" data-ticket-id="' + esc(String(row.id)) + '">'
                + '<i class="fa-solid fa-eye" aria-hidden="true"></i> مشاهده جزئیات</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        root.innerHTML = html;
        if (pagWrap) {
            pagWrap.innerHTML = payload.pagination_html || '';
            bindAjaxPagination(pagWrap, {
                onPage: function (page) {
                    listState.page = page;
                    refreshTicketsList();
                },
                onPerPage: function (perPage) {
                    listState.perPage = perPage;
                    listState.page = 1;
                    refreshTicketsList();
                },
            });
        }
        bindViewButtons();
    }

    function refreshTicketsList(options) {
        const opts = options || {};
        if (opts.tab) {
            listState.tab = opts.tab;
        }
        if (typeof opts.page === 'number') {
            listState.page = opts.page;
        }
        const wrap = document.getElementById('tk-wrap');
        if (wrap) {
            wrap.setAttribute('aria-busy', 'true');
        }
        let url = ticketsListUrl
            + '?tab=' + encodeURIComponent(listState.tab)
            + '&page=' + encodeURIComponent(String(listState.page || 1))
            + '&per_page=' + encodeURIComponent(String(listState.perPage || 15));
        if (listState.q) {
            url += '&q=' + encodeURIComponent(listState.q);
        }
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(parseJsonResponse)
            .then(function (payload) {
                Object.assign(snapshots, payload.snapshots || {});
                if (payload.active_tab) {
                    listState.tab = payload.active_tab;
                }
                renderTicketsTable(payload);
                updateTabBadges(payload.received_count, payload.sent_count);
                if (opts.updateUrl !== false) {
                    const pageUrl = new URL(window.location.href);
                    pageUrl.searchParams.set('tab', listState.tab);
                    if (listState.q) {
                        pageUrl.searchParams.set('q', listState.q);
                    } else {
                        pageUrl.searchParams.delete('q');
                    }
                    if (listState.page > 1) {
                        pageUrl.searchParams.set('page', String(listState.page));
                    } else {
                        pageUrl.searchParams.delete('page');
                    }
                    if (listState.perPage && listState.perPage !== 15) {
                        pageUrl.searchParams.set('per_page', String(listState.perPage));
                    } else {
                        pageUrl.searchParams.delete('per_page');
                    }
                    window.history.replaceState(null, '', pageUrl.toString());
                }
            })
            .finally(function () {
                if (wrap) {
                    wrap.removeAttribute('aria-busy');
                }
            });
    }

    function esc(s) {
        return stUi.esc(s);
    }

    function closeCompose() {
        if (composeDialog && composeDialog.open) {
            composeDialog.close();
        }
        if (typeof window.destroyAdminTicketComposeEditor === 'function') {
            window.destroyAdminTicketComposeEditor();
        }
    }

    function closeDetail() {
        setReplyVisible(false);
        if (detailDialog && detailDialog.open) {
            detailDialog.close();
        }
    }

    function currentRecipientMode() {
        const r = composeForm && composeForm.querySelector('input[name="recipient_mode"]:checked');

        return r ? String(r.value) : 'single';
    }

    function syncRecipientPanels() {
        const mode = currentRecipientMode();
        const pSingle = document.getElementById('tk-panel-single');
        const pMulti = document.getElementById('tk-panel-multiple');
        const pAll = document.getElementById('tk-panel-all');
        if (pSingle) {
            pSingle.hidden = mode !== 'single';
        }
        if (pMulti) {
            pMulti.hidden = mode !== 'multiple';
        }
        if (pAll) {
            pAll.hidden = mode !== 'all';
        }
    }

    function initSelect2($el, multiple) {
        if (!window.jQuery || !window.jQuery.fn.select2) {
            return null;
        }
        const $ = window.jQuery;
        if ($el.data('select2')) {
            try {
                $el.select2('destroy');
            } catch {
                /* noop */
            }
        }
        $el.select2({
            width: '100%',
            dir: 'rtl',
            dropdownParent: window.jQuery('#tk-compose-dialog'),
            placeholder: multiple ? 'جستجو و انتخاب چند کاربر…' : 'جستجو و انتخاب کاربر…',
            allowClear: !multiple,
            multiple: !!multiple,
            minimumInputLength: 0,
            ajax: {
                url: customerSearchUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: (data && data.results) ? data.results : [] };
                },
                cache: true,
            },
        });

        return $el;
    }

    function rebuildHiddenCustomerIds() {
        const wrap = document.getElementById('tk-customer-ids-hidden');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = '';
        const mode = currentRecipientMode();
        let ids = [];
        if (mode === 'single' && $single) {
            const v = $single.val();
            if (v) {
                ids.push(String(v));
            }
        } else if (mode === 'multiple' && $multi) {
            ids = ($multi.val() || []).map(String);
        }
        ids.forEach(function (id) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'customer_ids[]';
            inp.value = id;
            wrap.appendChild(inp);
        });
    }

    function renderDetail(snapshot) {
        const body = document.getElementById('tk-detail-body');
        if (!body || !snapshot) {
            return;
        }
        const partyLabel = snapshot.list_type === 'sent' ? 'گیرنده' : 'فرستنده';
        let html = '';
        html += '<div class="st-detail-meta">';
        html += '<div class="st-detail-meta-item"><span>موضوع</span><strong>' + esc(snapshot.subject) + '</strong></div>';
        html += '<div class="st-detail-meta-item"><span>' + esc(partyLabel) + '</span><strong>' + esc(snapshot.party_label) + '</strong></div>';
        html += '<div class="st-detail-meta-item"><span>وضعیت</span><strong>' + esc(snapshot.status_label || snapshot.status) + '</strong></div>';
        html += '<div class="st-detail-meta-item"><span>تاریخ</span><strong>' + esc(snapshot.datetime_fa) + '</strong></div>';
        html += '</div>';
        if (snapshot.status_options) {
            html += '<div class="tk-status-row">';
            html += '<label for="tk-status-select" style="font-size:0.74rem;font-weight:800;color:var(--muted)">تغییر وضعیت</label>';
            html += '<select id="tk-status-select">';
            Object.keys(snapshot.status_options).forEach(function (key) {
                const sel = key === snapshot.status ? ' selected' : '';
                html += '<option value="' + esc(key) + '"' + sel + '>' + esc(snapshot.status_options[key]) + '</option>';
            });
            html += '</select>';
            html += '<button type="button" class="tk-btn tk-btn--ghost" id="tk-status-save">ذخیره وضعیت</button>';
            html += '</div>';
        }
        html += stUi.renderChatHtml(snapshot.messages || [], esc);
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
        const title = document.getElementById('tk-detail-title');
        if (title) {
            title.textContent = snapshot.subject || 'جزئیات تیکت';
        }
    }

    function setReplyVisible(show) {
        const wrap = document.getElementById('tk-detail-reply-wrap');
        if (wrap) {
            wrap.hidden = !show;
        }
        if (show && typeof window.initAdminTicketReplyEditor === 'function') {
            window.initAdminTicketReplyEditor();
        } else if (!show && typeof window.destroyAdminTicketReplyEditor === 'function') {
            window.destroyAdminTicketReplyEditor();
        }
    }

    function prepareSmsFields(snapshot) {
        const optionWrap = document.getElementById('tk-sms-option-wrap');
        const fieldsWrap = document.getElementById('tk-sms-fields');
        const checkbox = document.getElementById('tk-send-sms');
        const textarea = document.getElementById('tk-sms-text');
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
        const checkbox = document.getElementById('tk-send-sms');
        const fieldsWrap = document.getElementById('tk-sms-fields');
        if (!checkbox || !fieldsWrap || checkbox.dataset.stBound === '1') {
            return;
        }
        checkbox.dataset.stBound = '1';
        checkbox.addEventListener('change', function () {
            fieldsWrap.hidden = !checkbox.checked;
        });
    }

    const smsComposeTemplate = cfg.smsComposeTemplate || '';
    const appDisplayName = cfg.appDisplayName || 'سامانه';
    const totalCustomerCount = Number(cfg.totalCustomerCount) || 0;

    function parseSelect2CustomerName(item) {
        if (!item) {
            return '';
        }
        const text = String(item.text || '').trim();
        if (!text) {
            return '';
        }
        const sep = text.indexOf(' — ');
        return sep > 0 ? text.substring(0, sep).trim() : text;
    }

    function customerGreetingFromName(name) {
        const n = String(name || '').trim();
        return n !== '' ? 'مشتری گرامی ' + n : 'مشتری گرامی';
    }

    function getComposeRecipientContext() {
        const mode = currentRecipientMode();
        if (mode === 'all') {
            return {
                mode: 'all',
                count: totalCustomerCount,
                sampleGreeting: 'مشتری گرامی',
                names: [],
            };
        }
        if (mode === 'single' && $single) {
            const data = $single.select2('data');
            const item = Array.isArray(data) ? data[0] : data;
            const name = parseSelect2CustomerName(item);
            return {
                mode: 'single',
                count: name ? 1 : 0,
                sampleGreeting: customerGreetingFromName(name),
                names: name ? [name] : [],
            };
        }
        if (mode === 'multiple' && $multi) {
            const data = $multi.select2('data') || [];
            const names = data.map(parseSelect2CustomerName).filter(Boolean);
            return {
                mode: 'multiple',
                count: names.length,
                sampleGreeting: names.length ? customerGreetingFromName(names[0]) : 'مشتری گرامی',
                names: names,
            };
        }
        return { mode: mode, count: 0, sampleGreeting: 'مشتری گرامی', names: [] };
    }

    function personalizeSmsText(template, greeting, subject) {
        const subj = String(subject || '').trim();
        const subjDisplay = subj !== '' ? subj : '(عنوان تیکت را وارد کنید)';
        return String(template || '')
            .replace(/\{customer_greeting\}/g, greeting || 'مشتری گرامی')
            .replace(/\{customer_name\}/g, String(greeting || '').replace(/^مشتری گرامی\s*/u, '') || 'مشتری گرامی')
            .replace(/\{subject\}/g, subjDisplay)
            .replace(/\{app_name\}/g, appDisplayName);
    }

    function updateComposeSmsPreview() {
        const checkbox = document.getElementById('tk-compose-send-sms');
        const previewWrap = document.getElementById('tk-compose-sms-preview-wrap');
        const previewMeta = document.getElementById('tk-compose-sms-preview-meta');
        const previewBody = document.getElementById('tk-compose-sms-preview');
        const ta = document.getElementById('tk-compose-sms-text');
        const subjectInput = document.getElementById('tk-subject');
        if (!checkbox || !checkbox.checked || !previewWrap || !previewMeta || !previewBody) {
            if (previewWrap) {
                previewWrap.hidden = true;
            }
            return;
        }
        previewWrap.hidden = false;
        const template = (ta && ta.value.trim()) ? ta.value : smsComposeTemplate;
        const subject = subjectInput ? subjectInput.value.trim() : '';
        const ctx = getComposeRecipientContext();
        let meta = '';
        if (ctx.mode === 'all') {
            meta = 'ارسال به همه کاربران';
            if (ctx.count > 0) {
                meta += ' (' + ctx.count + ' نفر)';
            }
            meta += ' — پیش‌نمایش نمونه:';
        } else if (ctx.mode === 'multiple' && ctx.count > 1) {
            meta = 'ارسال به ' + ctx.count + ' مشتری — پیش‌نمایش برای: ' + ctx.names[0];
            meta += ' (هر مشتری نام خودش را دریافت می‌کند)';
        } else if (ctx.mode === 'multiple' && ctx.count === 1) {
            meta = 'پیش‌نمایش برای: ' + ctx.names[0];
        } else if (ctx.mode === 'single' && ctx.count === 1) {
            meta = 'پیش‌نمایش برای: ' + ctx.names[0];
        } else {
            meta = 'گیرنده‌ای انتخاب نشده — پیش‌نمایش نمونه:';
        }
        previewMeta.textContent = meta;
        previewBody.textContent = personalizeSmsText(template, ctx.sampleGreeting, subject);
    }

    function applyComposeSmsTemplate() {
        const ta = document.getElementById('tk-compose-sms-text');
        if (!ta || !smsComposeTemplate) {
            return;
        }
        if (!ta.dataset.userEdited || ta.value.trim() === '') {
            ta.value = smsComposeTemplate;
            ta.dataset.userEdited = '';
        }
        updateComposeSmsPreview();
    }

    function prepareComposeSmsFields() {
        const optionWrap = document.getElementById('tk-compose-sms-option-wrap');
        const fieldsWrap = document.getElementById('tk-compose-sms-fields');
        const checkbox = document.getElementById('tk-compose-send-sms');
        const ta = document.getElementById('tk-compose-sms-text');
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
        const previewWrap = document.getElementById('tk-compose-sms-preview-wrap');
        if (previewWrap) {
            previewWrap.hidden = true;
        }
    }

    function bindComposeSmsToggle() {
        const checkbox = document.getElementById('tk-compose-send-sms');
        const fieldsWrap = document.getElementById('tk-compose-sms-fields');
        const ta = document.getElementById('tk-compose-sms-text');
        if (!checkbox || !fieldsWrap || checkbox.dataset.stComposeBound === '1') {
            return;
        }
        checkbox.dataset.stComposeBound = '1';
        checkbox.addEventListener('change', function () {
            fieldsWrap.hidden = !checkbox.checked;
            if (checkbox.checked) {
                applyComposeSmsTemplate();
            } else {
                const previewWrap = document.getElementById('tk-compose-sms-preview-wrap');
                if (previewWrap) {
                    previewWrap.hidden = true;
                }
            }
        });
        if (ta && ta.dataset.smsInputBound !== '1') {
            ta.dataset.smsInputBound = '1';
            ta.addEventListener('input', function () {
                ta.dataset.userEdited = '1';
                updateComposeSmsPreview();
            });
        }
    }

    function bindComposeSmsPreviewRefresh() {
        document.getElementById('tk-subject')?.addEventListener('input', function () {
            if (document.getElementById('tk-compose-send-sms')?.checked) {
                updateComposeSmsPreview();
            }
        });
        composeForm?.querySelectorAll('input[name="recipient_mode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (document.getElementById('tk-compose-send-sms')?.checked) {
                    updateComposeSmsPreview();
                }
            });
        });
        if ($single) {
            $single.on('change select2:select select2:unselect select2:clear', function () {
                if (document.getElementById('tk-compose-send-sms')?.checked) {
                    updateComposeSmsPreview();
                }
            });
        }
        if ($multi) {
            $multi.on('change select2:select select2:unselect select2:clear', function () {
                if (document.getElementById('tk-compose-send-sms')?.checked) {
                    updateComposeSmsPreview();
                }
            });
        }
    }

    function bindStatusSave() {
        const btn = document.getElementById('tk-status-save');
        const sel = document.getElementById('tk-status-select');
        if (!btn || !sel || !activeTicketId) {
            return;
        }
        btn.onclick = function () {
            stUi.setBtnLoading(btn, true, 'در حال ذخیره…');
            fetch(ticketsAdminBase + '/' + activeTicketId + '/status', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
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
                            }, { closeAllTicketDialogs: true });
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

    function openCompose() {
        if (!composeDialog || typeof composeDialog.showModal !== 'function') {
            return;
        }
        if (composeForm) {
            composeForm.reset();
        }
        const bodyTa = document.getElementById('tk-compose-body');
        if (bodyTa) {
            bodyTa.removeAttribute('required');
        }
        if ($single) {
            $single.val(null).trigger('change');
        }
        if ($multi) {
            $multi.val(null).trigger('change');
        }
        rebuildHiddenCustomerIds();
        syncRecipientPanels();
        prepareComposeSmsFields();
        composeDialog.showModal();
        if (typeof window.initAdminTicketComposeEditor === 'function') {
            window.initAdminTicketComposeEditor();
        }
    }

    document.getElementById('tk-open-compose')?.addEventListener('click', openCompose);
    document.querySelectorAll('[data-tk-close-compose]').forEach(function (b) {
        b.addEventListener('click', closeCompose);
    });
    document.querySelectorAll('[data-tk-close-detail]').forEach(function (b) {
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

    composeForm?.querySelectorAll('input[name="recipient_mode"]').forEach(function (radio) {
        radio.addEventListener('change', syncRecipientPanels);
    });

    if (window.jQuery) {
        $single = initSelect2(window.jQuery('#tk-customer-single'), false);
        $multi = initSelect2(window.jQuery('#tk-customer-multiple'), true);
    }

    composeForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        stUi.clearInlineAlert('tk-compose-scroll');
        if (!hasEditorContent('tk-compose-body')) {
            stUi.showInlineAlert('tk-compose-scroll', 'متن تیکت الزامی است.');
            return;
        }
        const mode = currentRecipientMode();
        rebuildHiddenCustomerIds();
        if (mode !== 'all') {
            const wrap = document.getElementById('tk-customer-ids-hidden');
            if (!wrap || !wrap.children.length) {
                const msg = mode === 'single' ? 'یک گیرنده انتخاب کنید.' : 'حداقل یک گیرنده انتخاب کنید.';
                stUi.showInlineAlert('tk-compose-scroll', msg);
                return;
            }
        }
        const composeSmsCb = document.getElementById('tk-compose-send-sms');
        const composeSmsText = document.getElementById('tk-compose-sms-text');
        if (composeSmsCb && composeSmsCb.checked) {
            const smsVal = composeSmsText ? composeSmsText.value.trim() : '';
            if (!smsVal) {
                stUi.showInlineAlert('tk-compose-scroll', 'متن پیامک را وارد کنید.');
                const fieldsWrap = document.getElementById('tk-compose-sms-fields');
                if (fieldsWrap) {
                    fieldsWrap.hidden = false;
                }
                return;
            }
        }
        const submitBtn = document.getElementById('tk-compose-submit');
        stUi.setBtnLoading(submitBtn, true);
        fetch(composeStoreUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new FormData(composeForm),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                closeCompose();
                stUi.notify({
                    icon: 'success',
                    title: 'ارسال تیکت',
                    text: data.message || 'تیکت با موفقیت ارسال شد.',
                    timer: 2200,
                    showConfirmButton: false,
                }, { closeAllTicketDialogs: true });
                listState.tab = 'sent';
                listState.page = 1;
                return refreshTicketsList();
            })
            .catch(function (err) {
                stUi.notify({
                    icon: 'error',
                    title: 'خطا',
                    text: err.message || 'خطا در ارسال تیکت',
                }, { closeDialogIds: ['tk-compose-dialog'] });
            })
            .finally(function () {
                stUi.setBtnLoading(submitBtn, false);
            });
    });

    bindViewButtons();

    document.getElementById('tk-reply-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!activeTicketId) {
            stUi.notify({ icon: 'warning', title: 'پاسخ', text: 'تیکت انتخاب نشده است.' }, { closeDialogIds: [] });
            return;
        }
        stUi.clearInlineAlert('tk-detail-reply-wrap');
        if (!hasEditorContent('tk-reply-body')) {
            stUi.showInlineAlert('tk-detail-reply-wrap', 'متن پاسخ الزامی است.');
            return;
        }
        const sendSmsCb = document.getElementById('tk-send-sms');
        const smsTextEl = document.getElementById('tk-sms-text');
        if (sendSmsCb && sendSmsCb.checked) {
            const smsVal = smsTextEl ? smsTextEl.value.trim() : '';
            if (!smsVal) {
                stUi.showInlineAlert('tk-detail-reply-wrap', 'متن پیامک را وارد کنید.');
                if (document.getElementById('tk-sms-fields')) {
                    document.getElementById('tk-sms-fields').hidden = false;
                }
                return;
            }
        }
        const replyBtn = document.getElementById('tk-reply-submit');
        const form = e.target;
        stUi.setBtnLoading(replyBtn, true);
        fetch(ticketsAdminBase + '/' + activeTicketId + '/reply', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new FormData(form),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                snapshots[String(activeTicketId)] = data.ticket;
                form.reset();
                if (typeof window.destroyAdminTicketReplyEditor === 'function') {
                    window.destroyAdminTicketReplyEditor();
                }
                closeDetail();
                stUi.notify({
                    icon: 'success',
                    title: 'پاسخ',
                    text: data.message || 'پاسخ ثبت شد.',
                    timer: 1800,
                    showConfirmButton: false,
                }, { closeAllTicketDialogs: true });
                return refreshTicketsList();
            })
            .catch(function (err) {
                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeAllTicketDialogs: true });
            })
            .finally(function () {
                stUi.setBtnLoading(replyBtn, false);
            });
    });

    syncRecipientPanels();
    bindSmsToggle();
    bindComposeSmsToggle();
    bindComposeSmsPreviewRefresh();

    if (cfg.flashSuccess) {
        stUi.notify({
            icon: 'success',
            title: 'تیکت',
            text: cfg.flashSuccess,
            timer: 2200,
            showConfirmButton: false,
        }, { closeDialogIds: ['tk-compose-dialog', 'tk-detail-dialog'] });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAdminTickets);
} else {
    bootAdminTickets();
}
