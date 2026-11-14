@extends('layouts.admin.app')
@section('title', 'مدیریت مشتریان چکی')
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/cheque-sales.css') }}?v={{ filemtime(public_path('css/cheque-sales.css')) }}">
<link rel="stylesheet" href="{{ asset('css/cheque-details.css') }}?v={{ filemtime(public_path('css/cheque-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/cheque-customer-form.css') }}?v={{ filemtime(public_path('css/cheque-customer-form.css')) }}">
@endpush
@section('content')
@php
    $money = fn($v) => \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int)$v));
    $jdate = fn($d) => $d ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers(\Hekmatinasser\Jalali\Jalali::instance($d)->format('Y/m/d')) : '—';
    $todayJ = \Hekmatinasser\Jalali\Jalali::instance(now())->format('Y/m/d');
@endphp
<main class="cq-page" data-cheque-module data-base="{{ url('/admin/cheque-sales') }}" data-today="{{ $todayJ }}" data-initial-customer="{{ $initialCustomerId }}" @adminCan('cheques.sms') data-can-sms="1" @else data-can-sms="0" @endadminCan>
    <header class="cq-header"><div><span class="cq-eyebrow">فروش چکی · مستقل از فروش اقساطی</span><h1>مدیریت مشتریان چکی</h1><p>ثبت خرید، پیگیری چک و سوابق وصول و انتقال</p></div>
        @adminCan('cheques.customers.create')<button class="cq-btn cq-primary" data-customer-new><i class="fa-solid fa-user-plus"></i> افزودن مشتری چکی</button>@endadminCan
    </header>
    @if($errors->any())<div class="cq-error">{{ $errors->first() }}</div>@endif
    <nav class="cq-tabs" aria-label="بخش‌های فروش چکی">
        <a class="{{ $activeView==='customers'?'is-active':'' }}" href="{{ route('admin.cheques.index') }}">مشتریان چکی</a>
        <a class="{{ $activeView==='cheques'?'is-active':'' }}" href="{{ route('admin.cheques.index',['view'=>'cheques']) }}">گردش چک‌ها</a>
        @adminCan('cheques.reports')<a class="{{ $activeView==='reports'?'is-active':'' }}" href="{{ route('admin.cheques.reports') }}">گزارش فروش چکی</a>@endadminCan
    </nav>
    @if($selectedCustomer)<section class="cq-panel cq-customer-banner"><strong>{{ $selectedCustomer->fullName() }} · CC {{ $selectedCustomer->id }}</strong><span dir="ltr">{{ $selectedCustomer->mobile }}</span>
        @adminCan('cheques.create')<button class="cq-btn cq-primary" data-sale-new="{{ $selectedCustomer->id }}" data-name="{{ $selectedCustomer->fullName() }}">افزودن چک / پرونده خرید</button>@endadminCan
        <button type="button" class="cq-btn" data-customer-notes="{{ $selectedCustomer->id }}">یادداشت‌های مشتری</button></section>@endif
    <details class="cq-panel cq-filter-panel"><summary><i class="fa-solid fa-sliders"></i> جستجو و فیلتر چک‌ها <small>برای تنظیم فیلترها باز کنید</small></summary><form class="cq-filters" method="get">
        <input type="hidden" name="view" value="{{ $activeView }}">
        @if($selectedCustomer)<input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">@endif
        <label>جستجو<input name="q" value="{{ request('q') }}" placeholder="نام، موبایل، شماره چک، صیاد یا بانک"></label>
        @if($activeView!=='customers')
        <label>پوشه<select name="folder"><option value="">همه چک‌ها</option>@foreach(\App\Models\ChequeInstrument::FOLDERS as $key=>$label)<option value="{{ $key }}" @selected(request('folder')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>حداقل مبلغ (تومان)<input name="min_amount" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="{{ request('min_amount') }}"></label>
        <label>حداکثر مبلغ (تومان)<input name="max_amount" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="{{ request('max_amount') }}"></label>
        @foreach(['due_from'=>'سررسید از', 'due_to'=>'سررسید تا','purchase_from'=>'خرید از','purchase_to'=>'خرید تا'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" data-cheque-date autocomplete="off" value="{{ request($key) }}" placeholder="{{ $todayJ }}" dir="ltr"></label>@endforeach
        @endif
        <button class="cq-btn cq-primary">اعمال فیلتر</button><a class="cq-btn" href="{{ route('admin.cheques.index',['view'=>$activeView,'customer_id'=>$selectedCustomer?->id]) }}">پاک‌کردن فیلتر</a>
        @if($activeView!=='customers')@adminCan('cheques.export')<a class="cq-btn" href="{{ route('admin.cheques.export',request()->except(['cheque_page','customer_page','workspace'])) }}"><i class="fa-solid fa-file-excel"></i> خروجی اکسل همه نتایج</a>@endadminCan @endif
    </form></details>
    @if($activeView==='customers')
    <div class="cq-table-wrap"><table class="cq-table"><thead><tr><th>کد / مشتری</th><th>موبایل</th><th>تعداد چک</th><th>مجموع چک با کارمزد</th><th>مانده روز تا سررسید</th><th>نزدیک‌ترین سررسید باز</th><th>کیف پول چکی</th><th>پیامک</th><th>یادداشت</th><th>عملیات</th></tr></thead><tbody>
        @forelse($customers as $c)
        @php
            $all = $c->sales->flatMap->cheques;
            $nearest = $all->filter(fn($row)=>$finance->balance($row)['remaining_toman']>0)->sortBy('due_date')->first();
        @endphp
        <tr>
        <td><button type="button" class="cq-customer-link" data-customer-manage="{{ $c->id }}" data-name="{{ $c->fullName() }}">{{ $c->fullName() }}</button><small>CC {{ $c->id }}</small></td>
        <td dir="ltr">{{ $c->mobile }}@if($c->mobile_verified_at)<small class="cq-good">تأییدشده</small>@endif</td>
        <td>{{ $money($all->count()) }}</td><td>{{ $money($all->sum('amount_toman')) }} تومان</td>
        <td>{{ $nearest ? $money($finance->balance($nearest)['days_to_due']) : '—' }}</td><td>{{ $jdate($nearest?->due_date) }}</td>
        <td>{{ $money($c->wallet_balance_toman) }} تومان</td>
        <td><span class="cq-badge {{ $c->sms_enabled?'cq-good':'' }}">{{ $c->sms_enabled?'فعال':'غیرفعال' }}</span>
            @adminCan('cheques.sms')<button class="cq-btn cq-icon" data-customer-sms="{{ $c->id }}" title="پیش‌نمایش خلاصه بدهی مشتری چکی"><i class="fa-solid fa-comment-sms"></i></button>@endadminCan
        </td>
        <td><button type="button" class="cq-btn cq-icon" data-customer-notes="{{ $c->id }}" title="یادداشت‌های مشتری و تمام چک‌ها" aria-label="یادداشت‌های مشتری و تمام چک‌ها"><i class="fa-solid fa-note-sticky"></i></button></td>
        <td><div class="cq-actions"><button type="button" class="cq-btn" data-customer-manage="{{ $c->id }}" data-name="{{ $c->fullName() }}">مدیریت چک‌ها</button><div class="cq-row-menu"><button type="button" class="cq-btn cq-icon" data-row-menu aria-expanded="false" aria-label="سایر عملیات"><i class="fa-solid fa-ellipsis-vertical"></i></button><div class="cq-menu" hidden>
        @adminCan('cheques.customers.update')<button class="cq-btn" data-profile="{{ json_encode($c) }}">ویرایش مشتری / یادداشت</button>@endadminCan
        @adminCan('cheques.create')<button class="cq-btn" data-sale-new="{{ $c->id }}" data-name="{{ $c->fullName() }}">افزودن چک</button>@endadminCan
        @adminCan('cheques.wallet')<button class="cq-btn" data-wallet="{{ $c->id }}">شارژ / برداشت کیف پول</button>@endadminCan
        </div></div></div></td></tr>
        @empty<tr><td colspan="10" class="cq-empty">هنوز مشتری چکی ثبت نشده است. از «افزودن مشتری چکی» شروع کنید.</td></tr>@endforelse
    </tbody></table></div>{{ $customers->links() }}
    @else
    @include('admin.cheques.partials.cheque-results')
    @endif
    <details class="cq-panel"><summary>آمار و نمودارهای مستقل فروش چکی</summary>@include('admin.cheques.statistics', ['chequeStatistics'=>$statistics, 'showChequeTables'=>true])</details>

    <dialog class="cq-dialog cq-management" id="cq-management" aria-labelledby="cq-management-title">
        <header data-admin-mw-head><div><h2 id="cq-management-title">مدیریت چک‌های مشتری</h2><p class="cq-hint">پرونده‌ها، مانده‌ها و سوابق مشتری چکی</p></div><button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن پنجره">بستن ×</button></header>
        <div id="cq-management-body" aria-live="polite"></div>
    </dialog>
    <dialog class="cq-dialog cq-dialog-wide" id="cq-customer" aria-labelledby="cq-customer-title"><form id="cq-customer-form" enctype="multipart/form-data"><header data-admin-mw-head><div><h2 id="cq-customer-title">اطلاعات مشتری چکی</h2><p class="cq-hint">ثبت مشخصات، احراز شماره و مدارک اختیاری مشتری</p></div><button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن پنجره">بستن ×</button></header><div class="cq-form-grid">
        <input type="hidden" name="id"><input type="hidden" name="verification_token">
        @foreach(['first_name'=>'نام','last_name'=>'نام خانوادگی','father_name'=>'نام پدر','national_id'=>'کد ملی','mobile'=>'موبایل','phone_landline'=>'تلفن ثابت','city'=>'شهر','postal_code'=>'کد پستی','email'=>'ایمیل'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" @if(in_array($key,['first_name','last_name','mobile'])) required @endif maxlength="{{ $key==='mobile'?11:($key==='national_id'||$key==='postal_code'?10:120) }}"></label>@endforeach
        <label class="cq-wide">آدرس<textarea name="address" maxlength="2000"></textarea></label><label class="cq-wide">یادداشت مشتری<textarea name="note" maxlength="5000"></textarea></label>
        <label class="cq-check"><input type="checkbox" name="sms_enabled" value="1" checked> پیامک فعال</label><label class="cq-check"><input type="checkbox" name="auto_reminders_enabled" value="1"> یادآوری خودکار چک (اختیاری)</label>
        @adminCan('cheques.verification')<button type="button" class="cq-btn" data-verify-customer>احراز اختیاری شماره با پیامک</button><span id="cq-customer-verified"></span>@endadminCan
    </div>
    @include('admin.customers.partials.media-editor', ['customerMediaBaseUrl'=>url('/admin/cheque-sales/customers')])
    <p class="cq-hint">این مشتری در بخش چکی ثبت می‌شود و حساب کاربری پنل مشتری یا پرونده اقساطی ایجاد نمی‌کند.</p><p class="cq-error" data-form-error hidden></p><footer><button class="cq-btn cq-primary">ذخیره مشتری</button></footer></form></dialog>

    <dialog class="cq-dialog cq-dialog-wide" id="cq-sale"><form id="cq-sale-form"><header data-admin-mw-head><div><h2>افزودن پرونده فروش چکی</h2><span id="cq-sale-customer"></span></div><button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن پنجره">بستن ×</button></header><input type="hidden" name="customer_id"><input type="hidden" name="verification_token">
    <div class="cq-form-grid">
        <label>تاریخ خرید<input data-cheque-date autocomplete="off" name="purchased_at" value="{{ $todayJ }}" dir="ltr" required placeholder="1405/08/01"></label><label>سررسید واریز به حساب مشتری<input data-cheque-date autocomplete="off" name="disbursement_due_date" dir="ltr" placeholder="1405/08/01"></label>
        <label>مبلغ خرید (تومان)<input name="purchase_amount_toman" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="1" max="1000000000000" required></label><label>تعداد ماه<input name="months" type="number" min="1" max="120" value="1" required></label>
        <label>نرخ کارمزد (درصد)<input name="interest_rate" type="number" step="0.0001" min="0" max="100" value="0" required></label><label>روش کارمزد<select name="profit_method" required><option value="monthly">ماهانه</option><option value="bank">سالانه</option></select></label>
        <label>پیش‌پرداخت (تومان)<input name="down_payment_toman" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="0" required></label><label>تخفیف (تومان)<input name="discount_toman" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="0" required></label>
        <label>ضریب روزانه دیرکرد برگشت<input name="daily_late_coefficient" type="number" min="0" max="0.1" step="0.00000001" value="0" required><small>مثال: ۰٫۰۰۱ یعنی ۰٫۱ درصد در روز</small></label><label>ضریب روزانه زودکرد<input name="daily_early_coefficient" type="number" min="0" max="0.1" step="0.00000001" value="0" required></label>
        <div class="cq-wide cq-quote"><button type="button" class="cq-btn" data-quote>محاسبه مبلغ چک‌ها با کارمزد</button><strong id="cq-quote-result" aria-live="polite">—</strong></div>
    </div>
    <section class="cq-section"><h3>اطلاعات چک‌ها</h3><p class="cq-hint">جمع مبالغ چک‌ها باید با مبلغ نهایی قرارداد برابر باشد. مبلغ هر چک را با کارمزد وارد کنید. سررسیدها می‌توانند متفاوت باشند.</p><div id="cq-cheque-rows"></div><button type="button" class="cq-btn" data-add-cheque>+ افزودن چک دیگر</button></section>
    <section class="cq-section"><h3>دارنده چک و تأیید ضمانت</h3><label class="cq-check"><input name="guarantee_by_self" type="checkbox" value="1" checked> چک متعلق به خریدار است</label><div class="cq-form-grid" id="cq-owner-fields" hidden>
    @foreach(['name'=>'نام و نام خانوادگی دارنده','mobile'=>'موبایل دارنده','father_name'=>'نام پدر (اختیاری)','national_id'=>'کد ملی (اختیاری)','address'=>'آدرس (اختیاری)'] as $key=>$label)<label>{{ $label }}<input name="owner_details[{{ $key }}]" @if(in_array($key,['name','mobile'])) data-owner-required @endif></label>@endforeach
    </div><p class="cq-hint">برای دارنده متفاوت، تأیید پیامکی الزامی است. برای خود خریدار اختیاری است. تأیید به شرایط و تمام چک‌های همین فرم متصل است.</p>
    @adminCan('cheques.verification')<button type="button" class="cq-btn" data-verify-owner>پیش‌نمایش و ارسال کد تأیید دارنده</button><span id="cq-owner-verified"></span>@endadminCan
    </section><label>یادداشت پرونده<textarea name="note" maxlength="5000"></textarea></label><p data-form-error class="cq-error" hidden></p><footer><button class="cq-btn cq-primary">ثبت پرونده و چک‌ها</button></footer></form></dialog>
    <template id="cq-cheque-template"><fieldset class="cq-cheque-row"><legend>مشخصات چک</legend><div class="cq-form-grid">
        <label>شماره صیاد<input data-field="sayad_number" inputmode="numeric" pattern="[0-9۰-۹]{16}" maxlength="16" required></label><label>شماره چک<input data-field="serial" maxlength="64" required></label><label>نام بانک<input data-field="bank_name" maxlength="120" required></label><label>شعبه<input data-field="branch" maxlength="120"></label><label>سررسید<input data-cheque-date autocomplete="off" data-field="due_date" dir="ltr" placeholder="1405/08/01" required></label><label>مبلغ با کارمزد (تومان)<input data-field="amount_toman" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="1" max="1000000000000" required></label><label class="cq-wide">یادداشت چک<textarea data-field="note" maxlength="2000"></textarea></label></div><button type="button" class="cq-btn cq-danger" data-remove-cheque>حذف این ردیف از فرم</button></fieldset></template>

    <dialog class="cq-dialog" id="cq-action"><form id="cq-action-form"><header data-admin-mw-head><h2>عملیات چک</h2><button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن پنجره">بستن ×</button></header><input type="hidden" name="id"><input type="hidden" name="version"><input type="hidden" name="request_uuid"><input type="hidden" name="verification_token"><p id="cq-action-summary" class="cq-quote"></p><div class="cq-form-grid">
        <label>عملیات<select name="action" required><option value="receive">دریافت / وصول وجه</option><option value="bank">انتقال به بانک</option><option value="person">انتقال به شخص</option><option value="bounce">ثبت برگشت چک</option><option value="return_owner">عودت به صاحب چک</option><option value="return_buyer">عودت به خریدار</option><option value="recover">دریافت مجدد اصل چک</option></select></label>
        <label>تاریخ عملیات<input data-cheque-date autocomplete="off" name="date" value="{{ $todayJ }}" dir="ltr" required></label>
        <label data-receive-field>مبلغ دریافتی (تومان)<input name="amount_toman" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="1"></label><label data-receive-field>روش دریافت<select name="method" required><option value="cheque_collection">وصول بانکی چک</option><option value="bank_transfer">کارت به کارت / واریز بانکی</option><option value="cash">نقدی</option></select></label>
        <label data-destination-field><span data-destination-name-label>نام مقصد</span><input name="destination[name]" maxlength="200"></label>
        <label data-destination-field data-destination-for="bank">شعبه بانک (اختیاری)<input name="destination[branch]" maxlength="120"></label>
        <label data-destination-field data-destination-for="bank">شماره حساب مقصد (اختیاری)<input name="destination[account_number]" inputmode="numeric" maxlength="30" dir="ltr"></label>
        <label data-destination-field data-destination-for="bank">شماره شبا (اختیاری)<input name="destination[iban]" maxlength="26" dir="ltr" placeholder="IR…"></label>
        <label data-destination-field data-destination-for="person">موبایل شخص مقصد (اختیاری)<input name="destination[mobile]" maxlength="11" inputmode="numeric" dir="ltr"></label>
        <label data-destination-field data-destination-for="person">کد ملی شخص مقصد (اختیاری)<input name="destination[national_id]" maxlength="10" inputmode="numeric" dir="ltr"></label>
        <label class="cq-wide" data-destination-field data-destination-for="person">آدرس شخص مقصد (اختیاری)<textarea name="destination[address]" maxlength="2000"></textarea></label>
        <label class="cq-wide" data-destination-field>توضیحات مقصد (اختیاری)<textarea name="destination[details]" maxlength="2000"></textarea></label>
        <label class="cq-wide">توضیح عملیات (الزامی)<textarea name="note" maxlength="2000" required></textarea></label>
        <button type="button" class="cq-btn" data-verify-return hidden>تأیید پیامکی تحویل به گیرنده</button><span id="cq-return-verified"></span>
    </div><p class="cq-hint">عودت و انتقال، اصل یا دیرکرد بدهی را حذف نمی‌کند. هر دریافت ثبت‌شده دارای سابقه مالی مستقل است.</p><p data-form-error class="cq-error" hidden></p><footer><button class="cq-btn cq-primary">بررسی و تأیید عملیات</button></footer></form></dialog>
    @include('admin.cheques.partials.details-dialog')
    @include('admin.cheques.partials.customer-notes-dialog')
    @include('admin.customers.partials.media-dialogs')
</main>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script src="{{ asset('js/cheque-windowing.js') }}?v={{ filemtime(public_path('js/cheque-windowing.js')) }}" defer></script>
    <script src="{{ asset('js/cheque-forms.js') }}?v={{ filemtime(public_path('js/cheque-forms.js')) }}" defer></script>
    <script src="{{ asset('js/cheque-money.js') }}?v={{ filemtime(public_path('js/cheque-money.js')) }}" defer></script>
    <script src="{{ asset('js/cheque-details.js') }}?v={{ filemtime(public_path('js/cheque-details.js')) }}" defer></script>
    <script src="{{ asset('js/cheque-sales.js') }}?v={{ filemtime(public_path('js/cheque-sales.js')) }}" defer></script>
@endpush
