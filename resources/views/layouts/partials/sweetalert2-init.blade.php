{{-- SweetAlert2 JS محلی + AdminSwal و flash پنل ادمین --}}
<script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>
<script>
    (function () {
        function rtlPopup() {
            var p = document.querySelector('.swal2-popup');
            if (p) {
                p.setAttribute('dir', 'rtl');
            }
        }

        window.AdminSwal = {
            fire: function (opts) {
                if (typeof Swal === 'undefined') {
                    return Promise.reject();
                }
                var base = {
                    confirmButtonText: 'باشه',
                    didOpen: rtlPopup,
                };
                return Swal.fire(Object.assign(base, opts || {}));
            },
            confirm: function (opts) {
                if (typeof Swal === 'undefined') {
                    return Promise.reject();
                }
                return Swal.fire(Object.assign({
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'بله',
                    cancelButtonText: 'خیر',
                    reverseButtons: true,
                    didOpen: rtlPopup,
                }, opts || {}));
            },
            success: function (message, title) {
                return this.fire({
                    icon: 'success',
                    title: title !== undefined ? title : '',
                    text: message,
                });
            },
            error: function (message, title) {
                return this.fire({
                    icon: 'error',
                    title: title !== undefined ? title : '',
                    text: message,
                });
            },
            info: function (message, title) {
                return this.fire({
                    icon: 'info',
                    title: title !== undefined ? title : '',
                    text: message,
                });
            },
            warning: function (message, title) {
                return this.fire({
                    icon: 'warning',
                    title: title !== undefined ? title : '',
                    text: message,
                });
            },
        };

        @if (session('flash_success'))
        function showFlashSuccess() {
            AdminSwal.success(@json(session('flash_success')));
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showFlashSuccess);
        } else {
            showFlashSuccess();
        }
        @endif

        @if (session('flash_error'))
        function showFlashError() {
            AdminSwal.error(@json(session('flash_error')));
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showFlashError);
        } else {
            showFlashError();
        }
        @endif
    })();
</script>
