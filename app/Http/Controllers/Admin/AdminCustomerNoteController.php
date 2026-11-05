<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAdminNote;
use App\Services\Customers\CustomerAdminNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AdminCustomerNoteController extends Controller
{
    public function index(Request $request, Customer $customer, CustomerAdminNoteService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        return response()->json([
            'customer' => [
                'id' => (int) $customer->id,
                'name' => $customer->fullName(),
                'mobile' => (string) ($customer->mobile ?? ''),
            ],
            'notes' => $service->listForCustomer($customer, $admin, ...$this->scopeIds($request, $customer)),
        ]);
    }

    public function store(Request $request, Customer $customer, CustomerAdminNoteService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'body' => 'متن یادداشت',
        ]);

        try {
            [$fileId, $installmentId] = $this->scopeIds($request, $customer);
            $scope = [];
            if ($fileId !== null) {
                $file = $customer->loanFiles()->findOrFail($fileId);
                $scope = ['loan_file_id' => $fileId, 'installment_id' => $installmentId,
                    'scope_label' => 'پرونده '.$file->loan_code.($installmentId ? ' · قسط '.$file->installments()->findOrFail($installmentId)->sequence : '')];
            }
            $note = $service->create($customer, $admin, (string) $validated['body'], $scope);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'یادداشت ثبت شد.',
            'note' => $note,
        ], 201);
    }

    public function update(
        Request $request,
        Customer $customer,
        CustomerAdminNote $note,
        CustomerAdminNoteService $service,
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $service->assertNoteBelongsToCustomer($note, $customer);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'body' => 'متن یادداشت',
        ]);

        try {
            $payload = $service->update($note, $admin, (string) $validated['body']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'یادداشت به‌روزرسانی شد.',
            'note' => $payload,
        ]);
    }

    private function scopeIds(Request $request, Customer $customer): array
    {
        $data = $request->validate(['loan_file_id' => ['nullable', 'integer', 'min:1'], 'installment_id' => ['nullable', 'integer', 'min:1']]);
        $fileId = isset($data['loan_file_id']) ? (int) $data['loan_file_id'] : null;
        $installmentId = isset($data['installment_id']) ? (int) $data['installment_id'] : null;
        if ($installmentId && ! $fileId) {
            abort(422, 'پرونده وام الزامی است.');
        }
        if ($fileId) {
            $file = $customer->loanFiles()->findOrFail($fileId);
            if ($installmentId) {
                $file->installments()->findOrFail($installmentId);
            }
        }

        return [$fileId, $installmentId];
    }

    public function destroy(
        Customer $customer,
        CustomerAdminNote $note,
        CustomerAdminNoteService $service,
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $service->assertNoteBelongsToCustomer($note, $customer);

        try {
            $service->delete($note, $admin);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'یادداشت حذف شد.',
        ]);
    }
}
