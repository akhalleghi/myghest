{{-- قبل از اولین رندر؛ جلوگیری از تکان تم --}}
<script>
(function () {
    try {
        var stored = localStorage.getItem('myghest-theme');
        if (stored === 'dark' || stored === 'light') {
            document.documentElement.setAttribute('data-theme', stored);

            return;
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) { /* تنظیمات مرورگر / حالت خصوصی */ }
})();
</script>
