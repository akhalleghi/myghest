@php
    $adminCanBackupView = $adminCanBackupView ?? false;
    $adminCanBackupCreate = $adminCanBackupCreate ?? false;
    $adminCanBackupDownload = $adminCanBackupDownload ?? false;
    $adminCanBackupDelete = $adminCanBackupDelete ?? false;
    $adminCanBackupRestore = $adminCanBackupRestore ?? false;
    $adminBackupDatabaseName = $adminBackupDatabaseName ?? '';
    $backupTableColspan = 3
        + ($adminCanBackupDownload ? 1 : 0)
        + ($adminCanBackupRestore ? 1 : 0)
        + ($adminCanBackupDelete ? 1 : 0);
@endphp
<div id="db-backup-overlay" class="db-backup-overlay" hidden aria-hidden="true">
    <div
        id="db-backup-modal"
        class="db-backup-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="db-backup-title"
        data-list-url="{{ route('admin.backups.index') }}"
        data-store-url="{{ route('admin.backups.store') }}"
        data-restore-url="{{ route('admin.backups.restore') }}"
        data-download-url-template="{{ route('admin.backups.download', ['backup' => '__FILE__']) }}"
        data-delete-url-template="{{ route('admin.backups.destroy', ['backup' => '__FILE__']) }}"
        data-database-name="{{ $adminBackupDatabaseName }}"
        data-can-create="{{ $adminCanBackupCreate ? '1' : '0' }}"
        data-can-download="{{ $adminCanBackupDownload ? '1' : '0' }}"
        data-can-delete="{{ $adminCanBackupDelete ? '1' : '0' }}"
        data-can-restore="{{ $adminCanBackupRestore ? '1' : '0' }}"
        data-table-colspan="{{ $backupTableColspan }}"
    >
        <div class="db-backup-head">
            <div>
                <h3 id="db-backup-title" class="db-backup-title">پشتیبان‌گیری و بازیابی</h3>
                <p class="db-backup-subtitle">مدیریت امن نسخه‌های پشتیبان پایگاه‌داده</p>
            </div>
            <button type="button" id="db-backup-close" class="db-backup-close" aria-label="بستن">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="db-backup-tabs-wrap">
            <div class="db-backup-tabs" role="tablist" aria-label="بخش‌های پشتیبان‌گیری">
                <button type="button" class="db-backup-tab is-active" role="tab" aria-selected="true" data-db-backup-tab="create">
                    بکاپ‌گیری
                </button>
                <button type="button" class="db-backup-tab" role="tab" aria-selected="false" data-db-backup-tab="restore">
                    بازگردانی بکاپ
                </button>
            </div>
        </div>

        <div class="db-backup-body">
            <section class="db-backup-panel is-active" data-db-backup-panel="create" role="tabpanel">
                @if($adminCanBackupCreate)
                    <p class="db-backup-lead">جهت تهیه بکاپ، دکمه ایجاد بکاپ را بزنید:</p>
                    <div class="db-backup-action-row">
                        <button type="button" class="db-backup-create-btn" id="db-backup-create-btn">
                            <i class="fa-solid fa-database" aria-hidden="true"></i>
                            ایجاد بکاپ
                        </button>
                        <span class="db-backup-status" id="db-backup-create-status" hidden></span>
                    </div>
                @else
                    <p class="db-backup-lead db-backup-lead--muted">شما مجوز ایجاد بکاپ ندارید.</p>
                @endif

                @if($adminCanBackupView || $adminCanBackupCreate)
                    <h4 class="db-backup-table-title">لیست بکاپ‌های موجود</h4>
                    <div class="db-backup-table-wrap">
                        <table class="db-backup-table">
                            <thead>
                                <tr>
                                    <th scope="col">نام فایل</th>
                                    <th scope="col">تاریخ ایجاد</th>
                                    <th scope="col">حجم فایل</th>
                                    @if($adminCanBackupDownload)
                                        <th scope="col">دانلود</th>
                                    @endif
                                    @if($adminCanBackupRestore)
                                        <th scope="col">بازگردانی</th>
                                    @endif
                                    @if($adminCanBackupDelete)
                                        <th scope="col">حذف</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="db-backup-table-body">
                                <tr class="db-backup-table-loading">
                                    <td colspan="{{ $backupTableColspan }}">در حال بارگذاری...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="db-backup-panel" data-db-backup-panel="restore" role="tabpanel" hidden>
                @if($adminCanBackupRestore)
                    <div class="db-backup-warning">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <p>
                            بازگردانی کل پایگاه‌داده را به وضعیت فایل بکاپ برمی‌گرداند.
                            تمام تغییراتی که <strong>بعد از تاریخ و ساعت همان بکاپ</strong> اعمال شده‌اند از بین می‌روند.
                        </p>
                    </div>

                    <div class="db-backup-restore-block">
                        <h4 class="db-backup-restore-heading">آپلود فایل بکاپ</h4>
                        <p class="db-backup-lead db-backup-lead--muted">فایل .sql یا .sqlite — حداکثر {{ (int) config('backup.max_upload_mb', 100) }} مگابایت</p>
                        <input type="file" id="db-backup-restore-file" class="db-backup-file-input" accept=".sql,.sqlite,.txt" />
                    </div>

                    <div class="db-backup-restore-block">
                        <h4 class="db-backup-restore-heading">یا انتخاب از لیست بکاپ‌های موجود</h4>
                        <select id="db-backup-restore-select" class="db-backup-restore-select">
                            <option value="">— انتخاب کنید —</option>
                        </select>
                    </div>

                    <div class="db-backup-restore-block">
                        <label class="db-backup-confirm-label" for="db-backup-restore-confirm">
                            برای تأیید، نام پایگاه‌داده را وارد کنید:
                            <code class="db-backup-db-name">{{ $adminBackupDatabaseName }}</code>
                        </label>
                        <input
                            type="text"
                            id="db-backup-restore-confirm"
                            class="db-backup-restore-confirm-input"
                            dir="ltr"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="{{ $adminBackupDatabaseName }}"
                        />
                    </div>

                    <div class="db-backup-action-row">
                        <button type="button" class="db-backup-restore-btn" id="db-backup-restore-submit">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            بازگردانی بکاپ
                        </button>
                        <span class="db-backup-status" id="db-backup-restore-status" hidden></span>
                    </div>
                @else
                    <p class="db-backup-lead db-backup-lead--muted">شما مجوز بازگردانی بکاپ ندارید.</p>
                @endif
            </section>
        </div>
    </div>
</div>
