@php
    use App\Support\AdminReportsDisplaySettings;
    $rptDisplay = AdminReportsDisplaySettings::resolved();
@endphp

<section class="app-settings-panel" data-settings-panel="reports" @if(($adminAppSettingsActivePanel ?? '') !== 'reports') hidden @endif>
    <h4 class="app-settings-panel-title">گزارش‌ها</h4>
    <p class="app-settings-panel-subtitle">چینش، اندازه فونت و خوانایی جداول همهٔ گزارش‌های پنل ادمین.</p>
    <form method="post" action="{{ route('admin.app-settings.reports.update') }}">
        @csrf
        <div class="app-settings-card">
            <h4>اندازه و تراکم</h4>
            <p class="app-settings-card-desc">اندازهٔ فونت جداول گزارش و فاصلهٔ داخلی سلول‌ها را تنظیم کنید.</p>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="rpt-font-scale">اندازه فونت جدول گزارش</label>
                    <select id="rpt-font-scale" name="font_scale" required>
                        <option value="small" @selected(old('font_scale', $rptDisplay['font_scale']) === 'small')>کوچک</option>
                        <option value="normal" @selected(old('font_scale', $rptDisplay['font_scale']) === 'normal')>معمولی</option>
                        <option value="large" @selected(old('font_scale', $rptDisplay['font_scale']) === 'large')>بزرگ</option>
                        <option value="xlarge" @selected(old('font_scale', $rptDisplay['font_scale']) === 'xlarge')>خیلی بزرگ</option>
                    </select>
                    @error('font_scale')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="app-settings-field">
                    <label for="rpt-cell-density">تراکم سلول‌ها</label>
                    <select id="rpt-cell-density" name="cell_density" required>
                        <option value="compact" @selected(old('cell_density', $rptDisplay['cell_density']) === 'compact')>فشرده</option>
                        <option value="normal" @selected(old('cell_density', $rptDisplay['cell_density']) === 'normal')>معمولی</option>
                        <option value="comfortable" @selected(old('cell_density', $rptDisplay['cell_density']) === 'comfortable')>راحت</option>
                    </select>
                    @error('cell_density')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="app-settings-card">
            <h4>چینش ستون‌ها</h4>
            <p class="app-settings-card-desc">متن، مبالغ و سرستون‌ها را هم‌تراز کنید تا اعداد و توضیحات در یک خط دیده شوند.</p>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="rpt-text-align">چینش متن معمولی</label>
                    <select id="rpt-text-align" name="text_align" required>
                        <option value="right" @selected(old('text_align', $rptDisplay['text_align']) === 'right')>راست</option>
                        <option value="center" @selected(old('text_align', $rptDisplay['text_align']) === 'center')>وسط</option>
                    </select>
                    @error('text_align')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="app-settings-field">
                    <label for="rpt-numeric-align">چینش مبالغ و اعداد</label>
                    <select id="rpt-numeric-align" name="numeric_align" required>
                        <option value="center" @selected(old('numeric_align', $rptDisplay['numeric_align']) === 'center')>وسط</option>
                        <option value="right" @selected(old('numeric_align', $rptDisplay['numeric_align']) === 'right')>راست</option>
                    </select>
                    @error('numeric_align')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="app-settings-row">
                <div class="app-settings-field">
                    <label for="rpt-header-mode">چینش سرستون‌ها</label>
                    <select id="rpt-header-mode" name="header_mode" required>
                        <option value="match" @selected(old('header_mode', $rptDisplay['header_mode']) === 'match')>هم‌تراز با نوع ستون (پیشنهادی)</option>
                        <option value="center" @selected(old('header_mode', $rptDisplay['header_mode']) === 'center')>همه وسط</option>
                        <option value="right" @selected(old('header_mode', $rptDisplay['header_mode']) === 'right')>همه راست</option>
                    </select>
                    @error('header_mode')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="app-settings-field">
                    <label for="rpt-stack-align">چینش سلول‌های چندخطی (مبلغ / وضعیت)</label>
                    <select id="rpt-stack-align" name="stack_align" required>
                        <option value="center" @selected(old('stack_align', $rptDisplay['stack_align']) === 'center')>وسط</option>
                        <option value="start" @selected(old('stack_align', $rptDisplay['stack_align']) === 'start')>راست (شروع)</option>
                    </select>
                    @error('stack_align')
                        <div class="app-settings-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="app-settings-actions">
            <button type="submit" class="app-settings-btn app-settings-btn--primary">ذخیره تغییرات</button>
        </div>
    </form>
</section>
