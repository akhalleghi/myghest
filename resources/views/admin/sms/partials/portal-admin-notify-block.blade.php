<article class="sms-notify-block @if($enabled) is-on @endif" id="{{ $blockId }}" data-sms-notify-block>
    <div class="sms-notify-block__head">
        <span class="sms-notify-block__icon {{ $iconClass }}" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
        <div class="sms-notify-block__titles">
            <h3 class="sms-notify-block__name">{{ $title }}</h3>
            <p class="sms-notify-block__desc">{{ $description }}</p>
        </div>
    </div>

    <form method="post" action="{{ $formAction }}" class="sms-notify-block__form" id="{{ $formId }}">
        @csrf
        <div class="sms-toggle-row sms-notify-block__toggle">
            <label class="sms-toggle-label">
                <input
                    type="checkbox"
                    name="{{ $enabledName }}"
                    id="{{ $enabledId }}"
                    value="1"
                    @checked($enabled)
                >
                فعال‌سازی این گزینه
            </label>
            @error($enabledName)<div class="sms-field-error">{{ $message }}</div>@enderror
        </div>

        <div id="{{ $fieldsId }}" class="sms-notify-block__body @if(! $enabled) sms-reminder-hidden @endif">
            <div class="sms-notify-row">
                <button type="button" class="sms-notify-pick-btn" id="{{ $openRecipientsId }}">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    انتخاب دریافت‌کنندگان
                    <span id="{{ $recipientCountId }}">({{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) count($selectedIds)) }})</span>
                </button>
                @error($recipientName)<div class="sms-field-error">{{ $message }}</div>@enderror
            </div>

            <div id="{{ $recipientInputsId }}">
                @foreach ($selectedIds as $rid)
                    <input type="hidden" name="{{ $recipientName }}[]" value="{{ $rid }}">
                @endforeach
            </div>

            @include('admin.sms.partials.admin-login-notify-message-field', [
                'textareaId' => $messageId,
                'textareaName' => $messageName,
                'textareaValue' => $messageValue,
                'previewId' => $previewId,
                'patternAttr' => $patternAttr,
                'errorKey' => $messageName,
                'notifyPatterns' => $notifyPatterns,
            ])
        </div>

        <div class="sms-notify-block__foot">
            <button class="sms-settings-submit" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                {{ $submitLabel }}
            </button>
        </div>
    </form>
</article>
