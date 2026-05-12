<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoanRequest;
use App\Models\LoanRequestStatusDefinition;
use App\Models\SmsTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class AdminLoanRequestStatusDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        $definitions = LoanRequestStatusDefinition::query()
            ->with(['smsTemplate:id,title'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static function (LoanRequestStatusDefinition $d): array {
                return [
                    'id' => $d->id,
                    'code' => $d->code,
                    'title' => $d->title,
                    'stage_slot' => $d->stage_slot,
                    'sms_template_id' => $d->sms_template_id,
                    'sms_template_title' => $d->smsTemplate?->title,
                    'is_mutable' => (bool) $d->is_mutable,
                    'allow_duplicate_request' => (bool) $d->allow_duplicate_request,
                    'sort_order' => (int) $d->sort_order,
                ];
            });

        $smsQuery = SmsTemplate::query()->orderBy('title');
        if (SmsTemplate::query()->where('category', 'loan_request_status')->exists()) {
            $smsQuery->where('category', 'loan_request_status');
        }
        $smsTemplates = $smsQuery
            ->get(['id', 'title'])
            ->map(static fn (SmsTemplate $t): array => [
                'id' => $t->id,
                'title' => $t->title,
            ])
            ->values();

        return response()->json([
            'definitions' => $definitions,
            'stage_slots' => LoanRequestStatusDefinition::STAGE_SLOT_LABELS,
            'sms_templates' => $smsTemplates,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'stage_slot' => ['nullable', 'string', Rule::in(array_keys(LoanRequestStatusDefinition::STAGE_SLOT_LABELS))],
            'sms_template_id' => $this->smsTemplateIdRules(),
            'is_mutable' => ['sometimes', 'boolean'],
            'allow_duplicate_request' => ['sometimes', 'boolean'],
        ], [], [
            'title' => 'عنوان وضعیت',
            'stage_slot' => 'جایگاه',
            'sms_template_id' => 'قالب پیامک',
        ]);

        $maxSort = (int) LoanRequestStatusDefinition::query()->max('sort_order');

        $code = $this->uniqueCustomCode();

        $row = LoanRequestStatusDefinition::query()->create([
            'code' => $code,
            'title' => trim((string) $validated['title']),
            'stage_slot' => $validated['stage_slot'] ?? null,
            'sms_template_id' => $validated['sms_template_id'] ?? null,
            'is_mutable' => (bool) ($validated['is_mutable'] ?? true),
            'allow_duplicate_request' => (bool) ($validated['allow_duplicate_request'] ?? false),
            'sort_order' => $maxSort + 10,
        ]);
        $row->load(['smsTemplate:id,title']);

        return response()->json([
            'definition' => [
                'id' => $row->id,
                'code' => $row->code,
                'title' => $row->title,
                'stage_slot' => $row->stage_slot,
                'sms_template_id' => $row->sms_template_id,
                'sms_template_title' => $row->smsTemplate?->title,
                'is_mutable' => (bool) $row->is_mutable,
                'allow_duplicate_request' => (bool) $row->allow_duplicate_request,
                'sort_order' => (int) $row->sort_order,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, LoanRequestStatusDefinition $loanRequestStatusDefinition): JsonResponse
    {
        if (! $loanRequestStatusDefinition->is_mutable) {
            return response()->json(['message' => 'این وضعیت سیستمی است و قابل ویرایش نیست.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'stage_slot' => ['nullable', 'string', Rule::in(array_keys(LoanRequestStatusDefinition::STAGE_SLOT_LABELS))],
            'sms_template_id' => $this->smsTemplateIdRules(),
            'allow_duplicate_request' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ], [], [
            'title' => 'عنوان وضعیت',
        ]);

        $loanRequestStatusDefinition->fill([
            'title' => trim((string) $validated['title']),
            'stage_slot' => $validated['stage_slot'] ?? null,
            'sms_template_id' => $validated['sms_template_id'] ?? null,
            'allow_duplicate_request' => (bool) ($validated['allow_duplicate_request'] ?? $loanRequestStatusDefinition->allow_duplicate_request),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $loanRequestStatusDefinition->sort_order,
        ]);
        $loanRequestStatusDefinition->save();

        $loanRequestStatusDefinition->loadMissing(['smsTemplate:id,title']);

        return response()->json([
            'definition' => [
                'id' => $loanRequestStatusDefinition->id,
                'code' => $loanRequestStatusDefinition->code,
                'title' => $loanRequestStatusDefinition->title,
                'stage_slot' => $loanRequestStatusDefinition->stage_slot,
                'sms_template_id' => $loanRequestStatusDefinition->sms_template_id,
                'sms_template_title' => $loanRequestStatusDefinition->smsTemplate?->title,
                'is_mutable' => (bool) $loanRequestStatusDefinition->is_mutable,
                'allow_duplicate_request' => (bool) $loanRequestStatusDefinition->allow_duplicate_request,
                'sort_order' => (int) $loanRequestStatusDefinition->sort_order,
            ],
        ]);
    }

    public function destroy(LoanRequestStatusDefinition $loanRequestStatusDefinition): JsonResponse
    {
        if (! $loanRequestStatusDefinition->is_mutable) {
            return response()->json(['message' => 'حذف این وضعیت مجاز نیست.'], Response::HTTP_FORBIDDEN);
        }

        $inUse = CustomerLoanRequest::query()
            ->where('status', $loanRequestStatusDefinition->code)
            ->exists();

        if ($inUse) {
            return response()->json(['message' => 'این وضعیت روی یک یا چند درخواست وام استفاده شده و قابل حذف نیست.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $loanRequestStatusDefinition->delete();

        return response()->json(['message' => 'وضعیت حذف شد.']);
    }

    private function uniqueCustomCode(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = 'custom_'.bin2hex(random_bytes(6));
            if (! LoanRequestStatusDefinition::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        return 'custom_'.bin2hex(random_bytes(8));
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\Exists>
     */
    private function smsTemplateIdRules(): array
    {
        if (! SmsTemplate::query()->where('category', 'loan_request_status')->exists()) {
            return ['nullable', 'integer', 'exists:sms_templates,id'];
        }

        return [
            'nullable',
            'integer',
            Rule::exists('sms_templates', 'id')->where('category', 'loan_request_status'),
        ];
    }
}
