import { bindAjaxPagination } from './mg-pagination-ajax.js';
import { SupportTicketUi, parseJsonResponse } from './support-ticket-ui.js';

function getConfig() {
    return window.__UT_PAGE__ || {};
}

function syncComposeBody() {
    if (typeof window.syncUserTicketComposeEditor === 'function') {
        window.syncUserTicketComposeEditor();
    }
}

function syncReplyBody() {
    if (typeof window.syncUserTicketReplyEditor === 'function') {
        window.syncUserTicketReplyEditor();
    }
}

function hasEditorContent(elementId) {
    if (elementId === 'ut-reply-body') {
        syncReplyBody();
    } else if (elementId === 'ut-compose-body') {
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

function bootUserTickets() {
    const cfg = getConfig();
    const stUi = SupportTicketUi;
    const ticketsBase = cfg.ticketsBase || '';
    const routes = {
        list: cfg.listUrl || '',
        store: cfg.storeUrl || '',
        show: function (id) {
            return ticketsBase + '/' + id;
        },
        reply: function (id) {
            return ticketsBase + '/' + id + '/reply';
        },
    };
    const csrf = cfg.csrf || '';
    const state = { tab: 'received', q: '', page: 1, perPage: 15, activeId: null };

    function esc(s) {
        return stUi.esc(s);
    }

    function setTab(tab) {
        state.tab = tab;
        state.page = 1;
        document.querySelectorAll('[data-ut-tab]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-ut-tab') === tab);
        });
        loadList();
    }

    function loadList() {
        document.getElementById('ut-loading').hidden = false;
        document.getElementById('ut-table').hidden = true;
        document.getElementById('ut-empty').hidden = true;
        let url = routes.list
            + '?tab=' + encodeURIComponent(state.tab)
            + '&page=' + state.page
            + '&per_page=' + encodeURIComponent(String(state.perPage || 15));
        if (state.q) {
            url += '&q=' + encodeURIComponent(state.q);
        }
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(parseJsonResponse)
            .then(function (json) {
                document.getElementById('ut-loading').hidden = true;
                renderRows(json.data || []);
                renderPagination(json.pagination_html || '', json.meta || {});
            })
            .catch(function (err) {
                document.getElementById('ut-loading').textContent = err.message || 'خطا در بارگذاری.';
            });
    }

    function renderRows(rows) {
        const tbody = document.getElementById('ut-tbody');
        const cards = document.getElementById('ut-cards');
        tbody.innerHTML = '';
        cards.innerHTML = '';
        if (!rows.length) {
            document.getElementById('ut-empty').hidden = false;
            return;
        }
        document.getElementById('ut-table').hidden = false;
        rows.forEach(function (row) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="ut-dt">' + esc(row.datetime_fa) + '</td>' +
                '<td class="ut-subject" title="' + esc(row.subject) + '">' + esc(row.subject) + '</td>' +
                '<td><span class="ut-status">' + esc(row.status_label) + '</span></td>' +
                '<td class="ut-excerpt" title="' + esc(row.excerpt) + '">' + esc(row.excerpt) + '</td>' +
                '<td>' + (row.has_attachment ? '<i class="fa-solid fa-paperclip"></i>' : '—') + '</td>' +
                '<td><button type="button" class="ut-btn ut-btn--ghost" data-ut-view="' + row.id + '">مشاهده</button></td>';
            tbody.appendChild(tr);
            const card = document.createElement('article');
            card.className = 'ut-card';
            card.innerHTML =
                '<h3>' + esc(row.subject) + '</h3>' +
                '<p>' + esc(row.datetime_fa) + ' · ' + esc(row.status_label) + '</p>' +
                '<p>' + esc(row.excerpt) + '</p>' +
                '<div class="ut-card__foot"><button type="button" class="ut-btn ut-btn--ghost" data-ut-view="' + row.id + '">مشاهده جزئیات</button></div>';
            cards.appendChild(card);
        });
        document.querySelectorAll('[data-ut-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openDetail(btn.getAttribute('data-ut-view'));
            });
        });
    }

    function renderPagination(html, meta) {
        const wrap = document.getElementById('ut-pagination-wrap');
        if (!wrap) {
            return;
        }
        const hasRows = meta && (meta.total || 0) > 0;
        if (!hasRows) {
            wrap.innerHTML = '';
            wrap.hidden = true;
            return;
        }
        wrap.hidden = false;
        wrap.innerHTML = html || '';
        bindAjaxPagination(wrap, {
            onPage: function (page) {
                state.page = page;
                loadList();
            },
            onPerPage: function (perPage) {
                state.perPage = perPage;
                state.page = 1;
                loadList();
            },
        });
    }

    function renderDetail(t) {
        const body = document.getElementById('ut-detail-body');
        if (!body || !t) {
            return;
        }
        let html = '<div class="st-detail-meta">';
        html += '<div class="st-detail-meta-item"><span>موضوع</span><strong>' + esc(t.subject) + '</strong></div>';
        html += '<div class="st-detail-meta-item"><span>وضعیت</span><strong>' + esc(t.status_label) + '</strong></div>';
        html += '</div>';
        html += stUi.renderChatHtml(t.messages || [], esc);
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
        document.getElementById('ut-detail-title').textContent = t.subject || 'جزئیات';
        const rw = document.getElementById('ut-reply-wrap');
        rw.hidden = !t.can_reply;
        if (t.can_reply) {
            if (typeof window.destroyUserTicketReplyEditor === 'function') {
                window.destroyUserTicketReplyEditor();
            }
            if (typeof window.initUserTicketReplyEditor === 'function') {
                window.initUserTicketReplyEditor();
            }
        }
    }

    function openDetail(id) {
        state.activeId = id;
        fetch(routes.show(id) + '?tab=' + encodeURIComponent(state.tab), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        })
            .then(parseJsonResponse)
            .then(function (json) {
                renderDetail(json.ticket);
                document.getElementById('ut-detail-dialog').showModal();
            })
            .catch(function (err) {
                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: [] });
            });
    }

    document.querySelectorAll('[data-ut-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
            setTab(b.getAttribute('data-ut-tab'));
        });
    });
    document.getElementById('ut-search-btn')?.addEventListener('click', function () {
        state.q = document.getElementById('ut-search-input').value.trim();
        state.page = 1;
        loadList();
    });

    document.getElementById('ut-open-compose')?.addEventListener('click', function () {
        document.getElementById('ut-compose-form').reset();
        const bodyTa = document.getElementById('ut-compose-body');
        if (bodyTa) {
            bodyTa.removeAttribute('required');
        }
        document.getElementById('ut-compose-dialog').showModal();
        if (typeof window.initUserTicketComposeEditor === 'function') {
            window.initUserTicketComposeEditor();
        }
    });

    document.querySelectorAll('[data-ut-close-compose]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('ut-compose-dialog').close();
            if (typeof window.destroyUserTicketComposeEditor === 'function') {
                window.destroyUserTicketComposeEditor();
            }
        });
    });
    document.querySelectorAll('[data-ut-close-detail]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('ut-detail-dialog').close();
        });
    });

    document.getElementById('ut-compose-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        stUi.clearInlineAlert('ut-compose-scroll');
        if (!hasEditorContent('ut-compose-body')) {
            stUi.showInlineAlert('ut-compose-scroll', 'متن تیکت الزامی است.');
            return;
        }
        const submitBtn = document.getElementById('ut-compose-submit');
        stUi.setBtnLoading(submitBtn, true);
        fetch(routes.store, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new FormData(e.target),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                document.getElementById('ut-compose-dialog').close();
                if (typeof window.destroyUserTicketComposeEditor === 'function') {
                    window.destroyUserTicketComposeEditor();
                }
                setTab('sent');
                stUi.notify({
                    icon: 'success',
                    title: 'ارسال تیکت',
                    text: data.message || 'تیکت با موفقیت ثبت شد.',
                    timer: 2200,
                    showConfirmButton: false,
                }, { closeDialogIds: ['ut-compose-dialog', 'ut-detail-dialog'] });
            })
            .catch(function (err) {
                stUi.notify({
                    icon: 'error',
                    title: 'خطا',
                    text: err.message || 'خطا',
                }, { closeDialogIds: ['ut-compose-dialog'] });
            })
            .finally(function () {
                stUi.setBtnLoading(submitBtn, false);
            });
    });

    document.getElementById('ut-reply-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!state.activeId) {
            stUi.notify({ icon: 'warning', title: 'پاسخ', text: 'تیکت انتخاب نشده است.' }, { closeDialogIds: [] });
            return;
        }
        stUi.clearInlineAlert('ut-reply-wrap');
        if (!hasEditorContent('ut-reply-body')) {
            stUi.showInlineAlert('ut-reply-wrap', 'متن پاسخ الزامی است.');
            return;
        }
        const replyBtn = document.getElementById('ut-reply-submit');
        const form = e.target;
        stUi.setBtnLoading(replyBtn, true);
        fetch(routes.reply(state.activeId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new FormData(form),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                renderDetail(data.ticket);
                form.reset();
                if (typeof window.destroyUserTicketReplyEditor === 'function') {
                    window.destroyUserTicketReplyEditor();
                }
                if (data.ticket && data.ticket.can_reply && typeof window.initUserTicketReplyEditor === 'function') {
                    window.initUserTicketReplyEditor();
                }
                loadList();
                stUi.notify({
                    icon: 'success',
                    title: 'پاسخ',
                    text: data.message || 'پاسخ ثبت شد.',
                    timer: 2000,
                    showConfirmButton: false,
                }, { closeAllTicketDialogs: true });
            })
            .catch(function (err) {
                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeAllTicketDialogs: true });
            })
            .finally(function () {
                stUi.setBtnLoading(replyBtn, false);
            });
    });

    const qp = new URLSearchParams(window.location.search);
    if (qp.get('tab') === 'sent' || qp.get('tab') === 'received') {
        setTab(qp.get('tab'));
    } else {
        loadList();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootUserTickets);
} else {
    bootUserTickets();
}
