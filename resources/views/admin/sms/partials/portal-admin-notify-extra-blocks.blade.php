@php
    $fullSettlementEnabled = old('customer_full_settlement_notify_admin_enabled', ($smsCustomerFullSettlementNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $fullSettlementMessage = old(
        'customer_full_settlement_notify_admin_message',
        ($smsCustomerFullSettlementNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerFullSettlementNotifyAdmin['message_template']
            : ($customerFullSettlementNotifyAdminDefaultTemplate ?? '')
    );
    $fullSettlementSelectedIds = array_values(array_map('intval', is_array(old('customer_full_settlement_recipient_admin_ids', $smsCustomerFullSettlementNotifyAdmin['recipient_ids'] ?? [])) ? old('customer_full_settlement_recipient_admin_ids', $smsCustomerFullSettlementNotifyAdmin['recipient_ids'] ?? []) : []));

    $depositEnabled = old('customer_deposit_declaration_notify_admin_enabled', ($smsCustomerDepositDeclarationNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $depositMessage = old(
        'customer_deposit_declaration_notify_admin_message',
        ($smsCustomerDepositDeclarationNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerDepositDeclarationNotifyAdmin['message_template']
            : ($customerDepositDeclarationNotifyAdminDefaultTemplate ?? '')
    );
    $depositSelectedIds = array_values(array_map('intval', is_array(old('customer_deposit_declaration_recipient_admin_ids', $smsCustomerDepositDeclarationNotifyAdmin['recipient_ids'] ?? [])) ? old('customer_deposit_declaration_recipient_admin_ids', $smsCustomerDepositDeclarationNotifyAdmin['recipient_ids'] ?? []) : []));

    $ticketEnabled = old('customer_support_ticket_notify_admin_enabled', ($smsCustomerSupportTicketNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $ticketMessage = old(
        'customer_support_ticket_notify_admin_message',
        ($smsCustomerSupportTicketNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerSupportTicketNotifyAdmin['message_template']
            : ($customerSupportTicketNotifyAdminDefaultTemplate ?? '')
    );
    $ticketSelectedIds = array_values(array_map('intval', is_array(old('customer_support_ticket_recipient_admin_ids', $smsCustomerSupportTicketNotifyAdmin['recipient_ids'] ?? [])) ? old('customer_support_ticket_recipient_admin_ids', $smsCustomerSupportTicketNotifyAdmin['recipient_ids'] ?? []) : []));

    $loanRequestEnabled = old('customer_loan_request_notify_admin_enabled', ($smsCustomerLoanRequestNotifyAdmin['enabled'] ?? '') === '1' ? '1' : '') === '1';
    $loanRequestMessage = old(
        'customer_loan_request_notify_admin_message',
        ($smsCustomerLoanRequestNotifyAdmin['message_template'] ?? '') !== ''
            ? $smsCustomerLoanRequestNotifyAdmin['message_template']
            : ($customerLoanRequestNotifyAdminDefaultTemplate ?? '')
    );
    $loanRequestSelectedIds = array_values(array_map('intval', is_array(old('customer_loan_request_recipient_admin_ids', $smsCustomerLoanRequestNotifyAdmin['recipient_ids'] ?? [])) ? old('customer_loan_request_recipient_admin_ids', $smsCustomerLoanRequestNotifyAdmin['recipient_ids'] ?? []) : []));
@endphp

@include('admin.sms.partials.portal-admin-notify-block', [
    'blockId' => 'sms-notify-block-full-settlement',
    'iconClass' => 'sms-notify-block__icon--settlement',
    'icon' => 'fa-hand-holding-dollar',
    'title' => 'ارسال پیامک تسویهٔ یکجای وام',
    'description' => 'پس از تسویهٔ کامل بدهی از پنل کاربری (درگاه یا کیف پول)، پیامک برای ادمین‌های انتخاب‌شده ارسال می‌شود.',
    'formAction' => route('admin.sms.customer-full-settlement-notify-admin.update'),
    'formId' => 'sms-customer-full-settlement-notify-form',
    'enabled' => $fullSettlementEnabled,
    'enabledName' => 'customer_full_settlement_notify_admin_enabled',
    'enabledId' => 'sms-customer-full-settlement-notify-enabled',
    'fieldsId' => 'sms-customer-full-settlement-notify-fields',
    'openRecipientsId' => 'sms-customer-full-settlement-notify-open-recipients',
    'recipientCountId' => 'sms-customer-full-settlement-notify-recipient-count',
    'recipientInputsId' => 'sms-customer-full-settlement-notify-recipient-inputs',
    'recipientName' => 'customer_full_settlement_recipient_admin_ids',
    'selectedIds' => $fullSettlementSelectedIds,
    'messageId' => 'sms-customer-full-settlement-notify-message',
    'messageName' => 'customer_full_settlement_notify_admin_message',
    'messageValue' => $fullSettlementMessage,
    'previewId' => 'sms-customer-full-settlement-notify-preview',
    'patternAttr' => 'data-sms-customer-full-settlement-pattern',
    'notifyPatterns' => [
        ['token' => '{customer_full_name}', 'label' => 'نام مشتری'],
        ['token' => '{loan_number}', 'label' => 'شماره وام'],
        ['token' => '{settlement_amount}', 'label' => 'مبلغ تسویه'],
        ['token' => '{payment_method}', 'label' => 'نحوه پرداخت'],
        ['token' => '{app_name}', 'label' => 'نام سامانه'],
    ],
    'submitLabel' => 'ذخیره پیامک تسویه یکجا',
])

@include('admin.sms.partials.portal-admin-notify-block', [
    'blockId' => 'sms-notify-block-deposit-declaration',
    'iconClass' => 'sms-notify-block__icon--deposit',
    'icon' => 'fa-receipt',
    'title' => 'ارسال پیامک اعلام واریزی مشتری',
    'description' => 'هر بار مشتری در پنل کاربری «اعلام واریزی» ثبت کند (وضعیت در حال بررسی)، پیامک برای ادمین‌های انتخاب‌شده ارسال می‌شود.',
    'formAction' => route('admin.sms.customer-deposit-declaration-notify-admin.update'),
    'formId' => 'sms-customer-deposit-declaration-notify-form',
    'enabled' => $depositEnabled,
    'enabledName' => 'customer_deposit_declaration_notify_admin_enabled',
    'enabledId' => 'sms-customer-deposit-declaration-notify-enabled',
    'fieldsId' => 'sms-customer-deposit-declaration-notify-fields',
    'openRecipientsId' => 'sms-customer-deposit-declaration-notify-open-recipients',
    'recipientCountId' => 'sms-customer-deposit-declaration-notify-recipient-count',
    'recipientInputsId' => 'sms-customer-deposit-declaration-notify-recipient-inputs',
    'recipientName' => 'customer_deposit_declaration_recipient_admin_ids',
    'selectedIds' => $depositSelectedIds,
    'messageId' => 'sms-customer-deposit-declaration-notify-message',
    'messageName' => 'customer_deposit_declaration_notify_admin_message',
    'messageValue' => $depositMessage,
    'previewId' => 'sms-customer-deposit-declaration-notify-preview',
    'patternAttr' => 'data-sms-customer-deposit-declaration-pattern',
    'notifyPatterns' => [
        ['token' => '{customer_full_name}', 'label' => 'نام مشتری'],
        ['token' => '{installment_number}', 'label' => 'شماره قسط'],
        ['token' => '{deposit_amount}', 'label' => 'مبلغ واریزی'],
        ['token' => '{loan_number}', 'label' => 'شماره وام'],
        ['token' => '{payment_method}', 'label' => 'نحوه پرداخت'],
    ],
    'submitLabel' => 'ذخیره پیامک اعلام واریزی',
])

@include('admin.sms.partials.portal-admin-notify-block', [
    'blockId' => 'sms-notify-block-support-ticket',
    'iconClass' => 'sms-notify-block__icon--ticket',
    'icon' => 'fa-life-ring',
    'title' => 'ارسال پیامک ثبت تیکت مشتری',
    'description' => 'وقتی مشتری تیکت جدید در پنل کاربری ثبت کند، پیامک اطلاع‌رسانی برای ادمین‌های انتخاب‌شده ارسال می‌شود.',
    'formAction' => route('admin.sms.customer-support-ticket-notify-admin.update'),
    'formId' => 'sms-customer-support-ticket-notify-form',
    'enabled' => $ticketEnabled,
    'enabledName' => 'customer_support_ticket_notify_admin_enabled',
    'enabledId' => 'sms-customer-support-ticket-notify-enabled',
    'fieldsId' => 'sms-customer-support-ticket-notify-fields',
    'openRecipientsId' => 'sms-customer-support-ticket-notify-open-recipients',
    'recipientCountId' => 'sms-customer-support-ticket-notify-recipient-count',
    'recipientInputsId' => 'sms-customer-support-ticket-notify-recipient-inputs',
    'recipientName' => 'customer_support_ticket_recipient_admin_ids',
    'selectedIds' => $ticketSelectedIds,
    'messageId' => 'sms-customer-support-ticket-notify-message',
    'messageName' => 'customer_support_ticket_notify_admin_message',
    'messageValue' => $ticketMessage,
    'previewId' => 'sms-customer-support-ticket-notify-preview',
    'patternAttr' => 'data-sms-customer-support-ticket-pattern',
    'notifyPatterns' => [
        ['token' => '{customer_full_name}', 'label' => 'نام مشتری'],
        ['token' => '{ticket_subject}', 'label' => 'موضوع تیکت'],
        ['token' => '{ticket_id}', 'label' => 'شناسه تیکت'],
    ],
    'submitLabel' => 'ذخیره پیامک تیکت',
])

@include('admin.sms.partials.portal-admin-notify-block', [
    'blockId' => 'sms-notify-block-loan-request',
    'iconClass' => 'sms-notify-block__icon--loan-request',
    'icon' => 'fa-file-signature',
    'title' => 'ارسال پیامک ثبت درخواست وام',
    'description' => 'پس از ثبت درخواست وام جدید توسط مشتری در پنل کاربری، پیامک برای ادمین‌های انتخاب‌شده ارسال می‌شود.',
    'formAction' => route('admin.sms.customer-loan-request-notify-admin.update'),
    'formId' => 'sms-customer-loan-request-notify-form',
    'enabled' => $loanRequestEnabled,
    'enabledName' => 'customer_loan_request_notify_admin_enabled',
    'enabledId' => 'sms-customer-loan-request-notify-enabled',
    'fieldsId' => 'sms-customer-loan-request-notify-fields',
    'openRecipientsId' => 'sms-customer-loan-request-notify-open-recipients',
    'recipientCountId' => 'sms-customer-loan-request-notify-recipient-count',
    'recipientInputsId' => 'sms-customer-loan-request-notify-recipient-inputs',
    'recipientName' => 'customer_loan_request_recipient_admin_ids',
    'selectedIds' => $loanRequestSelectedIds,
    'messageId' => 'sms-customer-loan-request-notify-message',
    'messageName' => 'customer_loan_request_notify_admin_message',
    'messageValue' => $loanRequestMessage,
    'previewId' => 'sms-customer-loan-request-notify-preview',
    'patternAttr' => 'data-sms-customer-loan-request-pattern',
    'notifyPatterns' => [
        ['token' => '{customer_full_name}', 'label' => 'نام مشتری'],
        ['token' => '{loan_type}', 'label' => 'طرح وام'],
        ['token' => '{request_amount}', 'label' => 'مبلغ درخواست'],
        ['token' => '{request_id}', 'label' => 'شناسه درخواست'],
    ],
    'submitLabel' => 'ذخیره پیامک درخواست وام',
])

@include('admin.sms.partials.portal-admin-notify-modals', [
    'prefix' => 'customer-full-settlement',
    'recipientsTitle' => 'دریافت‌کنندگان پیامک تسویه یکجا',
    'pickerHint' => 'ادمین‌های دریافت‌کننده پیامک تسویهٔ یکجای وام را انتخاب کنید.',
])
@include('admin.sms.partials.portal-admin-notify-modals', [
    'prefix' => 'customer-deposit-declaration',
    'recipientsTitle' => 'دریافت‌کنندگان پیامک اعلام واریزی',
    'pickerHint' => 'ادمین‌های دریافت‌کننده پیامک اعلام واریزی مشتری را انتخاب کنید.',
])
@include('admin.sms.partials.portal-admin-notify-modals', [
    'prefix' => 'customer-support-ticket',
    'recipientsTitle' => 'دریافت‌کنندگان پیامک تیکت',
    'pickerHint' => 'ادمین‌های دریافت‌کننده پیامک ثبت تیکت مشتری را انتخاب کنید.',
])
@include('admin.sms.partials.portal-admin-notify-modals', [
    'prefix' => 'customer-loan-request',
    'recipientsTitle' => 'دریافت‌کنندگان پیامک درخواست وام',
    'pickerHint' => 'ادمین‌های دریافت‌کننده پیامک ثبت درخواست وام را انتخاب کنید.',
])
