import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/panels/platform-dashboard.js',
                'resources/js/panels/api-monitoring.js',
                'resources/js/panels/queue-ops.js',
                'resources/js/panels/risk-reconciliation.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
});
