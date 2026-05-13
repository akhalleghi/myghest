    <div class="lrq-page">
        <h1 class="lrq-h1">{{ $pageTitle }}</h1>
        @php
            $lrqListRouteName = $lrqListRouteName ?? 'admin.loan-requests.index';
            $lrqListRouteParams = $lrqListRouteParams ?? [];
            $lrqForcedCustomerId = isset($lrqForcedCustomerId) ? (int) $lrqForcedCustomerId : 0;
            $lrqForcedCustomerId = $lrqForcedCustomerId > 0 ? $lrqForcedCustomerId : null;
            $lrqIndex = static function (array $query = []) use ($lrqListRouteName, $lrqListRouteParams): string {
                return route($lrqListRouteName, array_merge($lrqListRouteParams, $query));
            };
            $selectedStatuses = $selectedStatuses ?? [];
            $statusOptions = $statusOptions ?? [];
            $statusTitleMap = collect($statusOptions)->pluck('title', 'code')->all();
            $exportQuery = array_filter([
                'from_jdate' => $fromJDate,
                'to_jdate' => $toJDate,
                'q' => $search,
                'status' => $selectedStatuses,
                'customer_id' => $lrqForcedCustomerId,
            ], static function ($v): bool {
                return $v !== '' && $v !== null && $v !== [];
            });
        @endphp
        <p class="lrq-lead">
            @if(!empty($lrqEmbedCustomer) && $lrqEmbedCustomer instanceof \App\Models\Customer)
                بازهٔ تاریخ ثبت درخواست را انتخاب کنید و روی «دریافت اطلاعات» بزنید. فقط درخواست‌های وام همین مشتری در این صفحه نمایش داده می‌شود.
            @else
                بازهٔ تاریخ ثبت درخواست را انتخاب کنید و روی «دریافت اطلاعات» بزنید. سپس در جدول زیر می‌توانید جستجو کنید و برای مشاهدهٔ پروندهٔ وام مشتری، روی نام او کلیک کنید.
            @endif
        </p>

        <div class="lrq-date-card">
            <form method="get" action="{{ $lrqIndex([]) }}" class="lrq-date-form" id="lrq-date-form">
                @if ($search !== '')
                    <input type="hidden" name="q" value="{{ e($search) }}">
                @endif
                @foreach ($selectedStatuses as $sc)
                    <input type="hidden" name="status[]" value="{{ e($sc) }}">
                @endforeach
                <div class="lrq-date-row">
                    <div class="lrq-date-field">
                        <label for="lrq-from-jdate">از تاریخ</label>
                        <input type="text" name="from_jdate" id="lrq-from-jdate" value="{{ e($fromJDate) }}" autocomplete="off" required>
                    </div>
                    <div class="lrq-date-field">
                        <label for="lrq-to-jdate">تا تاریخ</label>
                        <input type="text" name="to_jdate" id="lrq-to-jdate" value="{{ e($toJDate) }}" autocomplete="off" required>
                    </div>
                </div>
                <button type="submit" class="lrq-btn-fetch">دریافت اطلاعات</button>
            </form>
        </div>

        <div class="lrq-search-row">
            <form method="get" class="lrq-search-form" action="{{ $lrqIndex([]) }}">
                <input type="hidden" name="from_jdate" value="{{ e($fromJDate) }}">
                <input type="hidden" name="to_jdate" value="{{ e($toJDate) }}">
                @foreach ($selectedStatuses as $sc)
                    <input type="hidden" name="status[]" value="{{ e($sc) }}">
                @endforeach
                <input type="search" name="q" value="{{ e($search) }}" placeholder="اطلاعات مورد نظر خود جهت جستجو وارد کنید" maxlength="200" autocomplete="off">
                <button type="submit" aria-label="جستجو"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
            </form>
            <label class="lrq-select-all">
                <input type="checkbox" id="lrq-select-all" aria-label="انتخاب همه">
                انتخاب همه
            </label>
        </div>

        <div class="lrq-toolbar" role="toolbar" aria-label="ابزار فیلتر و خروجی">
            <div class="lrq-toolbar__group">
                <details class="lrq-status-filter" id="lrq-status-filter">
                    <summary aria-haspopup="listbox" aria-expanded="false">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        فیلتر وضعیت‌ها
                        @if (count($selectedStatuses) > 0)
                            <span class="lrq-status-filter__count" aria-label="تعداد انتخاب‌شده">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($selectedStatuses)) }}</span>
                        @endif
                    </summary>
                    <div class="lrq-status-panel" role="dialog" aria-label="انتخاب وضعیت‌ها">
                        <p class="lrq-status-panel__hint">وضعیت‌هایی که می‌خواهید نمایش داده شوند را انتخاب و «اعمال فیلتر» را بزنید. در صورت خالی بودن، همه وضعیت‌ها نمایش داده می‌شوند.</p>
                        <form method="get" action="{{ $lrqIndex([]) }}" id="lrq-status-filter-form">
                            <input type="hidden" name="from_jdate" value="{{ e($fromJDate) }}">
                            <input type="hidden" name="to_jdate" value="{{ e($toJDate) }}">
                            @if ($search !== '')
                                <input type="hidden" name="q" value="{{ e($search) }}">
                            @endif
                            <div class="lrq-status-panel__list">
                                @forelse ($statusOptions as $opt)
                                    <label class="lrq-status-panel__item">
                                        <input type="checkbox" name="status[]" value="{{ e($opt['code']) }}"
                                            @checked(in_array($opt['code'], $selectedStatuses, true))>
                                        <span>{{ $opt['title'] }}</span>
                                    </label>
                                @empty
                                    <p class="lrq-status-panel__hint" style="text-align:center">وضعیتی تعریف نشده است.</p>
                                @endforelse
                            </div>
                            <div class="lrq-status-panel__actions">
                                <button type="submit" class="lrq-status-panel__btn lrq-status-panel__btn--primary">اعمال فیلتر</button>
                                <a href="{{ $lrqIndex(array_filter(['from_jdate' => $fromJDate, 'to_jdate' => $toJDate, 'q' => $search], static fn ($v) => $v !== '' && $v !== null)) }}"
                                    class="lrq-status-panel__btn lrq-status-panel__btn--ghost">پاک‌سازی</a>
                            </div>
                        </form>
                    </div>
                </details>
            </div>

            <div class="lrq-toolbar__spacer"></div>

            <div class="lrq-toolbar__group">
                <a class="lrq-tool-btn lrq-tool-btn--excel" href="{{ route('admin.loan-requests.export', $exportQuery) }}"
                    title="دریافت خروجی اکسل از همین فهرست فیلتر شده">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    خروجی اکسل
                </a>
                <a class="lrq-tool-btn lrq-tool-btn--print" href="{{ route('admin.loan-requests.print', $exportQuery) }}"
                    target="_blank" rel="noopener" title="چاپ A4 از همین فهرست فیلتر شده">
                    <i class="fa-solid fa-print" aria-hidden="true"></i>
                    چاپ
                </a>
            </div>
        </div>

        @if (count($selectedStatuses) > 0)
            <div class="lrq-active-status-chips" aria-label="وضعیت‌های فعال در فیلتر">
                @foreach ($selectedStatuses as $sc)
                    @php
                        $remaining = array_values(array_filter($selectedStatuses, static fn ($x) => $x !== $sc));
                        $removeUrl = $lrqIndex(array_filter([
                            'from_jdate' => $fromJDate,
                            'to_jdate' => $toJDate,
                            'q' => $search,
                            'status' => $remaining,
                        ], static fn ($v) => $v !== '' && $v !== null && $v !== []));
                    @endphp
                    <span class="lrq-active-status-chip">
                        {{ $statusTitleMap[$sc] ?? $sc }}
                        <a class="lrq-active-status-chip__x" href="{{ $removeUrl }}" title="حذف از فیلتر" aria-label="حذف وضعیت از فیلتر">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </a>
                    </span>
                @endforeach
                <a class="lrq-active-status-clear" href="{{ $lrqIndex(array_filter(['from_jdate' => $fromJDate, 'to_jdate' => $toJDate, 'q' => $search], static fn ($v) => $v !== '' && $v !== null)) }}">
                    حذف همه فیلترهای وضعیت
                </a>
            </div>
        @endif

        <div class="lrq-wrap lrq-desktop-only" role="region" aria-label="جدول درخواست‌های وام">
            <table class="lrq-tbl">
                <thead>
                    <tr>
                        <th scope="col" class="lrq-th-check"><span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">انتخاب</span></th>
                        <th scope="col">اطلاعات درخواست</th>
                        <th scope="col">نام مشتری</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">نظر کارشناس</th>
                        <th scope="col">عملیات‌ها</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loanRequests as $row)
                        <tr>
                            <td class="lrq-td-check">
                                <input type="checkbox" class="lrq-row-check" data-lrq-row-check data-lrq-id="{{ $row['id'] }}" aria-label="انتخاب ردیف">
                            </td>
                            <td>
                                <div class="lrq-req-cell">
                                    <div class="lrq-req-line"><strong>شماره:</strong> {{ $row['request_no_fa'] }}</div>
                                    <div class="lrq-req-line"><strong>مبلغ:</strong> {{ $row['amount_fa'] }} تومان</div>
                                    <div class="lrq-req-line"><strong>تاریخ و ساعت:</strong> {{ $row['datetime_fa'] }}</div>
                                    <div class="lrq-req-line"><strong>وام:</strong> {{ $row['loan_title'] }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="lrq-cust-cell">
                                    @if ($row['customer_id'] > 0)
                                        @php
                                            $custUrl = route('admin.customers.index', [
                                                'open_loan_manage' => '1',
                                                'customer_id' => $row['customer_id'],
                                            ]);
                                        @endphp
                                        <a href="{{ $custUrl }}" class="lrq-cust-name"@if(!empty($lrqEmbedCustomer)) target="_top" rel="noopener"@endif>{{ $row['customer_name'] }}</a>
                                    @else
                                        <span class="lrq-cust-name" style="cursor:default;color:var(--muted)">{{ $row['customer_name'] }}</span>
                                    @endif
                                    <span class="lrq-cust-sub">کد ملی: {{ $row['national_id_fa'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $row['status_badge_class'] }}">{{ $row['status_label'] }}</span>
                            </td>
                            <td>
                                <div class="lrq-expert">{!! $row['expert_note_html'] !!}</div>
                            </td>
                            <td>
                                <div class="lrq-ops">
                                    <button type="button" class="lrq-ico-btn lrq-ico-btn--action" data-lrq-open-edit="{{ $row['id'] }}" title="ویرایش درخواست" aria-label="ویرایش درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                    <button type="button" class="lrq-ico-btn lrq-ico-btn--action lrq-ico-btn--danger" data-lrq-delete="{{ $row['id'] }}" data-lrq-delete-no="{{ $row['request_no_fa'] }}" title="حذف درخواست" aria-label="حذف درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="lrq-empty">در این بازه تاریخ، درخواست وامی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lrq-mobile-stack" role="region" aria-label="کارت‌های درخواست وام (موبایل)">
            @forelse ($loanRequests as $row)
                <article class="lrq-card">
                    <div class="lrq-card-hd">
                        <div class="lrq-card-hd-check">
                            <input type="checkbox" class="lrq-row-check" data-lrq-row-check data-lrq-id="{{ $row['id'] }}" aria-label="انتخاب درخواست {{ $row['request_no_fa'] }}">
                        </div>
                        <div class="lrq-card-hd-main">
                            <span class="lrq-card-reqno">شماره {{ $row['request_no_fa'] }}</span>
                            <span class="{{ $row['status_badge_class'] }}">{{ $row['status_label'] }}</span>
                        </div>
                    </div>
                    <div class="lrq-card-body">
                        <div>
                            <p class="lrq-card-section-title">جزئیات درخواست</p>
                            <div class="lrq-card-kv">
                                <span class="lrq-card-k">مبلغ</span>
                                <span class="lrq-card-v">{{ $row['amount_fa'] }} تومان</span>
                                <span class="lrq-card-k">تاریخ و ساعت</span>
                                <span class="lrq-card-v">{{ $row['datetime_fa'] }}</span>
                                <span class="lrq-card-k">وام</span>
                                <span class="lrq-card-v">{{ $row['loan_title'] }}</span>
                            </div>
                        </div>
                        <div class="lrq-card-block">
                            <p class="lrq-card-section-title" style="margin-bottom:0.4rem">مشتری</p>
                            @if ($row['customer_id'] > 0)
                                @php
                                    $custUrl = route('admin.customers.index', [
                                        'open_loan_manage' => '1',
                                        'customer_id' => $row['customer_id'],
                                    ]);
                                @endphp
                                <a href="{{ $custUrl }}" class="lrq-cust-name"@if(!empty($lrqEmbedCustomer)) target="_top" rel="noopener"@endif>{{ $row['customer_name'] }}</a>
                            @else
                                <span class="lrq-cust-name" style="cursor:default;color:var(--muted)">{{ $row['customer_name'] }}</span>
                            @endif
                            <span class="lrq-cust-sub" style="display:block;margin-top:0.25rem">کد ملی: {{ $row['national_id_fa'] }}</span>
                        </div>
                        <div class="lrq-card-expert">
                            <p class="lrq-card-section-title">نظر کارشناس</p>
                            <div class="lrq-expert">{!! $row['expert_note_html'] !!}</div>
                        </div>
                    </div>
                    <div class="lrq-card-ft">
                        <div class="lrq-ops">
                            <button type="button" class="lrq-ico-btn lrq-ico-btn--action" data-lrq-open-edit="{{ $row['id'] }}" title="ویرایش درخواست" aria-label="ویرایش"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                            <button type="button" class="lrq-ico-btn lrq-ico-btn--action lrq-ico-btn--danger" data-lrq-delete="{{ $row['id'] }}" data-lrq-delete-no="{{ $row['request_no_fa'] }}" title="حذف درخواست" aria-label="حذف درخواست {{ $row['request_no_fa'] }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="lrq-card-empty" role="status">در این بازه تاریخ، درخواست وامی ثبت نشده است.</div>
            @endforelse
        </div>

        @if ($loanRequests->hasPages())
            <div class="lrq-pagination">{{ $loanRequests->links() }}</div>
        @endif

        <div id="lrq-edit-overlay" class="lrq-modal-overlay" hidden aria-hidden="true">
            <div class="lrq-edit-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-edit-title">
                <div class="lrq-edit-modal-head">
                    <h2 id="lrq-edit-title" class="lrq-edit-modal-title">مشخصات درخواست وام</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-edit-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-edit-modal-body">
                    <div id="lrq-edit-loading" class="lrq-empty" hidden>در حال بارگذاری…</div>
                    <div id="lrq-edit-form-wrap" hidden>
                        <div id="lrq-edit-converted-banner" class="lrq-converted-banner" hidden role="status" aria-live="polite">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span class="lrq-converted-banner-text">این درخواست به وام تخصیص داده شده است</span>
                            <span class="lrq-converted-banner-meta" id="lrq-edit-converted-meta"></span>
                        </div>
                        <div class="lrq-edit-layout">
                            <aside class="lrq-edit-side" aria-label="اطلاعات مشتری">
                                <div class="lrq-edit-side-top">
                                    <div class="lrq-edit-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
                                    <div>
                                        <p class="lrq-edit-name" id="lrq-edit-cust-name">—</p>
                                        <p class="lrq-edit-user">نام کاربری: <span id="lrq-edit-cust-username">—</span></p>
                                    </div>
                                </div>
                                <div class="lrq-edit-actions">
                                    <button type="button" class="lrq-edit-pill-btn" id="lrq-edit-open-customer-form"><i class="fa-solid fa-pen" aria-hidden="true"></i> ویرایش اطلاعات</button>
                                    <a class="lrq-edit-pill-btn" id="lrq-edit-open-loan-manage" href="#"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> پرونده مشتری</a>
                                </div>
                                <hr class="lrq-edit-sep">
                                <dl class="lrq-edit-dl">
                                    <div><dt>کد ملی</dt><dd id="lrq-edit-national">—</dd></div>
                                    <div><dt>موبایل</dt><dd id="lrq-edit-mobile">—</dd></div>
                                    <div><dt>نام پدر</dt><dd id="lrq-edit-father">—</dd></div>
                                    <div><dt>تعداد وام</dt><dd id="lrq-edit-loan-count">—</dd></div>
                                    <div><dt>مجموع وام‌ها</dt><dd id="lrq-edit-loans-total">—</dd></div>
                                    <div><dt>مانده اقساط</dt><dd id="lrq-edit-remain">—</dd></div>
                                    <div><dt>تاریخ و ساعت عضویت</dt><dd id="lrq-edit-membership">—</dd></div>
                                    <div><dt>آخرین ورود</dt><dd id="lrq-edit-last-login">—</dd></div>
                                    <div><dt>اعتبار کیف پول</dt><dd id="lrq-edit-wallet">—</dd></div>
                                    <div><dt>وضعیت خوش‌حسابی</dt><dd><span class="lrq-edit-badge" id="lrq-edit-good">نامشخص</span></dd></div>
                                </dl>
                            </aside>
                            <div class="lrq-edit-main">
                                <div class="lrq-edit-bar">
                                    <div class="lrq-edit-bar-cell">تاریخ درخواست <span id="lrq-edit-req-date">—</span></div>
                                    <div class="lrq-edit-bar-cell">وضعیت جاری درخواست <span id="lrq-edit-req-status-label">—</span></div>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-loan-type">نوع وام <span style="color:#b91c1c">*</span></label>
                                    <select id="lrq-edit-loan-type"></select>
                                </div>
                                <div class="lrq-field-row-4">
                                    <div class="lrq-field">
                                        <label for="lrq-edit-amount">مبلغ (تومان)</label>
                                        <input type="text" id="lrq-edit-amount" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-count">تعداد اقساط</label>
                                        <input type="text" id="lrq-edit-inst-count" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-gap">فاصله بین هر قسط</label>
                                        <input type="text" id="lrq-edit-inst-gap" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="lrq-field">
                                        <label for="lrq-edit-inst-amt">مبلغ هر قسط (تومان)</label>
                                        <input type="text" id="lrq-edit-inst-amt" inputmode="numeric" autocomplete="off" readonly>
                                    </div>
                                </div>
                                <p class="lrq-sdef-muted" id="lrq-edit-gap-unit-hint"></p>
                                <div class="lrq-status-row">
                                    <div class="lrq-field">
                                        <label for="lrq-edit-status">تغییر وضعیت به</label>
                                        <select id="lrq-edit-status"></select>
                                    </div>
                                    <button type="button" class="lrq-btn-ghost" id="lrq-open-status-defs">مدیریت وضعیت‌ها</button>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-expert-admin">نظر کارشناس (جهت ادمین)</label>
                                    <textarea id="lrq-edit-expert-admin" rows="3" placeholder="فقط در پنل ادمین دیده می‌شود"></textarea>
                                </div>
                                <div class="lrq-field">
                                    <label for="lrq-edit-expert-customer">نظر کارشناس (جهت مشتری)</label>
                                    <textarea id="lrq-edit-expert-customer" rows="3" placeholder="در پنل کاربر نمایش داده می‌شود"></textarea>
                                </div>
                                <div class="lrq-field">
                                    <span class="lrq-field-label">کالاها و خدمات (ثبت‌شده توسط مشتری)</span>
                                    <div id="lrq-edit-description" class="lrq-desc-readonly" aria-readonly="true">—</div>
                                </div>
                                <div class="lrq-check-row">
                                    <label>
                                        <input type="checkbox" id="lrq-edit-doc-received">
                                        <span>مدارک ارسال‌شده به دست شرکت رسیده است</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" id="lrq-edit-send-sms">
                                        <span>ارسال پیامک وضعیت درخواست به مشتری (هنگام تغییر وضعیت و ذخیره)</span>
                                    </label>
                                </div>
                                <div class="lrq-edit-foot">
                                    <div class="lrq-edit-foot-start">
                                        <button type="button" class="lrq-btn-ico" id="lrq-edit-open-status-log" title="گزارش تغییر وضعیت" aria-label="گزارش تغییر وضعیت">
                                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="lrq-btn-ico" id="lrq-edit-open-sms-log" title="لیست پیامک‌های وضعیت" aria-label="لیست پیامک‌های وضعیت">
                                            <i class="fa-solid fa-sms" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="lrq-edit-foot-end">
                                        <button type="button" class="lrq-btn-primary" id="lrq-edit-save">ذخیره تغییرات</button>
                                        <button type="button" class="lrq-btn-outline" id="lrq-edit-convert-loan">تبدیل به وام</button>
                                    </div>
                                </div>
                                <div class="lrq-docs-admin-section" id="lrq-docs-admin-section">
                                    <h3 class="lrq-docs-admin-h">مدارک</h3>
                                    <div id="lrq-edit-docs-host" class="lrq-edit-docs-host"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="lrq-statuslog-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-log-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-statuslog-title">
                <div class="lrq-log-modal-head">
                    <h2 id="lrq-statuslog-title">لاگ تغییر وضعیت درخواست</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-statuslog-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-log-toolbar">
                    <input type="search" id="lrq-statuslog-q" placeholder="جستجو در لاگ…" autocomplete="off">
                    <button type="button" class="lrq-btn-ghost" id="lrq-statuslog-export">خروجی اکسل</button>
                </div>
                <div class="lrq-log-body">
                    <table class="lrq-log-tbl">
                        <thead>
                            <tr>
                                <th>کاربر</th>
                                <th>تاریخ و ساعت</th>
                                <th>از وضعیت (مشتری)</th>
                                <th>به وضعیت (مشتری)</th>
                            </tr>
                        </thead>
                        <tbody id="lrq-statuslog-tbody"></tbody>
                    </table>
                    <div id="lrq-statuslog-empty" class="lrq-empty" hidden>لاگی ثبت نشده است.</div>
                </div>
            </div>
        </div>

        <div id="lrq-smslog-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-log-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-smslog-title">
                <div class="lrq-log-modal-head">
                    <h2 id="lrq-smslog-title">لیست پیامک‌های تغییر وضعیت درخواست</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-smslog-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-log-body">
                    <table class="lrq-log-tbl">
                        <thead>
                            <tr>
                                <th>پنل پیامک</th>
                                <th>وضعیت</th>
                                <th>زمان ارسال</th>
                                <th>متن</th>
                                <th>دریافت‌کننده</th>
                                <th>نوع</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="lrq-smslog-tbody"></tbody>
                    </table>
                    <div id="lrq-smslog-empty" class="lrq-empty" hidden>پیامکی ثبت نشده است.</div>
                </div>
            </div>
        </div>

        <div id="lrq-convert-overlay" class="lrq-modal-overlay lrq-modal-overlay--top" hidden aria-hidden="true">
            <div class="lrq-convert-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-convert-title">
                <div class="lrq-convert-head">
                    <h2 id="lrq-convert-title">تبدیل درخواست به وام</h2>
                    <button type="button" class="lrq-edit-modal-close" id="lrq-convert-close" aria-label="بستن">&times;</button>
                </div>
                <div class="lrq-convert-body">
                    <p class="lrq-convert-hint" id="lrq-convert-hint">دو تاریخ زیر را در تقویم شمسی وارد کنید. مبلغ وام، تعداد و فاصلهٔ اقساط و مبلغ هر قسط از مشخصات همین درخواست برداشته می‌شوند.</p>
                    <div class="lrq-convert-summary" id="lrq-convert-summary"></div>
                    <div class="lrq-convert-grid">
                        <div class="lrq-field">
                            <label for="lrq-convert-start-jdate">تاریخ شروع وام</label>
                            <input type="text" id="lrq-convert-start-jdate" autocomplete="off" placeholder="مثال: ۱۴۰۵/۰۲/۱۵" inputmode="numeric">
                        </div>
                        <div class="lrq-field">
                            <label for="lrq-convert-due-jdate">سررسید واریز به حساب مشتری</label>
                            <input type="text" id="lrq-convert-due-jdate" autocomplete="off" placeholder="مثال: ۱۴۰۵/۰۲/۲۰" inputmode="numeric">
                        </div>
                    </div>
                </div>
                <div class="lrq-convert-foot">
                    <button type="button" class="lrq-btn-ghost" id="lrq-convert-cancel">انصراف</button>
                    <button type="button" class="lrq-btn-primary" id="lrq-convert-submit">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        <span>ایجاد وام</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="lrq-sdef-overlay" class="lrq-modal-overlay lrq-modal-overlay--nested" hidden aria-hidden="true">
            <div class="lrq-sdef-modal" role="dialog" aria-modal="true" aria-labelledby="lrq-sdef-title">
                <div class="lrq-sdef-head">
                    <h2 id="lrq-sdef-title">مدیریت تعریف وضعیت درخواست‌ها</h2>
                    <div class="lrq-sdef-head-actions">
                        <a href="{{ route('admin.sms.index') }}" target="_blank" rel="noopener noreferrer" class="lrq-btn-ghost" style="text-decoration:none;display:inline-flex;align-items:center">مدیریت قالب‌های پیامک</a>
                        <button type="button" class="lrq-edit-modal-close" id="lrq-sdef-close" aria-label="بستن">&times;</button>
                    </div>
                </div>
                <div class="lrq-sdef-body">
                    <div id="lrq-sdef-list"></div>
                    <button type="button" class="lrq-sdef-add" id="lrq-sdef-add-row"><i class="fa-solid fa-plus" aria-hidden="true"></i> افزودن وضعیت جدید</button>
                    <p class="lrq-sdef-muted">برای ویرایش هر وضعیت ابتدا دکمهٔ «مداد» را بزنید؛ پس از تغییر، دکمه به «ذخیره» (تیک) تبدیل می‌شود. قالب‌های پیامک در مسیر «مدیریت پیامک → الگوهای پیامک» با دستهٔ «درخواست وام (وضعیت)» قابل ویرایش‌اند. وضعیت‌هایی که روی درخواست استفاده شده‌اند قابل حذف نیستند.</p>
                </div>
            </div>
        </div>
    </div>
