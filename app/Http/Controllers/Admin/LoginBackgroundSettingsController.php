<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ui\LoginBackgroundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class LoginBackgroundSettingsController extends Controller
{
    public function __construct(
        private readonly LoginBackgroundService $loginBackgrounds,
    ) {}

    public function updatePreference(Request $request, string $context): JsonResponse
    {
        $this->loginBackgrounds->assertContext($context);

        $validated = $request->validate([
            'mode' => ['required', 'in:fixed,random'],
            'selected' => ['nullable', 'string', 'max:260'],
        ], [], [
            'mode' => 'حالت نمایش',
            'selected' => 'تصویر انتخاب‌شده',
        ]);

        try {
            $this->loginBackgrounds->savePreference(
                $context,
                (string) $validated['mode'],
                isset($validated['selected']) ? (string) $validated['selected'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تنظیمات پیش‌زمینه ذخیره شد.',
            'state' => $this->loginBackgrounds->pickerState($context),
        ]);
    }

    public function upload(Request $request, string $context): JsonResponse
    {
        $this->loginBackgrounds->assertContext($context);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], [
            'image' => 'تصویر پیش‌زمینه',
        ]);

        try {
            $path = $this->loginBackgrounds->storeUpload($context, $validated['image']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تصویر با موفقیت بارگذاری شد.',
            'uploaded' => [
                'id' => $path,
                'url' => $this->loginBackgrounds->assetUrl($path),
                'is_custom' => true,
            ],
            'state' => $this->loginBackgrounds->pickerState($context),
        ]);
    }

    public function destroy(Request $request, string $context): JsonResponse
    {
        $this->loginBackgrounds->assertContext($context);

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:260'],
        ], [], [
            'path' => 'مسیر تصویر',
        ]);

        try {
            $this->loginBackgrounds->deleteCustom($context, (string) $validated['path']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تصویر حذف شد.',
            'state' => $this->loginBackgrounds->pickerState($context),
        ]);
    }
}
