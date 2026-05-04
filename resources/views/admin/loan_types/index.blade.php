@php
    use App\Models\LoanType;
@endphp
@extends('layouts.admin.app')

@section('title', 'تعریف انواع وام')

@push('head')
    <style>
        .lt-page { max-width: 100%; }
        .lt-title { margin: 0 0 0.9rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .lt-sub { margin: 0 0 0.75rem; font-size: 0.86rem; color: var(--muted); }
        .lt-flash { margin: 0 0 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; background: var(--primary-soft); color: var(--text); font-size: 0.84rem; font-weight: 600; border: 1px solid var(--border); }
        .lt-errs { margin: 0 0 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; background: rgba(254, 242, 242, 0.95); border: 1px solid rgba(248, 113, 113, 0.5); color: #7f1d1d; font-size: 0.8rem; }
        html[data-theme="dark"] .lt-errs { background: rgba(127, 29, 29, 0.25); color: #fecaca; border-color: rgba(248, 113, 113, 0.35); }
        .lt-errs ul { margin: 0; padding-inline-start: 1.1rem; }
        .lt-errs--modal { margin: 0 0 1rem; }
        .lt-errs__title { margin: 0 0 0.45rem; font-size: 0.82rem; font-weight: 800; color: inherit; }
        .lt-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.65rem; margin-bottom: 0.85rem; }
        .lt-search { flex: 1 1 12rem; min-width: 0; max-width: 22rem; }
        .lt-search input { width: 100%; padding: 0.55rem 0.75rem; border-radius: 0.7rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); font-size: 0.86rem; font-family: inherit; }
        .lt-search input:focus { outline: none; border-color: rgba(37, 99, 235, 0.45); box-shadow: 0 0 0 3px var(--primary-soft, rgba(37, 99, 235, 0.12)); }
        .lt-btn-add { font-family: inherit; font-size: 0.84rem; font-weight: 700; padding: 0.55rem 1rem; border-radius: 0.7rem; border: none; cursor: pointer; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.28); white-space: nowrap; }
        .lt-btn-add:hover { filter: brightness(1.04); }
        .lt-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.95rem; overflow: hidden; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05); }
        html[data-theme="dark"] .lt-card { box-shadow: 0 10px 28px rgba(0, 0, 0, 0.25); }
        .lt-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .lt-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .lt-table th, .lt-table td { padding: 0.62rem 0.85rem; border-bottom: 1px solid var(--border); text-align: start; }
        .lt-table th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        html[data-theme="dark"] .lt-table th { background: rgba(30, 41, 59, 0.95); }
        .lt-table td { color: var(--muted); }
        .lt-table tbody tr.lt-row--hidden { display: none; }
        .lt-num { direction: ltr; unicode-bidi: isolate; display: inline-block; }
        .lt-badge { display: inline-block; padding: 0.18rem 0.45rem; border-radius: 0.4rem; font-size: 0.72rem; font-weight: 700; }
        .lt-badge--on { background: rgba(16, 185, 129, 0.15); color: #059669; }
        html[data-theme="dark"] .lt-badge--on { color: #34d399; }
        .lt-badge--off { background: rgba(148, 163, 184, 0.2); color: var(--muted); }
        .lt-badge--warn { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        html[data-theme="dark"] .lt-badge--warn { color: #fbbf24; }
        .lt-empty { padding: 1.25rem; text-align: center; color: var(--muted); font-size: 0.86rem; }
        .lt-cell-title__main { font-weight: 700; color: var(--text); font-size: 0.88rem; }
        .lt-cell-title__sub { font-size: 0.72rem; color: var(--muted); margin-top: 0.28rem; line-height: 1.55; }
        .lt-settings-list { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.78rem; }
        .lt-settings-row { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
        .lt-settings-row span.lt-muted { color: var(--muted); font-weight: 600; }
        .lt-ico-ok { color: #059669; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; width: 1.15rem; }
        html[data-theme="dark"] .lt-ico-ok { color: #34d399; }
        .lt-ico-no { color: #94a3b8; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; width: 1.15rem; }
        .lt-ops { white-space: nowrap; text-align: center; }
        .lt-ops-inner { display: inline-flex; align-items: center; gap: 0.35rem; }
        .lt-ops-btn { font-family: inherit; cursor: pointer; border: 1px solid var(--border); background: var(--bg-card); color: var(--primary-dark); width: 2.15rem; height: 2.15rem; border-radius: 0.55rem; display: inline-grid; place-items: center; font-size: 0.92rem; vertical-align: middle; }
        .lt-ops-btn:hover { filter: brightness(0.97); border-color: rgba(37, 99, 235, 0.35); }
        .lt-ops-btn--danger { color: #b91c1c; border-color: rgba(248, 113, 113, 0.45); background: rgba(254, 242, 242, 0.6); }
        html[data-theme="dark"] .lt-ops-btn--danger { color: #fca5a5; background: rgba(127, 29, 29, 0.35); border-color: rgba(248, 113, 113, 0.3); }

        /* مودال */
        .lt-modal { position: fixed; inset: 0; z-index: 1300; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(2px); }
        html[data-theme="dark"] .lt-modal { background: rgba(0, 0, 0, 0.55); }
        .lt-modal[hidden] { display: none !important; }
        .lt-modal__box { background: var(--bg-card); color: var(--text); border: 1px solid var(--border); border-radius: 1rem; width: min(100%, 42rem); max-height: min(92vh, 720px); min-height: 0; display: flex; flex-direction: column; box-shadow: 0 28px 70px rgba(15, 23, 42, 0.18); }
        html[data-theme="dark"] .lt-modal__box { box-shadow: 0 28px 70px rgba(0, 0, 0, 0.45); }
        .lt-modal__form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
        .lt-modal__head { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-shrink: 0; }
        .lt-modal__title { margin: 0; font-size: 1rem; font-weight: 800; }
        .lt-modal__close { border: none; background: var(--primary-soft); color: var(--primary-dark); width: 2.15rem; height: 2.15rem; border-radius: 0.55rem; cursor: pointer; display: grid; place-items: center; font-size: 1rem; }
        .lt-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.55rem 0.75rem 0; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg-card); }
        .lt-tab { font-family: inherit; font-size: 0.78rem; font-weight: 700; padding: 0.45rem 0.75rem; border-radius: 0.55rem 0.55rem 0 0; border: 1px solid transparent; border-bottom: none; background: transparent; color: var(--muted); cursor: pointer; }
        .lt-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: var(--border); }
        .lt-modal__body { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; padding: 0.85rem 1rem; -webkit-overflow-scrolling: touch; }
        .lt-tab-panel { display: none; }
        .lt-tab-panel.is-active { display: block; }
        .lt-tab-panel--muted { font-size: 0.84rem; color: var(--muted); padding: 1rem 0; text-align: center; }
        .lt-field { margin-bottom: 0.85rem; }
        .lt-field label { display: block; font-size: 0.76rem; font-weight: 650; color: var(--muted); margin-bottom: 0.32rem; }
        .lt-field .req { color: #dc2626; font-weight: 800; }
        .lt-input, .lt-select, .lt-textarea { width: 100%; padding: 0.52rem 0.72rem; border-radius: 0.65rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); font-size: 0.86rem; font-family: inherit; }
        .lt-input:focus, .lt-select:focus, .lt-textarea:focus { outline: none; border-color: rgba(37, 99, 235, 0.45); box-shadow: 0 0 0 3px var(--primary-soft); }
        .lt-textarea { min-height: 4.5rem; max-height: 12rem; resize: vertical; box-sizing: border-box; }
        .lt-hint { margin: 0.28rem 0 0; font-size: 0.7rem; color: var(--muted); }
        .lt-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem 1rem; }
        @media (max-width: 640px) { .lt-grid2 { grid-template-columns: 1fr; } }
        .lt-radio-group label { display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.4rem; cursor: pointer; font-size: 0.82rem; font-weight: 600; color: var(--text); }
        .lt-radio-group input { accent-color: var(--primary); width: 1rem; height: 1rem; }
        .lt-subpanel { margin-top: 0.65rem; padding: 0.65rem 0.75rem; border-radius: 0.65rem; border: 1px dashed var(--border); background: var(--primary-soft); }
        html[data-theme="dark"] .lt-subpanel { background: rgba(30, 41, 59, 0.45); }
        .lt-subpanel[hidden] { display: none !important; }
        .lt-repeater-head { font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.45rem; }
        .lt-repeater-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.45rem; align-items: end; margin-bottom: 0.45rem; }
        @media (max-width: 520px) { .lt-repeater-row { grid-template-columns: 1fr 1fr; } .lt-repeater-row .lt-btn-icon { grid-column: 1 / -1; justify-self: stretch; } }
        .lt-btn-row { font-family: inherit; font-size: 0.74rem; font-weight: 700; padding: 0.42rem 0.65rem; border-radius: 0.55rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); cursor: pointer; }
        .lt-btn-icon { min-height: 2.35rem; }
        .lt-spec-options { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .lt-modal__foot { padding: 0.75rem 1rem; border-top: 1px solid var(--border); flex-shrink: 0; background: var(--bg-card); }
        .lt-check { display: flex; align-items: flex-start; gap: 0.45rem; margin-bottom: 0.55rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; user-select: none; }
        .lt-check input { accent-color: var(--primary); margin-top: 0.15rem; flex-shrink: 0; width: 1rem; height: 1rem; }
        .lt-modal__actions { display: flex; flex-wrap: wrap; gap: 0.45rem; justify-content: flex-end; margin-top: 0; }
        .lt-btn { font-family: inherit; font-size: 0.82rem; font-weight: 700; padding: 0.52rem 1rem; border-radius: 0.65rem; cursor: pointer; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); }
        .lt-btn--primary { border: none; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; }
        .lt-field-error { font-size: 0.72rem; color: #dc2626; margin-top: 0.25rem; }
        html[data-theme="dark"] .lt-field-error { color: #fca5a5; }
        .lt-plan-toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 0.65rem 0.75rem; border-radius: 0.65rem; border: 1px solid var(--border); background: var(--primary-soft); margin-bottom: 1rem; }
        html[data-theme="dark"] .lt-plan-toggle-row { background: rgba(30, 41, 59, 0.45); }
        .lt-plan-toggle-text { font-size: 0.84rem; font-weight: 700; color: var(--text); flex: 1 1 12rem; }
        .lt-switch { position: relative; display: inline-block; width: 3.1rem; height: 1.65rem; flex-shrink: 0; }
        .lt-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .lt-switch-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 999px; transition: 0.2s; border: 1px solid var(--border); }
        .lt-switch-slider::before { position: absolute; content: ""; height: 1.2rem; width: 1.2rem; right: 0.22rem; bottom: 0.15rem; background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
        .lt-switch input:checked + .lt-switch-slider { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); border-color: transparent; }
        .lt-switch input:checked + .lt-switch-slider::before { transform: translateX(-1.35rem); }
        .lt-plan-fields-wrap { margin-top: 0.25rem; }
        .lt-plan-image-card { padding: 0.75rem; border-radius: 0.65rem; border: 1px dashed var(--border); background: var(--bg-card); }
        .lt-plan-preview-box { margin-bottom: 0.65rem; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--border); max-width: 22rem; background: rgba(0,0,0,0.03); }
        html[data-theme="dark"] .lt-plan-preview-box { background: rgba(0,0,0,0.2); }
        .lt-plan-preview-img { display: block; width: 100%; height: auto; max-height: 14rem; object-fit: contain; }
        .lt-plan-image-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; }
        .lt-file-input { position: absolute; width: 0.01rem; height: 0.01rem; opacity: 0; overflow: hidden; z-index: -1; }
        .lt-btn--sm { font-size: 0.78rem; padding: 0.42rem 0.72rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .lt-body-field textarea.lt-textarea { min-height: 10rem; max-height: 22rem; }
    </style>
@endpush

@section('content')
    @php
        $ltAllowedRows = old('allowed_rows');
        if (! is_array($ltAllowedRows) || count($ltAllowedRows) === 0) {
            $ltAllowedRows = [['months' => '', 'cap' => '']];
        }
        $ltMaxLoanDisplay = '';
        $ltMaxLoanOld = old('max_loan_amount');
        if ($ltMaxLoanOld !== null && $ltMaxLoanOld !== '') {
            $ltMaxLoanDigits = preg_replace('/\D+/', '', (string) $ltMaxLoanOld);
            $ltMaxLoanDisplay = $ltMaxLoanDigits !== '' ? number_format((int) $ltMaxLoanDigits, 0, '.', ',') : '';
        }
        $ltEditingId = old('loan_type_id');
        $ltRoutePlaceholder = 999999001;
        $ltUpdateUrlTemplate = str_replace((string) $ltRoutePlaceholder, '__ID__', route('admin.loan-types.update', ['loanType' => $ltRoutePlaceholder]));
    @endphp
    <div class="lt-page">
        <h1 class="lt-title">وام‌های من</h1>
        <p class="lt-sub">تعریف انواع وام؛ داده‌ها در پایگاه ذخیره می‌شوند.</p>

        <div class="lt-toolbar">
            <div class="lt-search">
                <input
                    type="search"
                    id="loan-type-table-search"
                    placeholder="جستجو در عنوان، نحوه سود، بهره و وضعیت…"
                    autocomplete="off"
                    aria-label="جستجو در جدول"
                    aria-controls="loan-types-tbody"
                >
            </div>
            <button type="button" class="lt-btn-add" id="loan-type-add-btn">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                افزودن وام
            </button>
        </div>

        <div class="lt-card">
            <div class="lt-table-wrap">
                <table class="lt-table">
                    <thead>
                        <tr>
                            <th scope="col">شماره</th>
                            <th scope="col">عنوان وام</th>
                            <th scope="col">سایر تنظیمات</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="loan-types-tbody">
                        @forelse ($loanTypes as $lt)
                            @php
                                $rep = $lt->repayment_periods ?? [];
                                $repSummary = match ($rep['type'] ?? '') {
                                    LoanType::REPAY_UNLIMITED => 'بدون محدودیت',
                                    LoanType::REPAY_MAX_UNTIL => 'حداکثر تا '.$rep['max_months'].' ماه',
                                    LoanType::REPAY_ALLOWED_MONTHS => count($rep['allowed_rows'] ?? []).' ردیف ماه/سقف',
                                    default => '—',
                                };
                                $searchBlob = mb_strtolower(
                                    $lt->title.' '.$lt->profitCalculationLabel().' '.$lt->interest_rate
                                    .' '.$lt->daily_late_coefficient.' '.$lt->daily_early_coefficient
                                    .' '.($lt->sms_reminder_enabled ? 'پیامک فعال' : 'پیامک خاموش')
                                    .' '.($lt->registration_suspended ? 'غیرفعال ثبت' : 'فعال ثبت')
                                    .' '.$repSummary,
                                    'UTF-8',
                                );
                                $subLine = 'نوع بهره: '.$lt->profitCalculationLabel()
                                    .' · درصد بهره: '.(string) $lt->interest_rate
                                    .' · ضریب دیرکرد: '.$lt->daily_late_coefficient
                                    .' · ضریب زودکرد: '.$lt->daily_early_coefficient;
                            @endphp
                            <tr class="lt-row" data-search="{{ e($searchBlob) }}">
                                <td><span class="lt-num">{{ $loop->iteration }}</span></td>
                                <td>
                                    <div class="lt-cell-title__main">{{ $lt->title }}</div>
                                    <div class="lt-cell-title__sub">{{ $subLine }}</div>
                                </td>
                                <td>
                                    <div class="lt-settings-list">
                                        <div class="lt-settings-row">
                                            <span class="lt-muted">پیامک یادآوری:</span>
                                            @if ($lt->sms_reminder_enabled)
                                                <span class="lt-ico-ok" title="فعال" aria-label="فعال">✓</span>
                                                <span class="lt-muted">فعال</span>
                                            @else
                                                <span class="lt-ico-no" title="غیرفعال" aria-label="غیرفعال">✗</span>
                                                <span class="lt-muted">غیرفعال</span>
                                            @endif
                                        </div>
                                        <div class="lt-settings-row">
                                            <span class="lt-muted">وضعیت وام:</span>
                                            @if ($lt->registration_suspended)
                                                <span class="lt-ico-no" title="غیرفعال" aria-label="غیرفعال">✗</span>
                                                <span class="lt-muted">غیرفعال</span>
                                            @else
                                                <span class="lt-ico-ok" title="فعال" aria-label="فعال">✓</span>
                                                <span class="lt-muted">فعال</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="lt-ops">
                                    <div class="lt-ops-inner">
                                        <button type="button" class="lt-ops-btn lt-ops-btn--edit" data-lt-edit="{{ $lt->id }}" title="ویرایش" aria-label="ویرایش">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="lt-ops-btn lt-ops-btn--danger" data-lt-delete="{{ $lt->id }}" title="حذف" aria-label="حذف">
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <form id="lt-del-{{ $lt->id }}" method="post" action="{{ route('admin.loan-types.destroy', $lt) }}" class="lt-inline-form" hidden aria-hidden="true">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="lt-empty">هنوز نوع وامی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- مودال افزودن نوع وام --}}
    <div id="loan-type-modal" class="lt-modal" hidden aria-hidden="true">
        <div class="lt-modal__box" role="document">
            <div class="lt-modal__head">
                <h2 class="lt-modal__title" id="loan-type-modal-title">{{ $ltEditingId ? 'ویرایش نوع وام' : 'افزودن نوع وام' }}</h2>
                <button type="button" class="lt-modal__close" id="loan-type-modal-close" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="lt-tabs" role="tablist">
                <button type="button" class="lt-tab is-active" role="tab" aria-selected="true" data-lt-tab="spec">مشخصات وام</button>
                <button type="button" class="lt-tab" role="tab" aria-selected="false" data-lt-tab="plan">اطلاعات طرح</button>
                <button type="button" class="lt-tab" role="tab" aria-selected="false" data-lt-tab="docs">مدارک لازم</button>
            </div>

            <form
                method="post"
                action="{{ $ltEditingId ? route('admin.loan-types.update', $ltEditingId) : route('admin.loan-types.store') }}"
                id="loan-type-store-form"
                class="lt-modal__form"
                enctype="multipart/form-data"
                novalidate
            >
                @csrf
                @if ($ltEditingId)
                    @method('PUT')
                @endif
                <input type="hidden" name="loan_type_id" id="lt-hidden-loan-type-id" value="{{ $ltEditingId ?? '' }}" @if (! $ltEditingId) disabled @endif>

                <div class="lt-modal__body">
                    @if ($errors->any())
                        <div class="lt-errs lt-errs--modal" role="alert">
                            <p class="lt-errs__title">لطفاً موارد زیر را اصلاح کنید:</p>
                            <ul>
                                @foreach ($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="lt-tab-panel is-active" role="tabpanel" id="lt-panel-spec" data-lt-panel="spec">
                        <div class="lt-field">
                            <label for="lt-title">عنوان وام <span class="req">*</span></label>
                            <input class="lt-input" type="text" name="title" id="lt-title" required maxlength="255"
                                value="{{ old('title') }}" autocomplete="off">
                            @error('title')<div class="lt-field-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="lt-field">
                            <label for="lt-profit-method">نحوه محاسبه سود <span class="req">*</span></label>
                            <select class="lt-select" name="profit_calculation_method" id="lt-profit-method" required>
                                <option value="{{ LoanType::PROFIT_MONTHLY }}" @selected(old('profit_calculation_method', LoanType::PROFIT_MONTHLY) === LoanType::PROFIT_MONTHLY)>سود ماهانه</option>
                                <option value="{{ LoanType::PROFIT_BANK }}" @selected(old('profit_calculation_method') === LoanType::PROFIT_BANK)>سود بانکی</option>
                            </select>
                            @error('profit_calculation_method')<div class="lt-field-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="lt-grid2">
                            <div class="lt-field">
                                <label for="lt-interest">درصد بهره <span class="req">*</span></label>
                                <input class="lt-input" type="number" name="interest_rate" id="lt-interest" required
                                    min="0" max="100" step="0.01" value="{{ old('interest_rate') }}" placeholder="مثلاً ۱۸">
                                @error('interest_rate')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field">
                                <label for="lt-max-amount">سقف مبلغ وام (تومان)</label>
                                <input class="lt-input lt-num" type="text" name="max_loan_amount" id="lt-max-amount"
                                    inputmode="numeric" autocomplete="off" value="{{ $ltMaxLoanDisplay }}"
                                    placeholder="مثال: ۱۰,۰۰۰,۰۰۰">
                                @error('max_loan_amount')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="lt-grid2">
                            <div class="lt-field">
                                <label for="lt-late-coef">ضریب دیرکرد روزانه <span class="req">*</span></label>
                                <input class="lt-input lt-num" type="number" name="daily_late_coefficient" id="lt-late-coef" required
                                    min="0" step="0.000001" value="{{ old('daily_late_coefficient', '0.008') }}">
                                <p class="lt-hint">عدد اعشاری مانند ۰٫۰۰۸ برای محاسبه جریمه روزانه دیرکرد.</p>
                                @error('daily_late_coefficient')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field">
                                <label for="lt-early-coef">ضریب زودکرد روزانه <span class="req">*</span></label>
                                <input class="lt-input lt-num" type="number" name="daily_early_coefficient" id="lt-early-coef" required
                                    min="0" step="0.000001" value="{{ old('daily_early_coefficient', '0.008') }}">
                                <p class="lt-hint">برای تشویق بازپرداخت زودهنگام طبق سیاست مؤسسه.</p>
                                @error('daily_early_coefficient')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="lt-grid2">
                            <div class="lt-field">
                                <label for="lt-max-gap">حداکثر فاصله اقساط</label>
                                <input class="lt-input lt-num" type="number" name="max_installment_gap" id="lt-max-gap"
                                    min="1" step="1" value="{{ old('max_installment_gap') }}" placeholder="اختیاری">
                                @error('max_installment_gap')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field">
                                <label for="lt-gap-unit">نوع فاصله اقساط <span class="req">*</span></label>
                                <select class="lt-select" name="installment_gap_unit" id="lt-gap-unit" required>
                                    <option value="{{ LoanType::GAP_MONTHLY }}" @selected(old('installment_gap_unit', LoanType::GAP_MONTHLY) === LoanType::GAP_MONTHLY)>ماهانه</option>
                                    <option value="{{ LoanType::GAP_WEEKLY }}" @selected(old('installment_gap_unit') === LoanType::GAP_WEEKLY)>هفتگی</option>
                                </select>
                                @error('installment_gap_unit')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="lt-field">
                            <span class="lt-repeater-head">دوره‌های بازپرداخت مجاز <span class="req">*</span></span>
                            <div class="lt-radio-group" id="lt-repay-rule-group">
                                <label>
                                    <input type="radio" name="repayment_rule_type" value="{{ LoanType::REPAY_UNLIMITED }}"
                                        @checked(old('repayment_rule_type', LoanType::REPAY_UNLIMITED) === LoanType::REPAY_UNLIMITED)>
                                    بدون محدودیت
                                </label>
                                <label>
                                    <input type="radio" name="repayment_rule_type" value="{{ LoanType::REPAY_MAX_UNTIL }}"
                                        @checked(old('repayment_rule_type') === LoanType::REPAY_MAX_UNTIL)>
                                    حداکثر تا (تعداد ماه)
                                </label>
                                <label>
                                    <input type="radio" name="repayment_rule_type" value="{{ LoanType::REPAY_ALLOWED_MONTHS }}"
                                        @checked(old('repayment_rule_type') === LoanType::REPAY_ALLOWED_MONTHS)>
                                    تعیین ماه‌های مجاز (چند ردیف ماه + سقف مبلغ)
                                </label>
                            </div>
                            @error('repayment_rule_type')<div class="lt-field-error">{{ $message }}</div>@enderror

                            <div id="lt-repay-max-wrap" class="lt-subpanel" hidden>
                                <label for="lt-repay-max-months">حداکثر تعداد ماه بازپرداخت <span class="req">*</span></label>
                                <input class="lt-input lt-num" type="number" name="repayment_max_months" id="lt-repay-max-months"
                                    min="1" step="1" value="{{ old('repayment_max_months') }}" placeholder="مثلاً ۳۶">
                                @error('repayment_max_months')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>

                            <div id="lt-repay-allowed-wrap" class="lt-subpanel" hidden>
                                <div class="lt-repeater-head">ردیف‌ها (ماه مجاز / سقف مبلغ به تومان)</div>
                                <div id="lt-allowed-rows">
                                    @foreach ($ltAllowedRows as $ri => $row)
                                        <div class="lt-repeater-row" data-lt-allowed-row>
                                            <div class="lt-field" style="margin:0">
                                                <label class="lt-hint">ماه</label>
                                                <input class="lt-input lt-num" type="number" name="allowed_rows[{{ $ri }}][months]"
                                                    min="1" step="1" value="{{ $row['months'] ?? '' }}">
                                            </div>
                                            <div class="lt-field" style="margin:0">
                                                <label class="lt-hint">سقف (تومان)</label>
                                                <input class="lt-input lt-num" type="number" name="allowed_rows[{{ $ri }}][cap]"
                                                    min="0" step="1" value="{{ $row['cap'] ?? '' }}">
                                            </div>
                                            <button type="button" class="lt-btn-row lt-btn-icon lt-remove-allowed" aria-label="حذف ردیف">حذف</button>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="lt-btn-row" id="lt-add-allowed-row" style="margin-top:0.35rem">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> افزودن ردیف
                                </button>
                                @error('allowed_rows')<div class="lt-field-error">{{ $message }}</div>@enderror
                                @error('allowed_rows.*.months')<div class="lt-field-error">{{ $message }}</div>@enderror
                                @error('allowed_rows.*.cap')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="lt-spec-options" id="lt-spec-options">
                            <label class="lt-check">
                                <input type="checkbox" name="sms_reminder_enabled" value="1" @checked(old('sms_reminder_enabled', true))>
                                پیامک یادآوری این نوع وام فعال باشد.
                            </label>
                            <label class="lt-check">
                                <input type="checkbox" name="registration_suspended" value="1" id="lt-registration-suspended"
                                    @checked(old('registration_suspended'))>
                                ثبت و درخواست این نوع وام تا اطلاع ثانوی غیرفعال باشد.
                            </label>
                            <div id="lt-suspended-msg-wrap" class="lt-field" @if(! old('registration_suspended')) hidden @endif>
                                <label for="lt-suspended-msg">متن اطلاع‌رسانی به کاربر در صورت غیرفعال بودن <span class="req">*</span></label>
                                <textarea class="lt-textarea" name="registration_suspended_message" id="lt-suspended-msg" maxlength="2000"
                                    placeholder="مثلاً: امکان ثبت درخواست این وام موقتاً غیرفعال است.">{{ old('registration_suspended_message') }}</textarea>
                                @error('registration_suspended_message')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="lt-tab-panel" role="tabpanel" id="lt-panel-plan" data-lt-panel="plan">
                        <div class="lt-plan-toggle-row">
                            <span class="lt-plan-toggle-text">وام در لیست طرح‌ها قرار بگیرد؟</span>
                            <label class="lt-switch">
                                <input type="checkbox" name="plan_list_enabled" value="1" id="lt-plan-list-enabled"
                                    @checked(old('plan_list_enabled'))>
                                <span class="lt-switch-slider" aria-hidden="true"></span>
                            </label>
                        </div>
                        <input type="hidden" name="plan_remove_image" id="lt-plan-remove-field" value="{{ old('plan_remove_image') ? '1' : '0' }}">

                        <div id="lt-plan-fields-wrap" class="lt-plan-fields-wrap" @if (! old('plan_list_enabled')) hidden @endif>
                            <div class="lt-field">
                                <span class="lt-repeater-head">تصویر طرح</span>
                                <p class="lt-hint">فرمت‌های jpg، png، webp — حداکثر حجم ۵ مگابایت.</p>
                                <div class="lt-plan-image-card">
                                    <div id="lt-plan-preview-box" class="lt-plan-preview-box" hidden>
                                        <img src="" alt="" id="lt-plan-preview-img" class="lt-plan-preview-img" width="320" height="200" loading="lazy">
                                    </div>
                                    <div class="lt-plan-image-actions">
                                        <input type="file" name="plan_image" id="lt-plan-image" class="lt-file-input" accept="image/jpeg,image/png,image/webp">
                                        <button type="button" class="lt-btn lt-btn--sm" id="lt-plan-upload-btn">آپلود تصویر</button>
                                        <button type="button" class="lt-btn lt-btn--sm" id="lt-plan-remove-btn" hidden>حذف تصویر</button>
                                        <a class="lt-btn lt-btn--sm" id="lt-plan-download" href="#" download="plan.jpg" hidden>دانلود</a>
                                    </div>
                                </div>
                                @error('plan_image')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field">
                                <label for="lt-plan-title">عنوان طرح <span class="req">*</span></label>
                                <input class="lt-input" type="text" name="plan_title" id="lt-plan-title" maxlength="255"
                                    value="{{ old('plan_title') }}" placeholder="عنوانی که در لیست طرح‌ها نمایش داده می‌شود" autocomplete="off">
                                @error('plan_title')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field">
                                <label for="lt-plan-summary">توضیحات کوتاه طرح</label>
                                <textarea class="lt-textarea" name="plan_summary" id="lt-plan-summary" maxlength="2000" rows="3"
                                    placeholder="خلاصه برای کارت طرح در لیست">{{ old('plan_summary') }}</textarea>
                                @error('plan_summary')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="lt-field lt-body-field">
                                <label for="lt-plan-body">توضیحات کامل طرح</label>
                                <textarea class="lt-textarea" name="plan_body" id="lt-plan-body" maxlength="50000" rows="8"
                                    placeholder="متن کامل معرفی طرح برای کاربر">{{ old('plan_body') }}</textarea>
                                @error('plan_body')<div class="lt-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="lt-tab-panel" role="tabpanel" id="lt-panel-docs" data-lt-panel="docs">
                        <p class="lt-tab-panel--muted">مدارک لازم در مرحله بعد تکمیل می‌شود.</p>
                    </div>
                </div>

                <div class="lt-modal__foot">
                    <div class="lt-modal__actions">
                        <button type="button" class="lt-btn" id="loan-type-modal-cancel">انصراف</button>
                        <button type="submit" class="lt-btn lt-btn--primary" id="lt-submit-btn">{{ $ltEditingId ? 'ذخیرهٔ تغییرات' : 'ذخیره نوع وام' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var LT = {
                repayUnlimited: @json(\App\Models\LoanType::REPAY_UNLIMITED),
                profitMonthly: @json(\App\Models\LoanType::PROFIT_MONTHLY),
                gapMonthly: @json(\App\Models\LoanType::GAP_MONTHLY),
            };
            var LT_STORE = @json(route('admin.loan-types.store'));
            var LT_UPDATE_TMPL = @json($ltUpdateUrlTemplate);
            var LT_EDIT_MAP = @json($loanEditMap ?? []);

            var input = document.getElementById('loan-type-table-search');
            var tbody = document.getElementById('loan-types-tbody');
            if (input && tbody) {
                function normalize(s) {
                    return (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
                }
                function filter() {
                    var q = normalize(input.value);
                    tbody.querySelectorAll('tr.lt-row').forEach(function (tr) {
                        var hay = tr.getAttribute('data-search') || '';
                        if (!q) { tr.classList.remove('lt-row--hidden'); return; }
                        var match = hay.indexOf(q) !== -1 || normalize(tr.textContent).indexOf(q) !== -1;
                        tr.classList.toggle('lt-row--hidden', !match);
                    });
                }
                input.addEventListener('input', filter);
                input.addEventListener('search', filter);
            }

            var modal = document.getElementById('loan-type-modal');
            var openBtn = document.getElementById('loan-type-add-btn');
            var closeBtn = document.getElementById('loan-type-modal-close');
            var cancelBtn = document.getElementById('loan-type-modal-cancel');
            var form = document.getElementById('loan-type-store-form');

            function openModal() {
                if (!modal) return;
                modal.removeAttribute('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                if (!modal) return;
                modal.setAttribute('hidden', '');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }

            @if ($errors->any())
            openModal();
            @endif

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
            });

            /* تب‌ها */
            document.querySelectorAll('[data-lt-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var key = tab.getAttribute('data-lt-tab');
                    document.querySelectorAll('[data-lt-tab]').forEach(function (t) {
                        t.classList.toggle('is-active', t === tab);
                        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                    });
                    document.querySelectorAll('[data-lt-panel]').forEach(function (p) {
                        var show = p.getAttribute('data-lt-panel') === key;
                        p.classList.toggle('is-active', show);
                    });
                });
            });

            /* سقف مبلغ: جداکننده هزارگان */
            var maxLoanInput = document.getElementById('lt-max-amount');
            function maxLoanDigits(v) {
                return (v || '').replace(/\D/g, '');
            }
            function maxLoanFormatDisplay(digits) {
                if (!digits) return '';
                var n = parseInt(digits, 10);
                if (isNaN(n) || n < 0) return '';
                return n.toLocaleString('en-US');
            }
            if (maxLoanInput) {
                maxLoanInput.addEventListener('input', function () {
                    var d = maxLoanDigits(maxLoanInput.value);
                    maxLoanInput.value = maxLoanFormatDisplay(d);
                });
            }
            if (form && maxLoanInput) {
                form.addEventListener('submit', function () {
                    maxLoanInput.value = maxLoanDigits(maxLoanInput.value);
                });
            }

            /* دوره بازپرداخت */
            var repayGroup = document.getElementById('lt-repay-rule-group');
            var maxWrap = document.getElementById('lt-repay-max-wrap');
            var allowedWrap = document.getElementById('lt-repay-allowed-wrap');

            function syncRepayPanels() {
                var sel = repayGroup ? repayGroup.querySelector('input[name="repayment_rule_type"]:checked') : null;
                var v = sel ? sel.value : 'unlimited';
                if (maxWrap) maxWrap.hidden = v !== 'max_until';
                if (allowedWrap) allowedWrap.hidden = v !== 'allowed_months';
            }

            if (repayGroup) {
                repayGroup.addEventListener('change', syncRepayPanels);
                syncRepayPanels();
            }

            /* غیرفعال بودن ثبت */
            var susp = document.getElementById('lt-registration-suspended');
            var suspWrap = document.getElementById('lt-suspended-msg-wrap');
            function syncSusp() {
                if (!suspWrap) return;
                suspWrap.hidden = !susp || !susp.checked;
            }
            if (susp) {
                susp.addEventListener('change', syncSusp);
                syncSusp();
            }

            /* تب اطلاعات طرح */
            var planListEn = document.getElementById('lt-plan-list-enabled');
            var planFieldsWrap = document.getElementById('lt-plan-fields-wrap');
            var planFileInp = document.getElementById('lt-plan-image');
            var planPreviewBox = document.getElementById('lt-plan-preview-box');
            var planPreviewImg = document.getElementById('lt-plan-preview-img');
            var planRemoveField = document.getElementById('lt-plan-remove-field');
            var planUploadBtn = document.getElementById('lt-plan-upload-btn');
            var planRemoveBtn = document.getElementById('lt-plan-remove-btn');
            var planDownload = document.getElementById('lt-plan-download');
            var planBlobUrl = null;
            var planServerImageUrl = null;
            var planShowingServerImage = false;

            function revokePlanBlob() {
                if (planBlobUrl) {
                    try {
                        URL.revokeObjectURL(planBlobUrl);
                    } catch (e) {}
                    planBlobUrl = null;
                }
            }

            function hidePlanPreviewUi() {
                if (planPreviewBox) planPreviewBox.hidden = true;
                if (planPreviewImg) planPreviewImg.src = '';
                if (planRemoveBtn) planRemoveBtn.hidden = true;
                if (planDownload) {
                    planDownload.hidden = true;
                    planDownload.removeAttribute('href');
                }
            }

            function syncPlanTabPanels() {
                if (!planFieldsWrap || !planListEn) return;
                planFieldsWrap.hidden = !planListEn.checked;
            }

            function clearPlanImageUi() {
                revokePlanBlob();
                planServerImageUrl = null;
                planShowingServerImage = false;
                if (planFileInp) planFileInp.value = '';
                if (planRemoveField) planRemoveField.value = '0';
                hidePlanPreviewUi();
            }

            if (planListEn) {
                planListEn.addEventListener('change', syncPlanTabPanels);
                syncPlanTabPanels();
            }

            if (planUploadBtn && planFileInp) {
                planUploadBtn.addEventListener('click', function () {
                    planFileInp.click();
                });
            }

            if (planFileInp) {
                planFileInp.addEventListener('change', function () {
                    var f = planFileInp.files && planFileInp.files[0];
                    revokePlanBlob();
                    if (!f) {
                        hidePlanPreviewUi();
                        return;
                    }
                    planBlobUrl = URL.createObjectURL(f);
                    planShowingServerImage = false;
                    if (planPreviewImg) planPreviewImg.src = planBlobUrl;
                    if (planPreviewBox) planPreviewBox.hidden = false;
                    if (planRemoveBtn) planRemoveBtn.hidden = false;
                    if (planDownload) {
                        planDownload.href = planBlobUrl;
                        planDownload.download = f.name || 'plan.jpg';
                        planDownload.hidden = false;
                    }
                    if (planRemoveField) planRemoveField.value = '0';
                });
            }

            if (planRemoveBtn) {
                planRemoveBtn.addEventListener('click', function () {
                    var markRemoveServer = planShowingServerImage || !!planServerImageUrl;
                    revokePlanBlob();
                    if (planFileInp) planFileInp.value = '';
                    if (planRemoveField) planRemoveField.value = markRemoveServer ? '1' : '0';
                    hidePlanPreviewUi();
                    planServerImageUrl = null;
                    planShowingServerImage = false;
                });
            }

            /* تکرار ماه/سقف */
            var allowedContainer = document.getElementById('lt-allowed-rows');
            var addAllowedBtn = document.getElementById('lt-add-allowed-row');
            var allowedIdx = {{ count($ltAllowedRows) }};

            function bindRemove(btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('[data-lt-allowed-row]');
                    if (!row || !allowedContainer) return;
                    if (allowedContainer.querySelectorAll('[data-lt-allowed-row]').length <= 1) return;
                    row.remove();
                });
            }

            function resetLoanTypeForm() {
                if (!form) return;
                form.reset();
                var profit = document.getElementById('lt-profit-method');
                if (profit) profit.value = LT.profitMonthly;
                var gapU = document.getElementById('lt-gap-unit');
                if (gapU) gapU.value = LT.gapMonthly;
                var late = document.getElementById('lt-late-coef');
                if (late) late.value = '0.008';
                var early = document.getElementById('lt-early-coef');
                if (early) early.value = '0.008';
                var rUn = form.querySelector('input[name="repayment_rule_type"][value="' + LT.repayUnlimited + '"]');
                if (rUn) rUn.checked = true;
                var sms = form.querySelector('[name="sms_reminder_enabled"]');
                if (sms) sms.checked = true;
                var reg = document.getElementById('lt-registration-suspended');
                if (reg) reg.checked = false;
                var suspMsg = document.getElementById('lt-suspended-msg');
                if (suspMsg) suspMsg.value = '';
                if (maxLoanInput) maxLoanInput.value = '';
                if (allowedContainer) {
                    allowedContainer.innerHTML =
                        '<div class="lt-repeater-row" data-lt-allowed-row>' +
                        '<div class="lt-field" style="margin:0">' +
                        '<label class="lt-hint">ماه</label>' +
                        '<input class="lt-input lt-num" type="number" name="allowed_rows[0][months]" min="1" step="1">' +
                        '</div>' +
                        '<div class="lt-field" style="margin:0">' +
                        '<label class="lt-hint">سقف (تومان)</label>' +
                        '<input class="lt-input lt-num" type="number" name="allowed_rows[0][cap]" min="0" step="1">' +
                        '</div>' +
                        '<button type="button" class="lt-btn-row lt-btn-icon lt-remove-allowed" aria-label="حذف ردیف">حذف</button>' +
                        '</div>';
                    allowedIdx = 1;
                    allowedContainer.querySelectorAll('.lt-remove-allowed').forEach(bindRemove);
                }
                document.querySelectorAll('[data-lt-tab]').forEach(function (t) {
                    var isSpec = t.getAttribute('data-lt-tab') === 'spec';
                    t.classList.toggle('is-active', isSpec);
                    t.setAttribute('aria-selected', isSpec ? 'true' : 'false');
                });
                document.querySelectorAll('[data-lt-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-lt-panel') === 'spec');
                });
                syncRepayPanels();
                syncSusp();
                clearPlanImageUi();
                var plt = document.getElementById('lt-plan-title');
                if (plt) plt.value = '';
                var pls = document.getElementById('lt-plan-summary');
                if (pls) pls.value = '';
                var plb = document.getElementById('lt-plan-body');
                if (plb) plb.value = '';
                if (planListEn) planListEn.checked = false;
                syncPlanTabPanels();
                setCreateMode();
            }

            if (allowedContainer) {
                allowedContainer.querySelectorAll('.lt-remove-allowed').forEach(bindRemove);
            }

            if (addAllowedBtn && allowedContainer) {
                addAllowedBtn.addEventListener('click', function () {
                    var div = document.createElement('div');
                    div.className = 'lt-repeater-row';
                    div.setAttribute('data-lt-allowed-row', '');
                    div.innerHTML =
                        '<div class="lt-field" style="margin:0">' +
                        '<label class="lt-hint">ماه</label>' +
                        '<input class="lt-input lt-num" type="number" name="allowed_rows[' + allowedIdx + '][months]" min="1" step="1">' +
                        '</div>' +
                        '<div class="lt-field" style="margin:0">' +
                        '<label class="lt-hint">سقف (تومان)</label>' +
                        '<input class="lt-input lt-num" type="number" name="allowed_rows[' + allowedIdx + '][cap]" min="0" step="1">' +
                        '</div>' +
                        '<button type="button" class="lt-btn-row lt-btn-icon lt-remove-allowed" aria-label="حذف ردیف">حذف</button>';
                    allowedIdx++;
                    allowedContainer.appendChild(div);
                    bindRemove(div.querySelector('.lt-remove-allowed'));
                });
            }

            if (openBtn) {
                openBtn.addEventListener('click', function () {
                    resetLoanTypeForm();
                    openModal();
                });
            }

            function setCreateMode() {
                if (!form) return;
                form.setAttribute('action', LT_STORE);
                var mth = form.querySelector('input[name="_method"]');
                if (mth) mth.remove();
                var hid = document.getElementById('lt-hidden-loan-type-id');
                if (hid) {
                    hid.value = '';
                    hid.disabled = true;
                }
                var titleEl = document.getElementById('loan-type-modal-title');
                if (titleEl) titleEl.textContent = 'افزودن نوع وام';
                var subBtn = document.getElementById('lt-submit-btn');
                if (subBtn) subBtn.textContent = 'ذخیره نوع وام';
            }

            function setEditMode(id) {
                if (!form) return;
                form.setAttribute('action', LT_UPDATE_TMPL.replace('__ID__', String(id)));
                var mth = form.querySelector('input[name="_method"]');
                if (!mth) {
                    mth = document.createElement('input');
                    mth.type = 'hidden';
                    mth.name = '_method';
                    form.appendChild(mth);
                }
                mth.value = 'PUT';
                var hid = document.getElementById('lt-hidden-loan-type-id');
                if (hid) {
                    hid.value = String(id);
                    hid.disabled = false;
                }
                var titleEl = document.getElementById('loan-type-modal-title');
                if (titleEl) titleEl.textContent = 'ویرایش نوع وام';
                var subBtn = document.getElementById('lt-submit-btn');
                if (subBtn) subBtn.textContent = 'ذخیرهٔ تغییرات';
            }

            function applyLoanTypeFromJson(d) {
                if (!d || !form) return;
                var tEl = document.getElementById('lt-title');
                if (tEl) tEl.value = d.title || '';
                var pm = document.getElementById('lt-profit-method');
                if (pm) pm.value = d.profit_calculation_method || LT.profitMonthly;
                var ir = document.getElementById('lt-interest');
                if (ir) ir.value = d.interest_rate != null ? String(d.interest_rate) : '';
                var lc = document.getElementById('lt-late-coef');
                if (lc) lc.value = d.daily_late_coefficient != null ? String(d.daily_late_coefficient) : '';
                var ec = document.getElementById('lt-early-coef');
                if (ec) ec.value = d.daily_early_coefficient != null ? String(d.daily_early_coefficient) : '';
                if (maxLoanInput) {
                    if (d.max_loan_amount != null && d.max_loan_amount !== '') {
                        maxLoanInput.value = maxLoanFormatDisplay(String(d.max_loan_amount).replace(/\D/g, ''));
                    } else {
                        maxLoanInput.value = '';
                    }
                }
                var mg = document.getElementById('lt-max-gap');
                if (mg) mg.value = d.max_installment_gap != null ? String(d.max_installment_gap) : '';
                var gu = document.getElementById('lt-gap-unit');
                if (gu) gu.value = d.installment_gap_unit || LT.gapMonthly;
                var rep = d.repayment_periods || {};
                var rtype = rep.type || LT.repayUnlimited;
                var rad = form.querySelector('input[name="repayment_rule_type"][value="' + rtype + '"]');
                if (rad) rad.checked = true;
                var rmm = document.getElementById('lt-repay-max-months');
                if (rmm) rmm.value = rep.max_months != null ? String(rep.max_months) : '';
                if (allowedContainer) {
                    var rows = rep.allowed_rows;
                    if (rtype === 'allowed_months' && Array.isArray(rows) && rows.length > 0) {
                        var html = '';
                        rows.forEach(function (row, i) {
                            html +=
                                '<div class="lt-repeater-row" data-lt-allowed-row>' +
                                '<div class="lt-field" style="margin:0">' +
                                '<label class="lt-hint">ماه</label>' +
                                '<input class="lt-input lt-num" type="number" name="allowed_rows[' + i + '][months]" min="1" step="1" value="' +
                                (row.months != null ? String(row.months) : '') +
                                '">' +
                                '</div>' +
                                '<div class="lt-field" style="margin:0">' +
                                '<label class="lt-hint">سقف (تومان)</label>' +
                                '<input class="lt-input lt-num" type="number" name="allowed_rows[' + i + '][cap]" min="0" step="1" value="' +
                                (row.cap != null ? String(row.cap) : '') +
                                '">' +
                                '</div>' +
                                '<button type="button" class="lt-btn-row lt-btn-icon lt-remove-allowed" aria-label="حذف ردیف">حذف</button>' +
                                '</div>';
                        });
                        allowedContainer.innerHTML = html;
                        allowedIdx = rows.length;
                        allowedContainer.querySelectorAll('.lt-remove-allowed').forEach(bindRemove);
                    } else {
                        allowedContainer.innerHTML =
                            '<div class="lt-repeater-row" data-lt-allowed-row>' +
                            '<div class="lt-field" style="margin:0">' +
                            '<label class="lt-hint">ماه</label>' +
                            '<input class="lt-input lt-num" type="number" name="allowed_rows[0][months]" min="1" step="1">' +
                            '</div>' +
                            '<div class="lt-field" style="margin:0">' +
                            '<label class="lt-hint">سقف (تومان)</label>' +
                            '<input class="lt-input lt-num" type="number" name="allowed_rows[0][cap]" min="0" step="1">' +
                            '</div>' +
                            '<button type="button" class="lt-btn-row lt-btn-icon lt-remove-allowed" aria-label="حذف ردیف">حذف</button>' +
                            '</div>';
                        allowedIdx = 1;
                        allowedContainer.querySelectorAll('.lt-remove-allowed').forEach(bindRemove);
                    }
                }
                var sms = form.querySelector('[name="sms_reminder_enabled"]');
                if (sms) sms.checked = !!d.sms_reminder_enabled;
                var rs = document.getElementById('lt-registration-suspended');
                if (rs) rs.checked = !!d.registration_suspended;
                var sm = document.getElementById('lt-suspended-msg');
                if (sm) sm.value = d.registration_suspended_message || '';
                revokePlanBlob();
                planServerImageUrl = d.plan_image_url || null;
                planShowingServerImage = false;
                if (planRemoveField) planRemoveField.value = '0';
                if (planFileInp) planFileInp.value = '';
                var ptt = document.getElementById('lt-plan-title');
                if (ptt) ptt.value = d.plan_title || '';
                var pss = document.getElementById('lt-plan-summary');
                if (pss) pss.value = d.plan_summary || '';
                var pbb = document.getElementById('lt-plan-body');
                if (pbb) pbb.value = d.plan_body || '';
                if (planListEn) planListEn.checked = !!d.plan_list_enabled;
                if (d.plan_image_url && planPreviewImg) {
                    planShowingServerImage = true;
                    planPreviewImg.src = d.plan_image_url;
                    if (planPreviewBox) planPreviewBox.hidden = false;
                    if (planRemoveBtn) planRemoveBtn.hidden = false;
                    if (planDownload) {
                        planDownload.href = d.plan_image_url;
                        planDownload.download = 'plan.jpg';
                        planDownload.hidden = false;
                    }
                } else {
                    hidePlanPreviewUi();
                }
                syncPlanTabPanels();
                syncRepayPanels();
                syncSusp();
            }

            if (tbody) {
                tbody.addEventListener('click', function (e) {
                    var editBtn = e.target.closest('button[data-lt-edit]');
                    if (editBtn && tbody.contains(editBtn)) {
                        e.preventDefault();
                        var editId = parseInt(editBtn.getAttribute('data-lt-edit'), 10);
                        if (isNaN(editId) || !form) return;
                        var editData = LT_EDIT_MAP[editId];
                        if (!editData) return;
                        setEditMode(editData.id);
                        applyLoanTypeFromJson(editData);
                        openModal();
                        return;
                    }
                    var delBtn = e.target.closest('button[data-lt-delete]');
                    if (delBtn && tbody.contains(delBtn)) {
                        e.preventDefault();
                        var delId = delBtn.getAttribute('data-lt-delete');
                        if (!delId) return;
                        var formDel = document.getElementById('lt-del-' + delId);
                        if (!formDel) return;
                        if (typeof AdminSwal !== 'undefined' && typeof AdminSwal.confirm === 'function') {
                            AdminSwal.confirm({
                                title: 'حذف نوع وام',
                                text: 'آیا از حذف این نوع وام مطمئنید؟',
                            }).then(function (result) {
                                if (result.isConfirmed) {
                                    formDel.submit();
                                }
                            });
                            return;
                        }
                        if (window.confirm('آیا از حذف این نوع وام مطمئنید؟')) {
                            formDel.submit();
                        }
                    }
                });
            }

            @if ($errors->any())
            syncRepayPanels();
            syncSusp();
            syncPlanTabPanels();
            @endif
        })();
    </script>
@endpush
