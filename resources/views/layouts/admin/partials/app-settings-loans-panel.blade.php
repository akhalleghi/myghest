@php
    use App\Support\GuaranteeReturnOtpSettings;
    use App\Support\LoanCreationOtpSettings;
    use App\Support\LoanInstallmentRoundingSettings;
@endphp

<section class="app-settings-panel" data-settings-panel="loans" @if(($adminAppSettingsActivePanel ?? '') !== 'loans') hidden @endif>
    <h4 class="app-settings-panel-title">وام‌ها</h4>
    <p class="app-settings-panel-subtitle">سیاست‌های ثبت پرونده وام از پنل ادمین.</p>
    <form method="post" action="{{ route('admin.app-settings.loans.update') }}">
        @csrf
        <div class="app-settings-card">
            <h4>تایید پیامکی هنگام ایجاد وام</h4>
            <p class="app-settings-card-desc">
                با فعال بودن این گزینه، پیش از ثبت پرونده وام جدید برای مشتری، ارسال و تایید کد یک‌بارمصرف به موبایل ثبت‌شدهٔ مشتری الزامی می‌شود.
                ویرایش پرونده‌های موجود تحت تأثیر این تنظیم قرار نمی‌گیرد.
            </p>
            <div class="app-settings-field">
                <label for="loan-creation-customer-otp">الزام تایید پیامکی مشتری برای ایجاد وام</label>
                <select id="loan-creation-customer-otp" name="loan_creation_customer_otp_enabled" required>
                    <option value="0" @selected(old('loan_creation_customer_otp_enabled', LoanCreationOtpSettings::isEnabled() ? '1' : '0') === '0')>غیرفعال</option>
                    <option value="1" @selected(old('loan_creation_customer_otp_enabled', LoanCreationOtpSettings::isEnabled() ? '1' : '0') === '1')>فعال</option>
                </select>
                @error('loan_creation_customer_otp_enabled')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="app-settings-card">
            <h4>تایید پیامکی هنگام عودت ضمانت</h4>
            <p class="app-settings-card-desc">
                با فعال بودن این گزینه، هنگام ثبت عودت چک یا اوراق ضمانتی (طلا / سایر)، ارسال و تایید کد یک‌بارمصرف به موبایل مشتری و بارگذاری مستند عودت الزامی می‌شود.
            </p>
            <div class="app-settings-field">
                <label for="guarantee-return-customer-otp">الزام تایید پیامکی مشتری برای عودت ضمانت</label>
                <select id="guarantee-return-customer-otp" name="guarantee_return_customer_otp_enabled" required>
                    <option value="0" @selected(old('guarantee_return_customer_otp_enabled', GuaranteeReturnOtpSettings::isEnabled() ? '1' : '0') === '0')>غیرفعال</option>
                    <option value="1" @selected(old('guarantee_return_customer_otp_enabled', GuaranteeReturnOtpSettings::isEnabled() ? '1' : '0') === '1')>فعال</option>
                </select>
                @error('guarantee_return_customer_otp_enabled')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="app-settings-card">
            <h4>رندسازی مبلغ اقساط</h4>
            <p class="app-settings-card-desc">
                هنگام ثبت وام جدید، مبلغ پایهٔ هر قسط تا نزدیک‌ترین ۱۰٬۰۰۰ تومان پایین‌تر رند می‌شود.
                مبلغ خرد باقی‌مانده طبق گزینهٔ زیر به قسط اول، قسط آخر یا پیش‌پرداخت اضافه می‌شود.
            </p>
            <div class="app-settings-field">
                <label for="loan-installment-remainder-target">محل لحاظ مبلغ خرد اقساط</label>
                <select id="loan-installment-remainder-target" name="loan_installment_remainder_target" required>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_LAST }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_LAST)>قسط آخر</option>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_FIRST }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_FIRST)>قسط اول</option>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT)>پیش‌پرداخت</option>
                </select>
                @error('loan_installment_remainder_target')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="app-settings-actions">
            <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
        </div>
    </form>
</section>
