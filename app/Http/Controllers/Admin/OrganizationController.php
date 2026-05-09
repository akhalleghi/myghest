<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $name = trim((string) $validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'نام سازمان را وارد کنید.'], 422);
        }

        $organization = Organization::query()->create(['name' => $name]);

        return response()->json([
            'message' => 'سازمان ثبت شد.',
            'organization' => ['id' => (int) $organization->id, 'name' => (string) $organization->name],
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $name = trim((string) $validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'نام سازمان را وارد کنید.'], 422);
        }

        $organization->update(['name' => $name]);

        return response()->json([
            'message' => 'سازمان ویرایش شد.',
            'organization' => ['id' => (int) $organization->id, 'name' => (string) $organization->name],
        ]);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json([
            'message' => 'سازمان حذف شد.',
        ]);
    }
}
