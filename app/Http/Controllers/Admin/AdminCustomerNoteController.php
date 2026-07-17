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
    public function index(Customer $customer, CustomerAdminNoteService $service): JsonResponse
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
            'notes' => $service->listForCustomer($customer, $admin),
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
            $note = $service->create($customer, $admin, (string) $validated['body']);
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
