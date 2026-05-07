@extends('layouts.admin.app')

@section('title', 'لیست مشتریان')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .cust-page { max-width: 100%; }
        .cust-head { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .cust-title-wrap h1 { margin: 0 0 0.35rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .cust-title-wrap p { margin: 0; font-size: 0.8rem; color: var(--muted); line-height: 1.5; }
        .cust-add-btn {
            border: none; border-radius: 0.7rem; padding: 0.55rem 1rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.82rem; font-weight: 800; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 0.45rem;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.28);
        }
        .cust-add-btn:hover { filter: brightness(1.03); }
        .cust-search { flex: 1 1 16rem; max-width: 22rem; }
        .cust-search input {
            width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.72rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.84rem;
        }
        .cust-card {
            border: 1px solid var(--border); border-radius: 0.9rem; background: var(--bg-card);
            overflow: visible; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .cust-table-wrap { overflow-x: auto; overflow-y: visible; }
        .cust-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .cust-table th, .cust-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .cust-table th { background: var(--primary-soft); font-weight: 800; white-space: nowrap; }
        .cust-ops { position: relative; display: inline-block; vertical-align: middle; }
        .cust-ops-trigger {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.68rem;
            font-size: 0.86rem; font-weight: 700; color: var(--text); background: var(--bg-card);
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem;
            line-height: 1.2;
        }
        .cust-ops-trigger:hover,
        .cust-ops-trigger:focus-visible { background: var(--primary-soft); border-color: rgba(37, 99, 235, 0.35); outline: none; }
        .cust-ops-menu {
            position: fixed; min-width: 10rem; z-index: 1500;
            border: 1px solid var(--border); border-radius: 0.6rem; background: var(--bg-card);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); padding: 0.28rem;
        }
        .cust-ops-menu[hidden] { display: none !important; }
        .cust-ops-item {
            width: 100%; text-align: start; border: 0; border-radius: 0.45rem; padding: 0.42rem 0.5rem;
            font-family: inherit; font-size: 0.74rem; font-weight: 700; background: transparent; color: var(--text);
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .cust-ops-item:hover { background: var(--primary-soft); }
        .cust-ops-item--danger { color: #b91c1c; }
        .cust-ops-item--danger:hover { background: rgba(248, 113, 113, 0.14); }
        .cust-empty { text-align: center; padding: 1.5rem; color: var(--muted); }
        .cust-pagination { padding: 0.65rem 0.85rem; }

        .cust-overlay {
            position: fixed; inset: 0; z-index: 1400; background: rgba(15, 23, 42, 0.55);
            display: grid; place-items: center; padding: 1rem;
        }
        .cust-overlay[hidden] { display: none !important; }
        .cust-modal {
            width: min(900px, 100%); max-height: min(92vh, 900px); overflow: auto;
            border: 1px solid var(--border); border-radius: 1rem; background: var(--bg-card);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
        }
        .cust-modal-head {
            padding: 0.85rem 1rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
            position: sticky; top: 0; background: var(--bg-card); z-index: 2;
        }
        .cust-modal-head h2 { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text); }
        .cust-modal-head p { margin: 0.25rem 0 0; font-size: 0.75rem; color: var(--muted); line-height: 1.45; }
        .cust-modal-close {
            flex-shrink: 0; width: 2.1rem; height: 2.1rem; border: 0; border-radius: 0.55rem;
            background: var(--primary-soft); color: var(--primary-dark); cursor: pointer;
        }
        .cust-modal-body { padding: 1rem 1rem 1.15rem; }
        .cust-form-grid {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem 0.9rem;
        }
        @media (max-width: 720px) { .cust-form-grid { grid-template-columns: 1fr; } }
        .cust-field label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--muted); margin-bottom: 0.22rem; }
        .cust-field label .req { color: #b91c1c; font-weight: 800; }
        .cust-field input, .cust-field textarea, .cust-field select {
            width: 100%; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem;
            background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem;
        }
        .cust-field input:disabled { opacity: 0.72; cursor: not-allowed; background: var(--primary-soft); }
        .cust-field textarea { min-height: 4rem; resize: vertical; }
        .cust-field--full { grid-column: 1 / -1; }
        .cust-field-error { margin-top: 0.22rem; font-size: 0.72rem; color: #b91c1c; font-weight: 700; }
        .cust-field-hint { display: block; margin-top: 0.18rem; font-size: 0.7rem; color: var(--muted); line-height: 1.4; }

        .cust-section {
            margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border);
        }
        .cust-section-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;
            margin-bottom: 0.55rem;
        }
        .cust-section-head h3 { margin: 0; font-size: 0.84rem; font-weight: 800; color: var(--text); }
        .cust-section-head p { margin: 0.2rem 0 0; font-size: 0.72rem; color: var(--muted); width: 100%; }
        .cust-mini-btn {
            border: 1px solid rgba(37, 99, 235, 0.35); border-radius: 0.55rem; padding: 0.36rem 0.65rem;
            background: var(--primary-soft); color: var(--primary-dark); font-size: 0.75rem; font-weight: 800;
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .cust-mini-btn:hover { filter: brightness(0.98); }

        .cust-repeat-row {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.1fr) minmax(0, 1fr) auto;
            gap: 0.45rem; align-items: end; margin-bottom: 0.5rem;
            padding: 0.55rem 0.6rem; border: 1px solid var(--border); border-radius: 0.65rem; background: rgba(248, 250, 252, 0.55);
        }
        html[data-theme="dark"] .cust-repeat-row { background: rgba(30, 41, 59, 0.45); }
        @media (max-width: 900px) {
            .cust-repeat-row { grid-template-columns: 1fr 1fr; }
            .cust-repeat-row .cust-f-remove { grid-column: 1 / -1; justify-self: end; }
        }
        .cust-f-remove {
            width: 2.15rem; height: 2.15rem; border: 0; border-radius: 0.5rem; cursor: pointer;
            background: rgba(239, 68, 68, 0.12); color: #b91c1c; display: grid; place-items: center;
        }
        .cust-f-remove:hover { background: rgba(239, 68, 68, 0.2); }

        .cust-ref-row {
            display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.45rem; align-items: end;
            margin-bottom: 0.5rem; padding: 0.55rem 0.6rem; border: 1px solid var(--border);
            border-radius: 0.65rem; background: rgba(248, 250, 252, 0.55);
        }
        html[data-theme="dark"] .cust-ref-row { background: rgba(30, 41, 59, 0.45); }
        @media (max-width: 720px) {
            .cust-ref-row { grid-template-columns: 1fr; }
            .cust-ref-row .cust-f-remove { justify-self: end; }
        }

        .cust-send-row {
            margin-top: 1.1rem; padding: 0.65rem 0.75rem; border-radius: 0.65rem;
            border: 1px solid var(--border); background: var(--primary-soft);
            display: flex; align-items: flex-start; gap: 0.55rem;
        }
        .cust-send-row input[type="checkbox"] { width: 1rem; height: 1rem; margin-top: 0.1rem; accent-color: var(--primary); }
        .cust-send-row label { font-size: 0.78rem; font-weight: 700; color: var(--text); cursor: pointer; line-height: 1.55; }

        .cust-actions { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
        .cust-submit {
            border: none; border-radius: 0.65rem; padding: 0.55rem 1.35rem;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff;
            font-size: 0.82rem; font-weight: 800; cursor: pointer; font-family: inherit;
        }
        .cust-cancel {
            border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.52rem 1rem;
            background: var(--bg-card); color: var(--text); font-size: 0.8rem; font-weight: 700; cursor: pointer; font-family: inherit;
        }
        .cust-block-error { font-size: 0.75rem; color: #b91c1c; font-weight: 700; margin-top: 0.35rem; }
    </style>
@endpush

@section('content')
    @php
        $oldAccounts = old('accounts', []);
        $oldReferrers = old('referrers', []);
    @endphp

    <div class="cust-page">
        <div class="cust-head">
            <div class="cust-title-wrap">
                <h1>لیست مشتریان</h1>
                <p>مشاهده و ثبت مشتری جدید با اطلاعات هویتی، حساب بانکی و معرف‌ها.</p>
            </div>
            <button type="button" class="cust-add-btn" id="cust-open-modal" aria-haspopup="dialog">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                افزودن مشتری
            </button>
        </div>

        <div class="cust-head" style="margin-top: -0.25rem;">
            <form method="get" action="{{ route('admin.customers.index') }}" class="cust-search">
                <input type="search" name="q" value="{{ $search }}" placeholder="جستجو: کد، نام، موبایل، کد ملی..." autocomplete="off">
            </form>
        </div>

        <div class="cust-card">
            <div class="cust-table-wrap">
                <table class="cust-table">
                    <thead>
                        <tr>
                            <th>کد مشتری</th>
                            <th>نام و نام خانوادگی</th>
                            <th>موبایل</th>
                            <th>کد ملی</th>
                            <th>نام کاربری</th>
                            <th>تاریخ عضویت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $c)
                            <tr>
                                <td>{{ $c->customer_code }}</td>
                                <td>{{ $c->fullName() }}</td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($c->mobile) }}</td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($c->national_id) }}</td>
                                <td>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers($c->username) }}</td>
                                <td>
                                    @if ($c->membership_at)
                                        {{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(jalali($c->membership_at)->format('Y/m/d')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="cust-ops" data-cust-ops>
                                        <button
                                            type="button"
                                            class="cust-ops-trigger"
                                            data-cust-ops-toggle
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            title="عملیات"
                                        >
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        </button>
                                        <div class="cust-ops-menu" data-cust-ops-menu hidden>
                                            <button type="button" class="cust-ops-item" data-cust-edit data-customer-id="{{ $c->id }}">
                                                <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                                                ویرایش
                                            </button>
                                            <form method="post" action="{{ route('admin.customers.destroy', $c) }}" data-cust-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cust-ops-item cust-ops-item--danger">
                                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                                    حذف
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="cust-empty">هنوز مشتری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="cust-pagination">{{ $customers->links() }}</div>
            @endif
        </div>
    </div>

    <div class="cust-overlay" id="cust-modal-overlay" hidden aria-hidden="true">
        <div class="cust-modal" role="dialog" aria-modal="true" aria-labelledby="cust-modal-title" id="cust-modal">
            <div class="cust-modal-head">
                <div>
                    <h2 id="cust-modal-title">افزودن مشتری جدید</h2>
                    <p id="cust-modal-desc">فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.</p>
                </div>
                <button type="button" class="cust-modal-close" id="cust-close-modal" aria-label="بستن">&times;</button>
            </div>
            <div class="cust-modal-body">
                <form method="post" action="{{ route('admin.customers.store') }}" id="cust-store-form" novalidate>
                    @csrf

                    <div class="cust-form-grid">
                        <div class="cust-field">
                            <label for="cust-code">کد مشتری</label>
                            <input id="cust-code" name="customer_code" type="text" value="{{ old('customer_code') }}" placeholder="خالی = تولید خودکار" autocomplete="off">
                            @error('customer_code')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-username-preview">نام کاربری <span class="req">*</span></label>
                            <input id="cust-username-preview" type="text" readonly disabled placeholder="با وارد کردن موبایل پر می‌شود" value="">
                        </div>

                        <div class="cust-field">
                            <label for="cust-fname">نام <span class="req">*</span></label>
                            <input id="cust-fname" name="first_name" type="text" value="{{ old('first_name') }}" required autocomplete="given-name">
                            @error('first_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-lname">نام خانوادگی <span class="req">*</span></label>
                            <input id="cust-lname" name="last_name" type="text" value="{{ old('last_name') }}" required autocomplete="family-name">
                            @error('last_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-father">نام پدر <span class="req">*</span></label>
                            <input id="cust-father" name="father_name" type="text" value="{{ old('father_name') }}" required>
                            @error('father_name')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-national">کد ملی <span class="req">*</span></label>
                            <input id="cust-national" name="national_id" type="text" inputmode="numeric" value="{{ old('national_id') }}" maxlength="10" required>
                            @error('national_id')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-mobile">موبایل <span class="req">*</span></label>
                            <input id="cust-mobile" name="mobile" type="text" inputmode="numeric" value="{{ old('mobile') }}" placeholder="09123456789" required autocomplete="tel">
                            @error('mobile')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-phone">تلفن ثابت</label>
                            <input id="cust-phone" name="phone_landline" type="text" value="{{ old('phone_landline') }}">
                            @error('phone_landline')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-membership-jdate">تاریخ عضویت</label>
                            <input id="cust-membership-jdate" name="membership_jdate" type="text" value="{{ old('membership_jdate') }}" autocomplete="off" placeholder="۱۴۰۳/۰۱/۰۱">
                            @error('membership_jdate')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-birth-jdate">تاریخ تولد</label>
                            <input id="cust-birth-jdate" name="birth_jdate" type="text" value="{{ old('birth_jdate') }}" autocomplete="off">
                            @error('birth_jdate')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-email">ایمیل</label>
                            <input id="cust-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                            @error('email')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-password">کلمه عبور <span class="req" id="cust-password-req">*</span></label>
                            <input id="cust-password" name="password" type="password" autocomplete="new-password">
                            <span class="cust-field-hint" id="cust-password-hint" hidden>برای حفظ رمز فعلی هنگام ویرایش این فیلد را خالی بگذارید.</span>
                            @error('password')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-city">شهر <span class="req">*</span></label>
                            <input id="cust-city" name="city" type="text" value="{{ old('city') }}" required>
                            @error('city')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field cust-field--full">
                            <label for="cust-address">آدرس <span class="req">*</span></label>
                            <textarea id="cust-address" name="address" required>{{ old('address') }}</textarea>
                            @error('address')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="cust-field">
                            <label for="cust-postal">کدپستی <span class="req">*</span></label>
                            <input id="cust-postal" name="postal_code" type="text" inputmode="numeric" value="{{ old('postal_code') }}" maxlength="10" required>
                            @error('postal_code')<div class="cust-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="cust-section">
                        <div class="cust-section-head">
                            <div>
                                <h3>شماره حساب‌ها</h3>
                                <p>شماره کارت، حساب یا شبا در یک فیلد؛ در صورت نیاز چند ردیف اضافه کنید.</p>
                            </div>
                            <button type="button" class="cust-mini-btn" id="cust-add-bank">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                شماره حساب جدید
                            </button>
                        </div>
                        @error('accounts')<div class="cust-block-error">{{ $message }}</div>@enderror
                        <div id="cust-bank-rows">
                            @if (count($oldAccounts) > 0)
                                @foreach ($oldAccounts as $i => $row)
                                    <div class="cust-repeat-row" data-bank-row>
                                        <div class="cust-field">
                                            <label>شماره کارت / حساب / شبا</label>
                                            <input name="accounts[{{ $i }}][account_identifier]" value="{{ $row['account_identifier'] ?? '' }}" placeholder="مثلاً شبا یا شماره کارت">
                                        </div>
                                        <div class="cust-field">
                                            <label>بانک</label>
                                            <input name="accounts[{{ $i }}][bank_name]" value="{{ $row['bank_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>شعبه</label>
                                            <input name="accounts[{{ $i }}][branch_name]" value="{{ $row['branch_name'] ?? '' }}">
                                        </div>
                                        <button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="cust-section">
                        <div class="cust-section-head">
                            <div>
                                <h3>معرف‌ها</h3>
                                <p>در صورت وجود معرف، نام کامل و موبایل را وارد کنید.</p>
                            </div>
                            <button type="button" class="cust-mini-btn" id="cust-add-referrer">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                افزودن معرف
                            </button>
                        </div>
                        @error('referrers')<div class="cust-block-error">{{ $message }}</div>@enderror
                        <div id="cust-referrer-rows">
                            @if (count($oldReferrers) > 0)
                                @foreach ($oldReferrers as $i => $row)
                                    <div class="cust-ref-row" data-ref-row>
                                        <div class="cust-field">
                                            <label>نام</label>
                                            <input name="referrers[{{ $i }}][first_name]" value="{{ $row['first_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>نام خانوادگی</label>
                                            <input name="referrers[{{ $i }}][last_name]" value="{{ $row['last_name'] ?? '' }}">
                                        </div>
                                        <div class="cust-field">
                                            <label>شماره تماس</label>
                                            <input name="referrers[{{ $i }}][phone]" value="{{ $row['phone'] ?? '' }}" placeholder="09xxxxxxxxx">
                                        </div>
                                        <button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <input type="hidden" name="send_credentials" value="0" id="cust-send-hidden">

                    <div class="cust-send-row">
                        <input type="checkbox" id="cust-send-chk">
                        <label for="cust-send-chk">ارسال نام کاربری و رمز عبور برای کاربر (پیامک)</label>
                    </div>

                    <div class="cust-actions">
                        <button type="button" class="cust-cancel" id="cust-cancel-modal">انصراف</button>
                        <button type="submit" class="cust-submit">ذخیره</button>
                    </div>
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
            function toEnglishDigits(s) {
                var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                var en = ['0','1','2','3','4','5','6','7','8','9'];
                var out = s;
                for (var i = 0; i < 10; i++) out = out.split(fa[i]).join(en[i]);
                return out;
            }

            function usernameFromMobile(m) {
                var d = toEnglishDigits(String(m || '')).replace(/\D/g, '');
                if (d.length === 10 && d.charAt(0) === '9') d = '0' + d;
                return d;
            }

            var custFormMode = 'create';
            var custListBaseUrl = @json(rtrim(route('admin.customers.index'), '/'));
            var custStoreUrl = @json(route('admin.customers.store'));

            function custEditDataUrl(id) {
                return custListBaseUrl + '/' + id + '/edit-data';
            }

            function custUpdateUrl(id) {
                return custListBaseUrl + '/' + id;
            }

            var overlay = document.getElementById('cust-modal-overlay');
            var openBtn = document.getElementById('cust-open-modal');
            var closeBtn = document.getElementById('cust-close-modal');
            var cancelBtn = document.getElementById('cust-cancel-modal');
            var mobile = document.getElementById('cust-mobile');
            var userPrev = document.getElementById('cust-username-preview');
            var form = document.getElementById('cust-store-form');
            var sendHidden = document.getElementById('cust-send-hidden');
            var sendChk = document.getElementById('cust-send-chk');
            var bankContainer = document.getElementById('cust-bank-rows');
            var refContainer = document.getElementById('cust-referrer-rows');
            var bankBtn = document.getElementById('cust-add-bank');
            var refBtn = document.getElementById('cust-add-referrer');
            var bankIndex = {{ max(count(old('accounts', [])), 0) }};
            var refIndex = {{ max(count(old('referrers', [])), 0) }};
            var skipPrompt = false;
            var pwdInput = document.getElementById('cust-password');
            var pwdReq = document.getElementById('cust-password-req');
            var pwdHint = document.getElementById('cust-password-hint');
            var modalTitle = document.getElementById('cust-modal-title');
            var modalDesc = document.getElementById('cust-modal-desc');

            function escapeHtmlAttr(v) {
                if (v === undefined || v === null) return '';
                return String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function removeMethodField() {
                var el = document.getElementById('cust-http-method');
                if (el) {
                    el.remove();
                }
            }

            function addMethodPut() {
                if (document.getElementById('cust-http-method')) {
                    return;
                }
                var el = document.createElement('input');
                el.type = 'hidden';
                el.name = '_method';
                el.id = 'cust-http-method';
                el.value = 'PUT';
                form.appendChild(el);
            }

            function destroyCustPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    return;
                }
                try {
                    window.jQuery('#cust-membership-jdate, #cust-birth-jdate').each(function () {
                        var $el = window.jQuery(this);
                        if ($el.data('datepicker')) {
                            $el.pDatepicker('destroy');
                        }
                    });
                } catch (err) { /* noop */ }
            }

            function openCreateModal() {
                custFormMode = 'create';
                removeMethodField();
                form.action = custStoreUrl;
                form.setAttribute('method', 'post');
                if (modalTitle) {
                    modalTitle.textContent = 'افزودن مشتری جدید';
                }
                if (modalDesc) {
                    modalDesc.textContent = 'فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.';
                }
                if (pwdReq) {
                    pwdReq.style.display = '';
                }
                if (pwdHint) {
                    pwdHint.hidden = true;
                }
                if (pwdInput) {
                    pwdInput.value = '';
                    pwdInput.required = true;
                    pwdInput.placeholder = '';
                }
                bankContainer.innerHTML = '';
                refContainer.innerHTML = '';
                bankIndex = 0;
                refIndex = 0;
                form.reset();
                if (sendHidden) {
                    sendHidden.value = '0';
                }
                if (sendChk) {
                    sendChk.checked = false;
                }
                destroyCustPickers();
                syncUsername();
                openModal();
            }

            function openEditModal(customerId) {
                custFormMode = 'edit';
                addMethodPut();
                form.action = custUpdateUrl(customerId);
                form.setAttribute('method', 'post');
                if (modalTitle) {
                    modalTitle.textContent = 'ویرایش مشتری';
                }
                if (modalDesc) {
                    modalDesc.textContent = 'اطلاعات مشتری را ویرایش کنید. رمز عبور را فقط در صورت تغییر پر کنید.';
                }
                if (pwdReq) {
                    pwdReq.style.display = 'none';
                }
                if (pwdHint) {
                    pwdHint.hidden = false;
                }
                if (pwdInput) {
                    pwdInput.value = '';
                    pwdInput.required = false;
                    pwdInput.placeholder = '';
                }

                fetch(custEditDataUrl(customerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad');
                    }
                    return r.json();
                }).then(function (data) {
                    var c = data.customer;
                    document.getElementById('cust-code').value = c.customer_code || '';
                    document.getElementById('cust-fname').value = c.first_name || '';
                    document.getElementById('cust-lname').value = c.last_name || '';
                    document.getElementById('cust-father').value = c.father_name || '';
                    document.getElementById('cust-national').value = c.national_id || '';
                    document.getElementById('cust-mobile').value = c.mobile || '';
                    document.getElementById('cust-phone').value = c.phone_landline || '';
                    document.getElementById('cust-membership-jdate').value = c.membership_jdate || '';
                    document.getElementById('cust-birth-jdate').value = c.birth_jdate || '';
                    document.getElementById('cust-email').value = c.email || '';
                    document.getElementById('cust-city').value = c.city || '';
                    document.getElementById('cust-address').value = c.address || '';
                    document.getElementById('cust-postal').value = c.postal_code || '';

                    bankContainer.innerHTML = '';
                    bankIndex = 0;
                    var banks = data.bank_accounts || [];
                    banks.forEach(function (row, idx) {
                        var i = idx;
                        bankIndex = idx + 1;
                        var div = document.createElement('div');
                        div.className = 'cust-repeat-row';
                        div.setAttribute('data-bank-row', '');
                        div.innerHTML =
                            '<div class="cust-field"><label>شماره کارت / حساب / شبا</label>' +
                            '<input name="accounts[' + i + '][account_identifier]" value="' + escapeHtmlAttr(row.account_identifier) + '" placeholder="مثلاً شبا یا شماره کارت"></div>' +
                            '<div class="cust-field"><label>بانک</label><input name="accounts[' + i + '][bank_name]" value="' + escapeHtmlAttr(row.bank_name) + '"></div>' +
                            '<div class="cust-field"><label>شعبه</label><input name="accounts[' + i + '][branch_name]" value="' + escapeHtmlAttr(row.branch_name) + '"></div>' +
                            '<button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>';
                        bankContainer.appendChild(div);
                        div.querySelector('[data-remove-bank]').addEventListener('click', function () {
                            div.remove();
                        });
                    });

                    refContainer.innerHTML = '';
                    refIndex = 0;
                    var refs = data.referrers || [];
                    refs.forEach(function (row, idx) {
                        var i = idx;
                        refIndex = idx + 1;
                        var div = document.createElement('div');
                        div.className = 'cust-ref-row';
                        div.setAttribute('data-ref-row', '');
                        div.innerHTML =
                            '<div class="cust-field"><label>نام</label><input name="referrers[' + i + '][first_name]" value="' + escapeHtmlAttr(row.first_name) + '"></div>' +
                            '<div class="cust-field"><label>نام خانوادگی</label><input name="referrers[' + i + '][last_name]" value="' + escapeHtmlAttr(row.last_name) + '"></div>' +
                            '<div class="cust-field"><label>شماره تماس</label><input name="referrers[' + i + '][phone]" value="' + escapeHtmlAttr(row.phone) + '" placeholder="09xxxxxxxxx"></div>' +
                            '<button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>';
                        refContainer.appendChild(div);
                        div.querySelector('[data-remove-ref]').addEventListener('click', function () {
                            div.remove();
                        });
                    });

                    syncUsername();
                    destroyCustPickers();
                    openModal();
                }).catch(function () {
                    if (window.AdminSwal && window.AdminSwal.error) {
                        AdminSwal.error('بارگذاری اطلاعات مشتری ناموفق بود.');
                    }
                });
            }

            function syncUsername() {
                if (!userPrev) return;
                var u = usernameFromMobile(mobile ? mobile.value : '');
                userPrev.value = u || '';
            }

            if (mobile) {
                mobile.addEventListener('input', syncUsername);
                syncUsername();
            }

            function openModal() {
                if (!overlay) return;
                overlay.hidden = false;
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('app-settings-open');
                setTimeout(initPickers, 80);
            }

            function closeModal() {
                if (!overlay) return;
                overlay.hidden = true;
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('app-settings-open');
            }

            if (openBtn) openBtn.addEventListener('click', function () {
                openCreateModal();
            });

            /* capture: از آنجا که روی منو e.stopPropagation() است، در حباب رویداد هرگز به document نمی‌رسد */
            document.addEventListener('click', function (e) {
                var editBtn = e.target.closest('[data-cust-edit]');
                if (!editBtn) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var cid = editBtn.getAttribute('data-customer-id');
                if (cid) {
                    closeAllCustMenus();
                    openEditModal(parseInt(cid, 10));
                }
            }, true);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) closeModal();
                });
            }

            function initPickers() {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.pDatepicker) {
                    return;
                }
                destroyCustPickers();
                window.jQuery('#cust-membership-jdate, #cust-birth-jdate').each(function () {
                    var $el = window.jQuery(this);
                    $el.pDatepicker({
                        format: 'YYYY/MM/DD',
                        autoClose: true,
                        initialValue: false,
                        calendarType: 'persian',
                        initialValueType: 'persian',
                        toolbox: { calendarSwitch: false }
                    });
                });
            }

            if (window.jQuery) {
                window.jQuery(function () { initPickers(); });
            }

            function bindRemove(scope, selector, attr) {
                scope.querySelectorAll(selector).forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var row = btn.closest(attr);
                        if (row) row.remove();
                    });
                });
            }
            bindRemove(document, '[data-remove-bank]', '[data-bank-row]');
            bindRemove(document, '[data-remove-ref]', '[data-ref-row]');

            function addBankRow() {
                var i = bankIndex++;
                var div = document.createElement('div');
                div.className = 'cust-repeat-row';
                div.setAttribute('data-bank-row', '');
                div.innerHTML =
                    '<div class="cust-field"><label>شماره کارت / حساب / شبا</label>' +
                    '<input name="accounts[' + i + '][account_identifier]" placeholder="مثلاً شبا یا شماره کارت"></div>' +
                    '<div class="cust-field"><label>بانک</label><input name="accounts[' + i + '][bank_name]"></div>' +
                    '<div class="cust-field"><label>شعبه</label><input name="accounts[' + i + '][branch_name]"></div>' +
                    '<button type="button" class="cust-f-remove" data-remove-bank aria-label="حذف ردیف"><i class="fa-solid fa-trash"></i></button>';
                bankContainer.appendChild(div);
                div.querySelector('[data-remove-bank]').addEventListener('click', function () { div.remove(); });
            }

            function addRefRow() {
                var i = refIndex++;
                var div = document.createElement('div');
                div.className = 'cust-ref-row';
                div.setAttribute('data-ref-row', '');
                div.innerHTML =
                    '<div class="cust-field"><label>نام</label><input name="referrers[' + i + '][first_name]"></div>' +
                    '<div class="cust-field"><label>نام خانوادگی</label><input name="referrers[' + i + '][last_name]"></div>' +
                    '<div class="cust-field"><label>شماره تماس</label><input name="referrers[' + i + '][phone]" placeholder="09xxxxxxxxx"></div>' +
                    '<button type="button" class="cust-f-remove" data-remove-ref aria-label="حذف"><i class="fa-solid fa-trash"></i></button>';
                refContainer.appendChild(div);
                div.querySelector('[data-remove-ref]').addEventListener('click', function () { div.remove(); });
            }

            if (bankBtn) bankBtn.addEventListener('click', addBankRow);
            if (refBtn) refBtn.addEventListener('click', addRefRow);

            if (sendChk && sendHidden) {
                sendChk.addEventListener('change', function () {
                    sendHidden.value = sendChk.checked ? '1' : '0';
                });
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (skipPrompt) {
                        skipPrompt = false;
                        return;
                    }
                    if (!sendHidden || sendHidden.value === '1') return;
                    e.preventDefault();
                    if (typeof Swal === 'undefined') {
                        skipPrompt = true;
                        form.submit();
                        return;
                    }
                    Swal.fire({
                        icon: 'question',
                        title: 'ارسال پیامک',
                        text: 'آیا می‌خواهید نام کاربری و رمز عبور برای مشتری پیامک شود؟',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'بله، ارسال و ذخیره',
                        denyButtonText: 'خیر، فقط ذخیره',
                        cancelButtonText: 'عدم ذخیره',
                        reverseButtons: true,
                        allowOutsideClick: false,
                        focusCancel: false,
                        didOpen: function () {
                            var p = document.querySelector('.swal2-popup');
                            if (p) {
                                p.setAttribute('dir', 'rtl');
                            }
                        }
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            sendHidden.value = '1';
                            skipPrompt = true;
                            form.requestSubmit();
                            return;
                        }
                        if (result.isDenied) {
                            sendHidden.value = '0';
                            skipPrompt = true;
                            form.requestSubmit();
                            return;
                        }
                        // cancel، ESC یا بستن: هیچ‌چیز ذخیره نشود
                    });
                });
            }

            var custOpsBoxes = Array.from(document.querySelectorAll('[data-cust-ops]'));
            var custLeaveTimer = null;

            function placeCustMenu(toggle, menu) {
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

            function closeAllCustMenus() {
                custOpsBoxes.forEach(function (box) {
                    var m = box.querySelector('[data-cust-ops-menu]');
                    var t = box.querySelector('[data-cust-ops-toggle]');
                    if (m) m.hidden = true;
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }

            custOpsBoxes.forEach(function (box) {
                var toggle = box.querySelector('[data-cust-ops-toggle]');
                var menu = box.querySelector('[data-cust-ops-menu]');
                if (!toggle || !menu) return;

                function openThis() {
                    closeAllCustMenus();
                    placeCustMenu(toggle, menu);
                    toggle.setAttribute('aria-expanded', 'true');
                }

                function closeThis() {
                    menu.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }

                box.addEventListener('mouseenter', function () {
                    if (custLeaveTimer) {
                        clearTimeout(custLeaveTimer);
                        custLeaveTimer = null;
                    }
                    openThis();
                });

                box.addEventListener('mouseleave', function () {
                    custLeaveTimer = setTimeout(closeThis, 220);
                });

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isHidden = menu.hidden;
                    closeAllCustMenus();
                    if (isHidden) {
                        placeCustMenu(toggle, menu);
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });

                menu.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            });

            document.addEventListener('click', function () {
                closeAllCustMenus();
            });

            window.addEventListener('resize', function () {
                custOpsBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-cust-ops-menu]');
                    var toggle = box.querySelector('[data-cust-ops-toggle]');
                    if (!menu || !toggle || menu.hidden) return;
                    placeCustMenu(toggle, menu);
                });
            });

            window.addEventListener('scroll', function () {
                custOpsBoxes.forEach(function (box) {
                    var menu = box.querySelector('[data-cust-ops-menu]');
                    var toggle = box.querySelector('[data-cust-ops-toggle]');
                    if (!menu || !toggle || menu.hidden) return;
                    placeCustMenu(toggle, menu);
                });
            }, true);

            document.querySelectorAll('[data-cust-delete-form]').forEach(function (formEl) {
                formEl.addEventListener('submit', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!window.AdminSwal || !window.AdminSwal.confirm) {
                        formEl.submit();
                        return;
                    }
                    AdminSwal.confirm({
                        title: 'حذف مشتری',
                        text: 'این مشتری و اطلاعات مرتبطش حذف شود؟',
                        confirmButtonText: 'بله، حذف شود',
                        cancelButtonText: 'انصراف',
                    }).then(function (result) {
                        if (result && result.isConfirmed) {
                            formEl.submit();
                        }
                    });
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                closeAllCustMenus();
                if (overlay && !overlay.hidden) closeModal();
            });

            @if ($errors->any() && ! session('open_edit_customer_id'))
            custFormMode = 'create';
            removeMethodField();
            form.action = custStoreUrl;
            form.setAttribute('method', 'post');
            if (modalTitle) modalTitle.textContent = 'افزودن مشتری جدید';
            if (modalDesc) modalDesc.textContent = 'فیلدهای ستاره‌دار الزامی هستند. نام کاربری به‌صورت خودکار از روی موبایل ساخته می‌شود.';
            if (pwdReq) pwdReq.style.display = '';
            if (pwdHint) pwdHint.hidden = true;
            if (pwdInput) { pwdInput.required = true; }
            syncUsername();
            openModal();
            @elseif (session('open_edit_customer_id'))
            custFormMode = 'edit';
            addMethodPut();
            form.action = custUpdateUrl({{ (int) session('open_edit_customer_id') }});
            if (modalTitle) modalTitle.textContent = 'ویرایش مشتری';
            if (modalDesc) modalDesc.textContent = 'اطلاعات مشتری را ویرایش کنید. رمز عبور را فقط در صورت تغییر پر کنید.';
            if (pwdReq) pwdReq.style.display = 'none';
            if (pwdHint) pwdHint.hidden = false;
            if (pwdInput) { pwdInput.value = ''; pwdInput.required = false; }
            syncUsername();
            openModal();
            @endif
        })();
    </script>
@endpush
