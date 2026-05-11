<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerDepositDeclaration;
use App\Services\Deposits\DepositDeclarationAdminService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminDepositDeclarationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $statusStr = is_string($status) ? $status : null;
        $q = $request->query('q');
        $search = is_string($q) ? $q : null;

        $declarations = app(DepositDeclarationAdminService::class)->paginate($statusStr, $search, 20);

        $rowSnapshots = [];
        foreach ($declarations as $dec) {
            $rowSnapshots[$dec->id] = $this->buildRowModalPayload($dec);
        }

        return view('admin.deposit-declarations.index', [
            'pageTitle' => 'اعلام واریزها',
            'declarations' => $declarations,
            'statusFilter' => $statusStr ?? 'all',
            'searchQ' => $search ?? '',
            'rowSnapshots' => $rowSnapshots,
        ]);
    }

    public function attachment(Request $request, CustomerDepositDeclaration $deposit_declaration): StreamedResponse
    {
        $path = $deposit_declaration->attachment_path;
        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $download = $request->query('download') === '1';
        $name = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path)) ?: 'attachment';
        $mime = (string) (Storage::disk('public')->mimeType($path) ?: 'application/octet-stream');

        return Storage::disk('public')->response($path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }

    public function review(Request $request, CustomerDepositDeclaration $deposit_declaration): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['approve', 'approve_apply', 'reject'])],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $admin = $request->user('admin');
        if ($admin === null) {
            return response()->json(['message' => 'احراز هویت مدیر الزامی است.'], 401);
        }

        try {
            $out = app(DepositDeclarationAdminService::class)->review(
                $deposit_declaration,
                $admin,
                (string) $validated['action'],
                isset($validated['admin_note']) ? trim((string) $validated['admin_note']) : null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'ثبت شد.',
            'item' => $this->mapReviewRow($out),
        ]);
    }

    /**
     * دادهٔ نمایش در مودال «رسیدگی» و «مشاهدهٔ جزئیات» برای هر ردیف صفحهٔ فعلی.
     *
     * @return array<string, mixed>
     */
    private function buildRowModalPayload(CustomerDepositDeclaration $d): array
    {
        $d->loadMissing(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin']);
        $dep = Carbon::parse($d->deposited_at)->startOfDay();
        $path = $d->attachment_path;
        $hasFile = $path !== null && $path !== '' && Storage::disk('public')->exists($path);
        $kind = 'none';
        if ($hasFile) {
            $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
            $kind = $ext === 'pdf' ? 'pdf' : 'image';
        }
        $baseUrl = $hasFile ? route('admin.deposit-declarations.attachment', ['deposit_declaration' => $d]) : null;
        $downloadUrl = $baseUrl !== null ? $baseUrl.'?download=1' : null;
        $adminNote = trim((string) ($d->admin_note ?? ''));

        return [
            'is_pending' => $d->isPending(),
            'status_fa' => CustomerDepositDeclaration::statusLabelsFa()[$d->status] ?? $d->status,
            'admin_note' => $adminNote !== '' ? $adminNote : null,
            'reviewed_at_fa' => $d->reviewed_at !== null
                ? Jalali::enToFaNumbers(Jalali::instance($d->reviewed_at)->format('Y/m/d H:i'))
                : null,
            'reviewer' => $d->reviewedByAdmin !== null
                ? trim((string) ($d->reviewedByAdmin->name ?? $d->reviewedByAdmin->username ?? ''))
                : null,
            'customer_name' => $d->customer !== null ? trim($d->customer->first_name.' '.$d->customer->last_name) : '—',
            'mobile_fa' => Jalali::enToFaNumbers((string) ($d->customer?->mobile ?? '')),
            'loan_title' => (string) ($d->loanFile?->loanType?->title ?? '—'),
            'loan_code_fa' => Jalali::enToFaNumbers((string) ($d->loanFile?->loan_code ?? '')),
            'installment_seq_fa' => Jalali::enToFaNumbers((string) (int) ($d->installment?->sequence ?? 0)),
            'deposited_jalali_fa' => Jalali::enToFaNumbers(Jalali::instance($dep)->format('Y/m/d')),
            'amount_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $d->amount_toman), 0, '.', ',')).' تومان',
            'method_fa' => CustomerDepositDeclaration::userPaymentMethodLabelsFa()[$d->user_payment_method] ?? $d->user_payment_method,
            'tracking' => trim((string) ($d->tracking_number ?? '')),
            'customer_note' => (string) ($d->customer_note ?? ''),
            'attachment' => [
                'has' => $hasFile,
                'kind' => $kind,
                'inline_url' => $baseUrl,
                'download_url' => $downloadUrl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapReviewRow(CustomerDepositDeclaration $d): array
    {
        $d->loadMissing(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin']);
        $dep = Carbon::parse($d->deposited_at)->startOfDay();

        return [
            'id' => (int) $d->id,
            'status' => (string) $d->status,
            'status_fa' => CustomerDepositDeclaration::statusLabelsFa()[$d->status] ?? $d->status,
            'admin_note' => $d->admin_note,
            'reviewed_at_fa' => $d->reviewed_at !== null
                ? Jalali::enToFaNumbers(Jalali::instance($d->reviewed_at)->format('Y/m/d H:i'))
                : null,
            'reviewer' => $d->reviewedByAdmin !== null
                ? trim((string) ($d->reviewedByAdmin->name ?? $d->reviewedByAdmin->username ?? ''))
                : null,
            'deposited_jalali_fa' => Jalali::enToFaNumbers(Jalali::instance($dep)->format('Y/m/d')),
            'amount_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $d->amount_toman), 0, '.', ',')).' تومان',
            'customer_name' => $d->customer !== null ? trim($d->customer->first_name.' '.$d->customer->last_name) : '—',
            'loan_code_fa' => Jalali::enToFaNumbers((string) ($d->loanFile?->loan_code ?? '')),
            'loan_title' => (string) ($d->loanFile?->loanType?->title ?? '—'),
            'installment_seq_fa' => Jalali::enToFaNumbers((string) (int) ($d->installment?->sequence ?? 0)),
        ];
    }
}
