import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin.css',
                'resources/js/app.js',
                'resources/js/admin-viz.js',
                'resources/js/admin-password-tools.js',
                'resources/js/artist-rich-image.js',
                'resources/js/artist-rich-spacing.js',
            ],
            refresh: true,
        }),
    ],
    // Filament dynamically imports the Rich Text entry modules and expects
    // their default exports. Vite's client-build default (false) may remove
    // those exports even when the entry files themselves are emitted.
    build: {
        rolldownOptions: {
            preserveEntrySignatures: 'exports-only',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/.worktrees/**'],
        },
    },
});
