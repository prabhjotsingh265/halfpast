import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia({ ssr: 'resources/js/ssr.ts' }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        // Inside Docker the dev server has to listen on every interface.
        // The browser still reaches it, and hot reload, through localhost.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        // The app container asks Vite to render public pages at http://vite:5173.
        allowedHosts: ['vite'],
        watch: {
            ignored: ['**/vendor/**'],
        },
    },
});
