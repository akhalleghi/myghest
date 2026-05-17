@php
    $notifyPatterns = $notifyPatterns ?? [
        ['token' => '{admin_full_name}', 'label' => 'نام و نام خانوادگی'],
        ['token' => '{admin_username}', 'label' => 'نام کاربری'],
        ['token' => '{app_name}', 'label' => 'نام سامانه'],
    ];
@endphp
<div class="sms-settings-field sms-notify-message-field">
    <label for="{{ $textareaId }}">متن پیامک</label>
    <textarea id="{{ $textareaId }}" class="sms-notify-message" name="{{ $textareaName }}" maxlength="500">{{ $textareaValue }}</textarea>
    @error($errorKey)<div class="sms-field-error">{{ $message }}</div>@enderror
    <div class="sms-notify-patterns" aria-label="جای‌نگاشت‌های قابل استفاده">
        @foreach ($notifyPatterns as $pattern)
            <button type="button" class="sms-notify-pattern" {{ $patternAttr }}="{{ $pattern['token'] }}">{{ $pattern['label'] }}</button>
        @endforeach
    </div>
    <p class="sms-notify-preview-label">پیش‌نمایش پیامک</p>
    <div id="{{ $previewId }}" class="sms-notify-preview" aria-live="polite"></div>
</div>
