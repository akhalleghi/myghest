@extends('layouts.admin.auth')

@section('title', 'ورود مدیر')

@section('content')
    <div class="brand">
        <div class="brand-row">
            <div class="brand-ico" aria-hidden="true">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <h1>ورود به پنل مدیریت</h1>
                <p class="lead">نام کاربری و رمز عبور خود را به‌همراه کپچا وارد کنید.</p>
            </div>
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

    <form method="post" action="{{ route('admin.login.attempt') }}" novalidate autocomplete="off">
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

@section('scripts')
    <script>
        (function () {
            var img = document.getElementById('admin-captcha');
            var captchaInput = document.getElementById('captcha');
            var refreshUrl = @json(route('admin.captcha.refresh', [], false));
            var captchaGetBase = @json(route('admin.captcha', [], false));

            if (! img) return;

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
                        if (! res.ok) {
                            throw new Error('captcha-refresh-http');
                        }

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
                        if (captchaInput) {
                            captchaInput.value = '';
                        }
                    });
            }

            img.addEventListener('click', refreshCaptcha);
        })();
    </script>
@endsection
