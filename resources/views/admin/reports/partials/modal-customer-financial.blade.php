<div class="rpt-modal-overlay" id="rpt-modal-customer-financial" hidden aria-hidden="true">
    <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-cf-title">
        <div class="rpt-modal__head">
            <h2 class="rpt-modal__title" id="rpt-cf-title">گزارش جامع مالی مشتریان</h2>
            <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
        </div>
        <div class="rpt-modal__body">
            <div class="rpt-date-toolbar">
                <p class="rpt-date-scope">وام و کارمزد بر اساس تاریخ شروع قرارداد و پرداخت بر اساس تاریخ واریز در بازه محاسبه می‌شوند. دیرکرد، افزایش جریمه در بازه و زودکرد، تخفیف تحقق‌یافته در بازه است.</p>
                <form class="rpt-range-form" id="rpt-cf-form">
                    <div class="rpt-range-field">
                        <label for="rpt-cf-from">از تاریخ</label>
                        <input id="rpt-cf-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-cf-to">تا تاریخ</label>
                        <input id="rpt-cf-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-cf-group">گروه مشتریان</label>
                        <select id="rpt-cf-group" name="group">
                            <option value="all">همه مشتریان</option>
                            <option value="new">جدید؛ عضو شده در بازه</option>
                            <option value="old">قدیمی؛ عضو شده قبل از بازه</option>
                        </select>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-cf-search">جستجوی مشتری</label>
                        <input id="rpt-cf-search" type="search" name="q" maxlength="200" placeholder="نام، کد مشتری، موبایل یا کد ملی">
                    </div>
                    <button type="submit" class="rpt-range-submit">دریافت گزارش</button>
                    <a class="rpt-export-btn" id="rpt-cf-export" href="{{ route('admin.reports.customer-financial.export-excel') }}">خروجی اکسل</a>
                </form>
            </div>
            <p class="rpt-date-scope">مانده بدهی، تعداد پرونده‌های جاری و تسویه‌شده، کیف پول و تضامین، وضعیت فعلی هستند. مبلغ وام، اصل ثبت‌شده قرارداد است. کارمزد، سود قراردادی است. ضمانت‌های فاقد مبلغ به‌صورت تعداد جدا نمایش داده می‌شوند. پرونده‌های ابطال‌شده در محاسبات وارد نمی‌شوند.</p>
            <p class="rpt-meta" id="rpt-cf-meta" role="status" aria-live="polite">بازه و گروه مشتریان را انتخاب کنید.</p>
            <div class="rpt-table-card">
                <div class="rpt-table-wrap" tabindex="0" aria-label="جدول مالی مشتریان با پیمایش افقی">
                    <table class="rpt-table rpt-table--customer-financial">
                        <thead><tr>
                            @foreach($customerFinancialColumns as $label)
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody id="rpt-cf-tbody"><tr><td colspan="{{ count($customerFinancialColumns) }}" class="rpt-empty">هنوز گزارشی دریافت نشده است.</td></tr></tbody>
                        <tfoot id="rpt-cf-tfoot"></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
