import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/admin/gallery.css',
                'resources/css/admin/journal.css',
                'resources/css/admin/custom-page.css',
                'resources/css/admin/home.css',
                'resources/css/public-content.css',
                'resources/css/public-presentation.css',
                'resources/css/custom-pages.css',
                'resources/js/app.js',
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