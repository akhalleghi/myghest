<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Wallet\InstallmentWalletRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerInstallmentRewardController extends Controller
{
    public function __invoke(Request $request, Customer $customer, InstallmentWalletRewardService $rewards): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return response()->json($rewards->settings($customer));
        }
        $validated = $request->validate(['enabled' => ['required', 'boolean'], 'percent' => ['required', 'numeric', 'min:0.01', 'max:100']]);

        return response()->json(['message' => 'تنظیمات پاداش ذخیره شد؛ فقط تسویه‌های جدید پس از فعال‌سازی بررسی می‌شوند.',
            ...$rewards->saveSettings($customer, (bool) $validated['enabled'], (float) $validated['percent'])]);
    }
}
