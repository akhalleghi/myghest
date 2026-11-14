@php
    $money = fn($v) => \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((int)$v));
    $jdate = fn($d) => $d ? \Hekmatinasser\Jalali\Jalali::enToFaNumbers(\Hekmatinasser\Jalali\Jalali::instance($d)->format('Y/m/d')) : '—';
    $todayJ = \Hekmatinasser\Jalali\Jalali::instance(now())->format('Y/m/d');
    $activeView = 'cheques';
@endphp
    @if($selectedCustomer)<section class="cq-panel cq-customer-banner"><strong>{{ $selectedCustomer->fullName() }} · CC {{ $selectedCustomer->id }}</strong><span dir="ltr">{{ $selectedCustomer->mobile }}</span>
        @adminCan('cheques.create')<button class="cq-btn cq-primary" data-sale-new="{{ $selectedCustomer->id }}" data-name="{{ $selectedCustomer->fullName() }}">افزودن چک / پرونده خرید</button>@endadminCan
        <button type="button" class="cq-btn" data-customer-notes="{{ $selectedCustomer->id }}">یادداشت‌های مشتری</button></section>@endif
    <details class="cq-panel cq-filter-panel"><summary><i class="fa-solid fa-sliders"></i> جستجو و فیلتر چک‌ها <small>برای تنظیم فیلترها باز کنید</small></summary><form class="cq-filters" method="get" data-workspace-filter>
        <input type="hidden" name="view" value="{{ $activeView }}">
        @if($selectedCustomer)<input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">@endif
        <label>جستجو<input name="q" value="{{ request('q') }}" placeholder="نام، موبایل، شماره چک، صیاد یا بانک"></label>
        @if($activeView!=='customers')
        <label>پوشه<select name="folder"><option value="">همه چک‌ها</option>@foreach(\App\Models\ChequeInstrument::FOLDERS as $key=>$label)<option value="{{ $key }}" @selected(request('folder')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>حداقل مبلغ (تومان)<input name="min_amount" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="{{ request('min_amount') }}"></label>
        <label>حداکثر مبلغ (تومان)<input name="max_amount" type="text" inputmode="numeric" data-cheque-money dir="ltr" maxlength="32" min="0" value="{{ request('max_amount') }}"></label>
        @foreach(['due_from'=>'سررسید از', 'due_to'=>'سررسید تا','purchase_from'=>'خرید از','purchase_to'=>'خرید تا'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" data-cheque-date autocomplete="off" value="{{ request($key) }}" placeholder="{{ $todayJ }}" dir="ltr"></label>@endforeach
        @endif
        <button class="cq-btn cq-primary">اعمال فیلتر</button><a class="cq-btn" data-workspace-reset href="{{ route('admin.cheques.index',['view'=>$activeView,'customer_id'=>$selectedCustomer?->id]) }}">پاک‌کردن فیلتر</a>
        @if($activeView!=='customers')@adminCan('cheques.export')<a class="cq-btn" href="{{ route('admin.cheques.export',request()->except(['cheque_page','customer_page','workspace'])) }}"><i class="fa-solid fa-file-excel"></i> خروجی اکسل همه نتایج</a>@endadminCan @endif
    </form></details>
<details class="cq-panel cq-profile-info"><summary>اطلاعات مشتری و تنظیمات</summary><div class="cq-form-grid">
@foreach(['father_name'=>'نام پدر','national_id'=>'کد ملی','phone_landline'=>'تلفن ثابت','city'=>'شهر','address'=>'آدرس'] as $key=>$label)<div><small>{{ $label }}</small><p>{{ $selectedCustomer->$key ?: '—' }}</p></div>@endforeach
<div><small>کیف پول چکی</small><p>{{ $money($selectedCustomer->wallet_balance_toman) }} تومان</p></div><div><small>وضعیت پیامک</small><p>{{ $selectedCustomer->sms_enabled ? 'فعال' : 'غیرفعال' }}</p></div>
</div><div class="cq-actions">
@adminCan('cheques.customers.update')<button type="button" class="cq-btn" data-profile="{{ json_encode($selectedCustomer) }}">ویرایش اطلاعات مشتری</button>@endadminCan
@adminCan('cheques.wallet')<button type="button" class="cq-btn" data-wallet="{{ $selectedCustomer->id }}">شارژ / برداشت کیف پول</button>@endadminCan
</div></details>
@include('admin.cheques.partials.cheque-results')
