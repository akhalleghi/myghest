/* Opt cheque windows into the existing admin window manager without changing it. */
(() => {
    'use strict';
    const root = document.querySelector('[data-cheque-module]');
    if (!root) return;
    function closeIcon(button) {
        if (!button) return;
        button.setAttribute('data-admin-mw-close', '');
        button.setAttribute('aria-label', 'بستن پنجره');
        button.title = 'بستن پنجره';
        button.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    }
    root.querySelectorAll('dialog.cq-dialog [data-close]').forEach(closeIcon);
    const manager = root.querySelector('#cq-management');
    if (manager) {
        // Fit the customer workspace after the shared manager's initial placement.
        // Later filtering and manual resizing leave the chosen dimensions intact.
        new MutationObserver(() => {
            if (!manager.open) return;
            requestAnimationFrame(() => requestAnimationFrame(() => {
                if (!manager.open || !matchMedia('(min-width: 900px)').matches || manager.classList.contains('is-maximized')) return;
                const width = Math.min(1600, innerWidth - 40);
                const height = innerHeight - 40;
                manager.classList.add('is-positioned');
                Object.assign(manager.style, {width: width + 'px', height: height + 'px', left: (innerWidth - width) / 2 + 'px', top: '20px', right: 'auto', bottom: 'auto', transform: 'none'});
            }));
        }).observe(manager, {attributes: true, attributeFilter: ['open']});
    }
    root.querySelectorAll('.customer-media-overlay').forEach(overlay => {
        const panel = overlay.querySelector(':scope > [role="dialog"]');
        const head = panel?.querySelector('.customer-media-dialog-head');
        if (!head) return;
        head.setAttribute('data-admin-mw-head', '');
        head.querySelector('.customer-media-actions')?.classList.add('admin-mw-actions');
        closeIcon(head.querySelector('[data-camera-close], #customer-media-viewer-close'));
        panel.classList.add('cq-media-window');
        overlay.classList.add('cust-overlay');
        // Reinsert the same node so the shared manager discovers this local overlay.
        // Existing media handlers and selected files remain attached to their nodes.
        root.append(overlay);
    });
})();
