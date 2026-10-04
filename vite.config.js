import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/public-content.css',
                'resources/css/public-presentation.css',
                'resources/css/public-layout-settings.css',
                'resources/css/custom-pages.css',
                'resources/js/app.js',
                'resources/js/admin-viz.js',
                'resources/js/admin-password-tools.js',
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
