<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\LoanRequestStatusDefinition;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

/**
 * payload ویزارد برای «مشاهده / ویرایش» درخواست ثبت‌شده توسط مشتری.
 */
final class CustomerLoanRequestWizardContextService
{
    public function __construct(
        private readonly LoanPlanForWizardMapper $planMapper,
        private readonly LoanRequestStatusPresentation $statusPresentation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CustomerLoanRequest $loanRequest, Customer $customer): array
    {
        if ((int) $loanRequest->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $loanRequest->loadMissing(['loanType', 'documents']);
        $plan = $loanRequest->loanType;
        if ($plan === null) {
            abort(404);
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $statusChip = $this->statusPresentation->userChip((string) $loanRequest->status, $statusTitles);

        $docs = $loanRequest->documents;
        $wizardEditable = CustomerLoanRequest::wizardAllowsCustomerDocumentEditing($loanRequest);

        $waived = $loanRequest->waived_initial_preset_keys;
        $waived = is_array($waived) ? array_values(array_unique(array_map(static fn ($k): string => (string) $k, $waived))) : [];

        $documentPayload = $docs->map(function (CustomerLoanRequestDocument $d) use ($loanRequest): array {
            $isPdf = str_contains((string) $d->mime_type, 'pdf');
            $isImage = str_starts_with((string) $d->mime_type, 'image/');
            $mayReplace = CustomerLoanRequest::customerCanReplaceDocument($loanRequest, $d);

            return [
                'id' => (int) $d->id,
                'preset_key' => (string) $d->preset_key,
                'row_index' => (int) $d->row_index,
                'document_title' => (string) $d->document_title,
                'description' => $d->description !== null ? (string) $d->description : null,
                'expert_note' => $d->expert_note !== null ? (string) $d->expert_note : null,
                'review_status' => (string) $d->review_status,
                'review_status_label' => CustomerLoanRequestDocument::reviewStatusLabels()[$d->review_status] ?? $d->review_status,
                'needs_attention' => $d->review_status === CustomerLoanRequestDocument::REVIEW_INCOMPLETE,
                'may_replace_file' => $mayReplace,
                'mime_type' => (string) $d->mime_type,
                'is_image' => $isImage,
                'is_pdf' => $isPdf,
                'file_url' => route('user.loan-request.documents.file', [
                    'customerLoanRequest' => $loanRequest->id,
                    'customerLoanRequestDocument' => $d->id,
                ]),
            ];
        })->values()->all();

        $submitted = $loanRequest->submitted_at ?? $loanRequest->created_at;
        $submitted = $submitted instanceof Carbon ? $submitted : Carbon::parse((string) $submitted);

        $documentIssueHints = $docs
            ->filter(static function (CustomerLoanRequestDocument $d): bool {
                return in_array($d->review_status, [
                    CustomerLoanRequestDocument::REVIEW_INCOMPLETE,
                    CustomerLoanRequestDocument::REVIEW_WAITING_USER,
                ], true);
            })
            ->map(static function (CustomerLoanRequestDocument $d): array {
                return [
                    'title' => (string) $d->document_title,
                    'expert_note' => $d->expert_note !== null ? (string) $d->expert_note : null,
                    'status_label' => CustomerLoanRequestDocument::reviewStatusLabels()[$d->review_status] ?? $d->review_status,
                ];
            })
            ->values()
            ->all();

        return [
            'loan_request_id' => (int) $loanRequest->id,
            'wizard_editable' => $wizardEditable,
            'request_marked_documents_incomplete' => $loanRequest->status === CustomerLoanRequest::STATUS_DOCUMENTS_INCOMPLETE,
            'document_issue_hints' => $documentIssueHints,
            'waived_initial_preset_keys' => $waived,
            'plan' => $this->planMapper->map($plan),
            'request' => [
                'loan_type_id' => (int) $loanRequest->loan_type_id,
                'amount_toman' => (int) $loanRequest->amount_toman,
                'installments_count' => (int) $loanRequest->installments_count,
                'installment_gap' => (int) $loanRequest->installment_interval_count,
                'installment_gap_unit' => (string) $loanRequest->installment_interval_unit,
                'description' => (string) ($loanRequest->description ?? ''),
                'status' => (string) $loanRequest->status,
                'status_label' => $statusChip['label'],
                'submitted_at_jalali_fa' => Jalali::enToFaNumbers(Jalali::instance($submitted)->format('Y/m/d')),
            ],
            'documents' => $documentPayload,
        ];
    }
}
