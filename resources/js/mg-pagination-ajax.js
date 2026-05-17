/**
 * رویدادهای صفحه‌بندی تزریق‌شده از سرور (لینک‌ها و انتخاب per_page) برای لیست‌های AJAX.
 *
 * @param {HTMLElement|null} pagWrap
 * @param {{ onPage: (page: number) => void, onPerPage: (perPage: number) => void }} handlers
 */
export function bindAjaxPagination(pagWrap, handlers) {
    if (!pagWrap || !handlers) {
        return;
    }

    pagWrap.querySelectorAll('a[href]').forEach(function (link) {
        if (link.dataset.mgPagBound === '1') {
            return;
        }
        link.dataset.mgPagBound = '1';
        link.addEventListener('click', function (e) {
            e.preventDefault();
            try {
                const u = new URL(link.href, window.location.origin);
                const page = Number(u.searchParams.get('page')) || 1;
                handlers.onPage(page);
            } catch {
                /* noop */
            }
        });
    });

    pagWrap.querySelectorAll('.mg-per-page-form select[name="per_page"]').forEach(function (sel) {
        if (sel.dataset.mgPagBound === '1') {
            return;
        }
        sel.dataset.mgPagBound = '1';
        sel.addEventListener('change', function (e) {
            e.preventDefault();
            const per = parseInt(String(sel.value), 10);
            handlers.onPerPage(Number.isFinite(per) ? per : 15);
        });
    });
}
