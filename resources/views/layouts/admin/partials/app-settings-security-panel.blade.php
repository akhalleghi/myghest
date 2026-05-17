<section class="app-settings-panel" data-settings-panel="security" hidden>
    <h4 class="app-settings-panel-title">امنیت</h4>
    <p class="app-settings-panel-subtitle">برای افزایش امنیت دسترسی‌ها، سیاست‌های ورود را تنظیم کنید.</p>
    <form method="post" action="{{ route('admin.app-settings.security.update') }}">
        @csrf
        <div class="app-settings-card">
            <h4>ورود مشتریان (پنل کاربر)</h4>
            <p class="app-settings-card-desc">
                با فعال‌سازی این گزینه، پس از تأیید نام کاربری، رمز عبور و کپچا، کد یک‌بارمصرف پیامکی به موبایل ثبت‌شده در پرونده ارسال می‌شود و ورود بدون آن کد ممکن نیست.
            </p>
            <div class="app-settings-field">
                <label for="customer-login-two-factor">فعال بودن تأیید دو مرحله‌ای هنگام ورود مشتریان</label>
                <select id="customer-login-two-factor" name="customer_login_two_factor_enabled" required>
                    <option value="0" @selected(old('customer_login_two_factor_enabled', \App\Support\CustomerLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '0')>غیرفعال</option>
                    <option value="1" @selected(old('customer_login_two_factor_enabled', \App\Support\CustomerLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '1')>فعال</option>
                </select>
                @error('customer_login_two_factor_enabled')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="app-settings-actions">
            <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
        </div>
    </form>
</section>
