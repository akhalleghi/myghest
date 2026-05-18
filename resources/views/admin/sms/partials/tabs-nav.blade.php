@php
    $smsAllowedTabs = $smsAllowedTabs ?? [];
    $smsActiveTab = $smsActiveTab ?? array_key_first($smsAllowedTabs);
@endphp
@if(count($smsAllowedTabs) > 1)
    <div class="sms-tabs" role="tablist" aria-label="تب‌های مدیریت پیامک">
        @if(isset($smsAllowedTabs['reports']))
            <button type="button" class="sms-tab @if($smsActiveTab === 'reports') is-active @endif" role="tab" aria-selected="{{ $smsActiveTab === 'reports' ? 'true' : 'false' }}" data-sms-tab="reports">گزارش پیامک‌ها</button>
        @endif
        @if(isset($smsAllowedTabs['templates']))
            <button type="button" class="sms-tab @if($smsActiveTab === 'templates') is-active @endif" role="tab" aria-selected="{{ $smsActiveTab === 'templates' ? 'true' : 'false' }}" data-sms-tab="templates">الگوهای پیامک</button>
        @endif
        @if(isset($smsAllowedTabs['settings']))
            <button type="button" class="sms-tab @if($smsActiveTab === 'settings') is-active @endif" role="tab" aria-selected="{{ $smsActiveTab === 'settings' ? 'true' : 'false' }}" data-sms-tab="settings">تنظیمات پنل</button>
        @endif
    </div>
@endif
