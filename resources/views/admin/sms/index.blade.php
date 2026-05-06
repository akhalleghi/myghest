@extends('layouts.admin.app')

@section('title', 'مدیریت پیامک')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .sms-page { max-width: 100%; }
        .sms-title { margin: 0 0 0.9rem; font-size: 1.08rem; font-weight: 800; color: var(--text); }
        .sms-sub { margin: 0 0 0.8rem; font-size: 0.84rem; color: var(--muted); }
        .sms-tabs { display: flex; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .sms-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.75rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); cursor: pointer; font-family: inherit; }
        .sms-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); }
        .sms-tab.is-disabled { opacity: 0.55; cursor: not-allowed; }
        .sms-tab-panel[hidden] { display: none !important; }
        .sms-panel-select-card { border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); padding: 0.75rem 0.85rem; margin-bottom: 0.8rem; }
        .sms-panel-select-head { font-size: 0.8rem; font-weight: 800; color: var(--text); margin-bottom: 0.2rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-panel-select-sub { margin: 0 0 0.55rem; color: var(--muted); font-size: 0.74rem; }
        .sms-panel-select-field { max-width: 22rem; width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.5rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-conn-badge { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.74rem; font-weight: 800; border-radius: 999px; padding: 0.25rem 0.56rem; margin-bottom: 0.5rem; }
        .sms-conn-badge--connected { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-conn-badge--disconnected { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
        .sms-conn-badge--not-configured { background: rgba(148, 163, 184, 0.18); color: #475569; }
        .sms-settings-form { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: end; }
        .sms-settings-field { min-width: 14rem; flex: 1 1 16rem; }
        .sms-settings-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-settings-field input, .sms-settings-field select { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.5rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-field-error { margin-top: 0.22rem; font-size: 0.72rem; color: #b91c1c; font-weight: 700; }
        .sms-settings-submit { border: none; border-radius: 0.62rem; padding: 0.52rem 1rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
        .sms-settings-note { margin: 0 0 0.6rem; font-size: 0.74rem; color: var(--muted); }
        .sms-template-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .sms-template-toolbar-note { margin: 0; font-size: 0.75rem; color: var(--muted); }
        .sms-template-add-btn { border: none; border-radius: 0.62rem; padding: 0.5rem 0.9rem; background: linear-gradient(180deg, #2563eb, #1d4ed8); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.38rem; }
        .sms-template-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 0.85rem; }
        .sms-template-item { border: 1px solid var(--border); border-radius: 0.9rem; background: var(--bg-card); padding: 0.78rem 0.82rem; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05); transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease; display: flex; flex-direction: column; min-height: 100%; }
        .sms-template-item:hover { transform: translateY(-2px); border-color: rgba(37, 99, 235, 0.35); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1); }
        html[data-theme="dark"] .sms-template-item { box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22); }
        html[data-theme="dark"] .sms-template-item:hover { box-shadow: 0 12px 28px rgba(0, 0, 0, 0.34); border-color: rgba(96, 165, 250, 0.42); }
        .sms-template-item-head { display: flex; justify-content: space-between; align-items: center; gap: 0.45rem; margin-bottom: 0.25rem; }
        .sms-template-item-title { margin: 0; font-size: 0.83rem; font-weight: 800; color: var(--text); line-height: 1.45; }
        .sms-template-item-meta { margin: 0 0 0.48rem; font-size: 0.72rem; color: var(--muted); }
        .sms-template-system-badge { display: inline-flex; align-items: center; gap: 0.24rem; border-radius: 999px; padding: 0.18rem 0.46rem; font-size: 0.67rem; font-weight: 800; color: #0369a1; background: rgba(14, 165, 233, 0.14); margin-inline-start: 0.3rem; }
        .sms-template-item-body-wrap { background: var(--primary-soft); border-radius: 0.7rem; padding: 0.52rem 0.58rem; border: 1px dashed rgba(148, 163, 184, 0.35); flex: 1; }
        .sms-template-item-body { margin: 0; font-size: 0.75rem; color: var(--text); line-height: 1.75; white-space: pre-wrap; word-break: break-word; }
        .sms-template-item-actions { margin-top: 0.62rem; display: flex; gap: 0.4rem; }
        .sms-template-action-btn { border: 1px solid var(--border); border-radius: 0.55rem; padding: 0.34rem 0.62rem; font-size: 0.72rem; font-weight: 700; background: var(--bg-card); color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none; transition: background 0.12s ease, border-color 0.12s ease; }
        .sms-template-action-btn:hover { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); }
        .sms-template-action-btn--danger { color: #b91c1c; border-color: rgba(239, 68, 68, 0.32); }
        .sms-template-action-btn--danger:hover { background: rgba(248, 113, 113, 0.14); border-color: rgba(239, 68, 68, 0.4); }
        .sms-template-empty { border: 1px dashed var(--border); border-radius: 0.75rem; padding: 1rem; font-size: 0.8rem; color: var(--muted); text-align: center; background: var(--bg-card); }
        .sms-template-modal-overlay { position: fixed; inset: 0; z-index: 1300; background: rgba(15, 23, 42, 0.54); display: grid; place-items: center; padding: 0.9rem; }
        .sms-template-modal-overlay[hidden] { display: none !important; }
        .sms-template-modal { width: min(860px, 100%); max-height: min(88vh, 760px); overflow: auto; border: 1px solid var(--border); border-radius: 1rem; background: var(--bg-card); box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24); }
        .sms-template-modal-head { padding: 0.8rem 0.95rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; }
        .sms-template-modal-title { margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--text); }
        .sms-template-close-btn { width: 2rem; height: 2rem; border: 0; border-radius: 0.55rem; background: var(--primary-soft); color: var(--primary-dark); cursor: pointer; }
        .sms-template-modal-body { padding: 0.9rem 0.95rem 1rem; }
        .sms-template-form { display: grid; gap: 0.7rem; }
        .sms-template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
        .sms-template-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-template-field input, .sms-template-field select, .sms-template-field textarea { width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.52rem 0.64rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .sms-template-field textarea { min-height: 8.8rem; resize: vertical; }
        .sms-patterns-label { font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.24rem; }
        .sms-patterns { border: 1px dashed var(--border); border-radius: 0.65rem; padding: 0.5rem; display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .sms-pattern-chip { border: 1px solid rgba(124, 58, 237, 0.28); border-radius: 999px; padding: 0.24rem 0.55rem; font-size: 0.72rem; font-weight: 700; color: #7c3aed; background: rgba(124, 58, 237, 0.11); cursor: pointer; }
        .sms-template-preview { border: 1px solid var(--border); border-radius: 0.62rem; background: var(--bg-card); padding: 0.6rem 0.65rem; min-height: 4.5rem; font-size: 0.78rem; line-height: 1.7; white-space: pre-wrap; color: var(--text); }
        .sms-template-submit { justify-self: start; border: none; border-radius: 0.62rem; padding: 0.52rem 1rem; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
        @media (max-width: 760px) {
            .sms-template-grid { grid-template-columns: 1fr; }
        }

        .sms-filters { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .sms-statuses { display: inline-flex; gap: 0.45rem; flex-wrap: wrap; }
        .sms-status { border: 1px solid var(--border); border-radius: 999px; padding: 0.38rem 0.65rem; font-size: 0.75rem; font-weight: 700; color: var(--muted); text-decoration: none; background: var(--bg-card); }
        .sms-status.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .sms-search { min-width: min(100%, 19rem); flex: 1 1 16rem; max-width: 25rem; }
        .sms-search form { display: flex; gap: 0.45rem; }
        .sms-search input { width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.5rem 0.7rem; font-size: 0.84rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .sms-search button { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.7rem; background: var(--bg-card); color: var(--text); cursor: pointer; }
        .sms-export-btn { border: 1px solid rgba(22, 163, 74, 0.38); border-radius: 0.65rem; padding: 0.48rem 0.72rem; background: rgba(34, 197, 94, 0.14); color: #166534; cursor: pointer; font-size: 0.78rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; }
        .sms-export-btn:hover { background: rgba(34, 197, 94, 0.22); }
        html[data-theme="dark"] .sms-export-btn { color: #86efac; border-color: rgba(74, 222, 128, 0.4); background: rgba(22, 101, 52, 0.34); }

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

        .sms-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.9rem; overflow: visible; }
        .sms-table-wrap { overflow-x: auto; overflow-y: visible; }
        .sms-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .sms-table th, .sms-table td { padding: 0.58rem 0.72rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .sms-table th { white-space: nowrap; background: var(--primary-soft); color: var(--text); font-weight: 800; }
        .sms-msg { max-width: 22rem; line-height: 1.6; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sms-badge { display: inline-block; padding: 0.16rem 0.45rem; border-radius: 0.35rem; font-size: 0.71rem; font-weight: 700; }
        .sms-badge--pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .sms-badge--delivered { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .sms-badge--undelivered { background: rgba(248, 113, 113, 0.2); color: #b91c1c; }
        .sms-action-btn { border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.3rem 0.52rem; font-size: 0.72rem; font-weight: 700; color: var(--text); background: var(--bg-card); cursor: pointer; }
        .sms-actions { position: relative; display: inline-block; }
        .sms-actions-menu { position: fixed; min-width: 9.6rem; z-index: 1500; border: 1px solid var(--border); border-radius: 0.6rem; background: var(--bg-card); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); padding: 0.28rem; }
        .sms-actions-menu[hidden] { display: none !important; }
        .sms-actions-item { width: 100%; text-align: start; border: 0; border-radius: 0.45rem; padding: 0.42rem 0.5rem; font-family: inherit; font-size: 0.74rem; font-weight: 700; background: transparent; color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; }
        .sms-actions-item:hover { background: var(--primary-soft); }
        .sms-actions-item--danger { color: #b91c1c; }
        .sms-actions-item--danger:hover { background: rgba(248, 113, 113, 0.14); }
        .sms-empty { text-align: center; padding: 1.25rem; color: var(--muted); font-size: 0.84rem; }
        .sms-pagination { padding: 0.65rem 0.8rem; }
    </style>
@endpush

@section('content')
    <div class="sms-page">
        <h1 class="sms-title">مدیریت پیامک</h1>
        <p class="sms-sub">گزارش ارسال پیامک‌ها، جستجو و فیلتر وضعیت، و بازهٔ زمانی روزانه/دلخواه.</p>

        <div class="sms-tabs" role="tablist" aria-label="تب‌های مدیریت پیامک">
            <button type="button" class="sms-tab is-active" role="tab" aria-selected="true" data-sms-tab="reports">گزارش پیامک‌ها</button>
            <button type="button" class="sms-tab" role="tab" aria-selected="false" data-sms-tab="templates">الگوهای پیامک</button>
            <button type="button" class="sms-tab" role="tab" aria-selected="false" data-sms-tab="settings">تنظیمات پنل</button>
        </div>

        <section class="sms-tab-panel" data-sms-panel="reports">
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
                    <a
                        class="sms-export-btn"
                        href="{{ route('admin.sms.export-excel', request()->query()) }}"
                    >
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        خروجی اکسل
                    </a>
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
                                <td>
                                    <div class="sms-actions" data-sms-actions>
                                        <button type="button" class="sms-action-btn" data-sms-actions-toggle>
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            عملیات
                                        </button>
                                        <div class="sms-actions-menu" data-sms-actions-menu hidden>
                                            <button
                                                type="button"
                                                class="sms-actions-item"
                                                data-sms-view-detail
                                                data-message="{{ $log->message_text }}"
                                                data-recipient="{{ $log->recipient }}"
                                                data-type="{{ $log->type }}"
                                                data-status="{{ $log->statusLabel() }}"
                                            >
                                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                                جزئیات
                                            </button>
                                            <form method="post" action="{{ route('admin.sms.destroy', $log) }}" data-sms-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sms-actions-item sms-actions-item--danger">
                                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                                    حذف
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
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
        </section>

        <section class="sms-tab-panel" data-sms-panel="templates" hidden>
            <div class="sms-template-toolbar">
                <p class="sms-template-toolbar-note">قالب‌های آماده و سفارشی را مدیریت کنید. پترن‌ها در زمان ارسال با داده واقعی جایگزین می‌شوند.</p>
                <button type="button" class="sms-template-add-btn" id="sms-template-open-modal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    افزودن الگو جدید
                </button>
            </div>

            @if($smsTemplates->isEmpty())
                <div class="sms-template-empty">هنوز قالب پیامکی ثبت نشده است.</div>
            @else
                <div class="sms-template-list">
                    @foreach($smsTemplates as $tpl)
                        <article class="sms-template-item">
                            <div class="sms-template-item-head">
                                <h3 class="sms-template-item-title">{{ $tpl->title }}</h3>
                                @if($tpl->is_system)
                                    <span class="sms-template-system-badge">
                                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                        پیش‌فرض سیستم
                                    </span>
                                @endif
                            </div>
                            <p class="sms-template-item-meta">دسته: {{ $smsTemplateCategories[$tpl->category] ?? $tpl->category }}</p>
                            <div class="sms-template-item-body-wrap">
                                <p class="sms-template-item-body">{{ $tpl->body }}</p>
                            </div>
                            <div class="sms-template-item-actions">
                                <button
                                    type="button"
                                    class="sms-template-action-btn"
                                    data-template-edit
                                    data-template-id="{{ $tpl->id }}"
                                    data-template-title="{{ $tpl->title }}"
                                    data-template-category="{{ $tpl->category }}"
                                    data-template-body="{{ $tpl->body }}"
                                >
                                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                                    ویرایش
                                </button>
                                @unless($tpl->is_system)
                                    <form method="post" action="{{ route('admin.sms.templates.destroy', $tpl) }}" data-template-delete-form>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="sms-template-action-btn sms-template-action-btn--danger">
                                            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                            حذف
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="sms-tab-panel" data-sms-panel="settings" hidden>
            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-tower-cell" aria-hidden="true"></i>
                    انتخاب پنل پیامک
                </div>
                <p class="sms-panel-select-sub">پنل فعال سامانه را انتخاب کنید و اطلاعات اتصال را ذخیره کنید.</p>
                <div class="sms-conn-badge sms-conn-badge--{{ $smsPanelConnectionState['state'] }}">
                    <i class="fa-solid fa-signal" aria-hidden="true"></i>
                    وضعیت اتصال: {{ $smsPanelConnectionState['label'] }}
                </div>
                <p class="sms-settings-note">
                    {{ $smsPanelConnectionState['message'] }}
                    @if($smsPanelLastConnectedAt)
                        <span> - آخرین بررسی: {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(jalali($smsPanelLastConnectedAt)->format('Y/m/d H:i')) }}</span>
                    @endif
                </p>

                <form method="post" action="{{ route('admin.sms.panel-settings.update') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-settings-field">
                        <label for="sms-provider">پنل پیامک</label>
                        <select id="sms-provider" name="provider">
                            @foreach($smsPanelProviders as $providerKey => $providerLabel)
                                <option value="{{ $providerKey }}" @selected(old('provider', $smsPanelSelectedProvider) === $providerKey)>{{ $providerLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-username">نام کاربری پنل</label>
                        <input id="sms-panel-username" type="text" name="username" value="{{ old('username', $smsPanelUsername) }}">
                        @error('username')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-sender-number">شماره فرستنده</label>
                        <input id="sms-panel-sender-number" type="text" name="sender_number" value="{{ old('sender_number', $smsPanelSenderNumber) }}" placeholder="مثال: 50003300">
                        @error('sender_number')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-panel-password">رمز عبور پنل</label>
                        <input id="sms-panel-password" type="password" name="password" value="">
                        @error('password')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i>
                        ذخیره و تست اتصال
                    </button>
                </form>
            </div>

            <div class="sms-panel-select-card">
                <div class="sms-panel-select-head">
                    <i class="fa-solid fa-vial-circle-check" aria-hidden="true"></i>
                    تست پنل پیامک
                </div>
                <p class="sms-panel-select-sub">برای بررسی ارسال واقعی، شماره تماس و متن پیام تست را وارد کنید.</p>

                <form method="post" action="{{ route('admin.sms.panel-test.send') }}" class="sms-settings-form">
                    @csrf
                    <div class="sms-settings-field">
                        <label for="sms-test-recipient">شماره تماس</label>
                        <input id="sms-test-recipient" type="text" name="test_recipient" value="{{ old('test_recipient') }}" placeholder="مثال: 09123456789">
                        @error('test_recipient')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="sms-settings-field">
                        <label for="sms-test-message">متن پیام</label>
                        <input id="sms-test-message" type="text" name="test_message" value="{{ old('test_message', 'پیام تست پنل پیامک') }}">
                        @error('test_message')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>
                    <button class="sms-settings-submit" type="submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال پیام تست
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="sms-template-modal-overlay" id="sms-template-modal-overlay" @if(! ($errors->has('title') || $errors->has('category') || $errors->has('body'))) hidden @endif>
        <div class="sms-template-modal" role="dialog" aria-modal="true" aria-labelledby="sms-template-modal-title">
            <div class="sms-template-modal-head">
                <h2 class="sms-template-modal-title" id="sms-template-modal-title">ایجاد الگوی پیامک</h2>
                <button type="button" class="sms-template-close-btn" id="sms-template-close-modal" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sms-template-modal-body">
                <form method="post" action="{{ route('admin.sms.templates.store') }}" class="sms-template-form" id="sms-template-form">
                    @csrf
                    <input type="hidden" name="_method" id="sms-template-form-method" value="POST">
                    <div class="sms-template-grid">
                        <div class="sms-template-field">
                            <label for="sms-template-title">عنوان قالب *</label>
                            <input id="sms-template-title" type="text" name="title" value="{{ old('title') }}">
                            @error('title')<div class="sms-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="sms-template-field">
                            <label for="sms-template-category">لیست دسته قالب *</label>
                            <select id="sms-template-category" name="category">
                                <option value="">انتخاب کنید</option>
                                @foreach($smsTemplateCategories as $categoryKey => $categoryLabel)
                                    <option value="{{ $categoryKey }}" @selected(old('category') === $categoryKey)>{{ $categoryLabel }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="sms-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <div class="sms-patterns-label">پترن‌های قابل استفاده (برای درج داخل قالب کلیک کنید)</div>
                        <div class="sms-patterns">
                            @foreach($smsTemplatePatterns as $patternKey => $pattern)
                                <button type="button" class="sms-pattern-chip" data-sms-pattern="{{ $patternKey }}">{{ $pattern['label'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="sms-template-field">
                        <label for="sms-template-body">الگوی قالب *</label>
                        <textarea id="sms-template-body" name="body">{{ old('body') }}</textarea>
                        @error('body')<div class="sms-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <div class="sms-patterns-label">نمونه پیامک:</div>
                        <div class="sms-template-preview" id="sms-template-preview"></div>
                    </div>

                    <button class="sms-template-submit" type="submit">ذخیره</button>
                </form>
            </div>
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

            var actionBoxes = Array.from(document.querySelectorAll('[data-sms-actions]'));
            function placeMenu(toggle, menu) {
                var rect = toggle.getBoundingClientRect();
                var gap = 6;
                menu.style.left = '0px';
                menu.style.top = '0px';
                menu.hidden = false;
                var menuRect = menu.getBoundingClientRect();
                var left = rect.right - menuRect.width;
                if (left < 8) left = 8;
                if (left + menuRect.width > window.innerWidth - 8) {
                    left = window.innerWidth - menuRect.width - 8;
                }

                var top = rect.bottom + gap;
                if (top + menuRect.height > window.innerHeight - 8) {
                    top = rect.top - menuRect.height - gap;
                }
                if (top < 8) top = 8;

                menu.style.left = left + 'px';
                menu.style.top = top + 'px';
            }

            actionBoxes.forEach(function (box) {
                var toggle = box.querySelector('[data-sms-actions-toggle]');
                var menu = box.querySelector('[data-sms-actions-menu]');
                if (!toggle || !menu) return;
                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var isHidden = menu.hidden;
                    actionBoxes.forEach(function (otherBox) {
                        var otherMenu = otherBox.querySelector('[data-sms-actions-menu]');
                        if (otherMenu) otherMenu.hidden = true;
                    });
                    if (isHidden) {
                        placeMenu(toggle, menu);
                    } else {
                        menu.hidden = true;
                    }
                });
            });
            document.addEventListener('click', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (menu) menu.hidden = true;
                });
            });
            window.addEventListener('resize', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (!menu || menu.hidden) return;
                    var toggle = box.querySelector('[data-sms-actions-toggle]');
                    if (toggle) placeMenu(toggle, menu);
                });
            });
            window.addEventListener('scroll', function () {
                actionBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-sms-actions-menu]');
                    if (!menu || menu.hidden) return;
                    var toggle = box.querySelector('[data-sms-actions-toggle]');
                    if (toggle) placeMenu(toggle, menu);
                });
            }, true);

            document.querySelectorAll('[data-sms-view-detail]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.AdminSwal) return;
                    AdminSwal.info(
                        'گیرنده: ' + (btn.getAttribute('data-recipient') || '—')
                        + '\nنوع: ' + (btn.getAttribute('data-type') || '—')
                        + '\nوضعیت: ' + (btn.getAttribute('data-status') || '—')
                        + '\n\nمتن پیام:\n' + (btn.getAttribute('data-message') || '')
                    , 'جزئیات پیامک');
                });
            });

            document.querySelectorAll('[data-sms-delete-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (!window.AdminSwal) {
                        form.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف پیامک',
                        text: 'این رکورد حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            var tabButtons = Array.from(document.querySelectorAll('[data-sms-tab]'));
            var tabPanels = Array.from(document.querySelectorAll('[data-sms-panel]'));
            function activateTab(tabId) {
                tabButtons.forEach(function (btn) {
                    var isActive = btn.getAttribute('data-sms-tab') === tabId;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                tabPanels.forEach(function (tabPanel) {
                    tabPanel.hidden = tabPanel.getAttribute('data-sms-panel') !== tabId;
                });
            }
            tabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activateTab(btn.getAttribute('data-sms-tab'));
                });
            });
            activateTab(@json(($errors->has('title') || $errors->has('category') || $errors->has('body')) ? 'templates' : ($errors->any() ? 'settings' : session('sms_active_tab', 'reports'))));

            var templateOverlay = document.getElementById('sms-template-modal-overlay');
            var templateOpenBtn = document.getElementById('sms-template-open-modal');
            var templateCloseBtn = document.getElementById('sms-template-close-modal');
            var templateBody = document.getElementById('sms-template-body');
            var templatePreview = document.getElementById('sms-template-preview');
            var templateForm = document.getElementById('sms-template-form');
            var templateMethodInput = document.getElementById('sms-template-form-method');
            var templateModalTitle = document.getElementById('sms-template-modal-title');
            var templateTitleInput = document.getElementById('sms-template-title');
            var templateCategoryInput = document.getElementById('sms-template-category');
            var templatePatterns = @json($smsTemplatePatterns);
            var templateStoreUrl = @json(route('admin.sms.templates.store'));
            var templateUpdateUrlTpl = @json(route('admin.sms.templates.update', ['smsTemplate' => '__ID__']));
            function openTemplateModal() {
                if (!templateOverlay) return;
                templateOverlay.hidden = false;
            }
            function closeTemplateModal() {
                if (!templateOverlay) return;
                templateOverlay.hidden = true;
            }
            function renderTemplatePreview() {
                if (!templateBody || !templatePreview) return;
                var text = templateBody.value || '';
                Object.keys(templatePatterns).forEach(function (key) {
                    var pattern = templatePatterns[key];
                    var tokenRegex = new RegExp('\\{\\{\\s*' + key + '\\s*\\}\\}', 'gi');
                    text = text.replace(tokenRegex, pattern.sample);
                });
                templatePreview.textContent = text.trim() !== '' ? text : 'پیش‌نمایش پیامک اینجا نمایش داده می‌شود.';
            }
            if (templateOpenBtn) {
                templateOpenBtn.addEventListener('click', function () {
                    if (templateForm) templateForm.action = templateStoreUrl;
                    if (templateMethodInput) templateMethodInput.value = 'POST';
                    if (templateModalTitle) templateModalTitle.textContent = 'ایجاد الگوی پیامک';
                    if (templateTitleInput) templateTitleInput.value = '';
                    if (templateCategoryInput) templateCategoryInput.value = '';
                    if (templateBody) templateBody.value = '';
                    openTemplateModal();
                    activateTab('templates');
                    renderTemplatePreview();
                });
            }
            if (templateCloseBtn) {
                templateCloseBtn.addEventListener('click', closeTemplateModal);
            }
            if (templateOverlay) {
                templateOverlay.addEventListener('click', function (event) {
                    if (event.target === templateOverlay) closeTemplateModal();
                });
            }
            document.querySelectorAll('[data-sms-pattern]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (!templateBody) return;
                    var key = chip.getAttribute('data-sms-pattern');
                    if (!key) return;
                    var token = '{' + '{' + key + '}' + '}';
                    var start = templateBody.selectionStart || 0;
                    var end = templateBody.selectionEnd || 0;
                    var value = templateBody.value || '';
                    templateBody.value = value.slice(0, start) + token + value.slice(end);
                    var nextPos = start + token.length;
                    templateBody.setSelectionRange(nextPos, nextPos);
                    templateBody.focus();
                    renderTemplatePreview();
                });
            });
            document.querySelectorAll('[data-template-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-template-id') || '';
                    if (!id) return;
                    if (templateForm) templateForm.action = templateUpdateUrlTpl.replace('__ID__', id);
                    if (templateMethodInput) templateMethodInput.value = 'PUT';
                    if (templateModalTitle) templateModalTitle.textContent = 'ویرایش الگوی پیامک';
                    if (templateTitleInput) templateTitleInput.value = btn.getAttribute('data-template-title') || '';
                    if (templateCategoryInput) templateCategoryInput.value = btn.getAttribute('data-template-category') || '';
                    if (templateBody) templateBody.value = btn.getAttribute('data-template-body') || '';
                    openTemplateModal();
                    activateTab('templates');
                    renderTemplatePreview();
                });
            });
            document.querySelectorAll('[data-template-delete-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (!window.AdminSwal) {
                        form.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف الگوی پیامک',
                        text: 'این الگو حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
            if (templateBody) {
                templateBody.addEventListener('input', renderTemplatePreview);
            }
            renderTemplatePreview();

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
