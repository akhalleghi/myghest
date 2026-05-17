<div class="pwr-modal" id="pwr-modal-login-2fa" role="dialog" aria-modal="true" aria-labelledby="pwr-modal-login-2fa-title" aria-hidden="true" hidden>
    <div class="pwr-modal__backdrop" id="pwr-modal-login-2fa-backdrop" tabindex="-1" aria-hidden="true"></div>
    <div class="pwr-modal__dialog" role="document">
        <div class="pwr-modal__head">
            <div>
                <h2 class="pwr-modal__title" id="pwr-modal-login-2fa-title">تأیید دو مرحله‌ای</h2>
                <p class="pwr-modal__subtitle" id="pwr-modal-login-2fa-sub">کد احراز هویت برای شما ارسال گردید.</p>
            </div>
            <button type="button" class="pwr-modal__close" id="pwr-modal-login-2fa-close" aria-label="بستن">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="pwr-modal__body">
            <p class="login-2fa-mobile-hint" id="login-2fa-mobile-hint" hidden></p>
            <label for="login-2fa-code">
                <i class="fa-solid fa-key lbl-ico" aria-hidden="true"></i>
                کد پیامک‌شده
            </label>
            <input
                id="login-2fa-code"
                type="text"
                inputmode="numeric"
                maxlength="8"
                autocomplete="one-time-code"
                placeholder="۶ رقم"
                dir="ltr"
                class="login-2fa-code-input"
            >
            <p class="forgot-msg" id="login-2fa-msg" role="status"></p>
            <div class="row-actions login-2fa-actions">
                <button type="button" class="btn-secondary" id="login-2fa-verify">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    تأیید و ورود
                </button>
                <button type="button" class="btn-secondary login-2fa-resend" id="login-2fa-resend" disabled>
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    <span id="login-2fa-resend-label">ارسال مجدد</span>
                </button>
            </div>
        </div>
    </div>
</div>
