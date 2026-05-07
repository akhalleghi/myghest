<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class AppSettingsController extends Controller
{
    public function updateBase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
        ], [], [
            'display_name' => 'نام نمایشی سامانه',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_display_name'],
            ['value' => trim((string) $validated['display_name'])]
        );

        return back()->with('flash_success', 'نام نمایشی سامانه با موفقیت ذخیره شد.');
    }

    public function updateUi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'font_size' => ['required', 'in:small,normal,large'],
            'ui_font' => ['required', 'in:iransans,iranyekan,anjoman'],
            'app_icon_fa' => ['nullable', 'string', 'max:80', 'regex:/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/'],
            'favicon_fa' => ['nullable', 'string', 'max:80', 'regex:/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/'],
            'app_icon' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg,ico', 'max:1024'],
            'remove_app_icon' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [], [
            'font_size' => 'اندازه فونت',
            'ui_font' => 'فونت',
            'app_icon_fa' => 'آیکون Font Awesome',
            'favicon_fa' => 'فاوآیکون Font Awesome',
            'app_icon' => 'آیکون برنامه',
            'favicon' => 'فاوآیکون',
            'remove_app_icon' => 'حذف آیکون برنامه',
            'remove_favicon' => 'حذف فاوآیکون',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_font_size'],
            ['value' => $validated['font_size']]
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_ui_font'],
            ['value' => $validated['ui_font']]
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'app_icon_fa'],
            ['value' => trim((string) ($validated['app_icon_fa'] ?? ''))]
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'favicon_fa'],
            ['value' => trim((string) ($validated['favicon_fa'] ?? ''))]
        );

        if ($request->boolean('remove_app_icon')) {
            $old = AppSetting::query()->where('key', 'app_icon_path')->value('value');
            if (is_string($old) && $old !== '') {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_icon_path'], ['value' => '']);
        } elseif ($request->file('app_icon') instanceof UploadedFile) {
            $old = AppSetting::query()->where('key', 'app_icon_path')->value('value');
            $path = $this->storePublicAsset($request->file('app_icon'), 'app-icon');
            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'app_icon_path'], ['value' => $path]);
        }

        if ($request->boolean('remove_favicon')) {
            $old = AppSetting::query()->where('key', 'favicon_path')->value('value');
            if (is_string($old) && $old !== '') {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'favicon_path'], ['value' => '']);
        } elseif ($request->file('favicon') instanceof UploadedFile) {
            $old = AppSetting::query()->where('key', 'favicon_path')->value('value');
            $path = $this->storePublicAsset($request->file('favicon'), 'favicon');
            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->deletePublicAsset($old);
            }
            AppSetting::query()->updateOrCreate(['key' => 'favicon_path'], ['value' => $path]);
        }

        return back()->with('flash_success', 'تنظیمات ظاهر با موفقیت ذخیره شد.');
    }

    private function storePublicAsset(UploadedFile $file, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = $prefix.'-'.Str::lower(Str::random(18)).'.'.$extension;
        $targetDir = public_path('uploads/app-branding');
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file->move($targetDir, $safeName);

        return 'uploads/app-branding/'.$safeName;
    }

    private function deletePublicAsset(string $relativePath): void
    {
        $normalized = str_replace(['\\', '..'], ['/', ''], $relativePath);
        $full = public_path($normalized);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
