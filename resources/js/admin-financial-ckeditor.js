let bankingEditorInstance = null;

async function mountBankingEditorIfNeeded() {
    const ta = document.getElementById('banking-info-html');
    if (!ta) {
        return;
    }
    const state = ta.getAttribute('data-ckeditor-mounted');
    if (state === '1' || state === 'pending') {
        return;
    }
    ta.setAttribute('data-ckeditor-mounted', 'pending');
    try {
        const { default: ClassicEditor } = await import('@ckeditor/ckeditor5-build-classic');
        bankingEditorInstance = await ClassicEditor.create(ta, {
            language: { content: 'ar', ui: 'en' },
        });
        ta.setAttribute('data-ckeditor-mounted', '1');
    } catch (err) {
        ta.removeAttribute('data-ckeditor-mounted');
        throw err;
    }
}

function scheduleMountBankingEditor() {
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            mountBankingEditorIfNeeded().catch(function (e) {
                console.error(e);
            });
        });
    });
}

window.initFinancialBankingEditor = scheduleMountBankingEditor;

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.app-financial-form');
    if (form) {
        form.addEventListener('submit', function () {
            if (bankingEditorInstance) {
                const ta = document.getElementById('banking-info-html');
                if (ta) {
                    ta.value = bankingEditorInstance.getData();
                }
            }
        });
    }
});
