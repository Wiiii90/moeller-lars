import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/admin/dialog-contract.css',
                'resources/css/admin/dashboard.css',
                'resources/css/admin/dashboard-extras.css',
                'resources/css/admin/gallery.css',
                'resources/css/admin/journal.css',
                'resources/css/admin/custom-page.css',
                'resources/css/admin/home.css',
                'resources/css/admin/general.css',
                'resources/css/admin/general-interactions.css',
                'resources/css/admin/table-contract.css',
                'resources/css/admin/dashboard-feed.css',
                'resources/css/admin/stage.css',
                'resources/css/admin/typography.css',
                'resources/css/public-content.css',
                'resources/css/public-presentation.css',
                'resources/css/public-layout-settings.css',
                'resources/css/custom-pages.css',
                'resources/js/app.js',
                'resources/js/admin-viz.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
