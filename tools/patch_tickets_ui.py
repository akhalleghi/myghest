# -*- coding: utf-8 -*-
from pathlib import Path

# Admin blade patches
p = Path("resources/views/admin/tickets/index.blade.php")
s = p.read_text(encoding="utf-8")

s = s.replace(
    '<form method="post" action="{{ route(\'admin.tickets.store\') }}" enctype="multipart/form-data" id="tk-compose-form" novalidate>\n                @csrf\n                <motion.div class="tk-dialog-scroll">',
    '<form method="post" action="{{ route(\'admin.tickets.store\') }}" enctype="multipart/form-data" id="tk-compose-form" novalidate>\n                @csrf\n                <div class="tk-dialog-scroll" id="tk-compose-scroll">',
)
s = s.replace(
    '<form method="post" action="{{ route(\'admin.tickets.store\') }}" enctype="multipart/form-data" id="tk-compose-form" novalidate>\n                @csrf\n                <div class="tk-dialog-scroll">',
    '<form method="post" action="{{ route(\'admin.tickets.store\') }}" enctype="multipart/form-data" id="tk-compose-form" novalidate>\n                @csrf\n                <div class="tk-dialog-scroll" id="tk-compose-scroll">',
)

old_detail = """    <dialog id="tk-detail-dialog" aria-labelledby="tk-detail-title">
        <div class="tk-dialog-inner">
            <button type="button" class="tk-dialog-close" data-tk-close-detail aria-label="بستن">&times;</button>
            <div class="tk-dialog-head">
                <h2 id="tk-detail-title" class="tk-dialog-title">جزئیات تیکت</h2>
            </div>
            <div class="tk-dialog-scroll" id="tk-detail-body">
                <p class="tk-all-hint">در حال بارگذاری…</p>
            </div>
            <div class="tk-detail-reply" id="tk-detail-reply-wrap" hidden>"""

new_detail = """    <dialog id="tk-detail-dialog" aria-labelledby="tk-detail-title">
        <div class="tk-dialog-inner tk-dialog-inner--detail">
            <button type="button" class="tk-dialog-close" data-tk-close-detail aria-label="بستن">&times;</button>
            <motion.div class="tk-dialog-head">
                <h2 id="tk-detail-title" class="tk-dialog-title">جزئیات تیکت</h2>
            </motion.div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="tk-detail-body">
                    <p class="tk-all-hint">در حال بارگذاری…</p>
                </motion.div>
                <div class="st-detail-reply-zone tk-detail-reply" id="tk-detail-reply-wrap" hidden>"""

new_detail = new_detail.replace("<motion.div", "<div").replace("</motion.div>", "</div>")

if old_detail not in s:
    raise SystemExit("admin detail block not found")
s = s.replace(old_detail, new_detail, 1)

s = s.replace(
    """                </form>
            </div>
            <div class="tk-dialog-footer">
                <button type="button" class="tk-btn tk-btn--ghost" data-tk-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection""",
    """                </form>
                </div>
            </div>
            <div class="tk-dialog-footer">
                <button type="button" class="tk-btn tk-btn--ghost" data-tk-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection""",
    1,
)

s = s.replace(
    "@vite(['resources/js/admin-tickets-ckeditor.js'])",
    "@vite(['resources/js/support-ticket-ui.js', 'resources/js/admin-tickets-ckeditor.js'])",
)

p.write_text(s, encoding="utf-8")
print("admin html ok")
