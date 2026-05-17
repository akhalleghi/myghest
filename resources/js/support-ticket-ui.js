/**
 * UI مشترک تیکت پشتیبانی: چت، لودینگ دکمه، اعلان (بستن dialog قبل از Swal).
 */

function escHtml(s) {
    if (s == null) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = String(s);

    return d.innerHTML;
}

/** شناسه تمام مودال‌های تیکت (برای نمایش Swal جلو، باید بسته شوند). */
export const TICKET_DIALOG_IDS = [
    'tk-compose-dialog',
    'tk-detail-dialog',
    'ctk-compose-dialog',
    'ctk-detail-dialog',
    'ut-compose-dialog',
    'ut-detail-dialog',
];

function closeDialogs(ids) {
    (ids || []).forEach(function (id) {
        const el = document.getElementById(id);
        if (el && typeof el.close === 'function' && el.open) {
            el.close();
        }
    });
}

/**
 * @param {Response} response
 * @returns {Promise<object>}
 */
export async function parseJsonResponse(response) {
    let data = {};
    try {
        data = await response.json();
    } catch {
        data = {};
    }

    if (!response.ok) {
        let message = data.message || '';
        if (!message && data.errors && typeof data.errors === 'object') {
            const firstKey = Object.keys(data.errors)[0];
            const first = firstKey ? data.errors[firstKey] : null;
            if (Array.isArray(first) && first[0]) {
                message = String(first[0]);
            }
        }
        throw new Error(message || 'خطا در انجام عملیات');
    }

    return data;
}

export const SupportTicketUi = {
    esc: escHtml,

    setBtnLoading(btn, loading, loadingText) {
        if (!btn) {
            return;
        }
        if (loading) {
            if (!btn.dataset.stOrigHtml) {
                btn.dataset.stOrigHtml = btn.innerHTML;
            }
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> '
                + (loadingText || 'در حال ارسال…');
        } else {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            if (btn.dataset.stOrigHtml) {
                btn.innerHTML = btn.dataset.stOrigHtml;
            }
        }
    },

    notify(opts, options) {
        const optionsSafe = options || {};
        let closeIds = optionsSafe.closeDialogIds;
        if (optionsSafe.closeAllTicketDialogs) {
            closeIds = TICKET_DIALOG_IDS;
        }
        if (!closeIds) {
            closeIds = [];
        }
        closeDialogs(closeIds);
        if (window.AdminSwal && typeof window.AdminSwal.fire === 'function') {
            return window.AdminSwal.fire(Object.assign({
                confirmButtonText: 'باشه',
            }, opts || {}));
        }
        const text = (opts && opts.text) ? opts.text : ((opts && opts.title) ? opts.title : '');
        if (text) {
            window.alert(text);
        }

        return Promise.resolve();
    },

    showInlineAlert(elementId, message) {
        const host = document.getElementById(elementId);
        if (!host) {
            return;
        }
        let box = host.querySelector('.st-inline-alert');
        if (!box) {
            box = document.createElement('div');
            box.className = 'st-inline-alert';
            box.setAttribute('role', 'alert');
            host.prepend(box);
        }
        box.textContent = message || '';
        box.hidden = !message;
    },

    clearInlineAlert(elementId) {
        this.showInlineAlert(elementId, '');
    },

    renderChatHtml(messages, esc) {
        const e = typeof esc === 'function' ? esc : escHtml;
        let html = '<div class="st-chat">';
        if (!messages || !messages.length) {
            html += '<p class="st-chat-empty">پیامی ثبت نشده است.</p>';
        } else {
            messages.forEach(function (msg) {
                const side = msg.is_admin_sender ? 'st-msg--staff' : 'st-msg--customer';
                html += '<div class="st-msg ' + side + '">';
                html += '<div class="st-msg__meta">' + e(msg.sender_label) + ' · ' + e(msg.datetime_fa) + '</div>';
                html += '<div class="st-msg__bubble">' + (msg.body_html || '') + '</div>';
                if (msg.attachments && msg.attachments.length) {
                    html += '<div class="st-msg__att">';
                    msg.attachments.forEach(function (att) {
                        html += '<a href="' + e(att.url) + '" target="_blank" rel="noopener">';
                        html += '<i class="fa-solid fa-paperclip" aria-hidden="true"></i> ' + e(att.name);
                        html += '</a>';
                    });
                    html += '</div>';
                }
                html += '</div>';
            });
        }
        html += '</div>';

        return html;
    },
};

// سازگاری با اسکریپت‌های قدیمی
window.SupportTicketUi = SupportTicketUi;
