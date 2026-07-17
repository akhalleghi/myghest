<?php

declare(strict_types=1);

/**
 * درخت دسترسی‌های پنل ادمین.
 * هر گره می‌تواند فرزند داشته باشد؛ برگ‌ها (یا گره‌های دارای routes) مسیرهای مجاز را مشخص می‌کنند.
 */
return [
    'exempt_route_names' => [
        'admin.logout',
    ],

    'tree' => [
        [
            'key' => 'dashboard',
            'label' => 'داشبورد',
        ],
        [
            'key' => 'loan_types',
            'label' => 'تعریف انواع وام',
            'children' => [
                ['key' => 'loan_types.view', 'label' => 'مشاهده و فهرست', 'routes' => ['admin.loan-types.index', 'admin.loan-types.plan-image']],
                ['key' => 'loan_types.export', 'label' => 'خروجی اکسل', 'routes' => ['admin.loan-types.export-excel']],
                ['key' => 'loan_types.create', 'label' => 'افزودن نوع وام', 'routes' => ['admin.loan-types.store']],
                ['key' => 'loan_types.update', 'label' => 'ویرایش نوع وام', 'routes' => ['admin.loan-types.update']],
                ['key' => 'loan_types.delete', 'label' => 'حذف نوع وام', 'routes' => ['admin.loan-types.destroy']],
            ],
        ],
        [
            'key' => 'customers',
            'label' => 'لیست مشتریان',
            'children' => [
                ['key' => 'customers.view', 'label' => 'مشاهده لیست و جزئیات', 'routes' => [
                    'admin.customers.index', 'admin.customers.edit-data', 'admin.customers.loan-manage-modal-context',
                    'admin.customers.loan-board-summary', 'admin.customers.loan-requests.embed',
                    'admin.customers.customer-transactions.embed',
                    'admin.customers.tickets.embed', 'admin.customers.tickets.list',
                    'admin.customers.tickets.store', 'admin.customers.tickets.reply', 'admin.customers.tickets.status',
                    'admin.tickets.attachment',
                    'admin.customers.sms-modal-preview',
                    'admin.customers.sms-logs', 'admin.customers.guarantees-report',
                ]],
                ['key' => 'customers.export', 'label' => 'خروجی اکسل مشتریان', 'routes' => ['admin.customers.export-excel', 'admin.customers.sms-logs.export-excel', 'admin.customers.guarantees-report.export-excel']],
                ['key' => 'customers.import', 'label' => 'ورود اکسل مشتریان', 'routes' => ['admin.customers.import.sample-excel', 'admin.customers.import-excel']],
                ['key' => 'customers.create', 'label' => 'افزودن مشتری', 'routes' => ['admin.customers.store']],
                ['key' => 'customers.update', 'label' => 'ویرایش مشتری', 'routes' => ['admin.customers.update']],
                ['key' => 'customers.delete', 'label' => 'حذف مشتری', 'routes' => ['admin.customers.destroy']],
                ['key' => 'customers.quick_sms', 'label' => 'ارسال پیامک سریع', 'routes' => ['admin.customers.quick-sms']],
                [
                    'key' => 'customers.loans',
                    'label' => 'پرونده وام مشتری',
                    'children' => [
                        ['key' => 'customers.loans.create', 'label' => 'افزودن پرونده وام', 'routes' => [
                            'admin.customers.loan-files.store',
                            'admin.customers.loan-creation-otp.send',
                            'admin.customers.loan-creation-otp.verify',
                        ]],
                        ['key' => 'customers.loans.update', 'label' => 'ویرایش پرونده وام', 'routes' => ['admin.customers.loan-files.update']],
                        ['key' => 'customers.loans.delete', 'label' => 'حذف پرونده وام', 'routes' => ['admin.customers.loan-files.destroy']],
                        ['key' => 'customers.loans.revoke', 'label' => 'ابطال قرارداد', 'routes' => ['admin.customers.loan-files.revoke-contract']],
                        ['key' => 'customers.loans.sms', 'label' => 'پیامک پرونده', 'routes' => ['admin.customers.loan-files.send-sms']],
                        ['key' => 'customers.loans.booklet', 'label' => 'چاپ دفترچه اقساط', 'routes' => ['admin.customers.loan-files.installment-booklet']],
                        ['key' => 'customers.loans.settlement', 'label' => 'تسویه آنی / پیش‌نمایش', 'routes' => [
                            'admin.customers.loan-files.instant-settlement-preview',
                            'admin.customers.loan-files.discount-preview',
                            'admin.customers.loan-files.discount.store',
                        ]],
                        [
                            'key' => 'customers.loans.installments',
                            'label' => 'اقساط',
                            'children' => [
                                ['key' => 'customers.loans.installments.view', 'label' => 'مشاهده اقساط', 'routes' => [
                                    'admin.customers.loan-files.installments.index',
                                    'admin.customers.loan-files.installments.payments.index',
                                ]],
                                ['key' => 'customers.loans.installments.update', 'label' => 'ویرایش قسط', 'routes' => ['admin.customers.loan-files.installments.update']],
                                [
                                    'key' => 'customers.loans.installments.payments',
                                    'label' => 'پرداخت‌های قسط',
                                    'children' => [
                                        ['key' => 'customers.loans.installments.payments.create', 'label' => 'ثبت پرداخت', 'routes' => ['admin.customers.loan-files.installments.payments.store']],
                                        ['key' => 'customers.loans.installments.payments.update', 'label' => 'ویرایش پرداخت', 'routes' => ['admin.customers.loan-files.installments.payments.update']],
                                        ['key' => 'customers.loans.installments.payments.delete', 'label' => 'حذف پرداخت', 'routes' => [
                                            'admin.customers.loan-files.installments.payments.destroy',
                                            'admin.customers.loan-files.installments.payments.destroy-all',
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key' => 'customers.loans.guarantees',
                            'label' => 'تضامین',
                            'children' => [
                                ['key' => 'customers.loans.guarantees.view', 'label' => 'مشاهده تضامین', 'routes' => [
                                    'admin.customers.loan-files.guarantees.index',
                                    'admin.customers.loan-files.guarantees.attachment',
                                ]],
                        ['key' => 'customers.loans.guarantees.create', 'label' => 'افزودن ضمانت', 'routes' => [
                            'admin.customers.loan-files.guarantees.store',
                            'admin.guarantor-otp.send',
                            'admin.guarantor-otp.verify',
                            'admin.customers.loan-files.guarantee-return-otp.send',
                            'admin.customers.loan-files.guarantee-return-otp.verify',
                        ]],
                        ['key' => 'customers.loans.guarantees.update', 'label' => 'ویرایش ضمانت', 'routes' => [
                            'admin.customers.loan-files.guarantees.update',
                            'admin.customers.loan-files.guarantees.return-document',
                            'admin.customers.loan-files.guarantee-return-otp.send',
                            'admin.customers.loan-files.guarantee-return-otp.verify',
                        ]],
                                ['key' => 'customers.loans.guarantees.delete', 'label' => 'حذف ضمانت', 'routes' => ['admin.customers.loan-files.guarantees.destroy']],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'customers.wallet',
                    'label' => 'کیف پول مشتری',
                    'children' => [
                        ['key' => 'customers.wallet.view', 'label' => 'مشاهده کیف پول', 'routes' => [
                            'admin.customers.wallet.show',
                            'admin.customers.wallet.transactions',
                            'admin.customers.wallet.transactions.export-excel',
                        ]],
                        ['key' => 'customers.wallet.lock', 'label' => 'قفل / باز کردن کیف پول', 'routes' => ['admin.customers.wallet.lock']],
                        ['key' => 'customers.wallet.adjust', 'label' => 'تعدیل موجودی', 'routes' => ['admin.customers.wallet.adjust']],
                    ],
                ],
            ],
        ],
        [
            'key' => 'deposit_declarations',
            'label' => 'اعلام واریزها',
            'children' => [
                ['key' => 'deposit_declarations.view', 'label' => 'مشاهده و پیوست', 'routes' => [
                    'admin.deposit-declarations.index',
                    'admin.deposit-declarations.attachment',
                ]],
                ['key' => 'deposit_declarations.review', 'label' => 'بررسی و تأیید/رد', 'routes' => ['admin.deposit-declarations.review']],
            ],
        ],
        [
            'key' => 'customer_transactions',
            'label' => 'تراکنش‌ها',
            'children' => [
                ['key' => 'customer_transactions.view', 'label' => 'مشاهده تراکنش‌ها', 'routes' => ['admin.customer-transactions.index']],
                ['key' => 'customer_transactions.export', 'label' => 'خروجی تراکنش‌ها', 'routes' => ['admin.customer-transactions.export']],
            ],
        ],
        [
            'key' => 'tickets',
            'label' => 'تیکت‌ها',
            'children' => [
                ['key' => 'tickets.view', 'label' => 'مشاهده تیکت‌ها', 'routes' => [
                    'admin.tickets.index', 'admin.tickets.list', 'admin.tickets.customers-search',
                    'admin.tickets.attachment',
                ]],
                ['key' => 'tickets.create', 'label' => 'ایجاد تیکت', 'routes' => ['admin.tickets.store']],
                ['key' => 'tickets.reply', 'label' => 'پاسخ به تیکت', 'routes' => ['admin.tickets.reply']],
                ['key' => 'tickets.status', 'label' => 'تغییر وضعیت تیکت', 'routes' => ['admin.tickets.status']],
            ],
        ],
        [
            'key' => 'internal_tickets',
            'label' => 'تیکت داخلی',
            'children' => [
                ['key' => 'internal_tickets.view', 'label' => 'مشاهده تیکت داخلی', 'routes' => [
                    'admin.internal-tickets.index', 'admin.internal-tickets.list', 'admin.internal-tickets.admins-search',
                    'admin.internal-tickets.show', 'admin.internal-tickets.attachment',
                ]],
                ['key' => 'internal_tickets.create', 'label' => 'ایجاد تیکت داخلی', 'routes' => ['admin.internal-tickets.store']],
                ['key' => 'internal_tickets.reply', 'label' => 'پاسخ به تیکت داخلی', 'routes' => ['admin.internal-tickets.reply']],
                ['key' => 'internal_tickets.status', 'label' => 'تغییر وضعیت تیکت داخلی', 'routes' => ['admin.internal-tickets.status']],
            ],
        ],
        [
            'key' => 'sms',
            'label' => 'مدیریت پیامک',
            'children' => [
                ['key' => 'sms.reports', 'label' => 'گزارش پیامک‌ها', 'routes' => [
                    'admin.sms.index', 'admin.sms.export-excel', 'admin.sms.destroy',
                ]],
                [
                    'key' => 'sms.templates',
                    'label' => 'الگوهای پیامک',
                    'children' => [
                        ['key' => 'sms.templates.view', 'label' => 'مشاهده الگوها', 'routes' => ['admin.sms.index']],
                        ['key' => 'sms.templates.create', 'label' => 'افزودن الگو', 'routes' => ['admin.sms.templates.store']],
                        ['key' => 'sms.templates.update', 'label' => 'ویرایش الگو', 'routes' => ['admin.sms.templates.update']],
                        ['key' => 'sms.templates.delete', 'label' => 'حذف الگو', 'routes' => ['admin.sms.templates.destroy']],
                    ],
                ],
                [
                    'key' => 'sms.settings',
                    'label' => 'تنظیمات پنل پیامک',
                    'children' => [
                        ['key' => 'sms.settings.panel', 'label' => 'اتصال و تست پنل', 'routes' => [
                            'admin.sms.index',
                            'admin.sms.panel-settings.update', 'admin.sms.panel-test.send',
                        ]],
                        ['key' => 'sms.settings.scenarios', 'label' => 'قالب‌های سناریویی', 'routes' => [
                            'admin.sms.index', 'admin.sms.scenario-templates.update',
                        ]],
                        ['key' => 'sms.settings.reminders', 'label' => 'یادآوری اقساط', 'routes' => [
                            'admin.sms.index', 'admin.sms.reminder-settings.update',
                        ]],
                        ['key' => 'sms.settings.messages', 'label' => 'تنظیمات پیامک‌ها', 'routes' => [
                            'admin.sms.index',
                            'admin.sms.admin-login-notify.update',
                            'admin.sms.admin-login-self-notify.update',
                            'admin.sms.customer-login-notify-admin.update',
                            'admin.sms.customer-installment-payment-notify-admin.update',
                            'admin.sms.customer-full-settlement-notify-admin.update',
                            'admin.sms.customer-deposit-declaration-notify-admin.update',
                            'admin.sms.customer-support-ticket-notify-admin.update',
                            'admin.sms.customer-loan-request-notify-admin.update',
                        ]],
                    ],
                ],
            ],
        ],
        [
            'key' => 'loan_requests',
            'label' => 'درخواست وام‌ها',
            'children' => [
                ['key' => 'loan_requests.view', 'label' => 'مشاهده درخواست‌ها', 'routes' => [
                    'admin.loan-requests.index', 'admin.loan-requests.edit-context', 'admin.loan-requests.convert-preview',
                    'admin.loan-requests.status-logs', 'admin.loan-requests.status-sms-logs',
                    'admin.loan-requests.documents.file', 'admin.loan-requests.print',
                ]],
                ['key' => 'loan_requests.export', 'label' => 'خروجی درخواست‌ها', 'routes' => [
                    'admin.loan-requests.export', 'admin.loan-requests.status-logs.export',
                ]],
                ['key' => 'loan_requests.update', 'label' => 'ویرایش درخواست', 'routes' => ['admin.loan-requests.update']],
                ['key' => 'loan_requests.delete', 'label' => 'حذف درخواست', 'routes' => ['admin.loan-requests.destroy']],
                ['key' => 'loan_requests.convert', 'label' => 'تبدیل به پرونده وام', 'routes' => ['admin.loan-requests.convert']],
                ['key' => 'loan_requests.documents', 'label' => 'مدیریت مدارک درخواست', 'routes' => [
                    'admin.loan-requests.documents.update', 'admin.loan-requests.documents.destroy',
                ]],
                ['key' => 'loan_requests.status_sms', 'label' => 'پیامک وضعیت درخواست', 'routes' => ['admin.loan-requests.status-sms-logs.resend']],
                [
                    'key' => 'loan_requests.status_definitions',
                    'label' => 'تعریف وضعیت‌های درخواست',
                    'children' => [
                        ['key' => 'loan_requests.status_definitions.view', 'label' => 'مشاهده', 'routes' => ['admin.loan-request-status-definitions.index']],
                        ['key' => 'loan_requests.status_definitions.create', 'label' => 'افزودن', 'routes' => ['admin.loan-request-status-definitions.store']],
                        ['key' => 'loan_requests.status_definitions.update', 'label' => 'ویرایش', 'routes' => ['admin.loan-request-status-definitions.update']],
                        ['key' => 'loan_requests.status_definitions.delete', 'label' => 'حذف', 'routes' => ['admin.loan-request-status-definitions.destroy']],
                    ],
                ],
            ],
        ],
        [
            'key' => 'customer_login_logs',
            'label' => 'گزارش ورود مشتریان',
            'routes' => ['admin.customer-login-logs.index'],
        ],
        [
            'key' => 'reports',
            'label' => 'گزارش‌ها',
            'children' => [
                ['key' => 'reports.view', 'label' => 'دسترسی به صفحه گزارش‌ها', 'routes' => ['admin.reports.index']],
                ['key' => 'reports.member_loans', 'label' => 'گزارش وام‌های اعضا', 'routes' => [
                    'admin.reports.member-loans-by-date.data', 'admin.reports.member-loans-by-date.export-excel',
                ]],
                ['key' => 'reports.installment_due', 'label' => 'گزارش سررسید اقساط', 'routes' => [
                    'admin.reports.installment-due-by-date.data', 'admin.reports.installment-due-by-date.export-excel',
                ]],
                ['key' => 'reports.deposits', 'label' => 'گزارش واریزها', 'routes' => [
                    'admin.reports.deposits-by-date.data', 'admin.reports.deposits-by-date.export-excel',
                ]],
                ['key' => 'reports.settled_members', 'label' => 'گزارش اعضای تسویه‌شده', 'routes' => [
                    'admin.reports.settled-members.data', 'admin.reports.settled-members.export-excel',
                ]],
                ['key' => 'reports.wallet_transactions', 'label' => 'گزارش تراکنش کیف پول', 'routes' => [
                    'admin.reports.wallet-transactions-by-date.data', 'admin.reports.wallet-transactions-by-date.export-excel',
                ]],
                ['key' => 'reports.loan_guarantees', 'label' => 'گزارش تضامین', 'routes' => [
                    'admin.reports.loan-guarantees.data', 'admin.reports.loan-guarantees.export-excel',
                ]],
                ['key' => 'reports.loan_interest_fees', 'label' => 'گزارش بهره و کارمزد وام', 'routes' => [
                    'admin.reports.loan-interest-fees.data',
                    'admin.reports.loan-interest-fees.customers-search',
                    'admin.reports.loan-interest-fees.export-excel',
                ]],
                ['key' => 'reports.admin_activity', 'label' => 'گزارش فعالیت ادمین‌ها', 'routes' => [
                    'admin.reports.admin-activity.data',
                    'admin.reports.admin-activity.admins-search',
                    'admin.reports.admin-activity.export-excel',
                ]],
            ],
        ],
        [
            'key' => 'organizations',
            'label' => 'سازمان‌ها',
            'children' => [
                ['key' => 'organizations.view', 'label' => 'مشاهده', 'routes' => ['admin.organizations.index']],
                ['key' => 'organizations.create', 'label' => 'افزودن', 'routes' => ['admin.organizations.store']],
                ['key' => 'organizations.update', 'label' => 'ویرایش', 'routes' => ['admin.organizations.update']],
                ['key' => 'organizations.delete', 'label' => 'حذف', 'routes' => ['admin.organizations.destroy']],
            ],
        ],
        [
            'key' => 'users',
            'label' => 'کاربران ادمین',
            'children' => [
                ['key' => 'users.view', 'label' => 'مشاهده کاربران', 'routes' => ['admin.users.index']],
                ['key' => 'users.create', 'label' => 'افزودن کاربر', 'routes' => ['admin.users.store']],
                ['key' => 'users.update', 'label' => 'ویرایش اطلاعات کاربر', 'routes' => ['admin.users.update']],
                ['key' => 'users.delete', 'label' => 'حذف کاربر', 'routes' => ['admin.users.destroy']],
                ['key' => 'users.permissions', 'label' => 'مدیریت دسترسی‌ها', 'routes' => []],
            ],
        ],
        [
            'key' => 'app_settings',
            'label' => 'تنظیمات برنامه',
            'children' => [
                ['key' => 'app_settings.base', 'label' => 'اطلاعات پایه', 'routes' => ['admin.app-settings.base.update']],
                ['key' => 'app_settings.ui', 'label' => 'ظاهر و نمایش', 'routes' => [
                    'admin.app-settings.ui.update',
                    'admin.app-settings.login-background.preference.update',
                    'admin.app-settings.login-background.upload',
                    'admin.app-settings.login-background.destroy',
                ]],
                ['key' => 'app_settings.notifications', 'label' => 'اعلان‌ها', 'routes' => []],
                ['key' => 'app_settings.financial', 'label' => 'مالی و بانکی', 'routes' => ['admin.app-settings.financial.update']],
                ['key' => 'app_settings.security', 'label' => 'امنیت', 'routes' => [
                    'admin.app-settings.security.update',
                    'admin.app-settings.login-blocks.index',
                    'admin.app-settings.login-blocks.unblock',
                ]],
                ['key' => 'app_settings.loans', 'label' => 'وام‌ها', 'routes' => ['admin.app-settings.loans.update']],
                ['key' => 'app_settings.reports', 'label' => 'گزارش‌ها', 'routes' => ['admin.app-settings.reports.update']],
                ['key' => 'app_settings.print', 'label' => 'تنظیمات چاپ', 'routes' => ['admin.app-settings.print.update']],
                [
                    'key' => 'backup',
                    'label' => 'پشتیبان‌گیری و بازیابی',
                    'children' => [
                        ['key' => 'backup.view', 'label' => 'مشاهده لیست بکاپ‌ها', 'routes' => ['admin.backups.index']],
                        ['key' => 'backup.create', 'label' => 'ایجاد بکاپ', 'routes' => ['admin.backups.index', 'admin.backups.store']],
                        ['key' => 'backup.download', 'label' => 'دانلود بکاپ', 'routes' => ['admin.backups.download']],
                        ['key' => 'backup.delete', 'label' => 'حذف بکاپ', 'routes' => ['admin.backups.destroy']],
                        ['key' => 'backup.restore', 'label' => 'بازگردانی بکاپ', 'routes' => ['admin.backups.restore']],
                    ],
                ],
            ],
        ],
        [
            'key' => 'notifications',
            'label' => 'اعلان‌های سیستم',
            'children' => [
                ['key' => 'notifications.view', 'label' => 'مشاهده و پیگیری', 'routes' => ['admin.notifications.follow']],
                ['key' => 'notifications.mark_read', 'label' => 'علامت‌خوانده‌شده', 'routes' => ['admin.notifications.mark-all-read']],
            ],
        ],
    ],

    /*
    | صفحات مرکزی (hub): تب‌ها و پنل‌های UI از روی همان کلیدهای درخت دسترسی کنترل می‌شوند.
    */
    'section_hubs' => [
        'sms' => [
            'route' => 'admin.sms.index',
            'permission_prefix' => 'sms.',
            'tabs' => [
                'reports' => [
                    'label' => 'گزارش پیامک‌ها',
                    'permissions' => ['sms.reports'],
                ],
                'templates' => [
                    'label' => 'الگوهای پیامک',
                    'any_prefix' => 'sms.templates.',
                ],
                'settings' => [
                    'label' => 'تنظیمات پنل',
                    'any_prefix' => 'sms.settings.',
                ],
            ],
            'features' => [
                'reports.export' => ['permissions' => ['sms.reports']],
                'reports.destroy' => ['permissions' => ['sms.reports']],
                'templates.view' => ['permissions' => ['sms.templates.view']],
                'templates.create' => ['permissions' => ['sms.templates.create']],
                'templates.update' => ['permissions' => ['sms.templates.update']],
                'templates.delete' => ['permissions' => ['sms.templates.delete']],
                'settings.panel' => ['permissions' => ['sms.settings.panel']],
                'settings.scenarios' => ['permissions' => ['sms.settings.scenarios']],
                'settings.reminders' => ['permissions' => ['sms.settings.reminders']],
                'settings.messages' => ['permissions' => ['sms.settings.messages']],
            ],
        ],
        'reports' => [
            'route' => 'admin.reports.index',
            'permission_prefix' => 'reports.',
            'cards' => [
                'member-loans-by-date' => ['permissions' => ['reports.member_loans']],
                'installment-due-by-date' => ['permissions' => ['reports.installment_due']],
                'deposits-by-date' => ['permissions' => ['reports.deposits']],
                'settled-members' => ['permissions' => ['reports.settled_members']],
                'wallet-transactions-by-date' => ['permissions' => ['reports.wallet_transactions']],
                'loan-guarantees' => ['permissions' => ['reports.loan_guarantees']],
                'loan-interest-fees' => ['permissions' => ['reports.loan_interest_fees']],
                'admin-activity' => ['permissions' => ['reports.admin_activity']],
            ],
        ],
        'app_settings' => [
            'permission_prefix' => 'app_settings.',
            'panels' => [
                'base' => ['label' => 'تنظیمات بنیان', 'permissions' => ['app_settings.base']],
                'ui' => ['label' => 'ظاهر و تجربه کاربری', 'permissions' => ['app_settings.ui']],
                'notifications' => ['label' => 'اعلان‌ها', 'permissions' => ['app_settings.notifications']],
                'financial' => ['label' => 'تنظیمات مالی', 'permissions' => ['app_settings.financial']],
                'security' => ['label' => 'امنیت', 'permissions' => ['app_settings.security']],
                'loans' => ['label' => 'وام‌ها', 'permissions' => ['app_settings.loans']],
                'reports' => ['label' => 'گزارش‌ها', 'permissions' => ['app_settings.reports']],
                'print' => ['label' => 'تنظیمات چاپ', 'permissions' => ['app_settings.print']],
            ],
        ],
    ],

    'nav' => [
        ['label' => 'داشبورد', 'href' => 'admin.dashboard', 'icon' => 'fa-gauge-high', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view'],
        ['label' => 'تعریف انواع وام', 'href' => 'admin.loan-types.index', 'icon' => 'fa-money-bill-transfer', 'route' => 'admin.loan-types.index', 'permission' => 'loan_types.view'],
        ['label' => 'لیست مشتریان', 'href' => 'admin.customers.index', 'icon' => 'fa-users', 'route' => 'admin.customers.index', 'permission' => 'customers.view'],
        ['label' => 'اعلام واریزها', 'href' => 'admin.deposit-declarations.index', 'icon' => 'fa-building-columns', 'route' => 'admin.deposit-declarations.index', 'permission' => 'deposit_declarations.view'],
        ['label' => 'تراکنش‌ها', 'href' => 'admin.customer-transactions.index', 'icon' => 'fa-receipt', 'route' => 'admin.customer-transactions.index', 'permission' => 'customer_transactions.view'],
        ['label' => 'تیکت‌ها', 'href' => 'admin.tickets.index', 'icon' => 'fa-ticket', 'route' => 'admin.tickets.index', 'permission' => 'tickets.view'],
        ['label' => 'تیکت داخلی', 'href' => 'admin.internal-tickets.index', 'icon' => 'fa-comments', 'route' => 'admin.internal-tickets.index', 'permission' => 'internal_tickets.view'],
        ['label' => 'مدیریت پیامک', 'href' => 'admin.sms.index', 'icon' => 'fa-envelope', 'route' => 'admin.sms.index', 'permission' => 'sms.reports'],
        ['label' => 'درخواست وام‌ها', 'href' => 'admin.loan-requests.index', 'icon' => 'fa-file-invoice', 'route' => 'admin.loan-requests.index', 'permission' => 'loan_requests.view'],
        ['label' => 'گزارش ورود', 'href' => 'admin.customer-login-logs.index', 'icon' => 'fa-right-to-bracket', 'route' => 'admin.customer-login-logs.index', 'permission' => 'customer_login_logs'],
        ['label' => 'گزارش‌ها', 'href' => 'admin.reports.index', 'icon' => 'fa-chart-column', 'route' => 'admin.reports.index', 'permission' => 'reports.view'],
        ['label' => 'کاربران', 'href' => 'admin.users.index', 'icon' => 'fa-user-group', 'route' => 'admin.users.index', 'permission' => 'users.view'],
    ],
];
