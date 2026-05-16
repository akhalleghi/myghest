# -*- coding: utf-8 -*-
from pathlib import Path

p = Path("resources/views/user/portal/tickets.blade.php")
s = p.read_text(encoding="utf-8")

if "@include('partials.support-ticket-chat-styles')" not in s:
    s = s.replace(
        "@push('head')\n    <style>",
        "@push('head')\n    @include('partials.support-ticket-chat-styles')\n    <style>",
    )

s = s.replace(
    "        .ut-detail-reply { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--border); }\n        .ut-pagination",
    "        .ut-dialog-inner--detail { display: flex; flex-direction: column; flex: 1; min-height: 0; }\n        .ut-pagination",
)

s = s.replace(
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <motion.div class="ut-dialog-scroll">""",
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <div class="ut-dialog-scroll" id="ut-compose-scroll">""",
).replace("<motion.div", "<motion.div")  # noop if no motion

s = s.replace(
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <div class="ut-dialog-scroll">""",
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <motion.div class="ut-dialog-scroll" id="ut-compose-scroll">""",
)
s = s.replace(
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <motion.div class="ut-dialog-scroll" id="ut-compose-scroll">""",
    """            <form id="ut-compose-form" enctype="multipart/form-data" novalidate>
                <div class="ut-dialog-scroll" id="ut-compose-scroll">""",
)

s = s.replace(
    """                    <button type="submit" class="ut-btn ut-btn--pri">ارسال</button>""",
    """                    <button type="submit" class="ut-btn ut-btn--pri" id="ut-compose-submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال
                    </button>""",
)

old_detail = """    <dialog id="ut-detail-dialog">
        <div class="ut-dialog-inner">
            <button type="button" class="ut-dialog-close" data-ut-close-detail aria-label="بستن">&times;</button>
            <div class="ut-dialog-head"><h2 class="ut-dialog-title" id="ut-detail-title">جزئیات</h2></div>
            <div class="ut-dialog-scroll" id="ut-detail-body"></div>
            <div class="ut-detail-reply" id="ut-reply-wrap" hidden>
                <form id="ut-reply-form" enctype="multipart/form-data" novalidate>
                    <div class="ut-field ut-ck-wrap">
                        <label for="ut-reply-body">پاسخ شما</label>
                        <textarea id="ut-reply-body" name="body_html" rows="4"></textarea>
                    </div>
                    <div class="ut-field">
                        <label for="ut-reply-file">ضمیمه</label>
                        <input type="file" id="ut-reply-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                    </div>
                    <button type="submit" class="ut-btn ut-btn--pri"><i class="fa-solid fa-reply"></i> ارسال پاسخ</button>
                </form>
            </div>
            <div class="ut-dialog-footer">
                <button type="button" class="ut-btn ut-btn--ghost" data-ut-close-detail>بستن</button>
            </div>
        </div>
    </dialog>"""

new_detail = """    <dialog id="ut-detail-dialog">
        <div class="ut-dialog-inner ut-dialog-inner--detail">
            <button type="button" class="ut-dialog-close" data-ut-close-detail aria-label="بستن">&times;</button>
            <div class="ut-dialog-head"><h2 class="ut-dialog-title" id="ut-detail-title">جزئیات</h2></div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="ut-detail-body"></div>
                <div class="st-detail-reply-zone" id="ut-reply-wrap" hidden>
                    <form id="ut-reply-form" enctype="multipart/form-data" novalidate>
                        <div class="ut-field ut-ck-wrap">
                            <label for="ut-reply-body">پاسخ شما</label>
                            <textarea id="ut-reply-body" name="body_html" rows="3"></textarea>
                        </div>
                        <div class="ut-field">
                            <label for="ut-reply-file">ضمیمه (اختیاری)</label>
                            <input type="file" id="ut-reply-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        </div>
                        <button type="submit" class="ut-btn ut-btn--pri" id="ut-reply-submit">
                            <i class="fa-solid fa-reply" aria-hidden="true"></i>
                            ارسال پاسخ
                        </button>
                    </form>
                </div>
            </div>
            <div class="ut-dialog-footer">
                <button type="button" class="ut-btn ut-btn--ghost" data-ut-close-detail>بستن</button>
            </motion.div>
        </div>
    </dialog>"""
new_detail = new_detail.replace("</motion.div>\n        </div>", "            </div>")

if old_detail not in s:
    raise SystemExit("user detail block not found")
s = s.replace(old_detail, new_detail, 1)

s = s.replace(
    "@vite(['resources/js/admin-tickets-ckeditor.js'])",
    "@vite(['resources/js/support-ticket-ui.js', 'resources/js/admin-tickets-ckeditor.js'])",
)

s = s.replace(
    "        var csrf = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '';\n        var state = { tab: 'received', q: '', page: 1, activeId: null };",
    "        var csrf = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '';\n        var stUi = window.SupportTicketUi;\n        var state = { tab: 'received', q: '', page: 1, activeId: null };",
)

old_render = """        function renderDetail(t) {
            var body = document.getElementById('ut-detail-body');
            var html = '<div class="ut-detail-meta">';
            html += '<div><span>موضوع</span><strong>' + esc(t.subject) + '</strong></div>';
            html += '<div><span>وضعیت</span><strong>' + esc(t.status_label) + '</strong></div>';
            html += '</div>';
            (t.messages || []).forEach(function (msg) {
                html += '<div style="margin-bottom:0.75rem">';
                html += '<p style="font-size:0.72rem;font-weight:800;color:var(--muted)">' + esc(msg.sender_label) + ' — ' + esc(msg.datetime_fa) + '</p>';
                html += '<div class="ut-detail-body">' + (msg.body_html || '') + '</div>';
                if (msg.attachments && msg.attachments.length) {
                    html += '<motion.div class="ut-detail-att">';
                    msg.attachments.forEach(function (a) {
                        html += '<a href="' + esc(a.url) + '" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> ' + esc(a.name) + '</a>';
                    });
                    html += '</motion.div>';
                }
                html += '</div>';
            });
            body.innerHTML = html;
            document.getElementById('ut-detail-title').textContent = t.subject || 'جزئیات';
            var rw = document.getElementById('ut-reply-wrap');
            rw.hidden = !t.can_reply;
            if (t.can_reply && typeof window.initUserTicketReplyEditor === 'function') {
                if (typeof window.destroyUserTicketReplyEditor === 'function') {
                    window.destroyUserTicketReplyEditor();
                }
                window.initUserTicketReplyEditor();
            }
        }"""
old_render = old_render.replace("motion.div", "div")

new_render = """        function renderDetail(t) {
            var body = document.getElementById('ut-detail-body');
            var html = '<div class="st-detail-meta">';
            html += '<div class="st-detail-meta-item"><span>موضوع</span><strong>' + esc(t.subject) + '</strong></div>';
            html += '<div class="st-detail-meta-item"><span>وضعیت</span><strong>' + esc(t.status_label) + '</strong></div>';
            html += '</div>';
            if (stUi && stUi.renderChatHtml) {
                html += stUi.renderChatHtml(t.messages || [], esc);
            }
            body.innerHTML = html;
            body.scrollTop = body.scrollHeight;
            document.getElementById('ut-detail-title').textContent = t.subject || 'جزئیات';
            var rw = document.getElementById('ut-reply-wrap');
            rw.hidden = !t.can_reply;
            if (t.can_reply && typeof window.initUserTicketReplyEditor === 'function') {
                if (typeof window.destroyUserTicketReplyEditor === 'function') {
                    window.destroyUserTicketReplyEditor();
                }
                window.initUserTicketReplyEditor();
            }
        }"""

if old_render not in s:
    # try without motion typo
    old_render = old_render.replace('<motion.div class="ut-detail-att">', '<motion.div class="ut-detail-att">')
    raise SystemExit("user renderDetail not found")

s = s.replace(old_render, new_render, 1)

s = s.replace(
    """        document.getElementById('ut-compose-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            var hasBody = typeof window.syncTicketEditorField === 'function'
                ? window.syncTicketEditorField('ut-compose-body')
                : true;
            if (!hasBody) {
                alert('متن تیکت الزامی است.');
                return;
            }
            var fd = new FormData(e.target);
            fetch(routes.store, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.j.message || 'خطا');
                    document.getElementById('ut-compose-dialog').close();
                    setTab('sent');
                    alert(res.j.message || 'ثبت شد');
                })
                .catch(function (err) { alert(err.message || 'خطا'); });
        });""",
    """        document.getElementById('ut-compose-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (stUi) stUi.clearInlineAlert('ut-compose-scroll');
            var hasBody = typeof window.syncTicketEditorField === 'function'
                ? window.syncTicketEditorField('ut-compose-body')
                : true;
            if (!hasBody) {
                if (stUi) stUi.showInlineAlert('ut-compose-scroll', 'متن تیکت الزامی است.');
                return;
            }
            var submitBtn = document.getElementById('ut-compose-submit');
            if (stUi) stUi.setBtnLoading(submitBtn, true);
            var fd = new FormData(e.target);
            fetch(routes.store, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.j.message || 'خطا');
                    document.getElementById('ut-compose-dialog').close();
                    if (typeof window.destroyUserTicketComposeEditor === 'function') {
                        window.destroyUserTicketComposeEditor();
                    }
                    setTab('sent');
                    if (stUi) {
                        stUi.notify({
                            icon: 'success',
                            title: 'ارسال تیکت',
                            text: res.j.message || 'تیکت با موفقیت ثبت شد.',
                            timer: 2200,
                            showConfirmButton: false,
                        }, { closeDialogIds: ['ut-compose-dialog', 'ut-detail-dialog'] });
                    }
                })
                .catch(function (err) {
                    if (stUi) {
                        stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: ['ut-compose-dialog'] });
                    }
                })
                .finally(function () {
                    if (stUi) stUi.setBtnLoading(submitBtn, false);
                });
        });""",
)

s = s.replace(
    """        document.getElementById('ut-reply-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!state.activeId) return;
            var hasReply = typeof window.syncTicketEditorField === 'function'
                ? window.syncTicketEditorField('ut-reply-body')
                : true;
            if (!hasReply) {
                alert('متن پاسخ الزامی است.');
                return;
            }
            var fd = new FormData(e.target);
            fetch(routes.reply(state.activeId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.j.message || 'خطا');
                    renderDetail(res.j.ticket);
                    e.target.reset();
                    loadList();
                    alert(res.j.message || 'پاسخ ثبت شد');
                })
                .catch(function (err) { alert(err.message || 'خطا'); });
        });""",
    """        document.getElementById('ut-reply-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!state.activeId) return;
            if (stUi) stUi.clearInlineAlert('ut-reply-wrap');
            var hasReply = typeof window.syncTicketEditorField === 'function'
                ? window.syncTicketEditorField('ut-reply-body')
                : true;
            if (!hasReply) {
                if (stUi) stUi.showInlineAlert('ut-reply-wrap', 'متن پاسخ الزامی است.');
                return;
            }
            var replyBtn = document.getElementById('ut-reply-submit');
            if (stUi) stUi.setBtnLoading(replyBtn, true);
            var fd = new FormData(e.target);
            fetch(routes.reply(state.activeId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.j.message || 'خطا');
                    renderDetail(res.j.ticket);
                    e.target.reset();
                    if (typeof window.destroyUserTicketReplyEditor === 'function') {
                        window.destroyUserTicketReplyEditor();
                    }
                    loadList();
                    if (stUi) {
                        stUi.notify({
                            icon: 'success',
                            title: 'پاسخ',
                            text: res.j.message || 'پاسخ ثبت شد.',
                            timer: 2000,
                            showConfirmButton: false,
                        }, { closeDialogIds: [] });
                    }
                })
                .catch(function (err) {
                    if (stUi) {
                        stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: [] });
                    }
                })
                .finally(function () {
                    if (stUi) stUi.setBtnLoading(replyBtn, false);
                });
        });""",
)

p.write_text(s, encoding="utf-8")
print("user tickets ok")
