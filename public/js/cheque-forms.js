/* Form affordances are intentionally limited to the independent cheque module. */
(() => {
    'use strict';
    const root = document.querySelector('[data-cheque-module]');
    if (!root) return;
    const pickers = new Map();
    let activePicker = null;

    function hideCalendar() {
        if (activePicker) activePicker.hide();
        activePicker = null;
    }
    function positionCalendar(input, box) {
        const rect = input.getBoundingClientRect();
        const width = box.offsetWidth;
        const height = box.offsetHeight;
        box.style.setProperty('left', Math.max(8, Math.min(rect.right - width, innerWidth - width - 8)) + 'px', 'important');
        box.style.setProperty('top', Math.max(8, rect.bottom + height + 8 > innerHeight ? rect.top - height - 6 : rect.bottom + 6) + 'px', 'important');
    }
    function openCalendar(input) {
        if (input.disabled || input.readOnly || !window.jQuery?.fn?.pDatepicker) return;
        if (activePicker && activePicker !== pickers.get(input)) hideCalendar();
        let picker = pickers.get(input);
        if (!picker) {
            picker = window.jQuery(input).pDatepicker({
                format: 'YYYY/MM/DD', initialValue: false, initialValueType: 'persian',
                calendarType: 'persian', autoClose: true, responsive: false,
                toolbox: {calendarSwitch: false},
                onShow(model) {
                    const box = model.view.$container[0];
                    box.classList.add('cq-calendar');
                    // A popover inside the native dialog stays visible and interactive
                    // above its top layer, without moving or closing the form.
                    (input.closest('dialog') || root).append(box);
                    if (box.showPopover) {
                        box.setAttribute('popover', 'manual');
                        if (!box.matches(':popover-open')) box.showPopover();
                    }
                    positionCalendar(input, box);
                },
                onHide(model) {
                    const box = model.view.$container[0];
                    if (box.hidePopover && box.matches(':popover-open')) box.hidePopover();
                    if (activePicker === pickers.get(input)) activePicker = null;
                },
                onSelect() {
                    input.dispatchEvent(new Event('input', {bubbles: true}));
                    input.dispatchEvent(new Event('change', {bubbles: true}));
                },
            });
            pickers.set(input, picker);
            input.setAttribute('aria-haspopup', 'dialog');
        }
        activePicker = picker;
        picker.show();
    }
    function refreshLabels() {
        root.querySelectorAll('form input:not([type="hidden"]), form select, form textarea').forEach(input => {
            const label = input.closest('label') || input.labels?.[0];
            if (!label) return;
            let caption = label.querySelector(':scope > .cq-field-caption');
            if (!caption) {
                caption = document.createElement('span');
                caption.className = 'cq-field-caption';
                [...label.childNodes].filter(node => node.nodeType === Node.TEXT_NODE || (node.nodeType === Node.ELEMENT_NODE && node.matches('[data-destination-name-label]'))).forEach(node => {
                    if (node.nodeType === Node.TEXT_NODE) node.textContent = node.textContent.replace(/\s*\((اختیاری|الزامی)\)/g, '');
                    caption.append(node);
                });
                if (input.type === 'checkbox') label.append(caption); else label.prepend(caption);
            }
            let badge = caption.querySelector('.cq-field-status');
            if (!badge) { badge = document.createElement('small'); badge.className = 'cq-field-status'; caption.append(badge); }
            const required = input.required && !input.disabled;
            const text = required ? 'اجباری' : 'اختیاری';
            if (badge.textContent !== text) badge.textContent = text;
            badge.classList.toggle('is-required', required);
            if (input.getAttribute('aria-required') !== String(required)) input.setAttribute('aria-required', String(required));
        });
        pickers.forEach((picker, input) => {
            if (!input.isConnected) { if (activePicker === picker) hideCalendar(); picker.destroy(); pickers.delete(input); }
        });
    }
    root.addEventListener('focusin', event => { if (event.target.matches('[data-cheque-date]')) openCalendar(event.target); });
    root.addEventListener('click', event => { if (event.target.matches('[data-cheque-date]')) openCalendar(event.target); });
    root.addEventListener('close', hideCalendar, true);
    root.addEventListener('reset', hideCalendar);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && activePicker) { hideCalendar(); event.preventDefault(); event.stopPropagation(); }
    }, true);
    window.addEventListener('resize', hideCalendar);
    document.addEventListener('scroll', event => { if (!event.target.closest?.('.cq-calendar')) hideCalendar(); }, true);
    new MutationObserver(refreshLabels).observe(root, {subtree: true, childList: true, attributes: true, attributeFilter: ['required', 'disabled']});
    refreshLabels();
})();
