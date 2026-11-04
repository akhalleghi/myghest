import '../css/customer-media.css';

function initializeCustomerMedia() {
    const editor = document.querySelector('[data-customer-media-editor]');
    if (!editor) return;
    const form = editor.closest('form');
    const gallery = document.getElementById('customer-media-gallery');
    const camera = document.getElementById('customer-camera-overlay');
    const viewer = document.getElementById('customer-media-viewer');
    const live = document.getElementById('customer-camera-live');
    const deviceSelect = document.getElementById('customer-camera-device');
    const photoButton = document.getElementById('customer-camera-photo');
    const recordButton = document.getElementById('customer-camera-record');
    const stopButton = document.getElementById('customer-camera-stop');
    const cameraStatus = document.getElementById('customer-camera-status');
    const cameraError = document.getElementById('customer-camera-error');
    const status = editor.querySelector('[data-media-status]');
    const slots = new Map([...editor.querySelectorAll('[data-media-slot]')].map(slot => [slot.dataset.mediaSlot, slot]));
    const saved = new Map();
    const objectUrls = new Map();
    let editorVersion = 0;
    let galleryVersion = 0;
    let cameraVersion = 0;
    let stream = null;
    let recorder = null;
    let recordingTimer = null;
    let recordingClock = null;
    let captureKind = null;
    let captureCancelled = true;
    let previousFocus = null;
    const mediaUrl = id => `${editor.dataset.baseUrl}/${encodeURIComponent(id)}/media`;
    const isVideo = kind => kind === 'video';
    const releaseUrls = () => { objectUrls.forEach(url => URL.revokeObjectURL(url)); objectUrls.clear(); };

    function showViewer(item, fullscreen = false) {
        previousFocus = document.activeElement;
        const content = document.getElementById('customer-media-viewer-content');
        content.replaceChildren();
        document.getElementById('customer-media-viewer-title').textContent = item.label;
        const media = document.createElement(item.type === 'video' ? 'video' : 'img');
        if (item.type === 'video') { media.controls = true; media.playsInline = true; media.preload = 'metadata'; }
        else media.alt = item.label;
        media.src = item.url;
        content.append(media);
        viewer.hidden = false;
        document.getElementById('customer-media-viewer-close').focus();
        if (fullscreen) enterFullscreen();
    }

    function enterFullscreen() {
        if (viewer.requestFullscreen && !document.fullscreenElement) viewer.requestFullscreen().catch(() => {});
    }

    function closeViewer() {
        if (viewer.hidden) return;
        if (document.fullscreenElement === viewer) document.exitFullscreen().catch(() => {});
        viewer.querySelectorAll('video').forEach(video => video.pause());
        document.getElementById('customer-media-viewer-content').replaceChildren();
        viewer.hidden = true;
        previousFocus?.focus();
    }

    function thumbnail(item, compact = false) {
        const wrap = document.createElement('div');
        if (compact) wrap.className = 'customer-media-thumb';
        const media = document.createElement(item.type === 'video' ? 'video' : 'img');
        if (item.type === 'video') { media.preload = 'metadata'; media.muted = true; media.playsInline = true; }
        else { media.alt = item.label; media.loading = 'lazy'; }
        media.src = item.url;
        media.addEventListener('dblclick', () => showViewer(item, true));
        media.addEventListener('click', () => { if (window.matchMedia('(pointer: coarse)').matches) showViewer(item); });
        const open = document.createElement('button');
        open.type = 'button';
        open.textContent = compact ? item.label : 'نمایش فایل';
        open.setAttribute('aria-label', `نمایش ${item.label}`);
        if (!compact) open.className = 'cust-mini-btn';
        open.addEventListener('click', () => showViewer(item));
        wrap.append(media, open);
        return wrap;
    }

    function renderSlot(kind) {
        const slot = slots.get(kind);
        const input = slot.querySelector('[data-media-input]');
        const preview = slot.querySelector('[data-media-preview]');
        const pending = input.files?.[0];
        let item = saved.get(kind);
        if (objectUrls.has(kind)) { URL.revokeObjectURL(objectUrls.get(kind)); objectUrls.delete(kind); }
        if (pending) {
            const url = URL.createObjectURL(pending);
            objectUrls.set(kind, url);
            item = { type: isVideo(kind) ? 'video' : 'image', label: slot.querySelector('.customer-media-label').textContent, url };
        }
        preview.replaceChildren();
        if (item) preview.append(thumbnail(item));
        else preview.textContent = 'فایلی ثبت نشده است.';
        slot.querySelector('[data-media-clear]').hidden = !pending;
        slot.querySelector('[data-media-remove-label]').hidden = !saved.has(kind);
        slot.classList.toggle('is-removing', slot.querySelector('[data-media-remove]').checked);
    }

    function selectFile(kind, file) {
        const slot = slots.get(kind);
        const input = slot.querySelector('[data-media-input]');
        const max = (isVideo(kind) ? 20 : 5) * 1024 * 1024;
        const allowed = isVideo(kind) ? /^(video\/(mp4|webm|quicktime))$/ : /^image\/(jpeg|png|webp)$/;
        if (file && (file.size > max || (file.type && !allowed.test(file.type.split(';')[0])))) {
            input.value = '';
            slot.querySelector('[data-media-error]').textContent = 'نوع یا حجم فایل مجاز نیست؛ محدودیت‌های درج‌شده بالای این بخش را بررسی کنید.';
            renderSlot(kind);
            return false;
        }
        slot.querySelector('[data-media-error]').textContent = '';
        if (file && input.files?.[0] !== file) {
            try { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; }
            catch { cameraError.textContent = 'انتقال عکس/فیلم به فرم در این مرورگر ممکن نیست؛ از انتخاب فایل استفاده کنید.'; return false; }
        }
        if (file) slot.querySelector('[data-media-remove]').checked = false;
        renderSlot(kind);
        return true;
    }

    async function readItems(id) {
        const response = await fetch(mediaUrl(id), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('دریافت مدارک انجام نشد؛ دسترسی و اتصال را بررسی کنید.');
        return response.json();
    }

    async function resetEditor() {
        const version = ++editorVersion;
        closeCamera(); closeViewer(); releaseUrls(); saved.clear();
        slots.forEach((slot, kind) => {
            slot.querySelector('[data-media-input]').value = '';
            slot.querySelector('[data-media-remove]').checked = false;
            slot.querySelector('[data-media-error]').textContent = '';
            renderSlot(kind);
        });
        status.textContent = '';
        const id = form.dataset.mediaCustomerId;
        if (!id || editor.dataset.available !== '1') return;
        status.textContent = 'در حال دریافت مدارک ثبت‌شده…';
        try {
            const data = await readItems(id);
            if (version !== editorVersion) return;
            data.items.forEach(item => { if (slots.has(item.kind)) saved.set(item.kind, item); });
            slots.forEach((_, kind) => renderSlot(kind));
            status.textContent = data.available ? 'انتخاب فایل جدید، جایگزین فایل همان بخش می‌شود. حذف فایل فعلی فقط با علامت‌زدن حذف و ذخیره انجام می‌شود.' : '';
        } catch (error) { if (version === editorVersion) status.textContent = error.message; }
    }

    async function loadGallery() {
        const version = ++galleryVersion;
        gallery.replaceChildren();
        const id = gallery.dataset.customerId;
        if (!id) { gallery.hidden = true; return; }
        if (editor.dataset.available !== '1') {
            gallery.hidden = false;
            gallery.textContent = 'نمایش مدارک پس از اجرای مهاجرت دیتابیس نسخه جدید فعال می‌شود.';
            return;
        }
        gallery.hidden = false;
        gallery.textContent = 'در حال دریافت تصاویر مشتری…';
        try {
            const data = await readItems(id);
            if (version !== galleryVersion) return;
            gallery.replaceChildren();
            if (!data.items.length) {
                gallery.textContent = 'برای این مشتری مدرکی ثبت نشده است. می‌توانید از ویرایش مشتری، تصاویر یا ویدئو اضافه کنید.';
                return;
            }
            const title = document.createElement('h3');
            title.textContent = 'تصاویر و مدارک مشتری — برای تمام‌صفحه دوبار کلیک کنید';
            const row = document.createElement('div'); row.className = 'customer-media-thumbnails';
            data.items.forEach(item => row.append(thumbnail(item, true)));
            gallery.append(title, row);
        } catch (error) {
            if (version !== galleryVersion) return;
            gallery.textContent = error.message;
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'cust-mini-btn'; retry.textContent = 'تلاش مجدد'; retry.onclick = loadGallery; gallery.append(retry);
        }
    }

    function stopTracks() { stream?.getTracks().forEach(track => track.stop()); stream = null; live.srcObject = null; }
    function clearRecordingTimers() { clearTimeout(recordingTimer); clearInterval(recordingClock); recordingTimer = recordingClock = null; }
    function closeCamera() {
        cameraVersion++; captureCancelled = true; clearRecordingTimers();
        if (recorder?.state === 'recording') recorder.stop();
        recorder = null; stopTracks();
        if (!camera.hidden) { camera.hidden = true; previousFocus?.focus(); }
    }

    async function startCamera(deviceId = '') {
        const version = ++cameraVersion;
        stopTracks(); cameraError.textContent = ''; photoButton.disabled = recordButton.disabled = true;
        cameraStatus.textContent = 'در حال راه‌اندازی دوربین…';
        try {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw new Error('ثبت با دوربین به HTTPS یا localhost و مرورگر دارای پشتیبانی دوربین نیاز دارد.');
            const acquired = await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 1280 }, height: { ideal: 720 }, ...(deviceId ? { deviceId: { exact: deviceId } } : {}) }, audio: false });
            if (version !== cameraVersion || camera.hidden) { acquired.getTracks().forEach(track => track.stop()); return; }
            stream = acquired; live.srcObject = stream; await live.play();
            if (version !== cameraVersion || camera.hidden) return;
            const devices = await navigator.mediaDevices.enumerateDevices();
            if (version !== cameraVersion || camera.hidden) return;
            const currentId = stream.getVideoTracks()[0]?.getSettings().deviceId;
            deviceSelect.replaceChildren();
            devices.filter(device => device.kind === 'videoinput').forEach((device, index) => {
                const option = document.createElement('option'); option.value = device.deviceId; option.textContent = device.label || `دوربین ${index + 1}`; option.selected = device.deviceId === currentId; deviceSelect.append(option);
            });
            photoButton.disabled = false;
            recordButton.disabled = typeof MediaRecorder === 'undefined';
            cameraStatus.textContent = isVideo(captureKind) ? 'آماده ضبط — حداکثر ۳۰ ثانیه، بدون صدا' : 'تصویر را تنظیم کنید و ثبت عکس را بزنید.';
            if (isVideo(captureKind) && recordButton.disabled) cameraError.textContent = 'این مرورگر ضبط ویدئو را پشتیبانی نمی‌کند؛ می‌توانید فایل ویدئو را انتخاب کنید.';
        } catch (error) {
            if (version !== cameraVersion) return;
            stopTracks();
            cameraStatus.textContent = '';
            const messages = { NotAllowedError: 'اجازه دوربین داده نشد؛ مجوز دوربین را در مرورگر بررسی کنید.', NotFoundError: 'دوربینی شناسایی نشد؛ اتصال وبکم را بررسی کنید.', NotReadableError: 'دوربین در دسترس نیست یا برنامه دیگری از آن استفاده می‌کند.' };
            cameraError.textContent = messages[error.name] || error.message || 'راه‌اندازی دوربین ممکن نشد.';
        }
    }

    function openCamera(kind) {
        closeCamera(); captureKind = kind; previousFocus = document.activeElement;
        camera.hidden = false; deviceSelect.disabled = false;
        photoButton.hidden = isVideo(kind); recordButton.hidden = !isVideo(kind); stopButton.hidden = true;
        document.getElementById('customer-camera-title').textContent = `ثبت ${slots.get(kind).querySelector('.customer-media-label').textContent}`;
        camera.querySelector('[data-camera-close]').focus(); startCamera();
    }

    photoButton.addEventListener('click', async () => {
        if (!stream || !live.videoWidth) return;
        const version = cameraVersion; const kind = captureKind;
        photoButton.disabled = true;
        try {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 1600 / live.videoWidth);
            canvas.width = Math.round(live.videoWidth * scale); canvas.height = Math.round(live.videoHeight * scale);
            canvas.getContext('2d').drawImage(live, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .9));
            if (version !== cameraVersion || camera.hidden) return;
            if (!blob) throw new Error('ثبت عکس انجام نشد؛ دوباره تلاش کنید.');
            if (selectFile(kind, new File([blob], `customer-${Date.now()}.jpg`, { type: 'image/jpeg' }))) closeCamera();
        } catch (error) { if (version === cameraVersion) cameraError.textContent = error.message; }
        finally { if (version === cameraVersion) photoButton.disabled = false; }
    });

    recordButton.addEventListener('click', () => {
        if (!stream || recorder?.state === 'recording' || typeof MediaRecorder === 'undefined') return;
        const candidates = ['video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
        const mime = candidates.find(type => MediaRecorder.isTypeSupported(type));
        const chunks = []; const kind = captureKind; const version = cameraVersion;
        let bytes = 0;
        try {
            captureCancelled = false;
            const recording = new MediaRecorder(stream, { videoBitsPerSecond: 1500000, ...(mime ? { mimeType: mime } : {}) });
            recorder = recording;
            recording.ondataavailable = event => { if (event.data.size) { chunks.push(event.data); bytes += event.data.size; if (bytes > 20 * 1024 * 1024 && recording.state === 'recording') recording.stop(); } };
            recording.onstop = () => {
                if (version !== cameraVersion) return;
                clearRecordingTimers();
                if (captureCancelled || camera.hidden) return;
                const type = recording.mimeType.split(';')[0] || 'video/webm';
                const file = new File(chunks, `customer-${Date.now()}.${type === 'video/mp4' ? 'mp4' : 'webm'}`, { type });
                if (file.size && selectFile(kind, file)) closeCamera();
                else { cameraError.textContent = 'فیلم ثبت نشد یا حجم آن بیش از ۲۰ مگابایت است؛ دوباره ضبط کنید.'; recordButton.hidden = false; stopButton.hidden = true; deviceSelect.disabled = false; }
            };
            recording.onerror = () => { if (version === cameraVersion) { closeCamera(); status.textContent = 'ضبط فیلم ناموفق بود؛ دوباره تلاش کنید.'; } };
            recording.start(250);
            recordButton.hidden = true; stopButton.hidden = false; deviceSelect.disabled = true;
            const started = Date.now(); cameraStatus.textContent = 'در حال ضبط: ۰ / ۳۰ ثانیه';
            recordingClock = setInterval(() => { cameraStatus.textContent = `در حال ضبط: ${Math.min(30, Math.floor((Date.now() - started) / 1000))} / ۳۰ ثانیه`; }, 500);
            recordingTimer = setTimeout(() => { if (recording.state === 'recording') recording.stop(); }, 30000);
        } catch (error) { clearRecordingTimers(); cameraError.textContent = error.message || 'شروع ضبط ممکن نشد.'; }
    });

    stopButton.addEventListener('click', () => { if (recorder?.state === 'recording') recorder.stop(); });
    deviceSelect.addEventListener('change', () => startCamera(deviceSelect.value));
    camera.querySelectorAll('[data-camera-close]').forEach(button => button.addEventListener('click', closeCamera));
    document.getElementById('customer-media-fullscreen').addEventListener('click', enterFullscreen);
    document.getElementById('customer-media-viewer-close').addEventListener('click', closeViewer);
    viewer.addEventListener('dblclick', event => { if (event.target.matches('img,video')) enterFullscreen(); });
    for (const [kind, slot] of slots) {
        slot.querySelector('[data-media-input]').addEventListener('change', event => selectFile(kind, event.target.files?.[0]));
        slot.querySelector('[data-media-capture]').addEventListener('click', () => openCamera(kind));
        slot.querySelector('[data-media-clear]').addEventListener('click', () => { slot.querySelector('[data-media-input]').value = ''; renderSlot(kind); });
        slot.querySelector('[data-media-remove]').addEventListener('change', event => { if (event.target.checked) slot.querySelector('[data-media-input]').value = ''; renderSlot(kind); });
    }
    form.addEventListener('customer-media:reset', resetEditor);
    form.addEventListener('customer-media:stop', () => { closeCamera(); closeViewer(); });
    gallery?.addEventListener('customer-media:load', loadGallery);
    document.addEventListener('keydown', event => {
        const active = !viewer.hidden ? viewer : !camera.hidden ? camera : null;
        if (!active) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); if (active === viewer) closeViewer(); else closeCamera(); }
        if (event.key === 'Tab') {
            const buttons = [...active.querySelectorAll('button:not([disabled]),select:not([disabled]),video[controls]')].filter(node => !node.hidden && node.getClientRects().length);
            const first = buttons[0], last = buttons[buttons.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    }, true);
    document.addEventListener('visibilitychange', () => { if (document.hidden && !camera.hidden) closeCamera(); });
    window.addEventListener('pagehide', () => { closeCamera(); closeViewer(); releaseUrls(); });
    resetEditor();
    if (gallery?.dataset.customerId && !gallery.closest('[data-loan-panel]')?.hidden) loadGallery();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeCustomerMedia, { once: true });
else initializeCustomerMedia();
