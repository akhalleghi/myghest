    <div class="ctx-page">
        @php
            $ctxListRouteName = $ctxListRouteName ?? 'admin.customer-transactions.index';
            $ctxListRouteParams = $ctxListRouteParams ?? [];
            $ctxForcedCustomerId = isset($ctxForcedCustomerId) ? (int) $ctxForcedCustomerId : 0;
            $ctxForcedCustomerId = $ctxForcedCustomerId > 0 ? $ctxForcedCustomerId : null;
            $ctxIndex = static function (array $query = []) use ($ctxListRouteName, $ctxListRouteParams): string {
                return route($ctxListRouteName, array_merge($ctxListRouteParams, $query));
            };
            $ctxExportQuery = array_filter(
                array_merge(
                    request()->query(),
                    $ctxForcedCustomerId !== null ? ['customer_id' => (string) $ctxForcedCustomerId] : []
                ),
                static fn ($v): bool => $v !== '' && $v !== null
            );
        @endphp
        <div class="ctx-page-toolbar">
            <h1 class="ctx-h1">{{ $pageTitle }}</h1>
            <a href="{{ route('admin.customer-transactions.export', $ctxExportQuery) }}" class="ctx-btn ctx-btn--pri" title="خروجی همهٔ ردیف‌های مطابق فیلتر فعلی (نه فقط همین صفحه)">
                <i class="fa-regular fa-file-excel" aria-hidden="true"></i>
                خروجی اکسل
            </a>
        </div>
        <p class="ctx-lead">
            @if(!empty($ctxEmbedCustomer) && $ctxEmbedCustomer instanceof \App\Models\Customer)
                تراکنش‌های دفتر همین مشتری در سامانه. می‌توانید با بازهٔ تاریخ، نوع، وضعیت، درگاه و جستجو گزارش را محدود کنید.
            @else
                نمایش تمام تراکنش‌های ثبت‌شده در دفتر مشتریان (پرداخت درگاه قسط، و انواع دیگر در آینده). با فیلتر بازهٔ تاریخ شمسی، نوع، وضعیت، درگاه و جستجوی متنی می‌توانید گزارش را محدود کنید.
            @endif
        </p>

        <form class="ctx-filters" method="get" action="{{ $ctxIndex([]) }}">
            <div>
                <label for="ctx-date-from">از تاریخ (شمسی)</label>
                <input type="text" id="ctx-date-from" class="ctx-jdate-input" name="date_from" value="{{ $filterInputs['date_from'] ?? '' }}" placeholder="۱۴۰۴/۰۱/۰۱" maxlength="20" autocomplete="off" inputmode="none">
            </div>
            <div>
                <label for="ctx-date-to">تا تاریخ (شمسی)</label>
                <input type="text" id="ctx-date-to" class="ctx-jdate-input" name="date_to" value="{{ $filterInputs['date_to'] ?? '' }}" placeholder="۱۴۰۴/۱۲/۲۹" maxlength="20" autocomplete="off" inputmode="none">
            </div>
            <div>
                <label for="ctx-kind">نوع تراکنش</label>
                <select id="ctx-kind" name="kind">
                    <option value="">همه</option>
                    @foreach ($kindLabels as $k => $label)
                        <option value="{{ $k }}" @selected(($filterInputs['kind'] ?? '') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ctx-status">وضعیت</label>
                <select id="ctx-status" name="status">
                    <option value="">همه</option>
                    @foreach ($statusLabels as $k => $label)
                        <option value="{{ $k }}" @selected(($filterInputs['status'] ?? '') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ctx-gateway">درگاه</label>
                <select id="ctx-gateway" name="gateway">
                    <option value="">همه</option>
                    <option value="zibal" @selected(($filterInputs['gateway'] ?? '') === 'zibal')>زیبال</option>
                </select>
            </div>
            @if ($ctxForcedCustomerId !== null)
                <input type="hidden" name="customer_id" value="{{ (string) $ctxForcedCustomerId }}">
            @else
            <div>
                <label for="ctx-customer-id">شناسه مشتری</label>
                <input type="text" id="ctx-customer-id" name="customer_id" inputmode="numeric" pattern="[0-9]*" value="{{ $filterInputs['customer_id'] ?? '' }}" placeholder="مثلاً ۱۲" maxlength="12" autocomplete="off">
            </div>
            @endif
            <div style="grid-column: span 2; min-width: 12rem;">
                <label for="ctx-q">جستجو</label>
                <input type="search" id="ctx-q" name="q" value="{{ $filterInputs['q'] ?? '' }}" maxlength="120" placeholder="نام، موبایل، کد، پیگیری، عنوان، شرح…">
            </div>
            <div class="ctx-filters-actions">
                <button type="submit" class="ctx-btn ctx-btn--pri"><i class="fa-solid fa-filter" aria-hidden="true"></i> اعمال فیلتر</button>
                <a href="{{ $ctxIndex([]) }}" class="ctx-btn ctx-btn--ghost">پاک‌سازی</a>
            </div>
        </form>

        <div class="ctx-wrap">
            <table class="ctx-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تاریخ ثبت</th>
                        <th>مشتری</th>
                        <th>نوع</th>
                        <th>وضعیت</th>
                        <th>عنوان</th>
                        <th>شرح</th>
                        <th>مبلغ</th>
                        <th>درگاه</th>
                        <th>پیگیری</th>
                        <th>مرجع بانک</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        @php
                            $st = (string) $tx->status;
                            $tone = match ($st) {
                                'completed' => 'ok',
                                'failed' => 'danger',
                                'redirected' => 'pending',
                                default => 'muted',
                            };
                            $c = $tx->customer;
                            $custBrief = $c ? trim($c->fullName()) : '—';
                            $custSub = $c ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) ($c->mobile ?? '')) : '';
                            $kindFa = $kindLabels[$tx->kind] ?? $tx->kind;
                            $stFa = $statusLabels[$tx->status] ?? $tx->status;
                            $gwFa = $tx->gateway_key === 'zibal' ? 'زیبال' : ($tx->gateway_key ?: '—');
                            $created = \Carbon\Carbon::parse($tx->created_at);
                            $createdFa = \Hekmatinasser\Jalali\Jalali::enToFaNumbers(\Hekmatinasser\Jalali\Jalali::instance($created)->format('Y/m/d H:i'));
                        @endphp
                        <tr>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $tx->id) }}</td>
                            <td>{{ $createdFa }}</td>
                            <td>
                                {{ $custBrief }}<br>
                                <small style="opacity:0.88">{{ $custSub }}</small>
                            </td>
                            <td>{{ $kindFa }}</td>
                            <td><span class="ctx-badge ctx-badge--{{ $tone }}">{{ $stFa }}</span></td>
                            <td><span class="ctx-clip" title="{{ e($tx->title) }}">{{ $tx->title }}</span></td>
                            <td><span class="ctx-clip-2" title="{{ e($tx->detail ?? '') }}">{{ $tx->detail ?: '—' }}</span></td>
                            <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int) $tx->amount_toman, 0, '.', ',')) }} تومان</td>
                            <td>{{ $gwFa }}</td>
                            <td><span class="ctx-ltr">{{ $tx->track_id !== null ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $tx->track_id) : '—' }}</span></td>
                            <td><span class="ctx-ltr">{{ $tx->bank_reference ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers($tx->bank_reference) : '—' }}</span></td>
                            <td>
                                <button type="button" class="ctx-btn ctx-btn--ghost ctx-open-detail" data-id="{{ $tx->id }}">
                                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                    جزئیات
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" style="text-align:center;padding:1.4rem;color:var(--muted);font-weight:800">رکوردی با این فیلترها یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ctx-pagination">
            {{ $transactions->links() }}
        </div>
    </div>

    <dialog id="ctx-dialog" aria-labelledby="ctx-dialog-title">
        <div class="ctx-dlg-inner">
            <button type="button" class="ctx-dlg-close" data-ctx-close aria-label="بستن">&times;</button>
            <div class="ctx-dlg-head">
                <h2 id="ctx-dialog-title" class="ctx-dlg-title">جزئیات تراکنش</h2>
                <p id="ctx-dialog-sub" class="ctx-dlg-sub" aria-live="polite"></p>
            </div>
            <div class="ctx-dlg-scroll">
                <div id="ctx-detail-fields" class="ctx-detail-grid" role="region" aria-label="جزئیات رکورد"></div>
                <div id="ctx-meta-block" class="ctx-detail-card ctx-detail-card--wide ctx-detail-meta-wrap" style="display:none" hidden>
                    <div class="ctx-detail-card__head">
                        <span class="ctx-detail-card__ico" aria-hidden="true"><i class="fa-solid fa-code"></i></span>
                        <span class="ctx-detail-card__label">دادهٔ ساختاری (meta)</span>
                    </div>
                    <div class="ctx-detail-card__value">
                        <pre id="ctx-meta-pre" class="ctx-meta-pre" aria-label="JSON"></pre>
                    </div>
                </div>
            </div>
            <div class="ctx-dlg-footer">
                <a id="ctx-customer-link" href="#" class="ctx-btn ctx-btn--pri" style="display:none" target="_blank" rel="noopener">مشتری در لیست</a>
                <button type="button" class="ctx-btn ctx-btn--ghost" data-ctx-close>بستن</button>
            </div>
        </div>
    </dialog>
