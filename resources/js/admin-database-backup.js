/**
 * مودال پشتیبان‌گیری و بازیابی پایگاه‌داده در پنل ادمین.
 */
(function () {
    var overlay = document.getElementById('db-backup-overlay');
    var modal = document.getElementById('db-backup-modal');
    var openBtn = document.getElementById('db-backup-open');
    var closeBtn = document.getElementById('db-backup-close');
    if (!overlay || !modal) {
        return;
    }

    var listUrl = modal.getAttribute('data-list-url') || '';
    var storeUrl = modal.getAttribute('data-store-url') || '';
    var restoreUrl = modal.getAttribute('data-restore-url') || '';
    var downloadTemplate = modal.getAttribute('data-download-url-template') || '';
    var deleteTemplate = modal.getAttribute('data-delete-url-template') || '';
    var databaseName = modal.getAttribute('data-database-name') || '';
    var canCreate = modal.getAttribute('data-can-create') === '1';
    var canDownload = modal.getAttribute('data-can-download') === '1';
    var canDelete = modal.getAttribute('data-can-delete') === '1';
    var canRestore = modal.getAttribute('data-can-restore') === '1';
    var tableColspan = parseInt(modal.getAttribute('data-table-colspan') || '5', 10);

    var tableBody = document.getElementById('db-backup-table-body');
    var createBtn = document.getElementById('db-backup-create-btn');
    var createStatus = document.getElementById('db-backup-create-status');
    var restoreFileInput = document.getElementById('db-backup-restore-file');
    var restoreSelect = document.getElementById('db-backup-restore-select');
    var restoreConfirmInput = document.getElementById('db-backup-restore-confirm');
    var restoreSubmitBtn = document.getElementById('db-backup-restore-submit');
    var restoreStatus = document.getElementById('db-backup-restore-status');
    var tabButtons = Array.from(modal.querySelectorAll('[data-db-backup-tab]'));
    var tabPanels = Array.from(modal.querySelectorAll('[data-db-backup-panel]'));

    var cachedBackups = [];
    var isRestoring = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openModal() {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('db-backup-open');
        activateTab('create');
        if (listUrl) {
            loadBackups();
        }
    }

    function closeModal() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('db-backup-open');
    }

    function activateTab(tabId) {
        tabButtons.forEach(function (btn) {
            var active = btn.getAttribute('data-db-backup-tab') === tabId;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        tabPanels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-db-backup-panel') === tabId;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });
        if (tabId === 'restore') {
            populateRestoreSelect(cachedBackups);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function populateRestoreSelect(backups) {
        if (!restoreSelect) {
            return;
        }
        var options = '<option value="">— انتخاب کنید —</option>';
        if (Array.isArray(backups)) {
            backups.forEach(function (row) {
                if (!row.filename) {
                    return;
                }
                var label = (row.created_at || '') + ' — ' + row.filename;
                options +=
                    '<option value="' +
                    escapeHtml(row.filename) +
                    '">' +
                    escapeHtml(label) +
                    '</option>';
            });
        }
        restoreSelect.innerHTML = options;
    }

    function renderBackups(backups) {
        cachedBackups = Array.isArray(backups) ? backups : [];
        populateRestoreSelect(cachedBackups);

        if (!tableBody) {
            return;
        }
        if (cachedBackups.length === 0) {
            tableBody.innerHTML =
                '<tr><td colspan="' + tableColspan + '" class="db-backup-empty">هنوز بکاپی ثبت نشده است.</td></tr>';
            return;
        }

        tableBody.innerHTML = cachedBackups
            .map(function (row) {
                var rawFilename = row.filename || '';
                var filename = escapeHtml(rawFilename);
                var createdAt = escapeHtml(row.created_at || '—');
                var sizeLabel = escapeHtml(row.size_label || '—');

                var downloadCell = '';
                if (canDownload && rawFilename) {
                    var href = downloadTemplate.replace('__FILE__', encodeURIComponent(rawFilename));
                    downloadCell =
                        '<td><a class="db-backup-download-link" href="' +
                        escapeHtml(href) +
                        '" download><i class="fa-solid fa-download" aria-hidden="true"></i><span>دانلود</span></a></td>';
                }

                var restoreCell = '';
                if (canRestore && rawFilename) {
                    restoreCell =
                        '<td><button type="button" class="db-backup-restore-row-btn" data-filename="' +
                        filename +
                        '" data-created-at="' +
                        createdAt +
                        '"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>بازگردانی</span></button></td>';
                }

                var deleteCell = '';
                if (canDelete && rawFilename) {
                    deleteCell =
                        '<td><button type="button" class="db-backup-delete-btn" data-filename="' +
                        filename +
                        '"><i class="fa-solid fa-trash-can" aria-hidden="true"></i><span>حذف</span></button></td>';
                }

                return (
                    '<tr>' +
                    '<td class="db-backup-filename" title="' +
                    filename +
                    '">' +
                    filename +
                    '</td>' +
                    '<td>' +
                    createdAt +
                    '</td>' +
                    '<td>' +
                    sizeLabel +
                    '</td>' +
                    downloadCell +
                    restoreCell +
                    deleteCell +
                    '</tr>'
                );
            })
            .join('');
    }

    function loadBackups() {
        if (!listUrl) {
            return;
        }
        if (tableBody) {
            tableBody.innerHTML =
                '<tr class="db-backup-table-loading"><td colspan="' + tableColspan + '">در حال بارگذاری...</td></tr>';
        }
        fetch(listUrl, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        throw new Error((data && data.message) || 'بارگذاری لیست بکاپ‌ها ناموفق بود.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                renderBackups(data.backups || []);
            })
            .catch(function (err) {
                if (tableBody) {
                    tableBody.innerHTML =
                        '<tr><td colspan="' +
                        tableColspan +
                        '" class="db-backup-error">' +
                        escapeHtml(err.message || 'خطا در بارگذاری') +
                        '</td></tr>';
                }
            });
    }

    function setStatus(el, text, type) {
        if (!el) {
            return;
        }
        if (!text) {
            el.hidden = true;
            el.textContent = '';
            el.className = 'db-backup-status';
            return;
        }
        el.hidden = false;
        el.textContent = text;
        el.className = 'db-backup-status db-backup-status--' + (type || 'info');
    }

    function createBackup() {
        if (!canCreate || !storeUrl || !createBtn) {
            return;
        }
        createBtn.disabled = true;
        setStatus(createStatus, 'در حال ایجاد بکاپ...', 'info');
        fetch(storeUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        throw new Error((data && data.message) || 'ایجاد بکاپ ناموفق بود.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                setStatus(createStatus, data.message || 'بکاپ با موفقیت ایجاد شد.', 'success');
                renderBackups(data.backups || []);
                if (window.AdminSwal && typeof AdminSwal.success === 'function') {
                    AdminSwal.success(data.message || 'بکاپ ایجاد شد.');
                }
            })
            .catch(function (err) {
                setStatus(createStatus, err.message || 'خطا در ایجاد بکاپ', 'error');
                if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                    AdminSwal.error(err.message || 'ایجاد بکاپ ناموفق بود.');
                }
            })
            .finally(function () {
                createBtn.disabled = false;
            });
    }

    function deleteBackup(filename, triggerBtn) {
        if (!canDelete || !deleteTemplate || !filename) {
            return;
        }
        var url = deleteTemplate.replace('__FILE__', encodeURIComponent(filename));
        if (triggerBtn) {
            triggerBtn.disabled = true;
        }
        fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        throw new Error((data && data.message) || 'حذف بکاپ ناموفق بود.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                renderBackups(data.backups || []);
                if (window.AdminSwal && typeof AdminSwal.success === 'function') {
                    AdminSwal.success(data.message || 'بکاپ حذف شد.');
                }
            })
            .catch(function (err) {
                if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                    AdminSwal.error(err.message || 'حذف بکاپ ناموفق بود.');
                }
            })
            .finally(function () {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
            });
    }

    function confirmDeleteBackup(filename, triggerBtn) {
        var prompt = 'فایل «' + filename + '» برای همیشه حذف شود؟ این عمل قابل بازگشت نیست.';
        if (window.AdminSwal && typeof AdminSwal.confirm === 'function') {
            AdminSwal.confirm({
                title: 'حذف بکاپ',
                text: prompt,
                confirmButtonText: 'بله، حذف شود',
                cancelButtonText: 'انصراف',
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    deleteBackup(filename, triggerBtn);
                }
            });
            return;
        }
        if (window.confirm(prompt)) {
            deleteBackup(filename, triggerBtn);
        }
    }

    function restoreConfirmText(createdAtLabel) {
        var when = createdAtLabel ? ' (' + createdAtLabel + ')' : '';
        return (
            'مطمئن هستید؟ با بازگردانی این بکاپ' +
            when +
            '، تمام داده‌هایی که بعد از این تاریخ و ساعت در پایگاه‌داده اعمال شده‌اند حذف می‌شوند و سامانه به همان وضعیت بکاپ برمی‌گردد.'
        );
    }

    function runRestore(options) {
        if (!canRestore || !restoreUrl) {
            return;
        }
        if (isRestoring) {
            return;
        }

        var filename = options.filename || '';
        var file = options.file || null;
        var confirmValue = options.confirmValue || '';
        var triggerBtn = options.triggerBtn || null;
        var statusEl = options.statusEl || restoreStatus;

        if (confirmValue !== databaseName) {
            if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                AdminSwal.error('نام پایگاه‌داده برای تأیید درست وارد نشده است.');
            }
            return;
        }

        if (!file && !filename) {
            if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                AdminSwal.error('فایل بکاپ را انتخاب کنید یا از لیست انتخاب نمایید.');
            }
            return;
        }

        if (file && filename) {
            if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                AdminSwal.error('فقط یکی از آپلود فایل یا انتخاب از لیست را استفاده کنید.');
            }
            return;
        }

        var formData = new FormData();
        formData.append('confirm_database', confirmValue);
        if (file) {
            formData.append('file', file);
        } else {
            formData.append('filename', filename);
        }

        isRestoring = true;
        if (triggerBtn) {
            triggerBtn.disabled = true;
        }
        if (restoreSubmitBtn) {
            restoreSubmitBtn.disabled = true;
        }
        setStatus(statusEl, 'در حال بازگردانی... این کار ممکن است چند دقیقه طول بکشد.', 'info');

        fetch(restoreUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        var msg = (data && data.message) || 'بازگردانی بکاپ ناموفق بود.';
                        if (data && data.errors) {
                            var firstKey = Object.keys(data.errors)[0];
                            if (firstKey && data.errors[firstKey][0]) {
                                msg = data.errors[firstKey][0];
                            }
                        }
                        throw new Error(msg);
                    }
                    return data;
                });
            })
            .then(function (data) {
                setStatus(statusEl, data.message || 'بازگردانی انجام شد.', 'success');
                if (restoreFileInput) {
                    restoreFileInput.value = '';
                }
                if (restoreSelect) {
                    restoreSelect.value = '';
                }
                if (restoreConfirmInput) {
                    restoreConfirmInput.value = '';
                }
                renderBackups(data.backups || []);
                if (window.AdminSwal && typeof AdminSwal.success === 'function') {
                    AdminSwal.success(data.message || 'بکاپ بازگردانی شد.');
                }
            })
            .catch(function (err) {
                setStatus(statusEl, err.message || 'خطا در بازگردانی', 'error');
                if (window.AdminSwal && typeof AdminSwal.error === 'function') {
                    AdminSwal.error(err.message || 'بازگردانی بکاپ ناموفق بود.');
                }
            })
            .finally(function () {
                isRestoring = false;
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
                if (restoreSubmitBtn) {
                    restoreSubmitBtn.disabled = false;
                }
            });
    }

    function promptRestoreConfirm(createdAtLabel, onConfirmed) {
        var text = restoreConfirmText(createdAtLabel);
        if (window.AdminSwal && typeof AdminSwal.confirm === 'function') {
            AdminSwal.confirm({
                title: 'بازگردانی بکاپ',
                text: text,
                confirmButtonText: 'بله، بازگردانی شود',
                cancelButtonText: 'انصراف',
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    onConfirmed();
                }
            });
            return;
        }
        if (window.confirm(text)) {
            onConfirmed();
        }
    }

    function promptDatabaseNameThenRestore(createdAtLabel, buildPayload) {
        promptRestoreConfirm(createdAtLabel, function () {
            if (!databaseName) {
                runRestore(buildPayload(databaseName));
                return;
            }
            if (window.AdminSwal && typeof AdminSwal.fire === 'function') {
                AdminSwal.fire({
                    title: 'تأیید نهایی',
                    html:
                        'نام پایگاه‌داده را وارد کنید: <code dir="ltr" style="font-size:0.85em">' +
                        escapeHtml(databaseName) +
                        '</code>',
                    input: 'text',
                    inputAttributes: { dir: 'ltr', autocapitalize: 'off', autocomplete: 'off' },
                    showCancelButton: true,
                    confirmButtonText: 'بازگردانی',
                    cancelButtonText: 'انصراف',
                }).then(function (result) {
                    if (result && result.isConfirmed) {
                        runRestore(buildPayload(result.value || ''));
                    }
                });
                return;
            }
            var typed = window.prompt('نام پایگاه‌داده را وارد کنید: ' + databaseName);
            if (typed !== null) {
                runRestore(buildPayload(typed));
            }
        });
    }

    function restoreFromRow(filename, createdAtLabel, triggerBtn) {
        promptDatabaseNameThenRestore(createdAtLabel, function (confirmValue) {
            return {
                filename: filename,
                file: null,
                confirmValue: confirmValue,
                triggerBtn: triggerBtn,
                statusEl: restoreStatus,
            };
        });
    }

    function restoreFromTabForm() {
        var file = restoreFileInput && restoreFileInput.files && restoreFileInput.files[0] ? restoreFileInput.files[0] : null;
        var filename = restoreSelect ? restoreSelect.value : '';
        var confirmValue = restoreConfirmInput ? restoreConfirmInput.value.trim() : '';
        var selectedLabel = '';
        if (restoreSelect && restoreSelect.selectedIndex > 0) {
            selectedLabel = restoreSelect.options[restoreSelect.selectedIndex].text;
        }

        promptDatabaseNameThenRestore(selectedLabel, function (typedConfirm) {
            return {
                filename: file ? '' : filename,
                file: file,
                confirmValue: typedConfirm || confirmValue,
                triggerBtn: restoreSubmitBtn,
                statusEl: restoreStatus,
            };
        });
    }

    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            closeModal();
        }
    });

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.getAttribute('data-db-backup-tab'));
        });
    });

    if (tableBody) {
        tableBody.addEventListener('click', function (event) {
            var deleteBtnEl = event.target.closest('.db-backup-delete-btn');
            if (deleteBtnEl) {
                var deleteName = deleteBtnEl.getAttribute('data-filename');
                if (deleteName) {
                    confirmDeleteBackup(deleteName, deleteBtnEl);
                }
                return;
            }
            var restoreBtnEl = event.target.closest('.db-backup-restore-row-btn');
            if (restoreBtnEl && canRestore) {
                var restoreName = restoreBtnEl.getAttribute('data-filename');
                var restoreWhen = restoreBtnEl.getAttribute('data-created-at') || '';
                if (restoreName) {
                    restoreFromRow(restoreName, restoreWhen, restoreBtnEl);
                }
            }
        });
    }

    if (createBtn) {
        createBtn.addEventListener('click', function () {
            if (window.AdminSwal && typeof AdminSwal.confirm === 'function') {
                AdminSwal.confirm({
                    title: 'ایجاد بکاپ',
                    text: 'از ایجاد نسخه پشتیبان جدید اطمینان دارید؟',
                    confirmButtonText: 'بله، ایجاد شود',
                    cancelButtonText: 'انصراف',
                }).then(function (result) {
                    if (result && result.isConfirmed) {
                        createBackup();
                    }
                });
                return;
            }
            createBackup();
        });
    }

    if (restoreSubmitBtn) {
        restoreSubmitBtn.addEventListener('click', restoreFromTabForm);
    }

    if (restoreFileInput && restoreSelect) {
        restoreFileInput.addEventListener('change', function () {
            if (restoreFileInput.files && restoreFileInput.files.length > 0) {
                restoreSelect.value = '';
            }
        });
        restoreSelect.addEventListener('change', function () {
            if (restoreSelect.value) {
                restoreFileInput.value = '';
            }
        });
    }
})();
