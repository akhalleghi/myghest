<div class="rpt-modal-overlay" id="rpt-modal-loan-interest-fees" hidden aria-hidden="true">
    <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-loan-interest-fees-title">
        <div class="rpt-modal__head">
            <h2 class="rpt-modal__title" id="rpt-modal-loan-interest-fees-title">گزارش بهره و کارمزد وام</h2>
            <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
        </div>
        <div class="rpt-modal__body">
            <div class="rpt-date-toolbar">
                <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ شروع وام</strong> — محاسبهٔ بهره و پیش‌پرداخت مطابق منطق مالی پنل ادمین</p>
                <form class="rpt-range-form" id="rpt-lif-date-form">
                    <div class="rpt-range-field">
                        <label for="rpt-lif-from">از تاریخ</label>
                        <input type="text" id="rpt-lif-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-lif-to">تا تاریخ</label>
                        <input type="text" id="rpt-lif-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                    </div>
                    <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                </form>
            </div>

            <div class="rpt-filters">
                <div class="rpt-search-wrap rpt-customer-picker-wrap">
                    <label for="rpt-lif-customer-search">مشتری</label>
                    <input type="hidden" id="rpt-lif-customer-id" value="">
                    <div class="rpt-customer-picker">
                        <input
                            type="search"
                            id="rpt-lif-customer-search"
                            placeholder="همه مشتریان — برای فیلتر یک مشتری جستجو کنید…"
                            autocomplete="off"
                        >
                        <button type="button" class="rpt-customer-clear" id="rpt-lif-customer-clear" title="نمایش همه مشتریان" hidden aria-label="حذف فیلتر مشتری">&times;</button>
                        <div class="rpt-customer-suggest" id="rpt-lif-customer-suggest" hidden></div>
                    </div>
                </div>
                <div class="rpt-search-wrap">
                    <label for="rpt-lif-search">جستجو در نتایج</label>
                    <input type="search" id="rpt-lif-search" placeholder="شماره وام، نوع وام، مشتری…" autocomplete="off">
                </div>
                <div>
                    <label for="rpt-lif-settled">تسویه پرونده</label>
                    <select id="rpt-lif-settled">
                        <option value="">همه</option>
                        <option value="no">خیر</option>
                        <option value="yes">بلی</option>
                    </select>
                </div>
                <a
                    id="rpt-lif-export-excel"
                    class="rpt-export-btn"
                    href="{{ route('admin.reports.loan-interest-fees.export-excel') }}"
                    title="خروجی اکسل مطابق فیلترهای فعلی"
                >
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
            </div>

            <p class="rpt-meta" id="rpt-lif-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>
            <p class="rpt-guarantee-summary" id="rpt-lif-summary" hidden aria-live="polite"></p>

            <div class="rpt-table-card">
                <div class="rpt-table-wrap">
                    <table class="rpt-table rpt-table--interest-fees">
                        <colgroup>
                            <col class="rpt-col-lif-customer">
                            <col class="rpt-col-lif-loan">
                            <col class="rpt-col-lif-principal">
                            <col class="rpt-col-lif-profit">
                            <col class="rpt-col-lif-fee">
                            <col class="rpt-col-lif-repayable">
                            <col class="rpt-col-lif-rate">
                            <col class="rpt-col-lif-paid">
                            <col class="rpt-col-lif-remain">
                            <col class="rpt-col-lif-discount">
                            <col class="rpt-col-lif-settled">
                            <col class="rpt-col-lif-start">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="rpt-th-customer">مشتری</th>
                                <th scope="col" class="rpt-th-loan">پرونده وام</th>
                                <th scope="col">اصل</th>
                                <th scope="col">بهره</th>
                                <th scope="col">پیش‌پرداخت</th>
                                <th scope="col">قابل بازپرداخت</th>
                                <th scope="col">نرخ و روش</th>
                                <th scope="col">پرداختی</th>
                                <th scope="col">مانده</th>
                                <th scope="col">تخفیف</th>
                                <th scope="col">تسویه</th>
                                <th scope="col">شروع</th>
                            </tr>
                        </thead>
                        <tbody id="rpt-lif-tbody">
                            <tr>
                                <td colspan="12" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
