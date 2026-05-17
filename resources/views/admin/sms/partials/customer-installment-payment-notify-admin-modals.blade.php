<div class="sms-mini-modal-overlay" id="sms-customer-installment-payment-recipients-modal" hidden>
    <div class="sms-mini-modal sms-mini-modal--wide" role="dialog" aria-modal="true" aria-labelledby="sms-customer-installment-payment-recipients-title">
        <div class="sms-mini-modal-head">
            <h2 class="sms-mini-modal-title" id="sms-customer-installment-payment-recipients-title">دریافت‌کنندگان پیامک واریزی قسط</h2>
            <button type="button" class="sms-mini-modal-close" data-sms-customer-installment-payment-modal-close="recipients" aria-label="بستن">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="sms-mini-modal-body">
            <div class="sms-notify-row" style="margin-bottom:0.55rem;">
                <button type="button" class="sms-mini-btn sms-mini-btn--pri" id="sms-customer-installment-payment-open-picker">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    انتخاب ادمین
                </button>
            </div>
            <div class="sms-mini-table-wrap">
                <table class="sms-mini-table">
                    <thead>
                        <tr>
                            <th scope="col">نام و نام خانوادگی</th>
                            <th scope="col">نام کاربری</th>
                            <th scope="col">شماره تماس</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="sms-customer-installment-payment-recipients-tbody"></tbody>
                </table>
            </div>
            <p class="sms-mini-empty" id="sms-customer-installment-payment-recipients-empty" hidden>هنوز دریافت‌کننده‌ای انتخاب نشده است.</p>
        </div>
        <div class="sms-mini-modal-foot">
            <button type="button" class="sms-mini-btn" data-sms-customer-installment-payment-modal-close="recipients">انصراف</button>
            <button type="button" class="sms-mini-btn sms-mini-btn--pri" id="sms-customer-installment-payment-recipients-save">ذخیره</button>
        </div>
    </div>
</div>

<div class="sms-mini-modal-overlay" id="sms-customer-installment-payment-picker-modal" hidden>
    <div class="sms-mini-modal" role="dialog" aria-modal="true" aria-labelledby="sms-customer-installment-payment-picker-title">
        <div class="sms-mini-modal-head">
            <h2 class="sms-mini-modal-title" id="sms-customer-installment-payment-picker-title">انتخاب ادمین</h2>
            <button type="button" class="sms-mini-modal-close" data-sms-customer-installment-payment-modal-close="picker" aria-label="بستن">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="sms-mini-modal-body">
            <p class="sms-panel-select-sub">یک یا چند ادمین فعال را برای دریافت پیامک واریزی قسط مشتری انتخاب کنید.</p>
            <div class="sms-picker-toolbar">
                <label class="sms-picker-search" for="sms-customer-installment-payment-picker-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="sms-customer-installment-payment-picker-search"
                        placeholder="جستجو: نام، نام کاربری، موبایل…"
                        autocomplete="off"
                    >
                </label>
                <p class="sms-picker-count" id="sms-customer-installment-payment-picker-count" aria-live="polite"></p>
            </div>
            <p class="sms-mini-empty" id="sms-customer-installment-payment-picker-no-results" hidden>نتیجه‌ای یافت نشد.</p>
            <div class="sms-picker-list" id="sms-customer-installment-payment-picker-list"></div>
        </div>
        <div class="sms-mini-modal-foot">
            <button type="button" class="sms-mini-btn" data-sms-customer-installment-payment-modal-close="picker">انصراف</button>
            <button type="button" class="sms-mini-btn sms-mini-btn--pri" id="sms-customer-installment-payment-picker-apply">انتخاب</button>
        </div>
    </div>
</div>
