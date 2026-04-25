import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            filename: 'sw.js',
            includeAssets: ['icons/icon-192.png', 'icons/icon-512.png'],
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,woff2}'],
                runtimeCaching: [
                    {
                        urlPattern: /^\/api\/teachers/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'teachers-api',
                            expiration: {
                                maxAgeSeconds: 300,
                            },
                        },
                    },
                    {
                        urlPattern: /^\/student\/dashboard/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'dashboard',
                        },
                    },
                ],
            },
        }),
    ],
    build: {
        rollupOptions: {
                output: {
                    manualChunks: {
                        vendor: ['vue', 'axios'],
                        charts: ['chart.js'],
                        video: ['twilio-video'],
                    },
                },
            },
        },
});
