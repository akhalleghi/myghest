@php
    use App\Support\InstallmentBookletPrintSettings;
    $printSettings = InstallmentBookletPrintSettings::resolved();
    $printColumns = is_array($printSettings['columns'] ?? null) ? $printSettings['columns'] : InstallmentBookletPrintSettings::columnDefaults();
    $printLogoPath = trim((string) ($printSettings['logo_path'] ?? ''));
    $printLogoUrl = $printLogoPath !== '' ? asset($printLogoPath) : null;
@endphp

<section class="app-settings-panel" data-settings-panel="print" @if(($adminAppSettingsActivePanel ?? '') !== 'print') hidden @endif>
    <h4 class="app-settings-panel-title">تنظیمات چاپ</h4>
    <p class="app-settings-panel-subtitle">شخصی‌سازی خروجی چاپ دفترچه اقساط پرونده وام.</p>
    <form method="post" action="{{ route('admin.app-settings.print.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="remove_print_logo" value="0">

        <div class="app-settings-card">
            <h4>سربرگ و عنوان</h4>
            <p class="app-settings-card-desc">عنوان، زیرعنوان و لوگوی بالای صفحه چاپ دفترچه اقساط.</p>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-title-main">عنوان اصلی</label>
                    <input id="print-title-main" type="text" name="title_main" value="{{ old('title_main', $printSettings['title_main']) }}" required>
                    @error('title_main')<div class="app-settings-error">{{ $message }}</div>@enderror
                </div>
                <div class="app-settings-field">
                    <label for="print-subtitle">زیرعنوان</label>
                    <input id="print-subtitle" type="text" name="subtitle" value="{{ old('subtitle', $printSettings['subtitle']) }}" required>
                    @error('subtitle')<div class="app-settings-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-loan-amount-label">برچسب مبلغ وام (بالای صفحه)</label>
                    <input id="print-loan-amount-label" type="text" name="loan_amount_label" value="{{ old('loan_amount_label', $printSettings['loan_amount_label']) }}" required>
                    @error('loan_amount_label')<div class="app-settings-error">{{ $message }}</div>@enderror
                </div>
                <div class="app-settings-field">
                    <label for="print-font-scale">اندازه فونت چاپ</label>
                    <select id="print-font-scale" name="font_scale" required>
                        <option value="small" @selected(old('font_scale', $printSettings['font_scale']) === 'small')>کوچک</option>
                        <option value="normal" @selected(old('font_scale', $printSettings['font_scale']) === 'normal')>معمولی</option>
                        <option value="large" @selected(old('font_scale', $printSettings['font_scale']) === 'large')>بزرگ</option>
                    </select>
                    @error('font_scale')<div class="app-settings-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-show-loan-amount">نمایش مبلغ وام در بالای صفحه</label>
                    <select id="print-show-loan-amount" name="show_loan_amount" required>
                        <option value="1" @selected(old('show_loan_amount', $printSettings['show_loan_amount']) === '1')>بله</option>
                        <option value="0" @selected(old('show_loan_amount', $printSettings['show_loan_amount']) === '0')>خیر</option>
                    </select>
                </div>
                <div class="app-settings-field">
                    <label for="print-show-logo">نمایش لوگو</label>
                    <select id="print-show-logo" name="show_logo" required>
                        <option value="1" @selected(old('show_logo', $printSettings['show_logo']) === '1')>بله</option>
                        <option value="0" @selected(old('show_logo', $printSettings['show_logo']) === '0')>خیر</option>
                    </select>
                </div>
            </div>
            <div class="app-settings-field">
                <label for="print-use-app-logo">در صورت نبود لوگوی اختصاصی، از لوگوی سامانه استفاده شود</label>
                <select id="print-use-app-logo" name="use_app_logo" required>
                    <option value="1" @selected(old('use_app_logo', $printSettings['use_app_logo']) === '1')>بله</option>
                    <option value="0" @selected(old('use_app_logo', $printSettings['use_app_logo']) === '0')>خیر</option>
                </select>
            </div>
            <div class="app-settings-field app-settings-field--with-action">
                <label for="print-logo-file">لوگوی اختصاصی چاپ</label>
                <div class="app-settings-inline-action">
                    <input id="print-logo-file" type="file" name="print_logo" accept="image/png,image/jpeg,image/webp,image/gif">
                    @if($printLogoUrl)
                        <img src="{{ $printLogoUrl }}" alt="لوگوی چاپ" style="max-height:48px;border-radius:6px;">
                        <button type="button" class="app-settings-btn app-settings-btn--secondary" onclick="this.form.remove_print_logo.value='1';this.form.submit();">حذف لوگو</button>
                    @endif
                </div>
                @error('print_logo')<div class="app-settings-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="app-settings-card">
            <h4>بلوک پنل کاربری</h4>
            <p class="app-settings-card-desc">آدرس سایت، نام کاربری و رمز عبور مشتری در پایین دفترچه.</p>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-show-portal-block">نمایش بلوک پنل کاربری</label>
                    <select id="print-show-portal-block" name="show_portal_block" required>
                        <option value="1" @selected(old('show_portal_block', $printSettings['show_portal_block']) === '1')>بله</option>
                        <option value="0" @selected(old('show_portal_block', $printSettings['show_portal_block']) === '0')>خیر</option>
                    </select>
                </div>
                <div class="app-settings-field">
                    <label for="print-show-username">نمایش نام کاربری</label>
                    <select id="print-show-username" name="show_username" required>
                        <option value="1" @selected(old('show_username', $printSettings['show_username']) === '1')>بله</option>
                        <option value="0" @selected(old('show_username', $printSettings['show_username']) === '0')>خیر</option>
                    </select>
                </div>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-show-password">نمایش رمز عبور (در صورت ذخیره امن)</label>
                    <select id="print-show-password" name="show_password" required>
                        <option value="1" @selected(old('show_password', $printSettings['show_password']) === '1')>بله</option>
                        <option value="0" @selected(old('show_password', $printSettings['show_password']) === '0')>خیر</option>
                    </select>
                </div>
                <div class="app-settings-field">
                    <label for="print-password-unavailable">متن جایگزین وقتی رمز در دسترس نیست</label>
                    <input id="print-password-unavailable" type="text" name="password_unavailable_text" value="{{ old('password_unavailable_text', $printSettings['password_unavailable_text']) }}">
                </div>
            </div>
            <div class="app-settings-field">
                <label for="print-portal-intro">متن معرفی پنل</label>
                <input id="print-portal-intro" type="text" name="portal_intro_text" value="{{ old('portal_intro_text', $printSettings['portal_intro_text']) }}" required>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-username-label">برچسب نام کاربری</label>
                    <input id="print-username-label" type="text" name="username_label" value="{{ old('username_label', $printSettings['username_label']) }}" required>
                </div>
                <div class="app-settings-field">
                    <label for="print-password-label">برچسب رمز عبور</label>
                    <input id="print-password-label" type="text" name="password_label" value="{{ old('password_label', $printSettings['password_label']) }}" required>
                </div>
            </div>
        </div>

        <div class="app-settings-card">
            <h4>بخش‌های صفحه</h4>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-show-summary">نمایش جدول خلاصه</label>
                    <select id="print-show-summary" name="show_summary_table" required>
                        <option value="1" @selected(old('show_summary_table', $printSettings['show_summary_table']) === '1')>بله</option>
                        <option value="0" @selected(old('show_summary_table', $printSettings['show_summary_table']) === '0')>خیر</option>
                    </select>
                </div>
                <div class="app-settings-field">
                    <label for="print-show-detail">نمایش جدول اقساط</label>
                    <select id="print-show-detail" name="show_detail_table" required>
                        <option value="1" @selected(old('show_detail_table', $printSettings['show_detail_table']) === '1')>بله</option>
                        <option value="0" @selected(old('show_detail_table', $printSettings['show_detail_table']) === '0')>خیر</option>
                    </select>
                </div>
            </div>
            <div class="app-settings-field">
                <label for="print-show-signatures">نمایش بخش امضا</label>
                <select id="print-show-signatures" name="show_signatures" required>
                    <option value="1" @selected(old('show_signatures', $printSettings['show_signatures']) === '1')>بله</option>
                    <option value="0" @selected(old('show_signatures', $printSettings['show_signatures']) === '0')>خیر</option>
                </select>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="print-seller-signature">برچسب امضای فروشنده</label>
                    <input id="print-seller-signature" type="text" name="seller_signature_label" value="{{ old('seller_signature_label', $printSettings['seller_signature_label']) }}" required>
                </div>
                <div class="app-settings-field">
                    <label for="print-buyer-signature">برچسب امضای خریدار</label>
                    <input id="print-buyer-signature" type="text" name="buyer_signature_label" value="{{ old('buyer_signature_label', $printSettings['buyer_signature_label']) }}" required>
                </div>
            </div>
        </div>

        <div class="app-settings-card">
            <h4>ستون‌های جدول اقساط</h4>
            <p class="app-settings-card-desc">برای هر ستون می‌توانید نمایش و عنوان سفارشی تعیین کنید.</p>
            @foreach(InstallmentBookletPrintSettings::orderedColumnKeys() as $columnKey)
                @php
                    $column = $printColumns[$columnKey] ?? ['show' => '1', 'label' => $columnKey];
                @endphp
                <div class="app-settings-row">
                    <div class="app-settings-field">
                        <label for="print-col-{{ $columnKey }}-show">{{ $column['label'] }} — نمایش</label>
                        <select id="print-col-{{ $columnKey }}-show" name="columns[{{ $columnKey }}][show]" required>
                            <option value="1" @selected(old('columns.'.$columnKey.'.show', $column['show']) === '1')>بله</option>
                            <option value="0" @selected(old('columns.'.$columnKey.'.show', $column['show']) === '0')>خیر</option>
                        </select>
                    </div>
                    <div class="app-settings-field">
                        <label for="print-col-{{ $columnKey }}-label">عنوان ستون</label>
                        <input id="print-col-{{ $columnKey }}-label" type="text" name="columns[{{ $columnKey }}][label]" value="{{ old('columns.'.$columnKey.'.label', $column['label']) }}" required>
                        @error('columns.'.$columnKey.'.label')<div class="app-settings-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endforeach
        </div>

        <div class="app-settings-actions">
            <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
        </div>
    </form>
</section>
