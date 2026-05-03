<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    /**
     * داشبورد مدیریت با لایوت کامل (سایدبار، هدر، کارت‌ها و جداول نمونه).
     */
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
