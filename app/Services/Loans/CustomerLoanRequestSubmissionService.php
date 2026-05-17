<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Admin;
use App\Services\Sms\PortalAdminSmsDispatcher;
use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\CustomerLoanRequestStatusLog;
use App\Models\LoanType;
use App\Notifications\LoanRequestSubmittedForAdmins;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CustomerLoanRequestSubmissionService
{
    private const MAX_ATTACHMENTS_TOTAL = 40;

    private const MAX_ROWS_PER_PRESET = 5;

    public function __construct(
        private readonly LoanWizardParameterValidator $wizardRules,
        private readonly LoanRequestStatusTransitionLogger $statusLogger,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submit(
        Customer $customer,
        LoanType $plan,
        int $amountToman,
        int $installmentsCount,
        int $installmentGap,
        string $installmentGapUnit,
        string $description,
        string $attachmentsMetaJson,
        array $files,
    ): CustomerLoanRequest {
        if (! $plan->plan_list_enabled || $plan->registration_suspended) {
            throw ValidationException::withMessages(['loan_type_id' => 'این طرح در حال حاضر برای ثبت درخواست فعال نیست.']);
        }

        $this->wizardRules->assertPlanAcceptsParameters(
            $plan,
            $amountToman,
            $installmentsCount,
            $installmentGap,
            $installmentGapUnit,
            $description,
        );

        $initialMeta = $this->initialDocumentsMetaByPresetKey($plan);

        $metaItems = $this->decodeInitialAttachmentMeta($attachmentsMetaJson, $files, $plan, []);

        $storedPaths = [];

        DB::beginTransaction();

        try {
            $request = CustomerLoanRequest::query()->create([
                'customer_id' => $customer->id,
                'loan_type_id' => $plan->id,
                'status' => CustomerLoanRequest::STATUS_INITIAL,
                'amount_toman' => $amountToman,
                'installments_count' => $installmentsCount,
                'installment_interval_count' => $installmentGap,
                'installment_interval_unit' => $installmentGapUnit,
                'profit_calculation_method' => (string) $plan->profit_calculation_method,
                'interest_rate' => $plan->interest_rate,
                'daily_late_coefficient' => $plan->daily_late_coefficient,
                'daily_early_coefficient' => $plan->daily_early_coefficient,
                'description' => trim($description),
                'submitted_at' => now(),
            ]);

            $folder = 'loan_requests/'.$customer->id.'/'.$request->id;

            foreach ($metaItems as $index => $item) {
                $file = $files[$index];
                $presetKey = $item['preset_key'];
                $title = $initialMeta[$presetKey]['title'] ?? $presetKey;

                $relativePath = Storage::disk('local')->putFile($folder, $file);
                if ($relativePath === false) {
                    throw ValidationException::withMessages(['files' => 'ذخیره‌سازی یکی از فایل‌ها انجام نشد. لطفاً دوباره تلاش کنید.']);
                }
                $storedPaths[] = $relativePath;

                CustomerLoanRequestDocument::query()->create([
                    'customer_loan_request_id' => $request->id,
                    'preset_key' => $presetKey,
                    'document_title' => $title,
                    'row_index' => (int) $item['row_index'],
                    'client_row_id' => $item['client_row_id'],
                    'description' => $item['description'],
                    'stored_path' => $relativePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                    'size_bytes' => (int) $file->getSize(),
                    'review_status' => CustomerLoanRequestDocument::REVIEW_SUBMITTED_BY_USER,
                    'expert_note' => null,
                ]);
            }

            DB::commit();

            $this->statusLogger->log(
                $request,
                null,
                CustomerLoanRequest::STATUS_INITIAL,
                CustomerLoanRequestStatusLog::ACTOR_CUSTOMER,
                null,
                null,
            );

            $this->notifyAdminsOfNewSubmission($request, $customer);
            PortalAdminSmsDispatcher::afterLoanRequest((int) $request->id);

            return $request->load(['loanType', 'documents']);
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($storedPaths as $p) {
                if ($p !== '') {
                    Storage::disk('local')->delete($p);
                }
            }
            throw $e;
        }
    }

    /**
     * @param  list<string>  $waivedPresetKeys
     * @return list<array{preset_key: string, row_index: int, client_row_id: int|null, description: string|null}>
     */
    public function decodeInitialAttachmentMeta(string $json, array $files, LoanType $plan, array $waivedPresetKeys): array
    {
        $initialMeta = $this->initialDocumentsMetaByPresetKey($plan);

        return $this->decodeAndValidateMeta($json, $files, array_keys($initialMeta), $waivedPresetKeys);
    }

    /**
     * @return array<string, array{title: string}>
     */
    public function initialDocumentsMetaByPresetKey(LoanType $plan): array
    {
        $rd = is_array($plan->required_documents) ? $plan->required_documents : [];
        $out = [];
        foreach ($rd as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['timing'] ?? '') !== LoanType::DOC_TIMING_INITIAL) {
                continue;
            }
            $key = isset($row['preset_key']) ? trim((string) $row['preset_key']) : '';
            if ($key === '') {
                $key = LoanType::REQUIRED_DOCUMENT_CUSTOM_PREFIX.'wizard_'.$idx;
            }
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                $title = LoanType::requiredDocumentDefaultTitle($key);
            }
            $out[$key] = ['title' => $title];
        }

        return $out;
    }

    /**
     * @param  list<string>  $requiredKeys
     * @param  list<string>  $waivedPresetKeys
     * @return list<array{preset_key: string, row_index: int, client_row_id: int|null, description: string|null}>
     */
    private function decodeAndValidateMeta(string $json, array $files, array $requiredKeys, array $waivedPresetKeys): array
    {
        $waivedPresetKeys = array_values(array_unique(array_map(static fn (string $k): string => trim($k), $waivedPresetKeys)));
        $requiredKeys = array_values(array_diff($requiredKeys, $waivedPresetKeys));

        if ($requiredKeys === []) {
            if ($files !== []) {
                throw ValidationException::withMessages(['files' => 'برای این طرح مدرک اولیه‌ای لازم نیست؛ ارسال فایل مجاز نیست.']);
            }

            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['attachments_meta' => 'ساختار اطلاعات پیوست‌ها نامعتبر است.']);
        }

        if (count($decoded) !== count($files)) {
            throw ValidationException::withMessages(['attachments_meta' => 'تعداد پیوست‌ها با فایل‌های ارسال‌شده هم‌خوان نیست.']);
        }

        if (count($files) > self::MAX_ATTACHMENTS_TOTAL) {
            throw ValidationException::withMessages(['files' => 'تعداد فایل‌های ارسالی بیش از حد مجاز است.']);
        }

        $allowedSet = array_fill_keys($requiredKeys, true);
        $counts = array_fill_keys($requiredKeys, 0);
        $out = [];

        foreach ($decoded as $i => $raw) {
            if (! is_array($raw)) {
                throw ValidationException::withMessages(['attachments_meta' => 'یکی از ردیف‌های اطلاعات پیوست نامعتبر است.']);
            }
            $presetKey = isset($raw['preset_key']) ? trim((string) $raw['preset_key']) : '';
            if ($presetKey === '' || ! isset($allowedSet[$presetKey])) {
                throw ValidationException::withMessages(['attachments_meta' => 'کلید نوع مدرک نامعتبر است یا با طرح هم‌خوانی ندارد.']);
            }

            $rowIndex = isset($raw['row_index']) ? (int) $raw['row_index'] : 0;
            if ($rowIndex < 0 || $rowIndex > 1000) {
                throw ValidationException::withMessages(['attachments_meta' => 'شمارهٔ ردیف پیوست نامعتبر است.']);
            }

            $clientRowId = null;
            if (array_key_exists('client_row_id', $raw) && $raw['client_row_id'] !== null && $raw['client_row_id'] !== '') {
                $clientRowId = (int) $raw['client_row_id'];
                if ($clientRowId < 1) {
                    throw ValidationException::withMessages(['attachments_meta' => 'شناسهٔ ردیف سمت کاربر نامعتبر است.']);
                }
            }

            $desc = $raw['description'] ?? null;
            $desc = $desc !== null && $desc !== '' ? mb_substr(trim((string) $desc), 0, 500) : null;

            $counts[$presetKey]++;
            if ($counts[$presetKey] > self::MAX_ROWS_PER_PRESET) {
                throw ValidationException::withMessages(['attachments_meta' => 'حداکثر تعداد پیوست برای هر مدرک رعایت نشده است.']);
            }

            $out[] = [
                'preset_key' => $presetKey,
                'row_index' => $rowIndex,
                'client_row_id' => $clientRowId,
                'description' => $desc,
            ];
        }

        foreach ($requiredKeys as $key) {
            if (($counts[$key] ?? 0) < 1) {
                throw ValidationException::withMessages(['attachments_meta' => 'برای همهٔ مدارک اولیه باید حداقل یک فایل ارسال شود.']);
            }
        }

        return $out;
    }

    /**
     * ارسال اعلان درون‌برنامه‌ای به همهٔ ادمین‌های فعال.
     *
     * مسیر اصلی ثبت درخواست را هرگز قطع نمی‌کنیم؛ هر خطایی در ارسال اعلان فقط لاگ می‌شود.
     */
    private function notifyAdminsOfNewSubmission(CustomerLoanRequest $request, Customer $customer): void
    {
        try {
            $admins = Admin::query()->where('is_active', true)->get();
            if ($admins->isEmpty()) {
                return;
            }
            Notification::send($admins, LoanRequestSubmittedForAdmins::build($request, $customer));
        } catch (\Throwable $e) {
            Log::warning('notify admins of new loan request failed', [
                'loan_request_id' => $request->id ?? null,
                'customer_id' => $customer->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
