@extends('layouts.admin.auth')

@section('title', 'ورود به سامانه')

@push('head')
    <style>
        .user-login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.75rem;
            margin-bottom: 1.35rem;
        }
        .user-login-logo {
            width: min(100%, 14rem);
            max-height: 6rem;
            display: grid;
            place-items: center;
        }
        .user-login-logo img {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 6rem;
            object-fit: contain;
        }
        .user-login-logo-fallback {
            width: 4rem;
            height: 4rem;
            border-radius: 0.95rem;
            background: linear-gradient(145deg, var(--accent), var(--accent-strong));
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.55rem;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.28);
        }
        .user-login-message {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.7;
            color: var(--muted);
            font-weight: 650;
            max-width: 28em;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .user-login-title {
            margin: 0.15rem 0 0;
            font-size: 1.12rem;
            font-weight: 780;
            letter-spacing: -0.02em;
            color: var(--brand-heading);
        }
        .user-login-sub { margin: 0; font-size: 0.86rem; color: var(--muted); }
        .link-forgot-wrap { margin-top: 1rem; text-align: center; }
        .btn-link-forgot {
            background: none; border: none; color: var(--accent);
            font: inherit; font-weight: 650; cursor: pointer;
            text-decoration: underline; text-underline-offset: 0.18em;
            padding: 0.35rem 0.5rem; border-radius: 0.5rem;
        }
        .btn-link-forgot:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .pwr-modal {
            position: fixed; inset: 0; z-index: 220;
            display: none; align-items: center; justify-content: center;
            padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1.25rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
            box-sizing: border-box;
        }
        .pwr-modal.is-open { display: flex; }
        .pwr-modal__backdrop {
            position: absolute; inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        html[data-theme="dark"] .pwr-modal__backdrop {
            background: rgba(2, 6, 23, 0.72);
        }
        .pwr-modal__dialog {
            position: relative; z-index: 1;
            width: 100%; max-width: 22.5rem;
            max-height: min(90vh, 36rem);
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            margin: auto;
            background: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border-strong);
            border-radius: 1.1rem;
            box-shadow:
                0 28px 64px rgba(15, 23, 42, 0.22),
                0 12px 28px rgba(37, 99, 235, 0.1);
        }
        html[data-theme="dark"] .pwr-modal__dialog {
            box-shadow:
                0 28px 64px rgba(0, 0, 0, 0.45),
                0 12px 28px rgba(37, 99, 235, 0.12);
        }
        .pwr-modal__head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
            padding: 1.1rem 1.15rem 0.65rem;
            border-bottom: 1px solid var(--border-soft);
        }
        .pwr-modal__title {
            margin: 0; font-size: 1.05rem; font-weight: 750; letter-spacing: -0.02em;
            color: var(--brand-heading); line-height: 1.35;
        }
        .pwr-modal__subtitle {
            margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--muted); line-height: 1.45;
        }
        .pwr-modal__close {
            flex-shrink: 0;
            width: 2.35rem; height: 2.35rem; border-radius: 0.65rem;
            border: 1px solid var(--border-soft);
            background: var(--input-bg); color: var(--muted);
            cursor: pointer; display: grid; place-items: center;
            font-size: 1rem; transition: border-color 0.12s ease, color 0.12s ease;
        }
        .pwr-modal__close:hover { border-color: var(--border-strong); color: var(--text-main); }
        .pwr-modal__close:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .pwr-modal__body { padding: 1rem 1.15rem 1.2rem; }
        .pwr-modal__body label:first-of-type { margin-top: 0.35rem; }
        .step-label { font-size: 0.74rem; color: var(--muted); margin: 0 0 0.5rem; font-weight: 650; }
        .forgot-msg { font-size: 0.82rem; margin: 0.5rem 0 0; min-height: 1.25em; }
        .forgot-msg.ok { color: var(--accent); }
        .forgot-msg.err { color: var(--danger-text); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.85rem; align-items: center; }
        .btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            padding: 0.62rem 1rem; border-radius: 0.82rem; cursor: pointer; font: inherit; font-weight: 650;
            font-size: 0.88rem; border: 1px solid var(--border-strong); background: var(--surface);
            color: var(--text-main); transition: border-color 0.12s ease, background 0.12s ease;
        }
        .btn-secondary:hover { border-color: rgba(37, 99, 235, 0.45); background: var(--input-bg); }
        .hidden-step { display: none !important; }
        input[type="tel"] { font-variant-numeric: tabular-nums; }
        body.pwr-modal-open { overflow: hidden; touch-action: none; }
        .login-2fa-mobile-hint {
            margin: 0 0 0.65rem;
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.5;
        }
        .login-2fa-code-input { text-align: center; letter-spacing: 0.12em; font-weight: 700; }
        .login-2fa-actions { margin-top: 1rem; }
        .login-2fa-resend:disabled { opacity: 0.65; cursor: not-allowed; }
        .login-2fa-resend.is-waiting { border-style: dashed; }
        .login-mode-switch-wrap { margin-top: 0.85rem; text-align: center; }
        .btn-login-mode-switch {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
            width: 100%; padding: 0.7rem 1rem; border-radius: 0.82rem; cursor: pointer;
            font: inherit; font-weight: 650; font-size: 0.9rem;
            border: 1px dashed rgba(37, 99, 235, 0.45);
            background: rgba(37, 99, 235, 0.06); color: var(--accent);
            transition: border-color 0.12s ease, background 0.12s ease;
        }
        .btn-login-mode-switch:hover {
            border-color: rgba(37, 99, 235, 0.7);
            background: rgba(37, 99, 235, 0.1);
        }
        .btn-login-mode-switch:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        #login-mode-otp[hidden], #login-mode-password[hidden] { display: none !important; }
        .otp-login-send-row { display: block; width: 100%; }
        .otp-login-send-row .btn-secondary {
            width: 100%;
            min-height: 2.9rem;
            border-color: transparent;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #fff;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.2);
        }
        .otp-login-send-row .btn-secondary:hover {
            border-color: transparent;
            background: linear-gradient(135deg, var(--accent-strong), var(--accent));
        }
        .otp-login-send-row .btn-secondary:disabled { opacity: 0.68; cursor: wait; box-shadow: none; }
        .otp-login-modal__dialog { max-width: 25rem; overflow: hidden; }
        .otp-login-modal__hero {
            display: grid;
            place-items: center;
            width: 4rem;
            height: 4rem;
            margin: 0 auto 0.9rem;
            border-radius: 1.25rem;
            color: #fff;
            font-size: 1.4rem;
            background: linear-gradient(145deg, var(--accent), var(--accent-strong));
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.25);
        }
        .otp-login-modal__intro { margin: 0 0 1rem; text-align: center; color: var(--muted); font-size: 0.82rem; line-height: 1.7; }
        .otp-login-modal__mobile { text-align: center; margin-bottom: 0.9rem; }
        .login-otp-code-input {
            min-height: 3.55rem;
            text-align: center;
            letter-spacing: 0.48em;
            text-indent: 0.48em;
            font-size: 1.45rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            border-width: 2px;
        }
        .login-otp-code-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .otp-login-security-note {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            margin: 0.8rem 0 0;
            padding: 0.65rem 0.75rem;
            border-radius: 0.75rem;
            background: rgba(37, 99, 235, 0.07);
            color: var(--muted);
            font-size: 0.73rem;
            line-height: 1.65;
        }
        .otp-login-security-note i { color: var(--accent); margin-top: 0.15rem; }
        .otp-login-modal__actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .otp-login-modal__actions #btn-otp-login-verify {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
        }
        .login-otp-resend:disabled { opacity: 0.65; cursor: not-allowed; }
        .login-otp-resend.is-waiting { border-style: dashed; }
    </style>
@endpush

@section('content')
    <div class="user-login-brand">
        <div class="user-login-logo">
            @if(!empty($appLogoUrl ?? null))
                <img src="{{ $appLogoUrl }}" alt="{{ $appDisplayName ?? 'لوگوی سامانه' }}">
            @elseif(!empty($appIconUrl ?? null))
                <img src="{{ $appIconUrl }}" alt="{{ $appDisplayName ?? 'آیکون سامانه' }}">
            @else
                <div class="user-login-logo-fallback" aria-hidden="true">
                    <i class="{{ $appIconFaClass ?? 'fa-solid fa-store' }}"></i>
                </div>
            @endif
        </div>
        @if(!empty($customerLoginMessage ?? null))
            <p class="user-login-message">{{ $customerLoginMessage }}</p>
        @endif
        <h1 class="user-login-title">ورود مشتریان</h1>
    </div>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="login-mode-password">
        <form method="post" action="{{ route('customer.login.attempt') }}" novalidate autocomplete="off" id="customer-login-form">
            @csrf

            <label for="username">
                <i class="fa-solid fa-user lbl-ico" aria-hidden="true"></i>
                نام کاربری
            </label>
            <input
                id="username"
                name="username"
                type="text"
                value="{{ old('username') }}"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                maxlength="64"
                required
                autofocus
            >

            <label for="password">
                <i class="fa-solid fa-lock lbl-ico" aria-hidden="true"></i>
                رمز عبور
            </label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <label for="captcha-login">
                <i class="fa-solid fa-shield-virus lbl-ico" aria-hidden="true"></i>
                کد تأیید تصویر
            </label>
            <div class="captcha-line">
                <img
                    id="user-captcha-login"
                    src="{{ route('customer.auth.captcha', ['purpose' => 'login'], false) }}?initial={{ uniqid('', true) }}"
                    alt="کپچا"
                    title="برای تصویر جدید روی تصویر کلیک کنید"
                    width="160"
                    height="48"
                    decoding="async"
                >
                <input
                    class="captcha-input"
                    id="captcha-login"
                    name="captcha"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9۰-۹٠-٩]{5}"
                    maxlength="5"
                    minlength="5"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="۵ رقم داخل تصویر"
                    aria-describedby="captcha-login-note"
                    required
                >
            </div>
            <p id="captcha-login-note" class="help">
                <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                <span>فقط ۵ رقم؛ اعداد فارسی هم پذیرفته می‌شود. در صورت ناخوانایی روی تصویر کلیک کنید.</span>
            </p>

            <label class="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                مرا به خاطر بسپار
            </label>

            <button type="submit">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                ورود
            </button>
        </form>

        @if (!empty($customerLoginSmsOtpEnabled))
            <div class="login-mode-switch-wrap">
                <button type="button" class="btn-login-mode-switch" id="switch-to-otp-login">
                    <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                    ورود با رمز یکبار مصرف
                </button>
            </div>
        @endif

        <div class="link-forgot-wrap">
            <button type="button" class="btn-link-forgot" id="toggle-forgot" aria-haspopup="dialog" aria-expanded="false" aria-controls="pwr-modal-forgot">
                بازیابی رمز عبور
            </button>
        </div>
    </div>

    @if (!empty($customerLoginSmsOtpEnabled))
        <div id="login-mode-otp" hidden>
            <p class="step-label">ورود با کد پیامک‌شده به شماره موبایل</p>

            <label for="otp-login-mobile">
                <i class="fa-solid fa-phone lbl-ico" aria-hidden="true"></i>
                شماره موبایل
            </label>
            <input id="otp-login-mobile" type="tel" inputmode="numeric" autocomplete="tel" maxlength="11" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹">

            <label for="captcha-otp-login">
                <i class="fa-solid fa-shield-virus lbl-ico" aria-hidden="true"></i>
                کد تأیید تصویر
            </label>
            <div class="captcha-line">
                <img
                    id="user-captcha-otp-login"
                    src="{{ route('customer.auth.captcha', ['purpose' => 'otp-login'], false) }}?initial={{ uniqid('', true) }}"
                    alt="کپچا ورود پیامکی"
                    title="برای تصویر جدید روی تصویر کلیک کنید"
                    width="160"
                    height="48"
                    decoding="async"
                >
                <input
                    class="captcha-input"
                    id="captcha-otp-login"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9۰-۹٠-٩]{5}"
                    maxlength="5"
                    minlength="5"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="۵ رقم داخل تصویر"
                >
            </div>

            <label class="remember">
                <input id="otp-login-remember" type="checkbox" value="1">
                مرا به خاطر بسپار
            </label>

            <div class="row-actions otp-login-send-row">
                <button type="button" class="btn-secondary" id="btn-otp-login-send">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span id="btn-otp-login-send-label">ارسال کد ورود</span>
                </button>
            </div>
            <p class="forgot-msg" id="otp-login-msg-1" role="status"></p>

            <div class="login-mode-switch-wrap">
                <button type="button" class="btn-login-mode-switch" id="switch-to-password-login">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    ورود با نام کاربری و رمز عبور
                </button>
            </div>
        </div>
    @endif
@endsection

@push('portals')
    @if (!empty($customerLoginTwoFactorEnabled))
        @include('user.auth.partials.login-two-factor-modal')
    @endif
    @if (!empty($customerLoginSmsOtpEnabled))
        <div class="pwr-modal" id="pwr-modal-otp-login" role="dialog" aria-modal="true" aria-labelledby="pwr-modal-otp-login-title" aria-describedby="pwr-modal-otp-login-desc" aria-hidden="true" hidden>
            <div class="pwr-modal__backdrop" id="pwr-modal-otp-login-backdrop" tabindex="-1" aria-hidden="true"></div>
            <div class="pwr-modal__dialog otp-login-modal__dialog" role="document">
                <div class="pwr-modal__head">
                    <div>
                        <h2 class="pwr-modal__title" id="pwr-modal-otp-login-title">تأیید ورود پیامکی</h2>
                        <p class="pwr-modal__subtitle" id="pwr-modal-otp-login-desc">کد ۶ رقمی ارسال‌شده را وارد کنید.</p>
                    </div>
                    <button type="button" class="pwr-modal__close" id="pwr-modal-otp-login-close" aria-label="بستن پنجره ورود کد">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="pwr-modal__body">
                    <div class="otp-login-modal__hero" aria-hidden="true">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <p class="otp-login-modal__intro">برای تکمیل ورود، کدی را که به شماره ثبت‌شده ارسال شده است وارد کنید.</p>
                    <p class="login-2fa-mobile-hint otp-login-modal__mobile" id="otp-login-mobile-hint" hidden></p>

                    <label for="otp-login-code">
                        <i class="fa-solid fa-key lbl-ico" aria-hidden="true"></i>
                        کد یکبار مصرف
                    </label>
                    <input
                        id="otp-login-code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9۰-۹٠-٩]{6}"
                        minlength="6"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="ــــــ"
                        dir="ltr"
                        class="login-otp-code-input"
                        aria-describedby="otp-login-security-note otp-login-msg-2"
                    >
                    <div class="otp-login-security-note" id="otp-login-security-note">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>این کد محرمانه است. آن را در اختیار هیچ شخصی قرار ندهید.</span>
                    </div>
                    <p class="forgot-msg" id="otp-login-msg-2" role="status" aria-live="polite"></p>
                    <div class="row-actions otp-login-modal__actions">
                        <button type="button" class="btn-secondary" id="btn-otp-login-verify">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            تأیید و ورود امن
                        </button>
                        <button type="button" class="btn-secondary login-otp-resend" id="btn-otp-login-resend" disabled>
                            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                            <span id="otp-login-resend-label">ارسال مجدد</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="pwr-modal" id="pwr-modal-forgot" role="dialog" aria-modal="true" aria-labelledby="pwr-modal-title" aria-hidden="true" hidden>
        <div class="pwr-modal__backdrop" id="pwr-modal-backdrop" tabindex="-1" aria-hidden="true"></div>
        <div class="pwr-modal__dialog" role="document">
            <div class="pwr-modal__head">
                <div>
                    <h2 class="pwr-modal__title" id="pwr-modal-title">بازیابی رمز عبور</h2>
                    <p class="pwr-modal__subtitle">با شماره موبایل ثبت‌شده در پرونده؛ پس از دریافت پیامک، کد را وارد کنید و رمز جدید تعیین کنید.</p>
                </div>
                <button type="button" class="pwr-modal__close" id="pwr-modal-close" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="pwr-modal__body">
                <p class="step-label">مرحلهٔ ۱ — شماره و کپچا</p>

                <label for="forgot-mobile">
                    <i class="fa-solid fa-phone lbl-ico" aria-hidden="true"></i>
                    شماره موبایل
                </label>
                <input id="forgot-mobile" type="tel" inputmode="numeric" autocomplete="tel" maxlength="11" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹">

                <label for="captcha-forgot">
                    <i class="fa-solid fa-shield-virus lbl-ico" aria-hidden="true"></i>
                    کد تأیید تصویر
                </label>
                <div class="captcha-line">
                    <img
                        id="user-captcha-forgot"
                        src="{{ route('customer.auth.captcha', ['purpose' => 'forgot'], false) }}?initial={{ uniqid('', true) }}"
                        alt="کپچا بازیابی"
                        title="برای تصویر جدید روی تصویر کلیک کنید"
                        width="160"
                        height="48"
                        decoding="async"
                    >
                    <input class="captcha-input" id="captcha-forgot" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]{5}" maxlength="5" minlength="5" placeholder="۵ رقم تصویر" autocomplete="off" autocapitalize="none" spellcheck="false">
                </div>

                <div class="row-actions">
                    <button type="button" class="btn-secondary" id="btn-send-otp">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال کد
                    </button>
                </div>
                <p class="forgot-msg" id="forgot-msg-1" role="status"></p>

                <div id="step-otp" class="hidden-step">
                    <p class="step-label" style="margin-top:1rem">مرحلهٔ ۲ — کد پیامک</p>
                    <label for="forgot-otp">
                        <i class="fa-solid fa-key lbl-ico" aria-hidden="true"></i>
                        کد پیامک‌شده
                    </label>
                    <input id="forgot-otp" type="text" inputmode="numeric" maxlength="8" autocomplete="one-time-code" placeholder="۶ رقم">
                    <div class="row-actions">
                        <button type="button" class="btn-secondary" id="btn-verify-otp">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            تأیید کد
                        </button>
                    </div>
                    <p class="forgot-msg" id="forgot-msg-2" role="status"></p>
                </div>

                <div id="step-password" class="hidden-step">
                    <p class="step-label" style="margin-top:1rem">مرحلهٔ ۳ — رمز جدید</p>
                    <label for="new-password">
                        <i class="fa-solid fa-lock lbl-ico" aria-hidden="true"></i>
                        رمز عبور جدید
                    </label>
                    <input id="new-password" type="password" autocomplete="new-password" minlength="8" maxlength="128">

                    <label for="new-password-confirm">
                        <i class="fa-solid fa-lock lbl-ico" aria-hidden="true"></i>
                        تکرار رمز عبور
                    </label>
                    <input id="new-password-confirm" type="password" autocomplete="new-password" minlength="8" maxlength="128">

                    <p class="help" style="margin-top:0.5rem">
                        <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                        <span>حداقل ۸ کاراکتر؛ ترکیب حروف و اعداد توصیه می‌شود.</span>
                    </p>

                    <div class="row-actions">
                        <button type="button" class="btn-secondary" id="btn-save-password">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            ذخیرهٔ رمز جدید
                        </button>
                    </div>
                    <p class="forgot-msg" id="forgot-msg-3" role="status"></p>
                </div>
            </div>
        </div>
    </div>
@endpush

@section('scripts')
    <script>
        (function () {
            var loginImg = document.getElementById('user-captcha-login');
            var loginInput = document.getElementById('captcha-login');
            var forgotImg = document.getElementById('user-captcha-forgot');
            var forgotInput = document.getElementById('captcha-forgot');
            var otpLoginImg = document.getElementById('user-captcha-otp-login');
            var otpLoginCaptchaInput = document.getElementById('captcha-otp-login');
            var refreshUrl = @json(route('customer.auth.captcha.refresh', [], false));
            var captchaBase = @json(url('/auth/captcha'));

            function csrfToken() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta && meta.content) return meta.content;
                var inp = document.querySelector('input[name="_token"]');
                return inp ? inp.value : '';
            }

            function refreshCaptcha(img, input, purpose) {
                if (!img) return;
                var token = csrfToken();
                var fd = new FormData();
                fd.append('_token', token);
                fd.append('purpose', purpose);

                fetch(refreshUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: fd,
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('captcha-refresh-http');
                        return res.json();
                    })
                    .then(function (payload) {
                        if (!payload || typeof payload.data_url !== 'string' || payload.data_url.length < 40) {
                            throw new Error('captcha-refresh-body');
                        }
                        img.onload = null;
                        img.src = payload.data_url;
                        if (input) {
                            input.value = '';
                            input.focus();
                        }
                    })
                    .catch(function () {
                        var sep = captchaBase.indexOf('?') === -1 ? '?' : '&';
                        img.src = captchaBase + '/' + purpose + sep + '_recover=' + Date.now();
                        if (input) input.value = '';
                    });
            }

            if (loginImg) {
                loginImg.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    refreshCaptcha(loginImg, loginInput, 'login');
                });
            }
            if (forgotImg) {
                forgotImg.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    refreshCaptcha(forgotImg, forgotInput, 'forgot');
                });
            }
            if (otpLoginImg) {
                otpLoginImg.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    refreshCaptcha(otpLoginImg, otpLoginCaptchaInput, 'otp-login');
                });
            }

            var modal = document.getElementById('pwr-modal-forgot');
            var backdrop = document.getElementById('pwr-modal-backdrop');
            var btnClose = document.getElementById('pwr-modal-close');
            var toggleForgot = document.getElementById('toggle-forgot');
            var otpSession = null;
            var resetToken = null;

            function setMsg(el, text, kind) {
                if (!el) return;
                el.textContent = text || '';
                el.classList.remove('ok', 'err');
                if (kind) el.classList.add(kind);
            }

            function resetForgotFlow() {
                otpSession = null;
                resetToken = null;
                var m = document.getElementById('forgot-mobile');
                var c = document.getElementById('captcha-forgot');
                var o = document.getElementById('forgot-otp');
                var p1 = document.getElementById('new-password');
                var p2 = document.getElementById('new-password-confirm');
                if (m) m.value = '';
                if (c) c.value = '';
                if (o) o.value = '';
                if (p1) p1.value = '';
                if (p2) p2.value = '';
                var s1 = document.getElementById('step-otp');
                var s2 = document.getElementById('step-password');
                if (s1) s1.classList.add('hidden-step');
                if (s2) s2.classList.add('hidden-step');
                setMsg(document.getElementById('forgot-msg-1'), '', '');
                setMsg(document.getElementById('forgot-msg-2'), '', '');
                setMsg(document.getElementById('forgot-msg-3'), '', '');
            }

            function openForgotModal() {
                if (!modal) return;
                resetForgotFlow();
                modal.removeAttribute('hidden');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pwr-modal-open');
                if (toggleForgot) toggleForgot.setAttribute('aria-expanded', 'true');
                refreshCaptcha(forgotImg, forgotInput, 'forgot');
                var fm = document.getElementById('forgot-mobile');
                if (fm) setTimeout(function () { fm.focus(); }, 50);
            }

            function closeForgotModal() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modal.setAttribute('hidden', 'hidden');
                document.body.classList.remove('pwr-modal-open');
                if (toggleForgot) {
                    toggleForgot.setAttribute('aria-expanded', 'false');
                    toggleForgot.focus();
                }
            }

            if (backdrop) {
                backdrop.addEventListener('click', function () {
                    closeForgotModal();
                });
            }
            var dialogEl = modal ? modal.querySelector('.pwr-modal__dialog') : null;
            if (dialogEl) {
                dialogEl.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                });
            }
            if (btnClose) {
                btnClose.addEventListener('click', function () {
                    closeForgotModal();
                });
            }
            document.addEventListener('keydown', function (ev) {
                if (ev.key !== 'Escape') return;
                if (!modal || !modal.classList.contains('is-open')) return;
                ev.preventDefault();
                closeForgotModal();
            });

            if (toggleForgot && modal) {
                toggleForgot.addEventListener('click', function () {
                    openForgotModal();
                });
            }

            function jsonPost(url, body) {
                var token = csrfToken();
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(body),
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                });
            }

            function firstValidationMessage(data) {
                if (!data || typeof data !== 'object') return '';
                if (typeof data.message === 'string' && data.message) return data.message;
                var errs = data.errors;
                if (!errs || typeof errs !== 'object') return '';
                var k = Object.keys(errs)[0];
                if (!k || !errs[k] || !errs[k][0]) return '';
                return errs[k][0];
            }

            var btnSend = document.getElementById('btn-send-otp');
            if (btnSend) {
                btnSend.addEventListener('click', function () {
                    var mobile = (document.getElementById('forgot-mobile') || {}).value || '';
                    var captcha = (document.getElementById('captcha-forgot') || {}).value || '';
                    setMsg(document.getElementById('forgot-msg-1'), 'در حال ارسال…', '');
                    btnSend.disabled = true;
                    jsonPost(@json(route('customer.auth.forgot.request-otp', [], false)), {
                        mobile: mobile,
                        captcha: captcha,
                    })
                        .then(function (r) {
                            var m = firstValidationMessage(r.data) || 'خطا';
                            if (r.ok) {
                                if (r.data && r.data.otp_session) {
                                    otpSession = r.data.otp_session;
                                    setMsg(document.getElementById('forgot-msg-1'), m, 'ok');
                                    document.getElementById('step-otp').classList.remove('hidden-step');
                                    document.getElementById('forgot-otp').focus();
                                } else {
                                    otpSession = null;
                                    setMsg(document.getElementById('forgot-msg-1'), m, 'ok');
                                    refreshCaptcha(forgotImg, forgotInput, 'forgot');
                                }
                            } else {
                                setMsg(document.getElementById('forgot-msg-1'), m, 'err');
                                refreshCaptcha(forgotImg, forgotInput, 'forgot');
                            }
                        })
                        .catch(function () {
                            setMsg(document.getElementById('forgot-msg-1'), 'ارتباط با سرور برقرار نشد.', 'err');
                        })
                        .finally(function () {
                            btnSend.disabled = false;
                        });
                });
            }

            var btnVerify = document.getElementById('btn-verify-otp');
            if (btnVerify) {
                btnVerify.addEventListener('click', function () {
                    var mobile = (document.getElementById('forgot-mobile') || {}).value || '';
                    var code = (document.getElementById('forgot-otp') || {}).value || '';
                    if (!otpSession) {
                        setMsg(document.getElementById('forgot-msg-2'), 'ابتدا کد را دریافت کنید.', 'err');
                        return;
                    }
                    setMsg(document.getElementById('forgot-msg-2'), 'در حال بررسی…', '');
                    btnVerify.disabled = true;
                    jsonPost(@json(route('customer.auth.forgot.verify-otp', [], false)), {
                        otp_session: otpSession,
                        mobile: mobile,
                        code: code,
                    })
                        .then(function (r) {
                            var m = firstValidationMessage(r.data) || 'خطا';
                            if (r.ok && r.data && r.data.reset_token) {
                                resetToken = r.data.reset_token;
                                setMsg(document.getElementById('forgot-msg-2'), m, 'ok');
                                document.getElementById('step-password').classList.remove('hidden-step');
                                document.getElementById('new-password').focus();
                            } else {
                                setMsg(document.getElementById('forgot-msg-2'), m, 'err');
                            }
                        })
                        .catch(function () {
                            setMsg(document.getElementById('forgot-msg-2'), 'ارتباط با سرور برقرار نشد.', 'err');
                        })
                        .finally(function () {
                            btnVerify.disabled = false;
                        });
                });
            }

            var btnSave = document.getElementById('btn-save-password');
            if (btnSave) {
                btnSave.addEventListener('click', function () {
                    if (!resetToken) {
                        setMsg(document.getElementById('forgot-msg-3'), 'ابتدا کد پیامک را تأیید کنید.', 'err');
                        return;
                    }
                    var p1 = (document.getElementById('new-password') || {}).value || '';
                    var p2 = (document.getElementById('new-password-confirm') || {}).value || '';
                    setMsg(document.getElementById('forgot-msg-3'), 'در حال ذخیره…', '');
                    btnSave.disabled = true;
                    jsonPost(@json(route('customer.auth.forgot.reset-password', [], false)), {
                        reset_token: resetToken,
                        password: p1,
                        password_confirmation: p2,
                    })
                        .then(function (r) {
                            var m = firstValidationMessage(r.data) || 'خطا';
                            if (r.ok) {
                                setMsg(document.getElementById('forgot-msg-3'), '', '');
                                function afterPasswordResetSuccess() {
                                    resetForgotFlow();
                                    closeForgotModal();
                                    refreshCaptcha(loginImg, loginInput, 'login');
                                }
                                if (typeof Swal !== 'undefined') {
                                    return Swal.fire({
                                        icon: 'success',
                                        title: 'موفقیت‌آمیز بود',
                                        text: m,
                                        confirmButtonText: 'باشه',
                                        customClass: { popup: 'auth-swal-popup' },
                                        didOpen: function () {
                                            var p = document.querySelector('.swal2-popup');
                                            if (p) p.setAttribute('dir', 'rtl');
                                        },
                                    }).then(afterPasswordResetSuccess);
                                }
                                afterPasswordResetSuccess();
                                return Promise.resolve();
                            }
                            setMsg(document.getElementById('forgot-msg-3'), m, 'err');
                        })
                        .catch(function () {
                            setMsg(document.getElementById('forgot-msg-3'), 'ارتباط با سرور برقرار نشد.', 'err');
                        })
                        .finally(function () {
                            btnSave.disabled = false;
                        });
                });
            }

            @if (!empty($customerLoginTwoFactorEnabled))
            (function initLoginTwoFactor() {
                var loginForm = document.getElementById('customer-login-form');
                var modal2fa = document.getElementById('pwr-modal-login-2fa');
                if (!loginForm || !modal2fa) return;

                var backdrop2fa = document.getElementById('pwr-modal-login-2fa-backdrop');
                var btnClose2fa = document.getElementById('pwr-modal-login-2fa-close');
                var sub2fa = document.getElementById('pwr-modal-login-2fa-sub');
                var mobileHint = document.getElementById('login-2fa-mobile-hint');
                var codeInput = document.getElementById('login-2fa-code');
                var msg2fa = document.getElementById('login-2fa-msg');
                var btnVerify2fa = document.getElementById('login-2fa-verify');
                var btnResend2fa = document.getElementById('login-2fa-resend');
                var resendLabel = document.getElementById('login-2fa-resend-label');
                var loginSession = null;
                var resendTimer = null;
                var resendSecondsLeft = 0;

                function set2faMsg(text, kind) {
                    if (!msg2fa) return;
                    msg2fa.textContent = text || '';
                    msg2fa.classList.remove('ok', 'err');
                    if (kind) msg2fa.classList.add(kind);
                }

                function clearResendTimer() {
                    if (resendTimer) {
                        clearInterval(resendTimer);
                        resendTimer = null;
                    }
                }

                function updateResendButton() {
                    if (!btnResend2fa || !resendLabel) return;
                    if (resendSecondsLeft > 0) {
                        btnResend2fa.disabled = true;
                        btnResend2fa.classList.add('is-waiting');
                        resendLabel.textContent = 'ارسال مجدد (' + resendSecondsLeft + ')';
                    } else {
                        btnResend2fa.disabled = !loginSession;
                        btnResend2fa.classList.remove('is-waiting');
                        resendLabel.textContent = 'ارسال مجدد';
                    }
                }

                function startResendCountdown(seconds) {
                    clearResendTimer();
                    resendSecondsLeft = Math.max(0, parseInt(String(seconds || 60), 10) || 60);
                    updateResendButton();
                    resendTimer = setInterval(function () {
                        resendSecondsLeft -= 1;
                        if (resendSecondsLeft <= 0) {
                            resendSecondsLeft = 0;
                            clearResendTimer();
                        }
                        updateResendButton();
                    }, 1000);
                }

                function openLogin2faModal(payload) {
                    loginSession = payload && payload.login_session ? payload.login_session : null;
                    var message = (payload && payload.message) || 'کد احراز هویت برای شما ارسال گردید.';
                    if (sub2fa) sub2fa.textContent = message;
                    if (mobileHint) {
                        var masked = payload && payload.masked_mobile ? String(payload.masked_mobile) : '';
                        if (masked) {
                            mobileHint.textContent = 'ارسال به شماره: ' + masked;
                            mobileHint.hidden = false;
                        } else {
                            mobileHint.textContent = '';
                            mobileHint.hidden = true;
                        }
                    }
                    if (codeInput) {
                        codeInput.value = '';
                    }
                    set2faMsg('', '');
                    startResendCountdown(payload && payload.resend_available_in != null ? payload.resend_available_in : 60);
                    modal2fa.removeAttribute('hidden');
                    modal2fa.classList.add('is-open');
                    modal2fa.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('pwr-modal-open');
                    if (codeInput) setTimeout(function () { codeInput.focus(); }, 60);
                }

                function closeLogin2faModal() {
                    clearResendTimer();
                    loginSession = null;
                    resendSecondsLeft = 0;
                    updateResendButton();
                    modal2fa.classList.remove('is-open');
                    modal2fa.setAttribute('aria-hidden', 'true');
                    modal2fa.setAttribute('hidden', 'hidden');
                    document.body.classList.remove('pwr-modal-open');
                }

                if (backdrop2fa) {
                    backdrop2fa.addEventListener('click', closeLogin2faModal);
                }
                if (btnClose2fa) {
                    btnClose2fa.addEventListener('click', closeLogin2faModal);
                }
                document.addEventListener('keydown', function (ev) {
                    if (ev.key !== 'Escape') return;
                    if (!modal2fa.classList.contains('is-open')) return;
                    ev.preventDefault();
                    closeLogin2faModal();
                });

                loginForm.addEventListener('submit', function (ev) {
                    ev.preventDefault();
                    var submitBtn = loginForm.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;
                    set2faMsg('', '');
                    var fd = new FormData(loginForm);
                    var token = csrfToken();
                    fetch(loginForm.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Login-Mode': 'ajax',
                            'X-CSRF-TOKEN': token,
                        },
                        body: fd,
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                return { ok: res.ok, status: res.status, data: data };
                            }).catch(function () {
                                return { ok: false, status: res.status, data: {} };
                            });
                        })
                        .then(function (r) {
                            if (r.ok && r.data && r.data.requires_otp) {
                                openLogin2faModal(r.data);
                                refreshCaptcha(loginImg, loginInput, 'login');
                                return;
                            }
                            if (r.ok && r.data && r.data.redirect) {
                                window.location.href = r.data.redirect;
                                return;
                            }
                            var m = firstValidationMessage(r.data) || 'ورود ناموفق بود.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطا',
                                    text: m,
                                    confirmButtonText: 'باشه',
                                    customClass: { popup: 'auth-swal-popup' },
                                });
                            } else {
                                alert(m);
                            }
                            refreshCaptcha(loginImg, loginInput, 'login');
                        })
                        .catch(function () {
                            set2faMsg('ارتباط با سرور برقرار نشد.', 'err');
                        })
                        .finally(function () {
                            if (submitBtn) submitBtn.disabled = false;
                        });
                });

                if (btnVerify2fa) {
                    btnVerify2fa.addEventListener('click', function () {
                        if (!loginSession) {
                            set2faMsg('نشست ورود منقضی شده؛ دوباره تلاش کنید.', 'err');
                            return;
                        }
                        var code = codeInput ? codeInput.value : '';
                        set2faMsg('در حال بررسی…', '');
                        btnVerify2fa.disabled = true;
                        jsonPost(@json(route('customer.auth.login.verify-otp', [], false)), {
                            login_session: loginSession,
                            code: code,
                        })
                            .then(function (r) {
                                var m = firstValidationMessage(r.data) || 'خطا';
                                if (r.ok && r.data && r.data.redirect) {
                                    window.location.href = r.data.redirect;
                                    return;
                                }
                                set2faMsg(m, 'err');
                            })
                            .catch(function () {
                                set2faMsg('ارتباط با سرور برقرار نشد.', 'err');
                            })
                            .finally(function () {
                                btnVerify2fa.disabled = false;
                            });
                    });
                }

                if (codeInput) {
                    codeInput.addEventListener('keydown', function (ev) {
                        if (ev.key === 'Enter') {
                            ev.preventDefault();
                            if (btnVerify2fa) btnVerify2fa.click();
                        }
                    });
                }

                if (btnResend2fa) {
                    btnResend2fa.addEventListener('click', function () {
                        if (!loginSession || resendSecondsLeft > 0) return;
                        set2faMsg('در حال ارسال مجدد…', '');
                        btnResend2fa.disabled = true;
                        jsonPost(@json(route('customer.auth.login.resend-otp', [], false)), {
                            login_session: loginSession,
                        })
                            .then(function (r) {
                                var m = firstValidationMessage(r.data) || 'خطا';
                                if (r.ok) {
                                    set2faMsg(m, 'ok');
                                    startResendCountdown(
                                        r.data && r.data.resend_available_in != null ? r.data.resend_available_in : 60
                                    );
                                } else {
                                    set2faMsg(m, 'err');
                                    updateResendButton();
                                }
                            })
                            .catch(function () {
                                set2faMsg('ارتباط با سرور برقرار نشد.', 'err');
                                updateResendButton();
                            });
                    });
                }

                @if (session('login_2fa'))
                openLogin2faModal(@json(session('login_2fa')));
                @endif
            })();
            @endif

            @if (!empty($customerLoginSmsOtpEnabled))
            (function initSmsOtpLogin() {
                var modePassword = document.getElementById('login-mode-password');
                var modeOtp = document.getElementById('login-mode-otp');
                var switchToOtp = document.getElementById('switch-to-otp-login');
                var switchToPassword = document.getElementById('switch-to-password-login');
                if (!modePassword || !modeOtp || !switchToOtp || !switchToPassword) return;

                var btnSend = document.getElementById('btn-otp-login-send');
                var btnSendLabel = document.getElementById('btn-otp-login-send-label');
                var btnVerify = document.getElementById('btn-otp-login-verify');
                var btnResend = document.getElementById('btn-otp-login-resend');
                var resendLabel = document.getElementById('otp-login-resend-label');
                var modalOtpCode = document.getElementById('pwr-modal-otp-login');
                var modalOtpCodeBackdrop = document.getElementById('pwr-modal-otp-login-backdrop');
                var modalOtpCodeClose = document.getElementById('pwr-modal-otp-login-close');
                var mobileHint = document.getElementById('otp-login-mobile-hint');
                var codeInput = document.getElementById('otp-login-code');
                var loginSession = null;
                var resendTimer = null;
                var resendSecondsLeft = 0;
                var modalLastFocused = null;

                function clearResendTimer() {
                    if (resendTimer) {
                        clearInterval(resendTimer);
                        resendTimer = null;
                    }
                }

                function updateResendButton() {
                    if (!btnResend || !resendLabel) return;
                    if (resendSecondsLeft > 0) {
                        btnResend.disabled = true;
                        btnResend.classList.add('is-waiting');
                        resendLabel.textContent = 'ارسال مجدد (' + resendSecondsLeft + ')';
                    } else {
                        btnResend.disabled = !loginSession;
                        btnResend.classList.remove('is-waiting');
                        resendLabel.textContent = 'ارسال مجدد';
                    }
                }

                function startResendCountdown(seconds) {
                    clearResendTimer();
                    resendSecondsLeft = Math.max(0, parseInt(String(seconds || 60), 10) || 60);
                    updateResendButton();
                    resendTimer = setInterval(function () {
                        resendSecondsLeft -= 1;
                        if (resendSecondsLeft <= 0) {
                            resendSecondsLeft = 0;
                            clearResendTimer();
                        }
                        updateResendButton();
                    }, 1000);
                }

                function normalizeOtpCode(value) {
                    return String(value || '')
                        .replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); })
                        .replace(/[٠-٩]/g, function (digit) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)); })
                        .replace(/\D/g, '')
                        .slice(0, 6);
                }

                function openOtpCodeModal() {
                    if (!modalOtpCode || !loginSession) return;
                    modalLastFocused = document.activeElement;
                    modalOtpCode.removeAttribute('hidden');
                    modalOtpCode.classList.add('is-open');
                    modalOtpCode.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('pwr-modal-open');
                    setMsg(document.getElementById('otp-login-msg-2'), '', '');
                    if (codeInput) {
                        codeInput.value = '';
                        setTimeout(function () { codeInput.focus(); }, 60);
                    }
                }

                function closeOtpCodeModal() {
                    if (!modalOtpCode || !modalOtpCode.classList.contains('is-open')) return;
                    modalOtpCode.classList.remove('is-open');
                    modalOtpCode.setAttribute('aria-hidden', 'true');
                    modalOtpCode.setAttribute('hidden', 'hidden');
                    document.body.classList.remove('pwr-modal-open');
                    if (codeInput) codeInput.value = '';
                    setMsg(document.getElementById('otp-login-msg-2'), '', '');
                    if (modalLastFocused && typeof modalLastFocused.focus === 'function') {
                        modalLastFocused.focus();
                    }
                    modalLastFocused = null;
                }

                function resetOtpLoginFlow() {
                    closeOtpCodeModal();
                    clearResendTimer();
                    loginSession = null;
                    resendSecondsLeft = 0;
                    updateResendButton();
                    if (btnSendLabel) btnSendLabel.textContent = 'ارسال کد ورود';
                    var mobile = document.getElementById('otp-login-mobile');
                    var captcha = document.getElementById('captcha-otp-login');
                    var remember = document.getElementById('otp-login-remember');
                    if (mobile) mobile.value = '';
                    if (captcha) captcha.value = '';
                    if (codeInput) codeInput.value = '';
                    if (remember) remember.checked = false;
                    if (mobileHint) {
                        mobileHint.textContent = '';
                        mobileHint.hidden = true;
                    }
                    setMsg(document.getElementById('otp-login-msg-1'), '', '');
                    setMsg(document.getElementById('otp-login-msg-2'), '', '');
                }

                function showPasswordMode() {
                    resetOtpLoginFlow();
                    modeOtp.setAttribute('hidden', 'hidden');
                    modePassword.removeAttribute('hidden');
                    refreshCaptcha(loginImg, loginInput, 'login');
                    var u = document.getElementById('username');
                    if (u) setTimeout(function () { u.focus(); }, 40);
                }

                function showOtpMode() {
                    modePassword.setAttribute('hidden', 'hidden');
                    modeOtp.removeAttribute('hidden');
                    resetOtpLoginFlow();
                    refreshCaptcha(otpLoginImg, otpLoginCaptchaInput, 'otp-login');
                    var m = document.getElementById('otp-login-mobile');
                    if (m) setTimeout(function () { m.focus(); }, 40);
                }

                switchToOtp.addEventListener('click', showOtpMode);
                switchToPassword.addEventListener('click', showPasswordMode);

                if (modalOtpCodeBackdrop) {
                    modalOtpCodeBackdrop.addEventListener('click', closeOtpCodeModal);
                }
                if (modalOtpCodeClose) {
                    modalOtpCodeClose.addEventListener('click', closeOtpCodeModal);
                }
                document.addEventListener('keydown', function (ev) {
                    if (!modalOtpCode || !modalOtpCode.classList.contains('is-open')) return;
                    if (ev.key === 'Escape') {
                        ev.preventDefault();
                        closeOtpCodeModal();
                        return;
                    }
                    if (ev.key !== 'Tab') return;
                    var focusable = modalOtpCode.querySelectorAll(
                        'button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    if (!focusable.length) return;
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (ev.shiftKey && document.activeElement === first) {
                        ev.preventDefault();
                        last.focus();
                    } else if (!ev.shiftKey && document.activeElement === last) {
                        ev.preventDefault();
                        first.focus();
                    }
                });

                if (btnSend) {
                    btnSend.addEventListener('click', function () {
                        if (loginSession) {
                            openOtpCodeModal();
                            return;
                        }
                        var mobile = (document.getElementById('otp-login-mobile') || {}).value || '';
                        var captcha = (document.getElementById('captcha-otp-login') || {}).value || '';
                        var remember = !!(document.getElementById('otp-login-remember') || {}).checked;
                        setMsg(document.getElementById('otp-login-msg-1'), 'در حال ارسال…', '');
                        btnSend.disabled = true;
                        jsonPost(@json(route('customer.auth.login-otp.request', [], false)), {
                            mobile: mobile,
                            captcha: captcha,
                            remember: remember,
                        })
                            .then(function (r) {
                                var m = firstValidationMessage(r.data) || 'خطا';
                                if (r.ok && r.data && r.data.login_session) {
                                    loginSession = r.data.login_session;
                                    setMsg(document.getElementById('otp-login-msg-1'), m, 'ok');
                                    if (btnSendLabel) btnSendLabel.textContent = 'ورود کد تأیید';
                                    if (mobileHint) {
                                        var masked = r.data.masked_mobile ? String(r.data.masked_mobile) : '';
                                        if (masked) {
                                            mobileHint.textContent = 'ارسال به شماره: ' + masked;
                                            mobileHint.hidden = false;
                                        } else {
                                            mobileHint.textContent = '';
                                            mobileHint.hidden = true;
                                        }
                                    }
                                    startResendCountdown(
                                        r.data.resend_available_in != null ? r.data.resend_available_in : 60
                                    );
                                    openOtpCodeModal();
                                } else {
                                    loginSession = null;
                                    setMsg(document.getElementById('otp-login-msg-1'), m, 'err');
                                    refreshCaptcha(otpLoginImg, otpLoginCaptchaInput, 'otp-login');
                                }
                            })
                            .catch(function () {
                                setMsg(document.getElementById('otp-login-msg-1'), 'ارتباط با سرور برقرار نشد.', 'err');
                            })
                            .finally(function () {
                                btnSend.disabled = false;
                            });
                    });
                }

                if (btnVerify) {
                    btnVerify.addEventListener('click', function () {
                        if (!loginSession) {
                            setMsg(document.getElementById('otp-login-msg-2'), 'ابتدا کد را دریافت کنید.', 'err');
                            return;
                        }
                        var code = normalizeOtpCode((codeInput || {}).value || '');
                        if (code.length !== 6) {
                            setMsg(document.getElementById('otp-login-msg-2'), 'کد ورود باید دقیقاً ۶ رقم باشد.', 'err');
                            if (codeInput) codeInput.focus();
                            return;
                        }
                        setMsg(document.getElementById('otp-login-msg-2'), 'در حال بررسی…', '');
                        btnVerify.disabled = true;
                        jsonPost(@json(route('customer.auth.login-otp.verify', [], false)), {
                            login_session: loginSession,
                            code: code,
                        })
                            .then(function (r) {
                                var m = firstValidationMessage(r.data) || 'خطا';
                                if (r.ok && r.data && r.data.redirect) {
                                    setMsg(document.getElementById('otp-login-msg-2'), m, 'ok');
                                    window.location.href = r.data.redirect;
                                    return;
                                }
                                setMsg(document.getElementById('otp-login-msg-2'), m, 'err');
                            })
                            .catch(function () {
                                setMsg(document.getElementById('otp-login-msg-2'), 'ارتباط با سرور برقرار نشد.', 'err');
                            })
                            .finally(function () {
                                btnVerify.disabled = false;
                            });
                    });
                }

                if (codeInput) {
                    codeInput.addEventListener('input', function () {
                        var normalized = normalizeOtpCode(codeInput.value);
                        if (codeInput.value !== normalized) codeInput.value = normalized;
                        setMsg(document.getElementById('otp-login-msg-2'), '', '');
                    });
                    codeInput.addEventListener('paste', function (ev) {
                        ev.preventDefault();
                        var pasted = ev.clipboardData ? ev.clipboardData.getData('text') : '';
                        codeInput.value = normalizeOtpCode(pasted);
                        codeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    codeInput.addEventListener('keydown', function (ev) {
                        if (ev.key === 'Enter') {
                            ev.preventDefault();
                            if (btnVerify) btnVerify.click();
                        }
                    });
                }

                if (btnResend) {
                    btnResend.addEventListener('click', function () {
                        if (!loginSession || resendSecondsLeft > 0) return;
                        setMsg(document.getElementById('otp-login-msg-2'), 'در حال ارسال مجدد…', '');
                        btnResend.disabled = true;
                        jsonPost(@json(route('customer.auth.login-otp.resend', [], false)), {
                            login_session: loginSession,
                        })
                            .then(function (r) {
                                var m = firstValidationMessage(r.data) || 'خطا';
                                if (r.ok) {
                                    setMsg(document.getElementById('otp-login-msg-2'), m, 'ok');
                                    startResendCountdown(
                                        r.data && r.data.resend_available_in != null ? r.data.resend_available_in : 60
                                    );
                                } else {
                                    setMsg(document.getElementById('otp-login-msg-2'), m, 'err');
                                    updateResendButton();
                                }
                            })
                            .catch(function () {
                                setMsg(document.getElementById('otp-login-msg-2'), 'ارتباط با سرور برقرار نشد.', 'err');
                                updateResendButton();
                            });
                    });
                }
            })();
            @endif
        })();
    </script>
@endsection
