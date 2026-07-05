<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\AdminPermissionRegistry;

/**
 * تبدیل نام route به عنوان فارسی قابل‌فهم برای ادمین.
 */
final class AdminActivityRouteLabelResolver
{
    /** @var array<string, string>|null */
    private static ?array $routeLabels = null;

    /** @var array<string, string> */
    private const SEGMENTS = [
        'backups' => 'بکاپ‌ها',
        'backup' => 'بکاپ',
        'customers' => 'مشتریان',
        'customer' => 'مشتری',
        'loan-types' => 'انواع وام',
        'loan-type' => 'نوع وام',
        'loan-requests' => 'درخواست‌های وام',
        'loan-request' => 'درخواست وام',
        'loan-request-status-definitions' => 'وضعیت‌های درخواست وام',
        'loan-files' => 'پرونده وام',
        'loan-file' => 'پرونده وام',
        'installments' => 'اقساط',
        'installment' => 'قسط',
        'payments' => 'پرداخت‌های قسط',
        'payment' => 'پرداخت قسط',
        'guarantees' => 'تضامین',
        'guarantee' => 'ضمانت',
        'organizations' => 'سازمان‌ها',
        'organization' => 'سازمان',
        'users' => 'کاربران ادمین',
        'user' => 'کاربر ادمین',
        'sms' => 'مدیریت پیامک',
        'reports' => 'گزارش‌ها',
        'report' => 'گزارش',
        'dashboard' => 'داشبورد',
        'tickets' => 'تیکت‌ها',
        'ticket' => 'تیکت',
        'deposit-declarations' => 'اعلام واریزها',
        'deposit-declaration' => 'اعلام واریز',
        'customer-transactions' => 'تراکنش‌های مشتری',
        'customer-transaction' => 'تراکنش مشتری',
        'customer-login-logs' => 'گزارش ورود مشتریان',
        'app-settings' => 'تنظیمات برنامه',
        'notifications' => 'اعلان‌های سیستم',
        'notification' => 'اعلان',
        'documents' => 'مدارک',
        'document' => 'مدرک',
        'wallet' => 'کیف پول',
        'templates' => 'الگوهای پیامک',
        'template' => 'الگوی پیامک',
        'guarantor-otp' => 'تأیید OTP ضامن',
        'login-blocks' => 'مسدودسازی ورود',
        'login-background' => 'پس‌زمینه ورود',
        'status-logs' => 'گزارش تغییر وضعیت',
        'status-sms-logs' => 'پیامک‌های وضعیت',
        'member-loans-by-date' => 'گزارش وام‌های اعضا',
        'installment-due-by-date' => 'گزارش سررسید اقساط',
        'deposits-by-date' => 'گزارش واریزها',
        'settled-members' => 'گزارش اعضای تسویه‌شده',
        'wallet-transactions-by-date' => 'گزارش تراکنش کیف پول',
        'loan-guarantees' => 'گزارش تضامین',
        'loan-interest-fees' => 'گزارش بهره و کارمزد وام',
        'admin-activity' => 'گزارش فعالیت ادمین‌ها',
        'sms-logs' => 'گزارش پیامک‌های مشتری',
        'quick-sms' => 'ارسال پیامک سریع',
        'sms-modal-preview' => 'پیش‌نمایش پیامک',
        'loan-creation-otp' => 'OTP ایجاد وام',
        'loan-manage-modal-context' => 'مدیریت وام مشتری',
        'loan-board-summary' => 'خلاصه وام‌های مشتری',
        'edit-data' => 'اطلاعات ویرایش',
        'edit-context' => 'اطلاعات ویرایش',
        'convert-preview' => 'پیش‌نمایش تبدیل به پرونده',
        'convert' => 'تبدیل به پرونده وام',
        'revoke-contract' => 'ابطال قرارداد',
        'installment-booklet' => 'دفترچه اقساط',
        'send-sms' => 'ارسال پیامک',
        'settlement' => 'تسویه وام',
        'adjust' => 'تعدیل موجودی',
        'lock' => 'قفل کیف پول',
        'attachment' => 'پیوست',
        'review' => 'بررسی',
        'reply' => 'پاسخ',
        'status' => 'وضعیت',
        'print' => 'چاپ',
        'restore' => 'بازگردانی',
        'download' => 'دانلود',
        'resend' => 'ارسال مجدد',
        'verify' => 'تأیید',
        'send' => 'ارسال',
        'unblock' => 'رفع مسدودیت',
        'mark-all-read' => 'علامت‌خوانده‌شده همه',
        'follow' => 'پیگیری اعلان',
        'plan-image' => 'تصویر طرح وام',
        'reminder-settings' => 'تنظیمات یادآوری پیامک',
        'admin-login-notify' => 'اعلان ورود مدیران',
        'admin-login-self-notify' => 'اعلان ورود خود مدیر',
        'customer-login-notify' => 'اعلان ورود مشتری',
        'customer-installment-payment-notify' => 'اعلان پرداخت قسط مشتری',
        'portal-admin-notify' => 'اعلان مدیر پرتال',
        'scenario-templates' => 'قالب‌های سناریوی پیامک',
        'panel-settings' => 'تنظیمات پنل پیامک',
        'panel-test' => 'تست پنل پیامک',
    ];

    /** @var array<string, string> */
    private const ACTIONS = [
        'index' => 'مشاهده',
        'list' => 'مشاهده فهرست',
        'show' => 'مشاهده جزئیات',
        'store' => 'ثبت',
        'update' => 'ویرایش',
        'destroy' => 'حذف',
        'export' => 'خروجی',
        'export-excel' => 'خروجی اکسل',
        'import-excel' => 'ورود اکسل',
        'import' => 'ورود',
        'sample-excel' => 'نمونه فایل اکسل',
        'data' => 'دریافت داده',
        'customers-search' => 'جستجوی مشتری',
        'admins-search' => 'جستجوی ادمین',
        'embed' => 'مشاهده بخش',
        'file' => 'دریافت فایل',
        'preference' => 'تنظیم',
        'upload' => 'بارگذاری',
    ];

    /** @var array<string, string> */
    private const MANUAL = [
        'admin.sms.reminder-settings.update' => 'ذخیره تنظیمات یادآوری پیامک',
        'admin.sms.scenario-templates.update' => 'ذخیره قالب‌های سناریوی پیامک',
        'admin.sms.panel-settings.update' => 'ذخیره تنظیمات پنل پیامک',
        'admin.sms.panel-test.send' => 'ارسال پیامک تست پنل',
        'admin.sms.templates.store' => 'افزودن الگوی پیامک',
        'admin.sms.templates.update' => 'ویرایش الگوی پیامک',
        'admin.sms.templates.destroy' => 'حذف الگوی پیامک',
        'admin.app-settings.base.update' => 'ذخیره اطلاعات پایه برنامه',
        'admin.app-settings.ui.update' => 'ذخیره تنظیمات ظاهر',
        'admin.app-settings.financial.update' => 'ذخیره تنظیمات مالی',
        'admin.app-settings.security.update' => 'ذخیره تنظیمات امنیت',
        'admin.app-settings.loans.update' => 'ذخیره تنظیمات وام',
        'admin.app-settings.reports.update' => 'ذخیره تنظیمات نمایش گزارش‌ها',
    ];

    public function resolve(?string $routeName): ?string
    {
        if ($routeName === null || trim($routeName) === '') {
            return null;
        }

        $map = $this->routeLabels();
        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        if (isset(self::MANUAL[$routeName])) {
            return self::MANUAL[$routeName];
        }

        return $this->composeFromRouteName($routeName);
    }

    /**
     * @return array<string, string>
     */
    private function routeLabels(): array
    {
        if (self::$routeLabels !== null) {
            return self::$routeLabels;
        }

        $map = [];

        $walk = function (array $nodes) use (&$walk, &$map): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $label = trim((string) ($node['label'] ?? ''));
                $routes = $node['routes'] ?? [];
                if ($label !== '' && is_array($routes)) {
                    foreach ($routes as $routeName) {
                        if (! is_string($routeName) || $routeName === '') {
                            continue;
                        }
                        if (! isset($map[$routeName])) {
                            $map[$routeName] = $label;
                        }
                    }
                }

                $children = $node['children'] ?? [];
                if (is_array($children) && $children !== []) {
                    $walk($children);
                }
            }
        };

        $walk(app(AdminPermissionRegistry::class)->tree());

        foreach (config('admin_permissions.nav', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $route = trim((string) ($row['route'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($route !== '' && $label !== '') {
                $map[$route] = $label;
            }
        }

        foreach (self::MANUAL as $route => $label) {
            $map[$route] ??= $label;
        }

        self::$routeLabels = $map;

        return $map;
    }

    private function composeFromRouteName(string $routeName): string
    {
        $normalized = preg_replace('/^admin\./', '', $routeName) ?? $routeName;
        $parts = array_values(array_filter(explode('.', $normalized), static fn (string $p): bool => $p !== ''));

        if ($parts === []) {
            return $routeName;
        }

        $action = array_pop($parts);
        $actionFa = self::ACTIONS[$action] ?? $this->translateSegment($action);

        $resourceParts = array_map(fn (string $part): string => $this->translateSegment($part), $parts);
        $resourceText = implode(' — ', array_values(array_filter($resourceParts, static fn (string $s): bool => $s !== '')));

        if ($resourceText === '') {
            return $actionFa;
        }

        if (in_array($action, ['index', 'list', 'embed', 'edit-data', 'edit-context', 'data'], true)) {
            return $actionFa.' '.$resourceText;
        }

        return $actionFa.' '.$resourceText;
    }

    private function translateSegment(string $segment): string
    {
        $segment = strtolower(trim($segment));
        if ($segment === '') {
            return '';
        }

        if (isset(self::SEGMENTS[$segment])) {
            return self::SEGMENTS[$segment];
        }

        $normalized = str_replace(['-', '_'], ' ', $segment);

        return trim($normalized);
    }
}
