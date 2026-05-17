<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Admin;
use App\Services\Sms\SmsSettingsService;
use App\Support\IranMobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait UpdatesPortalAdminRecipientSmsSettings
{
  /**
   * @param  callable(): array{enabled: string, recipient_ids: list<int>, message_template: string}  $readSettings
   * @param  callable(array{enabled: string, recipient_ids: list<int>, message_template: string}): void  $saveSettings
   * @param  callable(): string  $defaultTemplate
   */
    protected function updatePortalAdminRecipientSmsSettings(
        Request $request,
        SmsSettingsService $smsSettings,
        string $enabledField,
        string $recipientField,
        string $messageField,
        string $enabledLabel,
        callable $readSettings,
        callable $saveSettings,
        callable $defaultTemplate,
        string $successMessage,
    ): RedirectResponse {
        $enabled = $request->boolean($enabledField);

        $validated = $request->validate([
            $enabledField => ['nullable', 'boolean'],
            $recipientField => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'array',
                'min:1',
            ],
            $recipientField.'.*' => ['integer', 'exists:admins,id'],
            $messageField => [
                Rule::requiredIf(fn (): bool => $enabled),
                'nullable',
                'string',
                'max:500',
            ],
        ], [], [
            $enabledField => $enabledLabel,
            $recipientField => 'دریافت‌کنندگان',
            $recipientField.'.*' => 'شناسه ادمین',
            $messageField => 'متن پیامک',
        ]);

        $recipientIds = [];
        if ($enabled) {
            $rawIds = $validated[$recipientField] ?? [];
            $activeAdmins = Admin::query()
                ->whereIn('id', $rawIds)
                ->where('is_active', true)
                ->get(['id', 'mobile']);

            $recipientIds = $activeAdmins
                ->map(static fn (Admin $admin): int => (int) $admin->id)
                ->values()
                ->all();

            $hasDeliverableMobile = $activeAdmins->contains(
                static fn (Admin $admin): bool => IranMobile::isValid((string) ($admin->mobile ?? ''))
            );

            if ($recipientIds === [] || ! $hasDeliverableMobile) {
                return back()
                    ->withInput()
                    ->withErrors([$recipientField => 'حداقل یک ادمین فعال با شماره موبایل معتبر انتخاب کنید.'])
                    ->with('sms_active_tab', 'settings');
            }
        }

        $message = strip_tags(trim((string) ($validated[$messageField] ?? '')));
        if ($enabled && $message === '') {
            return back()
                ->withInput()
                ->withErrors([$messageField => 'متن پیامک را وارد کنید.'])
                ->with('sms_active_tab', 'settings');
        }

        if (! $enabled) {
            $existing = $readSettings();
            $recipientIds = $existing['recipient_ids'];
            $message = $existing['message_template'] !== ''
                ? $existing['message_template']
                : $defaultTemplate();
        }

        $saveSettings([
            'enabled' => $enabled ? '1' : '0',
            'recipient_ids' => $recipientIds,
            'message_template' => $message,
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('flash_success', $successMessage)
            ->with('sms_active_tab', 'settings');
    }
}
