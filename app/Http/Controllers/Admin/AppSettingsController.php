<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        ], [], [
            'font_size' => 'اندازه فونت',
            'ui_font' => 'فونت',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_font_size'],
            ['value' => $validated['font_size']]
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'app_ui_font'],
            ['value' => $validated['ui_font']]
        );

        return back()->with('flash_success', 'تنظیمات ظاهر با موفقیت ذخیره شد.');
    }
}
