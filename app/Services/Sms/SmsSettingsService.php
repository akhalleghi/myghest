<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\AppSetting;

final class SmsSettingsService
{
    /**
     * @return array{
     *   tpl_installment_thanks_id:string,
     *   tpl_login_id:string,
     *   tpl_register_verify_code_id:string,
     *   tpl_register_welcome_id:string
     * }
     */
    public function scenarioTemplateIds(): array
    {
        return $this->readMap([
            'tpl_installment_thanks_id' => 'sms_tpl_installment_thanks_id',
            'tpl_login_id' => 'sms_tpl_login_id',
            'tpl_register_verify_code_id' => 'sms_tpl_register_verify_code_id',
            'tpl_register_welcome_id' => 'sms_tpl_register_welcome_id',
        ]);
    }

    /**
     * @param  array{
     *   tpl_installment_thanks_id:int|string|null,
     *   tpl_login_id:int|string|null,
     *   tpl_register_verify_code_id:int|string|null,
     *   tpl_register_welcome_id:int|string|null
     * }  $values
     */
    public function saveScenarioTemplateIds(array $values): void
    {
        $this->saveMap([
            'sms_tpl_installment_thanks_id' => $values['tpl_installment_thanks_id'] ?? null,
            'sms_tpl_login_id' => $values['tpl_login_id'] ?? null,
            'sms_tpl_register_verify_code_id' => $values['tpl_register_verify_code_id'] ?? null,
            'sms_tpl_register_welcome_id' => $values['tpl_register_welcome_id'] ?? null,
        ]);
    }

    /**
     * @return array{
     *   reminder_enabled:string,
     *   reminder_send_time:string,
     *   due_day_enabled:string,
     *   due_day_template_id:string,
     *   before_due_enabled:string,
     *   before_due_template_id:string,
     *   before_due_days:string,
     *   overdue_days_after:string,
     *   overdue_daily_until_paid:string,
     *   overdue_template_id:string
     * }
     */
    public function reminderSettings(): array
    {
        return $this->readMap([
            'reminder_enabled' => 'sms_reminder_enabled',
            'reminder_send_time' => 'sms_reminder_send_time',
            'due_day_enabled' => 'sms_reminder_due_day_enabled',
            'due_day_template_id' => 'sms_reminder_due_day_template_id',
            'before_due_enabled' => 'sms_reminder_before_due_enabled',
            'before_due_template_id' => 'sms_reminder_before_due_template_id',
            'before_due_days' => 'sms_reminder_before_due_days',
            'overdue_days_after' => 'sms_reminder_overdue_days_after',
            'overdue_daily_until_paid' => 'sms_reminder_overdue_daily_until_paid',
            'overdue_template_id' => 'sms_reminder_overdue_template_id',
        ]);
    }

    /**
     * @param  array{
     *   reminder_enabled:string|int|bool|null,
     *   reminder_send_time:string|null,
     *   due_day_enabled:string|int|bool|null,
     *   due_day_template_id:int|string|null,
     *   before_due_enabled:string|int|bool|null,
     *   before_due_template_id:int|string|null,
     *   before_due_days:int|string|null,
     *   overdue_days_after:int|string|null,
     *   overdue_daily_until_paid:string|int|bool|null,
     *   overdue_template_id:int|string|null
     * }  $values
     */
    public function reminderLastDispatchDate(): ?string
    {
        $value = AppSetting::query()->where('key', 'sms_reminder_last_dispatch_date')->value('value');

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public function markReminderDispatchedToday(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'sms_reminder_last_dispatch_date'],
            ['value' => now()->toDateString()]
        );
    }

    public function isSettingEnabled(string $value): bool
    {
        return in_array(trim($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array{
     *   enabled: string,
     *   recipient_ids: list<int>,
     *   message_template: string
     * }
     */
    public function adminLoginNotifySettings(): array
    {
        $enabled = trim((string) (AppSetting::query()->where('key', 'sms_admin_login_notify_enabled')->value('value') ?? ''));
        $template = AppSetting::query()->where('key', 'sms_admin_login_notify_message_template')->value('value');
        $idsRaw = AppSetting::query()->where('key', 'sms_admin_login_notify_recipient_ids')->value('value');

        return [
            'enabled' => $enabled,
            'recipient_ids' => $this->decodeAdminIdList($idsRaw),
            'message_template' => is_scalar($template) ? trim((string) $template) : '',
        ];
    }

    /**
     * @param  array{
     *   enabled?: string|int|bool|null,
     *   recipient_ids?: list<int|string>|null,
     *   message_template?: string|null
     * }  $values
     */
    public function saveAdminLoginNotifySettings(array $values): void
    {
        $ids = [];
        foreach ($values['recipient_ids'] ?? [] as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }

        $this->saveMap([
            'sms_admin_login_notify_enabled' => $values['enabled'] ?? '0',
            'sms_admin_login_notify_recipient_ids' => json_encode(array_values($ids), JSON_UNESCAPED_UNICODE),
            'sms_admin_login_notify_message_template' => $values['message_template'] ?? '',
        ]);
    }

    /**
     * @return array{enabled: string, message_template: string}
     */
    public function adminLoginSelfNotifySettings(): array
    {
        $enabled = trim((string) (AppSetting::query()->where('key', 'sms_admin_login_self_notify_enabled')->value('value') ?? ''));
        $template = AppSetting::query()->where('key', 'sms_admin_login_self_notify_message_template')->value('value');

        return [
            'enabled' => $enabled,
            'message_template' => is_scalar($template) ? trim((string) $template) : '',
        ];
    }

    /**
     * @param  array{enabled?: string|int|bool|null, message_template?: string|null}  $values
     */
    public function saveAdminLoginSelfNotifySettings(array $values): void
    {
        $this->saveMap([
            'sms_admin_login_self_notify_enabled' => $values['enabled'] ?? '0',
            'sms_admin_login_self_notify_message_template' => $values['message_template'] ?? '',
        ]);
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerLoginNotifyAdminSettings(): array
    {
        $enabled = trim((string) (AppSetting::query()->where('key', 'sms_customer_login_notify_admin_enabled')->value('value') ?? ''));
        $template = AppSetting::query()->where('key', 'sms_customer_login_notify_admin_message_template')->value('value');
        $idsRaw = AppSetting::query()->where('key', 'sms_customer_login_notify_admin_recipient_ids')->value('value');

        return [
            'enabled' => $enabled,
            'recipient_ids' => $this->decodeAdminIdList($idsRaw),
            'message_template' => is_scalar($template) ? trim((string) $template) : '',
        ];
    }

    /**
     * @param  array{
     *   enabled?: string|int|bool|null,
     *   recipient_ids?: list<int|string>|null,
     *   message_template?: string|null
     * }  $values
     */
    public function saveCustomerLoginNotifyAdminSettings(array $values): void
    {
        $ids = [];
        foreach ($values['recipient_ids'] ?? [] as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }

        $this->saveMap([
            'sms_customer_login_notify_admin_enabled' => $values['enabled'] ?? '0',
            'sms_customer_login_notify_admin_recipient_ids' => json_encode(array_values($ids), JSON_UNESCAPED_UNICODE),
            'sms_customer_login_notify_admin_message_template' => $values['message_template'] ?? '',
        ]);
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerInstallmentPaymentNotifyAdminSettings(): array
    {
        $enabled = trim((string) (AppSetting::query()->where('key', 'sms_customer_installment_payment_notify_admin_enabled')->value('value') ?? ''));
        $template = AppSetting::query()->where('key', 'sms_customer_installment_payment_notify_admin_message_template')->value('value');
        $idsRaw = AppSetting::query()->where('key', 'sms_customer_installment_payment_notify_admin_recipient_ids')->value('value');

        return [
            'enabled' => $enabled,
            'recipient_ids' => $this->decodeAdminIdList($idsRaw),
            'message_template' => is_scalar($template) ? trim((string) $template) : '',
        ];
    }

    /**
     * @param  array{
     *   enabled?: string|int|bool|null,
     *   recipient_ids?: list<int|string>|null,
     *   message_template?: string|null
     * }  $values
     */
    public function saveCustomerInstallmentPaymentNotifyAdminSettings(array $values): void
    {
        $ids = [];
        foreach ($values['recipient_ids'] ?? [] as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }

        $this->saveMap([
            'sms_customer_installment_payment_notify_admin_enabled' => $values['enabled'] ?? '0',
            'sms_customer_installment_payment_notify_admin_recipient_ids' => json_encode(array_values($ids), JSON_UNESCAPED_UNICODE),
            'sms_customer_installment_payment_notify_admin_message_template' => $values['message_template'] ?? '',
        ]);
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerFullSettlementNotifyAdminSettings(): array
    {
        return $this->readAdminRecipientNotifySettings(
            'sms_customer_full_settlement_notify_admin_enabled',
            'sms_customer_full_settlement_notify_admin_recipient_ids',
            'sms_customer_full_settlement_notify_admin_message_template',
        );
    }

    /**
     * @param  array{enabled?: string|int|bool|null, recipient_ids?: list<int|string>|null, message_template?: string|null}  $values
     */
    public function saveCustomerFullSettlementNotifyAdminSettings(array $values): void
    {
        $this->saveAdminRecipientNotifySettings(
            'sms_customer_full_settlement_notify_admin_enabled',
            'sms_customer_full_settlement_notify_admin_recipient_ids',
            'sms_customer_full_settlement_notify_admin_message_template',
            $values,
        );
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerDepositDeclarationNotifyAdminSettings(): array
    {
        return $this->readAdminRecipientNotifySettings(
            'sms_customer_deposit_declaration_notify_admin_enabled',
            'sms_customer_deposit_declaration_notify_admin_recipient_ids',
            'sms_customer_deposit_declaration_notify_admin_message_template',
        );
    }

    /**
     * @param  array{enabled?: string|int|bool|null, recipient_ids?: list<int|string>|null, message_template?: string|null}  $values
     */
    public function saveCustomerDepositDeclarationNotifyAdminSettings(array $values): void
    {
        $this->saveAdminRecipientNotifySettings(
            'sms_customer_deposit_declaration_notify_admin_enabled',
            'sms_customer_deposit_declaration_notify_admin_recipient_ids',
            'sms_customer_deposit_declaration_notify_admin_message_template',
            $values,
        );
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerSupportTicketNotifyAdminSettings(): array
    {
        return $this->readAdminRecipientNotifySettings(
            'sms_customer_support_ticket_notify_admin_enabled',
            'sms_customer_support_ticket_notify_admin_recipient_ids',
            'sms_customer_support_ticket_notify_admin_message_template',
        );
    }

    /**
     * @param  array{enabled?: string|int|bool|null, recipient_ids?: list<int|string>|null, message_template?: string|null}  $values
     */
    public function saveCustomerSupportTicketNotifyAdminSettings(array $values): void
    {
        $this->saveAdminRecipientNotifySettings(
            'sms_customer_support_ticket_notify_admin_enabled',
            'sms_customer_support_ticket_notify_admin_recipient_ids',
            'sms_customer_support_ticket_notify_admin_message_template',
            $values,
        );
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    public function customerLoanRequestNotifyAdminSettings(): array
    {
        return $this->readAdminRecipientNotifySettings(
            'sms_customer_loan_request_notify_admin_enabled',
            'sms_customer_loan_request_notify_admin_recipient_ids',
            'sms_customer_loan_request_notify_admin_message_template',
        );
    }

    /**
     * @param  array{enabled?: string|int|bool|null, recipient_ids?: list<int|string>|null, message_template?: string|null}  $values
     */
    public function saveCustomerLoanRequestNotifyAdminSettings(array $values): void
    {
        $this->saveAdminRecipientNotifySettings(
            'sms_customer_loan_request_notify_admin_enabled',
            'sms_customer_loan_request_notify_admin_recipient_ids',
            'sms_customer_loan_request_notify_admin_message_template',
            $values,
        );
    }

    /**
     * @return array{enabled: string, recipient_ids: list<int>, message_template: string}
     */
    private function readAdminRecipientNotifySettings(string $enabledKey, string $idsKey, string $templateKey): array
    {
        $enabled = trim((string) (AppSetting::query()->where('key', $enabledKey)->value('value') ?? ''));
        $template = AppSetting::query()->where('key', $templateKey)->value('value');
        $idsRaw = AppSetting::query()->where('key', $idsKey)->value('value');

        return [
            'enabled' => $enabled,
            'recipient_ids' => $this->decodeAdminIdList($idsRaw),
            'message_template' => is_scalar($template) ? trim((string) $template) : '',
        ];
    }

    /**
     * @param  array{enabled?: string|int|bool|null, recipient_ids?: list<int|string>|null, message_template?: string|null}  $values
     */
    private function saveAdminRecipientNotifySettings(
        string $enabledKey,
        string $idsKey,
        string $templateKey,
        array $values,
    ): void {
        $ids = [];
        foreach ($values['recipient_ids'] ?? [] as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }

        $this->saveMap([
            $enabledKey => $values['enabled'] ?? '0',
            $idsKey => json_encode(array_values($ids), JSON_UNESCAPED_UNICODE),
            $templateKey => $values['message_template'] ?? '',
        ]);
    }

    /**
     * @return list<int>
     */
    private function decodeAdminIdList(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode(trim($raw), true);
        if (! is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }

        return array_values($ids);
    }

    public function saveReminderSettings(array $values): void
    {
        $this->saveMap([
            'sms_reminder_enabled' => $values['reminder_enabled'] ?? '0',
            'sms_reminder_send_time' => $values['reminder_send_time'] ?? '',
            'sms_reminder_due_day_enabled' => $values['due_day_enabled'] ?? '0',
            'sms_reminder_due_day_template_id' => $values['due_day_template_id'] ?? null,
            'sms_reminder_before_due_enabled' => $values['before_due_enabled'] ?? '0',
            'sms_reminder_before_due_template_id' => $values['before_due_template_id'] ?? null,
            'sms_reminder_before_due_days' => $values['before_due_days'] ?? null,
            'sms_reminder_overdue_days_after' => $values['overdue_days_after'] ?? null,
            'sms_reminder_overdue_daily_until_paid' => $values['overdue_daily_until_paid'] ?? '0',
            'sms_reminder_overdue_template_id' => $values['overdue_template_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function readMap(array $map): array
    {
        $out = [];
        foreach ($map as $field => $settingKey) {
            $value = AppSetting::query()->where('key', $settingKey)->value('value');
            $out[$field] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $out;
    }

    /**
     * @param  array<string, int|string|bool|null>  $pairs
     */
    private function saveMap(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $normalized = is_bool($value)
                ? ($value ? '1' : '0')
                : trim((string) ($value ?? ''));
            AppSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $normalized]
            );
        }
    }
}

