import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const dockerDev = process.env.MOELLER_LARS_VITE_DOCKER === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin.css',
                'resources/js/app.js',
                'resources/js/admin-viz.js',
                'resources/js/admin-password-tools.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: dockerDev ? '0.0.0.0' : '127.0.0.1',
        port: 5173,
        strictPort: true,
        origin: dockerDev ? 'http://127.0.0.1:5173' : undefined,
        hmr: dockerDev ? {
            host: '127.0.0.1',
            clientPort: 5173,
        } : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**', '**/.worktrees/**'],
        },
    },
});
