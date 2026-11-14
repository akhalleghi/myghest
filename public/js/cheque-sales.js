(() => {
    'use strict';
    const root = document.querySelector('[data-cheque-module]');
    if (!root) return;
    const base = root.dataset.base;
    const today = root.dataset.today;
    const amounts = window.ChequeMoney;
    amounts.bind(root);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const $ = (s, parent = root) => parent.querySelector(s);
    const money = v => new Intl.NumberFormat('fa-IR').format(Number(v || 0)) + ' تومان';
    const english = v => String(v).replace(/[۰-۹٠-٩]/g, c => '۰۱۲۳۴۵۶۷۸۹'.includes(c) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(c) : '٠١٢٣٤٥٦٧٨٩'.indexOf(c));
    const uuid = () => crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
    async function api(path, data, method = 'POST') {
        const multipart = data instanceof FormData;
        const response = await fetch(base + path, {method, headers: {'Accept': 'application/json', ...(!multipart ? {'Content-Type': 'application/json'} : {}), 'X-CSRF-TOKEN': csrf}, ...(method === 'GET' ? {} : {body: multipart ? data : JSON.stringify(data)})});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('پاسخ سرور قابل خواندن نیست؛ صفحه را تازه کنید.'); }
        if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || 'عملیات ناموفق بود.');
        return result;
    }
    async function alert(options) {
        if (!window.Swal) throw new Error('ابزار پیش‌نمایش بارگذاری نشده است؛ صفحه را تازه کنید.');
        if (options.input) {
            options = {...options, inputLabel: options.inputLabel || 'این فیلد اجباری است', inputAttributes: {...options.inputAttributes, required:'required'}, inputValidator: options.inputValidator || (value => !value?.trim() ? 'این فیلد را تکمیل کنید.' : undefined)};
        }
        // Native dialogs occupy the top layer. Restore them after SweetAlert closes.
        const open = [...root.querySelectorAll('dialog[open]')];
        const maximized = open.filter(dialog => dialog.classList.contains('is-maximized'));
        open.forEach(dialog => dialog.close());
        try { return await Swal.fire({...options, returnFocus: false}); }
        finally {
            open.forEach(dialog => dialog.showModal());
            maximized.forEach(dialog => dialog.querySelector('.admin-mw-maximize')?.click());
        }
    }
    async function error(error) {
        try { await alert({icon: 'error', title: 'بررسی اطلاعات', text: error.message}); }
        catch (_) { window.alert(error.message); }
    }
    function formData(form) {
        const data = {};
        for (const [key, raw] of new FormData(form)) {
            const v = /^(min_amount|max_amount|purchase_amount_toman|down_payment_toman|discount_toman|amount_toman)$/.test(key) ? amounts.normalize(raw) : /amount|coefficient|interest|months|mobile|national_id|postal_code|sayad_number|iban|account_number/.test(key) ? english(raw) : raw;
            const match = key.match(/^(owner_details|destination)\[(\w+)\]$/);
            if (match) { data[match[1]] ||= {}; data[match[1]][match[2]] = v; }
            else if (!key.startsWith('cheques[')) data[key] = v;
        }
        form.querySelectorAll('input[type="checkbox"][name]').forEach(input => data[input.name] = input.checked ? 1 : 0);
        if (form.id === 'cq-sale-form') {
            data.cheques = [...form.querySelectorAll('.cq-cheque-row')].map(row => Object.fromEntries([...row.querySelectorAll('[data-field]')].map(input => [input.dataset.field, input.dataset.field === 'amount_toman' ? amounts.normalize(input.value) : /sayad_number/.test(input.dataset.field) ? english(input.value) : input.value])));
        }
        return data;
    }
    async function verify(payload) {
        const preview = await api('/verification/preview', payload);
        const message = await alert({title: 'پیش‌نمایش پیامک تأیید', text: 'گیرنده: ' + preview.mobile + ' · عبارت {{code}} را حفظ کنید.', input: 'textarea', inputValue: preview.body, showCancelButton: true, confirmButtonText: 'تأیید و ارسال', cancelButtonText: 'انصراف', inputAttributes: {maxlength: '1000'}});
        if (!message.isConfirmed) return null;
        const sent = await api('/verification/send', {preview_token: preview.preview_token, body: message.value});
        const code = await alert({title: 'کد تأیید دریافت‌شده', input: 'text', inputAttributes: {inputmode: 'numeric', maxlength: '6'}, showCancelButton: true, confirmButtonText: 'بررسی کد', cancelButtonText: 'انصراف'});
        if (!code.isConfirmed) return null;
        return (await api('/verification/confirm', {session: sent.session, code: english(code.value)})).verification_token;
    }
    async function sendSms(id, type, customer = false) {
        const path = `/${customer ? 'customers' : 'cheques'}/${id}`;
        const preview = await api(`${path}/sms-preview`, {type});
        const choice = await alert({title: 'پیش‌نمایش پیامک مشتری چکی', input: 'textarea', inputValue: preview.body, inputAttributes: {maxlength: '1000'}, showCancelButton: true, confirmButtonText: 'تأیید و ارسال', cancelButtonText: 'انصراف'});
        if (!choice.isConfirmed) return;
        const result = await api(`${path}/sms-send`, {body: choice.value, preview_token: preview.preview_token});
        await alert({icon: 'success', text: result.message});
    }
    const customerForm = $('#cq-customer-form');
    const saleForm = $('#cq-sale-form');
    const actionForm = $('#cq-action-form');
    const detailView = window.ChequeDetails.create({root, api, alert, money});
    const manager = $('#cq-management');
    const managerBody = $('#cq-management-body');
    let managerRequest = 0;
    let managerCustomer = '';
    let managerName = '';
    async function manageCustomer(id, name = '', requestedUrl = null) {
        const serial = ++managerRequest;
        const keepContent = managerCustomer === String(id) && !!managerBody.querySelector('[data-workspace-filter]');
        if (managerCustomer !== String(id) || name) managerName = name;
        managerCustomer = String(id);
        const url = new URL(requestedUrl || base, location.href);
        url.searchParams.set('view', 'cheques');
        url.searchParams.set('customer_id', id);
        url.searchParams.set('workspace', '1');
        $('#cq-management-title').textContent = 'مدیریت چک‌ها' + (managerName ? ' · ' + managerName : '');
        managerBody.querySelector('[data-workspace-error]')?.remove();
        if (!keepContent) managerBody.textContent = 'در حال دریافت اطلاعات مشتری…';
        managerBody.setAttribute('aria-busy', 'true');
        if (!manager.open) manager.showModal();
        try {
            const response = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'text/html'}});
            if (!response.ok || response.redirected) throw new Error('دریافت اطلاعات مشتری ممکن نشد؛ دسترسی و اتصال خود را بررسی کنید.');
            const html = await response.text();
            if (serial !== managerRequest || !manager.open) return;
            managerBody.innerHTML = html;
            managerBody.querySelectorAll('[data-cheque-money]').forEach(input => amounts.set(input, input.value));
            managerBody.scrollTop = 0;
        } catch (err) {
            if (serial !== managerRequest) return;
            if (!keepContent) managerBody.replaceChildren();
            const message = document.createElement('p'); message.className = 'cq-error'; message.dataset.workspaceError = ''; message.textContent = keepContent ? 'فیلترها اعمال نشدند. تاریخ‌ها و محدوده مبالغ را بررسی کنید و دوباره تلاش کنید.' : err.message;
            if (keepContent) { managerBody.prepend(message); message.scrollIntoView({block:'nearest'}); return; }
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'cq-btn'; retry.textContent = 'تلاش مجدد';
            retry.addEventListener('click', () => manageCustomer(id, name, url.href));
            managerBody.append(message, retry);
        } finally { if (serial === managerRequest) managerBody.removeAttribute('aria-busy'); }
    }
    manager.addEventListener('close', () => { ++managerRequest; });
    managerBody.addEventListener('submit', event => {
        if (!event.target.matches('[data-workspace-filter]')) return;
        event.preventDefault();
        const url = new URL(base, location.href);
        new FormData(event.target).forEach((value,key) => url.searchParams.set(key,value));
        manageCustomer(managerCustomer, '', url.href);
    });
    managerBody.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || link.href.includes('/export')) return;
        const url = new URL(link.href);
        if (!link.hasAttribute('data-workspace-reset') && !url.searchParams.has('cheque_page')) return;
        event.preventDefault(); manageCustomer(managerCustomer, '', url.href);
    });
    // A fixed popover sits outside table flow and cannot stretch its row.
    let rowMenu = null;
    function closeRowMenu() {
        if (!rowMenu) return;
        rowMenu.menu.hidden = true;
        rowMenu.button.after(rowMenu.menu);
        rowMenu.menu.removeEventListener('click', moduleClick);
        rowMenu.button.setAttribute('aria-expanded', 'false');
        rowMenu = null;
    }
    document.addEventListener('click', event => { if (rowMenu && !rowMenu.menu.contains(event.target) && !rowMenu.button.contains(event.target)) closeRowMenu(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && rowMenu) { const button = rowMenu.button; closeRowMenu(); button.focus(); } });
    window.addEventListener('resize', closeRowMenu);
    document.addEventListener('scroll', closeRowMenu, true);
    function reset(form) { form.reset(); $('[data-form-error]', form).hidden = true; }
    function openCustomer(profile) {
        reset(customerForm);
        customerForm.dataset.mediaCustomerId = profile ? String(profile.id) : '';
        customerForm.dispatchEvent(new CustomEvent('customer-media:reset'));
        customerForm.elements.verification_token.value = '';
        $('#cq-customer-verified') && ($('#cq-customer-verified').textContent = '');
        if (profile) Object.entries(profile).forEach(([key, value]) => {
            const input = customerForm.elements.namedItem(key);
            if (input) input.type === 'checkbox' ? input.checked = !!value : input.value = value ?? '';
        });
        else customerForm.elements.id.value = '';
        $('#cq-customer').showModal();
    }
    customerForm.closest('dialog').addEventListener('close', () => {
        if (customerForm.dataset.mediaOverlayActive !== '1') customerForm.dispatchEvent(new CustomEvent('customer-media:stop'));
    });
    function addCheque() {
        $('#cq-cheque-rows').append($('#cq-cheque-template').content.cloneNode(true));
        saleForm.elements.verification_token.value = '';
    }
    function ownerFields() {
        const self = saleForm.elements.guarantee_by_self.checked;
        $('#cq-owner-fields').hidden = self;
        $('#cq-owner-fields').querySelectorAll('input').forEach(input => { input.disabled = self; input.required = !self && input.hasAttribute('data-owner-required'); });
    }
    function actionFields() {
        const action = actionForm.elements.action.value;
        actionForm.querySelectorAll('[data-receive-field]').forEach(label => { label.hidden = action !== 'receive'; label.querySelectorAll('input,select').forEach(input => input.disabled = action !== 'receive'); });
        actionForm.querySelectorAll('[data-destination-field]').forEach(label => {
            label.hidden = !['bank','person'].includes(action) || (!!label.dataset.destinationFor && label.dataset.destinationFor !== action);
            label.querySelectorAll('input,textarea').forEach(input => input.disabled = label.hidden);
        });
        $('[data-destination-name-label]').textContent = action === 'bank' ? 'نام بانک مقصد' : 'نام و نام خانوادگی شخص مقصد';
        actionForm.elements.amount_toman.required = action === 'receive';
        $('[name="destination[name]"]', actionForm).required = ['bank','person'].includes(action);
        $('[data-verify-return]').hidden = !action.startsWith('return_');
        actionForm.elements.verification_token.value = '';
        $('#cq-return-verified').textContent = '';
    }
    let actionRequest = 0;
    async function updateActionSummary(opening = false) {
        const serial = ++actionRequest;
        const id = actionForm.elements.id.value;
        const selectedDate = actionForm.elements.date.value;
        const submit = actionForm.querySelector('button:not([type])');
        actionForm.dataset.quoteReady = '';
        if (submit) submit.disabled = true;
        $('#cq-action-summary').textContent = 'در حال بررسی مانده همین چک در تاریخ انتخابی…';
        let details;
        try { details = await api('/cheques/' + id + '?at=' + encodeURIComponent(selectedDate), null, 'GET'); }
        catch (err) { if (serial !== actionRequest) return null; throw err; }
        if (serial !== actionRequest || id !== actionForm.elements.id.value || selectedDate !== actionForm.elements.date.value) return null;
        if (String(details.cheque.id) !== id || details.cheque.cleared_at) throw new Error('این چک تسویه شده یا اطلاعات تغییر کرده است؛ صفحه را تازه کنید.');
        if (!opening && String(details.cheque.version) !== actionForm.elements.version.value) throw new Error('این چک توسط کاربر دیگری تغییر کرده است؛ فرم را ببندید و دوباره باز کنید.');
        actionForm.elements.version.value = details.cheque.version;
        const b = details.balance;
        const buyer = details.cheque.sale.customer;
        $('#cq-action-summary').textContent = buyer.first_name + ' ' + buyer.last_name + ' · ' + details.cheque.sale.code + ' · صیاد ' + details.cheque.sayad_number + '\nاصل باقیمانده: ' + money(b.principal_remaining_toman) + ' · دیرکرد مانده: ' + money(b.late_remaining_toman) + ' · زودکرد این تاریخ: ' + money(b.early_quote_toman) + ' · قابل دریافت: ' + money(b.remaining_toman);
        actionForm.elements.amount_toman.max = b.remaining_toman;
        amounts.validate(actionForm.elements.amount_toman);
        actionForm.dataset.quoteKey = JSON.stringify([id, selectedDate, String(details.cheque.version)]);
        actionForm.dataset.quoteReady = '1';
        if (submit) submit.disabled = false;
        return details;
    }
    async function openAction(id, action) {
        reset(actionForm);
        actionForm.elements.id.value = id;
        actionForm.elements.version.value = '';
        actionForm.elements.date.value = today;
        actionForm.elements.request_uuid.value = uuid();
        const details = await updateActionSummary(true);
        if (!details) return;
        [...actionForm.elements.action.options].forEach(option => option.disabled = action === 'receive' ? option.value !== 'receive' : option.value === 'receive');
        actionForm.elements.action.value = action === 'receive' ? 'receive' : 'bank';
        amounts.set(actionForm.elements.amount_toman, details.balance.remaining_toman);
        actionFields();
        if (!$('#cq-action').open) $('#cq-action').showModal();
    }
    async function details(id) { return detailView.open(id); }
    async function wallet(id) {
        const amount = await alert({title:'شارژ / برداشت کیف پول چکی', text:'تومان؛ برای برداشت، مبلغ منفی وارد کنید.', input:'text', inputAttributes:{inputmode:'text', 'data-cheque-money':'', 'data-money-signed':'1', min:'-1000000000000', max:'1000000000000', maxlength:'32', dir:'ltr'}, didOpen:()=>amounts.bind(Swal.getPopup()), inputValidator:value => { const input=Swal.getInput(); amounts.validate(input); return !amounts.normalize(value) || Number(amounts.normalize(value))===0 ? 'مبلغ غیرصفر وارد کنید.' : input.validationMessage || undefined; }, showCancelButton:true, confirmButtonText:'ادامه', cancelButtonText:'انصراف'});
        if (!amount.isConfirmed) return;
        const note = await alert({title:'شرح تراکنش کیف پول', input:'textarea', showCancelButton:true, confirmButtonText:'ثبت تراکنش', cancelButtonText:'انصراف', inputValidator: value => !value?.trim() ? 'شرح الزامی است.' : undefined});
        if (!note.isConfirmed) return;
        await api(`/customers/${id}/wallet`, {amount_toman: amounts.normalize(amount.value), note: note.value, request_uuid: uuid()});
        location.reload();
    }
    async function moduleClick(event) {
        const button = event.target.closest('button');
        if (!button) return;
        try {
            if (button.hasAttribute('data-row-menu')) {
                const same = rowMenu?.button === button; closeRowMenu();
                if (!same) {
                    const menu = button.nextElementSibling;
                    menu.classList.add('cq-floating-menu');
                    document.body.append(menu);
                    menu.addEventListener('click', moduleClick);
                    menu.hidden = false; button.setAttribute('aria-expanded','true');
                    const rect = button.getBoundingClientRect();
                    const width = menu.offsetWidth; const height = menu.offsetHeight;
                    menu.style.left = Math.max(8, Math.min(rect.right-width, innerWidth-width-8)) + 'px';
                    menu.style.top = Math.max(8, rect.bottom+height+8 > innerHeight ? rect.top-height-6 : rect.bottom+6) + 'px';
                    rowMenu = {menu,button};
                }
                return;
            }
            closeRowMenu();
            if (button.hasAttribute('data-customer-manage')) await manageCustomer(button.dataset.customerManage, button.dataset.name);
            if (button.hasAttribute('data-close')) button.closest('dialog').close();
            if (button.hasAttribute('data-customer-new')) openCustomer();
            if (button.hasAttribute('data-profile')) openCustomer(JSON.parse(button.dataset.profile));
            if (button.hasAttribute('data-customer-notes')) await detailView.customerNotes(button.dataset.customerNotes);
            if (button.hasAttribute('data-wallet')) await wallet(button.dataset.wallet);
            if (button.hasAttribute('data-sale-new')) {
                reset(saleForm); saleForm.elements.customer_id.value = button.dataset.saleNew; saleForm.elements.verification_token.value = '';
                $('#cq-sale-customer').textContent = button.dataset.name; $('#cq-owner-verified') && ($('#cq-owner-verified').textContent = '');
                $('#cq-quote-result').textContent = '—'; $('#cq-cheque-rows').replaceChildren(); addCheque(); ownerFields(); $('#cq-sale').showModal();
            }
            if (button.hasAttribute('data-add-cheque')) addCheque();
            if (button.hasAttribute('data-remove-cheque')) { button.closest('fieldset').remove(); saleForm.elements.verification_token.value = ''; }
            if (button.hasAttribute('data-quote')) {
                const quote = await api('/quote', formData(saleForm));
                $('#cq-quote-result').textContent = `کارمزد: ${money(quote.profit_toman)} · جمع چک‌ها: ${money(quote.total_toman)}`;
                const rows = saleForm.querySelectorAll('.cq-cheque-row');
                if (rows.length === 1 && !$('[data-field="amount_toman"]', rows[0]).value) amounts.set($('[data-field="amount_toman"]', rows[0]), quote.total_toman);
            }
            if (button.hasAttribute('data-verify-customer')) {
                const token = await verify({purpose:'customer', mobile:english(customerForm.elements.mobile.value), name:customerForm.elements.first_name.value+' '+customerForm.elements.last_name.value});
                if (token) { customerForm.elements.verification_token.value = token; $('#cq-customer-verified').textContent = 'شماره تأیید شد ✓'; }
            }
            if (button.hasAttribute('data-verify-owner')) {
                if (!saleForm.reportValidity()) return;
                const token = await verify({...formData(saleForm), purpose:'owner'});
                if (token) { saleForm.elements.verification_token.value = token; $('#cq-owner-verified').textContent = 'دارنده و شرایط تأیید شدند ✓'; }
            }
            if (button.hasAttribute('data-verify-return')) {
                const token = await verify({purpose:'return', cheque_id:actionForm.elements.id.value, target:actionForm.elements.action.value});
                if (token) { actionForm.elements.verification_token.value = token; $('#cq-return-verified').textContent = 'گیرنده تأیید شد ✓'; }
            }
            if (button.hasAttribute('data-action')) await openAction(button.dataset.cheque, button.dataset.action);
            if (button.hasAttribute('data-details')) await details(button.dataset.details);
            if (button.hasAttribute('data-sms')) await sendSms(button.dataset.sms, button.dataset.type);
            if (button.hasAttribute('data-customer-sms')) await sendSms(button.dataset.customerSms, null, true);
        } catch (err) { await error(err); }
    }
    root.addEventListener('click', moduleClick);
    customerForm.elements.mobile.addEventListener('input', () => { customerForm.elements.verification_token.value = ''; $('#cq-customer-verified') && ($('#cq-customer-verified').textContent = ''); });
    saleForm.addEventListener('input', () => { saleForm.elements.verification_token.value = ''; $('#cq-owner-verified') && ($('#cq-owner-verified').textContent = ''); });
    saleForm.elements.guarantee_by_self.addEventListener('change', ownerFields);
    actionForm.elements.action.addEventListener('change', actionFields);
    actionForm.elements.date.addEventListener('change', () => updateActionSummary().catch(error));
    [customerForm, saleForm, actionForm].forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.dataset.saving) return;
        const feedback = $('[data-form-error]', form); feedback.hidden = true;
        const data = formData(form); const submit = form.querySelector('button:not([type])');
        form.dataset.saving = '1'; if (submit) submit.disabled = true;
        try {
            let result;
            if (form === actionForm && (form.dataset.quoteReady !== '1' || form.dataset.quoteKey !== JSON.stringify([String(data.id),String(data.date),String(data.version)]))) {
                throw new Error('مانده چک برای این تاریخ هنوز بررسی نشده است؛ تاریخ را دوباره انتخاب کنید.');
            }
            if (form === customerForm) {
                const upload = new FormData(customerForm);
                upload.set('sms_enabled', customerForm.elements.sms_enabled.checked ? '1' : '0');
                upload.set('auto_reminders_enabled', customerForm.elements.auto_reminders_enabled.checked ? '1' : '0');
                if (data.id) upload.set('_method', 'PUT');
                result = await api(data.id ? '/customers/' + data.id : '/customers', upload, 'POST');
                customerForm.dispatchEvent(new CustomEvent('customer-media:stop'));
            }
            else if (form === saleForm) result = await api(`/customers/${data.customer_id}/sales`, data);
            else {
                const choice = await alert({title:'تأیید عملیات چک', text:`${actionForm.elements.action.selectedOptions[0].textContent}${data.amount_toman ? ' · ' + money(data.amount_toman) : ''}\n${data.note}`, icon:'warning', showCancelButton:true, confirmButtonText:'ثبت عملیات', cancelButtonText:'بازبینی'});
                if (!choice.isConfirmed) return;
                result = await api(`/cheques/${data.id}/${data.action === 'receive' ? 'receipts' : 'actions'}`, data);
                form.closest('dialog').close();
                await alert({icon:'success', text:result.message});
                const state = await api(`/cheques/${data.id}`, null, 'GET');
                if (root.dataset.canSms === '1' && (data.action === 'bounce' || (data.action === 'receive' && state.cheque.cleared_at))) {
                    // Sending still requires an explicit confirmation of the editable preview.
                    try { await sendSms(data.id, data.action === 'bounce' ? 'bounced' : 'cleared'); }
                    catch (err) { await alert({icon:'warning', title:'عملیات مالی ثبت شد؛ پیامک ارسال نشد', text:err.message}); }
                }
                location.reload(); return;
            }
            form.closest('dialog').close(); await alert({icon:'success', text:result.message}); location.reload();
        } catch (err) { feedback.textContent = err.message; feedback.hidden = false; feedback.scrollIntoView({block:'nearest'}); }
        finally { delete form.dataset.saving; if (submit) submit.disabled = form === actionForm && form.dataset.quoteReady !== '1'; }
    }));
    if (root.dataset.initialCustomer) manageCustomer(root.dataset.initialCustomer, '', location.href);
})();
