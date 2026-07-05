<div class="rpt-modal-overlay" id="rpt-modal-admin-activity" hidden aria-hidden="true">
    <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-admin-activity-title">
        <div class="rpt-modal__head">
            <h2 class="rpt-modal__title" id="rpt-modal-admin-activity-title">گزارش فعالیت ادمین‌های سامانه</h2>
            <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
        </div>
        <div class="rpt-modal__body">
            <div class="rpt-date-toolbar">
                <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>زمان انجام اقدام</strong> — شامل ورود، خروج و تمام فعالیت‌ها در پنل</p>
                <form class="rpt-range-form" id="rpt-aa-date-form">
                    <div class="rpt-range-field">
                        <label for="rpt-aa-from">از تاریخ</label>
                        <input type="text" id="rpt-aa-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-aa-to">تا تاریخ</label>
                        <input type="text" id="rpt-aa-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                    </div>
                    <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                </form>
            </div>

            <div class="rpt-filters">
                <div class="rpt-search-wrap rpt-customer-picker-wrap">
                    <label for="rpt-aa-admin-search">ادمین</label>
                    <input type="hidden" id="rpt-aa-admin-id" value="">
                    <div class="rpt-customer-picker">
                        <input
                            type="search"
                            id="rpt-aa-admin-search"
                            placeholder="همه ادمین‌ها — برای فیلتر یک ادمین جستجو کنید…"
                            autocomplete="off"
                        >
                        <button type="button" class="rpt-customer-clear" id="rpt-aa-admin-clear" title="نمایش همه ادمین‌ها" hidden aria-label="حذف فیلتر ادمین">&times;</button>
                        <div class="rpt-customer-suggest" id="rpt-aa-admin-suggest" hidden></div>
                    </div>
                </div>
                <div>
                    <label for="rpt-aa-action">نوع اقدام</label>
                    <select id="rpt-aa-action">
                        @foreach($adminActivityActionOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rpt-search-wrap">
                    <label for="rpt-aa-search">جستجو در نتایج</label>
                    <input type="search" id="rpt-aa-search" placeholder="شرح، مسیر، نام کاربری…" autocomplete="off">
                </div>
                <a
                    id="rpt-aa-export-excel"
                    class="rpt-export-btn"
                    href="{{ route('admin.reports.admin-activity.export-excel') }}"
                    title="خروجی اکسل مطابق فیلترهای فعلی"
                >
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
            </div>

            <p class="rpt-meta" id="rpt-aa-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>

            <div class="rpt-table-card">
                <div class="rpt-table-wrap">
                    <table class="rpt-table rpt-table--admin-activity">
                        <colgroup>
                            <col class="rpt-col-aa-time">
                            <col class="rpt-col-aa-admin">
                            <col class="rpt-col-aa-type">
                            <col class="rpt-col-aa-desc">
                            <col class="rpt-col-aa-path">
                            <col class="rpt-col-aa-ip">
                            <col class="rpt-col-aa-device">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">زمان</th>
                                <th scope="col">ادمین</th>
                                <th scope="col">نوع</th>
                                <th scope="col">شرح اقدام</th>
                                <th scope="col">مسیر</th>
                                <th scope="col">IP</th>
                                <th scope="col">دستگاه</th>
                            </tr>
                        </thead>
                        <tbody id="rpt-aa-tbody">
                            <tr>
                                <td colspan="7" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
