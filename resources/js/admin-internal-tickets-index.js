import { bindAjaxPagination } from './mg-pagination-ajax.js';
import { SupportTicketUi, parseJsonResponse } from './support-ticket-ui.js';

function getConfig() {
    return window.__ITK_PAGE__ || {};
}

function syncComposeBody() {
    if (typeof window.syncItkComposeEditor === 'function') {
        window.syncItkComposeEditor();
    }
}

function syncReplyBody() {
    if (typeof window.syncItkReplyEditor === 'function') {
        window.syncItkReplyEditor();
    }
}

function hasEditorContent(elementId) {
    if (elementId === 'itk-reply-body') {
        syncReplyBody();
    } else if (elementId === 'itk-compose-body') {
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

function bootInternalTickets() {
    const cfg = getConfig();
    const stUi = SupportTicketUi;
    const composeDialog = document.getElementById('itk-compose-dialog');
    const detailDialog = document.getElementById('itk-detail-dialog');
    const composeForm = document.getElementById('itk-compose-form');
    let snapshots = Object.assign({}, cfg.snapshots || {});
    const adminSearchUrl = cfg.adminSearchUrl || '';
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
        document.querySelectorAll('.itk-view-btn').forEach(function (btn) {
            if (btn.dataset.itkBound === '1') {
                return;
            }
            btn.dataset.itkBound = '1';
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-ticket-id');
                if (id) {
                    openDetail(id);
                }
            });
        });
    }

    function updateTabBadges(receivedCount, sentCount) {
        document.querySelectorAll('.itk-tab').forEach(function (tabEl) {
            const href = tabEl.getAttribute('href') || '';
            const badge = tabEl.querySelector('.itk-tab-badge');
            if (!badge) {
                return;
            }
            if (href.indexOf('tab=sent') !== -1) {
                badge.textContent = String(sentCount ?? badge.textContent);
            } else if (href.indexOf('tab=received') !== -1) {
                badge.textContent = String(receivedCount ?? badge.textContent);
            }
        });
        document.querySelectorAll('.itk-tab').forEach(function (tabEl) {
            const href = tabEl.getAttribute('href') || '';
            const isSent = href.indexOf('tab=sent') !== -1;
            const isActive = (listState.tab === 'sent' && isSent) || (listState.tab === 'received' && !isSent);
            tabEl.classList.toggle('is-active', isActive);
        });
    }

    function emptyTableMessage() {
        if (listState.tab === 'sent') {
            return 'هنوز تیکت داخلی ارسال نکرده‌اید. با دکمه «ارسال تیکت جدید» اولین تیکت را بفرستید.';
        }
        return 'تیکت دریافتی ثبت نشده است. تیکت‌های ارسالی از سمت سایر ادمین‌ها در این بخش نمایش داده می‌شوند.';
    }

    function renderTicketsTable(payload) {
        const root = document.getElementById('itk-table-root');
        const pagWrap = document.getElementById('itk-pagination-wrap');
        if (!root) {
            return;
        }
        const rows = payload.data || [];
        const partyLabel = payload.party_column_label || (listState.tab === 'sent' ? 'گیرنده' : 'فرستنده');
        if (!rows.length) {
            root.innerHTML =
                '<div class="itk-empty" id="itk-empty">'
                + '<i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>'
                + esc(emptyTableMessage())
                + '</div>';
            if (pagWrap) {
                pagWrap.innerHTML = '';
            }
            return;
        }
        let html = '<table class="itk-tbl"><thead><tr>'
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
            let stClass = 'itk-status';
            if (st === 'closed') {
                stClass += ' itk-status--closed';
            } else if (st === 'on_hold') {
                stClass += ' itk-status--hold';
            }
            html += '<tr>';
            html += '<td class="itk-dt">' + esc(row.datetime_fa) + '</td>';
            html += '<td class="itk-party" title="' + esc(row.party_label) + '">' + esc(row.party_label) + '</td>';
            html += '<td class="itk-subject" title="' + esc(row.subject) + '">' + esc(row.subject) + '</td>';
            html += '<td><span class="' + stClass + '">' + esc(row.status_label || st) + '</span></td>';
            html += '<td class="itk-excerpt" title="' + esc(row.excerpt) + '">' + esc(row.excerpt) + '</td>';
            html += '<td>';
            if (row.has_attachment) {
                html += '<span class="itk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>';
            } else {
                html += '<span class="itk-dt">—</span>';
            }
            html += '</td>';
            html += '<td><button type="button" class="itk-btn itk-btn--ghost itk-view-btn" data-ticket-id="' + esc(String(row.id)) + '">'
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
        const wrap = document.getElementById('itk-wrap');
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
        if (typeof window.destroyItkComposeEditor === 'function') {
            window.destroyItkComposeEditor();
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
        const pSingle = document.getElementById('itk-panel-single');
        const pMulti = document.getElementById('itk-panel-multiple');
        const pAll = document.getElementById('itk-panel-all');
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
            dropdownParent: window.jQuery('#itk-compose-dialog'),
            placeholder: multiple ? 'جستجو و انتخاب چند ادمین…' : 'جستجو و انتخاب ادمین…',
            allowClear: !multiple,
            multiple: !!multiple,
            minimumInputLength: 0,
            ajax: {
                url: adminSearchUrl,
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

    function rebuildHiddenAdminIds() {
        const wrap = document.getElementById('itk-admin-ids-hidden');
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
            inp.name = 'admin_ids[]';
            inp.value = id;
            wrap.appendChild(inp);
        });
    }

    function renderDetail(snapshot) {
        const body = document.getElementById('itk-detail-body');
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
            html += '<div class="itk-status-row">';
            html += '<label for="itk-status-select" style="font-size:0.74rem;font-weight:800;color:var(--muted)">تغییر وضعیت</label>';
            html += '<select id="itk-status-select">';
            Object.keys(snapshot.status_options).forEach(function (key) {
                const sel = key === snapshot.status ? ' selected' : '';
                html += '<option value="' + esc(key) + '"' + sel + '>' + esc(snapshot.status_options[key]) + '</option>';
            });
            html += '</select>';
            html += '<button type="button" class="itk-btn itk-btn--ghost" id="itk-status-save">ذخیره وضعیت</button>';
            html += '</div>';
        }
        html += stUi.renderChatHtml(snapshot.messages || [], esc);
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
        const title = document.getElementById('itk-detail-title-text');
        if (title) {
            title.textContent = snapshot.subject || 'جزئیات تیکت';
        }
    }

    function scrollConversationToLatest() {
        const body = document.getElementById('itk-detail-body');
        if (!body) {
            return;
        }
        requestAnimationFrame(function () {
            body.scrollTop = body.scrollHeight;
        });
    }

    function setReplyVisible(show) {
        const wrap = document.getElementById('itk-detail-reply-wrap');
        if (wrap) {
            wrap.hidden = !show;
        }
        if (show && typeof window.initItkReplyEditor === 'function') {
            window.initItkReplyEditor();
        } else if (!show && typeof window.destroyItkReplyEditor === 'function') {
            window.destroyItkReplyEditor();
        }
    }

    function bindStatusSave() {
        const btn = document.getElementById('itk-status-save');
        const sel = document.getElementById('itk-status-select');
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
        bindStatusSave();
    }

    function openDetail(id) {
        const showUrl = ticketsAdminBase + '/' + encodeURIComponent(String(id));
        fetch(showUrl, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(parseJsonResponse)
            .then(function (data) {
                if (data.ticket) {
                    snapshots[String(id)] = data.ticket;
                    showTicketDetail(data.ticket);
                } else {
                    const snap = snapshots[String(id)] || snapshots[id];
                    if (!snap) {
                        return;
                    }
                    showTicketDetail(snap);
                }
                if (detailDialog && typeof detailDialog.showModal === 'function') {
                    detailDialog.showModal();
                    scrollConversationToLatest();
                }
            })
            .catch(function (err) {
                const snap = snapshots[String(id)] || snapshots[id];
                if (snap) {
                    showTicketDetail(snap);
                    if (detailDialog && typeof detailDialog.showModal === 'function') {
                        detailDialog.showModal();
                        scrollConversationToLatest();
                    }
                    return;
                }
                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا در بارگذاری تیکت' }, { closeDialogIds: [] });
            });
    }

    function openCompose() {
        if (!composeDialog || typeof composeDialog.showModal !== 'function') {
            return;
        }
        if (composeForm) {
            composeForm.reset();
        }
        const bodyTa = document.getElementById('itk-compose-body');
        if (bodyTa) {
            bodyTa.removeAttribute('required');
        }
        if ($single) {
            $single.val(null).trigger('change');
        }
        if ($multi) {
            $multi.val(null).trigger('change');
        }
        rebuildHiddenAdminIds();
        syncRecipientPanels();
        composeDialog.showModal();
        if (typeof window.initItkComposeEditor === 'function') {
            window.initItkComposeEditor();
        }
    }

    document.getElementById('itk-open-compose')?.addEventListener('click', openCompose);
    document.querySelectorAll('[data-itk-close-compose]').forEach(function (b) {
        b.addEventListener('click', closeCompose);
    });
    document.querySelectorAll('[data-itk-close-detail]').forEach(function (b) {
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
        $single = initSelect2(window.jQuery('#itk-admin-single'), false);
        $multi = initSelect2(window.jQuery('#itk-admin-multiple'), true);
    }

    composeForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        stUi.clearInlineAlert('itk-compose-scroll');
        if (!hasEditorContent('itk-compose-body')) {
            stUi.showInlineAlert('itk-compose-scroll', 'متن تیکت الزامی است.');
            return;
        }
        const mode = currentRecipientMode();
        rebuildHiddenAdminIds();
        if (mode !== 'all') {
            const wrap = document.getElementById('itk-admin-ids-hidden');
            if (!wrap || !wrap.children.length) {
                const msg = mode === 'single' ? 'یک گیرنده انتخاب کنید.' : 'حداقل یک گیرنده انتخاب کنید.';
                stUi.showInlineAlert('itk-compose-scroll', msg);
                return;
            }
        }
        const submitBtn = document.getElementById('itk-compose-submit');
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
                    title: 'ارسال تیکت داخلی',
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
                }, { closeDialogIds: ['itk-compose-dialog'] });
            })
            .finally(function () {
                stUi.setBtnLoading(submitBtn, false);
            });
    });

    bindViewButtons();

    document.getElementById('itk-reply-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!activeTicketId) {
            stUi.notify({ icon: 'warning', title: 'پاسخ', text: 'تیکت انتخاب نشده است.' }, { closeDialogIds: [] });
            return;
        }
        stUi.clearInlineAlert('itk-detail-reply-wrap');
        if (!hasEditorContent('itk-reply-body')) {
            stUi.showInlineAlert('itk-detail-reply-wrap', 'متن پاسخ الزامی است.');
            return;
        }
        const replyBtn = document.getElementById('itk-reply-submit');
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
                if (typeof window.destroyItkReplyEditor === 'function') {
                    window.destroyItkReplyEditor();
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

    if (cfg.flashSuccess) {
        stUi.notify({
            icon: 'success',
            title: 'تیکت داخلی',
            text: cfg.flashSuccess,
            timer: 2200,
            showConfirmButton: false,
        }, { closeDialogIds: ['itk-compose-dialog', 'itk-detail-dialog'] });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootInternalTickets);
} else {
    bootInternalTickets();
}
