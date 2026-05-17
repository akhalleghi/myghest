<div id="login-blocks-overlay" class="login-blocks-overlay" hidden aria-hidden="true">
    <div class="login-blocks-modal" role="dialog" aria-modal="true" aria-labelledby="login-blocks-title">
        <div class="login-blocks-head">
            <div>
                <h3 id="login-blocks-title" class="login-blocks-title">مدیریت مسدودی‌های ورود</h3>
                <p class="login-blocks-subtitle" id="login-blocks-subtitle">کاربرانی که به‌دلیل تلاش‌های ناموفق بیش از حد مجاز مسدود شده‌اند.</p>
            </div>
            <button type="button" class="app-settings-close" id="login-blocks-close" aria-label="بستن">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="login-blocks-body">
            <p class="login-blocks-msg" id="login-blocks-msg" role="status" hidden></p>
            <div class="login-blocks-table-wrap">
                <table class="login-blocks-table" id="login-blocks-table">
                    <thead>
                        <tr>
                            <th scope="col">نام کاربری</th>
                            <th scope="col">IP</th>
                            <th scope="col">تلاش ناموفق</th>
                            <th scope="col">زمان مسدودیت</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="login-blocks-tbody">
                        <tr class="login-blocks-empty">
                            <td colspan="5">در حال بارگذاری…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
