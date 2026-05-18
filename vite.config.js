import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin-financial-ckeditor.js',
                'resources/js/admin-tickets-ckeditor.js',
                'resources/js/support-ticket-ui.js',
                'resources/js/admin-tickets-index.js',
                'resources/js/admin-customer-tickets-embed.js',
                'resources/js/user-tickets-portal.js',
                'resources/js/admin-dashboard-charts.js',
                'resources/js/admin-reports.js',
                'resources/js/admin-permission-tree.js',
                'resources/js/admin-login-backgrounds.js',
                'resources/js/admin-database-backup.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
