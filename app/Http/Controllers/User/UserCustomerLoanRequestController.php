<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\LoanRequestStatusDefinition;
use App\Models\LoanType;
use App\Services\Loans\CustomerLoanRequestSubmissionService;
use App\Services\Loans\CustomerLoanRequestUserPresenter;
use App\Services\Loans\CustomerLoanRequestUserUpdateService;
use App\Services\Loans\CustomerLoanRequestWizardContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class UserCustomerLoanRequestController extends Controller
{
    public function store(
        Request $request,
        CustomerLoanRequestSubmissionService $submission,
        CustomerLoanRequestUserPresenter $presenter,
    ): JsonResponse {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }

        $validated = $request->validate([
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:600'],
            'installment_gap' => ['required', 'integer', 'min:1', 'max:600'],
            'installment_gap_unit' => ['required', 'string', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'attachments_meta' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:40'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [], [
            'loan_type_id' => 'طرح وام',
            'amount_toman' => 'مبلغ وام',
            'installments_count' => 'تعداد اقساط',
            'installment_gap' => 'فاصله اقساط',
            'description' => 'شرح کالاها و خدمات',
        ]);

        $plan = LoanType::query()->findOrFail((int) $validated['loan_type_id']);

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [];
        }
        $files = array_values(array_filter($files, static fn ($f) => $f !== null));

        $metaJson = isset($validated['attachments_meta']) ? (string) $validated['attachments_meta'] : '';
        if ($metaJson === '') {
            $metaJson = '[]';
        }

        try {
            $row = $submission->submit(
                $customer,
                $plan,
                (int) $validated['amount_toman'],
                (int) $validated['installments_count'],
                (int) $validated['installment_gap'],
                (string) $validated['installment_gap_unit'],
                (string) $validated['description'],
                $metaJson,
                $files,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();

        return response()->json([
            'message' => 'درخواست با موفقیت ثبت و برای بررسی به کارشناس ارسال شد.',
            'request' => $presenter->mapRequest($row, $statusTitles),
        ], 201);
    }

    public function wizardContext(
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestWizardContextService $context,
    ): JsonResponse {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }
        $this->assertOwnsRequest($customer, $customerLoanRequest);

        return response()->json($context->build($customerLoanRequest, $customer));
    }

    public function update(
        Request $request,
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestUserUpdateService $updater,
        CustomerLoanRequestUserPresenter $presenter,
    ): JsonResponse {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }
        $this->assertOwnsRequest($customer, $customerLoanRequest);

        $validated = $request->validate([
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:600'],
            'installment_gap' => ['required', 'integer', 'min:1', 'max:600'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'attachments_meta' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:40'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [], [
            'amount_toman' => 'مبلغ وام',
            'installments_count' => 'تعداد اقساط',
            'installment_gap' => 'فاصله اقساط',
            'description' => 'شرح کالاها و خدمات',
        ]);

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [];
        }
        $files = array_values(array_filter($files, static fn ($f) => $f !== null));

        $metaJson = isset($validated['attachments_meta']) ? (string) $validated['attachments_meta'] : '';
        if ($metaJson === '') {
            $metaJson = '[]';
        }

        try {
            $row = $updater->update(
                $customer,
                $customerLoanRequest,
                (int) $validated['amount_toman'],
                (int) $validated['installments_count'],
                (int) $validated['installment_gap'],
                (string) $validated['description'],
                $metaJson,
                $files,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();

        return response()->json([
            'message' => 'تغییرات ذخیره شد.',
            'request' => $presenter->mapRequest($row, $statusTitles),
        ]);
    }

    public function documentFile(
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestDocument $customerLoanRequestDocument,
    ): BinaryFileResponse {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(401);
        }
        $this->assertOwnsRequest($customer, $customerLoanRequest);
        $this->assertDocumentBelongs($customerLoanRequest, $customerLoanRequestDocument);

        $path = (string) $customerLoanRequestDocument->stored_path;
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $absolute = Storage::disk('local')->path($path);
        if (! is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute, [
            'Content-Type' => (string) ($customerLoanRequestDocument->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.basename((string) $customerLoanRequestDocument->original_filename).'"',
        ]);
    }

    private function assertOwnsRequest(Customer $customer, CustomerLoanRequest $loanRequest): void
    {
        if ((int) $loanRequest->customer_id !== (int) $customer->id) {
            abort(404);
        }
    }

    private function assertDocumentBelongs(
        CustomerLoanRequest $loanRequest,
        CustomerLoanRequestDocument $document,
    ): void {
        if ((int) $document->customer_loan_request_id !== (int) $loanRequest->id) {
            abort(404);
        }
    }
}
