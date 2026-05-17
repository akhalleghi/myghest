<div class="rpt-modal-overlay" id="rpt-modal-loan-guarantees" hidden aria-hidden="true">
    <div class="rpt-modal" role="dialog" aria-modal="true" aria-labelledby="rpt-modal-loan-guarantees-title">
        <div class="rpt-modal__head">
            <h2 class="rpt-modal__title" id="rpt-modal-loan-guarantees-title">گزارش تضامین</h2>
            <button type="button" class="rpt-modal__close" data-rpt-modal-close aria-label="بستن">&times;</button>
        </div>
        <div class="rpt-modal__body">
            <div class="rpt-date-toolbar">
                <p class="rpt-date-scope">بازهٔ تاریخ بر اساس <strong>تاریخ شروع وام</strong> (پرونده‌هایی که در این بازه شروع شده‌اند)</p>
                <form class="rpt-range-form" id="rpt-gr-date-form">
                    <div class="rpt-range-field">
                        <label for="rpt-gr-from">از تاریخ</label>
                        <input type="text" id="rpt-gr-from" name="from_jdate" value="{{ $defaultFromJdate }}" autocomplete="off" required>
                    </div>
                    <div class="rpt-range-field">
                        <label for="rpt-gr-to">تا تاریخ</label>
                        <input type="text" id="rpt-gr-to" name="to_jdate" value="{{ $defaultToJdate }}" autocomplete="off" required>
                    </div>
                    <button type="submit" class="rpt-range-submit">دریافت اطلاعات</button>
                </form>
            </div>

            <div class="rpt-filters">
                <div class="rpt-search-wrap">
                    <label for="rpt-gr-search">جستجو</label>
                    <input type="search" id="rpt-gr-search" placeholder="شماره وام، مشتری، نوع ضمانت، جزئیات ضامن…" autocomplete="off">
                </div>
                <div>
                    <label for="rpt-gr-type">نوع ضمانت</label>
                    <select id="rpt-gr-type">
                        <option value="">همه</option>
                        @foreach (($guaranteeTypeFilterOptions ?? []) as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rpt-gr-settled">تسویه پرونده</label>
                    <select id="rpt-gr-settled">
                        <option value="">همه</option>
                        <option value="no">خیر</option>
                        <option value="yes">بلی</option>
                    </select>
                </div>
                <a
                    id="rpt-gr-export-excel"
                    class="rpt-export-btn"
                    href="{{ route('admin.reports.loan-guarantees.export-excel') }}"
                    title="خروجی اکسل مطابق فیلترهای فعلی"
                >
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
            </div>

            <p class="rpt-meta" id="rpt-gr-meta">بازهٔ تاریخ را انتخاب کنید و «دریافت اطلاعات» را بزنید.</p>
            <p class="rpt-guarantee-summary" id="rpt-gr-summary" hidden aria-live="polite"></p>

            <div class="rpt-table-card">
                <div class="rpt-table-wrap">
                    <table class="rpt-table rpt-table--guarantees">
                        <colgroup>
                            <col class="rpt-col-gr-loan">
                            <col class="rpt-col-gr-customer">
                            <col class="rpt-col-gr-amount">
                            <col class="rpt-col-gr-inst">
                            <col class="rpt-col-gr-type">
                            <col class="rpt-col-gr-detail">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="rpt-th-loan">اطلاعات وام</th>
                                <th scope="col" class="rpt-th-customer">اطلاعات مشتری</th>
                                <th scope="col">مبلغ</th>
                                <th scope="col">مبلغ اقساط</th>
                                <th scope="col">نوع ضمانت</th>
                                <th scope="col">اطلاعات ضمانت</th>
                            </tr>
                        </thead>
                        <tbody id="rpt-gr-tbody">
                            <tr>
                                <td colspan="6" class="rpt-empty">هنوز داده‌ای بارگذاری نشده است.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
