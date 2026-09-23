import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins, loadEnv } from 'vite-plus';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: lazyPlugins(() => [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/passkeys.js',
                    'resources/css/filament/admin/theme.css',
                ],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                    bunny('Fraunces', {
                        weights: [600, 700],
                        styles: ['normal', 'italic'],
                    }),
                ],
            }),
            tailwindcss(),
        ]),
        server: {
            cors: true,
            host: env.VITE_HOST || undefined,
            hmr:
                env.VITE_HMR_HOST || env.VITE_HOST
                    ? { host: env.VITE_HMR_HOST || env.VITE_HOST }
                    : undefined,
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/storage/framework/views/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
