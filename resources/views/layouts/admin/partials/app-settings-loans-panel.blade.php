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
                هنگام ثبت یا ویرایش وام، مبلغ پایهٔ هر قسط تا حد رند (پیشنهادی یا ورود دستی) پایین‌تر گرد می‌شود.
                مبلغ خرد باقی‌مانده طبق گزینهٔ زیر به قسط اول، قسط آخر، پیش‌پرداخت اضافه می‌شود یا بین اقساط تقسیم می‌گردد.
            </p>
            <div class="app-settings-field app-settings-field--stack">
                <label for="loan-installment-rounding-step-preset">حد رندسازی (تومان)</label>
                @php
                    $currentRoundingStep = (int) old('loan_installment_rounding_step_toman', LoanInstallmentRoundingSettings::roundingStepToman());
                    $roundingPresets = LoanInstallmentRoundingSettings::roundingStepPresets();
                    $roundingStepIsPreset = in_array($currentRoundingStep, $roundingPresets, true);
                @endphp
                <input
                    type="hidden"
                    id="loan-installment-rounding-step-value"
                    name="loan_installment_rounding_step_toman"
                    value="{{ $currentRoundingStep }}"
                >
                <div style="display:flex; flex-wrap:wrap; gap:0.55rem; align-items:stretch;">
                    <select
                        id="loan-installment-rounding-step-preset"
                        aria-label="انتخاب حد رندسازی"
                        style="flex:1 1 12rem; min-width:10rem;"
                    >
                        @foreach ($roundingPresets as $stepOption)
                            <option
                                value="{{ $stepOption }}"
                                @selected($roundingStepIsPreset && $currentRoundingStep === $stepOption)
                            >{{ LoanInstallmentRoundingSettings::roundingStepLabel($stepOption) }}</option>
                        @endforeach
                        <option value="custom" @selected(! $roundingStepIsPreset)>مقدار دلخواه (دستی)</option>
                    </select>
                    <input
                        id="loan-installment-rounding-step"
                        type="number"
                        value="{{ $currentRoundingStep }}"
                        min="{{ LoanInstallmentRoundingSettings::ROUNDING_STEP_MIN_TOMAN }}"
                        max="{{ LoanInstallmentRoundingSettings::ROUNDING_STEP_MAX_TOMAN }}"
                        step="1"
                        inputmode="numeric"
                        aria-label="مبلغ دلخواه حد رندسازی"
                        aria-describedby="loan-installment-rounding-step-hint"
                        @disabled($roundingStepIsPreset)
                        style="flex:1 1 10rem; min-width:8rem;"
                    >
                </div>
                <p id="loan-installment-rounding-step-hint" class="app-settings-card-desc" style="margin:0.45rem 0 0;">
                    ابتدا گزینه را انتخاب کنید؛ فقط وقتی «مقدار دلخواه (دستی)» انتخاب شود، ورود مبلغ دستی فعال می‌شود
                    (حداقل {{ number_format(LoanInstallmentRoundingSettings::ROUNDING_STEP_MIN_TOMAN, 0, '.', ',') }} و حداکثر {{ number_format(LoanInstallmentRoundingSettings::ROUNDING_STEP_MAX_TOMAN, 0, '.', ',') }} تومان).
                </p>
                @error('loan_installment_rounding_step_toman')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="app-settings-field">
                <label for="loan-installment-remainder-target">محل لحاظ مبلغ خرد اقساط</label>
                <select id="loan-installment-remainder-target" name="loan_installment_remainder_target" required>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_LAST }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_LAST)>قسط آخر</option>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_FIRST }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_FIRST)>قسط اول</option>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_DOWN_PAYMENT)>پیش‌پرداخت</option>
                    <option value="{{ LoanInstallmentRoundingSettings::REMAINDER_DISTRIBUTE }}" @selected(old('loan_installment_remainder_target', LoanInstallmentRoundingSettings::remainderTarget()) === LoanInstallmentRoundingSettings::REMAINDER_DISTRIBUTE)>تقسیم بر اقساط</option>
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

@once
    @push('scripts')
        <script>
            (function () {
                var preset = document.getElementById('loan-installment-rounding-step-preset');
                var input = document.getElementById('loan-installment-rounding-step');
                var hidden = document.getElementById('loan-installment-rounding-step-value');
                if (!preset || !input || !hidden) return;

                var applyMode = function (fromUser) {
                    var isCustom = preset.value === 'custom';
                    input.disabled = !isCustom;
                    if (!isCustom) {
                        input.value = preset.value;
                        hidden.value = preset.value;
                    } else {
                        hidden.value = String(parseInt(String(input.value || ''), 10) || '');
                        if (fromUser) {
                            input.focus();
                            input.select();
                        }
                    }
                };

                preset.addEventListener('change', function () {
                    applyMode(true);
                });

                input.addEventListener('input', function () {
                    if (preset.value !== 'custom') return;
                    var n = parseInt(String(input.value || ''), 10);
                    hidden.value = Number.isFinite(n) ? String(n) : '';
                });

                input.addEventListener('change', function () {
                    if (preset.value !== 'custom') return;
                    var n = parseInt(String(input.value || ''), 10);
                    hidden.value = Number.isFinite(n) ? String(n) : '';
                });

                applyMode(false);
            })();
        </script>
    @endpush
@endonce
