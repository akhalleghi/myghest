@php
    use App\Models\LoginAccessBlock;
    use App\Support\AdminLoginSecuritySettings;
    use App\Support\CustomerLoginSecuritySettings;
    use App\Support\PortalLoginSecuritySettings;

    $customerSessionMinutes = old('customer_login_session_lifetime_minutes', PortalLoginSecuritySettings::sessionLifetimeMinutes(LoginAccessBlock::GUARD_CUSTOMER));
    $customerMaxAttempts = old('customer_login_max_failed_attempts', PortalLoginSecuritySettings::maxFailedAttempts(LoginAccessBlock::GUARD_CUSTOMER));
    $adminSessionMinutes = old('admin_login_session_lifetime_minutes', PortalLoginSecuritySettings::sessionLifetimeMinutes(LoginAccessBlock::GUARD_ADMIN));
    $adminMaxAttempts = old('admin_login_max_failed_attempts', PortalLoginSecuritySettings::maxFailedAttempts(LoginAccessBlock::GUARD_ADMIN));
@endphp

<section class="app-settings-panel" data-settings-panel="security" hidden>
    <h4 class="app-settings-panel-title">امنیت</h4>
    <p class="app-settings-panel-subtitle">برای افزایش امنیت دسترسی‌ها، سیاست‌های ورود و نشست را تنظیم کنید.</p>
    <form method="post" action="{{ route('admin.app-settings.security.update') }}">
        @csrf
        <div class="app-settings-card">
            <h4>ورود مشتریان (پنل کاربر)</h4>
            <p class="app-settings-card-desc">
                با فعال‌سازی تأیید دو مرحله‌ای، پس از تأیید نام کاربری، رمز عبور و کپچا، کد یک‌بارمصرف پیامکی ارسال می‌شود.
                زمان نشست، حداکثر تلاش ناموفق و مسدودی‌ها فقط برای صفحه ورود مشتریان اعمال می‌شود.
            </p>
            <div class="app-settings-field">
                <label for="customer-login-two-factor">فعال بودن تأیید دو مرحله‌ای هنگام ورود مشتریان</label>
                <select id="customer-login-two-factor" name="customer_login_two_factor_enabled" required>
                    <option value="0" @selected(old('customer_login_two_factor_enabled', CustomerLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '0')>غیرفعال</option>
                    <option value="1" @selected(old('customer_login_two_factor_enabled', CustomerLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '1')>فعال</option>
                </select>
                @error('customer_login_two_factor_enabled')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="customer-login-session-lifetime">زمان نشست فعال (دقیقه)</label>
                    <input
                        id="customer-login-session-lifetime"
                        type="number"
                        name="customer_login_session_lifetime_minutes"
                        min="5"
                        max="1440"
                        step="1"
                        required
                        value="{{ $customerSessionMinutes }}"
                    >
                    @error('customer_login_session_lifetime_minutes')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="app-settings-field app-settings-field--with-action">
                    <label for="customer-login-max-attempts">تعداد دفعات مجاز ورود اطلاعات نادرست</label>
                    <div class="app-settings-inline-action">
                        <input
                            id="customer-login-max-attempts"
                            type="number"
                            name="customer_login_max_failed_attempts"
                            min="3"
                            max="50"
                            step="1"
                            required
                            value="{{ $customerMaxAttempts }}"
                        >
                        <button
                            type="button"
                            class="app-settings-btn app-settings-btn--secondary"
                            data-login-blocks-open
                            data-login-blocks-guard="customer"
                        >
                            <i class="fa-solid fa-user-lock" aria-hidden="true"></i>
                            مدیریت مسدودی‌ها
                        </button>
                    </div>
                    @error('customer_login_max_failed_attempts')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="app-settings-card">
            <h4>ورود مدیران (پنل ادمین)</h4>
            <p class="app-settings-card-desc">
                با فعال‌سازی تأیید دو مرحله‌ای برای ادمین، کد پیامکی به موبایل ثبت‌شده در پرونده ارسال می‌شود.
                زمان نشست و محدودیت تلاش ناموفق فقط برای صفحه ورود ادمین اعمال می‌شود.
            </p>
            <div class="app-settings-field">
                <label for="admin-login-two-factor">فعال بودن تأیید دو مرحله‌ای هنگام ورود کاربران ادمین</label>
                <select id="admin-login-two-factor" name="admin_login_two_factor_enabled" required>
                    <option value="0" @selected(old('admin_login_two_factor_enabled', AdminLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '0')>غیرفعال</option>
                    <option value="1" @selected(old('admin_login_two_factor_enabled', AdminLoginSecuritySettings::isTwoFactorEnabled() ? '1' : '0') === '1')>فعال</option>
                </select>
                @error('admin_login_two_factor_enabled')
                    <div class="app-settings-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="admin-login-session-lifetime">زمان نشست فعال (دقیقه)</label>
                    <input
                        id="admin-login-session-lifetime"
                        type="number"
                        name="admin_login_session_lifetime_minutes"
                        min="5"
                        max="1440"
                        step="1"
                        required
                        value="{{ $adminSessionMinutes }}"
                    >
                    @error('admin_login_session_lifetime_minutes')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="app-settings-field app-settings-field--with-action">
                    <label for="admin-login-max-attempts">تعداد دفعات مجاز ورود اطلاعات نادرست</label>
                    <div class="app-settings-inline-action">
                        <input
                            id="admin-login-max-attempts"
                            type="number"
                            name="admin_login_max_failed_attempts"
                            min="3"
                            max="50"
                            step="1"
                            required
                            value="{{ $adminMaxAttempts }}"
                        >
                        <button
                            type="button"
                            class="app-settings-btn app-settings-btn--secondary"
                            data-login-blocks-open
                            data-login-blocks-guard="admin"
                        >
                            <i class="fa-solid fa-user-lock" aria-hidden="true"></i>
                            مدیریت مسدودی‌ها
                        </button>
                    </div>
                    @error('admin_login_max_failed_attempts')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="app-settings-actions">
            <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
        </div>
    </form>
</section>
