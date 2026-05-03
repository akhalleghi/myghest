<script>
document.addEventListener('DOMContentLoaded', function () {
    var btns = document.querySelectorAll('[data-myghest-theme-toggle]');

    if (! btns.length) return;

    function syncIconsOn(btn) {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';

        btn.querySelectorAll('[data-theme-icon]').forEach(function (el) {
            var kind = el.getAttribute('data-theme-icon');
            var show = dark ? kind === 'sun' : kind === 'moon';

            el.style.display = show ? 'inline-block' : 'none';
        });
    }

    function syncAllIcons() {
        btns.forEach(syncIconsOn);
    }

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cur = document.documentElement.getAttribute('data-theme') || 'light';
            var next = cur === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', next);

            try {
                localStorage.setItem('myghest-theme', next);
            } catch (e) { /* */ }

            syncAllIcons();
        });
    });

    syncAllIcons();
});
</script>
