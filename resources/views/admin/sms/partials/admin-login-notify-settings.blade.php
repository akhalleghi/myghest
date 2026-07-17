@php
    $smsAdminLoginEnabled = old('admin_login_notify_enabled', ($smsAdminLoginNotify['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $smsAdminLoginMessage = old(
        'admin_login_notify_message',
        ($smsAdminLoginNotify['message_template'] ?? '') !== ''
            ? $smsAdminLoginNotify['message_template']
            : ($adminLoginNotifyDefaultTemplate ?? '')
    );
    $smsAdminLoginSelectedIds = old('recipient_admin_ids', $smsAdminLoginNotify['recipient_ids'] ?? []);
    if (! is_array($smsAdminLoginSelectedIds)) {
        $smsAdminLoginSelectedIds = [];
    }
    $smsAdminLoginSelectedIds = array_values(array_map('intval', $smsAdminLoginSelectedIds));

    $smsAdminSelfEnabled = old('admin_login_self_notify_enabled', ($smsAdminLoginSelfNotify['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $smsAdminSelfMessage = old(
        'admin_login_self_notify_message',
        ($smsAdminLoginSelfNotify['message_template'] ?? '') !== ''
            ? $smsAdminLoginSelfNotify['message_template']
            : ($adminLoginSelfNotifyDefaultTemplate ?? '')
    );

    $smsCustomerLoginNotifyEnabled = old('customer_login_notify_admin_enabled', ($smsCustomerLoginNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $smsCustomerLoginNotifyMessage = old(
        'customer_login_notify_admin_message',
        ($smsCustomerLoginNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerLoginNotifyAdmin['message_template']
            : ($customerLoginNotifyAdminDefaultTemplate ?? '')
    );
    $smsCustomerLoginSelectedIds = old('customer_login_recipient_admin_ids', $smsCustomerLoginNotifyAdmin['recipient_ids'] ?? []);
    if (! is_array($smsCustomerLoginSelectedIds)) {
        $smsCustomerLoginSelectedIds = [];
    }
    $smsCustomerLoginSelectedIds = array_values(array_map('intval', $smsCustomerLoginSelectedIds));

    $smsCustomerInstallmentPaymentEnabled = old('customer_installment_payment_notify_admin_enabled', ($smsCustomerInstallmentPaymentNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $smsCustomerInstallmentPaymentMessage = old(
        'customer_installment_payment_notify_admin_message',
        ($smsCustomerInstallmentPaymentNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerInstallmentPaymentNotifyAdmin['message_template']
            : ($customerInstallmentPaymentNotifyAdminDefaultTemplate ?? '')
    );
    $smsCustomerInstallmentPaymentSelectedIds = old('customer_installment_payment_recipient_admin_ids', $smsCustomerInstallmentPaymentNotifyAdmin['recipient_ids'] ?? []);
    if (! is_array($smsCustomerInstallmentPaymentSelectedIds)) {
        $smsCustomerInstallmentPaymentSelectedIds = [];
    }
    $smsCustomerInstallmentPaymentSelectedIds = array_values(array_map('intval', $smsCustomerInstallmentPaymentSelectedIds));
@endphp

<section class="sms-notify-hub" id="sms-admin-notify-hub" aria-labelledby="sms-notify-hub-title">
    <header class="sms-notify-hub__head">
        <h2 class="sms-notify-hub__title" id="sms-notify-hub-title">
            <i class="fa-solid fa-comments" aria-hidden="true"></i>
            تنظیمات پیامک‌ها
        </h2>
        <p class="sms-notify-hub__lead">هر گزینه را جداگانه فعال و ذخیره کنید. ارسال پیامک‌ها پس از تکمیل عملیات کاربر (در پس‌زمینه) انجام می‌شود و سرعت پنل را کند نمی‌کند.</p>
    </header>

    <article class="sms-notify-block @if($smsAdminLoginEnabled) is-on @endif" id="sms-notify-block-managers" data-sms-notify-block>
        <div class="sms-notify-block__head">
            <span class="sms-notify-block__icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
            <div class="sms-notify-block__titles">
                <h3 class="sms-notify-block__name">اعلان ورود به سایر مدیران</h3>
                <p class="sms-notify-block__desc">هر بار یکی از کاربران ادمین (بخش «کاربران» پنل) وارد شود، پیامک برای ادمین‌های انتخاب‌شده در زیر ارسال می‌شود.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.sms.admin-login-notify.update') }}" class="sms-notify-block__form" id="sms-admin-login-notify-form">
            @csrf
            <div class="sms-toggle-row sms-notify-block__toggle">
                <label class="sms-toggle-label">
                    <input
                        type="checkbox"
                        name="admin_login_notify_enabled"
                        id="sms-admin-login-enabled"
                        value="1"
                        data-sms-notify-block-toggle="managers"
                        @checked($smsAdminLoginEnabled)
                    >
                    فعال‌سازی این گزینه
                </label>
                @error('admin_login_notify_enabled')<div class="sms-field-error">{{ $message }}</div>@enderror
            </div>

            <div id="sms-admin-login-fields" class="sms-notify-block__body @if(! $smsAdminLoginEnabled) sms-reminder-hidden @endif">
                <div class="sms-notify-row">
                    <button type="button" class="sms-notify-pick-btn" id="sms-admin-login-open-recipients">
                        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                        انتخاب دریافت‌کنندگان
                        <span id="sms-admin-login-recipient-count">({{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($smsAdminLoginSelectedIds)) }})</span>
                    </button>
                    @error('recipient_admin_ids')<div class="sms-field-error">{{ $message }}</div>@enderror
                </div>

                <div id="sms-admin-login-recipient-inputs">
                    @foreach ($smsAdminLoginSelectedIds as $rid)
                        <input type="hidden" name="recipient_admin_ids[]" value="{{ $rid }}">
                    @endforeach
                </div>

                @include('admin.sms.partials.admin-login-notify-message-field', [
                    'textareaId' => 'sms-admin-login-message',
                    'textareaName' => 'admin_login_notify_message',
                    'textareaValue' => $smsAdminLoginMessage,
                    'previewId' => 'sms-admin-login-preview',
                    'patternAttr' => 'data-sms-login-pattern',
                    'errorKey' => 'admin_login_notify_message',
                ])
            </div>

            <div class="sms-notify-block__foot">
                <button class="sms-settings-submit" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    ذخیره اعلان مدیران
                </button>
            </div>
        </form>
    </article>

    <article class="sms-notify-block @if($smsAdminSelfEnabled) is-on @endif" id="sms-notify-block-self" data-sms-notify-block>
        <div class="sms-notify-block__head">
            <span class="sms-notify-block__icon sms-notify-block__icon--self" aria-hidden="true"><i class="fa-solid fa-mobile-screen-button"></i></span>
            <div class="sms-notify-block__titles">
                <h3 class="sms-notify-block__name">پیامک تأیید ورود برای خود ادمین</h3>
                <p class="sms-notify-block__desc">برای همهٔ کاربران ادمین تعریف‌شده در بخش «کاربران»؛ با ورود موفق هر کدام، پیامک تأیید به شمارهٔ موبایل همان حساب (خودش) ارسال می‌شود.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.sms.admin-login-self-notify.update') }}" class="sms-notify-block__form" id="sms-admin-login-self-form">
            @csrf
            <div class="sms-toggle-row sms-notify-block__toggle">
                <label class="sms-toggle-label">
                    <input
                        type="checkbox"
                        name="admin_login_self_notify_enabled"
                        id="sms-admin-login-self-enabled"
                        value="1"
                        data-sms-notify-block-toggle="self"
                        @checked($smsAdminSelfEnabled)
                    >
                    فعال‌سازی این گزینه
                </label>
                @error('admin_login_self_notify_enabled')<div class="sms-field-error">{{ $message }}</div>@enderror
            </div>

            <div id="sms-admin-self-fields" class="sms-notify-block__body @if(! $smsAdminSelfEnabled) sms-reminder-hidden @endif">
                @include('admin.sms.partials.admin-login-notify-message-field', [
                    'textareaId' => 'sms-admin-self-message',
                    'textareaName' => 'admin_login_self_notify_message',
                    'textareaValue' => $smsAdminSelfMessage,
                    'previewId' => 'sms-admin-self-preview',
                    'patternAttr' => 'data-sms-self-pattern',
                    'errorKey' => 'admin_login_self_notify_message',
                ])
            </div>

            <div class="sms-notify-block__foot">
                <button class="sms-settings-submit" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    ذخیره پیامک خود ادمین
                </button>
            </div>
        </form>
    </article>

    <article class="sms-notify-block @if($smsCustomerLoginNotifyEnabled) is-on @endif" id="sms-notify-block-customer-login" data-sms-notify-block>
        <div class="sms-notify-block__head">
            <span class="sms-notify-block__icon sms-notify-block__icon--customer" aria-hidden="true"><i class="fa-solid fa-user-lock"></i></span>
            <div class="sms-notify-block__titles">
                <h3 class="sms-notify-block__name">ارسال پیامک ورود مشتری برای ادمین</h3>
                <p class="sms-notify-block__desc">هر بار مشتری وارد پنل کاربری شود، پیامک برای ادمین‌های انتخاب‌شده ارسال می‌شود.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.sms.customer-login-notify-admin.update') }}" class="sms-notify-block__form" id="sms-customer-login-notify-form">
            @csrf
            <div class="sms-toggle-row sms-notify-block__toggle">
                <label class="sms-toggle-label">
                    <input
                        type="checkbox"
                        name="customer_login_notify_admin_enabled"
                        id="sms-customer-login-notify-enabled"
                        value="1"
                        data-sms-notify-block-toggle="customer-login"
                        @checked($smsCustomerLoginNotifyEnabled)
                    >
                    فعال‌سازی این گزینه
                </label>
                @error('customer_login_notify_admin_enabled')<div class="sms-field-error">{{ $message }}</div>@enderror
            </div>

            <div id="sms-customer-login-notify-fields" class="sms-notify-block__body @if(! $smsCustomerLoginNotifyEnabled) sms-reminder-hidden @endif">
                <div class="sms-notify-row">
                    <button type="button" class="sms-notify-pick-btn" id="sms-customer-login-notify-open-recipients">
                        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                        انتخاب دریافت‌کنندگان
                        <span id="sms-customer-login-notify-recipient-count">({{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($smsCustomerLoginSelectedIds)) }})</span>
                    </button>
                    @error('customer_login_recipient_admin_ids')<div class="sms-field-error">{{ $message }}</div>@enderror
                </div>

                <div id="sms-customer-login-notify-recipient-inputs">
                    @foreach ($smsCustomerLoginSelectedIds as $rid)
                        <input type="hidden" name="customer_login_recipient_admin_ids[]" value="{{ $rid }}">
                    @endforeach
                </div>

                @include('admin.sms.partials.admin-login-notify-message-field', [
                    'textareaId' => 'sms-customer-login-notify-message',
                    'textareaName' => 'customer_login_notify_admin_message',
                    'textareaValue' => $smsCustomerLoginNotifyMessage,
                    'previewId' => 'sms-customer-login-notify-preview',
                    'patternAttr' => 'data-sms-customer-login-pattern',
                    'errorKey' => 'customer_login_notify_admin_message',
                    'notifyPatterns' => [
                        ['token' => '{customer_full_name}', 'label' => 'نام و نام خانوادگی مشتری'],
                        ['token' => '{customer_username}', 'label' => 'نام کاربری مشتری'],
                        ['token' => '{app_name}', 'label' => 'نام سامانه'],
                    ],
                ])
            </div>

            <div class="sms-notify-block__foot">
                <button class="sms-settings-submit" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    ذخیره پیامک ورود مشتری
                </button>
            </div>
        </form>
    </article>

    <article class="sms-notify-block @if($smsCustomerInstallmentPaymentEnabled) is-on @endif" id="sms-notify-block-customer-installment-payment" data-sms-notify-block>
        <div class="sms-notify-block__head">
            <span class="sms-notify-block__icon sms-notify-block__icon--installment" aria-hidden="true"><i class="fa-solid fa-money-bill-transfer"></i></span>
            <div class="sms-notify-block__titles">
                <h3 class="sms-notify-block__name">ارسال پیامک واریزی قسط توسط مشتری</h3>
                <p class="sms-notify-block__desc">پس از پرداخت موفق قسط از پنل کاربری (درگاه بانکی یا کیف پول)، پیامک برای ادمین‌های انتخاب‌شده ارسال می‌شود.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.sms.customer-installment-payment-notify-admin.update') }}" class="sms-notify-block__form" id="sms-customer-installment-payment-notify-form">
            @csrf
            <div class="sms-toggle-row sms-notify-block__toggle">
                <label class="sms-toggle-label">
                    <input
                        type="checkbox"
                        name="customer_installment_payment_notify_admin_enabled"
                        id="sms-customer-installment-payment-notify-enabled"
                        value="1"
                        data-sms-notify-block-toggle="customer-installment-payment"
                        @checked($smsCustomerInstallmentPaymentEnabled)
                    >
                    فعال‌سازی این گزینه
                </label>
                @error('customer_installment_payment_notify_admin_enabled')<div class="sms-field-error">{{ $message }}</div>@enderror
            </div>

            <div id="sms-customer-installment-payment-notify-fields" class="sms-notify-block__body @if(! $smsCustomerInstallmentPaymentEnabled) sms-reminder-hidden @endif">
                <div class="sms-notify-row">
                    <button type="button" class="sms-notify-pick-btn" id="sms-customer-installment-payment-notify-open-recipients">
                        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                        انتخاب دریافت‌کنندگان
                        <span id="sms-customer-installment-payment-notify-recipient-count">({{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($smsCustomerInstallmentPaymentSelectedIds)) }})</span>
                    </button>
                    @error('customer_installment_payment_recipient_admin_ids')<div class="sms-field-error">{{ $message }}</div>@enderror
                </div>

                <div id="sms-customer-installment-payment-notify-recipient-inputs">
                    @foreach ($smsCustomerInstallmentPaymentSelectedIds as $rid)
                        <input type="hidden" name="customer_installment_payment_recipient_admin_ids[]" value="{{ $rid }}">
                    @endforeach
                </div>

                @include('admin.sms.partials.admin-login-notify-message-field', [
                    'textareaId' => 'sms-customer-installment-payment-notify-message',
                    'textareaName' => 'customer_installment_payment_notify_admin_message',
                    'textareaValue' => $smsCustomerInstallmentPaymentMessage,
                    'previewId' => 'sms-customer-installment-payment-notify-preview',
                    'patternAttr' => 'data-sms-customer-installment-payment-pattern',
                    'errorKey' => 'customer_installment_payment_notify_admin_message',
                    'notifyPatterns' => [
                        ['token' => '{customer_full_name}', 'label' => 'نام مشتری'],
                        ['token' => '{installment_number}', 'label' => 'شماره قسط'],
                        ['token' => '{installment_amount}', 'label' => 'مبلغ قسط'],
                        ['token' => '{loan_number}', 'label' => 'شماره وام'],
                        ['token' => '{payment_method}', 'label' => 'نحوه پرداخت'],
                        ['token' => '{app_name}', 'label' => 'نام سامانه'],
                    ],
                ])
            </div>

            <div class="sms-notify-block__foot">
                <button class="sms-settings-submit" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    ذخیره پیامک واریزی قسط
                </button>
            </div>
        </form>
    </article>

    @include('admin.sms.partials.portal-admin-notify-extra-blocks')
</section>

@include('admin.sms.partials.admin-login-notify-modals')
@include('admin.sms.partials.customer-login-notify-admin-modals')
@include('admin.sms.partials.customer-installment-payment-notify-admin-modals')
