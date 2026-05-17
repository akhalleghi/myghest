@php
    use Illuminate\Support\Facades\Auth;

    $rawMessage = isset($exception) && $exception instanceof \Throwable
        ? trim((string) $exception->getMessage())
        : '';

    if ($rawMessage === '' || in_array($rawMessage, ['Forbidden', 'HTTP 403 Forbidden', 'Http Exception'], true)) {
        $rawMessage = 'شما به این بخش دسترسی ندارید.';
    }

    $isAdminContext = request()->routeIs('admin.*')
        || Auth::guard('admin')->check()
        || str_starts_with(request()->path(), 'admin');

    $isUserContext = ! $isAdminContext && (
        request()->routeIs('user.*')
        || Auth::guard('customer')->check()
        || str_starts_with(request()->path(), 'user')
    );

    if ($isAdminContext) {
        $homeUrl = route('admin.dashboard');
        $panelBadge = 'پنل مدیریت';
        $homeLabel = 'بازگشت به داشبورد';
        $hint = 'اگر فکر می‌کنید باید به این بخش دسترسی داشته باشید، از مدیر سامانه بخواهید دسترسی‌های حساب شما را در «کاربران ادمین» بررسی کند.';
    } elseif ($isUserContext) {
        $homeUrl = route('user.dashboard');
        $panelBadge = 'پنل کاربری';
        $homeLabel = 'بازگشت به داشبورد';
        $hint = 'این عملیات برای حساب شما فعال نیست یا به این بخش دسترسی ندارید.';
    } else {
        $homeUrl = url('/');
        $panelBadge = null;
        $homeLabel = 'صفحهٔ اصلی';
        $hint = 'لطفاً از مسیر صحیح وارد شوید یا با پشتیبانی تماس بگیرید.';
    }

    $errorPanelBadge = $panelBadge;
@endphp

@extends('errors.layout')

@section('error_title', 'دسترسی مجاز نیست')
@section('error_home_url', $homeUrl)

@section('error_content')
    <article class="http-error-card">
        <div class="http-error-visual" aria-hidden="true">
            <span class="http-error-visual__ring"></span>
            <span class="http-error-visual__icon">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
            </span>
            <span class="http-error-code">403</span>
        </div>

        <h1 class="http-error-title">دسترسی مجاز نیست</h1>
        <p class="http-error-message">{{ $rawMessage }}</p>

        <div class="http-error-hint">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>{{ $hint }}</span>
        </div>

        <div class="http-error-actions">
            <a href="{{ $homeUrl }}" class="http-error-btn http-error-btn--primary">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                {{ $homeLabel }}
            </a>
            <button type="button" class="http-error-btn http-error-btn--ghost" onclick="if (window.history.length > 1) { history.back(); } else { window.location.href = @json($homeUrl); }">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                صفحهٔ قبل
            </button>
        </div>
    </article>
@endsection

@section('error_footer')
    کد خطا: 403 — {{ $appDisplayName ?? config('app.name') }}
@endsection
