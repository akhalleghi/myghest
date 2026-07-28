@extends('layouts.admin.auth')

@section('title', 'ورود مدیر' . (! empty($appDisplayName ?? null) ? ' — ' . $appDisplayName : ''))

@push('head')
    <style>
        .admin-login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.75rem;
            margin-bottom: 1.35rem;
        }
        .admin-login-logo {
            width: min(100%, 14rem);
            max-height: 6rem;
            display: grid;
            place-items: center;
        }
        .admin-login-logo img {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 6rem;
            object-fit: contain;
        }
        .admin-login-logo-fallback {
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
        .admin-login-title {
            margin: 0.15rem 0 0;
            font-size: 1.12rem;
            font-weight: 780;
            letter-spacing: -0.02em;
            color: var(--brand-heading);
            line-height: 1.45;
        }
        .admin-login-lead {
            margin: 0.35rem 0 0;
            font-size: 0.86rem;
            color: var(--muted);
            max-width: 30em;
            line-height: 1.6;
        }
        @if (!empty($adminLoginTwoFactorEnabled))
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
        html[data-theme="dark"] .pwr-modal__backdrop { background: rgba(2, 6, 23, 0.72); }
        .pwr-modal__dialog {
            position: relative; z-index: 1;
            width: 100%; max-width: 22.5rem;
            max-height: min(90vh, 36rem);
            overflow: auto;
            margin: auto;
            background: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border-strong);
            border-radius: 1.1rem;
            box-shadow: 0 28px 64px rgba(15, 23, 42, 0.22), 0 12px 28px rgba(37, 99, 235, 0.1);
        }
        .pwr-modal__head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
            padding: 1.1rem 1.15rem 0.65rem;
            border-bottom: 1px solid var(--border-soft);
        }
        .pwr-modal__title { margin: 0; font-size: 1.05rem; font-weight: 750; color: var(--brand-heading); }
        .pwr-modal__subtitle { margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--muted); line-height: 1.45; }
        .pwr-modal__close {
            flex-shrink: 0; width: 2.35rem; height: 2.35rem; border-radius: 0.65rem;
            border: 1px solid var(--border-soft); background: var(--input-bg); color: var(--muted);
            cursor: pointer; display: grid; place-items: center;
        }
        .pwr-modal__body { padding: 1rem 1.15rem 1.2rem; }
        .forgot-msg { font-size: 0.82rem; margin: 0.5rem 0 0; min-height: 1.25em; }
        .forgot-msg.ok { color: var(--accent); }
        .forgot-msg.err { color: var(--danger-text); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.85rem; }
        .btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            padding: 0.62rem 1rem; border-radius: 0.82rem; cursor: pointer; font: inherit; font-weight: 650;
            font-size: 0.88rem; border: 1px solid var(--border-strong); background: var(--surface); color: var(--text-main);
        }
        .btn-secondary:hover { border-color: rgba(37, 99, 235, 0.45); background: var(--input-bg); }
        body.pwr-modal-open { overflow: hidden; touch-action: none; }
        .login-2fa-mobile-hint { margin: 0 0 0.65rem; font-size: 0.78rem; color: var(--muted); }
        .login-2fa-code-input { text-align: center; letter-spacing: 0.12em; font-weight: 700; }
        .login-2fa-actions { margin-top: 1rem; }
        .login-2fa-resend:disabled { opacity: 0.65; cursor: not-allowed; }
        .login-2fa-resend.is-waiting { border-style: dashed; }
        @endif
    </style>
@endpush

@section('content')
    <div class="admin-login-brand">
        <div class="admin-login-logo">
            @if(!empty($appLogoUrl ?? null))
                <img src="{{ $appLogoUrl }}" alt="{{ $appDisplayName ?? 'لوگوی سامانه' }}">
            @elseif(!empty($appIconUrl ?? null))
                <img src="{{ $appIconUrl }}" alt="{{ $appDisplayName ?? 'آیکون سامانه' }}">
            @else
                <div class="admin-login-logo-fallback" aria-hidden="true">
                    <i class="{{ $appIconFaClass ?? 'fa-solid fa-shield-halved' }}"></i>
                </div>
            @endif
        </div>
        <div>
            <h1 class="admin-login-title">
                ورود به پنل مدیریت
                @if(!empty($appDisplayName ?? null))
                    ({{ $appDisplayName }})
                @endif
            </h1>
            <p class="admin-login-lead">
                @if (!empty($adminLoginTwoFactorEnabled))
                    نام کاربری و رمز عبور را وارد کنید؛ پس از آن کد پیامکی تأیید می‌شود.
                @else
                    نام کاربری و رمز عبور خود را به‌همراه کپچا وارد کنید.
                @endif
            </p>
        </div>
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

    <form id="admin-login-form" method="post" action="{{ route('admin.login.attempt') }}" novalidate autocomplete="off">
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

        <label for="captcha">
            <i class="fa-solid fa-shield-virus lbl-ico" aria-hidden="true"></i>
            کد تأیید تصویر
        </label>
        <div class="captcha-line">
            <img
                id="admin-captcha"
                src="{{ route('admin.captcha', [], false) }}?initial={{ uniqid('', true) }}"
                alt="کپچا"
                title="برای تصویر جدید روی تصویر کلیک کنید"
                width="160"
                height="48"
                decoding="async"
            >
            <input
                class="captcha-input"
                id="captcha"
                name="captcha"
                type="text"
                inputmode="text"
                maxlength="5"
                minlength="5"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="none"
                spellcheck="false"
                placeholder="کد داخل تصویر"
                aria-describedby="captcha-note"
                required
            >
        </div>
        <p id="captcha-note" class="help">
            <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
            <span>حروف کوچک انگلیسی و اعداد؛ ۵ کاراکتر؛ در صورت ناخوانایی روی تصویر کلیک کنید.</span>
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
@endsection

@if (!empty($adminLoginTwoFactorEnabled))
@push('portals')
    @include('admin.auth.partials.login-two-factor-modal')
@endpush
@endif

@section('scripts')
    <script>
        (function () {
            var img = document.getElementById('admin-captcha');
            var captchaInput = document.getElementById('captcha');
            var refreshUrl = @json(route('admin.captcha.refresh', [], false));
            var captchaGetBase = @json(route('admin.captcha', [], false));

            function csrfToken() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta && meta.content) return meta.content;
                var inp = document.querySelector('input[name="_token"]');

                return inp ? inp.value : '';
            }

            function refreshCaptcha(ev) {
                if (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }

                var token = csrfToken();
                var fd = new FormData();
                fd.append('_token', token);

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
                        if (! res.ok) throw new Error('captcha-refresh-http');
                        return res.json();
                    })
                    .then(function (payload) {
                        if (! payload || typeof payload.data_url !== 'string' || payload.data_url.length < 40) {
                            throw new Error('captcha-refresh-body');
                        }
                        img.onload = null;
                        img.src = payload.data_url;
                        if (captchaInput) {
                            captchaInput.value = '';
                            captchaInput.focus();
                        }
                    })
                    .catch(function () {
                        var sep = captchaGetBase.indexOf('?') === -1 ? '?' : '&';
                        img.src = captchaGetBase + sep + '_recover=' + Date.now();
                        if (captchaInput) captchaInput.value = '';
                    });
            }

            if (img) img.addEventListener('click', refreshCaptcha);

            @if (!empty($adminLoginTwoFactorEnabled))
            function jsonPost(url, body) {
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(body),
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    }).catch(function () {
                        return { ok: false, status: res.status, data: {} };
                    });
                });
            }

            (function initAdminLoginTwoFactor() {
                var modal2fa = document.getElementById('admin-modal-login-2fa');
                if (! modal2fa) return;

                var backdrop2fa = document.getElementById('admin-modal-login-2fa-backdrop');
                var btnClose2fa = document.getElementById('admin-modal-login-2fa-close');
                var sub2fa = document.getElementById('admin-modal-login-2fa-sub');
                var mobileHint = document.getElementById('admin-login-2fa-mobile-hint');
                var codeInput = document.getElementById('admin-login-2fa-code');
                var msg2fa = document.getElementById('admin-login-2fa-msg');
                var btnVerify2fa = document.getElementById('admin-login-2fa-verify');
                var btnResend2fa = document.getElementById('admin-login-2fa-resend');
                var resendLabel = document.getElementById('admin-login-2fa-resend-label');
                var loginSession = null;
                var resendTimer = null;
                var resendSecondsLeft = 0;

                function set2faMsg(text, kind) {
                    if (! msg2fa) return;
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
                    if (! btnResend2fa || ! resendLabel) return;
                    if (resendSecondsLeft > 0) {
                        btnResend2fa.disabled = true;
                        btnResend2fa.classList.add('is-waiting');
                        resendLabel.textContent = 'ارسال مجدد (' + resendSecondsLeft + ')';
                    } else {
                        btnResend2fa.disabled = ! loginSession;
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
                    if (codeInput) codeInput.value = '';
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

                if (backdrop2fa) backdrop2fa.addEventListener('click', closeLogin2faModal);
                if (btnClose2fa) btnClose2fa.addEventListener('click', closeLogin2faModal);
                document.addEventListener('keydown', function (ev) {
                    if (ev.key !== 'Escape' || ! modal2fa.classList.contains('is-open')) return;
                    ev.preventDefault();
                    closeLogin2faModal();
                });

                if (btnVerify2fa) {
                    btnVerify2fa.addEventListener('click', function () {
                        if (! loginSession) {
                            set2faMsg('نشست ورود منقضی شده؛ دوباره تلاش کنید.', 'err');
                            return;
                        }
                        var code = codeInput ? codeInput.value : '';
                        set2faMsg('در حال بررسی…', '');
                        btnVerify2fa.disabled = true;
                        jsonPost(@json(route('admin.login.verify-otp', [], false)), {
                            login_session: loginSession,
                            code: code,
                        })
                            .then(function (r) {
                                var m = (r.data && (r.data.message || r.data.errors)) ? (r.data.message || 'خطا') : 'خطا';
                                if (typeof r.data.errors === 'object' && r.data.errors) {
                                    var keys = Object.keys(r.data.errors);
                                    if (keys.length && r.data.errors[keys[0]][0]) m = r.data.errors[keys[0]][0];
                                }
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
                        if (! loginSession || resendSecondsLeft > 0) return;
                        set2faMsg('در حال ارسال مجدد…', '');
                        btnResend2fa.disabled = true;
                        jsonPost(@json(route('admin.login.resend-otp', [], false)), {
                            login_session: loginSession,
                        })
                            .then(function (r) {
                                var m = (r.data && r.data.message) ? r.data.message : 'خطا';
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
        })();
    </script>
@endsection
