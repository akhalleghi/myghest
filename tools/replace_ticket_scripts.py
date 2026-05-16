# -*- coding: utf-8 -*-
from pathlib import Path

admin_scripts = """@push('scripts')
    <script>
        window.__TK_PAGE__ = {
            snapshots: @json($rowSnapshots),
            customerSearchUrl: @json(route('admin.tickets.customers-search')),
            composeStoreUrl: @json(route('admin.tickets.store')),
            ticketsAdminBase: @json(url('admin/tickets')),
            csrf: @json(csrf_token()),
            flashSuccess: @json(session('ticket_flash_success')),
        };
    </script>
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
    @vite(['resources/js/support-ticket-ui.js', 'resources/js/admin-tickets-ckeditor.js', 'resources/js/admin-tickets-index.js'])
@endpush"""

user_scripts = """@push('scripts')
    <script>
        window.__UT_PAGE__ = {
            listUrl: @json(route('user.tickets.list')),
            storeUrl: @json(route('user.tickets.store')),
            ticketsBase: @json(url('user/tickets')),
            csrf: @json(csrf_token()),
        };
    </script>
    @vite(['resources/js/support-ticket-ui.js', 'resources/js/admin-tickets-ckeditor.js', 'resources/js/user-tickets-portal.js'])
@endpush"""

for rel, new_block in [
    ('resources/views/admin/tickets/index.blade.php', admin_scripts),
    ('resources/views/user/portal/tickets.blade.php', user_scripts),
]:
    p = Path(rel)
    s = p.read_text(encoding='utf-8')
    start = s.find("@push('scripts')")
    end = s.find('@endpush', start)
    if start == -1 or end == -1:
        raise SystemExit(f'block not found in {rel}')
    end = end + len('@endpush')
    s = s[:start] + new_block + s[end:]
    p.write_text(s, encoding='utf-8')
    print('ok', rel)
