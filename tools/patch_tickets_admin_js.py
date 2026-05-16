# -*- coding: utf-8 -*-
from pathlib import Path
import re

p = Path("resources/views/admin/tickets/index.blade.php")
s = p.read_text(encoding="utf-8")

s = s.replace(
    "            var $multi = null;\n\n            function esc(s) {",
    "            var $multi = null;\n            var stUi = window.SupportTicketUi;\n            var composeStoreUrl = @json(route('admin.tickets.store'));\n\n            function esc(s) {",
)

old_render = """            function renderDetail(snapshot) {
                var body = document.getElementById('tk-detail-body');
                if (!body || !snapshot) return;
                var partyLabel = snapshot.list_type === 'sent' ? 'گیرنده' : 'فرستنده';
                var html = '';
                html += '<motion.div class="tk-detail-meta">';
                html += '<motion.div class="tk-detail-meta-item"><span>موضوع</span><strong>' + esc(snapshot.subject) + '</strong></motion.div>';
                html += '<motion.div class="tk-detail-meta-item"><span>' + esc(partyLabel) + '</span><strong>' + esc(snapshot.party_label) + '</strong></motion.div>';
                html += '<motion.div class="tk-detail-meta-item"><span>وضعیت</span><strong>' + esc(snapshot.status_label || snapshot.status) + '</strong></motion.div>';
                html += '<motion.div class="tk-detail-meta-item"><span>تاریخ</span><strong>' + esc(snapshot.datetime_fa) + '</strong></motion.div>';
                html += '</motion.div>';
                if (snapshot.status_options) {
                    html += '<motion.div class="tk-status-row">';
                    html += '<label for="tk-status-select" style="font-size:0.74rem;font-weight:800;color:var(--muted)">تغییر وضعیت</label>';
                    html += '<select id="tk-status-select">';
                    Object.keys(snapshot.status_options).forEach(function (key) {
                        var sel = key === snapshot.status ? ' selected' : '';
                        html += '<option value="' + esc(key) + '"' + sel + '>' + esc(snapshot.status_options[key]) + '</option>';
                    });
                    html += '</select>';
                    html += '<button type="button" class="tk-btn tk-btn--ghost" id="tk-status-save">ذخیره وضعیت</button>';
                    html += '</motion.div>';
                }
                var messages = snapshot.messages || [];
                messages.forEach(function (msg) {
                    html += '<motion.div style="margin-bottom:0.85rem">';
                    html += '<p style="margin:0 0 0.35rem;font-size:0.74rem;font-weight:800;color:var(--muted)">';
                    html += esc(msg.sender_label) + ' — ' + esc(msg.datetime_fa);
                    html += '</p>';
                    html += '<motion.div class="tk-detail-body">' + (msg.body_html || '') + '</motion.div>';
                    if (msg.attachments && msg.attachments.length) {
                        html += '<motion.div class="tk-detail-att">';
                        msg.attachments.forEach(function (att) {
                            html += '<a href="' + esc(att.url) + '" target="_blank" rel="noopener">';
                            html += '<i class="fa-solid fa-paperclip" aria-hidden="true"></i> ' + esc(att.name);
                            html += '</a>';
                        });
                        html += '</motion.div>';
                    }
                    html += '</motion.div>';
                });
                body.innerHTML = html;
                var title = document.getElementById('tk-detail-title');
                if (title) title.textContent = snapshot.subject || 'جزئیات تیکت';
            }"""

old_render = old_render.replace("motion.div", "motion.div")
# fix to div
old_render = re.sub(r"</?motion\.motion.div>", lambda m: "</div>" if m.group().startswith("</") else "<div>", old_render)
old_render = old_render.replace("<motion.div", "<motion.div").replace("motion.div", "div")
old_render = old_render.replace("<motion.div", "<div").replace("</motion.div>", "</div>")

new_render = """            function renderDetail(snapshot) {
                var body = document.getElementById('tk-detail-body');
                if (!body || !snapshot) return;
                var partyLabel = snapshot.list_type === 'sent' ? 'گیرنده' : 'فرستنده';
                var html = '';
                html += '<div class="st-detail-meta">';
                html += '<div class="st-detail-meta-item"><span>موضوع</span><strong>' + esc(snapshot.subject) + '</strong></div>';
                html += '<div class="st-detail-meta-item"><span>' + esc(partyLabel) + '</span><strong>' + esc(snapshot.party_label) + '</strong></motion.div>';
                html += '<div class="st-detail-meta-item"><span>وضعیت</span><strong>' + esc(snapshot.status_label || snapshot.status) + '</strong></motion.div>';
                html += '<div class="st-detail-meta-item"><span>تاریخ</span><strong>' + esc(snapshot.datetime_fa) + '</strong></motion.div>';
                html += '</motion.div>';
                if (snapshot.status_options) {
                    html += '<div class="tk-status-row">';
                    html += '<label for="tk-status-select" style="font-size:0.74rem;font-weight:800;color:var(--muted)">تغییر وضعیت</label>';
                    html += '<select id="tk-status-select">';
                    Object.keys(snapshot.status_options).forEach(function (key) {
                        var sel = key === snapshot.status ? ' selected' : '';
                        html += '<option value="' + esc(key) + '"' + sel + '>' + esc(snapshot.status_options[key]) + '</option>';
                    });
                    html += '</select>';
                    html += '<button type="button" class="tk-btn tk-btn--ghost" id="tk-status-save">ذخیره وضعیت</button>';
                    html += '</motion.div>';
                }
                if (stUi && stUi.renderChatHtml) {
                    html += stUi.renderChatHtml(snapshot.messages || [], esc);
                }
                body.innerHTML = html;
                body.scrollTop = body.scrollHeight;
                var title = document.getElementById('tk-detail-title');
                if (title) title.textContent = snapshot.subject || 'جزئیات تیکت';
            }"""
new_render = new_render.replace("</motion.div>", "</div>").replace("<motion.div", "<div")

# find actual old_render in file - use simpler marker
start = s.find("            function renderDetail(snapshot) {")
end = s.find("            function openDetail(id) {")
if start == -1 or end == -1:
    raise SystemExit("renderDetail block not found")
s = s[:start] + new_render + "\n\n" + s[end:]

old_compose_submit = """            composeForm?.addEventListener('submit', function (e) {
                if (typeof window.syncAdminTicketComposeEditor === 'function') {
                    window.syncAdminTicketComposeEditor();
                }
                var hasBody = typeof window.syncTicketEditorField === 'function'
                    ? window.syncTicketEditorField('tk-compose-body')
                    : true;
                if (!hasBody) {
                    e.preventDefault();
                    if (window.AdminSwal && AdminSwal.fire) {
                        AdminSwal.fire({ icon: 'warning', title: 'متن تیکت', text: 'متن تیکت الزامی است.' });
                    } else {
                        window.alert('متن تیکت الزامی است.');
                    }
                    return;
                }
                var mode = currentRecipientMode();
                if (mode === 'all') {
                    rebuildHiddenCustomerIds();
                    return;
                }
                rebuildHiddenCustomerIds();
                var wrap = document.getElementById('tk-customer-ids-hidden');
                if (!wrap || !wrap.children.length) {
                    e.preventDefault();
                    var msg = mode === 'single' ? 'یک گیرنده انتخاب کنید.' : 'حداقل یک گیرنده انتخاب کنید.';
                    if (window.AdminSwal && AdminSwal.fire) {
                        AdminSwal.fire({ icon: 'warning', title: 'گیرنده', text: msg });
                    } else {
                        window.alert(msg);
                    }
                }
            });"""

new_compose_submit = """            composeForm?.addEventListener('submit', function (e) {
                e.preventDefault();
                if (stUi) stUi.clearInlineAlert('tk-compose-scroll');
                if (typeof window.syncAdminTicketComposeEditor === 'function') {
                    window.syncAdminTicketComposeEditor();
                }
                var hasBody = typeof window.syncTicketEditorField === 'function'
                    ? window.syncTicketEditorField('tk-compose-body')
                    : true;
                if (!hasBody) {
                    if (stUi) stUi.showInlineAlert('tk-compose-scroll', 'متن تیکت الزامی است.');
                    return;
                }
                var mode = currentRecipientMode();
                rebuildHiddenCustomerIds();
                if (mode !== 'all') {
                    var wrap = document.getElementById('tk-customer-ids-hidden');
                    if (!wrap || !wrap.children.length) {
                        var msg = mode === 'single' ? 'یک گیرنده انتخاب کنید.' : 'حداقل یک گیرنده انتخاب کنید.';
                        if (stUi) stUi.showInlineAlert('tk-compose-scroll', msg);
                        return;
                    }
                }
                var submitBtn = document.getElementById('tk-compose-submit');
                if (stUi) stUi.setBtnLoading(submitBtn, true);
                var fd = new FormData(composeForm);
                fetch(composeStoreUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok) throw new Error((res.j && res.j.message) ? res.j.message : 'خطا در ارسال');
                        closeCompose();
                        if (stUi) {
                            stUi.notify({
                                icon: 'success',
                                title: 'ارسال تیکت',
                                text: res.j.message || 'تیکت با موفقیت ارسال شد.',
                                timer: 2200,
                                showConfirmButton: false,
                            }, { closeDialogIds: ['tk-compose-dialog', 'tk-detail-dialog'] });
                        }
                        if (res.j.redirect) {
                            window.setTimeout(function () { window.location.href = res.j.redirect; }, 400);
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        if (stUi) {
                            stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا در ارسال تیکت' }, { closeDialogIds: ['tk-compose-dialog'] });
                        }
                    })
                    .finally(function () {
                        if (stUi) stUi.setBtnLoading(submitBtn, false);
                    });
            });"""

if old_compose_submit not in s:
    raise SystemExit("compose submit not found")
s = s.replace(old_compose_submit, new_compose_submit, 1)

# Move csrf before compose submit - csrf is defined later! Need to move var csrf up or use inline in compose handler
# csrf is at line 553 - compose submit is at 510 - PROBLEM
# Fix: move csrf and ticketsAdminBase to top after stUi

csrf_block = """            var activeTicketId = null;
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var ticketsAdminBase = @json(url('admin/tickets'));"""

if csrf_block in s:
    s = s.replace(csrf_block, """            var activeTicketId = null;""", 1)
    s = s.replace(
        "            var composeStoreUrl = @json(route('admin.tickets.store'));\n",
        "            var composeStoreUrl = @json(route('admin.tickets.store'));\n            var csrf = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '';\n            var ticketsAdminBase = @json(url('admin/tickets'));\n",
    )

# status save loading + notify
s = s.replace(
    "                btn.onclick = function () {\n                    fetch(ticketsAdminBase",
    "                btn.onclick = function () {\n                    if (stUi) stUi.setBtnLoading(btn, true, 'در حال ذخیره…');\n                    fetch(ticketsAdminBase",
)
s = s.replace(
    """                            if (window.AdminSwal && AdminSwal.fire) {
                                AdminSwal.fire({ icon: 'success', title: 'وضعیت', text: res.j.message, timer: 1800, showConfirmButton: false });
                            }
                        })
                        .catch(function (err) {
                            if (window.AdminSwal && AdminSwal.fire) {
                                AdminSwal.fire({ icon: 'error', title: 'خطا', text: err.message || 'خطا' });
                            } else { window.alert(err.message || 'خطا'); }
                        });
                };""",
    """                            if (stUi) {
                                stUi.notify({ icon: 'success', title: 'وضعیت', text: res.j.message, timer: 1800, showConfirmButton: false }, { closeDialogIds: [] });
                            }
                        })
                        .catch(function (err) {
                            if (stUi) {
                                stUi.notify({ icon: 'error', title: 'خطا', text: err.message || 'خطا' }, { closeDialogIds: [] });
                            }
                        })
                        .finally(function () {
                            if (stUi) stUi.setBtnLoading(btn, false);
                        });
                };""",
)

# reply loading + notify
s = s.replace(
    """                var fd = new FormData(e.target);
                fetch(ticketsAdminBase + '/' + activeTicketId + '/reply', {""",
    """                var replyBtn = document.getElementById('tk-reply-submit');
                if (stUi) stUi.setBtnLoading(replyBtn, true);
                var fd = new FormData(e.target);
                fetch(ticketsAdminBase + '/' + activeTicketId + '/reply', {""",
)
s = s.replace(
    """                        if (window.AdminSwal && AdminSwal.fire) {
                            AdminSwal.fire({ icon: 'success', title: 'پاسخ', text: res.j.message, timer: 1800, showConfirmButton: false });
                        }
                    })
                    .catch(function (err) {
                        if (window.AdminSwal && AdminSwal.fire) {
                            AdminSwal.fire({ icon: 'error', title: 'خطا', text: err.message || 'خطا' });
                        } else { window.alert(err.message || 'خطا'); }
                    });
            });""",
    """                        if (stUi) {
                            stUi.notify({ icon: 'success', title: 'پاسخ', text: res.j.message, timer: 1800, showConfirmButton: false }, { closeDialogIds: [] });
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

# reply validation inline
s = s.replace(
    """                if (!hasReply) {
                    if (window.AdminSwal && AdminSwal.fire) {
                        AdminSwal.fire({ icon: 'warning', title: 'پاسخ', text: 'متن پاسخ الزامی است.' });
                    } else {
                        window.alert('متن پاسخ الزامی است.');
                    }
                    return;
                }""",
    """                if (!hasReply) {
                    if (stUi) stUi.showInlineAlert('tk-detail-reply-wrap', 'متن پاسخ الزامی است.');
                    return;
                }""",
)

# session flash
s = s.replace(
    "            syncRecipientPanels();\n        })();",
    """            syncRecipientPanels();

            @if(session('ticket_flash_success'))
            if (stUi) {
                stUi.notify({ icon: 'success', title: 'تیکت', text: @json(session('ticket_flash_success')), timer: 2200, showConfirmButton: false }, { closeDialogIds: ['tk-compose-dialog', 'tk-detail-dialog'] });
            }
            @endif
        })();""",
)

p.write_text(s, encoding="utf-8")
print("admin js ok")
