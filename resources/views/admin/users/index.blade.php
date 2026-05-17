@php
    use Hekmatinasser\Jalali\Jalali;
@endphp
@extends('layouts.admin.app')

@section('title', 'کاربران')

@push('head')
    <style>
        .au-page { max-width: 100%; }
        .au-title { margin: 0 0 0.35rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .au-sub { margin: 0 0 0.9rem; font-size: 0.84rem; color: var(--muted); line-height: 1.55; }
        .au-flash { margin: 0 0 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; background: var(--primary-soft); color: var(--text); font-size: 0.84rem; font-weight: 600; border: 1px solid var(--border); }
        .au-errs { margin: 0 0 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; background: rgba(254, 242, 242, 0.95); border: 1px solid rgba(248, 113, 113, 0.5); color: #7f1d1d; font-size: 0.8rem; }
        html[data-theme="dark"] .au-errs { background: rgba(127, 29, 29, 0.25); color: #fecaca; border-color: rgba(248, 113, 113, 0.35); }
        .au-errs ul { margin: 0; padding-inline-start: 1.1rem; }
        .au-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.65rem; margin-bottom: 0.85rem; }
        .au-search { flex: 1 1 12rem; min-width: 0; max-width: 24rem; }
        .au-search input { width: 100%; padding: 0.55rem 0.75rem; border-radius: 0.7rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); font-size: 0.86rem; font-family: inherit; }
        .au-search input:focus { outline: none; border-color: rgba(37, 99, 235, 0.45); box-shadow: 0 0 0 3px var(--primary-soft); }
        .au-btn-add { font-family: inherit; font-size: 0.84rem; font-weight: 700; padding: 0.55rem 1rem; border-radius: 0.7rem; border: none; cursor: pointer; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.28); white-space: nowrap; }
        .au-btn-add:hover { filter: brightness(1.04); }
        .au-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.95rem; overflow: hidden; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05); }
        html[data-theme="dark"] .au-card { box-shadow: 0 10px 28px rgba(0, 0, 0, 0.25); }
        .au-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .au-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 52rem; }
        .au-table th, .au-table td { padding: 0.62rem 0.85rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .au-table th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        html[data-theme="dark"] .au-table th { background: rgba(30, 41, 59, 0.95); }
        .au-table td { color: var(--muted); font-weight: 600; }
        .au-table tbody tr.au-row--hidden { display: none; }
        .au-table tbody tr:hover td { background: rgba(37, 99, 235, 0.04); }
        .au-name { font-weight: 800; color: var(--text); }
        .au-num { direction: ltr; unicode-bidi: isolate; display: inline-block; }
        .au-status { text-align: center; }
        .au-ico-ok { color: #059669; font-size: 1.05rem; }
        html[data-theme="dark"] .au-ico-ok { color: #34d399; }
        .au-ico-no { color: #94a3b8; font-size: 1.05rem; }
        .au-ops { white-space: nowrap; text-align: center; }
        .au-ops-inner { display: inline-flex; align-items: center; gap: 0.35rem; }
        .au-ops-btn { font-family: inherit; cursor: pointer; border: 1px solid var(--border); background: var(--bg-card); color: var(--primary-dark); width: 2.15rem; height: 2.15rem; border-radius: 0.55rem; display: inline-grid; place-items: center; font-size: 0.92rem; }
        .au-ops-btn:hover { border-color: rgba(37, 99, 235, 0.35); }
        .au-ops-btn--danger { color: #b91c1c; border-color: rgba(248, 113, 113, 0.45); background: rgba(254, 242, 242, 0.6); }
        html[data-theme="dark"] .au-ops-btn--danger { color: #fca5a5; background: rgba(127, 29, 29, 0.35); }
        .au-empty { padding: 1.25rem; text-align: center; color: var(--muted); font-size: 0.86rem; }
        .au-you { font-size: 0.68rem; font-weight: 700; color: var(--primary-dark); margin-inline-start: 0.35rem; }

        .au-modal { position: fixed; inset: 0; z-index: 1300; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(2px); }
        html[data-theme="dark"] .au-modal { background: rgba(0, 0, 0, 0.55); }
        .au-modal[hidden] { display: none !important; }
        .au-modal__box { background: var(--bg-card); color: var(--text); border: 1px solid var(--border); border-radius: 1rem; width: min(100%, 40rem); max-height: min(92vh, 680px); min-height: 0; display: flex; flex-direction: column; box-shadow: 0 28px 70px rgba(15, 23, 42, 0.18); }
        .au-modal__form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
        .au-modal__head { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-shrink: 0; }
        .au-modal__title { margin: 0; font-size: 1rem; font-weight: 800; }
        .au-modal__close { border: none; background: var(--primary-soft); color: var(--primary-dark); width: 2.15rem; height: 2.15rem; border-radius: 0.55rem; cursor: pointer; display: grid; place-items: center; font-size: 1rem; }
        .au-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.65rem 1rem 0; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg-card); justify-content: center; }
        .au-tab { font-family: inherit; font-size: 0.8rem; font-weight: 700; padding: 0.5rem 1rem; border-radius: 0.55rem 0.55rem 0 0; border: 1px solid transparent; border-bottom: none; background: transparent; color: var(--muted); cursor: pointer; }
        .au-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: var(--border); }
        .au-modal__body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 0.85rem 1rem; }
        .au-tab-panel { display: none; }
        .au-tab-panel.is-active { display: block; }
        .au-tab-panel--placeholder { text-align: center; padding: 2rem 1rem; color: var(--muted); font-size: 0.88rem; line-height: 1.7; }
        .au-field { margin-bottom: 0.85rem; }
        .au-field label { display: block; font-size: 0.76rem; font-weight: 650; color: var(--muted); margin-bottom: 0.32rem; }
        .au-field .req { color: #dc2626; font-weight: 800; }
        .au-input { width: 100%; padding: 0.52rem 0.72rem; border-radius: 0.65rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); font-size: 0.86rem; font-family: inherit; box-sizing: border-box; }
        .au-input:focus { outline: none; border-color: rgba(37, 99, 235, 0.45); box-shadow: 0 0 0 3px var(--primary-soft); }
        .au-input[dir="ltr"] { text-align: left; }
        .au-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem 1rem; }
        @media (max-width: 520px) { .au-grid2 { grid-template-columns: 1fr; } }
        .au-check { display: flex; align-items: center; gap: 0.45rem; font-size: 0.84rem; font-weight: 600; cursor: pointer; user-select: none; margin-top: 0.25rem; }
        .au-check input { accent-color: var(--primary); width: 1rem; height: 1rem; }
        .au-field-error { font-size: 0.72rem; color: #dc2626; margin-top: 0.25rem; }
        html[data-theme="dark"] .au-field-error { color: #fca5a5; }
        .au-modal__foot { padding: 0.75rem 1rem; border-top: 1px solid var(--border); flex-shrink: 0; }
        .au-modal__actions { display: flex; flex-wrap: wrap; gap: 0.45rem; justify-content: flex-end; }
        .au-btn { font-family: inherit; font-size: 0.82rem; font-weight: 700; padding: 0.52rem 1rem; border-radius: 0.65rem; cursor: pointer; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); }
        .au-btn--primary { border: none; background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; }
        .au-errs--modal { margin: 0 0 1rem; }
    </style>
@endpush

@section('content')
    @php
        $auEditingId = old('admin_user_id');
        $auRoutePlaceholder = 999999001;
        $auUpdateUrlTemplate = str_replace((string) $auRoutePlaceholder, '__ID__', route('admin.users.update', ['admin' => $auRoutePlaceholder]));
        $auShouldOpenModal = $errors->any() || session('au_open_modal');
    @endphp
    <div class="au-page">
        <h1 class="au-title">کاربران</h1>
        <p class="au-sub">مدیریت حساب‌های دسترسی به پنل ادمین؛ تعریف دسترسی‌ها در مرحله بعد تکمیل می‌شود.</p>

        @if (session('flash_success'))
            <div class="au-flash" role="status">{{ session('flash_success') }}</div>
        @endif
        @if ($errors->has('delete') || $errors->has('is_active'))
            <div class="au-errs" role="alert">
                <ul>
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="au-toolbar">
            <div class="au-search">
                <input
                    type="search"
                    id="au-table-search"
                    placeholder="جستجو در نام، نام کاربری، موبایل…"
                    autocomplete="off"
                    aria-label="جستجو در جدول کاربران"
                    aria-controls="au-users-tbody"
                >
            </div>
            <button type="button" class="au-btn-add" id="au-add-btn">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                افزودن کاربر
            </button>
        </div>

        <div class="au-card">
            <div class="au-table-wrap">
                <table class="au-table">
                    <thead>
                        <tr>
                            <th scope="col">نام کاربر</th>
                            <th scope="col">نام کاربری</th>
                            <th scope="col">شماره تماس</th>
                            <th scope="col">دفعات مراجعه</th>
                            <th scope="col">آخرین فعالیت</th>
                            <th scope="col">وضعیت اکانت</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="au-users-tbody">
                        @forelse ($admins as $admin)
                            @php
                                $searchBlob = mb_strtolower(
                                    $admin->fullName().' '.($admin->username ?? '').' '.($admin->mobile ?? ''),
                                    'UTF-8',
                                );
                                $lastLogin = $admin->last_login_at
                                    ? Jalali::enToFaNumbers(Jalali::instance($admin->last_login_at)->format('Y/m/d')).' — '.Jalali::enToFaNumbers($admin->last_login_at->format('H:i'))
                                    : '—';
                            @endphp
                            <tr class="au-row" data-search="{{ e($searchBlob) }}">
                                <td>
                                    <span class="au-name">{{ $admin->fullName() }}</span>
                                    @if ($admin->id === $currentAdminId)
                                        <span class="au-you">(شما)</span>
                                    @endif
                                </td>
                                <td><span class="au-num">{{ $admin->username }}</span></td>
                                <td><span class="au-num">{{ $admin->mobile ? Jalali::enToFaNumbers($admin->mobile) : '—' }}</span></td>
                                <td><span class="au-num">{{ Jalali::enToFaNumbers((string) (int) $admin->login_count) }}</span></td>
                                <td>{{ $lastLogin }}</td>
                                <td class="au-status">
                                    @if ($admin->is_active)
                                        <i class="fa-solid fa-circle-check au-ico-ok" title="فعال" aria-label="فعال"></i>
                                    @else
                                        <i class="fa-solid fa-circle-xmark au-ico-no" title="غیرفعال" aria-label="غیرفعال"></i>
                                    @endif
                                </td>
                                <td class="au-ops">
                                    <div class="au-ops-inner">
                                        <button type="button" class="au-ops-btn au-ops-btn--edit" data-au-edit="{{ $admin->id }}" title="ویرایش" aria-label="ویرایش">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="au-ops-btn au-ops-btn--danger"
                                            data-au-delete="{{ $admin->id }}"
                                            title="حذف"
                                            aria-label="حذف"
                                            @if ($admin->id === $currentAdminId) disabled style="opacity:0.45;cursor:not-allowed" @endif
                                        >
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <form id="au-del-{{ $admin->id }}" method="post" action="{{ route('admin.users.destroy', $admin) }}" hidden aria-hidden="true">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="au-empty">هنوز کاربری ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="au-user-modal" class="au-modal" hidden aria-hidden="true">
        <div class="au-modal__box" role="dialog" aria-modal="true" aria-labelledby="au-modal-title">
            <div class="au-modal__head">
                <h2 class="au-modal__title" id="au-modal-title">{{ $auEditingId ? 'ویرایش کاربر' : 'افزودن کاربر' }}</h2>
                <button type="button" class="au-modal__close" id="au-modal-close" aria-label="بستن">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form
                id="au-user-form"
                method="post"
                action="{{ $auEditingId ? route('admin.users.update', ['admin' => (int) $auEditingId]) : route('admin.users.store') }}"
                class="au-modal__form"
                novalidate
            >
                @csrf
                @if ($auEditingId)
                    @method('PUT')
                @endif
                <input type="hidden" name="admin_user_id" id="au-hidden-admin-id" value="{{ $auEditingId ?? '' }}">

                <div class="au-tabs" role="tablist" aria-label="بخش‌های فرم کاربر">
                    <button type="button" class="au-tab is-active" data-au-tab="profile" role="tab" aria-selected="true">اطلاعات کاربری</button>
                    <button type="button" class="au-tab" data-au-tab="permissions" role="tab" aria-selected="false">دسترسی‌ها</button>
                </div>

                <div class="au-modal__body">
                    @if ($errors->any() && ! $errors->has('delete'))
                        <div class="au-errs au-errs--modal" role="alert">
                            <ul>
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="au-tab-panel is-active" data-au-panel="profile" role="tabpanel">
                        <div class="au-field">
                            <label for="au-username">نام کاربری <span class="req">*</span></label>
                            <input
                                type="text"
                                class="au-input"
                                id="au-username"
                                name="username"
                                dir="ltr"
                                autocomplete="off"
                                maxlength="64"
                                pattern="[a-zA-Z0-9._-]+"
                                value="{{ old('username') }}"
                                required
                            >
                            @error('username')<div class="au-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="au-grid2">
                            <div class="au-field">
                                <label for="au-first-name">نام <span class="req">*</span></label>
                                <input type="text" class="au-input" id="au-first-name" name="first_name" value="{{ old('first_name') }}" maxlength="80" required>
                                @error('first_name')<div class="au-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="au-field">
                                <label for="au-last-name">نام خانوادگی <span class="req">*</span></label>
                                <input type="text" class="au-input" id="au-last-name" name="last_name" value="{{ old('last_name') }}" maxlength="80" required>
                                @error('last_name')<div class="au-field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="au-field">
                            <label for="au-mobile">موبایل <span class="req">*</span></label>
                            <input type="tel" class="au-input" id="au-mobile" name="mobile" dir="ltr" inputmode="numeric" maxlength="11" placeholder="09123456789" value="{{ old('mobile') }}" required>
                            @error('mobile')<div class="au-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="au-grid2">
                            <div class="au-field">
                                <label for="au-password">کلمه عبور @if (! $auEditingId)<span class="req">*</span>@endif</label>
                                <input
                                    type="password"
                                    class="au-input"
                                    id="au-password"
                                    name="password"
                                    dir="ltr"
                                    autocomplete="new-password"
                                    @if (! $auEditingId) required @endif
                                >
                                @error('password')<div class="au-field-error">{{ $message }}</div>@enderror
                                @if ($auEditingId)
                                    <p class="au-field-error" style="color:var(--muted);font-weight:500;margin-top:0.2rem">در صورت خالی بودن، رمز تغییر نمی‌کند.</p>
                                @endif
                            </div>
                            <div class="au-field">
                                <label for="au-password-confirmation">تکرار کلمه عبور @if (! $auEditingId)<span class="req">*</span>@endif</label>
                                <input
                                    type="password"
                                    class="au-input"
                                    id="au-password-confirmation"
                                    name="password_confirmation"
                                    dir="ltr"
                                    autocomplete="new-password"
                                    @if (! $auEditingId) required @endif
                                >
                            </div>
                        </div>
                        <label class="au-check">
                            <input type="checkbox" name="is_active" value="1" id="au-is-active" @checked(old('is_active', true))>
                            اکانت فعال باشد
                        </label>
                    </div>

                    <div class="au-tab-panel" data-au-panel="permissions" role="tabpanel">
                        <p class="au-tab-panel--placeholder">متن تستی — بخش دسترسی‌ها در مرحله بعد پیاده‌سازی می‌شود.</p>
                    </div>
                </div>

                <div class="au-modal__foot">
                    <div class="au-modal__actions">
                        <button type="button" class="au-btn" id="au-modal-cancel">انصراف</button>
                        <button type="submit" class="au-btn au-btn--primary" id="au-modal-submit">ذخیره</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var editMap = @json($adminEditMap);
            var updateUrlTemplate = @json($auUpdateUrlTemplate);
            var storeUrl = @json(route('admin.users.store'));
            var shouldOpen = @json($auShouldOpenModal);

            var modal = document.getElementById('au-user-modal');
            var form = document.getElementById('au-user-form');
            var titleEl = document.getElementById('au-modal-title');
            var hiddenId = document.getElementById('au-hidden-admin-id');
            var pwd = document.getElementById('au-password');
            var pwdConf = document.getElementById('au-password-confirmation');
            var searchInput = document.getElementById('au-table-search');
            var tbody = document.getElementById('au-users-tbody');

            function openModal() {
                if (!modal) return;
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                if (!modal) return;
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function setTab(name) {
                document.querySelectorAll('.au-tab').forEach(function (btn) {
                    var on = btn.getAttribute('data-au-tab') === name;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                document.querySelectorAll('.au-tab-panel').forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-au-panel') === name);
                });
            }

            function resetToCreate() {
                if (!form) return;
                form.action = storeUrl;
                form.querySelector('input[name="_method"]')?.remove();
                if (hiddenId) hiddenId.value = '';
                if (titleEl) titleEl.textContent = 'افزودن کاربر';
                if (pwd) { pwd.required = true; pwd.value = ''; }
                if (pwdConf) { pwdConf.required = true; pwdConf.value = ''; }
                form.reset();
                var activeChk = document.getElementById('au-is-active');
                if (activeChk) activeChk.checked = true;
                setTab('profile');
            }

            function fillEdit(id) {
                var d = editMap[id];
                if (!d || !form) return;
                form.action = updateUrlTemplate.replace('__ID__', String(id));
                if (!form.querySelector('input[name="_method"]')) {
                    var m = document.createElement('input');
                    m.type = 'hidden';
                    m.name = '_method';
                    m.value = 'PUT';
                    form.appendChild(m);
                }
                if (hiddenId) hiddenId.value = String(id);
                if (titleEl) titleEl.textContent = 'ویرایش کاربر';
                document.getElementById('au-username').value = d.username || '';
                document.getElementById('au-first-name').value = d.first_name || '';
                document.getElementById('au-last-name').value = d.last_name || '';
                document.getElementById('au-mobile').value = d.mobile || '';
                if (pwd) { pwd.required = false; pwd.value = ''; }
                if (pwdConf) { pwdConf.required = false; pwdConf.value = ''; }
                var activeChk = document.getElementById('au-is-active');
                if (activeChk) activeChk.checked = !!d.is_active;
                setTab('profile');
            }

            document.getElementById('au-add-btn')?.addEventListener('click', function () {
                resetToCreate();
                openModal();
            });

            document.querySelectorAll('[data-au-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = parseInt(btn.getAttribute('data-au-edit'), 10);
                    if (!id) return;
                    fillEdit(id);
                    openModal();
                });
            });

            document.querySelectorAll('[data-au-delete]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.disabled) return;
                    var id = btn.getAttribute('data-au-delete');
                    if (!id) return;
                    if (!window.confirm('این کاربر حذف شود؟')) return;
                    document.getElementById('au-del-' + id)?.submit();
                });
            });

            document.querySelectorAll('.au-tab').forEach(function (tabBtn) {
                tabBtn.addEventListener('click', function () {
                    setTab(tabBtn.getAttribute('data-au-tab') || 'profile');
                });
            });

            document.getElementById('au-modal-close')?.addEventListener('click', closeModal);
            document.getElementById('au-modal-cancel')?.addEventListener('click', closeModal);
            modal?.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
            });

            if (searchInput && tbody) {
                searchInput.addEventListener('input', function () {
                    var q = (searchInput.value || '').trim().toLowerCase();
                    tbody.querySelectorAll('.au-row').forEach(function (row) {
                        var blob = (row.getAttribute('data-search') || '').toLowerCase();
                        row.classList.toggle('au-row--hidden', q !== '' && blob.indexOf(q) === -1);
                    });
                });
            }

            if (shouldOpen) {
                var editId = @json($auEditingId ? (int) $auEditingId : null);
                if (editId && editMap[editId]) {
                    fillEdit(editId);
                }
                openModal();
            }
        })();
    </script>
@endpush
