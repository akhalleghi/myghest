<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\AdminLayoutThemeSettings;
use App\Support\AdminReportsDisplaySettings;
use App\Support\BankingHtmlSanitizer;
use App\Models\LoginAccessBlock;
use App\Services\Auth\LoginAccessBlockService;
use App\Support\AdminLoginSecuritySettings;
use App\Support\CustomerLoginSecuritySettings;
use App\Support\GuaranteeReturnOtpSettings;
use App\Support\LoanCreationOtpSettings;
use App\Support\InstallmentBookletPrintSettings;
use App\Support\LoanInstallmentRoundingSettings;
use App\Support\PortalLoginSecuritySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AppSettingsController extends Controller
{
    public function __construct(
        private readonly LoginAccessBlockService $loginAccessBlocks,
    ) {}

    public function updateBase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
        ], [], [
            'display_name' => 'نام نمایشی سامانه',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_display_name'],
            ['value' => trim((string) $validated['display_name'])]
        );

        return back()->with('flash_success', 'نام نمایشی سامانه با موفقیت ذخیره شد.');
    }

    public function updateUi(Request $request): RedirectResponse
    {
        if ($request->boolean('reset_admin_layout_theme')) {
            AdminLayoutThemeSettings::persist(AdminLayoutThemeSettings::defaults());

            return back()
                ->with('flash_success', 'رنگ‌های چیدمان به حالت اولیه بازگردانده شد.')
                ->with('open_app_settings_tab', 'ui');
        }

        $validated = $request->validate([
            'font_size' => ['required', 'in:small,normal,large,xlarge'],
            'ui_font' => ['required', 'in:iransans,iranyekan,anjoman,estedad'],
            'app_icon_fa' => ['nullable', 'string', 'max:80', 'regex:/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/'],
            'favicon_fa' => ['nullable', 'string', 'max:80', 'regex:/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/'],
            'app_icon' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'],
            'app_logo' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg,ico', 'max:1024'],
            'remove_app_icon' => ['nullable', 'boolean'],
            'remove_app_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [], [
            'font_size' => 'اندازه فونت',
            'ui_font' => 'فونت',
            'app_icon_fa' => 'آیکون Font Awesome',
            'favicon_fa' => 'فاوآیکون Font Awesome',
            'app_icon' => 'آیکون برنامه',
            'app_logo' => 'لوگوی سامانه',
            'favicon' => 'فاوآیکون',
            'remove_app_icon' => 'حذف آیکون برنامه',
            'remove_app_logo' => 'حذف لوگوی سامانه',
            'remove_favicon' => 'حذف فاوآیکون',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_font_size'],
            ['value' => $validated['font_size']]
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_ui_font'],
            ['value' => $validated['ui_font']]
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'app_icon_fa'],
            ['value' => trim((string) ($validated['app_icon_fa'] ?? ''))]
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'favicon_fa'],
            ['value' => trim((string) ($validated['favicon_fa'] ?? ''))]
        );

        if ($request->boolean('remove_app_icon')) {
            $old = AppSetting::query()->where('key', 'app_icon_path')->value('value');
            if (is_string($old) && $old !== '') {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_icon_path'], ['value' => '']);
        } elseif ($request->file('app_icon') instanceof UploadedFile) {
            $old = AppSetting::query()->where('key', 'app_icon_path')->value('value');
            $path = $this->storePublicAsset($request->file('app_icon'), 'app-icon');
            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_icon_path'], ['value' => $path]);
        }

        if ($request->boolean('remove_app_logo')) {
            $old = AppSetting::query()->where('key', 'app_logo_path')->value('value');
            if (is_string($old) && $old !== '') {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_logo_path'], ['value' => '']);
        } elseif ($request->file('app_logo') instanceof UploadedFile) {
            $old = AppSetting::query()->where('key', 'app_logo_path')->value('value');
            $path = $this->storePublicAsset($request->file('app_logo'), 'app-logo');
            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_logo_path'], ['value' => $path]);
        }

        if ($request->boolean('remove_favicon')) {
            $old = AppSetting::query()->where('key', 'favicon_path')->value('value');
            if (is_string($old) && $old !== '') {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'favicon_path'], ['value' => '']);
        } elseif ($request->file('favicon') instanceof UploadedFile) {
            $old = AppSetting::query()->where('key', 'favicon_path')->value('value');
            $path = $this->storePublicAsset($request->file('favicon'), 'favicon');
            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'favicon_path'], ['value' => $path]);
        }

        $themeInput = $request->input('admin_layout_theme');
        if (is_array($themeInput)) {
            AdminLayoutThemeSettings::persist($themeInput);
        }

        return back()
            ->with('flash_success', 'تنظیمات ظاهر با موفقیت ذخیره شد.')
            ->with('open_app_settings_tab', 'ui');
    }

    public function updateFinancial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_gateway' => ['required', 'string', 'in:zibal'],
            'zibal_merchant' => ['required', 'string', 'max:128'],
            'banking_info_show_in_user_panel' => ['required', 'string', 'in:0,1'],
            'banking_info_html' => ['nullable', 'string', 'max:65000'],
        ], [], [
            'payment_gateway' => 'درگاه پرداخت',
            'zibal_merchant' => 'شناسه مرچنت زیبال',
            'banking_info_show_in_user_panel' => 'نمایش اطلاعات بانکی در پنل کاربر',
            'banking_info_html' => 'توضیحات اطلاعات بانکی',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'payment_gateway'],
            ['value' => $validated['payment_gateway']]
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'zibal_merchant'],
            ['value' => trim((string) $validated['zibal_merchant'])]
        );

        $bankingHtml = BankingHtmlSanitizer::clean($validated['banking_info_html'] ?? null);
        AppSetting::query()->updateOrCreate(
            ['key' => 'banking_info_html'],
            ['value' => $bankingHtml]
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'banking_info_show_in_user_panel'],
            ['value' => $validated['banking_info_show_in_user_panel'] === '1' ? '1' : '0']
        );

        AppSetting::query()->where('key', 'zibal_callback_url')->delete();

        return back()
            ->with('flash_success', 'تنظیمات مالی ذخیره شد.')
            ->with('open_app_settings_tab', 'financial');
    }

    public function updateLoans(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_creation_customer_otp_enabled' => ['required', 'string', 'in:0,1'],
            'guarantee_return_customer_otp_enabled' => ['required', 'string', 'in:0,1'],
            'loan_installment_remainder_target' => ['required', 'string', Rule::in(LoanInstallmentRoundingSettings::remainderTargetOptions())],
        ], [], [
            'loan_creation_customer_otp_enabled' => 'تایید پیامکی ایجاد وام',
            'guarantee_return_customer_otp_enabled' => 'تایید پیامکی عودت ضمانت',
            'loan_installment_remainder_target' => 'محل لحاظ مبلغ خرد اقساط',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => LoanCreationOtpSettings::SETTING_KEY],
            ['value' => $validated['loan_creation_customer_otp_enabled'] === '1' ? '1' : '0'],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => GuaranteeReturnOtpSettings::SETTING_KEY],
            ['value' => $validated['guarantee_return_customer_otp_enabled'] === '1' ? '1' : '0'],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => LoanInstallmentRoundingSettings::SETTING_KEY_REMAINDER_TARGET],
            ['value' => (string) $validated['loan_installment_remainder_target']],
        );

        return back()
            ->with('flash_success', 'تنظیمات وام‌ها ذخیره شد.')
            ->with('open_app_settings_tab', 'loans');
    }

    public function updateReports(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'font_scale' => ['required', 'string', Rule::in(['small', 'normal', 'large', 'xlarge'])],
            'text_align' => ['required', 'string', Rule::in(['right', 'center'])],
            'numeric_align' => ['required', 'string', Rule::in(['right', 'center'])],
            'header_mode' => ['required', 'string', Rule::in(['match', 'center', 'right'])],
            'stack_align' => ['required', 'string', Rule::in(['start', 'center'])],
            'cell_density' => ['required', 'string', Rule::in(['compact', 'normal', 'comfortable'])],
        ], [], [
            'font_scale' => 'اندازه فونت جدول گزارش',
            'text_align' => 'چینش متن معمولی',
            'numeric_align' => 'چینش مبالغ و اعداد',
            'header_mode' => 'چینش سرستون‌ها',
            'stack_align' => 'چینش سلول‌های چندخطی',
            'cell_density' => 'تراکم سلول‌ها',
        ]);

        AdminReportsDisplaySettings::persist($validated);

        return back()
            ->with('flash_success', 'تنظیمات گزارش‌ها ذخیره شد.')
            ->with('open_app_settings_tab', 'reports');
    }

    public function updatePrint(Request $request): RedirectResponse
    {
        $columnKeys = InstallmentBookletPrintSettings::orderedColumnKeys();
        $columnRules = [];
        $columnLabels = [];
        foreach ($columnKeys as $columnKey) {
            $columnRules['columns.'.$columnKey.'.show'] = ['required', 'string', 'in:0,1'];
            $columnRules['columns.'.$columnKey.'.label'] = ['required', 'string', 'max:80'];
            $columnLabels['columns.'.$columnKey.'.show'] = 'نمایش ستون '.$columnKey;
            $columnLabels['columns.'.$columnKey.'.label'] = 'عنوان ستون '.$columnKey;
        }

        $validated = $request->validate(array_merge([
            'title_main' => ['required', 'string', 'max:120'],
            'subtitle' => ['required', 'string', 'max:120'],
            'loan_amount_label' => ['required', 'string', 'max:120'],
            'show_logo' => ['required', 'string', 'in:0,1'],
            'use_app_logo' => ['required', 'string', 'in:0,1'],
            'show_loan_amount' => ['required', 'string', 'in:0,1'],
            'show_summary_table' => ['required', 'string', 'in:0,1'],
            'show_detail_table' => ['required', 'string', 'in:0,1'],
            'show_portal_block' => ['required', 'string', 'in:0,1'],
            'show_username' => ['required', 'string', 'in:0,1'],
            'show_password' => ['required', 'string', 'in:0,1'],
            'password_unavailable_text' => ['nullable', 'string', 'max:80'],
            'portal_intro_text' => ['required', 'string', 'max:300'],
            'username_label' => ['required', 'string', 'max:80'],
            'password_label' => ['required', 'string', 'max:80'],
            'show_signatures' => ['required', 'string', 'in:0,1'],
            'seller_signature_label' => ['required', 'string', 'max:120'],
            'buyer_signature_label' => ['required', 'string', 'max:120'],
            'font_scale' => ['required', 'string', Rule::in(['small', 'normal', 'large'])],
            'remove_print_logo' => ['nullable', 'boolean'],
            'print_logo' => ['nullable', 'image', 'max:4096'],
        ], $columnRules), [], array_merge([
            'title_main' => 'عنوان اصلی چاپ',
            'subtitle' => 'زیرعنوان چاپ',
            'loan_amount_label' => 'برچسب مبلغ وام',
            'show_logo' => 'نمایش لوگو',
            'use_app_logo' => 'استفاده از لوگوی سامانه',
            'show_loan_amount' => 'نمایش مبلغ وام',
            'show_summary_table' => 'نمایش جدول خلاصه',
            'show_detail_table' => 'نمایش جدول اقساط',
            'show_portal_block' => 'نمایش بلوک پنل کاربری',
            'show_username' => 'نمایش نام کاربری',
            'show_password' => 'نمایش رمز عبور',
            'password_unavailable_text' => 'متن جایگزین رمز',
            'portal_intro_text' => 'متن معرفی پنل',
            'username_label' => 'برچسب نام کاربری',
            'password_label' => 'برچسب رمز عبور',
            'show_signatures' => 'نمایش امضاها',
            'seller_signature_label' => 'برچسب امضای فروشنده',
            'buyer_signature_label' => 'برچسب امضای خریدار',
            'font_scale' => 'اندازه فونت چاپ',
            'print_logo' => 'لوگوی چاپ',
        ], $columnLabels));

        $current = InstallmentBookletPrintSettings::resolved();
        $logoPath = trim((string) ($current['logo_path'] ?? ''));

        if ($request->boolean('remove_print_logo')) {
            if ($logoPath !== '') {
                $this->deletePublicAsset($logoPath);
            }
            $logoPath = '';
        } elseif ($request->file('print_logo') instanceof UploadedFile) {
            if ($logoPath !== '') {
                $this->deletePublicAsset($logoPath);
            }
            $logoPath = $this->storePublicAsset($request->file('print_logo'), 'booklet-print-logo');
        }

        $validated['logo_path'] = $logoPath;
        InstallmentBookletPrintSettings::persist($validated);

        return back()
            ->with('flash_success', 'تنظیمات چاپ ذخیره شد.')
            ->with('open_app_settings_tab', 'print');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_login_two_factor_enabled' => ['required', 'string', 'in:0,1'],
            'admin_login_two_factor_enabled' => ['required', 'string', 'in:0,1'],
            'customer_login_session_lifetime_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'customer_login_max_failed_attempts' => ['required', 'integer', 'min:3', 'max:50'],
            'admin_login_session_lifetime_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'admin_login_max_failed_attempts' => ['required', 'integer', 'min:3', 'max:50'],
        ], [], [
            'customer_login_two_factor_enabled' => 'تأیید دو مرحله‌ای ورود مشتریان',
            'admin_login_two_factor_enabled' => 'تأیید دو مرحله‌ای ورود ادمین',
            'customer_login_session_lifetime_minutes' => 'زمان نشست فعال مشتری',
            'customer_login_max_failed_attempts' => 'تعداد تلاش ناموفق ورود مشتری',
            'admin_login_session_lifetime_minutes' => 'زمان نشست فعال ادمین',
            'admin_login_max_failed_attempts' => 'تعداد تلاش ناموفق ورود ادمین',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => CustomerLoginSecuritySettings::SETTING_KEY],
            ['value' => $validated['customer_login_two_factor_enabled'] === '1' ? '1' : '0'],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => AdminLoginSecuritySettings::SETTING_KEY],
            ['value' => $validated['admin_login_two_factor_enabled'] === '1' ? '1' : '0'],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => PortalLoginSecuritySettings::CUSTOMER_SESSION_LIFETIME_KEY],
            ['value' => (string) $validated['customer_login_session_lifetime_minutes']],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => PortalLoginSecuritySettings::CUSTOMER_MAX_FAILED_ATTEMPTS_KEY],
            ['value' => (string) $validated['customer_login_max_failed_attempts']],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => PortalLoginSecuritySettings::ADMIN_SESSION_LIFETIME_KEY],
            ['value' => (string) $validated['admin_login_session_lifetime_minutes']],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => PortalLoginSecuritySettings::ADMIN_MAX_FAILED_ATTEMPTS_KEY],
            ['value' => (string) $validated['admin_login_max_failed_attempts']],
        );

        return back()
            ->with('flash_success', 'تنظیمات امنیتی ذخیره شد.')
            ->with('open_app_settings_tab', 'security');
    }

    public function loginBlocks(Request $request): JsonResponse
    {
        $guard = $request->query('guard');
        if (! is_string($guard) || ! in_array($guard, [LoginAccessBlock::GUARD_ADMIN, LoginAccessBlock::GUARD_CUSTOMER], true)) {
            $guard = null;
        }

        return response()->json([
            'items' => $this->loginAccessBlocks->listActiveBlocks($guard)->values(),
        ]);
    }

    public function unblockLoginBlock(Request $request, LoginAccessBlock $block): JsonResponse
    {
        $admin = $request->user('admin');
        if ($admin === null) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        if (! $block->is_active) {
            return response()->json(['message' => 'این مسدودیت قبلاً رفع شده است.'], 422);
        }

        $this->loginAccessBlocks->unblock((int) $block->id, (int) $admin->id);

        return response()->json(['message' => 'مسدودیت با موفقیت رفع شد.']);
    }

    private function storePublicAsset(UploadedFile $file, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = $prefix.'-'.Str::lower(Str::random(18)).'.'.$extension;
        $targetDir = public_path('uploads/app-branding');
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file->move($targetDir, $safeName);

        return 'uploads/app-branding/'.$safeName;
    }

    private function deletePublicAsset(string $relativePath): void
    {
        $normalized = str_replace(['\\', '..'], ['/', ''], $relativePath);
        $full = public_path($normalized);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
