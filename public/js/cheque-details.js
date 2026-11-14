/* Structured, read-only cheque details. Financial commands stay in cheque-sales.js. */
(() => {
    'use strict';
    window.ChequeDetails = {
        create({root, api, alert, money}) {
            const dialog = root.querySelector('#cq-details');
            const customerDialog = root.querySelector('#cq-customer-notes');
            const find = selector => dialog.querySelector(selector);
            const date = (value, time = false) => {
                if (!value) return '—';
                const parsed = new Date(value);
                if (!Number.isFinite(parsed.getTime())) return '—';
                return new Intl.DateTimeFormat('fa-IR', {timeZone: 'Asia/Tehran', dateStyle: 'medium', ...(time ? {timeStyle: 'short'} : {})}).format(parsed);
            };
            const actor = record => record.admin?.name || [record.admin?.first_name, record.admin?.last_name].filter(Boolean).join(' ') || 'مدیر پیشین / سامانه';
            const folders = {active:'چک جاری', bank:'واگذار به بانک', transferred:'انتقال به شخص', bounced:'برگشتی', returned:'عودت‌شده', archive:'بایگانی وصول'};
            const custody = {store:'فروشگاه', bank:'بانک', person:'شخص دیگر', owner:'صاحب چک', buyer:'خریدار'};
            const actions = {created:'ثبت چک', receive:'دریافت وجه', bounce:'ثبت برگشت', bank:'انتقال به بانک', person:'انتقال به شخص', return_owner:'عودت به صاحب چک', return_buyer:'عودت به خریدار', recover:'دریافت مجدد اصل چک'};
            const methods = {cheque_collection:'وصول بانکی چک', bank_transfer:'واریز بانکی / کارت به کارت', cash:'نقدی'};
            let requestSerial = 0;
            let customerSerial = 0;
            let noteSaving = false;
            function node(tag, text, className) {
                const result = document.createElement(tag);
                if (text != null) result.textContent = String(text);
                if (className) result.className = className;
                return result;
            }
            function empty(parent, text) {
                parent.replaceChildren(node('p', text, 'cq-empty'));
            }
            function card(parent, title, fields) {
                const article = node('article', null, 'cq-detail-card');
                article.append(node('h3', title));
                const list = node('dl');
                fields.forEach(([label, value]) => {
                    const row = node('div', null, 'cq-detail-field');
                    row.append(node('dt', label), node('dd', value == null || value === '' ? '—' : value));
                    list.append(row);
                });
                article.append(list); parent.append(article);
            }
            function note(parent, label, body, by, at) {
                if (!body) return;
                const article = node('article', null, 'cq-detail-note');
                article.append(node('small', [label, by, at ? date(at, true) : null].filter(Boolean).join(' · ')), node('p', body));
                parent.append(article);
            }
            function tab(key, focus = false) {
                dialog.querySelectorAll('[data-detail-tab]').forEach(button => {
                    const active = button.dataset.detailTab === key;
                    button.setAttribute('aria-selected', String(active));
                    button.tabIndex = active ? 0 : -1;
                    if (active && focus) button.focus();
                });
                dialog.querySelectorAll('[data-detail-panel]').forEach(panel => panel.hidden = panel.dataset.detailPanel !== key);
                if (key === 'media') find('#customer-media-gallery')?.dispatchEvent(new CustomEvent('customer-media:load'));
            }
            function setBusy(busy) {
                dialog.setAttribute('aria-busy', String(busy));
                dialog.querySelectorAll('[data-note-new]').forEach(button => button.disabled = busy || noteSaving || !dialog.dataset.chequeId);
            }
            function destinationText(destination) {
                if (!destination) return '—';
                const fields = [['name','نام'], ['mobile','موبایل'], ['branch','شعبه'], ['account_number','شماره حساب'], ['iban','شبا'], ['national_id','کد ملی'], ['address','آدرس'], ['details','توضیحات']];
                return fields.filter(([key]) => destination[key]).map(([key,label]) => label + ': ' + destination[key]).join('\n') || '—';
            }
            function render({cheque, balance}) {
                const sale = cheque.sale;
                const buyer = sale.customer;
                const gallery = find('#customer-media-gallery');
                gallery.dataset.customerId = String(buyer.id);
                gallery.replaceChildren();
                find('#cq-details-caption').textContent = sale.code + ' · ' + buyer.first_name + ' ' + buyer.last_name + ' · صیاد ' + cheque.sayad_number;
                const summary = find('#cq-detail-summary');
                summary.replaceChildren();
                [['مبلغ اسمی چک',cheque.amount_toman], ['دریافت واقعی',balance.paid_toman], ['اصل باقی‌مانده',balance.principal_remaining_toman], ['دیرکرد پرداخت‌نشده',balance.late_remaining_toman], ['زودکرد تسویه امروز',balance.early_quote_toman], ['قابل تسویه تا امروز',balance.remaining_toman]].forEach(([label,value]) => {
                    const article = node('article');
                    article.append(node('small', label), node('strong', money(value))); summary.append(article);
                });
                const overview = find('#cq-panel-overview');
                overview.replaceChildren();
                const grid = node('div', null, 'cq-detail-grid');
                card(grid, 'مشخصات چک و وضعیت فعلی', [
                    ['شماره صیاد',cheque.sayad_number], ['شماره چک',cheque.serial], ['بانک',cheque.bank_name], ['شعبه',cheque.branch],
                    ['سررسید',date(cheque.due_date)], ['مانده روز تا سررسید',new Intl.NumberFormat('fa-IR').format(balance.days_to_due)],
                    ['پوشه',folders[cheque.folder] || cheque.folder], ['محل نگهداری',custody[cheque.custody] || cheque.custody],
                    ['برگشت اولیه',date(cheque.bounced_at)], ['تسویه مالی',date(cheque.cleared_at)], ['مقصد فعلی',destinationText(cheque.destination)]
                ]);
                card(grid, 'شرایط ثبت‌شده پرونده ' + sale.code, [
                    ['تاریخ خرید',date(sale.purchased_at)], ['سررسید واریز به مشتری',date(sale.disbursement_due_date)],
                    ['مبلغ خرید',money(sale.purchase_amount_toman)], ['مدت قرارداد',sale.months + ' ماه'],
                    ['نرخ کارمزد',sale.interest_rate + ' درصد'], ['روش کارمزد',sale.profit_method === 'bank' ? 'سالانه' : 'ماهانه'],
                    ['کارمزد',money(sale.profit_toman)], ['پیش‌پرداخت',money(sale.down_payment_toman)], ['تخفیف قرارداد',money(sale.discount_toman)],
                    ['جمع چک‌های پرونده',money(sale.total_toman)], ['ضریب روزانه دیرکرد',sale.daily_late_coefficient],
                    ['ضریب روزانه زودکرد',sale.daily_early_coefficient], ['زودکرد محقق‌شده این چک',money(balance.early_earned_toman)],
                    ['تأیید دارنده',sale.owner_verified_at ? date(sale.owner_verified_at,true) : 'ثبت بدون احراز اختیاری'], ['یادداشت پرونده',sale.note]
                ]);
                card(grid, 'خریدار', [
                    ['نام و نام خانوادگی',buyer.first_name + ' ' + buyer.last_name], ['کد مشتری چکی','CC ' + buyer.id],
                    ['موبایل',buyer.mobile], ['احراز شماره',buyer.mobile_verified_at ? date(buyer.mobile_verified_at,true) : 'انجام نشده'],
                    ['نام پدر',buyer.father_name], ['کد ملی',buyer.national_id], ['شهر',buyer.city], ['آدرس',buyer.address],
                    ['کیف پول چکی',money(buyer.wallet_balance_toman)], ['پیامک',buyer.sms_enabled ? 'فعال' : 'غیرفعال']
                ]);
                const owner = sale.owner_details;
                card(grid, sale.guarantee_by_self ? 'دارنده چک (خود خریدار)' : 'دارنده چک (متفاوت از خریدار)', [
                    ['نام و نام خانوادگی',owner.name], ['موبایل دارنده',owner.mobile], ['نام پدر',owner.father_name], ['کد ملی',owner.national_id], ['آدرس',owner.address]
                ]);
                overview.append(grid);
                if ((sale.cheques || []).length > 1) {
                    const related = node('div', null, 'cq-section');
                    related.append(node('h3','سایر چک‌های همین پرونده'));
                    sale.cheques.forEach(item => {
                        const button = node('button', item.sayad_number + ' · ' + money(item.amount_toman) + ' · ' + date(item.due_date), 'cq-btn');
                        button.type = 'button'; button.dataset.details = item.id; button.disabled = item.id === cheque.id; related.append(button);
                    });
                    overview.append(related);
                }
                const receipts = find('#cq-panel-receipts');
                receipts.replaceChildren();
                if (!cheque.receipts.length) empty(receipts,'دریافتی برای این چک ثبت نشده است.');
                else {
                    const wrap = node('div',null,'cq-table-wrap'); const table = node('table',null,'cq-table');
                    const head = node('thead'); const hrow = node('tr');
                    ['تاریخ دریافت','مبلغ واقعی','سهم اصل','سهم دیرکرد','زودکرد محقق‌شده','روش دریافت','ثبت‌کننده','توضیح'].forEach(text => hrow.append(node('th',text)));
                    head.append(hrow); table.append(head); const body = node('tbody');
                    [...cheque.receipts].sort((a,b) => String(b.paid_at).localeCompare(String(a.paid_at)) || b.id-a.id).forEach(receipt => {
                        const row = node('tr');
                        [date(receipt.paid_at),money(receipt.amount_toman),money(receipt.principal_toman),money(receipt.late_fee_toman),money(receipt.early_benefit_toman),methods[receipt.method] || receipt.method,actor(receipt),receipt.note || '—'].forEach(value => row.append(node('td',value)));
                        body.append(row);
                    });
                    table.append(body); wrap.append(table); receipts.append(wrap);
                }
                const events = find('#cq-panel-events');
                events.replaceChildren();
                const history = cheque.events.filter(event => event.action !== 'note');
                history.forEach(event => {
                    const p = event.payload || {};
                    const article = node('article',null,'cq-detail-event');
                    article.append(node('strong',actions[event.action] || 'عملیات ثبت‌شده'),node('small',' · ' + actor(event) + ' · ' + date(event.created_at,true)));
                    if (p.date) article.append(node('p','تاریخ مؤثر عملیات: ' + date(p.date)));
                    if (p.amount_toman != null) article.append(node('p','مبلغ دریافت: ' + money(p.amount_toman)));
                    if (p.before && p.after) {
                        article.append(node('p','پوشه: ' + (folders[p.before.folder] || '—') + ' ← ' + (folders[p.after.folder] || '—')));
                        article.append(node('p','محل نگهداری: ' + (custody[p.before.custody] || '—') + ' ← ' + (custody[p.after.custody] || '—')));
                        if (p.after.destination) article.append(node('p',destinationText(p.after.destination)));
                    }
                    if (p.note) article.append(node('p',p.note)); events.append(article);
                });
                if (!history.length) empty(events,'سابقه گردش برای این چک ثبت نشده است.');
                const notes = find('#cq-detail-notes'); notes.replaceChildren();
                const created = cheque.events.find(event => event.action === 'created');
                note(notes,'یادداشت ثبت چک',cheque.note,created ? actor(created) : '',cheque.created_at);
                note(notes,'یادداشت پرونده',sale.note,'',sale.created_at);
                const additions = cheque.events.filter(event => event.action === 'note');
                additions.forEach(event => note(notes,'یادداشت چک',event.payload.note,actor(event),event.created_at));
                if (!notes.children.length) empty(notes,'برای این چک هنوز یادداشتی ثبت نشده است.');
                [['receipts',cheque.receipts.length], ['events',history.length], ['notes',additions.length + Number(!!cheque.note) + Number(!!sale.note)]].forEach(([key,count]) => find('[data-detail-count="' + key + '"]').textContent = new Intl.NumberFormat('fa-IR').format(count));
            }
            async function open(id, options = {}) {
                const serial = ++requestSerial;
                const same = dialog.open && dialog.dataset.chequeId === String(id);
                const selected = options.tab || (same ? find('[aria-selected="true"]')?.dataset.detailTab : 'overview') || 'overview';
                dialog.dataset.chequeId = '';
                find('#customer-media-gallery').dataset.customerId = '';
                find('#customer-media-gallery').replaceChildren();
                setBusy(true); find('#cq-detail-summary').replaceChildren(); find('#cq-details-caption').textContent = 'در حال دریافت اطلاعات چک…';
                ['overview','receipts','events'].forEach(key => empty(find('#cq-panel-' + key),'در حال دریافت اطلاعات…'));
                find('#cq-detail-notes').replaceChildren(); tab('overview');
                if (!dialog.open) dialog.showModal();
                try {
                    const data = await api('/cheques/' + id,null,'GET');
                    if (serial !== requestSerial) return;
                    render(data); dialog.dataset.chequeId = String(id); tab(selected);
                } catch (err) {
                    if (serial === requestSerial) {
                        empty(find('#cq-panel-overview'),err.message);
                        find('#cq-details-caption').textContent = 'اطلاعات دریافت نشد؛ پنجره را ببندید و دوباره تلاش کنید.';
                    }
                } finally { if (serial === requestSerial) setBusy(false); }
            }
            async function customerNotes(id) {
                const serial = ++customerSerial;
                customerDialog.dataset.customerId = '';
                const host = customerDialog.querySelector('[data-customer-notes-list]');
                empty(host,'در حال دریافت یادداشت‌ها…');
                customerDialog.querySelector('[data-customer-note-new]')?.setAttribute('disabled','');
                if (!customerDialog.open) customerDialog.showModal();
                try {
                    const data = await api('/customers/' + id + '/notes',null,'GET');
                    if (serial !== customerSerial) return;
                    host.replaceChildren(); customerDialog.dataset.customerId = String(id);
                    customerDialog.querySelector('[data-customer-notes-caption]').textContent = data.customer.first_name + ' ' + data.customer.last_name + ' · CC ' + id;
                    note(host,'یادداشت مشخصات مشتری',data.customer.note,'',null);
                    data.sales.forEach(sale => {
                        note(host,'پرونده ' + sale.code,sale.note,'',null);
                        sale.cheques.forEach(cheque => note(host,sale.code + ' · صیاد ' + cheque.sayad_number,cheque.note,'',null));
                    });
                    data.notes.forEach(event => note(host,event.cheque ? event.cheque.sale.code + ' · صیاد ' + event.cheque.sayad_number : 'یادداشت مشتری',event.payload.note,actor(event),event.created_at));
                    if (!host.children.length) empty(host,'هنوز یادداشتی برای این مشتری و چک‌های او ثبت نشده است.');
                } catch (err) { if (serial === customerSerial) empty(host,err.message); }
                finally {
                    if (serial === customerSerial && customerDialog.dataset.customerId) customerDialog.querySelector('[data-customer-note-new]')?.removeAttribute('disabled');
                }
            }
            root.addEventListener('click', async event => {
                const target = event.target.closest('button');
                if (!target) return;
                if (target.dataset.detailTab) tab(target.dataset.detailTab);
                if (!target.hasAttribute('data-note-new') && !target.hasAttribute('data-customer-note-new')) return;
                if (noteSaving) return;
                const forCustomer = target.hasAttribute('data-customer-note-new');
                const id = forCustomer ? customerDialog.dataset.customerId : dialog.dataset.chequeId;
                if (!id) return;
                noteSaving = true; target.disabled = true;
                try {
                    const choice = await alert({title:forCustomer ? 'یادداشت مشتری چکی' : 'یادداشت همین چک', input:'textarea', inputAttributes:{maxlength:'2000'}, showCancelButton:true, confirmButtonText:'ثبت یادداشت', cancelButtonText:'انصراف', inputValidator:value => !value?.trim() ? 'متن یادداشت را وارد کنید.' : undefined});
                    if (!choice.isConfirmed) return;
                    await api((forCustomer ? '/customers/' : '/cheques/') + id + '/notes',{note:choice.value});
                    if (forCustomer) await customerNotes(id); else await open(id,{tab:'notes'});
                } catch (err) { await alert({icon:'error',text:err.message}); }
                finally { noteSaving = false; target.disabled = false; }
            });
            dialog.querySelector('[role="tablist"]').addEventListener('keydown', event => {
                const tabs = [...dialog.querySelectorAll('[data-detail-tab]')];
                const current = tabs.indexOf(document.activeElement);
                if (current < 0) return;
                let next;
                if (event.key === 'ArrowLeft') next = (current+1)%tabs.length;
                if (event.key === 'ArrowRight') next = (current+tabs.length-1)%tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length-1;
                if (next != null) { event.preventDefault(); tab(tabs[next].dataset.detailTab,true); }
            });
            dialog.addEventListener('close',() => { requestSerial++; });
            customerDialog.addEventListener('close',() => { customerSerial++; });
            return {open,customerNotes};
        }
    };
})();
