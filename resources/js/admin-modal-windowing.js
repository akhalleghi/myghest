/**
 * پنجره‌سازی دسکتاپ برای مدال‌های پنل ادمین:
 * جابه‌جایی با درگ هدر، تغییر اندازه، تمام‌صفحه — فقط ≥۹۰۰px.
 */
import '../css/admin-modal-windowing.css';

const DESKTOP_MQ = '(min-width: 900px)';
const ENHANCED = new WeakSet();

const OVERLAY_SELECTORS = [
    '.cust-overlay',
    '.rpt-modal-overlay',
    '.rpt-quick-overlay',
    '.lrq-modal-overlay',
    '.sms-template-modal-overlay',
    '.sms-mini-modal-overlay',
    '.app-settings-overlay',
    '.db-backup-overlay',
    '.login-blocks-overlay',
    '.dash-widget-overlay',
    '.au-modal',
    '.lt-modal',
];

const SKIP_OVERLAY_IDS = new Set([
    'admin-notif-overlay',
]);

const HEAD_SELECTORS = [
    '.cust-modal-head',
    '.loan-manage-modal-head',
    '.rpt-modal__head',
    '.rpt-quick-head',
    '.lrq-edit-modal-head',
    '.lrq-log-modal-head',
    '.lrq-convert-head',
    '.lrq-sdef-head',
    '.app-settings-head',
    '.db-backup-head',
    '.login-blocks-head',
    '.au-modal__head',
    '.lt-modal__head',
    '.sms-template-modal-head',
    '.sms-mini-modal-head',
    '.dash-widget-dialog__head',
    '.tk-dialog-head',
    '.itk-dialog-head',
    '.ctk-dialog-head',
    '.dd-dialog-head',
    '[data-admin-mw-head]',
].join(', ');

const CLOSE_BTN_SELECTORS = [
    '.cust-modal-close',
    '.rpt-modal__close',
    '.rpt-quick-close',
    '.lrq-edit-modal-close',
    '.lrq-log-modal-close',
    '.lrq-convert-modal-close',
    '.lrq-sdef-modal-close',
    '.app-settings-close',
    '.db-backup-close',
    '.login-blocks-close',
    '.au-modal__close',
    '.lt-modal__close',
    '.sms-template-modal-close',
    '.sms-mini-modal-close',
    '.dash-widget-dialog__close',
    '.tk-dialog-close',
    '.itk-dialog-close',
    '.ctk-dialog-close',
    '.dd-dialog-close',
    '[data-rpt-modal-close]',
    '[data-admin-mw-close]',
].join(', ');

function isDesktop() {
    return !!(window.matchMedia && window.matchMedia(DESKTOP_MQ).matches);
}

function isOverlayOpen(overlay) {
    if (!overlay) return false;
    if (overlay.hidden) return false;
    if (overlay.hasAttribute('hidden')) return false;
    if (overlay.classList.contains('hidden')) return false;
    const style = window.getComputedStyle(overlay);
    return style.display !== 'none' && style.visibility !== 'hidden';
}

function findPanel(overlay) {
    if (!overlay) return null;
    return (
        overlay.querySelector(':scope > [role="dialog"]') ||
        overlay.querySelector(':scope > .cust-modal') ||
        overlay.querySelector(':scope > .rpt-modal') ||
        overlay.querySelector(':scope > .rpt-quick-modal') ||
        overlay.querySelector(':scope > .lrq-edit-modal') ||
        overlay.querySelector(':scope > .lrq-log-modal') ||
        overlay.querySelector(':scope > .lrq-convert-modal') ||
        overlay.querySelector(':scope > .lrq-sdef-modal') ||
        overlay.querySelector(':scope > .sms-template-modal') ||
        overlay.querySelector(':scope > .sms-mini-modal') ||
        overlay.querySelector(':scope > .app-settings-modal') ||
        overlay.querySelector(':scope > .db-backup-modal') ||
        overlay.querySelector(':scope > .login-blocks-modal') ||
        overlay.querySelector(':scope > .dash-widget-dialog') ||
        overlay.querySelector(':scope > .au-modal__box') ||
        overlay.querySelector(':scope > .lt-modal__box') ||
        overlay.querySelector(':scope > [data-admin-mw-panel]') ||
        overlay.firstElementChild
    );
}

function findHead(panel) {
    if (!panel) return null;
    return panel.querySelector(HEAD_SELECTORS) || panel.querySelector(':scope > header') || null;
}

function ensureMaximizeUi(panel, head) {
    if (!head) return { maximizeBtn: null, maximizeIcon: null };

    let actions = head.querySelector('.admin-mw-actions, .loan-manage-window-actions');
    if (!actions) {
        actions = document.createElement('div');
        actions.className = 'admin-mw-actions';
        const closeBtn = head.querySelector(CLOSE_BTN_SELECTORS);
        if (closeBtn && closeBtn.parentElement === head) {
            head.insertBefore(actions, closeBtn);
            actions.appendChild(closeBtn);
        } else if (closeBtn && closeBtn.parentElement) {
            // close already wrapped; use parent if it looks like actions
            const parent = closeBtn.parentElement;
            if (parent !== head && parent.children.length <= 3) {
                parent.classList.add('admin-mw-actions');
                actions = parent;
            } else {
                closeBtn.parentElement.insertBefore(actions, closeBtn);
                actions.appendChild(closeBtn);
            }
        } else {
            head.appendChild(actions);
        }
    } else {
        actions.classList.add('admin-mw-actions');
    }

    let maximizeBtn =
        actions.querySelector('.admin-mw-maximize, .loan-manage-window-btn--maximize, #loan-manage-maximize') ||
        head.querySelector('.admin-mw-maximize, .loan-manage-window-btn--maximize, #loan-manage-maximize');

    if (!maximizeBtn) {
        maximizeBtn = document.createElement('button');
        maximizeBtn.type = 'button';
        maximizeBtn.className = 'admin-mw-maximize';
        maximizeBtn.setAttribute('aria-label', 'تمام‌صفحه');
        maximizeBtn.title = 'تمام‌صفحه / بازگردانی';
        maximizeBtn.innerHTML = '<i class="fa-solid fa-expand" aria-hidden="true"></i>';
        const closeBtn = actions.querySelector(CLOSE_BTN_SELECTORS);
        if (closeBtn) {
            actions.insertBefore(maximizeBtn, closeBtn);
        } else {
            actions.appendChild(maximizeBtn);
        }
    } else {
        maximizeBtn.classList.add('admin-mw-maximize');
    }

    let maximizeIcon = maximizeBtn.querySelector('i');
    if (!maximizeIcon) {
        maximizeIcon = document.createElement('i');
        maximizeIcon.className = 'fa-solid fa-expand';
        maximizeIcon.setAttribute('aria-hidden', 'true');
        maximizeBtn.textContent = '';
        maximizeBtn.appendChild(maximizeIcon);
    }

    return { maximizeBtn, maximizeIcon };
}

function clearInline(panel) {
    panel.style.left = '';
    panel.style.top = '';
    panel.style.width = '';
    panel.style.height = '';
    panel.style.right = '';
    panel.style.bottom = '';
    panel.style.transform = '';
}

function updateMaximizeUi(panel, maximizeBtn, maximizeIcon) {
    const maximized = panel.classList.contains('is-maximized');
    if (maximizeIcon) {
        maximizeIcon.classList.toggle('fa-expand', !maximized);
        maximizeIcon.classList.toggle('fa-compress', maximized);
    }
    if (maximizeBtn) {
        maximizeBtn.setAttribute('aria-label', maximized ? 'بازگردانی اندازه' : 'تمام‌صفحه');
        maximizeBtn.setAttribute('title', maximized ? 'بازگردانی اندازه' : 'تمام‌صفحه');
    }
}

function resetLayout(ctx) {
    const { panel, head, maximizeBtn, maximizeIcon } = ctx;
    ctx.drag = null;
    if (head) head.classList.remove('is-dragging');
    panel.classList.remove('is-maximized', 'is-positioned');
    clearInline(panel);
    updateMaximizeUi(panel, maximizeBtn, maximizeIcon);
}

function placeCentered(ctx) {
    const { overlay, panel, maximizeBtn, maximizeIcon } = ctx;
    if (!isDesktop()) {
        resetLayout(ctx);
        return;
    }
    if (panel.classList.contains('is-maximized')) {
        updateMaximizeUi(panel, maximizeBtn, maximizeIcon);
        return;
    }

    const pad = 16;
    const ow = overlay ? overlay.clientWidth || window.innerWidth : window.innerWidth;
    const oh = overlay ? overlay.clientHeight || window.innerHeight : window.innerHeight;

    // Prefer current visual size if already rendered; else sensible defaults
    const rect = panel.getBoundingClientRect();
    let w = Math.round(rect.width) || 720;
    let h = Math.round(rect.height) || 520;

    // Cap to viewport; keep a usable minimum
    const maxW = Math.max(280, ow - pad * 2);
    const maxH = Math.max(280, oh - pad * 2);
    w = Math.min(Math.max(w, 320), maxW);
    h = Math.min(Math.max(h, 280), maxH);

    // Prefer reading CSS intended width when first opening (not yet positioned)
    if (!panel.classList.contains('is-positioned')) {
        const preferredW = Math.min(1180, maxW);
        const preferredH = Math.min(Math.floor(oh * 0.92), 860, maxH);
        // If the modal is naturally smaller (e.g. 560px forms), keep that width
        if (rect.width > 0 && rect.width < preferredW - 40) {
            w = Math.min(Math.max(Math.round(rect.width), 320), maxW);
        } else {
            w = preferredW;
        }
        if (rect.height > 0 && rect.height < preferredH - 40 && rect.height >= 280) {
            h = Math.min(Math.max(Math.round(rect.height), 280), maxH);
        } else {
            h = preferredH;
        }
    }

    const left = Math.max(pad, Math.round((ow - w) / 2));
    const top = Math.max(pad, Math.round((oh - h) / 2));

    panel.classList.add('is-positioned');
    panel.classList.remove('is-maximized');
    panel.style.transform = 'none';
    panel.style.width = `${w}px`;
    panel.style.height = `${h}px`;
    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
    updateMaximizeUi(panel, maximizeBtn, maximizeIcon);
}

function clampPos(overlay, left, top, width, height) {
    const ow = overlay ? overlay.clientWidth : window.innerWidth;
    const oh = overlay ? overlay.clientHeight : window.innerHeight;
    const minVisible = 72;
    const maxLeft = Math.max(0, ow - minVisible);
    const maxTop = Math.max(0, oh - minVisible);
    const minLeft = Math.min(0, ow - width);
    return {
        left: Math.min(maxLeft, Math.max(minLeft, left)),
        top: Math.min(maxTop, Math.max(0, top)),
    };
}

function ensurePositioned(ctx) {
    const { overlay, panel } = ctx;
    if (panel.classList.contains('is-positioned') && panel.style.left) return;
    const host = overlay || document.body;
    const hostRect = host.getBoundingClientRect();
    const modalRect = panel.getBoundingClientRect();
    panel.classList.add('is-positioned');
    panel.style.transform = 'none';
    panel.style.width = `${Math.round(modalRect.width)}px`;
    panel.style.height = `${Math.round(modalRect.height)}px`;
    panel.style.left = `${Math.round(modalRect.left - hostRect.left)}px`;
    panel.style.top = `${Math.round(modalRect.top - hostRect.top)}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
}

function enhanceOverlayPair(overlay, panel, options = {}) {
    if (!panel || ENHANCED.has(panel)) return null;
    if (panel.closest('.swal2-container')) return null;

    const head = findHead(panel);
    if (!head) return null;

    const { maximizeBtn, maximizeIcon } = ensureMaximizeUi(panel, head);
    head.classList.add('admin-mw-head');
    panel.classList.add('admin-mw-panel');
    if (overlay) overlay.classList.add('admin-mw-overlay');

    const ctx = {
        overlay,
        panel,
        head,
        maximizeBtn,
        maximizeIcon,
        drag: null,
        isDialog: options.isDialog === true,
    };

    function onMove(ev) {
        if (!ctx.drag) return;
        ev.preventDefault();
        const nextLeft = ctx.drag.startLeft + (ev.clientX - ctx.drag.originX);
        const nextTop = ctx.drag.startTop + (ev.clientY - ctx.drag.originY);
        const clamped = clampPos(ctx.overlay || document.documentElement, nextLeft, nextTop, ctx.drag.width, ctx.drag.height);
        panel.style.left = `${clamped.left}px`;
        panel.style.top = `${clamped.top}px`;
    }

    function endDrag() {
        if (!ctx.drag) return;
        ctx.drag = null;
        head.classList.remove('is-dragging');
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerup', endDrag);
        document.removeEventListener('pointercancel', endDrag);
    }

    function startDrag(ev) {
        if (!isDesktop()) return;
        if (panel.classList.contains('is-maximized')) return;
        if (ev.button != null && ev.button !== 0) return;
        if (ev.target && ev.target.closest && ev.target.closest('button, a, input, textarea, select, label')) return;
        ensurePositioned(ctx);
        ctx.drag = {
            originX: ev.clientX,
            originY: ev.clientY,
            startLeft: parseFloat(panel.style.left) || 0,
            startTop: parseFloat(panel.style.top) || 0,
            width: panel.offsetWidth,
            height: panel.offsetHeight,
        };
        head.classList.add('is-dragging');
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', endDrag);
        document.addEventListener('pointercancel', endDrag);
        ev.preventDefault();
    }

    function toggleMaximize(ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        if (!isDesktop()) return;
        const willMaximize = !panel.classList.contains('is-maximized');
        if (willMaximize) {
            panel.classList.add('is-maximized', 'is-positioned');
            clearInline(panel);
            updateMaximizeUi(panel, maximizeBtn, maximizeIcon);
        } else {
            panel.classList.remove('is-maximized');
            placeCentered(ctx);
        }
    }

    head.addEventListener('pointerdown', startDrag);
    head.addEventListener('dblclick', (ev) => {
        if (!isDesktop()) return;
        if (ev.target && ev.target.closest && ev.target.closest('button, a, input, textarea, select, label')) return;
        ev.preventDefault();
        toggleMaximize(ev);
    });
    if (maximizeBtn) {
        maximizeBtn.addEventListener('click', toggleMaximize);
    }

    ctx.place = () => {
        requestAnimationFrame(() => placeCentered(ctx));
    };
    ctx.reset = () => {
        endDrag();
        resetLayout(ctx);
    };
    ctx.onViewportResize = () => {
        if (!isOverlayOpen(overlay) && !(ctx.isDialog && panel.open)) return;
        if (!isDesktop()) {
            ctx.reset();
            return;
        }
        if (panel.classList.contains('is-maximized')) return;
        if (panel.classList.contains('is-positioned')) {
            const left = parseFloat(panel.style.left) || 0;
            const top = parseFloat(panel.style.top) || 0;
            const clamped = clampPos(overlay || document.documentElement, left, top, panel.offsetWidth, panel.offsetHeight);
            panel.style.left = `${clamped.left}px`;
            panel.style.top = `${clamped.top}px`;
        }
    };

    ENHANCED.add(panel);
    panel._adminMw = ctx;
    return ctx;
}

function observeOverlay(overlay, ctx) {
    const mo = new MutationObserver(() => {
        if (isOverlayOpen(overlay)) {
            ctx.place();
        } else {
            ctx.reset();
        }
    });
    mo.observe(overlay, { attributes: true, attributeFilter: ['hidden', 'class', 'aria-hidden', 'style'] });
}

function enhanceOverlays(root = document) {
    const contexts = [];
    OVERLAY_SELECTORS.forEach((sel) => {
        root.querySelectorAll(sel).forEach((overlay) => {
            if (SKIP_OVERLAY_IDS.has(overlay.id)) return;
            if (overlay.getAttribute('data-admin-windowed') === 'off') return;
            const panel = findPanel(overlay);
            const ctx = enhanceOverlayPair(overlay, panel);
            if (!ctx) return;
            observeOverlay(overlay, ctx);
            if (isOverlayOpen(overlay)) ctx.place();
            contexts.push(ctx);
        });
    });
    return contexts;
}

function enhanceDialogs(root = document) {
    const contexts = [];
    root.querySelectorAll('dialog').forEach((dialog) => {
        if (dialog.getAttribute('data-admin-windowed') === 'off') return;
        if (dialog.closest('.swal2-container')) return;
        // Prefer dialogs that look like admin ticket/workspace dialogs
        const ctx = enhanceOverlayPair(null, dialog, { isDialog: true });
        if (!ctx) return;

        const mo = new MutationObserver(() => {
            if (dialog.open) ctx.place();
            else ctx.reset();
        });
        mo.observe(dialog, { attributes: true, attributeFilter: ['open'] });
        dialog.addEventListener('close', () => ctx.reset());
        if (dialog.open) ctx.place();
        contexts.push(ctx);
    });
    return contexts;
}

function boot() {
    const all = [];
    all.push(...enhanceOverlays(document));
    all.push(...enhanceDialogs(document));

    window.addEventListener('resize', () => {
        all.forEach((ctx) => ctx.onViewportResize && ctx.onViewportResize());
    });

    // Dynamic overlays inserted later (rare)
    const bodyMo = new MutationObserver((mutations) => {
        let need = false;
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (
                    node.matches &&
                    (OVERLAY_SELECTORS.some((s) => node.matches(s)) || node.tagName === 'DIALOG')
                ) {
                    need = true;
                } else if (node.querySelector) {
                    if (
                        OVERLAY_SELECTORS.some((s) => node.querySelector(s)) ||
                        node.querySelector('dialog')
                    ) {
                        need = true;
                    }
                }
            });
        });
        if (need) {
            all.push(...enhanceOverlays(document));
            all.push(...enhanceDialogs(document));
        }
    });
    bodyMo.observe(document.body, { childList: true, subtree: true });

    window.AdminModalWindowing = {
        refresh() {
            all.push(...enhanceOverlays(document));
            all.push(...enhanceDialogs(document));
        },
        isDesktop,
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
