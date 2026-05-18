/**
 * مدیریت پیش‌زمینهٔ صفحات لاگین در تنظیمات برنامه.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setMsg(root, text, type) {
        var el = root.querySelector('[data-login-bg-msg]');
        if (!el) return;
        if (!text) {
            el.hidden = true;
            el.textContent = '';
            el.classList.remove('is-ok', 'is-err');
            return;
        }
        el.hidden = false;
        el.textContent = text;
        el.classList.toggle('is-ok', type === 'ok');
        el.classList.toggle('is-err', type === 'err');
    }

    function applySelection(root, mode, selectedPath) {
        root.dataset.mode = mode;
        root.dataset.selected = selectedPath || '';

        root.querySelectorAll('[data-login-bg-select]').forEach(function (btn) {
            var btnMode = btn.getAttribute('data-mode') || 'fixed';
            var path = btn.getAttribute('data-path') || '';
            var active = btnMode === 'random'
                ? mode === 'random'
                : mode === 'fixed' && path === selectedPath;
            btn.classList.toggle('is-selected', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        updatePreview(root, mode, selectedPath);
    }

    function escapePath(path) {
        if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
            return CSS.escape(path);
        }
        return String(path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function findImageUrl(root, path) {
        if (!path) return null;
        var btn = root.querySelector('[data-login-bg-select][data-path="' + escapePath(path) + '"]');
        if (!btn) return null;
        var style = btn.getAttribute('style') || '';
        var match = style.match(/url\(['"]?([^'")]+)['"]?\)/);
        return match ? match[1] : null;
    }

    function updatePreview(root, mode, selectedPath) {
        var preview = root.querySelector('[data-login-bg-preview]');
        if (!preview) return;

        var randomEl = preview.querySelector('[data-login-bg-preview-random]');
        var fixedEl = preview.querySelector('[data-login-bg-preview-fixed]');
        var img = preview.querySelector('[data-login-bg-preview-img]');

        if (mode === 'random') {
            if (fixedEl) fixedEl.hidden = true;
            if (randomEl) randomEl.hidden = false;
            return;
        }

        if (randomEl) randomEl.hidden = true;
        if (fixedEl) fixedEl.hidden = false;

        var url = findImageUrl(root, selectedPath);
        if (img && url) {
            img.src = url;
            img.hidden = false;
            if (fixedEl) fixedEl.hidden = false;
        } else if (fixedEl) {
            fixedEl.hidden = true;
        }
    }

    function renderCustomGrid(root, customImages, mode, selectedPath) {
        var section = root.querySelector('[data-login-bg-custom-section]');
        var grid = root.querySelector('[data-login-bg-custom-grid]');
        var empty = root.querySelector('[data-login-bg-custom-empty]');
        if (!grid) return;

        grid.innerHTML = '';
        var hasCustom = customImages && customImages.length > 0;

        if (section) section.hidden = !hasCustom;
        if (empty) empty.hidden = hasCustom;

        if (!hasCustom) return;

        customImages.forEach(function (image) {
            var wrap = document.createElement('div');
            wrap.className = 'app-login-bg-tile-wrap';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'app-login-bg-tile';
            btn.setAttribute('data-login-bg-select', '');
            btn.setAttribute('data-mode', 'fixed');
            btn.setAttribute('data-path', image.id);
            btn.setAttribute('title', 'انتخاب این تصویر');
            btn.style.backgroundImage = "url('" + image.url + "')";
            if (mode === 'fixed' && image.id === selectedPath) {
                btn.classList.add('is-selected');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                btn.setAttribute('aria-pressed', 'false');
            }

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'app-login-bg-delete';
            del.setAttribute('data-login-bg-delete', '');
            del.setAttribute('data-path', image.id);
            del.setAttribute('aria-label', 'حذف تصویر');
            del.setAttribute('title', 'حذف');
            del.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

            wrap.appendChild(btn);
            wrap.appendChild(del);
            grid.appendChild(wrap);
        });
    }

    function syncFromState(root, state) {
        if (!state) return;
        var mode = state.mode === 'random' ? 'random' : 'fixed';
        var selected = state.selected || '';
        renderCustomGrid(root, state.custom || [], mode, selected);
        applySelection(root, mode, selected);
    }

    function jsonFetch(url, options) {
        return fetch(url, options).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        });
    }

    function savePreference(root) {
        var url = root.getAttribute('data-preference-url');
        if (!url) return Promise.resolve();

        var mode = root.dataset.mode || 'fixed';
        var selected = root.dataset.selected || '';
        var saveBtn = root.querySelector('[data-login-bg-save]');
        if (saveBtn) saveBtn.disabled = true;
        setMsg(root, 'در حال ذخیره…', '');

        return jsonFetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                mode: mode,
                selected: mode === 'fixed' ? selected : null,
            }),
        })
            .then(function (r) {
                if (r.ok) {
                    syncFromState(root, r.data.state);
                    setMsg(root, (r.data && r.data.message) || 'ذخیره شد.', 'ok');
                    return;
                }
                setMsg(root, (r.data && r.data.message) || 'ذخیره ممکن نشد.', 'err');
            })
            .catch(function () {
                setMsg(root, 'ارتباط با سرور برقرار نشد.', 'err');
            })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
            });
    }

    function uploadFile(root, file) {
        var url = root.getAttribute('data-upload-url');
        if (!url || !file) return;

        var fd = new FormData();
        fd.append('image', file);
        setMsg(root, 'در حال بارگذاری…', '');

        return jsonFetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: fd,
        })
            .then(function (r) {
                if (!r.ok) {
                    setMsg(root, (r.data && r.data.message) || 'بارگذاری ناموفق بود.', 'err');
                    return;
                }
                syncFromState(root, r.data.state);
                if (r.data.uploaded && r.data.uploaded.id) {
                    applySelection(root, 'fixed', r.data.uploaded.id);
                }
                setMsg(root, (r.data && r.data.message) || 'بارگذاری شد.', 'ok');
            })
            .catch(function () {
                setMsg(root, 'ارتباط با سرور برقرار نشد.', 'err');
            });
    }

    function deleteImage(root, path) {
        var url = root.getAttribute('data-delete-url');
        if (!url || !path) return;

        if (!window.confirm('این تصویر حذف شود؟')) return;

        setMsg(root, 'در حال حذف…', '');

        jsonFetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ path: path }),
        })
            .then(function (r) {
                if (!r.ok) {
                    setMsg(root, (r.data && r.data.message) || 'حذف ممکن نشد.', 'err');
                    return;
                }
                syncFromState(root, r.data.state);
                var mode = root.dataset.mode || 'fixed';
                var selected = root.dataset.selected || '';
                if (mode === 'fixed' && selected === path) {
                    applySelection(root, 'fixed', '');
                }
                setMsg(root, (r.data && r.data.message) || 'حذف شد.', 'ok');
            })
            .catch(function () {
                setMsg(root, 'ارتباط با سرور برقرار نشد.', 'err');
            });
    }

    function initPicker(root) {
        root.addEventListener('click', function (ev) {
            var selectBtn = ev.target.closest('[data-login-bg-select]');
            if (selectBtn) {
                var mode = selectBtn.getAttribute('data-mode') || 'fixed';
                var path = selectBtn.getAttribute('data-path') || '';
                applySelection(root, mode, path);
                return;
            }

            var deleteBtn = ev.target.closest('[data-login-bg-delete]');
            if (deleteBtn) {
                deleteImage(root, deleteBtn.getAttribute('data-path') || '');
                return;
            }

            var saveBtn = ev.target.closest('[data-login-bg-save]');
            if (saveBtn) {
                savePreference(root);
                return;
            }

            var expandBtn = ev.target.closest('[data-login-bg-toggle-expand]');
            if (expandBtn) {
                var grid = root.querySelector('[data-login-bg-grid]');
                if (!grid) return;
                var collapsed = grid.classList.toggle('is-collapsed');
                var label = expandBtn.querySelector('[data-login-bg-toggle-label]');
                if (label) {
                    label.textContent = collapsed ? 'نمایش همه' : 'نمایش کمتر';
                }
                var icon = expandBtn.querySelector('.fa-chevron-down, .fa-chevron-up');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down', collapsed);
                    icon.classList.toggle('fa-chevron-up', !collapsed);
                }
            }
        });

        var fileInput = root.querySelector('[data-login-bg-file]');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                fileInput.value = '';
                if (file) uploadFile(root, file);
            });
        }

        var uploadLabel = root.querySelector('.app-login-bg-upload');
        if (uploadLabel) {
            ['dragenter', 'dragover'].forEach(function (evt) {
                uploadLabel.addEventListener(evt, function (e) {
                    e.preventDefault();
                    uploadLabel.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                uploadLabel.addEventListener(evt, function (e) {
                    e.preventDefault();
                    uploadLabel.classList.remove('is-dragover');
                });
            });
            uploadLabel.addEventListener('drop', function (e) {
                var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (file) uploadFile(root, file);
            });
        }
    }

    document.querySelectorAll('[data-login-bg-picker]').forEach(initPicker);
})();
