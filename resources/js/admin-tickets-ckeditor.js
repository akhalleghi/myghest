const ticketEditors = {};

async function mountTicketEditor(elementId) {
    const ta = document.getElementById(elementId);
    if (!ta) {
        return;
    }
    const state = ta.getAttribute('data-ckeditor-mounted');
    if (state === '1' || state === 'pending') {
        return;
    }
    ta.removeAttribute('required');
    ta.setAttribute('data-ckeditor-mounted', 'pending');
    try {
        const { default: ClassicEditor } = await import('@ckeditor/ckeditor5-build-classic');
        ticketEditors[elementId] = await ClassicEditor.create(ta, {
            language: { content: 'ar', ui: 'en' },
            toolbar: [
                'heading',
                '|',
                'bold',
                'italic',
                'link',
                'bulletedList',
                'numberedList',
                'blockQuote',
                '|',
                'undo',
                'redo',
            ],
        });
        ta.removeAttribute('required');
        ta.setAttribute('data-ckeditor-mounted', '1');
    } catch (err) {
        ta.removeAttribute('data-ckeditor-mounted');
        throw err;
    }
}

function scheduleMount(elementId) {
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            mountTicketEditor(elementId).catch(function (e) {
                console.error(e);
            });
        });
    });
}

window.initAdminTicketComposeEditor = function () {
    scheduleMount('tk-compose-body');
};

window.initAdminTicketReplyEditor = function () {
    scheduleMount('tk-reply-body');
};

window.syncAdminTicketComposeEditor = function () {
    const editor = ticketEditors['tk-compose-body'];
    const ta = document.getElementById('tk-compose-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.syncAdminTicketReplyEditor = function () {
    const editor = ticketEditors['tk-reply-body'];
    const ta = document.getElementById('tk-reply-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

function destroyTicketEditor(elementId) {
    const editor = ticketEditors[elementId];
    if (editor) {
        editor.destroy().catch(function () { /* noop */ });
        delete ticketEditors[elementId];
    }
    const ta = document.getElementById(elementId);
    if (ta) {
        ta.removeAttribute('data-ckeditor-mounted');
        ta.value = '';
    }
}

window.destroyAdminTicketComposeEditor = function () {
    destroyTicketEditor('tk-compose-body');
};

window.destroyAdminTicketReplyEditor = function () {
    destroyTicketEditor('tk-reply-body');
};

window.initUserTicketComposeEditor = function () {
    scheduleMount('ut-compose-body');
};

window.initUserTicketReplyEditor = function () {
    scheduleMount('ut-reply-body');
};

window.syncUserTicketComposeEditor = function () {
    const editor = ticketEditors['ut-compose-body'];
    const ta = document.getElementById('ut-compose-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.syncUserTicketReplyEditor = function () {
    const editor = ticketEditors['ut-reply-body'];
    const ta = document.getElementById('ut-reply-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

/**
 * همگام‌سازی ادیتور با textarea و بررسی خالی نبودن متن (بدون تگ HTML).
 */
window.syncTicketEditorField = function (elementId) {
    const editor = ticketEditors[elementId];
    const ta = document.getElementById(elementId);
    if (!ta) {
        return false;
    }
    if (editor) {
        ta.value = editor.getData();
    }
    const plain = ta.value.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim();

    return plain !== '';
};

window.destroyUserTicketComposeEditor = function () {
    destroyTicketEditor('ut-compose-body');
};

window.destroyUserTicketReplyEditor = function () {
    destroyTicketEditor('ut-reply-body');
};

window.initCtkEmbedComposeEditor = function () {
    scheduleMount('ctk-compose-body');
};

window.initCtkEmbedReplyEditor = function () {
    scheduleMount('ctk-reply-body');
};

window.syncCtkEmbedComposeEditor = function () {
    const editor = ticketEditors['ctk-compose-body'];
    const ta = document.getElementById('ctk-compose-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.syncCtkEmbedReplyEditor = function () {
    const editor = ticketEditors['ctk-reply-body'];
    const ta = document.getElementById('ctk-reply-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.destroyCtkEmbedComposeEditor = function () {
    destroyTicketEditor('ctk-compose-body');
};

window.destroyCtkEmbedReplyEditor = function () {
    destroyTicketEditor('ctk-reply-body');
};

window.initItkComposeEditor = function () {
    scheduleMount('itk-compose-body');
};

window.initItkReplyEditor = function () {
    scheduleMount('itk-reply-body');
};

window.syncItkComposeEditor = function () {
    const editor = ticketEditors['itk-compose-body'];
    const ta = document.getElementById('itk-compose-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.syncItkReplyEditor = function () {
    const editor = ticketEditors['itk-reply-body'];
    const ta = document.getElementById('itk-reply-body');
    if (editor && ta) {
        ta.value = editor.getData();
    }
};

window.destroyItkComposeEditor = function () {
    destroyTicketEditor('itk-compose-body');
};

window.destroyItkReplyEditor = function () {
    destroyTicketEditor('itk-reply-body');
};

/**
 * @param {string} elementId
 * @returns {boolean}
 */
window.ticketEditorHasContent = function (elementId) {
    const editor = ticketEditors[elementId];
    const raw = editor ? editor.getData() : (document.getElementById(elementId)?.value ?? '');
    const text = String(raw).replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim();

    return text.length > 0;
};
