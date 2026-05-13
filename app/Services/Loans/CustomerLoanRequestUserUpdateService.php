<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\LoanType;
use App\Notifications\LoanRequestUpdatedForAdmins;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CustomerLoanRequestUserUpdateService
{
    public function __construct(
        private readonly LoanWizardParameterValidator $wizardRules,
        private readonly CustomerLoanRequestSubmissionService $submissionHelper,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function update(
        Customer $customer,
        CustomerLoanRequest $loanRequest,
        int $amountToman,
        int $installmentsCount,
        int $installmentGap,
        string $description,
        string $attachmentsMetaJson,
        array $files,
    ): CustomerLoanRequest {
        if ((int) $loanRequest->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(['loan_request' => 'درخواست نامعتبر است.']);
        }

        $loanRequest->loadMissing(['documents', 'loanType']);
        $plan = $loanRequest->loanType;
        if ($plan === null) {
            throw ValidationException::withMessages(['loan_request' => 'طرح وام یافت نشد.']);
        }

        if (! $plan->plan_list_enabled || $plan->registration_suspended) {
            throw ValidationException::withMessages(['loan_request' => 'این طرح در حال حاضر برای ویرایش فعال نیست.']);
        }

        $editable = CustomerLoanRequest::wizardAllowsCustomerDocumentEditing($loanRequest);

        if (! $editable) {
            throw ValidationException::withMessages(['loan_request' => 'ویرایش این درخواست در وضعیت فعلی مجاز نیست.']);
        }

        $desc = trim($description);
        $this->wizardRules->assertPlanAcceptsParameters(
            $plan,
            $amountToman,
            $installmentsCount,
            $installmentGap,
            (string) $plan->installment_gap_unit,
            $desc,
        );

        $files = array_values(array_filter($files, static fn ($f) => $f instanceof UploadedFile));
        $metaJsonTrim = trim($attachmentsMetaJson);
        $hasFilePayload = $files !== [] || ($metaJsonTrim !== '' && $metaJsonTrim !== '[]');

        $waived = $loanRequest->waived_initial_preset_keys;
        $waived = is_array($waived) ? array_values(array_unique(array_map(static fn ($k): string => (string) $k, $waived))) : [];

        $initialMeta = $this->submissionHelper->initialDocumentsMetaByPresetKey($plan);

        $operations = [];
        if ($hasFilePayload) {
            $decoded = json_decode($attachmentsMetaJson, true);
            if (! is_array($decoded) || count($decoded) !== count($files)) {
                throw ValidationException::withMessages(['attachments_meta' => 'ساختار پیوست‌ها با فایل‌های ارسال‌شده هم‌خوان نیست.']);
            }

            foreach ($decoded as $i => $raw) {
                if (! is_array($raw)) {
                    throw ValidationException::withMessages(['attachments_meta' => 'اطلاعات پیوست نامعتبر است.']);
                }
                $file = $files[$i];
                if (isset($raw['document_id']) && $raw['document_id'] !== null && $raw['document_id'] !== '') {
                    $docId = (int) $raw['document_id'];
                    $doc = CustomerLoanRequestDocument::query()
                        ->where('customer_loan_request_id', $loanRequest->id)
                        ->whereKey($docId)
                        ->first();
                    if ($doc === null) {
                        throw ValidationException::withMessages(['attachments_meta' => 'مدرک انتخاب‌شده معتبر نیست.']);
                    }
                    if (! CustomerLoanRequest::customerCanReplaceDocument($loanRequest, $doc)) {
                        throw ValidationException::withMessages(['attachments_meta' => 'جایگزینی برای این مدرک مجاز نیست.']);
                    }
                    $rowIndex = isset($raw['row_index']) ? (int) $raw['row_index'] : (int) $doc->row_index;
                    $clientRowId = null;
                    if (array_key_exists('client_row_id', $raw) && $raw['client_row_id'] !== null && $raw['client_row_id'] !== '') {
                        $clientRowId = (int) $raw['client_row_id'];
                    }
                    $dDesc = $raw['description'] ?? null;
                    $dDesc = $dDesc !== null && $dDesc !== '' ? mb_substr(trim((string) $dDesc), 0, 500) : null;
                    $operations[] = ['type' => 'replace', 'document' => $doc, 'file' => $file, 'row_index' => $rowIndex, 'client_row_id' => $clientRowId, 'description' => $dDesc];
                } else {
                    $presetKey = isset($raw['preset_key']) ? trim((string) $raw['preset_key']) : '';
                    if ($presetKey === '' || ! in_array($presetKey, $waived, true)) {
                        throw ValidationException::withMessages(['attachments_meta' => 'بارگذاری فقط برای مدارک حذف‌شده توسط کارشناس مجاز است.']);
                    }
                    $rowIndex = isset($raw['row_index']) ? (int) $raw['row_index'] : 0;
                    $clientRowId = null;
                    if (array_key_exists('client_row_id', $raw) && $raw['client_row_id'] !== null && $raw['client_row_id'] !== '') {
                        $clientRowId = (int) $raw['client_row_id'];
                    }
                    $dDesc = $raw['description'] ?? null;
                    $dDesc = $dDesc !== null && $dDesc !== '' ? mb_substr(trim((string) $dDesc), 0, 500) : null;
                    $title = $initialMeta[$presetKey]['title'] ?? $presetKey;
                    $operations[] = ['type' => 'create', 'preset_key' => $presetKey, 'title' => $title, 'file' => $file, 'row_index' => $rowIndex, 'client_row_id' => $clientRowId, 'description' => $dDesc];
                }
            }
        }

        $storedPaths = [];
        $reAddedPresets = [];

        DB::beginTransaction();

        try {
            $loanRequest->amount_toman = $amountToman;
            $loanRequest->installments_count = $installmentsCount;
            $loanRequest->installment_interval_count = $installmentGap;
            $loanRequest->installment_interval_unit = (string) $plan->installment_gap_unit;
            $loanRequest->profit_calculation_method = (string) $plan->profit_calculation_method;
            $loanRequest->interest_rate = $plan->interest_rate;
            $loanRequest->daily_late_coefficient = $plan->daily_late_coefficient;
            $loanRequest->daily_early_coefficient = $plan->daily_early_coefficient;
            $loanRequest->description = $desc;
            $loanRequest->save();

            $folder = 'loan_requests/'.$customer->id.'/'.$loanRequest->id;

            foreach ($operations as $op) {
                if ($op['type'] === 'replace') {
                    /** @var CustomerLoanRequestDocument $doc */
                    $doc = $op['document'];
                    /** @var UploadedFile $file */
                    $file = $op['file'];
                    $oldPath = (string) $doc->stored_path;
                    $relativePath = Storage::disk('local')->putFile($folder, $file);
                    if ($relativePath === false) {
                        throw ValidationException::withMessages(['files' => 'ذخیره‌سازی فایل انجام نشد.']);
                    }
                    $storedPaths[] = $relativePath;
                    if ($oldPath !== '') {
                        Storage::disk('local')->delete($oldPath);
                    }
                    $doc->stored_path = $relativePath;
                    $doc->original_filename = $file->getClientOriginalName();
                    $doc->mime_type = (string) ($file->getMimeType() ?: 'application/octet-stream');
                    $doc->size_bytes = (int) $file->getSize();
                    $doc->row_index = (int) $op['row_index'];
                    $doc->client_row_id = $op['client_row_id'];
                    $doc->description = $op['description'];
                    $doc->review_status = CustomerLoanRequestDocument::REVIEW_WAITING_EXPERT;
                    $doc->expert_note = null;
                    $doc->save();
                } else {
                    $presetKey = (string) $op['preset_key'];
                    /** @var UploadedFile $file */
                    $file = $op['file'];
                    $relativePath = Storage::disk('local')->putFile($folder, $file);
                    if ($relativePath === false) {
                        throw ValidationException::withMessages(['files' => 'ذخیره‌سازی فایل انجام نشد.']);
                    }
                    $storedPaths[] = $relativePath;
                    CustomerLoanRequestDocument::query()->create([
                        'customer_loan_request_id' => $loanRequest->id,
                        'preset_key' => $presetKey,
                        'document_title' => (string) $op['title'],
                        'row_index' => (int) $op['row_index'],
                        'client_row_id' => $op['client_row_id'],
                        'description' => $op['description'],
                        'stored_path' => $relativePath,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                        'size_bytes' => (int) $file->getSize(),
                        'review_status' => CustomerLoanRequestDocument::REVIEW_WAITING_EXPERT,
                        'expert_note' => null,
                    ]);
                    $reAddedPresets[] = $presetKey;
                }
            }

            if ($reAddedPresets !== []) {
                $loanRequest->waived_initial_preset_keys = array_values(array_diff($waived, array_unique($reAddedPresets)));
                $loanRequest->save();
            }

            DB::commit();

            $fresh = $loanRequest->fresh(['loanType', 'documents']) ?? $loanRequest;
            if ($operations !== []) {
                $this->maybeAdvanceRequestAfterDocumentsFixed($fresh);
            }

            $this->notifyAdminsOfCustomerUpdate($fresh, $customer);

            return $fresh;
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

    private function maybeAdvanceRequestAfterDocumentsFixed(CustomerLoanRequest $loanRequest): void
    {
        $loanRequest->load('documents');
        $stillNeedsUser = $loanRequest->documents->contains(static function (CustomerLoanRequestDocument $d): bool {
            return in_array($d->review_status, [
                CustomerLoanRequestDocument::REVIEW_INCOMPLETE,
                CustomerLoanRequestDocument::REVIEW_WAITING_USER,
            ], true);
        });
        if ($stillNeedsUser) {
            return;
        }
        if ($loanRequest->status !== CustomerLoanRequest::STATUS_DOCUMENTS_INCOMPLETE) {
            return;
        }
        $loanRequest->status = CustomerLoanRequest::STATUS_PENDING_EXPERT_REVIEW;
        $loanRequest->save();
    }

    /**
     * ارسال اعلان درون‌برنامه‌ای به ادمین‌های فعال پس از ویرایش درخواست توسط مشتری.
     * عمداً هرگز Exception پرتاب نمی‌کنیم تا مسیر اصلی ذخیره/ویرایش مختل نشود.
     */
    private function notifyAdminsOfCustomerUpdate(CustomerLoanRequest $request, Customer $customer): void
    {
        try {
            $admins = Admin::query()->where('is_active', true)->get();
            if ($admins->isEmpty()) {
                return;
            }
            Notification::send($admins, LoanRequestUpdatedForAdmins::build($request, $customer));
        } catch (\Throwable $e) {
            Log::warning('notify admins of customer loan request update failed', [
                'loan_request_id' => $request->id ?? null,
                'customer_id' => $customer->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
