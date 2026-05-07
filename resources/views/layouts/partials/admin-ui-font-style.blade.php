{{-- اندازه و خانوادهٔ فونت سامانه (تنظیمات ادمین) — برای هر صفحه با data روی <html> --}}
<style id="admin-ui-global-font">
    html[data-admin-font="small"] {
        font-size: 87.5%;
    }

    html[data-admin-font="large"] {
        font-size: 112.5%;
    }

    html[data-admin-ui-font="iransans"] body {
        font-family: IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
    }

    html[data-admin-ui-font="iranyekan"] body {
        font-family: IRANYekan, IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
    }

    html[data-admin-ui-font="anjoman"] body {
        font-family: Anjoman, IRANSans, system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
    }

    body input,
    body button,
    body textarea,
    body select {
        font-family: inherit;
    }
</style>
