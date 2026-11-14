(() => {
    'use strict';
    const normalize = value => String(value ?? '').replace(/[۰-۹٠-٩]/g, c => '۰۱۲۳۴۵۶۷۸۹'.includes(c) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(c) : '٠١٢٣٤٥٦٧٨٩'.indexOf(c)).replace(/[,\u066c\s\u200e\u200f]/g, '');
    function format(value) {
        const raw = normalize(value);
        if (!/^-?\d+$/.test(raw)) return String(value ?? '');
        const negative = raw.startsWith('-');
        const digits = raw.replace(/^-/, '').replace(/^0+(?=\d)/, '');
        return (negative ? '-' : '') + digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function validate(input) {
        const raw = normalize(input.value);
        const signed = input.dataset.moneySigned === '1';
        let message = '';
        if (raw && (!(signed ? /^-?\d+$/ : /^\d+$/).test(raw) || !Number.isSafeInteger(Number(raw)))) message = 'مبلغ را به تومان و به صورت عدد صحیح وارد کنید.';
        if (!message && raw) {
            if (input.getAttribute('min') !== null && Number(raw) < Number(input.getAttribute('min'))) message = 'مبلغ از حداقل مجاز کمتر است.';
            if (input.getAttribute('max') !== null && Number(raw) > Number(input.getAttribute('max'))) message = 'مبلغ از سقف مجاز بیشتر است.';
        }
        input.setCustomValidity(message);
        return !message;
    }
    function set(input, value) {
        input.value = format(value);
        validate(input);
    }
    function edit(input) {
        const position = input.selectionStart ?? input.value.length;
        const digitsBefore = normalize(input.value.slice(0, position)).replace(/-/g, '').length;
        const atStart = position === 0;
        set(input, input.value);
        if (atStart) return input.setSelectionRange(0, 0);
        let cursor = input.value.startsWith('-') ? 1 : 0;
        let count = 0;
        while (cursor < input.value.length && count < digitsBefore) {
            if (/[0-9]/.test(input.value[cursor])) count++;
            cursor++;
        }
        input.setSelectionRange(cursor, cursor);
    }
    function bind(root) {
        root.querySelectorAll('[data-cheque-money]').forEach(input => set(input, input.value));
        root.addEventListener('input', event => {
            if (event.target.matches('[data-cheque-money]')) edit(event.target);
        });
        root.addEventListener('beforeinput', event => {
            const input = event.target;
            if (!input.matches('[data-cheque-money]') || input.selectionStart !== input.selectionEnd) return;
            const cursor = input.selectionStart;
            if (event.inputType === 'deleteContentBackward' && input.value[cursor - 1] === ',') input.setSelectionRange(Math.max(0,cursor-2), cursor);
            if (event.inputType === 'deleteContentForward' && input.value[cursor] === ',') input.setSelectionRange(cursor, Math.min(input.value.length,cursor+2));
        });
        root.addEventListener('reset', event => {
            setTimeout(() => event.target.querySelectorAll('[data-cheque-money]').forEach(input => set(input,input.value)),0);
        });
    }
    window.ChequeMoney = {normalize,format,validate,set,bind,edit};
})();
