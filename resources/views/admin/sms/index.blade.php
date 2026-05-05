@extends('layouts.admin.app')

@section('title', 'مدیریت پیامک')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .sms-page { max-width: 100%; }
        .sms-title { margin: 0 0 0.9rem; font-size: 1.08rem; font-weight: 800; color: var(--text); }
        .sms-sub { margin: 0 0 0.8rem; font-size: 0.84rem; color: var(--muted); }
        .sms-tabs { display: flex; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .sms-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.75rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); }
        .sms-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); }
        .sms-tab.is-disabled { opacity: 0.55; }

        .sms-filters { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .sms-statuses { display: inline-flex; gap: 0.45rem; flex-wrap: wrap; }
        .sms-status { border: 1px solid var(--border); border-radius: 999px; padding: 0.38rem 0.65rem; font-size: 0.75rem; font-weight: 700; color: var(--muted); text-decoration: none; background: var(--bg-card); }
        .sms-status.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .sms-search { min-width: min(100%, 19rem); flex: 1 1 16rem; max-width: 25rem; }
        .sms-search form { display: flex; gap: 0.45rem; }
        .sms-search input { width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.5rem 0.7rem; font-size: 0.84rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .sms-search button { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.7rem; background: var(--bg-card); color: var(--text); cursor: pointer; }

        .sms-date-toolbar { border: 1px solid var(--border); border-radius: 0.85rem; padding: 0.7rem 0.75rem; margin-bottom: 0.75rem; background: var(--bg-card); display: flex; flex-direction: column; gap: 0.65rem; }
        .sms-day-nav { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; justify-content: center; }
        .sms-day-btn { text-decoration: none; border: 1px solid var(--border); border-radius: 0.6rem; font-size: 0.78rem; font-weight: 700; padding: 0.42rem 0.7rem; color: var(--text); background: var(--bg-card); }
        .sms-day-current { border: 1px dashed var(--border); border-radius: 0.6rem; padding: 0.42rem 0.7rem; min-width: 9.7rem; text-align: center; font-size: 0.83rem; font-weight: 700; color: var(--text); background: var(--primary-soft); }
        .sms-range-toggle { text-align: center; }
        .sms-range-toggle button { border: 1px solid var(--border); border-radius: 0.6rem; font-size: 0.78rem; font-weight: 700; padding: 0.42rem 0.7rem; background: var(--bg-card); color: var(--text); cursor: pointer; }
        .sms-range-panel { border-top: 1px solid var(--border); padding-top: 0.65rem; }
        .sms-range-panel[hidden] { display: none !important; }
        .sms-range-form { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: end; justify-content: center; }
        .sms-range-field { min-width: 10rem; }
        .sms-range-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.2rem; }
        .sms-range-field input { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .sms-range-form button { border: none; border-radius: 0.62rem; padding: 0.52rem 0.9rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }

        .sms-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.9rem; overflow: hidden; }
        .sms-table-wrap { overflow-x: auto; }
        .sms-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .sms-table th, .sms-table td { padding: 0.58rem 0.72rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .sms-table th { white-space: nowrap; background: var(--primary-soft); color: var(--text); font-weight: 800; }
        .sms-msg { max-width: 22rem; line-height: 1.6; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sms-badge { display: inline-block; padding: 0.16rem 0.45rem; border-radius: 0.35rem; font-size: 0.71rem; font-weight: 700; }
        .sms-badge--pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .sms-badge--delivered { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-badge--undelivered { background: rgba(248, 113, 113, 0.2); color: #b91c1c; }
        .sms-action-btn { border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.3rem 0.52rem; font-size: 0.72rem; font-weight: 700; color: var(--text); background: var(--bg-card); cursor: pointer; }
        .sms-empty { text-align: center; padding: 1.25rem; color: var(--muted); font-size: 0.84rem; }
        .sms-pagination { padding: 0.65rem 0.8rem; }
    </style>
@endpush

@section('content')
    <div class="sms-page">
        <h1 class="sms-title">مدیریت پیامک</h1>
        <p class="sms-sub">گزارش ارسال پیامک‌ها، جستجو و فیلتر وضعیت، و بازهٔ زمانی روزانه/دلخواه.</p>

        <div class="sms-tabs" role="tablist" aria-label="تب‌های مدیریت پیامک">
            <span class="sms-tab is-active">گزارش پیامک‌ها</span>
            <span class="sms-tab is-disabled">الگوهای پیامک (به‌زودی)</span>
            <span class="sms-tab is-disabled">تنظیمات پنل (به‌زودی)</span>
        </div>

        <div class="sms-date-toolbar">
            <div class="sms-day-nav">
                <a class="sms-day-btn" href="{{ request()->fullUrlWithQuery(['mode' => 'day', 'date' => $prevDate]) }}">روز قبل</a>
                <div class="sms-day-current">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($selectedDateJalali) }}</div>
                <a class="sms-day-btn" href="{{ request()->fullUrlWithQuery(['mode' => 'day', 'date' => $nextDate]) }}">روز بعد</a>
            </div>
            <div class="sms-range-toggle">
                <button type="button" id="sms-toggle-range">انتخاب بازه زمانی</button>
            </div>
            <div class="sms-range-panel" id="sms-range-panel" @if (! $isRangeMode) hidden @endif>
                <form method="get" class="sms-range-form">
                    <input type="hidden" name="mode" value="range">
                    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    <div class="sms-range-field">
                        <label for="sms-from-jdate">از تاریخ</label>
                        <input id="sms-from-jdate" name="from_jdate" type="text" value="{{ $fromJDate }}" autocomplete="off">
                    </div>
                    <div class="sms-range-field">
                        <label for="sms-to-jdate">تا تاریخ</label>
                        <input id="sms-to-jdate" name="to_jdate" type="text" value="{{ $toJDate }}" autocomplete="off">
                    </div>
                    <button type="submit">اعمال</button>
                </form>
            </div>
        </div>

        <div class="sms-filters">
            <div class="sms-statuses">
                <a class="sms-status @if($status === '') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => null]) }}">همه</a>
                <a class="sms-status @if($status === 'pending') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'pending']) }}">در انتظارها</a>
                <a class="sms-status @if($status === 'delivered') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'delivered']) }}">تحویل شده‌ها</a>
                <a class="sms-status @if($status === 'undelivered') is-active @endif" href="{{ request()->fullUrlWithQuery(['status' => 'undelivered']) }}">تحویل نشده‌ها</a>
            </div>
            <div class="sms-search">
                <form method="get">
                    <input type="hidden" name="mode" value="{{ $isRangeMode ? 'range' : 'day' }}">
                    @if ($isRangeMode)
                        <input type="hidden" name="from_jdate" value="{{ $fromJDate }}">
                        <input type="hidden" name="to_jdate" value="{{ $toJDate }}">
                    @else
                        <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    @endif
                    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="جستجو در متن، دریافت‌کننده، نوع یا پنل...">
                    <button type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>

        <div class="sms-card">
            <div class="sms-table-wrap">
                <table class="sms-table">
                    <thead>
                        <tr>
                            <th>پنل پیامک</th>
                            <th>وضعیت</th>
                            <th>زمان ارسال</th>
                            <th>متن</th>
                            <th>دریافت کننده</th>
                            <th>نوع</th>
                            <th>عملیات</th>
                            <th>هزینه</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $statusClass = match ($log->status) {
                                    \App\Models\SmsLog::STATUS_PENDING => 'sms-badge--pending',
                                    \App\Models\SmsLog::STATUS_DELIVERED => 'sms-badge--delivered',
                                    default => 'sms-badge--undelivered',
                                };
                                $sentAt = $log->sent_at ? jalali($log->sent_at)->format('Y/m/d H:i') : '—';
                            @endphp
                            <tr>
                                <td>{{ $log->sms_panel }}</td>
                                <td><span class="sms-badge {{ $statusClass }}">{{ $log->statusLabel() }}</span></td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($sentAt) }}</td>
                                <td><div class="sms-msg" title="{{ $log->message_text }}">{{ $log->message_text }}</div></td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($log->recipient) }}</td>
                                <td>{{ $log->type }}</td>
                                <td><button type="button" class="sms-action-btn" title="{{ $log->message_text }}">جزئیات</button></td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format((float) $log->cost, 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="sms-empty">پیامکی در بازه انتخابی یافت نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="sms-pagination">{{ $logs->links() }}</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-date.min.js') }}"></script>
    <script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
    <script>
        (function () {
            var toggleBtn = document.getElementById('sms-toggle-range');
            var panel = document.getElementById('sms-range-panel');
            if (toggleBtn && panel) {
                toggleBtn.addEventListener('click', function () {
                    panel.hidden = !panel.hidden;
                });
            }

            function initJalaliPicker() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    console.error('pDatepicker is not available.');
                    return;
                }

                window.jQuery('#sms-from-jdate, #sms-to-jdate').pDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    calendarType: 'persian',
                    initialValueType: 'persian',
                    toolbox: {
                        calendarSwitch: false
                    },
                });
            }

            if (window.jQuery) {
                window.jQuery(function () {
                    initJalaliPicker();
                });
            } else {
                initJalaliPicker();
            }
        })();
    </script>
@endpush
