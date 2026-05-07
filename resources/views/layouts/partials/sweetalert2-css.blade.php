{{-- SweetAlert2 CSS محلی — public/vendor/sweetalert2/sweetalert2.min.css --}}
<link rel="stylesheet" href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}">
<style>
    {{-- بالاتر از مودال‌های پنل (مثلاً ~۱۴۰۰) تا تأییدها دیده شوند --}}
    .swal2-container { z-index: 20000 !important; }
</style>
